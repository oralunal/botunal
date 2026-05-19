<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Panel',
                href: dashboard(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { formatIstanbul, formatIstanbulDate } from '@/lib/datetime';
    import { dashboard as kickDashboard } from '@/routes/kick';
    import { toUrl } from '@/lib/utils';

    type MessageRow = { content: string; sent_at: string | null };
    type SubscriptionRow = {
        type: string;
        tier: string | null;
        gifter_username: string | null;
        duration: number | null;
        occurred_at: string | null;
    };
    type GiftRow = {
        gift_name: string | null;
        kicks_amount: number;
        occurred_at: string | null;
    };
    type FollowRow = { followed_at: string | null };

    let {
        isKickMember,
        kick_username = null,
        follow = null,
        recent_messages = [],
        recent_subscriptions = [],
        recent_gifts = [],
    }: {
        isKickMember: boolean;
        kick_username?: string | null;
        follow?: FollowRow | null;
        recent_messages?: MessageRow[];
        recent_subscriptions?: SubscriptionRow[];
        recent_gifts?: GiftRow[];
    } = $props();

    const userName = $derived(page.props.auth?.user?.name ?? '');
    const isSuperAdmin = $derived(page.props.auth?.is_super_admin === true);
</script>

<AppHead title="Panel" />

<div class="flex flex-1 flex-col gap-4 p-4">
    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <h2 class="text-xl font-semibold">
            Merhaba{userName ? `, ${userName}` : ''}
        </h2>
        {#if isKickMember && kick_username}
            <span class="text-sm text-muted-foreground">@{kick_username}</span>
        {/if}
    </div>

    {#if isKickMember}
        <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground">
                        Takip durumu
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {#if follow?.followed_at}
                        <p class="text-lg font-semibold">
                            {formatIstanbulDate(follow.followed_at)}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            tarihinden beri takipte
                        </p>
                    {:else}
                        <p class="text-sm text-muted-foreground">
                            Kanalı henüz takip etmiyorsun.
                        </p>
                    {/if}
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground">
                        Toplam aboneliklerim
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold">
                        {recent_subscriptions.length}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        son 5 kayıt görüntüleniyor
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground">
                        Gönderdiğim hediyeler
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold">
                        {recent_gifts.reduce(
                            (sum, g) => sum + g.kicks_amount,
                            0,
                        )}
                        <span class="text-sm font-normal text-muted-foreground">
                            Kicks
                        </span>
                    </p>
                    <p class="text-xs text-muted-foreground">
                        son 5 hediye toplamı
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Son 10 mesajım</CardTitle>
                </CardHeader>
                <CardContent>
                    {#if recent_messages.length > 0}
                        <ul class="divide-y">
                            {#each recent_messages as msg, i (i)}
                                <li class="space-y-1 py-2 text-sm">
                                    <p class="text-foreground">{msg.content}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {formatIstanbul(msg.sent_at)}
                                    </p>
                                </li>
                            {/each}
                        </ul>
                    {:else}
                        <p class="text-sm text-muted-foreground">
                            Henüz kanalda mesaj göndermemişsin.
                        </p>
                    {/if}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Son aktivitem</CardTitle>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div>
                        <h4 class="mb-2 text-sm font-semibold">Abonelikler</h4>
                        {#if recent_subscriptions.length > 0}
                            <ul class="space-y-2 text-sm">
                                {#each recent_subscriptions as sub, i (i)}
                                    <li
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Badge variant="secondary">
                                                {sub.type}
                                            </Badge>
                                            {#if sub.tier}
                                                <span>Tier {sub.tier}</span>
                                            {/if}
                                            {#if sub.duration}
                                                <span
                                                    class="text-muted-foreground"
                                                >
                                                    · {sub.duration} ay
                                                </span>
                                            {/if}
                                            {#if sub.gifter_username}
                                                <span
                                                    class="text-muted-foreground"
                                                >
                                                    · hediye eden: @{sub.gifter_username}
                                                </span>
                                            {/if}
                                        </div>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {formatIstanbulDate(sub.occurred_at)}
                                        </span>
                                    </li>
                                {/each}
                            </ul>
                        {:else}
                            <p class="text-sm text-muted-foreground">
                                Abonelik kaydın yok.
                            </p>
                        {/if}
                    </div>

                    <div>
                        <h4 class="mb-2 text-sm font-semibold">
                            Gönderdiğim hediyeler
                        </h4>
                        {#if recent_gifts.length > 0}
                            <ul class="space-y-2 text-sm">
                                {#each recent_gifts as gift, i (i)}
                                    <li
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Badge>
                                                {gift.kicks_amount} Kicks
                                            </Badge>
                                            {#if gift.gift_name}
                                                <span>{gift.gift_name}</span>
                                            {/if}
                                        </div>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {formatIstanbulDate(gift.occurred_at)}
                                        </span>
                                    </li>
                                {/each}
                            </ul>
                        {:else}
                            <p class="text-sm text-muted-foreground">
                                Henüz hediye göndermemişsin.
                            </p>
                        {/if}
                    </div>
                </CardContent>
            </Card>
        </div>
    {:else}
        <Card>
            <CardHeader>
                <CardTitle>Hoş geldin</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm text-muted-foreground">
                <p>Yönetici hesabıyla giriş yaptın.</p>
                {#if isSuperAdmin}
                    <p>
                        <Link
                            href={toUrl(kickDashboard())}
                            class="font-medium text-foreground underline-offset-4 hover:underline"
                        >
                            Kick paneline git →
                        </Link>
                    </p>
                {/if}
            </CardContent>
        </Card>
    {/if}
</div>
