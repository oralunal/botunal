<script lang="ts">
    import { Deferred } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Skeleton } from '@/components/ui/skeleton';
    import { usePoll } from '@/lib/kick-poll.svelte';
    import type { FeedItem } from '@/types/kick';

    let {
        connections,
        is_live,
        counts,
        feed,
    }: {
        connections: { channel: boolean; bot: boolean };
        is_live: boolean;
        counts: {
            messages: number;
            follows: number;
            subscriptions: number;
            kicks: number;
            bans: number;
        };
        feed?: FeedItem[];
    } = $props();

    usePoll(['counts', 'feed'], 10000);

    const tiles = $derived([
        { label: 'Messages', value: counts.messages },
        { label: 'Follows', value: counts.follows },
        { label: 'Subscriptions', value: counts.subscriptions },
        { label: 'Kicks', value: counts.kicks },
        { label: 'Bans', value: counts.bans },
    ]);
</script>

<AppHead title="Kick dashboard" />

<div class="flex items-center gap-3">
    <h2 class="text-lg font-semibold">Today</h2>
    {#if is_live}
        <Badge>Live</Badge>
    {:else}
        <Badge variant="secondary">Offline</Badge>
    {/if}
    {#if !connections.channel || !connections.bot}
        <Badge variant="destructive">Connection incomplete</Badge>
    {/if}
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
    {#each tiles as tile (tile.label)}
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-sm text-muted-foreground">
                    {tile.label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p class="text-2xl font-bold">{tile.value}</p>
            </CardContent>
        </Card>
    {/each}
</div>

<Card>
    <CardHeader>
        <CardTitle>Recent activity</CardTitle>
    </CardHeader>
    <CardContent>
        <Deferred data="feed">
            {#snippet fallback()}
                <div class="space-y-2">
                    {#each Array(6) as _, i (i)}
                        <Skeleton class="h-8 w-full" />
                    {/each}
                </div>
            {/snippet}

            {#if feed && feed.length > 0}
                <ul class="divide-y">
                    {#each feed as item (item.at + item.actor)}
                        <li class="flex items-center gap-3 py-2 text-sm">
                            <Badge variant="secondary">{item.type}</Badge>
                            <span class="font-medium">{item.actor}</span>
                            <span class="truncate text-muted-foreground">
                                {item.detail ?? ''}
                            </span>
                        </li>
                    {/each}
                </ul>
            {:else}
                <p class="text-sm text-muted-foreground">No activity yet.</p>
            {/if}
        </Deferred>
    </CardContent>
</Card>
