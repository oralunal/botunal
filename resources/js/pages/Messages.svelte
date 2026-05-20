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
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import Pagination from '@/components/kick/Pagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import { formatIstanbul } from '@/lib/datetime';
    import type { MemberMessageRow, PaginatedMessages } from '@/types';

    let {
        messages,
    }: {
        messages: PaginatedMessages<MemberMessageRow>;
    } = $props();
</script>

<AppHead title="Mesajlar" />

<h1 class="sr-only">Mesajlar</h1>

<div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title="Yayıncıya mesaj gönder"
        description="Yayıncıya iletmek istediğiniz mesajı buradan gönderebilirsiniz"
    />

    <Form
        {...MemberMessageController.store.form()}
        class="space-y-6"
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
                    class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    placeholder="Mesajınızı yazın..."
                    required
                ></textarea>
                <InputError class="mt-2" message={errors.body} />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    disabled={processing}
                    data-test="send-member-message-button">Gönder</Button
                >
            </div>
        {/snippet}
    </Form>

    <div class="overflow-x-auto rounded-md border">
        <table class="w-full text-sm">
            <thead class="bg-muted/50 text-left">
                <tr>
                    <th class="px-3 py-2">Tarih</th>
                    <th class="px-3 py-2">Mesaj</th>
                    <th class="px-3 py-2">Durum</th>
                </tr>
            </thead>
            <tbody>
                {#each messages.data as message (message.id)}
                    <tr class="border-t">
                        <td
                            class="px-3 py-2 whitespace-nowrap text-muted-foreground"
                        >
                            {formatIstanbul(message.created_at)}
                        </td>
                        <td class="px-3 py-2">{message.body}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            {#if message.is_read}
                                <Badge variant="secondary">Okundu</Badge>
                            {:else}
                                <Badge variant="outline">Okunmadı</Badge>
                            {/if}
                        </td>
                    </tr>
                {:else}
                    <tr>
                        <td
                            colspan="3"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            Henüz mesaj göndermediniz.
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>

    <Pagination links={messages.links} />
</div>
