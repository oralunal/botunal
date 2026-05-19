<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import MemberController from '@/actions/App/Http/Controllers/Kick/MemberController';
    import AppHead from '@/components/AppHead.svelte';
    import Pagination from '@/components/kick/Pagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import type {
        AdminMemberRow,
        PaginatedMessages,
        PermissionRegistry,
    } from '@/types';

    let {
        users,
        registry,
    }: {
        users: PaginatedMessages<AdminMemberRow>;
        registry: PermissionRegistry;
    } = $props();

    const groups = $derived(Object.entries(registry));

    let selected = $state<Record<number, Set<string>>>({});

    function stateFor(user: AdminMemberRow): Set<string> {
        if (!selected[user.id]) {
            selected[user.id] = new Set(user.permissions);
        }

        return selected[user.id];
    }

    function isChecked(user: AdminMemberRow, ability: string): boolean {
        if (user.is_super_admin) {
            return true;
        }

        return stateFor(user).has(ability);
    }

    function toggle(user: AdminMemberRow, ability: string, on: boolean) {
        const set = new Set(stateFor(user));
        if (on) {
            set.add(ability);
        } else {
            set.delete(ability);
        }
        selected[user.id] = set;
    }

    function save(user: AdminMemberRow) {
        router.patch(
            MemberController.update(user.id).url,
            { abilities: Array.from(stateFor(user)) },
            { preserveScroll: true },
        );
    }

    function fullName(user: AdminMemberRow): string {
        return (
            [user.first_name, user.last_name].filter(Boolean).join(' ') ||
            user.name
        );
    }
</script>

<AppHead title="Üyeler" />

<div class="flex items-center justify-between">
    <h2 class="text-lg font-semibold">Üyeler</h2>
</div>

<div class="mt-4 space-y-4">
    {#each users.data as user (user.id)}
        <Card>
            <CardContent class="space-y-4 pt-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{fullName(user)}</span>
                            {#if user.is_super_admin}
                                <Badge>Süper yönetici</Badge>
                            {/if}
                        </div>
                        {#if user.kick_username}
                            <div class="text-sm text-muted-foreground">
                                @{user.kick_username}
                            </div>
                        {/if}
                        <div class="text-sm text-muted-foreground">
                            {user.email}
                        </div>
                        <div
                            class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                        >
                            {#if user.phone}
                                <span>Tel: {user.phone}</span>
                            {/if}
                            {#if user.instagram}
                                <span>Instagram: {user.instagram}</span>
                            {/if}
                            {#if user.twitter}
                                <span>Twitter: {user.twitter}</span>
                            {/if}
                        </div>
                    </div>
                    {#if !user.is_super_admin}
                        <Button size="sm" onclick={() => save(user)}>
                            Kaydet
                        </Button>
                    {/if}
                </div>

                {#if user.is_super_admin}
                    <p class="text-sm text-muted-foreground">
                        Süper yönetici — tüm yetkiler. Bu kullanıcının izinleri
                        değiştirilemez.
                    </p>
                {/if}

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {#each groups as [groupLabel, abilities] (groupLabel)}
                        <div class="rounded-md border p-3">
                            <div class="mb-2 text-sm font-semibold">
                                {groupLabel}
                            </div>
                            <div class="space-y-1">
                                {#each Object.entries(abilities) as [ability, itemLabel] (ability)}
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={isChecked(user, ability)}
                                            disabled={user.is_super_admin}
                                            onchange={(e) =>
                                                toggle(
                                                    user,
                                                    ability,
                                                    e.currentTarget.checked,
                                                )}
                                        />
                                        {itemLabel}
                                    </label>
                                {/each}
                            </div>
                        </div>
                    {/each}
                </div>
            </CardContent>
        </Card>
    {:else}
        <Card>
            <CardContent class="py-6 text-center text-muted-foreground">
                Üye yok.
            </CardContent>
        </Card>
    {/each}
</div>

<Pagination links={users.links} />
