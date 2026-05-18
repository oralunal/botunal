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
                $bare = str_replace(['\\', '%', '_'], '', $search);

                if ($bare === '' && $norm === '') {
                    $q->whereRaw('0');

                    return;
                }

                $like = '%'.$bare.'%';
                $normLike = '%'.$norm.'%';
                $q->where(function ($w) use ($bare, $like, $normLike, $norm): void {
                    if ($bare !== '') {
                        $w->where('name_en', 'like', $like)
                            ->orWhere('name_tr', 'like', $like)
                            ->orWhere('owner', 'like', $like);
                    }

                    if ($norm !== '') {
                        $w->orWhere('name_tr_norm', 'like', $normLike)
                            ->orWhere('slug', 'like', $normLike)
                            ->orWhereHas('aliases', fn ($a) => $a->where('alias_norm', 'like', '%'.$norm.'%'));
                    }
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
            $entry->slug = WikiText::slug($entry->type, $entry->owner, $entry->name_en);
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
