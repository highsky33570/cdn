<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { computed } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();

const consoleBreadcrumbs: Record<string, BreadcrumbItem[]> = {
    '/console/security/cc': [
        { title: '首页', href: '/console' },
        { title: '网站管理', href: '/console/sites' },
        { title: 'CC 规则', href: '/console/security/cc' },
    ],
    '/console/security/acls': [
        { title: '首页', href: '/console' },
        { title: '网站管理', href: '/console/sites' },
        { title: 'ACL 规则', href: '/console/security/acls' },
    ],
    '/console/cache/jobs': [
        { title: '首页', href: '/console' },
        { title: '网站管理', href: '/console/sites' },
        { title: '刷新预热', href: '/console/cache/jobs' },
    ],
    '/console/billing/subscriptions': [
        { title: '首页', href: '/console' },
        { title: '套餐管理', href: '/console/billing/subscriptions' },
        { title: '我的套餐', href: '/console/billing/subscriptions' },
    ],
    '/console/billing/packages': [
        { title: '首页', href: '/console' },
        { title: '套餐管理', href: '/console/billing/subscriptions' },
        { title: '套餐购买', href: '/console/billing/packages' },
    ],
    '/console/billing/traffic-packs': [
        { title: '首页', href: '/console' },
        { title: '套餐管理', href: '/console/billing/subscriptions' },
        { title: '流量包', href: '/console/billing/traffic-packs' },
    ],
    '/console/billing/usage': [
        { title: '首页', href: '/console' },
        { title: '套餐管理', href: '/console/billing/subscriptions' },
        { title: '用量查询', href: '/console/billing/usage' },
    ],
};

const effectiveBreadcrumbs = computed(() => {
    const path = page.url.split('?')[0];

    return consoleBreadcrumbs[path] ?? props.breadcrumbs;
});

function reloadPage(): void {
    window.location.reload();
}
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-3 border-b border-sidebar-border bg-card px-4 transition-[width,height] ease-linear"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <button
                type="button"
                class="flex size-8 items-center justify-center text-muted-foreground transition-colors hover:text-foreground"
                title="刷新页面"
                @click="reloadPage"
            >
                <RefreshCw class="size-4" />
            </button>
            <template
                v-if="effectiveBreadcrumbs && effectiveBreadcrumbs.length > 0"
            >
                <Breadcrumbs :breadcrumbs="effectiveBreadcrumbs" />
            </template>
        </div>
        <AppearanceTabs compact />
    </header>
</template>
