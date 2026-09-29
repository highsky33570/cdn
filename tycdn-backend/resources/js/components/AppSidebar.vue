<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import {
    mainNavItems,
    adminNavItems,
    adminUtilityNavItems,
} from '@/lib/consoleNavigation';
import type { User } from '@/types';

const page = usePage();
const { currentUrl } = useCurrentUrl();
const user = computed(() => page.props.auth.user as User);
const isAdmin = computed(
    () => user.value.is_admin === true || user.value.role === 'admin',
);

// Prefer the pathname helper so query strings / absolute URLs cannot leave
// this stuck on the admin label after switching to the personal console.
const adminScope = computed(
    () => isAdmin.value && currentUrl.value.startsWith('/console/admin'),
);
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        class="border-r border-sidebar-border"
    >
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link
                            :href="adminScope ? '/console/admin' : '/console'"
                        >
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain
                :key="adminScope ? 'admin' : 'user'"
                :label="adminScope ? '管理控制台' : '个人控制台'"
                :items="adminScope ? adminNavItems : mainNavItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <details
                v-if="adminScope"
                class="mx-2 text-sm group-data-[collapsible=icon]:hidden"
            >
                <summary
                    class="cursor-pointer rounded-md px-3 py-2 text-muted-foreground hover:bg-sidebar-accent"
                >
                    控制台工具
                </summary>
                <Link
                    v-for="item in adminUtilityNavItems"
                    :key="item.title"
                    :href="item.href"
                    class="block rounded-md px-3 py-2 text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-foreground"
                    >{{ item.title }}</Link
                >
            </details>
            <Link
                v-if="isAdmin"
                :href="adminScope ? '/console' : '/console/admin'"
                class="m-2 rounded-lg border px-3 py-2 text-center text-sm text-primary group-data-[collapsible=icon]:hidden"
                >{{
                    adminScope ? '切换到个人控制台' : '切换到管理控制台'
                }}</Link
            >
        </SidebarFooter>

        <SidebarRail />
    </Sidebar>
    <slot />
</template>
