<script module lang="ts">
    export const layout = {
        title: 'Kayıt Ol',
        description:
            'Kick hesabınla giriş yap; ardından profilini tamamla (ad, soyad ve e-posta zorunludur).',
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { login } from '@/routes';
    import { redirect as kickRedirect } from '@/routes/auth/kick';
</script>

<AppHead title="Kayıt Ol" />

<div class="space-y-6">
    <Button asChild class="w-full">
        {#snippet children(props)}
            <a {...props} href={kickRedirect().url} class={props.class}>
                Kick ile Giriş Yap
            </a>
        {/snippet}
    </Button>

    <div class="space-x-1 text-center text-sm text-muted-foreground">
        <span>Zaten hesabın var mı?</span>
        <TextLink href={login()}>Giriş yap</TextLink>
    </div>
</div>
