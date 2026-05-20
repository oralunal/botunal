<script module lang="ts">
    import { index as messagesIndex } from '@/routes/account/messages';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Mesajlar',
                href: messagesIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import MemberMessageController from '@/actions/App/Http/Controllers/Account/MemberMessageController';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import Pagination from '@/components/kick/Pagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { formatIstanbul } from '@/lib/datetime';
    import type { MemberMessageRow, PaginatedMessages } from '@/types';

    let {
        messages,
    }: {
        messages: PaginatedMessages<MemberMessageRow>;
    } = $props();
</script>

<AppHead title="Mesajlar" />

<div class="flex flex-1 flex-col gap-6 p-4">
    <div>
        <h2 class="text-xl font-semibold">Mesajlar</h2>
        <p class="text-sm text-muted-foreground">
            Yayıncıya doğrudan mesaj gönder ve gönderdiklerini takip et.
        </p>
    </div>

    <Card>
        <CardHeader class="pb-3">
            <CardTitle class="text-base">Yeni mesaj</CardTitle>
        </CardHeader>
        <CardContent>
            <Form
                {...MemberMessageController.store.form()}
                class="space-y-4"
                options={{ preserveScroll: true }}
                resetOnSuccess
            >
                {#snippet children({ errors, processing })}
                    <div class="grid gap-2">
                        <Label for="body">Mesaj</Label>
                        <textarea
                            id="body"
                            name="body"
                            rows="4"
                            class="block w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            placeholder="Yayıncıya iletmek istediğin mesajı yaz..."
                            required
                        ></textarea>
                        <InputError message={errors.body} />
                    </div>

                    <div class="flex items-center justify-end">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="send-member-message-button"
                        >
                            {#if processing}<Spinner />{/if}
                            Gönder
                        </Button>
                    </div>
                {/snippet}
            </Form>
        </CardContent>
    </Card>

    <Card>
        <CardHeader class="pb-3">
            <CardTitle class="text-base">Gönderdiklerin</CardTitle>
        </CardHeader>
        <CardContent class="p-0">
            {#if messages.data.length > 0}
                <ul class="divide-y">
                    {#each messages.data as message (message.id)}
                        <li
                            class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="min-w-0 flex-1 space-y-1">
                                <p
                                    class="text-sm whitespace-pre-wrap text-foreground"
                                >
                                    {message.body}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {formatIstanbul(message.created_at)}
                                </p>
                            </div>
                            <div class="shrink-0">
                                {#if message.is_read}
                                    <Badge variant="secondary">Okundu</Badge>
                                {:else}
                                    <Badge variant="outline">Okunmadı</Badge>
                                {/if}
                            </div>
                        </li>
                    {/each}
                </ul>
            {:else}
                <p
                    class="px-6 py-10 text-center text-sm text-muted-foreground"
                >
                    Henüz mesaj göndermedin.
                </p>
            {/if}
        </CardContent>
    </Card>

    {#if messages.last_page > 1}
        <Pagination links={messages.links} />
    {/if}
</div>
