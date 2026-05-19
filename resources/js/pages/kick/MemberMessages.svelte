<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Pagination from '@/components/kick/Pagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { formatIstanbul } from '@/lib/datetime';
    import {
        index as memberMessagesIndex,
        read,
        unread,
    } from '@/routes/kick/member-messages';
    import type { AdminMemberMessageRow, PaginatedMessages } from '@/types';

    let {
        messages,
        filters,
    }: {
        messages: PaginatedMessages<AdminMemberMessageRow>;
        filters: { unread: boolean };
    } = $props();

    function toggleRead(message: AdminMemberMessageRow) {
        const action = message.is_read ? unread : read;
        router.patch(
            action(message.id).url,
            {},
            { preserveScroll: true, preserveState: true },
        );
    }

    function setUnreadFilter(value: boolean) {
        router.get(
            memberMessagesIndex().url,
            { unread: value },
            { preserveState: true, preserveScroll: true },
        );
    }
</script>

<AppHead title="Üye Mesajları" />

<div class="flex items-center justify-between">
    <h2 class="text-lg font-semibold">Üye Mesajları</h2>
    <label class="flex items-center gap-2 text-sm">
        <input
            type="checkbox"
            checked={filters.unread}
            onchange={(e) => setUnreadFilter(e.currentTarget.checked)}
        />
        Sadece okunmamış
    </label>
</div>

<div class="mt-4 overflow-x-auto rounded-md border">
    <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
            <tr>
                <th class="px-3 py-2">Gönderen</th>
                <th class="px-3 py-2">Mesaj</th>
                <th class="px-3 py-2">Tarih</th>
                <th class="px-3 py-2">Durum</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody>
            {#each messages.data as message (message.id)}
                <tr class="border-t">
                    <td class="px-3 py-2">
                        {#if message.user}
                            <div class="font-medium">{message.user.name}</div>
                            {#if message.user.kick_username}
                                <div class="text-muted-foreground">
                                    @{message.user.kick_username}
                                </div>
                            {/if}
                            <div class="text-xs text-muted-foreground">
                                {message.user.email}
                            </div>
                        {:else}
                            <span class="text-muted-foreground">—</span>
                        {/if}
                    </td>
                    <td class="px-3 py-2">{message.body}</td>
                    <td
                        class="px-3 py-2 whitespace-nowrap text-muted-foreground"
                    >
                        {formatIstanbul(message.created_at)}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        {#if message.is_read}
                            <Badge variant="secondary">Okundu</Badge>
                        {:else}
                            <Badge variant="outline">Okunmadı</Badge>
                        {/if}
                    </td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => toggleRead(message)}
                        >
                            {message.is_read
                                ? 'Okunmadı yap'
                                : 'Okundu olarak işaretle'}
                        </Button>
                    </td>
                </tr>
            {:else}
                <tr>
                    <td
                        colspan="5"
                        class="px-3 py-6 text-center text-muted-foreground"
                    >
                        Mesaj yok.
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>

<Pagination links={messages.links} />
