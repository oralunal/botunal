<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import Pagination from '@/components/kick/Pagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { usePoll } from '@/lib/kick-poll.svelte';
    import type { Paginated } from '@/types/kick';

    type LogRow = {
        id: number;
        invoker_username: string;
        raw_message: string;
        response_sent: string | null;
        outcome: string;
        occurred_at: string | null;
    };

    let { logs }: { logs: Paginated<LogRow> } = $props();

    usePoll(['logs'], 15000);

    const variant = (outcome: string) =>
        outcome === 'sent'
            ? 'default'
            : outcome === 'error'
              ? 'destructive'
              : 'secondary';
</script>

<AppHead title="Command activity" />

<h2 class="text-lg font-semibold">Command activity</h2>

<div class="mt-4 overflow-x-auto rounded-md border">
    <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
            <tr>
                <th class="px-3 py-2">Time</th>
                <th class="px-3 py-2">User</th>
                <th class="px-3 py-2">Message</th>
                <th class="px-3 py-2">Response</th>
                <th class="px-3 py-2">Outcome</th>
            </tr>
        </thead>
        <tbody>
            {#each logs.data as log (log.id)}
                <tr class="border-t">
                    <td
                        class="px-3 py-2 whitespace-nowrap text-muted-foreground"
                    >
                        {log.occurred_at ?? '—'}
                    </td>
                    <td class="px-3 py-2 font-medium">
                        {log.invoker_username}
                    </td>
                    <td class="px-3 py-2">{log.raw_message}</td>
                    <td class="px-3 py-2 text-muted-foreground">
                        {log.response_sent ?? '—'}
                    </td>
                    <td class="px-3 py-2">
                        <Badge variant={variant(log.outcome)}>
                            {log.outcome}
                        </Badge>
                    </td>
                </tr>
            {:else}
                <tr>
                    <td
                        colspan="5"
                        class="px-3 py-6 text-center text-muted-foreground"
                    >
                        No command activity yet.
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>

<Pagination links={logs.links} />
