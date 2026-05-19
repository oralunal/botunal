<script module lang="ts">
    export const layout = {
        title: 'E-posta doğrulama',
        description:
            'Lütfen az önce gönderdiğimiz bağlantıya tıklayarak e-posta adresini doğrula.',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Spinner } from '@/components/ui/spinner';
    import { logout } from '@/routes';
    import { send } from '@/routes/verification';

    let {
        status = '',
    }: {
        status?: string;
    } = $props();
</script>

<AppHead title="E-posta doğrulama" />

{#if status === 'verification-link-sent'}
    <div class="mb-4 text-center text-sm font-medium text-green-600">
        Kayıt sırasında verdiğin e-posta adresine yeni bir doğrulama bağlantısı
        gönderildi.
    </div>
{/if}

<Form {...send.form()} class="space-y-6 text-center">
    {#snippet children({ processing })}
        <Button type="submit" disabled={processing} variant="secondary">
            {#if processing}<Spinner />{/if}
            Doğrulama e-postasını tekrar gönder
        </Button>

        <TextLink href={logout()} as="button" class="mx-auto block text-sm">
            Çıkış yap
        </TextLink>
    {/snippet}
</Form>
