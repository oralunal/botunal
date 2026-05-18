<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Pagination from '@/components/kick/Pagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        destroy,
        index as wikiIndex,
        store,
        update,
    } from '@/routes/kick/wiki';
    import type { Paginated, WikiEntryRow } from '@/types/kick';

    let {
        entries,
        types,
        filters,
    }: {
        entries: Paginated<WikiEntryRow>;
        types: string[];
        filters: { search: string | null; type: string | null };
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search ?? '');
    // svelte-ignore state_referenced_locally
    let typeFilter = $state(filters.type ?? '');

    const blank = {
        id: null as number | null,
        type: 'perk',
        name_en: '',
        name_tr: '',
        owner: '',
        role: '',
        description_tr: '',
        description_en: '',
        source_url: '',
        is_enabled: true,
        aliases: '',
    };

    let form = $state({ ...blank });
    let editing = $state(false);

    function newEntry() {
        form = { ...blank };
        editing = true;
    }

    function edit(entry: WikiEntryRow) {
        form = {
            id: entry.id,
            type: entry.type,
            name_en: entry.name_en,
            name_tr: entry.name_tr ?? '',
            owner: entry.owner ?? '',
            role: entry.role ?? '',
            description_tr: entry.description_tr ?? '',
            description_en: entry.description_en ?? '',
            source_url: entry.source_url ?? '',
            is_enabled: entry.is_enabled,
            aliases: entry.aliases.map((a) => a.alias).join(', '),
        };
        editing = true;
    }

    function payload() {
        return {
            ...form,
            role: form.role || null,
            name_tr: form.name_tr || null,
            owner: form.owner || null,
            description_tr: form.description_tr || null,
            description_en: form.description_en || null,
            source_url: form.source_url || null,
            aliases: form.aliases
                .split(',')
                .map((a) => a.trim())
                .filter(Boolean),
        };
    }

    function save() {
        const options = {
            onSuccess: () => (editing = false),
            preserveScroll: true,
        };
        if (form.id) {
            router.put(update(form.id).url, payload(), options);
        } else {
            router.post(store().url, payload(), options);
        }
    }

    function remove(entry: WikiEntryRow) {
        if (confirm(`"${entry.name_en}" silinsin mi?`)) {
            router.delete(destroy(entry.id).url, { preserveScroll: true });
        }
    }

    function applyFilters(event: SubmitEvent) {
        event.preventDefault();
        router.get(
            wikiIndex().url,
            { search, type: typeFilter },
            { preserveState: true, preserveScroll: true },
        );
    }
</script>

<AppHead title="DBD Wiki" />

<div class="flex items-center justify-between">
    <h2 class="text-lg font-semibold">DBD Wiki</h2>
    <Button onclick={newEntry}>Yeni kayıt</Button>
</div>

<Card class="mt-4">
    <CardContent class="pt-6">
        <form class="grid gap-3 sm:grid-cols-3" onsubmit={applyFilters}>
            <div class="grid gap-1">
                <Label for="search">Ara (TR/EN/alias/sahip)</Label>
                <Input
                    id="search"
                    value={search}
                    oninput={(e) => (search = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="typeFilter">Tür</Label>
                <select
                    id="typeFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    bind:value={typeFilter}
                >
                    <option value="">hepsi</option>
                    {#each types as t (t)}
                        <option value={t}>{t}</option>
                    {/each}
                </select>
            </div>
            <div class="flex items-end">
                <Button type="submit">Filtrele</Button>
            </div>
        </form>
    </CardContent>
</Card>

{#if editing}
    <Card class="mt-4">
        <CardContent class="space-y-4 pt-6">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1">
                    <Label for="type">Tür</Label>
                    <select
                        id="type"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        bind:value={form.type}
                    >
                        {#each types as t (t)}
                            <option value={t}>{t}</option>
                        {/each}
                    </select>
                </div>
                <div class="grid gap-1">
                    <Label for="role">Rol (Kurban/Katil etiketi)</Label>
                    <select
                        id="role"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        bind:value={form.role}
                    >
                        <option value="">yok</option>
                        <option value="survivor">survivor (Kurban)</option>
                        <option value="killer">killer (Katil)</option>
                    </select>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1">
                    <Label for="name_en">İngilizce ad</Label>
                    <Input
                        id="name_en"
                        value={form.name_en}
                        oninput={(e) => (form.name_en = e.currentTarget.value)}
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="name_tr">Türkçe ad (lakap)</Label>
                    <Input
                        id="name_tr"
                        value={form.name_tr}
                        oninput={(e) => (form.name_tr = e.currentTarget.value)}
                    />
                </div>
            </div>
            <div class="grid gap-1">
                <Label for="owner">Sahip (karakter)</Label>
                <Input
                    id="owner"
                    value={form.owner}
                    oninput={(e) => (form.owner = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="description_tr"
                    >Açıklama (TR — chat'te gösterilir)</Label
                >
                <textarea
                    id="description_tr"
                    rows="3"
                    class="rounded-md border bg-background px-3 py-2 text-sm"
                    bind:value={form.description_tr}
                ></textarea>
            </div>
            <div class="grid gap-1">
                <Label for="description_en"
                    >Açıklama (EN — referans, chat'te gösterilmez)</Label
                >
                <textarea
                    id="description_en"
                    rows="2"
                    class="rounded-md border bg-background px-3 py-2 text-sm"
                    bind:value={form.description_en}
                ></textarea>
            </div>
            <div class="grid gap-1">
                <Label for="aliases"
                    >Alias'lar (virgülle ayrılmış — TR ve EN)</Label
                >
                <Input
                    id="aliases"
                    value={form.aliases}
                    oninput={(e) => (form.aliases = e.currentTarget.value)}
                />
            </div>
            <div class="grid gap-1">
                <Label for="source_url">Kaynak URL</Label>
                <Input
                    id="source_url"
                    value={form.source_url}
                    oninput={(e) => (form.source_url = e.currentTarget.value)}
                />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" bind:checked={form.is_enabled} />
                Aktif
            </label>
            <div class="flex gap-2">
                <Button onclick={save}>Kaydet</Button>
                <Button variant="outline" onclick={() => (editing = false)}>
                    Vazgeç
                </Button>
            </div>
        </CardContent>
    </Card>
{/if}

<div class="mt-4 overflow-x-auto rounded-md border">
    <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
            <tr>
                <th class="px-3 py-2">Tür</th>
                <th class="px-3 py-2">TR adı</th>
                <th class="px-3 py-2">EN adı</th>
                <th class="px-3 py-2">Sahip</th>
                <th class="px-3 py-2">Alias</th>
                <th class="px-3 py-2">Durum</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody>
            {#each entries.data as entry (entry.id)}
                <tr class="border-t">
                    <td class="px-3 py-2">{entry.type}</td>
                    <td class="px-3 py-2 font-medium">{entry.name_tr ?? '—'}</td
                    >
                    <td class="px-3 py-2">{entry.name_en}</td>
                    <td class="px-3 py-2 text-muted-foreground">
                        {entry.owner ?? '—'}
                    </td>
                    <td class="px-3 py-2">
                        {#each entry.aliases as alias (alias.id)}
                            <Badge variant="secondary" class="ml-1">
                                {alias.alias}
                            </Badge>
                        {/each}
                    </td>
                    <td class="px-3 py-2">
                        {#if entry.is_enabled}
                            <Badge>aktif</Badge>
                        {:else}
                            <Badge variant="secondary">pasif</Badge>
                        {/if}
                    </td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => edit(entry)}
                        >
                            Düzenle
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => remove(entry)}
                        >
                            Sil
                        </Button>
                    </td>
                </tr>
            {:else}
                <tr>
                    <td
                        colspan="7"
                        class="px-3 py-6 text-center text-muted-foreground"
                    >
                        Kayıt yok.
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>

<Pagination links={entries.links} />
