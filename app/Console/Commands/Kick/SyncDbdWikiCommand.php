<?php

namespace App\Console\Commands\Kick;

use App\Models\Command as ChatCommand;
use App\Models\WikiEntry;
use App\Services\Kick\WikiLookup;
use App\Services\Kick\WikiText;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('dbd:sync-wiki {--tier=all : Which dataset(s) to load: 1, 2 or all} {--path= : Directory containing tierN.json (defaults to bundled data)} {--fresh : Truncate wiki tables before importing (dev only)}')]
#[Description('Import/refresh the Dead by Daylight glossary from bundled datasets (idempotent, non-destructive to curated rows)')]
class SyncDbdWikiCommand extends Command
{
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
