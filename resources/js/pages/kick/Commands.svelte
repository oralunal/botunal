<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { destroy, store, update } from '@/routes/kick/commands';

    type Command = {
        id: number;
        name: string;
        type: 'static' | 'dynamic';
        handler: string | null;
        response: string | null;
        permission: string;
        cooldown_seconds: number;
        user_cooldown_seconds: number;
        is_enabled: boolean;
        reply_in_thread: boolean;
        aliases: { id: number; alias: string }[];
    };

    let {
        commands,
        handlers,
    }: {
        commands: Command[];
        handlers: string[];
    } = $props();

    const blank = {
        id: null as number | null,
        name: '',
        type: 'static' as 'static' | 'dynamic',
        handler: '',
        response: '',
        permission: 'everyone',
        cooldown_seconds: 5,
        user_cooldown_seconds: 0,
        is_enabled: true,
        reply_in_thread: false,
        aliases: '',
    };

    let form = $state({ ...blank });
    let editing = $state(false);

    function newCommand() {
        form = { ...blank };
        editing = true;
    }

    function edit(command: Command) {
        form = {
            ...command,
            handler: command.handler ?? '',
            response: command.response ?? '',
            aliases: command.aliases.map((a) => a.alias).join(', '),
        };
        editing = true;
    }

    function payload() {
        return {
            ...form,
            aliases: form.aliases
                .split(',')
                .map((a) => a.trim())
                .filter(Boolean),
        };
    }

    function save() {
        const options = { onSuccess: () => (editing = false) };

        if (form.id) {
            router.put(update(form.id).url, payload(), options);
        } else {
            router.post(store().url, payload(), options);
        }
    }

    function remove(command: Command) {
        if (confirm(`!${command.name} silinsin mi?`)) {
            router.delete(destroy(command.id).url);
        }
    }
</script>

<AppHead title="Kick komutları" />

<div class="flex items-center justify-between">
    <h2 class="text-lg font-semibold">Komutlar</h2>
    <Button onclick={newCommand}>Yeni komut</Button>
</div>

{#if editing}
    <Card>
        <CardContent class="space-y-4 pt-6">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1">
                    <Label for="name">Ad (önek olmadan)</Label>
                    <Input
                        id="name"
                        value={form.name}
                        oninput={(e) => (form.name = e.currentTarget.value)}
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="type">Tür</Label>
                    <select
                        id="type"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        bind:value={form.type}
                    >
                        <option value="static">statik</option>
                        <option value="dynamic">dinamik</option>
                    </select>
                </div>
            </div>

            {#if form.type === 'static'}
                <div class="grid gap-1">
                    <Label for="response">Yanıt</Label>
                    <textarea
                        id="response"
                        rows="3"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                        bind:value={form.response}
                    ></textarea>
                    <p class="text-xs text-muted-foreground">
                        Yer tutucular: {'{user}'}
                        {'{channel}'}
                        {'{args}'}
                        {'{1}'}
                        {'{count}'}
                        {'{uptime}'}
                        {'{random.1-100}'}
                    </p>
                </div>
            {:else}
                <div class="grid gap-1">
                    <Label for="handler">Yerleşik işleyici</Label>
                    <select
                        id="handler"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        bind:value={form.handler}
                    >
                        <option value="" disabled>Seç…</option>
                        {#each handlers as handler (handler)}
                            <option value={handler}>{handler}</option>
                        {/each}
                    </select>
                </div>
            {/if}

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="grid gap-1">
                    <Label for="permission">Yetki</Label>
                    <select
                        id="permission"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        bind:value={form.permission}
                    >
                        <option value="everyone">herkes</option>
                        <option value="subscriber">abone</option>
                        <option value="moderator">moderatör</option>
                        <option value="broadcaster">yayıncı</option>
                    </select>
                </div>
                <div class="grid gap-1">
                    <Label for="cooldown">Bekleme (sn)</Label>
                    <Input
                        id="cooldown"
                        type="number"
                        value={form.cooldown_seconds}
                        oninput={(e) =>
                            (form.cooldown_seconds = Number(
                                e.currentTarget.value,
                            ))}
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="ucooldown">Kullanıcı başına bekleme (sn)</Label>
                    <Input
                        id="ucooldown"
                        type="number"
                        value={form.user_cooldown_seconds}
                        oninput={(e) =>
                            (form.user_cooldown_seconds = Number(
                                e.currentTarget.value,
                            ))}
                    />
                </div>
            </div>

            <div class="grid gap-1">
                <Label for="aliases">Takma adlar (virgülle ayrılmış)</Label>
                <Input
                    id="aliases"
                    value={form.aliases}
                    oninput={(e) => (form.aliases = e.currentTarget.value)}
                />
            </div>

            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <input type="checkbox" bind:checked={form.is_enabled} />
                    Etkin
                </label>
                <label class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        bind:checked={form.reply_in_thread}
                    />
                    Konu içinde yanıtla
                </label>
            </div>

            <div class="flex gap-2">
                <Button onclick={save}>Kaydet</Button>
                <Button variant="outline" onclick={() => (editing = false)}>
                    İptal
                </Button>
            </div>
        </CardContent>
    </Card>
{/if}

<div class="mt-4 overflow-x-auto rounded-md border">
    <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
            <tr>
                <th class="px-3 py-2">Ad</th>
                <th class="px-3 py-2">Tür</th>
                <th class="px-3 py-2">Yetki</th>
                <th class="px-3 py-2">Bekleme</th>
                <th class="px-3 py-2">Durum</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody>
            {#each commands as command (command.id)}
                <tr class="border-t">
                    <td class="px-3 py-2 font-medium">
                        !{command.name}
                        {#each command.aliases as alias (alias.id)}
                            <Badge variant="secondary" class="ml-1">
                                {alias.alias}
                            </Badge>
                        {/each}
                    </td>
                    <td class="px-3 py-2">{command.type}</td>
                    <td class="px-3 py-2">{command.permission}</td>
                    <td class="px-3 py-2">{command.cooldown_seconds}s</td>
                    <td class="px-3 py-2">
                        {#if command.is_enabled}
                            <Badge>etkin</Badge>
                        {:else}
                            <Badge variant="secondary">devre dışı</Badge>
                        {/if}
                    </td>
                    <td class="px-3 py-2 text-right">
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => edit(command)}
                        >
                            Düzenle
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => remove(command)}
                        >
                            Sil
                        </Button>
                    </td>
                </tr>
            {:else}
                <tr>
                    <td
                        colspan="6"
                        class="px-3 py-6 text-center text-muted-foreground"
                    >
                        Henüz komut yok.
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>
