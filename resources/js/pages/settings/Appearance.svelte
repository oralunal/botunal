<script module lang="ts">
    import { edit as editAppearance } from '@/routes/appearance';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Görünüm ayarları',
                href: editAppearance(),
            },
        ],
    };
</script>

<script lang="ts">
    import AppearanceTabs from '@/components/AppearanceTabs.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
</script>

<AppHead title="Görünüm ayarları" />

<h1 class="sr-only">Görünüm ayarları</h1>

<div class="space-y-6">
    <Heading
        variant="small"
        title="Görünüm ayarları"
        description="Hesabının görünüm ayarlarını güncelle"
    />
    <AppearanceTabs />
</div>
