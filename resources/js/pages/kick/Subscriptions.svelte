<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { destroy, store, sync } from '@/routes/kick/subscriptions';

    type Subscription = {
        id: number;
        kick_subscription_id: string | null;
        event_name: string;
        event_version: number;
        status: string;
        last_synced_at: string | null;
    };

    let {
        subscriptions,
        expected_events,
        channel_connected,
    }: {
        subscriptions: Subscription[];
        expected_events: string[];
        channel_connected: boolean;
    } = $props();

    const activeNames = $derived(
        new Set(
            subscriptions
                .filter((s) => s.status === 'active')
                .map((s) => s.event_name),
        ),
    );
</script>

<AppHead title="Kick abonelikleri" />

<Card>
    <CardHeader>
        <CardTitle>Webhook abonelikleri</CardTitle>
        <CardDescription>
            Uygulamayı işleyebileceği tüm Kick etkinliklerine abone et.
        </CardDescription>
    </CardHeader>
    <CardContent class="space-y-4">
        {#if !channel_connected}
            <Badge variant="destructive">
                Önce kanal hesabını bağla
            </Badge>
        {/if}

        <div class="flex gap-2">
            <Button
                disabled={!channel_connected}
                onclick={() => router.post(store().url)}
            >
                Tüm etkinliklere abone ol
            </Button>
            <Button
                variant="outline"
                disabled={!channel_connected}
                onclick={() => router.post(sync().url)}
            >
                Kick ile eşitle
            </Button>
        </div>

        <div class="space-y-2">
            {#each expected_events as name (name)}
                {@const sub = subscriptions.find((s) => s.event_name === name)}
                <div
                    class="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                >
                    <span class="font-mono">{name}</span>
                    <div class="flex items-center gap-2">
                        {#if activeNames.has(name)}
                            <Badge>etkin</Badge>
                        {:else}
                            <Badge variant="secondary">eksik</Badge>
                        {/if}
                        {#if sub}
                            <Button
                                variant="ghost"
                                size="sm"
                                onclick={() =>
                                    router.delete(destroy(sub.id).url)}
                            >
                                Kaldır
                            </Button>
                        {/if}
                    </div>
                </div>
            {/each}
        </div>
    </CardContent>
</Card>
