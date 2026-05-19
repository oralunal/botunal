<script module lang="ts">
    import { edit } from '@/routes/profile';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Profil ayarları',
                href: edit(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, page } from '@inertiajs/svelte';
    import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
    import AppHead from '@/components/AppHead.svelte';
    import DeleteUser from '@/components/DeleteUser.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { send } from '@/routes/verification';

    let {
        mustVerifyEmail,
        status = '',
    }: {
        mustVerifyEmail: boolean;
        status?: string;
    } = $props();

    const user = $derived(page.props.auth.user);
</script>

<AppHead title="Profil ayarları" />

<h1 class="sr-only">Profil ayarları</h1>

<div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title="Profil bilgileri"
        description="Ad ve e-posta adresini güncelle"
    />

    <Form
        {...ProfileController.update.form()}
        class="space-y-6"
        options={{ preserveScroll: true }}
    >
        {#snippet children({ errors, processing })}
            <div class="grid gap-2">
                <Label for="name">Ad</Label>
                <Input
                    id="name"
                    name="name"
                    class="mt-1 block w-full"
                    value={user.name}
                    required
                    autocomplete="name"
                    placeholder="Ad soyad"
                />
                <InputError class="mt-2" message={errors.name} />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-posta adresi</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    class="mt-1 block w-full"
                    value={user.email}
                    required
                    autocomplete="username"
                    placeholder="E-posta adresi"
                />
                <InputError class="mt-2" message={errors.email} />
            </div>

            {#if mustVerifyEmail && !user.email_verified_at}
                <div>
                    <p class="-mt-4 text-sm text-muted-foreground">
                        E-posta adresin doğrulanmamış.
                        <TextLink href={send()} as="button">
                            Doğrulama e-postasını yeniden göndermek için buraya
                            tıkla.
                        </TextLink>
                    </p>

                    {#if status === 'verification-link-sent'}
                        <div class="mt-2 text-sm font-medium text-green-600">
                            E-posta adresine yeni bir doğrulama bağlantısı
                            gönderildi.
                        </div>
                    {/if}
                </div>
            {/if}

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    disabled={processing}
                    data-test="update-profile-button">Kaydet</Button
                >
            </div>
        {/snippet}
    </Form>
</div>

<DeleteUser />
