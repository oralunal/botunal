<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Pagination from '@/components/kick/Pagination.svelte';
    import UserLink from '@/components/kick/UserLink.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { formatIstanbul } from '@/lib/datetime';
    import { usePoll } from '@/lib/kick-poll.svelte';
    import { messages as messagesRoute } from '@/routes/kick';
    import { ban, message as deleteMessage } from '@/routes/kick/moderation';
    import type { ChatMessageRow, Paginated } from '@/types/kick';

    let {
        messages,
        filters,
    }: {
        messages: Paginated<ChatMessageRow>;
        filters: {
            username: string | null;
            contains: string | null;
            date: string | null;
        };
    } = $props();

    // Filter inputs are seeded once from the server, then edited locally.
    // svelte-ignore state_referenced_locally
    let username = $state(filters.username ?? '');
    // svelte-ignore state_referenced_locally
    let contains = $state(filters.contains ?? '');
    // svelte-ignore state_referenced_locally
    let date = $state(filters.date ?? '');

    usePoll(['messages'], 15000);

    function applyFilters(event: SubmitEvent) {
        event.preventDefault();
        router.get(
            messagesRoute().url,
            { username, contains, date },
            { preserveState: true, preserveScroll: true },
        );
    }

    function removeMessage(row: ChatMessageRow) {
        if (confirm('Bu mesaj sohbetten silinsin mi?')) {
            router.delete(deleteMessage().url, {
                data: { message_id: row.kick_message_id },
                preserveScroll: true,
            });
        }
    }

    function banSender(row: ChatMessageRow, minutes: number | null) {
        const label = minutes
            ? `${row.sender_username} için zaman aşımı uygulansın mı?`
            : `${row.sender_username} yasaklansın mı?`;

        if (confirm(label)) {
            router.post(ban().url, {
                target: row.sender_username,
                duration_minutes: minutes,
                reason: null,
            });
        }
    }
</script>

<AppHead title="Kick mesajları" />

<Card>
    <CardContent class="pt-6">
        <form class="grid gap-3 sm:grid-cols-4" onsubmit={applyFilters}>
            <div class="grid gap-1">
                <Label for="username">Kullanıcı adı</Label>
                <Input
                    id="username"
                    value={username}
                    oninput={(e) => (username = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="contains">İçerir</Label>
                <Input
                    id="contains"
                    value={contains}
                    oninput={(e) => (contains = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="date">Tarih</Label>
                <Input
                    id="date"
                    type="date"
                    value={date}
                    oninput={(e) => (date = e.currentTarget.value)}
                />
            </div>
            <div class="flex items-end">
                <Button type="submit">Filtrele</Button>
            </div>
        </form>
    </CardContent>
</Card>

<div class="mt-4 overflow-x-auto rounded-md border">
    <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
            <tr>
                <th class="px-3 py-2">Zaman</th>
                <th class="px-3 py-2">Kullanıcı</th>
                <th class="px-3 py-2">Mesaj</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody>
            {#each messages.data as message (message.id)}
                <tr class="border-t">
                    <td
                        class="px-3 py-2 whitespace-nowrap text-muted-foreground"
                    >
                        {formatIstanbul(message.sent_at)}
                    </td>
                    <td class="px-3 py-2">
                        <UserLink
                            username={message.sender_username}
                            class="font-medium"
                        />
                    </td>
                    <td
                        class="px-3 py-2 {message.deleted_at
                            ? 'text-muted-foreground line-through'
                            : ''}"
                    >
                        {message.content}
                        {#if message.deleted_at}
                            <Badge variant="secondary" class="ml-2 no-underline"
                                >silindi</Badge
                            >
                        {/if}
                    </td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">
                        {#if !message.deleted_at}
                            <Button
                                variant="ghost"
                                size="sm"
                                onclick={() => removeMessage(message)}
                            >
                                Sil
                            </Button>
                        {/if}
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => banSender(message, 10)}
                        >
                            Zaman aşımı
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => banSender(message, null)}
                        >
                            Yasakla
                        </Button>
                    </td>
                </tr>
            {:else}
                <tr>
                    <td
                        colspan="4"
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
