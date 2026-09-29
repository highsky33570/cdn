<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
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
import {
    mainNavItems,
    adminNavItems,
    adminUtilityNavItems,
} from '@/lib/consoleNavigation';
import type { User } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user as User);
const isAdmin = computed(
    () => user.value.is_admin === true || user.value.role === 'admin',
);

function pathnameOf(url: unknown): string {
    if (typeof window !== 'undefined' && (url === null || url === undefined || url === '')) {
        return window.location.pathname;
    }

    if (typeof URL !== 'undefined' && url instanceof URL) {
        return url.pathname;
    }

    if (typeof url === 'string' && url !== '') {
        try {
            return new URL(
                url,
                typeof window !== 'undefined'
                    ? window.location.origin
                    : 'http://localhost',
            ).pathname;
        } catch {
            return url.split('?')[0] || '/';
        }
    }

    if (url && typeof url === 'object' && 'pathname' in url) {
        return String((url as { pathname: unknown }).pathname || '/');
    }

    return typeof window !== 'undefined' ? window.location.pathname : '/';
}

function pathIsAdmin(path: string): boolean {
    return path === '/console/admin' || path.startsWith('/console/admin/');
}

/**
 * Own the console mode in this sidebar. Deriving only from Inertia page.url
 * left the switch label stuck on “个人” after soft-navigating to /console
 * (nav could remount while the label lagged). Flip optimistically on click and
 * re-sync from visit/router/window location.
 */
const path = ref(pathnameOf(null));

function syncPath(url?: unknown): void {
    const next = pathnameOf(url);

    if (path.value !== next) {
        path.value = next;
    }
}

const adminScope = computed(
    () => isAdmin.value && pathIsAdmin(path.value),
);

const switchHref = computed(() =>
    adminScope.value ? '/console' : '/console/admin',
);
const switchLabel = computed(() =>
    adminScope.value ? '切换到个人控制台' : '切换到管理控制台',
);

function onSwitchClick(): void {
    // Immediate flip so the label never waits on the Inertia round-trip.
    path.value = adminScope.value ? '/console' : '/console/admin';
}

const cleanups: Array<() => void> = [];

onMounted(() => {
    syncPath();

    cleanups.push(
        router.on('before', (event) => {
            syncPath(event.detail.visit.url);
        }),
    );
    cleanups.push(
        router.on('navigate', (event) => {
            syncPath(event.detail.page.url);
        }),
    );
    cleanups.push(
        router.on('success', (event) => {
            syncPath(event.detail.page.url);
        }),
    );
    cleanups.push(
        router.on('finish', () => {
            syncPath();
        }),
    );

    const onPopState = () => syncPath();
    window.addEventListener('popstate', onPopState);
    cleanups.push(() => window.removeEventListener('popstate', onPopState));
});

onUnmounted(() => {
    while (cleanups.length > 0) {
        cleanups.pop()?.();
    }
});
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
                :key="switchLabel"
                :href="switchHref"
                class="m-2 rounded-lg border px-3 py-2 text-center text-sm text-primary group-data-[collapsible=icon]:hidden"
                @click="onSwitchClick"
                >{{ switchLabel }}</Link
            >
        </SidebarFooter>

        <SidebarRail />
    </Sidebar>
    <slot />
</template>
