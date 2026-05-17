import { router } from '@inertiajs/svelte';
import { onMount } from 'svelte';

/**
 * Periodically reload the given Inertia props while the component is mounted.
 * Used for the auto-refreshing Kick dashboard / log tables (no websockets).
 */
export function usePoll(only: string[], intervalMs = 15000): void {
    onMount(() => {
        const id = setInterval(() => {
            router.reload({ only });
        }, intervalMs);

        return () => clearInterval(id);
    });
}
