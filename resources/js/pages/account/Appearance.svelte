<script module lang="ts">
    import { edit as editAccount } from '@/routes/account';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Görünüm',
                href: editAccount(),
            },
        ],
    };
</script>

<script lang="ts">
    import AppearanceTabs from '@/components/AppearanceTabs.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
</script>

<AppHead title="Görünüm" />

<div class="space-y-6">
    <Heading
        variant="small"
        title="Görünüm"
        description="Açık veya koyu temayı seç"
    />
    <AppearanceTabs />
</div>
