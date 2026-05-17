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
    import { index as usersRoute, show } from '@/routes/kick/users';
    import type { KickUserRow, Paginated } from '@/types/kick';

    let {
        users,
        filters,
    }: {
        users: Paginated<KickUserRow>;
        filters: { username: string | null };
    } = $props();

    // Seeded once from the server, then edited locally.
    // svelte-ignore state_referenced_locally
    let username = $state(filters.username ?? '');

    usePoll(['users'], 30000);

    function applyFilters(event: SubmitEvent) {
        event.preventDefault();
        router.get(
            usersRoute().url,
            { username },
            { preserveState: true, preserveScroll: true },
        );
    }

    function open(user: KickUserRow) {
        router.get(show(user.id).url);
    }
</script>

<AppHead title="Kick users" />

<Card>
    <CardContent class="pt-6">
        <form class="grid gap-3 sm:grid-cols-4" onsubmit={applyFilters}>
            <div class="grid gap-1 sm:col-span-3">
                <Label for="username">Username (current or former)</Label>
                <Input
                    id="username"
                    value={username}
                    oninput={(e) => (username = e.currentTarget.value)}
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
                <th class="px-3 py-2">User</th>
                <th class="px-3 py-2">Kick ID</th>
                <th class="px-3 py-2">First seen</th>
                <th class="px-3 py-2">Last active</th>
            </tr>
        </thead>
        <tbody>
            {#each users.data as user (user.id)}
                <tr
                    class="cursor-pointer border-t hover:bg-muted/30"
                    onclick={() => open(user)}
                >
                    <td class="px-3 py-2 font-medium">{user.username}</td>
                    <td class="px-3 py-2 text-muted-foreground">
                        {user.kick_user_id ?? '—'}
                    </td>
                    <td
                        class="px-3 py-2 whitespace-nowrap text-muted-foreground"
                    >
                        {formatIstanbul(user.first_seen_at)}
                    </td>
                    <td
                        class="px-3 py-2 whitespace-nowrap text-muted-foreground"
                    >
                        {formatIstanbul(user.last_seen_at)}
                    </td>
                </tr>
            {:else}
                <tr>
                    <td
                        colspan="4"
                        class="px-3 py-6 text-center text-muted-foreground"
                    >
                        No users.
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>

<Pagination links={users.links} />
