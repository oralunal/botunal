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
                return WikiEntry::query()->enabled()->find($row['id']);
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
