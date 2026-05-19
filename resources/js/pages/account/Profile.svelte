<script module lang="ts">
    import { edit } from '@/routes/account';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Profil',
                href: edit(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, page } from '@inertiajs/svelte';
    import AccountController from '@/actions/App/Http/Controllers/Account/AccountController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import type { MemberProfile } from '@/types';

    let {
        status = '',
    }: {
        status?: string;
    } = $props();

    const user = $derived(page.props.auth.user as unknown as MemberProfile);
</script>

<AppHead title="Profil" />

<h1 class="sr-only">Profil</h1>

<div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title="Profil bilgileri"
        description="Ad, soyad ve iletişim bilgilerinizi güncelleyin"
    />

    <Form
        {...AccountController.update.form()}
        class="space-y-6"
        options={{ preserveScroll: true }}
    >
        {#snippet children({ errors, processing })}
            <div class="grid gap-2">
                <Label for="first_name">Ad</Label>
                <Input
                    id="first_name"
                    name="first_name"
                    class="mt-1 block w-full"
                    value={user.first_name ?? ''}
                    required
                    autocomplete="given-name"
                    placeholder="Ad"
                />
                <InputError class="mt-2" message={errors.first_name} />
            </div>

            <div class="grid gap-2">
                <Label for="last_name">Soyad</Label>
                <Input
                    id="last_name"
                    name="last_name"
                    class="mt-1 block w-full"
                    value={user.last_name ?? ''}
                    required
                    autocomplete="family-name"
                    placeholder="Soyad"
                />
                <InputError class="mt-2" message={errors.last_name} />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-posta</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    class="mt-1 block w-full"
                    value={user.email}
                    required
                    autocomplete="username"
                    placeholder="E-posta"
                />
                <InputError class="mt-2" message={errors.email} />
            </div>

            <div class="grid gap-2">
                <Label for="phone">Telefon</Label>
                <Input
                    id="phone"
                    name="phone"
                    class="mt-1 block w-full"
                    value={user.phone ?? ''}
                    autocomplete="tel"
                    placeholder="Telefon"
                />
                <InputError class="mt-2" message={errors.phone} />
            </div>

            <div class="grid gap-2">
                <Label for="instagram">Instagram</Label>
                <Input
                    id="instagram"
                    name="instagram"
                    class="mt-1 block w-full"
                    value={user.instagram ?? ''}
                    placeholder="Instagram"
                />
                <InputError class="mt-2" message={errors.instagram} />
            </div>

            <div class="grid gap-2">
                <Label for="twitter">Twitter</Label>
                <Input
                    id="twitter"
                    name="twitter"
                    class="mt-1 block w-full"
                    value={user.twitter ?? ''}
                    placeholder="Twitter"
                />
                <InputError class="mt-2" message={errors.twitter} />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    disabled={processing}
                    data-test="update-account-profile-button">Kaydet</Button
                >
            </div>
        {/snippet}
    </Form>
</div>
