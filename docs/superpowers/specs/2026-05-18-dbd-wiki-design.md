# DBD Wiki / Glossary (`!wiki`) — Design

- **Date:** 2026-05-18
- **Status:** Approved (design); pending implementation plan
- **Channel context:** Botunal — Kick chat-bot admin panel for the TROLUNAL channel (Dead by Daylight)

## Problem

The streamer plays Dead by Daylight (DBD). Chat needs a `!wiki <term>` command that acts as a
bilingual (Turkish + English) DBD glossary: perks, killers, survivors, killer powers, add-ons,
and general game terms. Lookups must work by either the English in-game name or a Turkish
community nickname/alias, be tolerant of Turkish diacritics, suggest near matches when nothing
is found, and be fully editable by an admin from the existing Kick panel.

## Key Constraints & Decisions (from brainstorming)

1. **No official Turkish localization exists in DBD.** Turkish names (e.g. "Kum Torbası" for
   *Dead Hard*) are **community/streamer slang**, not in-game strings. There is no authoritative
   Turkish data source. Turkish names and descriptions are therefore **best-effort seeded and
   admin-curated**.
2. **Knowledge-cutoff vs currency:** the assistant's training cutoff predates 2026-05-18. To be
   "current as of today", the seed datasets are fetched from the **live official DBD wiki** at
   implementation time. English names/descriptions/owners come from the wiki; Turkish is
   generated best-effort.
3. **Scope:** everything — all killers, survivors, perks, killer powers, **add-ons**, plus
   general game terms.
4. **Description language in chat:** **Turkish** (translated/summarised from the English source
   at seed time, admin-correctable). English source text is stored for admin reference only.
5. **Not-found behaviour:** list the **nearest 3 suggestions** (names only); if nothing is close,
   show usage.
6. **Delivery strategy (Approach B):** ship the complete system now; data delivered via an
   idempotent `dbd:sync-wiki` command reading bundled JSON datasets. **Tier 1** (perks, powers,
   characters, terms — the high-frequency ~400 entries) delivered first and complete; **Tier 2**
   (add-ons, ~760+) in a second pass. Re-runnable for future DBD chapters without clobbering
   admin-curated content.

Accuracy expectation: with ~1200–1500 entries, seeded Turkish names/descriptions are
**best-effort and admin-curated**. The system is designed so re-running sync never overwrites
curated entries.

## Architecture

Follows existing codebase conventions exactly: DB-driven dynamic command (like `uptime`,
`shoutout`) via the `BuiltInCommand` interface + `BuiltInCommandRegistry`; admin CRUD mirrors
`CommandController` + `Commands.svelte`; models use `#[Fillable([...])]` + `casts()`; Pest
feature tests in `tests/Feature/Kick/`.

### 1. Data model

**Table `wiki_entries`** (model `App\Models\WikiEntry`)

| column | type | notes |
|---|---|---|
| `id` | id | |
| `type` | string | `killer \| survivor \| perk \| power \| addon \| term` |
| `name_en` | string | canonical English in-game name (e.g. "Dead Hard") |
| `name_tr` | string nullable | Turkish community nickname (e.g. "Kum Torbası"); best-effort, admin-curated |
| `slug` | string unique | normalized stable identity, derived from `name_en` (+ type/owner disambiguation if needed) |
| `owner` | string nullable | character this belongs to: perk → survivor/killer name; power/addon → killer name; null for `term`, `killer`, `survivor` |
| `role` | string nullable | `survivor \| killer` — drives the "Kurban"/"Katil" label; null for `term` |
| `description_tr` | text nullable | shown in chat |
| `description_en` | text nullable | original English; admin reference only, never shown in chat |
| `is_enabled` | boolean default true | hide from `!wiki` without deleting |
| `is_curated` | boolean default false | set true on any admin edit; sync command will not overwrite name/description of curated rows |
| `source_url` | string nullable | wiki page it came from (admin reference) |
| timestamps | | |

Indexes: `slug` unique, `type`, `name_en`, `owner`.

**Table `wiki_aliases`** (model `App\Models\WikiAlias`)

| column | type | notes |
|---|---|---|
| `id` | id | |
| `wiki_entry_id` | foreignId | constrained, cascade on delete |
| `alias` | string | raw alias as displayed/entered |
| `alias_norm` | string | normalized form used for lookup; indexed |
| timestamps | | |

Constraints: `unique(wiki_entry_id, alias_norm)`; index on `alias_norm`.

**Normalization** — new `App\Support\WikiText::normalize(string): string`:
trim → Turkish-safe lowercase → fold Turkish diacritics (`ı/İ→i`, `ş/Ş→s`, `ç/Ç→c`, `ğ/Ğ→g`,
`ö/Ö→o`, `ü/Ü→u`) → collapse internal whitespace → strip punctuation (keep spaces). Used for
**both** `slug`/`alias_norm` generation **and** query-time term normalization, so
`!wiki kum torbası` == `!wiki kum torbasi` == `!wiki KUM TORBASI`. Unit-tested.

Models: `#[Fillable([...])]` + `casts()`. `WikiEntry::aliases()` hasMany; `scopeEnabled()`;
a `resolveByTerm(string $normalized)` query matching `slug` OR normalized `name_en` OR
normalized `name_tr` OR `wiki_aliases.alias_norm`, `enabled()` only. Factories
`WikiEntryFactory`, `WikiAliasFactory`.

### 2. `!wiki` command behaviour

`App\Services\Kick\BuiltIns\WikiCommand implements BuiltInCommand`, handler key `wiki`,
registered in `BuiltInCommandRegistry::map()`. The `dbd:sync-wiki` command idempotently
ensures a `Command` row exists (`name=wiki`, `type=dynamic`, `handler=wiki`,
`permission=everyone`, default cooldowns matching existing commands, enabled) via
`firstOrCreate` so the admin can still adjust cooldown/permission afterwards.

`handle(CommandContext $ctx): string`:

- `term = trim(implode(' ', $ctx->args))`.
- **Empty (`!wiki`)** → usage:
  `Kullanım: !wiki <terim>. Örn: !wiki dead hard. DBD perk/killer/survivor/add-on sözlüğü (TR+EN).`
- **Exact match** (normalized; slug / name_en / name_tr / alias_norm; `enabled` only) → format:
  - perk: `Kum Torbası (Dead Hard) — Kurban: David King · Açıklama: <description_tr>`
  - power: `<TR> (<EN>) — Katil: The Nurse [Güç] · Açıklama: …`
  - addon: `<TR> (<EN>) — Add-on: The Nurse · Açıklama: …`
  - character: `<TR> (<EN>) — <Katil|Kurban> · Açıklama: …`
  - term: `<TR/EN> — Terim · Açıklama: …`
  - Label rule: `role==survivor` → "Kurban", `role==killer` → "Katil".
  - If `name_tr` is null → show only EN.
  - Whole message capped at **≤480 chars** (`SendChatMessageJob`/API limit is 500); only the
    **description** is truncated with `…`; name/character are never cut.
- **Not found, near matches exist** → top 3 distinct entries by Levenshtein over the normalized
  name/alias pool (with a `str_contains` boost), within a length-relative distance threshold:
  `'<terim>' bulunamadı. Şunları deneyin: Kum Torbası, Dead Hard, Dead Man's Switch`
  (prefer `name_tr`, else `name_en`).
- **Not found, nothing close** → `'<terim>' bulunamadı. Kullanım: !wiki <terim>`.

The returned string flows through the existing pipeline: queued via `SendChatMessageJob`,
logged to `command_logs` automatically (no separate logging). Fuzzy matching runs **only on a
miss**; the candidate pool (normalized id/name/alias triples, ~1500 short strings) is held in
`Cache::remember('wiki:index', …)`, invalidated on any admin write, so the Levenshtein sweep is
an in-memory pass over small strings.

### 3. Admin panel (CRUD)

Mirrors `CommandController` + `Commands.svelte`.

- Routes (`routes/kick.php`, `['auth','verified']` group):
  - `GET  /kick/wiki` → `WikiController@index` (`kick.wiki.index`)
  - `POST /kick/wiki` → `store` (`kick.wiki.store`)
  - `PUT  /kick/wiki/{wikiEntry}` → `update` (`kick.wiki.update`)
  - `DELETE /kick/wiki/{wikiEntry}` → `destroy` (`kick.wiki.destroy`)
- `WikiController`:
  - `index`: search (normalized, over name_en / name_tr / alias / owner) + `type` filter;
    `paginate(50)->withQueryString()`; renders `kick/Wiki`.
  - `store`/`update`: validated via new `App\Concerns\WikiValidationRules` concern
    (`type` in allowed set; `name_en` required; `name_tr`/`description_tr`/`description_en`/
    `owner`/`source_url` nullable; `role` in `survivor|killer|null`; `is_enabled` boolean;
    `aliases` array of strings, `distinct`, normalized + deduped server-side).
    `update` sets `is_curated = true`.
  - `destroy`: deletes entry (aliases cascade).
- `resources/js/pages/kick/Wiki.svelte`: filter card (search input + type select), table
  (Tür / TR adı / EN adı / Sahip / Alias adedi / Durum), create/edit form following whatever
  pattern `Commands.svelte` uses (modal or inline), alias list editor, enable/disable toggle,
  delete with confirm. Nav item "Wiki" added to `resources/js/layouts/kick/Layout.svelte`
  (+ Wayfinder import, generated at build). Types `WikiEntryRow` / `WikiEntryDetail` added to
  `resources/js/types/kick.ts`.

### 4. Data sourcing & seeding (Approach B)

- `php artisan dbd:sync-wiki [--tier=1|2|all] [--fresh]` — new console command in
  `app/Console/Commands/Kick/`, idempotent, progress bar in the `SyncSubscriptionsCommand`
  style.
- Reads repo-committed datasets: `database/data/dbd/tier1.json` (perks, powers, characters,
  terms) and `database/data/dbd/tier2.json` (add-ons). Each record:
  `{ type, name_en, name_tr, owner, role, description_tr, description_en, source_url, aliases[] }`.
- Upsert keyed by `slug`:
  - New entries created; new auto-aliases added; **admin-added aliases never deleted**.
  - Rows with `is_curated = true` keep their `name_*`/`description_*` untouched (only missing
    aliases/new entries are added). `is_curated = false` rows have name/description refreshed
    from the dataset. → safe to re-run for future chapters.
  - `--fresh` truncates and re-imports (dev convenience only).
- The JSON datasets are produced during implementation by fetching the **live official DBD
  wiki** (current as of the build date): English name/description/owner scraped, Turkish
  name/description generated best-effort, `source_url` recorded. **Tier 1 first** (complete &
  reviewable); **Tier 2 (add-ons) in a second pass**.
- `WikiSeeder` calls the command for local/dev convenience. Tests use a small fixture JSON, not
  the large datasets.

### 5. Testing (Pest, `tests/Feature/Kick/`)

- `WikiCommandTest`: empty → usage; exact EN / TR / alias hit; Turkish-diacritic-insensitive
  hit (`kum torbasi` == `kum torbası`); disabled entry not returned; not-found → exactly 3
  suggestions; not-found nothing close → usage; answer format per type (perk shows Kurban/Katil
  + both names); ≤500-char enforced for long descriptions; end-to-end through the dispatcher
  → `SendChatMessageJob` queued + `command_logs` row (reuse existing dispatcher test helpers).
- `WikiTextTest` (unit): normalization folds Turkish diacritics, collapses whitespace, strips
  punctuation.
- `WikiPanelTest`: index requires auth; list + `type` filter + search matches TR / EN / alias /
  owner; paginate 50; store creates entry + normalized deduped aliases; update edits and sets
  `is_curated`; destroy cascades aliases; validation errors surfaced.
- `DbdSyncWikiTest`: seeds from fixture JSON; idempotent (2× → identical counts); does not
  overwrite `is_curated` rows' name/description but still adds new aliases/entries; `--tier`
  filters correctly.

## Build Order

1. Migrations + models + factories + `WikiText` normalizer + `WikiTextTest`.
2. `WikiCommand` + `BuiltInCommandRegistry` wiring + `Command` row bootstrap; `WikiCommandTest`
   green.
3. `WikiController` + routes + `WikiValidationRules`; `npm run build` (Wayfinder) →
   `Wiki.svelte` + nav + types; `WikiPanelTest` green.
4. `dbd:sync-wiki` command + Tier 1 dataset (fetched from live wiki) + `WikiSeeder`;
   `DbdSyncWikiTest` green.
5. Tier 2 dataset (add-ons), second pass.
6. `vendor/bin/pint --dirty --format agent`, full `php artisan test --compact`, `npm run build`.

## Risks & Notes

- **Turkish data accuracy:** best-effort at seed time; `is_curated` + non-destructive sync make
  admin curation the source of truth over time. This is by design, not a defect.
- **Volume (~1200–1500 rows):** lookups are indexed exact-match queries; fuzzy only on miss over
  a cached in-memory pool — performance is fine. Tier 2 (add-ons) is the largest, lowest-value
  slice and is deliberately deferred to a second pass.
- **Wiki scraping fragility:** scraping happens once at implementation time to produce static
  JSON committed to the repo; the runtime never scrapes. Future updates = regenerate datasets +
  re-run idempotent command.
- **500-char chat limit:** descriptions can be long (esp. add-ons); only the description is
  truncated, never names/character, so the answer stays meaningful.
- **Wayfinder:** `kick.wiki.*` routes must exist before the Svelte page build (route TS is
  generated at build start).
- **Commits/deploys:** the user controls all commits/pushes/deploys; commit/push triggers the
  production pipeline. The agent never commits — including this spec doc, which is left on disk
  for the user to commit.
