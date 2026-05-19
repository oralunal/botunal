<script lang="ts">
    import { Deferred, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Pagination from '@/components/kick/Pagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import { Skeleton } from '@/components/ui/skeleton';
    import { formatIstanbul } from '@/lib/datetime';
    import { usePoll } from '@/lib/kick-poll.svelte';
    import { show, unban } from '@/routes/kick/users';
    import type {
        ChatMessageDetailRow,
        KickUserDetail,
        Paginated,
        UserEventItem,
        UserEvents,
    } from '@/types/kick';

    let {
        user,
        filters,
        messages,
        events,
    }: {
        user: KickUserDetail;
        filters: { deleted_only: boolean };
        messages?: Paginated<ChatMessageDetailRow>;
        events?: UserEvents;
    } = $props();

    // svelte-ignore state_referenced_locally
    let deletedOnly = $state(filters.deleted_only);

    usePoll(['user'], 30000);

    const status = $derived(user.ban_status.status);

    function removeRestriction() {
        const label =
            status === 'banned'
                ? `${user.username} kullanıcısının yasağı kaldırılsın mı?`
                : `${user.username} kullanıcısının zaman aşımı kaldırılsın mı?`;

        if (confirm(label)) {
            router.delete(unban(user.id).url, { preserveScroll: true });
        }
    }

    function toggleDeleted() {
        deletedOnly = !deletedOnly;
        router.get(
            show(user.id).url,
            { deleted_only: deletedOnly },
            { preserveScroll: true, preserveState: true },
        );
    }

    function eventBadge(
        item: UserEventItem,
    ): 'secondary' | 'destructive' | 'outline' {
        if (item.type === 'ban') {
            return item.action === 'unban' ? 'secondary' : 'destructive';
        }

        return item.type === 'follow' || item.type === 'subscription'
            ? 'secondary'
            : 'outline';
    }

    function eventLabel(item: UserEventItem): string {
        switch (item.type) {
            case 'follow':
                return 'Kanalı takip etti';
            case 'subscription':
                return [
                    `${item.sub_type ?? 'sub'} abonelik`,
                    item.tier ? `seviye ${item.tier}` : null,
                    item.duration ? `${item.duration} ay` : null,
                    item.gifter_username
                        ? `${item.gifter_username} hediye etti`
                        : null,
                ]
                    .filter(Boolean)
                    .join(' · ');
            case 'gift_sent':
                return [
                    `${item.kicks_amount ?? 0} Kicks`,
                    item.gift_name ? `(${item.gift_name})` : null,
                    item.recipient_username
                        ? `→ ${item.recipient_username}`
                        : null,
                    item.message ? `“${item.message}”` : null,
                ]
                    .filter(Boolean)
                    .join(' ');
            case 'redemption':
                return [
                    item.reward_title ?? 'Ödül',
                    item.reward_cost != null ? `(${item.reward_cost})` : null,
                    item.status ? `· ${item.status}` : null,
                    item.user_input ? `“${item.user_input}”` : null,
                ]
                    .filter(Boolean)
                    .join(' ');
            case 'ban':
                return [
                    item.action ?? 'ban',
                    item.reason ? `· ${item.reason}` : null,
                    item.moderator_username ? `${item.moderator_username} tarafından` : null,
                    item.expires_at
                        ? `${formatIstanbul(item.expires_at)} tarihine kadar`
                        : null,
                    item.source ? `(${item.source})` : null,
                ]
                    .filter(Boolean)
                    .join(' ');
            case 'rename':
                return `${item.previous_username} → ${item.new_username}`;
            default:
                return '';
        }
    }
</script>

<AppHead title={`Kick kullanıcısı · ${user.username}`} />

<Card>
    <CardContent class="space-y-4 pt-6">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-lg font-semibold">{user.username}</h2>
            {#if status === 'banned'}
                <Badge variant="destructive">Yasaklı</Badge>
            {:else if status === 'timed_out'}
                <Badge variant="destructive">
                    {formatIstanbul(user.ban_status.expires_at)} tarihine kadar
                    zaman aşımında
                </Badge>
            {/if}
            {#if status === 'banned' || status === 'timed_out'}
                <Button
                    variant="outline"
                    size="sm"
                    onclick={removeRestriction}
                >
                    {status === 'banned'
                        ? 'Yasağı kaldır'
                        : 'Zaman aşımını kaldır'}
                </Button>
            {/if}
        </div>

        <div
            class="grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2 lg:grid-cols-4"
        >
            <div>
                <span class="text-muted-foreground">Kick kimliği</span><br />
                {user.kick_user_id ?? '—'}
            </div>
            <div>
                <span class="text-muted-foreground">İlk görülme</span><br />
                {formatIstanbul(user.first_seen_at)}
            </div>
            <div>
                <span class="text-muted-foreground">Son aktiflik</span><br />
                {formatIstanbul(user.last_seen_at)}
            </div>
            <div>
                <span class="text-muted-foreground">Eski kullanıcı adları</span
                ><br
                />
                {#if user.former_usernames.length > 0}
                    <span class="flex flex-wrap gap-1">
                        {#each user.former_usernames as name (name)}
                            <Badge variant="outline">{name}</Badge>
                        {/each}
                    </span>
                {:else}
                    —
                {/if}
            </div>
        </div>

        {#if user.kick_user_id === null}
            <p class="text-xs text-muted-foreground">
                Kimlik doğrulanmadı — yalnızca kullanıcı adına göre eşleştirildi.
                Burada gösterilen olaylar bu adı kullanan başka kişileri de
                içerebilir ve moderasyon işlemleri kullanılamaz.
            </p>
        {/if}
    </CardContent>
</Card>

<div class="mt-6 flex items-center justify-between">
    <h3 class="text-base font-semibold">Mesajlar</h3>
    <label class="flex items-center gap-2 text-sm">
        <input
            type="checkbox"
            checked={deletedOnly}
            onchange={toggleDeleted}
        />
        Yalnız silinenler
    </label>
</div>

<div class="mt-2 overflow-x-auto rounded-md border">
    <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
            <tr>
                <th class="px-3 py-2">Zaman</th>
                <th class="px-3 py-2">Mesaj</th>
                <th class="px-3 py-2">Durum</th>
            </tr>
        </thead>
        <tbody>
            <Deferred data="messages">
                {#snippet fallback()}
                    {#each Array(5) as _, i (i)}
                        <tr class="border-t">
                            <td class="px-3 py-2"
                                ><Skeleton class="h-4 w-32" /></td
                            >
                            <td class="px-3 py-2"
                                ><Skeleton class="h-4 w-full" /></td
                            >
                            <td class="px-3 py-2"
                                ><Skeleton class="h-4 w-16" /></td
                            >
                        </tr>
                    {/each}
                {/snippet}

                {#if messages}
                    {#each messages.data as message (message.id)}
                        <tr class="border-t">
                            <td
                                class="px-3 py-2 whitespace-nowrap text-muted-foreground"
                            >
                                {formatIstanbul(message.sent_at)}
                            </td>
                            <td
                                class="px-3 py-2 {message.deleted_at
                                    ? 'text-muted-foreground line-through'
                                    : ''}"
                            >
                                {message.content}
                            </td>
                            <td class="px-3 py-2">
                                {#if message.deleted_at}
                                    <Badge variant="secondary">silindi</Badge>
                                {/if}
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td
                                colspan="3"
                                class="px-3 py-6 text-center text-muted-foreground"
                            >
                                Mesaj yok.
                            </td>
                        </tr>
                    {/each}
                {/if}
            </Deferred>
        </tbody>
    </table>
</div>

{#if messages}
    <Pagination links={messages.links} />
{/if}

<h3 class="mt-6 text-base font-semibold">Etkinlik</h3>

<Card class="mt-2">
    <CardContent class="pt-6">
        <Deferred data="events">
            {#snippet fallback()}
                <div class="space-y-2">
                    {#each Array(6) as _, i (i)}
                        <Skeleton class="h-8 w-full" />
                    {/each}
                </div>
            {/snippet}

            {#if events}
                {#if events.items.length > 0}
                    <ul class="divide-y">
                        {#each events.items as item, i (i)}
                            <li
                                class="flex flex-wrap items-center gap-3 py-2 text-sm"
                            >
                                <span
                                    class="w-40 shrink-0 whitespace-nowrap text-muted-foreground"
                                >
                                    {formatIstanbul(item.at)}
                                </span>
                                <Badge variant={eventBadge(item)}>
                                    {item.type}
                                </Badge>
                                <span class="text-muted-foreground">
                                    {eventLabel(item)}
                                </span>
                            </li>
                        {/each}
                    </ul>
                    {#if events.truncated}
                        <p class="mt-3 text-xs text-muted-foreground">
                            En son 200 olay gösteriliyor.
                        </p>
                    {/if}
                {:else}
                    <p class="text-sm text-muted-foreground">
                        Kaydedilmiş etkinlik yok.
                    </p>
                {/if}
            {/if}
        </Deferred>
    </CardContent>
</Card>
