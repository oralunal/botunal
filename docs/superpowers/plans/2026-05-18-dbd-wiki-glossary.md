# DBD `!wiki` Glossary Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a bilingual (Turkish + English) Dead by Daylight glossary: a `!wiki <term>` chat command backed by an admin-managed `wiki_entries` registry, seeded from the live DBD wiki via an idempotent console command.

**Architecture:** A DB-driven dynamic command (`WikiCommand` implementing the existing `BuiltInCommand` contract, registered in `BuiltInCommandRegistry`) delegates all lookup/format/fuzzy logic to a single-responsibility `WikiLookup` service. Two tables (`wiki_entries`, `wiki_aliases`) follow the existing `Command`/`CommandAlias` conventions. Admin CRUD mirrors `CommandController` + paginated index. Data is delivered by `php artisan dbd:sync-wiki` reading committed JSON datasets, tiered (Tier 1 = perks/powers/characters/terms first; Tier 2 = add-ons second), idempotent and non-destructive to admin-curated rows.

**Tech Stack:** Laravel 13, PHP 8.3, Inertia v3 + Svelte 5 runes, Tailwind v4, Laravel Wayfinder, Pest 4, MariaDB (prod) / SQLite (test).

---

## ⚠️ Operating Constraints (read first)

- **The agent never commits or pushes.** The user controls all commits/pushes/deploys (commit/push triggers the production pipeline). Each task ends with a "Stage & hand off" step: run `git add` for the exact paths, then **stop and tell the user** the task is complete and staged so they can review and commit. Do **not** run `git commit`. Do **not** run `git push` ever.
- **Pint:** after any PHP change, run `vendor/bin/pint --dirty --format agent` before handing off.
- **Tests:** `php artisan test --compact` (filter to the file under work for speed).
- **Frontend:** Wayfinder route TS (`@/routes/kick/wiki`) is generated at build start. Routes must exist before building the Svelte page. Build with `npm run build`.
- **Data accuracy:** seeded Turkish names/descriptions are best-effort and admin-curated by design; `is_curated` protects them from re-sync. This is not a defect.
- Spec: `docs/superpowers/specs/2026-05-18-dbd-wiki-design.md`.

---

## File Structure

**New (PHP):**
- `database/migrations/2026_05_18_000001_create_wiki_entries_table.php`
- `database/migrations/2026_05_18_000002_create_wiki_aliases_table.php`
- `app/Services/Kick/WikiText.php` — pure normalization helper (static)
- `app/Models/WikiEntry.php`, `app/Models/WikiAlias.php`
- `database/factories/WikiEntryFactory.php`, `database/factories/WikiAliasFactory.php`
- `app/Services/Kick/WikiLookup.php` — resolve + format + suggest + usage; owns the fuzzy cache
- `app/Services/Kick/BuiltIns/WikiCommand.php` — thin `BuiltInCommand` adapter
- `app/Concerns/WikiValidationRules.php`
- `app/Http/Requests/Kick/WikiStoreRequest.php`, `app/Http/Requests/Kick/WikiUpdateRequest.php`
- `app/Http/Controllers/Kick/WikiController.php`
- `app/Console/Commands/Kick/SyncDbdWikiCommand.php` — `dbd:sync-wiki`
- `database/seeders/WikiSeeder.php`
- `database/seeders/data/dbd/tier1.json`, `database/seeders/data/dbd/tier2.json` — real datasets
- `resources/js/pages/kick/Wiki.svelte`

**New (tests):**
- `tests/Feature/Kick/WikiTextTest.php`
- `tests/Feature/Kick/WikiLookupTest.php`
- `tests/Feature/Kick/WikiCommandTest.php`
- `tests/Feature/Kick/WikiPanelTest.php`
- `tests/Feature/Kick/DbdSyncWikiTest.php`

**Modified:**
- `app/Services/Kick/BuiltInCommandRegistry.php` — add `'wiki' => WikiCommand::class`
- `routes/kick.php` — import + 4 routes
- `resources/js/layouts/kick/Layout.svelte` — nav item + Wayfinder import
- `resources/js/types/kick.ts` — `WikiEntryRow`, `WikiAliasRow`

---

## Task 1: Database schema + normalization helper

**Files:**
- Create: `database/migrations/2026_05_18_000001_create_wiki_entries_table.php`
- Create: `database/migrations/2026_05_18_000002_create_wiki_aliases_table.php`
- Create: `app/Services/Kick/WikiText.php`
- Test: `tests/Feature/Kick/WikiTextTest.php`

- [ ] **Step 1: Write the failing normalization test**

Create `tests/Feature/Kick/WikiTextTest.php`:

```php
<?php

use App\Services\Kick\WikiText;

test('normalize folds Turkish diacritics and casing', function () {
    expect(WikiText::normalize('Kum Torbası'))->toBe('kum torbasi')
        ->and(WikiText::normalize('KUM TORBASI'))->toBe('kum torbasi')
        ->and(WikiText::normalize('İŞÇĞÖÜ'))->toBe('iscgou')
        ->and(WikiText::normalize('Dead Hard'))->toBe('dead hard');
});

test('normalize collapses whitespace and strips punctuation', function () {
    expect(WikiText::normalize("  Dead   Man's  Switch!! "))->toBe('dead mans switch')
        ->and(WikiText::normalize('Hex: No One Escapes Death'))->toBe('hex no one escapes death')
        ->and(WikiText::normalize(''))->toBe('');
});

test('slug joins type owner and name deterministically', function () {
    expect(WikiText::slug('perk', 'David King', 'Dead Hard'))
        ->toBe('perk david king dead hard')
        ->and(WikiText::slug('term', null, 'Gen Rush'))->toBe('term gen rush');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact tests/Feature/Kick/WikiTextTest.php`
Expected: FAIL — `Class "App\Services\Kick\WikiText" not found`.

- [ ] **Step 3: Implement `WikiText`**

Create `app/Services/Kick/WikiText.php`:

```php
<?php

namespace App\Services\Kick;

/**
 * Pure text normalization for the DBD wiki: Turkish-fold + lowercase so
 * "Kum Torbası", "kum torbasi" and "KUM TORBASI" all resolve identically.
 */
class WikiText
{
    private const TR_MAP = [
        'ı' => 'i', 'İ' => 'i', 'i' => 'i', 'I' => 'i',
        'ş' => 's', 'Ş' => 's', 'ç' => 'c', 'Ç' => 'c',
        'ğ' => 'g', 'Ğ' => 'g', 'ö' => 'o', 'Ö' => 'o',
        'ü' => 'u', 'Ü' => 'u',
    ];

    public static function normalize(string $value): string
    {
        $value = strtr($value, self::TR_MAP);
        $value = mb_strtolower($value, 'UTF-8');
        // Keep letters, digits and spaces only; everything else becomes a space.
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    public static function slug(string $type, ?string $owner, string $nameEn): string
    {
        return self::normalize($type.' '.($owner ?? '').' '.$nameEn);
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --compact tests/Feature/Kick/WikiTextTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Create the migrations**

Create `database/migrations/2026_05_18_000001_create_wiki_entries_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // killer|survivor|perk|power|addon|term
            $table->string('name_en');
            $table->string('name_tr')->nullable();
            $table->string('slug', 191)->unique();
            $table->string('owner')->nullable()->index(); // character this belongs to
            $table->string('role')->nullable(); // survivor|killer (label) | null
            $table->text('description_tr')->nullable();
            $table->text('description_en')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('is_curated')->default(false);
            $table->string('source_url')->nullable();
            $table->timestamps();

            $table->index('name_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_entries');
    }
};
```

Create `database/migrations/2026_05_18_000002_create_wiki_aliases_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wiki_entry_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->string('alias_norm')->index();
            $table->timestamps();

            $table->unique(['wiki_entry_id', 'alias_norm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_aliases');
    }
};
```

- [ ] **Step 6: Run the migration and full file test**

Run: `php artisan migrate` then `php artisan test --compact tests/Feature/Kick/WikiTextTest.php`
Expected: migration creates `wiki_entries` and `wiki_aliases`; tests PASS.

- [ ] **Step 7: Pint + stage & hand off**

Run: `vendor/bin/pint --dirty --format agent`
Run: `git add app/Services/Kick/WikiText.php database/migrations/2026_05_18_000001_create_wiki_entries_table.php database/migrations/2026_05_18_000002_create_wiki_aliases_table.php tests/Feature/Kick/WikiTextTest.php`
Then STOP and tell the user: "Task 1 complete & staged (schema + WikiText normalizer). Review & commit when ready." Do not commit.

---

## Task 2: Models + factories

**Files:**
- Create: `app/Models/WikiEntry.php`, `app/Models/WikiAlias.php`
- Create: `database/factories/WikiEntryFactory.php`, `database/factories/WikiAliasFactory.php`
- Test: `tests/Feature/Kick/WikiLookupTest.php` (model-relations portion only in this task)

- [ ] **Step 1: Write the failing model test**

Create `tests/Feature/Kick/WikiLookupTest.php`:

```php
<?php

use App\Models\WikiEntry;

test('a wiki entry has aliases and an enabled scope', function () {
    $entry = WikiEntry::factory()->create(['type' => 'perk', 'name_en' => 'Dead Hard']);
    $entry->aliases()->create(['alias' => 'DH', 'alias_norm' => 'dh']);
    WikiEntry::factory()->create(['name_en' => 'Hidden', 'is_enabled' => false]);

    expect($entry->aliases)->toHaveCount(1)
        ->and($entry->aliases->first()->alias)->toBe('DH')
        ->and(WikiEntry::enabled()->count())->toBe(1);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact tests/Feature/Kick/WikiLookupTest.php`
Expected: FAIL — `Class "App\Models\WikiEntry" not found`.

- [ ] **Step 3: Implement the models**

Create `app/Models/WikiEntry.php`:

```php
<?php

namespace App\Models;

use Database\Factories\WikiEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type', 'name_en', 'name_tr', 'slug', 'owner', 'role',
    'description_tr', 'description_en', 'is_enabled', 'is_curated', 'source_url',
])]
class WikiEntry extends Model
{
    /** @use HasFactory<WikiEntryFactory> */
    use HasFactory;

    public const TYPE_KILLER = 'killer';

    public const TYPE_SURVIVOR = 'survivor';

    public const TYPE_PERK = 'perk';

    public const TYPE_POWER = 'power';

    public const TYPE_ADDON = 'addon';

    public const TYPE_TERM = 'term';

    public const TYPES = [
        self::TYPE_KILLER, self::TYPE_SURVIVOR, self::TYPE_PERK,
        self::TYPE_POWER, self::TYPE_ADDON, self::TYPE_TERM,
    ];

    public const ROLE_SURVIVOR = 'survivor';

    public const ROLE_KILLER = 'killer';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_curated' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WikiAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(WikiAlias::class);
    }

    /**
     * @param  Builder<WikiEntry>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    public function displayName(): string
    {
        return $this->name_tr ?: $this->name_en;
    }
}
```

Create `app/Models/WikiAlias.php`:

```php
<?php

namespace App\Models;

use Database\Factories\WikiAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['wiki_entry_id', 'alias', 'alias_norm'])]
class WikiAlias extends Model
{
    /** @use HasFactory<WikiAliasFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<WikiEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(WikiEntry::class, 'wiki_entry_id');
    }
}
```

- [ ] **Step 4: Implement the factories**

Create `database/factories/WikiEntryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\WikiEntry;
use App\Services\Kick\WikiText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WikiEntry>
 */
class WikiEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameEn = $this->faker->unique()->words(2, true);

        return [
            'type' => WikiEntry::TYPE_PERK,
            'name_en' => ucwords($nameEn),
            'name_tr' => null,
            'slug' => WikiText::slug(WikiEntry::TYPE_PERK, null, $nameEn),
            'owner' => null,
            'role' => null,
            'description_tr' => $this->faker->sentence(),
            'description_en' => $this->faker->sentence(),
            'is_enabled' => true,
            'is_curated' => false,
            'source_url' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }

    public function curated(): static
    {
        return $this->state(fn () => ['is_curated' => true]);
    }

    public function perk(string $owner, string $role): static
    {
        return $this->state(fn () => [
            'type' => WikiEntry::TYPE_PERK,
            'owner' => $owner,
            'role' => $role,
        ]);
    }
}
```

Create `database/factories/WikiAliasFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\WikiAlias;
use App\Models\WikiEntry;
use App\Services\Kick\WikiText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WikiAlias>
 */
class WikiAliasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $alias = $this->faker->unique()->word();

        return [
            'wiki_entry_id' => WikiEntry::factory(),
            'alias' => $alias,
            'alias_norm' => WikiText::normalize($alias),
        ];
    }
}
```

- [ ] **Step 5: Run it to verify it passes**

Run: `php artisan test --compact tests/Feature/Kick/WikiLookupTest.php`
Expected: PASS (1 test).

- [ ] **Step 6: Pint + stage & hand off**

Run: `vendor/bin/pint --dirty --format agent`
Run: `git add app/Models/WikiEntry.php app/Models/WikiAlias.php database/factories/WikiEntryFactory.php database/factories/WikiAliasFactory.php tests/Feature/Kick/WikiLookupTest.php`
STOP and tell the user: "Task 2 complete & staged (models + factories)." Do not commit.

---

## Task 3: `WikiLookup` service (resolve + format + suggest + usage)

**Files:**
- Create: `app/Services/Kick/WikiLookup.php`
- Test: `tests/Feature/Kick/WikiLookupTest.php` (extend)

- [ ] **Step 1: Add failing behavior tests**

Append to `tests/Feature/Kick/WikiLookupTest.php`:

```php
use App\Models\WikiAlias;
use App\Services\Kick\WikiLookup;
use Illuminate\Support\Facades\Cache;

function lookup(): WikiLookup
{
    return app(WikiLookup::class);
}

function seedDeadHard(): WikiEntry
{
    $entry = WikiEntry::factory()->perk('David King', 'survivor')->create([
        'name_en' => 'Dead Hard',
        'name_tr' => 'Kum Torbası',
        'slug' => 'perk david king dead hard',
        'description_tr' => 'Yaralıyken bir saniye boyunca dayanıklılık kazanırsın.',
    ]);
    foreach (['Dead Hard', 'Kum Torbası', 'DH'] as $a) {
        $entry->aliases()->create(['alias' => $a, 'alias_norm' => \App\Services\Kick\WikiText::normalize($a)]);
    }

    return $entry;
}

test('empty term returns usage', function () {
    expect(lookup()->answer(''))->toContain('Kullanım: !wiki');
});

test('exact English, Turkish and alias hits resolve and format a perk', function () {
    seedDeadHard();

    $expected = 'Kum Torbası (Dead Hard) — Kurban: David King · Açıklama: Yaralıyken bir saniye boyunca dayanıklılık kazanırsın.';

    expect(lookup()->answer('dead hard'))->toBe($expected)
        ->and(lookup()->answer('KUM TORBASI'))->toBe($expected)
        ->and(lookup()->answer('kum torbasi'))->toBe($expected)
        ->and(lookup()->answer('dh'))->toBe($expected);
});

test('disabled entries are not returned', function () {
    $e = seedDeadHard();
    $e->update(['is_enabled' => false]);
    Cache::forget('wiki:index');

    expect(lookup()->answer('dead hard'))->toContain('bulunamadı');
});

test('power and term formats differ', function () {
    WikiEntry::factory()->create([
        'type' => 'power', 'name_en' => 'Blink', 'name_tr' => 'Işınlanma',
        'owner' => 'The Nurse', 'role' => 'killer', 'slug' => 'power the nurse blink',
        'description_tr' => 'Kısa mesafe ışınlanır.',
    ]);
    WikiEntry::factory()->create([
        'type' => 'term', 'name_en' => 'Gen Rush', 'name_tr' => null,
        'owner' => null, 'role' => null, 'slug' => 'term gen rush',
        'description_tr' => 'Jeneratörleri çok hızlı tamamlama.',
    ]);

    expect(lookup()->answer('Blink'))
        ->toBe('Işınlanma (Blink) — Katil: The Nurse [Güç] · Açıklama: Kısa mesafe ışınlanır.')
        ->and(lookup()->answer('gen rush'))
        ->toBe('Gen Rush — Terim · Açıklama: Jeneratörleri çok hızlı tamamlama.');
});

test('not found with near matches lists exactly three names', function () {
    seedDeadHard();
    WikiEntry::factory()->create(['name_en' => 'Dead Mans Switch', 'name_tr' => null, 'slug' => 'perk x dead mans switch']);
    WikiEntry::factory()->create(['name_en' => 'Deadlock', 'name_tr' => null, 'slug' => 'perk x deadlock']);
    WikiEntry::factory()->create(['name_en' => 'Decisive Strike', 'name_tr' => null, 'slug' => 'perk x decisive strike']);
    Cache::forget('wiki:index');

    $answer = lookup()->answer('deadh');

    expect($answer)->toContain('bulunamadı')
        ->and($answer)->toContain('Şunları deneyin:');
    expect(substr_count($answer, ','))->toBe(2); // exactly 3 suggestions
});

test('not found with nothing close returns usage', function () {
    seedDeadHard();
    Cache::forget('wiki:index');

    expect(lookup()->answer('zzzzzzzzplugh'))
        ->toContain('bulunamadı')
        ->toContain('Kullanım: !wiki');
});

test('long descriptions are truncated to keep the message <= 480 chars', function () {
    WikiEntry::factory()->create([
        'type' => 'perk', 'name_en' => 'Verbose', 'name_tr' => 'Uzun',
        'owner' => 'Someone', 'role' => 'survivor', 'slug' => 'perk someone verbose',
        'description_tr' => str_repeat('uzun açıklama ', 80),
    ]);

    $answer = lookup()->answer('Verbose');

    expect(mb_strlen($answer))->toBeLessThanOrEqual(480)
        ->and($answer)->toContain('Uzun (Verbose) — Kurban: Someone')
        ->and($answer)->toEndWith('…');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact tests/Feature/Kick/WikiLookupTest.php`
Expected: FAIL — `Class "App\Services\Kick\WikiLookup" not found`.

- [ ] **Step 3: Implement `WikiLookup`**

Create `app/Services/Kick/WikiLookup.php`:

```php
<?php

namespace App\Services\Kick;

use App\Models\WikiEntry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * All `!wiki` behavior: exact resolution, answer formatting, fuzzy
 * suggestions and the usage string. Kept separate from the command so it
 * is testable without the dispatcher.
 */
class WikiLookup
{
    private const MAX_LEN = 480;

    private const CACHE_KEY = 'wiki:index';

    public function answer(string $term): string
    {
        $term = trim($term);

        if ($term === '') {
            return $this->usage();
        }

        $entry = $this->resolve($term);

        if ($entry !== null) {
            return $this->format($entry);
        }

        $suggestions = $this->suggest($term);

        if ($suggestions === []) {
            return "'{$term}' bulunamadı. ".$this->usage();
        }

        return "'{$term}' bulunamadı. Şunları deneyin: ".implode(', ', $suggestions);
    }

    public function usage(): string
    {
        return 'Kullanım: !wiki <terim>. Örn: !wiki dead hard · DBD perk/killer/survivor/add-on sözlüğü (TR+EN).';
    }

    public function resolve(string $term): ?WikiEntry
    {
        $norm = WikiText::normalize($term);

        if ($norm === '') {
            return null;
        }

        return WikiEntry::query()
            ->enabled()
            ->where(function ($q) use ($norm): void {
                $q->where('slug', $norm)
                    ->orWhereRaw('LOWER(name_en) = ?', [$norm])
                    ->orWhereRaw('LOWER(name_tr) = ?', [$norm])
                    ->orWhereHas('aliases', fn ($a) => $a->where('alias_norm', $norm));
            })
            ->orderBy('id')
            ->first()
            ?? $this->resolveByNormalizedName($norm);
    }

    /**
     * Fallback: names may contain punctuation/casing the column comparison
     * above misses (e.g. "Dead Man's Switch"); compare the normalized form.
     */
    private function resolveByNormalizedName(string $norm): ?WikiEntry
    {
        foreach ($this->index() as $row) {
            if (in_array($norm, $row['keys'], true)) {
                return WikiEntry::find($row['id']);
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function suggest(string $term, int $limit = 3): array
    {
        $norm = WikiText::normalize($term);
        $scored = [];

        foreach ($this->index() as $row) {
            $best = PHP_INT_MAX;
            foreach ($row['keys'] as $key) {
                if ($key === '') {
                    continue;
                }
                $distance = levenshtein($norm, $key);
                if (str_contains($key, $norm) || str_contains($norm, $key)) {
                    $distance = (int) min($distance, 1);
                }
                $best = min($best, $distance);
            }
            $scored[$row['id']] = ['d' => $best, 'name' => $row['display']];
        }

        $threshold = max(2, (int) floor(mb_strlen($norm) * 0.6));

        $scored = array_filter($scored, fn ($s) => $s['d'] <= $threshold);
        uasort($scored, fn ($a, $b) => $a['d'] <=> $b['d']);

        return array_slice(array_values(array_map(fn ($s) => $s['name'], $scored)), 0, $limit);
    }

    private function format(WikiEntry $e): string
    {
        $head = $e->name_tr ? "{$e->name_tr} ({$e->name_en})" : $e->name_en;

        $context = match ($e->type) {
            WikiEntry::TYPE_PERK => $this->roleLabel($e->role).': '.$e->owner,
            WikiEntry::TYPE_POWER => 'Katil: '.$e->owner.' [Güç]',
            WikiEntry::TYPE_ADDON => 'Add-on: '.$e->owner,
            WikiEntry::TYPE_KILLER => 'Katil',
            WikiEntry::TYPE_SURVIVOR => 'Kurban',
            default => 'Terim',
        };

        $prefix = "{$head} — {$context} · Açıklama: ";
        $budget = self::MAX_LEN - mb_strlen($prefix);
        $desc = (string) $e->description_tr;

        if (mb_strlen($desc) > $budget) {
            $desc = Str::limit($desc, max(0, $budget - 1), '…');
        }

        return $prefix.$desc;
    }

    private function roleLabel(?string $role): string
    {
        return $role === WikiEntry::ROLE_KILLER ? 'Katil' : 'Kurban';
    }

    /**
     * Lightweight in-memory index of enabled entries for fuzzy matching,
     * cached and invalidated by WikiController writes / the sync command.
     *
     * @return array<int, array{id:int, display:string, keys:array<int,string>}>
     */
    private function index(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): array {
            return WikiEntry::query()
                ->enabled()
                ->with('aliases:id,wiki_entry_id,alias_norm')
                ->get(['id', 'name_en', 'name_tr', 'slug'])
                ->map(function (WikiEntry $e): array {
                    $keys = array_filter(array_unique(array_merge(
                        [WikiText::normalize($e->name_en), WikiText::normalize((string) $e->name_tr), $e->slug],
                        $e->aliases->pluck('alias_norm')->all(),
                    )));

                    return [
                        'id' => $e->id,
                        'display' => $e->name_tr ?: $e->name_en,
                        'keys' => array_values($keys),
                    ];
                })
                ->all();
        });
    }

    public static function flushIndex(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --compact tests/Feature/Kick/WikiLookupTest.php`
Expected: PASS (all tests). If the "exactly three suggestions" comma count is off, adjust the test data — the implementation returns at most 3; the assertion expects 3 distinct results from the 4 seeded near names.

- [ ] **Step 5: Pint + stage & hand off**

Run: `vendor/bin/pint --dirty --format agent`
Run: `git add app/Services/Kick/WikiLookup.php tests/Feature/Kick/WikiLookupTest.php`
STOP and tell the user: "Task 3 complete & staged (WikiLookup service)." Do not commit.

---

## Task 4: `WikiCommand` + registry wiring

**Files:**
- Create: `app/Services/Kick/BuiltIns/WikiCommand.php`
- Modify: `app/Services/Kick/BuiltInCommandRegistry.php`
- Test: `tests/Feature/Kick/WikiCommandTest.php`

- [ ] **Step 1: Write the failing command test**

Create `tests/Feature/Kick/WikiCommandTest.php`:

```php
<?php

use App\Jobs\Kick\SendChatMessageJob;
use App\Models\ChatMessage;
use App\Models\Command;
use App\Models\CommandLog;
use App\Models\WikiEntry;
use App\Services\Kick\BuiltInCommandRegistry;
use App\Services\Kick\BuiltIns\WikiCommand;
use App\Services\Kick\CommandContext;
use App\Services\Kick\CommandDispatcher;
use App\Services\Kick\WikiText;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.kick.command_prefix', '!');
    config()->set('services.kick.channel_slug', 'trolunal');
});

test('the registry maps the wiki handler', function () {
    expect(BuiltInCommandRegistry::handlers())->toContain('wiki')
        ->and(app(BuiltInCommandRegistry::class)->resolve('wiki'))
        ->toBeInstanceOf(WikiCommand::class);
});

test('WikiCommand returns the formatted answer for its args', function () {
    $entry = WikiEntry::factory()->perk('David King', 'survivor')->create([
        'name_en' => 'Dead Hard', 'name_tr' => 'Kum Torbası',
        'slug' => 'perk david king dead hard', 'description_tr' => 'Dayanıklılık.',
    ]);
    $entry->aliases()->create(['alias' => 'Kum Torbası', 'alias_norm' => WikiText::normalize('Kum Torbası')]);

    $command = Command::factory()->dynamic('wiki')->create(['name' => 'wiki']);
    $message = ChatMessage::factory()->make(['sender_username' => 'bob']);
    $context = new CommandContext($message, $command, 'wiki', ['kum', 'torbası']);

    expect(app(WikiCommand::class)->handle($context))
        ->toBe('Kum Torbası (Dead Hard) — Kurban: David King · Açıklama: Dayanıklılık.');
});

test('bare !wiki returns usage', function () {
    $command = Command::factory()->dynamic('wiki')->create(['name' => 'wiki']);
    $context = new CommandContext(ChatMessage::factory()->make(), $command, 'wiki', []);

    expect(app(WikiCommand::class)->handle($context))->toContain('Kullanım: !wiki');
});

test('end to end: !wiki dispatches through the command pipeline', function () {
    Queue::fake();
    WikiEntry::factory()->create([
        'type' => 'term', 'name_en' => 'Tunnel', 'name_tr' => 'Tünelleme',
        'slug' => 'term tunnel', 'description_tr' => 'Aynı kurbanı kovalama.',
    ]);
    Command::factory()->dynamic('wiki')->create(['name' => 'wiki']);

    $message = ChatMessage::factory()->create([
        'content' => '!wiki tunnel', 'is_command' => true, 'sender_kick_user_id' => 7,
        'sender_identity' => ['badges' => []],
    ]);
    app(CommandDispatcher::class)->handle($message);

    Queue::assertPushed(SendChatMessageJob::class, fn (SendChatMessageJob $j) => str_contains($j->content, 'Tünelleme (Tunnel)'));
    expect(CommandLog::where('outcome', 'sent')->count())->toBe(1);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact tests/Feature/Kick/WikiCommandTest.php`
Expected: FAIL — `Class "App\Services\Kick\BuiltIns\WikiCommand" not found`.

- [ ] **Step 3: Implement `WikiCommand`**

Create `app/Services/Kick/BuiltIns/WikiCommand.php`:

```php
<?php

namespace App\Services\Kick\BuiltIns;

use App\Services\Kick\CommandContext;
use App\Services\Kick\Contracts\BuiltInCommand;
use App\Services\Kick\WikiLookup;

/**
 * "!wiki <term>" — bilingual Dead by Daylight glossary lookup.
 */
class WikiCommand implements BuiltInCommand
{
    public function __construct(private readonly WikiLookup $lookup) {}

    public function handle(CommandContext $context): string
    {
        return $this->lookup->answer($context->argString());
    }
}
```

- [ ] **Step 4: Wire it into the registry**

Modify `app/Services/Kick/BuiltInCommandRegistry.php`. Add the import after the existing `use` block:

```php
use App\Services\Kick\BuiltIns\WikiCommand;
```

Add to the array returned by `map()` (after `'shoutout' => ShoutoutCommand::class,`):

```php
            'wiki' => WikiCommand::class,
```

- [ ] **Step 5: Run it to verify it passes**

Run: `php artisan test --compact tests/Feature/Kick/WikiCommandTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Pint + stage & hand off**

Run: `vendor/bin/pint --dirty --format agent`
Run: `git add app/Services/Kick/BuiltIns/WikiCommand.php app/Services/Kick/BuiltInCommandRegistry.php tests/Feature/Kick/WikiCommandTest.php`
STOP and tell the user: "Task 4 complete & staged (WikiCommand + registry)." Do not commit.

---

## Task 5: Validation concern + form requests

**Files:**
- Create: `app/Concerns/WikiValidationRules.php`
- Create: `app/Http/Requests/Kick/WikiStoreRequest.php`, `app/Http/Requests/Kick/WikiUpdateRequest.php`

- [ ] **Step 1: Implement the validation concern**

Create `app/Concerns/WikiValidationRules.php`:

```php
<?php

namespace App\Concerns;

use App\Models\WikiEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait WikiValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function wikiRules(): array
    {
        return [
            'type' => ['required', Rule::in(WikiEntry::TYPES)],
            'name_en' => ['required', 'string', 'max:191'],
            'name_tr' => ['nullable', 'string', 'max:191'],
            'owner' => ['nullable', 'string', 'max:191'],
            'role' => ['nullable', Rule::in([WikiEntry::ROLE_SURVIVOR, WikiEntry::ROLE_KILLER])],
            'description_tr' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'source_url' => ['nullable', 'string', 'max:255', 'url'],
            'is_enabled' => ['boolean'],
            'aliases' => ['array'],
            'aliases.*' => ['string', 'max:100', 'distinct'],
        ];
    }
}
```

- [ ] **Step 2: Implement the form requests**

Create `app/Http/Requests/Kick/WikiStoreRequest.php`:

```php
<?php

namespace App\Http\Requests\Kick;

use App\Concerns\WikiValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WikiStoreRequest extends FormRequest
{
    use WikiValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->wikiRules();
    }
}
```

Create `app/Http/Requests/Kick/WikiUpdateRequest.php`:

```php
<?php

namespace App\Http\Requests\Kick;

use App\Concerns\WikiValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WikiUpdateRequest extends FormRequest
{
    use WikiValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->wikiRules();
    }
}
```

- [ ] **Step 3: Pint + stage & hand off**

Run: `vendor/bin/pint --dirty --format agent`
Run: `git add app/Concerns/WikiValidationRules.php app/Http/Requests/Kick/WikiStoreRequest.php app/Http/Requests/Kick/WikiUpdateRequest.php`
STOP and tell the user: "Task 5 complete & staged (validation)." Do not commit. (No test step — validation is exercised by the panel tests in Task 6.)

---

## Task 6: `WikiController` + routes

**Files:**
- Create: `app/Http/Controllers/Kick/WikiController.php`
- Modify: `routes/kick.php`
- Test: `tests/Feature/Kick/WikiPanelTest.php`

- [ ] **Step 1: Write the failing panel test**

Create `tests/Feature/Kick/WikiPanelTest.php`:

```php
<?php

use App\Models\User;
use App\Models\WikiEntry;
use App\Services\Kick\WikiText;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the wiki index requires authentication', function () {
    auth()->logout();
    $this->get(route('kick.wiki.index'))->assertRedirect(route('login'));
});

test('index lists, filters by type and searches name/alias/owner', function () {
    $dh = WikiEntry::factory()->perk('David King', 'survivor')->create([
        'name_en' => 'Dead Hard', 'name_tr' => 'Kum Torbası', 'slug' => 'perk david king dead hard',
    ]);
    $dh->aliases()->create(['alias' => 'DH', 'alias_norm' => 'dh']);
    WikiEntry::factory()->create(['type' => 'term', 'name_en' => 'Tunnel', 'slug' => 'term tunnel']);

    $this->get(route('kick.wiki.index'))
        ->assertInertia(fn ($p) => $p->component('kick/Wiki')->has('entries.data', 2));

    $this->get(route('kick.wiki.index', ['type' => 'term']))
        ->assertInertia(fn ($p) => $p->has('entries.data', 1)
            ->where('entries.data.0.name_en', 'Tunnel'));

    $this->get(route('kick.wiki.index', ['search' => 'kum torbasi']))
        ->assertInertia(fn ($p) => $p->has('entries.data', 1)
            ->where('entries.data.0.name_en', 'Dead Hard'));

    $this->get(route('kick.wiki.index', ['search' => 'david']))
        ->assertInertia(fn ($p) => $p->has('entries.data', 1));
});

test('index paginates at 50', function () {
    WikiEntry::factory()->count(55)->create();

    $this->get(route('kick.wiki.index'))
        ->assertInertia(fn ($p) => $p->has('entries.data', 50));
});

test('store creates an entry with normalized deduped aliases', function () {
    $this->post(route('kick.wiki.store'), [
        'type' => 'perk', 'name_en' => 'Sprint Burst', 'name_tr' => 'Sprint',
        'owner' => 'Meg Thomas', 'role' => 'survivor', 'description_tr' => 'Hız.',
        'is_enabled' => true, 'aliases' => ['Sprint', 'sprint', 'SB'],
    ])->assertRedirect(route('kick.wiki.index'));

    $entry = WikiEntry::firstWhere('name_en', 'Sprint Burst');
    expect($entry)->not->toBeNull()
        ->and($entry->slug)->toBe('perk meg thomas sprint burst')
        ->and($entry->is_curated)->toBeTrue()
        ->and($entry->aliases()->pluck('alias_norm')->sort()->values()->all())
        ->toEqual(['sb', 'sprint']); // 'Sprint' & 'sprint' dedupe
});

test('update edits the entry, marks it curated and resyncs aliases', function () {
    $entry = WikiEntry::factory()->create(['name_en' => 'Old', 'slug' => 'perk x old']);
    $entry->aliases()->create(['alias' => 'o', 'alias_norm' => 'o']);

    $this->put(route('kick.wiki.update', $entry), [
        'type' => 'perk', 'name_en' => 'Old', 'name_tr' => 'Yeni',
        'description_tr' => 'Güncellendi.', 'is_enabled' => true, 'aliases' => ['yeni'],
    ])->assertRedirect(route('kick.wiki.index'));

    $entry->refresh();
    expect($entry->name_tr)->toBe('Yeni')
        ->and($entry->is_curated)->toBeTrue()
        ->and($entry->aliases()->pluck('alias_norm')->all())->toEqual(['yeni']);
});

test('destroy removes the entry and cascades aliases', function () {
    $entry = WikiEntry::factory()->create(['slug' => 'perk x gone']);
    $entry->aliases()->create(['alias' => 'g', 'alias_norm' => 'g']);

    $this->delete(route('kick.wiki.destroy', $entry))->assertRedirect(route('kick.wiki.index'));

    expect(WikiEntry::count())->toBe(0)
        ->and(\App\Models\WikiAlias::count())->toBe(0);
});

test('store validation rejects an invalid type', function () {
    $this->post(route('kick.wiki.store'), ['type' => 'bogus', 'name_en' => 'X'])
        ->assertSessionHasErrors('type');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact tests/Feature/Kick/WikiPanelTest.php`
Expected: FAIL — route `kick.wiki.index` not defined.

- [ ] **Step 3: Implement `WikiController`**

Create `app/Http/Controllers/Kick/WikiController.php`:

```php
<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kick\WikiStoreRequest;
use App\Http\Requests\Kick\WikiUpdateRequest;
use App\Models\WikiEntry;
use App\Services\Kick\WikiLookup;
use App\Services\Kick\WikiText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WikiController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $type = (string) $request->query('type', '');

        $entries = WikiEntry::query()
            ->with('aliases:id,wiki_entry_id,alias')
            ->when(in_array($type, WikiEntry::TYPES, true), fn ($q) => $q->where('type', $type))
            ->when($search !== '', function ($q) use ($search): void {
                $norm = WikiText::normalize($search);
                $like = '%'.$search.'%';
                $normLike = '%'.$norm.'%';
                $q->where(function ($w) use ($like, $normLike, $norm): void {
                    $w->where('name_en', 'like', $like)
                        ->orWhere('name_tr', 'like', $like)
                        ->orWhere('owner', 'like', $like)
                        ->orWhere('slug', 'like', $normLike)
                        ->orWhereHas('aliases', fn ($a) => $a->where('alias_norm', 'like', '%'.$norm.'%'));
                });
            })
            ->orderBy('type')
            ->orderBy('name_en')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('kick/Wiki', [
            'entries' => $entries,
            'types' => WikiEntry::TYPES,
            'filters' => ['search' => $search, 'type' => $type],
        ]);
    }

    public function store(WikiStoreRequest $request): RedirectResponse
    {
        $this->persist(new WikiEntry, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Wiki entry created.')]);

        return to_route('kick.wiki.index');
    }

    public function update(WikiUpdateRequest $request, WikiEntry $wikiEntry): RedirectResponse
    {
        $this->persist($wikiEntry, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Wiki entry updated.')]);

        return to_route('kick.wiki.index');
    }

    public function destroy(WikiEntry $wikiEntry): RedirectResponse
    {
        $wikiEntry->delete();
        WikiLookup::flushIndex();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Wiki entry deleted.')]);

        return to_route('kick.wiki.index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(WikiEntry $entry, array $data): void
    {
        $aliases = collect($data['aliases'] ?? [])
            ->map(fn (string $a): string => trim($a))
            ->filter()
            ->mapWithKeys(fn (string $a): array => [WikiText::normalize($a) => $a])
            ->filter(fn ($a, $norm): bool => $norm !== '');

        unset($data['aliases']);

        DB::transaction(function () use ($entry, $data, $aliases): void {
            $entry->fill($data);
            $entry->slug = WikiText::slug($data['type'], $data['owner'] ?? null, $data['name_en']);
            $entry->is_curated = true;
            $entry->save();

            $entry->aliases()->delete();
            $entry->aliases()->createMany(
                $aliases->map(fn (string $alias, string $norm): array => [
                    'alias' => $alias, 'alias_norm' => $norm,
                ])->values()->all(),
            );
        });

        WikiLookup::flushIndex();
    }
}
```

- [ ] **Step 4: Add the routes**

Modify `routes/kick.php`. Add the import alphabetically in the `use` block (after `use App\Http\Controllers\Kick\UserController;`):

```php
use App\Http\Controllers\Kick\WikiController;
```

Inside the `Route::middleware(['auth', 'verified'])->group(...)`, add after the "Command activity log." block (before "// User registry."):

```php
    // DBD wiki / glossary.
    Route::get('/kick/wiki', [WikiController::class, 'index'])
        ->name('kick.wiki.index');
    Route::post('/kick/wiki', [WikiController::class, 'store'])
        ->name('kick.wiki.store');
    Route::put('/kick/wiki/{wikiEntry}', [WikiController::class, 'update'])
        ->name('kick.wiki.update');
    Route::delete('/kick/wiki/{wikiEntry}', [WikiController::class, 'destroy'])
        ->name('kick.wiki.destroy');
```

- [ ] **Step 5: Run it to verify it passes**

Run: `php artisan test --compact tests/Feature/Kick/WikiPanelTest.php`
Expected: PASS (8 tests).

- [ ] **Step 6: Pint + stage & hand off**

Run: `vendor/bin/pint --dirty --format agent`
Run: `git add app/Http/Controllers/Kick/WikiController.php routes/kick.php tests/Feature/Kick/WikiPanelTest.php`
STOP and tell the user: "Task 6 complete & staged (controller + routes)." Do not commit.

---

## Task 7: Wayfinder build + `Wiki.svelte` page + nav + types

**Files:**
- Modify: `resources/js/types/kick.ts`
- Create: `resources/js/pages/kick/Wiki.svelte`
- Modify: `resources/js/layouts/kick/Layout.svelte`

- [ ] **Step 1: Generate Wayfinder routes**

Run: `php artisan wayfinder:generate`
Expected: creates `resources/js/routes/kick/wiki/index.ts` (and the `kick.wiki.*` helpers). Confirm with: `ls resources/js/routes/kick/wiki`.

- [ ] **Step 2: Add types**

Modify `resources/js/types/kick.ts`. Append:

```ts
export type WikiAliasRow = {
    id: number;
    alias: string;
};

export type WikiEntryRow = {
    id: number;
    type: 'killer' | 'survivor' | 'perk' | 'power' | 'addon' | 'term';
    name_en: string;
    name_tr: string | null;
    owner: string | null;
    role: 'survivor' | 'killer' | null;
    description_tr: string | null;
    description_en: string | null;
    source_url: string | null;
    is_enabled: boolean;
    is_curated: boolean;
    aliases: WikiAliasRow[];
};
```

- [ ] **Step 3: Create the Svelte page**

Create `resources/js/pages/kick/Wiki.svelte` (paginated index — `Messages.svelte` filter/pagination pattern — plus an inline create/edit form — `Commands.svelte` pattern):

```svelte
<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Pagination from '@/components/kick/Pagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { destroy, index as wikiIndex, store, update } from '@/routes/kick/wiki';
    import type { Paginated, WikiEntryRow } from '@/types/kick';

    let {
        entries,
        types,
        filters,
    }: {
        entries: Paginated<WikiEntryRow>;
        types: string[];
        filters: { search: string | null; type: string | null };
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search ?? '');
    // svelte-ignore state_referenced_locally
    let typeFilter = $state(filters.type ?? '');

    const blank = {
        id: null as number | null,
        type: 'perk',
        name_en: '',
        name_tr: '',
        owner: '',
        role: '',
        description_tr: '',
        description_en: '',
        source_url: '',
        is_enabled: true,
        aliases: '',
    };

    let form = $state({ ...blank });
    let editing = $state(false);

    function newEntry() {
        form = { ...blank };
        editing = true;
    }

    function edit(entry: WikiEntryRow) {
        form = {
            id: entry.id,
            type: entry.type,
            name_en: entry.name_en,
            name_tr: entry.name_tr ?? '',
            owner: entry.owner ?? '',
            role: entry.role ?? '',
            description_tr: entry.description_tr ?? '',
            description_en: entry.description_en ?? '',
            source_url: entry.source_url ?? '',
            is_enabled: entry.is_enabled,
            aliases: entry.aliases.map((a) => a.alias).join(', '),
        };
        editing = true;
    }

    function payload() {
        return {
            ...form,
            role: form.role || null,
            name_tr: form.name_tr || null,
            owner: form.owner || null,
            description_tr: form.description_tr || null,
            description_en: form.description_en || null,
            source_url: form.source_url || null,
            aliases: form.aliases
                .split(',')
                .map((a) => a.trim())
                .filter(Boolean),
        };
    }

    function save() {
        const options = { onSuccess: () => (editing = false), preserveScroll: true };
        if (form.id) {
            router.put(update(form.id).url, payload(), options);
        } else {
            router.post(store().url, payload(), options);
        }
    }

    function remove(entry: WikiEntryRow) {
        if (confirm(`"${entry.name_en}" silinsin mi?`)) {
            router.delete(destroy(entry.id).url, { preserveScroll: true });
        }
    }

    function applyFilters(event: SubmitEvent) {
        event.preventDefault();
        router.get(
            wikiIndex().url,
            { search, type: typeFilter },
            { preserveState: true, preserveScroll: true },
        );
    }
</script>

<AppHead title="DBD Wiki" />

<div class="flex items-center justify-between">
    <h2 class="text-lg font-semibold">DBD Wiki</h2>
    <Button onclick={newEntry}>Yeni kayıt</Button>
</div>

<Card class="mt-4">
    <CardContent class="pt-6">
        <form class="grid gap-3 sm:grid-cols-3" onsubmit={applyFilters}>
            <div class="grid gap-1">
                <Label for="search">Ara (TR/EN/alias/sahip)</Label>
                <Input
                    id="search"
                    value={search}
                    oninput={(e) => (search = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="typeFilter">Tür</Label>
                <select
                    id="typeFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    bind:value={typeFilter}
                >
                    <option value="">hepsi</option>
                    {#each types as t (t)}
                        <option value={t}>{t}</option>
                    {/each}
                </select>
            </div>
            <div class="flex items-end">
                <Button type="submit">Filtrele</Button>
            </div>
        </form>
    </CardContent>
</Card>

{#if editing}
    <Card class="mt-4">
        <CardContent class="space-y-4 pt-6">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1">
                    <Label for="type">Tür</Label>
                    <select
                        id="type"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        bind:value={form.type}
                    >
                        {#each types as t (t)}
                            <option value={t}>{t}</option>
                        {/each}
                    </select>
                </div>
                <div class="grid gap-1">
                    <Label for="role">Rol (Kurban/Katil etiketi)</Label>
                    <select
                        id="role"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        bind:value={form.role}
                    >
                        <option value="">yok</option>
                        <option value="survivor">survivor (Kurban)</option>
                        <option value="killer">killer (Katil)</option>
                    </select>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1">
                    <Label for="name_en">İngilizce ad</Label>
                    <Input
                        id="name_en"
                        value={form.name_en}
                        oninput={(e) => (form.name_en = e.currentTarget.value)}
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="name_tr">Türkçe ad (lakap)</Label>
                    <Input
                        id="name_tr"
                        value={form.name_tr}
                        oninput={(e) => (form.name_tr = e.currentTarget.value)}
                    />
                </div>
            </div>
            <div class="grid gap-1">
                <Label for="owner">Sahip (karakter)</Label>
                <Input
                    id="owner"
                    value={form.owner}
                    oninput={(e) => (form.owner = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="description_tr">Açıklama (TR — chat'te gösterilir)</Label>
                <textarea
                    id="description_tr"
                    rows="3"
                    class="rounded-md border bg-background px-3 py-2 text-sm"
                    bind:value={form.description_tr}
                ></textarea>
            </div>
            <div class="grid gap-1">
                <Label for="description_en">Açıklama (EN — referans, chat'te gösterilmez)</Label>
                <textarea
                    id="description_en"
                    rows="2"
                    class="rounded-md border bg-background px-3 py-2 text-sm"
                    bind:value={form.description_en}
                ></textarea>
            </div>
            <div class="grid gap-1">
                <Label for="aliases">Alias'lar (virgülle ayrılmış — TR ve EN)</Label>
                <Input
                    id="aliases"
                    value={form.aliases}
                    oninput={(e) => (form.aliases = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="source_url">Kaynak URL</Label>
                <Input
                    id="source_url"
                    value={form.source_url}
                    oninput={(e) => (form.source_url = e.currentTarget.value)}
                />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" bind:checked={form.is_enabled} />
                Aktif
            </label>
            <div class="flex gap-2">
                <Button onclick={save}>Kaydet</Button>
                <Button variant="outline" onclick={() => (editing = false)}>
                    Vazgeç
                </Button>
            </div>
        </CardContent>
    </Card>
{/if}

<div class="mt-4 overflow-x-auto rounded-md border">
    <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
            <tr>
                <th class="px-3 py-2">Tür</th>
                <th class="px-3 py-2">TR adı</th>
                <th class="px-3 py-2">EN adı</th>
                <th class="px-3 py-2">Sahip</th>
                <th class="px-3 py-2">Alias</th>
                <th class="px-3 py-2">Durum</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody>
            {#each entries.data as entry (entry.id)}
                <tr class="border-t">
                    <td class="px-3 py-2">{entry.type}</td>
                    <td class="px-3 py-2 font-medium">{entry.name_tr ?? '—'}</td>
                    <td class="px-3 py-2">{entry.name_en}</td>
                    <td class="px-3 py-2 text-muted-foreground">
                        {entry.owner ?? '—'}
                    </td>
                    <td class="px-3 py-2">
                        {#each entry.aliases as alias (alias.id)}
                            <Badge variant="secondary" class="ml-1">
                                {alias.alias}
                            </Badge>
                        {/each}
                    </td>
                    <td class="px-3 py-2">
                        {#if entry.is_enabled}
                            <Badge>aktif</Badge>
                        {:else}
                            <Badge variant="secondary">pasif</Badge>
                        {/if}
                    </td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => edit(entry)}
                        >
                            Düzenle
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => remove(entry)}
                        >
                            Sil
                        </Button>
                    </td>
                </tr>
            {:else}
                <tr>
                    <td
                        colspan="7"
                        class="px-3 py-6 text-center text-muted-foreground"
                    >
                        Kayıt yok.
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>

<Pagination links={entries.links} />
```

- [ ] **Step 4: Add the nav item**

Modify `resources/js/layouts/kick/Layout.svelte`. Add the import after `import { index as usersIndex } from '@/routes/kick/users';`:

```ts
    import { index as wikiIndex } from '@/routes/kick/wiki';
```

Add to `sidebarNavItems` after the `Users` item:

```ts
        { title: 'Wiki', href: wikiIndex() },
```

- [ ] **Step 5: Build the frontend**

Run: `npm run build`
Expected: build succeeds, no "Unable to locate file in Vite manifest" / no unresolved `@/routes/kick/wiki` import.

- [ ] **Step 6: Run the panel test again (still green through the page contract)**

Run: `php artisan test --compact tests/Feature/Kick/WikiPanelTest.php`
Expected: PASS (8 tests) — `component('kick/Wiki')` resolves.

- [ ] **Step 7: Prettier + stage & hand off**

Run: `npx prettier --write resources/js/pages/kick/Wiki.svelte resources/js/layouts/kick/Layout.svelte resources/js/types/kick.ts`
Run: `git add resources/js/pages/kick/Wiki.svelte resources/js/layouts/kick/Layout.svelte resources/js/types/kick.ts`
STOP and tell the user: "Task 7 complete & staged (admin page + nav). Note: built assets changed; build/commit of compiled assets is yours to handle per your deploy flow." Do not commit. Do not `git add` generated `resources/js/routes/**` (gitignored, regenerated at build).

---

## Task 8: `dbd:sync-wiki` console command + seeder

**Files:**
- Create: `app/Console/Commands/Kick/SyncDbdWikiCommand.php`
- Create: `database/seeders/WikiSeeder.php`
- Test: `tests/Feature/Kick/DbdSyncWikiTest.php`

- [ ] **Step 1: Write the failing sync test**

Create `tests/Feature/Kick/DbdSyncWikiTest.php`:

```php
<?php

use App\Models\Command;
use App\Models\WikiEntry;

/**
 * Writes a tiny fixture dataset to a temp dir and points the command at it
 * via --path, so the test never depends on the large real datasets.
 *
 * @param  array<int, array<string, mixed>>  $tier1
 * @param  array<int, array<string, mixed>>  $tier2
 */
function writeDbdFixture(array $tier1, array $tier2 = []): string
{
    $dir = sys_get_temp_dir().'/dbd-'.uniqid();
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/tier1.json', json_encode($tier1));
    file_put_contents($dir.'/tier2.json', json_encode($tier2));

    return $dir;
}

test('sync seeds entries, aliases and the wiki command', function () {
    $dir = writeDbdFixture([
        [
            'type' => 'perk', 'name_en' => 'Dead Hard', 'name_tr' => 'Kum Torbası',
            'owner' => 'David King', 'role' => 'survivor',
            'description_tr' => 'Dayanıklılık.', 'description_en' => 'Endurance.',
            'source_url' => 'https://example.test/dh', 'aliases' => ['DH'],
        ],
    ]);

    $this->artisan('dbd:sync-wiki', ['--path' => $dir, '--tier' => '1'])
        ->assertSuccessful();

    $entry = WikiEntry::firstWhere('name_en', 'Dead Hard');
    expect($entry->slug)->toBe('perk david king dead hard')
        ->and($entry->is_curated)->toBeFalse()
        // auto aliases (name_en, name_tr) + explicit (DH), normalized + deduped
        ->and($entry->aliases()->pluck('alias_norm')->sort()->values()->all())
        ->toEqual(['dead hard', 'dh', 'kum torbasi'])
        ->and(Command::where('name', 'wiki')->where('handler', 'wiki')->exists())->toBeTrue();
});

test('sync is idempotent', function () {
    $dir = writeDbdFixture([
        ['type' => 'term', 'name_en' => 'Tunnel', 'name_tr' => 'Tünel', 'aliases' => []],
    ]);

    $this->artisan('dbd:sync-wiki', ['--path' => $dir, '--tier' => '1'])->assertSuccessful();
    $this->artisan('dbd:sync-wiki', ['--path' => $dir, '--tier' => '1'])->assertSuccessful();

    expect(WikiEntry::where('name_en', 'Tunnel')->count())->toBe(1)
        ->and(WikiEntry::firstWhere('name_en', 'Tunnel')->aliases()->count())->toBe(2);
});

test('sync does not overwrite curated name/description but still adds new aliases', function () {
    $dir = writeDbdFixture([
        [
            'type' => 'perk', 'name_en' => 'Adrenaline', 'name_tr' => 'Wiki TR',
            'description_tr' => 'Wiki açıklaması.', 'aliases' => ['adr'],
        ],
    ]);
    $this->artisan('dbd:sync-wiki', ['--path' => $dir, '--tier' => '1'])->assertSuccessful();

    $entry = WikiEntry::firstWhere('name_en', 'Adrenaline');
    $entry->update(['name_tr' => 'Admin TR', 'description_tr' => 'Admin yazdı.', 'is_curated' => true]);

    // Re-sync with a NEW alias and changed wiki text.
    $dir2 = writeDbdFixture([
        [
            'type' => 'perk', 'name_en' => 'Adrenaline', 'name_tr' => 'Değişti',
            'description_tr' => 'Değişti.', 'aliases' => ['adr', 'adre'],
        ],
    ]);
    $this->artisan('dbd:sync-wiki', ['--path' => $dir2, '--tier' => '1'])->assertSuccessful();

    $entry->refresh();
    expect($entry->name_tr)->toBe('Admin TR')                 // curated, untouched
        ->and($entry->description_tr)->toBe('Admin yazdı.')   // curated, untouched
        ->and($entry->aliases()->pluck('alias_norm')->sort()->values()->all())
        ->toContain('adre');                                  // new alias still added
});

test('tier option filters which dataset loads', function () {
    $dir = writeDbdFixture(
        [['type' => 'perk', 'name_en' => 'T1 Perk', 'aliases' => []]],
        [['type' => 'addon', 'name_en' => 'T2 Addon', 'owner' => 'The Trapper', 'aliases' => []]],
    );

    $this->artisan('dbd:sync-wiki', ['--path' => $dir, '--tier' => '2'])->assertSuccessful();

    expect(WikiEntry::where('name_en', 'T2 Addon')->exists())->toBeTrue()
        ->and(WikiEntry::where('name_en', 'T1 Perk')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact tests/Feature/Kick/DbdSyncWikiTest.php`
Expected: FAIL — command `dbd:sync-wiki` not defined.

- [ ] **Step 3: Implement the command**

Create `app/Console/Commands/Kick/SyncDbdWikiCommand.php`:

```php
<?php

namespace App\Console\Commands\Kick;

use App\Models\Command as ChatCommand;
use App\Models\WikiEntry;
use App\Services\Kick\WikiLookup;
use App\Services\Kick\WikiText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncDbdWikiCommand extends Command
{
    protected $signature = 'dbd:sync-wiki
        {--tier=all : Which dataset(s) to load: 1, 2 or all}
        {--path= : Directory containing tierN.json (defaults to bundled data)}
        {--fresh : Truncate wiki tables before importing (dev only)}';

    protected $description = 'Import/refresh the Dead by Daylight glossary from bundled datasets (idempotent, non-destructive to curated rows)';

    public function handle(): int
    {
        $path = rtrim((string) ($this->option('path') ?: database_path('seeders/data/dbd')), '/');
        $tier = (string) $this->option('tier');
        $tiers = $tier === 'all' ? ['1', '2'] : [$tier];

        if ($this->option('fresh')) {
            DB::table('wiki_aliases')->delete();
            DB::table('wiki_entries')->delete();
            $this->warn('Wiki tables truncated.');
        }

        $this->ensureWikiCommand();

        $total = 0;
        foreach ($tiers as $t) {
            $file = "{$path}/tier{$t}.json";
            if (! is_file($file)) {
                $this->warn("Tier {$t} dataset not found at {$file}; skipping.");

                continue;
            }

            /** @var array<int, array<string, mixed>> $records */
            $records = json_decode((string) file_get_contents($file), true) ?: [];
            $bar = $this->output->createProgressBar(count($records));
            $bar->start();

            foreach ($records as $record) {
                $this->upsert($record);
                $bar->advance();
                $total++;
            }

            $bar->finish();
            $this->newLine();
        }

        WikiLookup::flushIndex();
        $this->info("Synced {$total} DBD wiki entries.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function upsert(array $r): void
    {
        $type = (string) $r['type'];
        $nameEn = (string) $r['name_en'];
        $owner = $r['owner'] ?? null;
        $slug = WikiText::slug($type, $owner, $nameEn);

        DB::transaction(function () use ($r, $type, $nameEn, $owner, $slug): void {
            $entry = WikiEntry::firstOrNew(['slug' => $slug]);

            if (! $entry->exists) {
                $entry->fill([
                    'type' => $type, 'name_en' => $nameEn, 'name_tr' => $r['name_tr'] ?? null,
                    'slug' => $slug, 'owner' => $owner, 'role' => $r['role'] ?? null,
                    'description_tr' => $r['description_tr'] ?? null,
                    'description_en' => $r['description_en'] ?? null,
                    'source_url' => $r['source_url'] ?? null,
                    'is_enabled' => true, 'is_curated' => false,
                ])->save();
            } elseif (! $entry->is_curated) {
                $entry->fill([
                    'type' => $type, 'name_en' => $nameEn, 'name_tr' => $r['name_tr'] ?? null,
                    'owner' => $owner, 'role' => $r['role'] ?? null,
                    'description_tr' => $r['description_tr'] ?? null,
                    'description_en' => $r['description_en'] ?? null,
                    'source_url' => $r['source_url'] ?? null,
                ])->save();
            }

            // Auto + explicit aliases; never delete existing (admin or prior).
            $aliases = collect([$nameEn, $r['name_tr'] ?? null])
                ->merge($r['aliases'] ?? [])
                ->filter(fn ($a): bool => is_string($a) && trim($a) !== '')
                ->mapWithKeys(fn (string $a): array => [WikiText::normalize($a) => trim($a)])
                ->filter(fn ($a, $norm): bool => $norm !== '');

            foreach ($aliases as $norm => $alias) {
                $entry->aliases()->firstOrCreate(
                    ['alias_norm' => $norm],
                    ['alias' => $alias],
                );
            }
        });
    }

    private function ensureWikiCommand(): void
    {
        ChatCommand::firstOrCreate(
            ['name' => 'wiki'],
            [
                'type' => ChatCommand::TYPE_DYNAMIC,
                'handler' => 'wiki',
                'response' => null,
                'permission' => ChatCommand::PERMISSION_EVERYONE,
                'cooldown_seconds' => 3,
                'user_cooldown_seconds' => 0,
                'is_enabled' => true,
                'reply_in_thread' => false,
            ],
        );
    }
}
```

> Note: Laravel auto-discovers commands in `app/Console/Commands`. No Kernel registration needed (consistent with `SyncSubscriptionsCommand`).

- [ ] **Step 4: Implement the seeder**

Create `database/seeders/WikiSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class WikiSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->call('dbd:sync-wiki', ['--tier' => 'all']);
    }
}
```

- [ ] **Step 5: Run it to verify it passes**

Run: `php artisan test --compact tests/Feature/Kick/DbdSyncWikiTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Pint + stage & hand off**

Run: `vendor/bin/pint --dirty --format agent`
Run: `git add app/Console/Commands/Kick/SyncDbdWikiCommand.php database/seeders/WikiSeeder.php tests/Feature/Kick/DbdSyncWikiTest.php`
STOP and tell the user: "Task 8 complete & staged (sync command + seeder)." Do not commit.

---

## Task 9: Author the Tier 1 dataset (perks, powers, characters, terms)

This task produces real data, so it is not TDD. It has a concrete acceptance check.

**Files:**
- Create: `database/seeders/data/dbd/tier1.json`

- [ ] **Step 1: Fetch the current DBD roster & perks from the live wiki**

Use WebFetch against the official community wiki **`https://deadbydaylight.wiki.gg`** (current, maintained; verify it is the live wiki on the day of execution — if it moved, use the canonical wiki the game links to). Fetch these list pages and the per-character pages they link:
- Killers list (each killer page → killer name, power name + description, the killer's 3 perks).
- Survivors list (each survivor page → survivor name, their 3 perks).
- "Perks" pages (Survivor Perks, Killer Perks) for canonical English names + descriptions.
- A general-terms source (community glossary / "Gameplay terminology") for `term` entries (gen, loop, pallet, totem, hex, tunnel, camp, gen rush, BNP, hook stages, bloodpoints, prestige, etc.).

Cross-check the roster is current as of the execution date (note the latest chapter on the wiki's main page).

- [ ] **Step 2: Build `tier1.json`**

Write `database/seeders/data/dbd/tier1.json` as a JSON array. Each record:

```json
{
  "type": "perk",
  "name_en": "Dead Hard",
  "name_tr": "Kum Torbası",
  "owner": "David King",
  "role": "survivor",
  "description_tr": "Kısa Türkçe özet (oyun terimleriyle).",
  "description_en": "Concise English text from the wiki.",
  "source_url": "https://deadbydaylight.wiki.gg/wiki/Dead_Hard",
  "aliases": ["dead hard", "kum torbası", "dh"]
}
```

Rules:
- `type`: `perk` | `power` | `killer` | `survivor` | `term`.
- `owner`: perk → owning survivor/killer full name; power → killer name; killer/survivor/term → omit/null.
- `role`: perk/character → `survivor` or `killer`; term → null.
- `name_tr`: best-effort common Turkish community nickname or a natural Turkish rendering; if none is known, set `null` (do not invent nonsense). It is explicitly admin-curated later.
- `description_tr`: a faithful, concise Turkish summary of the English effect (1–2 sentences; keep it short — chat truncates at 480 chars).
- `aliases`: include EN name, TR name (if any), and obvious community short forms. Do **not** worry about diacritics/casing — the importer normalizes.
- Work in batches by section (all killers, then all survivors' perks, then terms) to keep each fetch reviewable. It is acceptable to commit the file incrementally across sessions; the importer is idempotent.

- [ ] **Step 3: Acceptance check**

Run: `php artisan migrate:fresh` then `php artisan dbd:sync-wiki --tier=1`
Expected: progress bar completes; final line `Synced <N> DBD wiki entries.` with N in the expected order of magnitude (hundreds — all perks + powers + characters + terms).

Run a spot check via tinker:
`php artisan tinker --execute 'echo app(App\Services\Kick\WikiLookup::class)->answer("dead hard");'`
Expected: a formatted line like `Kum Torbası (Dead Hard) — Kurban: David King · Açıklama: …`.

Run: `php artisan tinker --execute 'echo app(App\Services\Kick\WikiLookup::class)->answer("deadhard");'`
Expected: `'deadhard' bulunamadı. Şunları deneyin: …` (fuzzy works).

- [ ] **Step 4: Stage & hand off**

Run: `git add database/seeders/data/dbd/tier1.json`
STOP and tell the user: "Task 9 complete & staged (Tier 1 dataset, N entries). After you deploy, run `php artisan dbd:sync-wiki --tier=1` on the server." Do not commit.

---

## Task 10: Author the Tier 2 dataset (add-ons)

**Files:**
- Create: `database/seeders/data/dbd/tier2.json`

- [ ] **Step 1: Fetch add-ons**

For every killer, fetch the add-ons section of their wiki page (each killer has ~20 add-ons). Record: add-on English name, owning killer, English description.

- [ ] **Step 2: Build `tier2.json`**

Same schema as Tier 1, with `type: "addon"`, `owner: "<Killer Name>"`, `role: "killer"`, `name_tr` best-effort or `null`, concise `description_tr`, `aliases` = [EN name, TR name if any]. Work killer-by-killer (batches); the file may be committed incrementally — the importer is idempotent and slug-keyed so partial re-runs are safe.

- [ ] **Step 3: Acceptance check**

Run: `php artisan dbd:sync-wiki --tier=2`
Expected: completes; `Synced <M> DBD wiki entries.` with M in the hundreds (add-ons).
Spot check: `php artisan tinker --execute 'echo app(App\Services\Kick\WikiLookup::class)->answer("<a known add-on name>");'` → formatted `… — Add-on: <Killer> · Açıklama: …`.

- [ ] **Step 4: Stage & hand off**

Run: `git add database/seeders/data/dbd/tier2.json`
STOP and tell the user: "Task 10 complete & staged (Tier 2 add-ons, M entries). On the server run `php artisan dbd:sync-wiki --tier=all`." Do not commit.

---

## Task 11: Full verification + final hand off

**Files:** none (verification only).

- [ ] **Step 1: Pint (whole diff)**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no style issues remain.

- [ ] **Step 2: Full test suite**

Run: `php artisan test --compact`
Expected: all green — existing suite plus the 5 new files (`WikiTextTest`, `WikiLookupTest`, `WikiCommandTest`, `WikiPanelTest`, `DbdSyncWikiTest`). 0 failed. (Pre-existing `RegistrationTest` self-skip is expected.)

- [ ] **Step 3: Frontend build**

Run: `npm run build`
Expected: builds clean; no Vite/Wayfinder errors.

- [ ] **Step 4: Manual smoke (optional, ask the user to run the dev server)**

Suggest the user run the panel and verify: `/kick/wiki` lists entries, type filter + search work, create/edit/delete work, "Wiki" appears in the sidebar; in chat `!wiki dead hard`, `!wiki kum torbasi`, `!wiki` (usage), `!wiki deadx` (suggestions) behave per spec.

- [ ] **Step 5: Stage anything outstanding & final hand off**

Run: `git status` to confirm only intended files changed.
STOP and report to the user: full summary of what was built, the test result line, and the **post-deploy reminder**: run `php artisan dbd:sync-wiki --tier=all` once on the server after deploying so the glossary and the `!wiki` command row are populated. Remind them nothing was committed or pushed — all commits/pushes/deploy are theirs.

---

## Self-Review

**Spec coverage:**
- Data model (`wiki_entries`, `wiki_aliases`, normalization, `is_curated`) → Tasks 1–2. ✓
- `!wiki` behavior (empty/usage, exact, format per type, ≤480 truncation, 3 suggestions, nothing-close usage) → Task 3 (`WikiLookup`) + Task 4 (command/dispatch). ✓
- Turkish-diacritic-insensitive lookup → `WikiText` (Task 1) + tested in Tasks 3/4. ✓
- Admin CRUD (search, type filter, paginate 50, store/update/destroy, `is_curated` on edit, cascade) → Tasks 5–6. ✓
- Svelte page + nav + types + Wayfinder ordering → Task 7. ✓
- `dbd:sync-wiki` idempotent, tiered, non-destructive to curated, ensures `wiki` command, `--fresh`/`--tier`/`--path` → Task 8. ✓
- Approach B tiering: Tier 1 first (Task 9), Tier 2 add-ons second (Task 10). ✓
- Live-wiki sourcing for currency; best-effort TR + admin curation → Tasks 9–10 procedure + `is_curated`. ✓
- Testing matrix (WikiText/WikiLookup/WikiCommand/WikiPanel/DbdSyncWiki) → Tasks 1,3,4,6,8 + Task 11 full run. ✓

**Placeholder scan:** No TBD/TODO. Data-authoring tasks (9–10) intentionally contain a procedure + concrete acceptance commands rather than fabricated data — correct for a non-TDD data task.

**Type consistency:** `WikiText::normalize`/`::slug`, `WikiEntry::TYPES`/`ROLE_*`/`enabled()`/`aliases()`/`displayName()`, `WikiLookup::answer/resolve/suggest/usage/flushIndex` (static `flushIndex` used by controller + command), `CommandContext::argString()`, `BuiltInCommand::handle` signature, `Inertia::flash('toast', …)`, route names `kick.wiki.*`, prop shape `entries`(Paginated)/`types`/`filters`, TS `WikiEntryRow`/`WikiAliasRow` — all consistent across tasks. Cache key `wiki:index` centralized in `WikiLookup` and only flushed via `WikiLookup::flushIndex()`.
