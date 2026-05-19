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
    import { disconnect } from '@/routes/kick/connections';
    import { redirect } from '@/routes/kick/oauth';
    import type { KickConnectionView } from '@/types/kick';

    let {
        channel,
        bot,
    }: {
        channel: KickConnectionView;
        bot: KickConnectionView;
    } = $props();

    const cards = $derived([
        {
            conn: channel,
            title: 'Kanal (TROLUNAL)',
            description: 'Webhook etkinliklerini alır ve moderasyon yapar.',
        },
        {
            conn: bot,
            title: 'Bot (botunal)',
            description: 'Sohbet mesajlarını bot hesabıyla gönderir.',
        },
    ]);

    function remove(type: 'channel' | 'bot') {
        if (confirm(`${type} hesabının bağlantısı kesilsin mi?`)) {
            router.delete(disconnect(type).url);
        }
    }
</script>

<AppHead title="Kick bağlantıları" />

<div class="grid gap-6 md:grid-cols-2">
    {#each cards as { conn, title, description } (conn.type)}
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>{title}</CardTitle>
                    {#if conn.connected && !conn.is_expired}
                        <Badge>Bağlı</Badge>
                    {:else if conn.connected && conn.is_expired}
                        <Badge variant="destructive">Jeton süresi doldu</Badge>
                    {:else}
                        <Badge variant="secondary">Bağlı değil</Badge>
                    {/if}
                </div>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                {#if conn.connected}
                    <dl class="text-sm">
                        <div class="flex justify-between py-1">
                            <dt class="text-muted-foreground">Hesap</dt>
                            <dd>{conn.display_name ?? conn.slug ?? '—'}</dd>
                        </div>
                        {#if conn.type === 'channel'}
                            <div class="flex justify-between py-1">
                                <dt class="text-muted-foreground">
                                    Yayıncı kimliği
                                </dt>
                                <dd>{conn.broadcaster_user_id ?? '—'}</dd>
                            </div>
                        {/if}
                    </dl>
                {/if}

                {#if conn.missing_scopes.length > 0}
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-destructive">
                            Eksik izinler
                        </p>
                        <div class="flex flex-wrap gap-1">
                            {#each conn.missing_scopes as scope (scope)}
                                <Badge variant="destructive">{scope}</Badge>
                            {/each}
                        </div>
                    </div>
                {:else if conn.connected}
                    <p class="text-sm text-muted-foreground">
                        Gerekli tüm izinler verildi.
                    </p>
                {/if}

                <div class="flex gap-2">
                    <Button asChild>
                        {#snippet children(props)}
                            <a
                                href={redirect(conn.type).url}
                                class={props.class}
                            >
                                {conn.connected ? 'Yeniden bağlan' : 'Bağlan'}
                            </a>
                        {/snippet}
                    </Button>
                    {#if conn.connected}
                        <Button
                            variant="outline"
                            onclick={() => remove(conn.type)}
                        >
                            Bağlantıyı kes
                        </Button>
                    {/if}
                </div>
            </CardContent>
        </Card>
    {/each}
</div>
