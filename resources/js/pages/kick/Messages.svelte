<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Pagination from '@/components/kick/Pagination.svelte';
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
        if (confirm('Delete this message from chat?')) {
            router.delete(deleteMessage().url, {
                data: { message_id: row.kick_message_id },
                preserveScroll: true,
            });
        }
    }

    function banSender(row: ChatMessageRow, minutes: number | null) {
        const label = minutes
            ? `Timeout ${row.sender_username}?`
            : `Ban ${row.sender_username}?`;

        if (confirm(label)) {
            router.post(ban().url, {
                target: row.sender_username,
                duration_minutes: minutes,
                reason: null,
            });
        }
    }
</script>

<AppHead title="Kick messages" />

<Card>
    <CardContent class="pt-6">
        <form class="grid gap-3 sm:grid-cols-4" onsubmit={applyFilters}>
            <div class="grid gap-1">
                <Label for="username">Username</Label>
                <Input
                    id="username"
                    value={username}
                    oninput={(e) => (username = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="contains">Contains</Label>
                <Input
                    id="contains"
                    value={contains}
                    oninput={(e) => (contains = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="date">Date</Label>
                <Input
                    id="date"
                    type="date"
                    value={date}
                    oninput={(e) => (date = e.currentTarget.value)}
                />
            </div>
            <div class="flex items-end">
                <Button type="submit">Filter</Button>
            </div>
        </form>
    </CardContent>
</Card>

<div class="mt-4 overflow-x-auto rounded-md border">
    <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
            <tr>
                <th class="px-3 py-2">Time</th>
                <th class="px-3 py-2">User</th>
                <th class="px-3 py-2">Message</th>
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
                    <td class="px-3 py-2 font-medium">
                        {message.sender_username}
                    </td>
                    <td class="px-3 py-2">{message.content}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => removeMessage(message)}
                        >
                            Delete
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => banSender(message, 10)}
                        >
                            Timeout
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => banSender(message, null)}
                        >
                            Ban
                        </Button>
                    </td>
                </tr>
            {:else}
                <tr>
                    <td
                        colspan="4"
                        class="px-3 py-6 text-center text-muted-foreground"
                    >
                        No messages.
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>

<Pagination links={messages.links} />
