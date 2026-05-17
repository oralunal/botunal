<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { destroy, store, update } from '@/routes/kick/timers';

    type Timer = {
        id: number;
        name: string;
        message: string;
        interval_seconds: number;
        min_messages_between: number;
        only_when_live: boolean;
        is_enabled: boolean;
    };

    let { timers }: { timers: Timer[] } = $props();

    const blank = {
        id: null as number | null,
        name: '',
        message: '',
        interval_seconds: 600,
        min_messages_between: 0,
        only_when_live: true,
        is_enabled: true,
    };

    let form = $state({ ...blank });
    let editing = $state(false);

    function newTimer() {
        form = { ...blank };
        editing = true;
    }

    function edit(timer: Timer) {
        form = { ...timer };
        editing = true;
    }

    function save() {
        const options = { onSuccess: () => (editing = false) };

        if (form.id) {
            router.put(update(form.id).url, form, options);
        } else {
            router.post(store().url, form, options);
        }
    }

    function remove(timer: Timer) {
        if (confirm(`Delete timer "${timer.name}"?`)) {
            router.delete(destroy(timer.id).url);
        }
    }
</script>

<AppHead title="Kick timers" />

<div class="flex items-center justify-between">
    <h2 class="text-lg font-semibold">Timers</h2>
    <Button onclick={newTimer}>New timer</Button>
</div>

{#if editing}
    <Card>
        <CardContent class="space-y-4 pt-6">
            <div class="grid gap-1">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    value={form.name}
                    oninput={(e) => (form.name = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="message">Message</Label>
                <textarea
                    id="message"
                    rows="2"
                    class="rounded-md border bg-background px-3 py-2 text-sm"
                    bind:value={form.message}
                ></textarea>
                <p class="text-xs text-muted-foreground">
                    Placeholders: {'{channel}'}
                    {'{uptime}'}
                    {'{random.1-100}'}
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1">
                    <Label for="interval">Interval (s)</Label>
                    <Input
                        id="interval"
                        type="number"
                        value={form.interval_seconds}
                        oninput={(e) =>
                            (form.interval_seconds = Number(
                                e.currentTarget.value,
                            ))}
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="minmsg">Min messages between</Label>
                    <Input
                        id="minmsg"
                        type="number"
                        value={form.min_messages_between}
                        oninput={(e) =>
                            (form.min_messages_between = Number(
                                e.currentTarget.value,
                            ))}
                    />
                </div>
            </div>
            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <input type="checkbox" bind:checked={form.only_when_live} />
                    Only when live
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" bind:checked={form.is_enabled} />
                    Enabled
                </label>
            </div>
            <div class="flex gap-2">
                <Button onclick={save}>Save</Button>
                <Button variant="outline" onclick={() => (editing = false)}>
                    Cancel
                </Button>
            </div>
        </CardContent>
    </Card>
{/if}

<div class="mt-4 overflow-x-auto rounded-md border">
    <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
            <tr>
                <th class="px-3 py-2">Name</th>
                <th class="px-3 py-2">Interval</th>
                <th class="px-3 py-2">Message</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody>
            {#each timers as timer (timer.id)}
                <tr class="border-t">
                    <td class="px-3 py-2 font-medium">{timer.name}</td>
                    <td class="px-3 py-2">{timer.interval_seconds}s</td>
                    <td class="px-3 py-2 truncate">{timer.message}</td>
                    <td class="px-3 py-2">
                        {#if timer.is_enabled}
                            <Badge>enabled</Badge>
                        {:else}
                            <Badge variant="secondary">disabled</Badge>
                        {/if}
                    </td>
                    <td class="px-3 py-2 text-right">
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => edit(timer)}
                        >
                            Edit
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => remove(timer)}
                        >
                            Delete
                        </Button>
                    </td>
                </tr>
            {:else}
                <tr>
                    <td
                        colspan="5"
                        class="px-3 py-6 text-center text-muted-foreground"
                    >
                        No timers yet.
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>
