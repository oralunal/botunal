<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { lookup } from '@/routes/kick/users';

    let {
        username,
        class: className = '',
    }: {
        username: string | null | undefined;
        class?: string;
    } = $props();

    const name = $derived(username?.trim() ? username.trim() : null);
    const linkable = $derived(
        name !== null && name.toLowerCase() !== 'unknown',
    );
</script>

{#if linkable && name}
    <Link
        href={lookup(name).url}
        class={`text-primary underline-offset-2 hover:underline ${className}`}
    >
        {name}
    </Link>
{:else}
    <span class={className}>{name ?? '—'}</span>
{/if}
