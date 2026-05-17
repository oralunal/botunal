<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Pagination from '@/components/kick/Pagination.svelte';
    import { Button } from '@/components/ui/button';
    import { usePoll } from '@/lib/kick-poll.svelte';
    import { events as eventsRoute } from '@/routes/kick';
    import type { Paginated } from '@/types/kick';

    let {
        type,
        types,
        events,
    }: {
        type: string;
        types: string[];
        events: Paginated<Record<string, unknown>>;
    } = $props();

    usePoll(['events'], 15000);

    const hidden = new Set(['id', 'created_at', 'updated_at', 'payload']);

    const columns = $derived(
        events.data.length > 0
            ? Object.keys(events.data[0]).filter((k) => !hidden.has(k))
            : [],
    );

    function switchType(next: string) {
        router.get(eventsRoute().url, { type: next }, { preserveScroll: true });
    }

    function display(value: unknown): string {
        if (value === null || value === undefined) {
            return '—';
        }

        if (typeof value === 'object') {
            return JSON.stringify(value);
        }

        return String(value);
    }
</script>

<AppHead title="Kick events" />

<div class="flex flex-wrap gap-1">
    {#each types as t (t)}
        <Button
            variant={t === type ? 'default' : 'outline'}
            size="sm"
            onclick={() => switchType(t)}
        >
            {t}
        </Button>
    {/each}
</div>

<div class="mt-4 overflow-x-auto rounded-md border">
    <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
            <tr>
                {#each columns as column (column)}
                    <th class="px-3 py-2">{column}</th>
                {/each}
            </tr>
        </thead>
        <tbody>
            {#each events.data as row (row.id)}
                <tr class="border-t">
                    {#each columns as column (column)}
                        <td class="px-3 py-2">{display(row[column])}</td>
                    {/each}
                </tr>
            {:else}
                <tr>
                    <td
                        colspan={Math.max(columns.length, 1)}
                        class="px-3 py-6 text-center text-muted-foreground"
                    >
                        No events.
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>

<Pagination links={events.links} />
