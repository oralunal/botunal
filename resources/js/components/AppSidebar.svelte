<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import LayoutGrid from 'lucide-svelte/icons/layout-grid';
    import MessageSquare from 'lucide-svelte/icons/message-square';
    import Radio from 'lucide-svelte/icons/radio';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import { index as accountMessages } from '@/routes/account/messages';
    import { landing as kickLanding } from '@/routes/kick';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const isKickMember = $derived(
        page.props.auth?.user?.kick_user_id != null,
    );
    const canSeeKick = $derived(
        page.props.auth?.is_super_admin === true ||
            (page.props.auth?.permissions ?? []).length > 0,
    );

    const mainNavItems = $derived<NavItem[]>([
        { title: 'Panel', href: dashboard(), icon: LayoutGrid },
        ...(isKickMember
            ? [
                  {
                      title: 'Mesajlar',
                      href: accountMessages(),
                      icon: MessageSquare,
                  },
              ]
            : []),
        ...(canSeeKick
            ? [{ title: 'Kick', href: kickLanding(), icon: Radio }]
            : []),
    ]);
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(dashboard())}
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
