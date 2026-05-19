<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import UserLink from '@/components/kick/UserLink.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { formatIstanbul } from '@/lib/datetime';
    import { usePoll } from '@/lib/kick-poll.svelte';
    import { ban, unban } from '@/routes/kick/moderation';

    type Ban = {
        id: number;
        target_username: string;
        moderator_username: string | null;
        action: string;
        reason: string | null;
        expires_at: string | null;
        source: string;
        occurred_at: string | null;
    };

    let { recent_bans }: { recent_bans: Ban[] } = $props();

    let target = $state('');
    let duration = $state('');
    let reason = $state('');

    usePoll(['recent_bans'], 15000);

    function doBan() {
        if (!target) {
            return;
        }

        router.post(ban().url, {
            target,
            duration_minutes: duration ? Number(duration) : null,
            reason: reason || null,
        });
    }

    function doUnban() {
        if (target && confirm(`${target} kullanıcısının yasağı kaldırılsın mı?`)) {
            router.delete(unban().url, { data: { target } });
        }
    }
</script>

<AppHead title="Kick moderasyonu" />

<Card>
    <CardContent class="space-y-4 pt-6">
        <h2 class="text-lg font-semibold">Moderasyon işlemi</h2>
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="grid gap-1">
                <Label for="target">Kullanıcı adı veya kullanıcı kimliği</Label>
                <Input
                    id="target"
                    value={target}
                    oninput={(e) => (target = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="duration">Zaman aşımı dakikası (boş = yasak)</Label>
                <Input
                    id="duration"
                    type="number"
                    value={duration}
                    oninput={(e) => (duration = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="reason">Sebep</Label>
                <Input
                    id="reason"
                    value={reason}
                    oninput={(e) => (reason = e.currentTarget.value)}
                />
            </div>
        </div>
        <div class="flex gap-2">
            <Button onclick={doBan}>Yasakla / Zaman aşımı</Button>
            <Button variant="outline" onclick={doUnban}>Yasağı kaldır</Button>
        </div>
        <p class="text-xs text-muted-foreground">
            Kullanıcı adları sohbet geçmişinden çözümlenir. Kullanıcı hiç
            sohbet etmediyse sayısal Kick kullanıcı kimliğini gir.
        </p>
    </CardContent>
</Card>

<div class="mt-4 overflow-x-auto rounded-md border">
    <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
            <tr>
                <th class="px-3 py-2">Zaman</th>
                <th class="px-3 py-2">Kullanıcı</th>
                <th class="px-3 py-2">İşlem</th>
                <th class="px-3 py-2">Yapan</th>
                <th class="px-3 py-2">Kaynak</th>
                <th class="px-3 py-2">Sebep</th>
            </tr>
        </thead>
        <tbody>
            {#each recent_bans as row (row.id)}
                <tr class="border-t">
                    <td
                        class="px-3 py-2 whitespace-nowrap text-muted-foreground"
                    >
                        {formatIstanbul(row.occurred_at)}
                    </td>
                    <td class="px-3 py-2">
                        <UserLink
                            username={row.target_username}
                            class="font-medium"
                        />
                    </td>
                    <td class="px-3 py-2">
                        <Badge
                            variant={row.action === 'unban'
                                ? 'secondary'
                                : 'destructive'}
                        >
                            {row.action}
                        </Badge>
                    </td>
                    <td class="px-3 py-2">
                        <UserLink username={row.moderator_username} />
                    </td>
                    <td class="px-3 py-2">{row.source}</td>
                    <td class="px-3 py-2 text-muted-foreground">
                        {row.reason ?? '—'}
                    </td>
                </tr>
            {:else}
                <tr>
                    <td
                        colspan="6"
                        class="px-3 py-6 text-center text-muted-foreground"
                    >
                        Henüz moderasyon etkinliği yok.
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>
