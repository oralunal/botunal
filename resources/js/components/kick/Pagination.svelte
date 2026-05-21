<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';

    let {
        links,
    }: {
        links: { url: string | null; label: string; active: boolean }[];
    } = $props();

    /** Laravel paginator labels arrive with HTML entities; render as text. */
    function label(raw: string): string {
        return raw
            .replace(/&laquo;/g, '«')
            .replace(/&raquo;/g, '»')
            .replace(/<[^>]*>/g, '')
            .trim();
    }
</script>

{#if links.length > 3}
    <nav class="mt-4 flex flex-wrap gap-1" aria-label="Sayfalama">
        {#each links as link, index (link.url ?? `gap-${index}`)}
            {#if link.url}
                {@const href = link.url}
                <Button
                    variant={link.active ? 'default' : 'outline'}
                    size="sm"
                    asChild
                >
                    {#snippet children(props)}
                        <Link
                            {href}
                            class={props.class}
                            preserveScroll
                            preserveState
                        >
                            {label(link.label)}
                        </Link>
                    {/snippet}
                </Button>
            {:else}
                <Button variant="outline" size="sm" disabled class="opacity-50">
                    {label(link.label)}
                </Button>
            {/if}
        {/each}
    </nav>
{/if}
