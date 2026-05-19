<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';
    import { Separator } from '@/components/ui/separator';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import {
        connections,
        dashboard,
        events,
        messages,
        subscriptions,
    } from '@/routes/kick';
    import { index as commandLogsIndex } from '@/routes/kick/command-logs';
    import { index as commandsIndex } from '@/routes/kick/commands';
    import { index as moderationIndex } from '@/routes/kick/moderation';
    import { index as timersIndex } from '@/routes/kick/timers';
    import { index as usersIndex } from '@/routes/kick/users';
    import { index as memberMessagesIndex } from '@/routes/kick/member-messages';
    import { index as membersIndex } from '@/routes/kick/members';
    import { index as wikiIndex } from '@/routes/kick/wiki';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const sidebarNavItems: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), ability: 'dashboard.view' },
        {
            title: 'Connections',
            href: connections(),
            ability: 'connections.manage',
        },
        {
            title: 'Subscriptions',
            href: subscriptions(),
            ability: 'subscriptions.manage',
        },
        { title: 'Messages', href: messages(), ability: 'messages.view' },
        { title: 'Events', href: events(), ability: 'events.view' },
        {
            title: 'Commands',
            href: commandsIndex(),
            ability: 'commands.manage',
        },
        { title: 'Timers', href: timersIndex(), ability: 'timers.manage' },
        {
            title: 'Command logs',
            href: commandLogsIndex(),
            ability: 'command-logs.view',
        },
        {
            title: 'Moderation',
            href: moderationIndex(),
            ability: 'moderation.view',
        },
        { title: 'Users', href: usersIndex(), ability: 'kick-users.view' },
        { title: 'Wiki', href: wikiIndex(), ability: 'wiki.view' },
        {
            title: 'Üye Mesajları',
            href: memberMessagesIndex(),
            ability: 'member-messages.view',
        },
        {
            title: 'Üyeler',
            href: membersIndex(),
            ability: 'users.manage',
        },
    ];

    const perms = $derived(
        ($page.props.auth?.permissions ?? []) as string[],
    );
    const visibleNavItems = $derived(
        sidebarNavItems.filter(
            (item) => !item.ability || perms.includes(item.ability),
        ),
    );

    const url = currentUrlState();
</script>

<div class="px-4 py-6">
    <Heading
        title="Kick"
        description="Manage the bot, connections, events and chat logs"
    />

    <div class="flex flex-col lg:flex-row lg:space-x-12">
        <aside class="w-full max-w-xl lg:w-48">
            <nav class="flex flex-col space-y-1 space-x-0" aria-label="Kick">
                {#each visibleNavItems as item (toUrl(item.href))}
                    <Button
                        variant="ghost"
                        class="w-full justify-start {url.isCurrentUrl(
                            item.href,
                            url.currentUrl,
                        )
                            ? 'bg-muted'
                            : ''}"
                        asChild
                    >
                        {#snippet children(props)}
                            <Link href={toUrl(item.href)} class={props.class}>
                                {item.title}
                            </Link>
                        {/snippet}
                    </Button>
                {/each}
            </nav>
        </aside>

        <Separator class="my-6 lg:hidden" />

        <div class="flex-1">
            <section class="space-y-8">
                {@render children?.()}
            </section>
        </div>
    </div>
</div>
