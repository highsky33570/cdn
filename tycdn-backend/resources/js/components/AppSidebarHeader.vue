<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { RefreshCw, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { adminNavItems, consoleNavigationTitle } from '@/lib/consoleNavigation';
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

type BreadcrumbMeta = [section: string, sectionHref: string, page: string];

const consoleBreadcrumbMeta: Record<string, BreadcrumbMeta> = {
    '/console': ['用户控制台', '/console', '服务概览'],
    '/dashboard': ['用户控制台', '/console', '服务概览'],
    '/console/sites': ['网站管理', '/console/sites', '网站列表'],
    '/console/site-groups': ['网站管理', '/console/sites', '分组管理'],
    '/console/certificates': ['网站管理', '/console/sites', '证书管理'],
    '/console/dnsapis': ['网站管理', '/console/sites', 'DNS API'],
    '/console/cache': ['网站管理', '/console/sites', '刷新预热'],
    '/console/cache/jobs': ['网站管理', '/console/sites', '刷新预热'],
    '/console/security/acls': ['网站管理', '/console/sites', 'ACL 规则'],
    '/console/security/cc': ['网站管理', '/console/sites', 'CC 规则'],
    '/console/security/blackip': [
        '统计分析',
        '/console/analytics/realtime',
        '拉黑日志',
    ],
    '/console/analytics/realtime': [
        '统计分析',
        '/console/analytics/realtime',
        '实时监控',
    ],
    '/console/analytics/top': [
        '统计分析',
        '/console/analytics/realtime',
        '数据分析',
    ],
    '/console/analytics/logs': [
        '统计分析',
        '/console/analytics/realtime',
        '访问日志',
    ],
    '/console/analytics/usage': [
        '统计分析',
        '/console/analytics/realtime',
        '用量统计',
    ],
    '/console/streams': ['四层转发', '/console/streams', '转发列表'],
    '/console/streams/analytics': ['四层转发', '/console/streams', '实时监控'],
    '/console/billing/subscriptions': [
        '套餐管理',
        '/console/billing/subscriptions',
        '我的套餐',
    ],
    '/console/billing/packages': [
        '套餐管理',
        '/console/billing/subscriptions',
        '套餐购买',
    ],
    '/console/billing/traffic-packs': [
        '套餐管理',
        '/console/billing/subscriptions',
        '流量包',
    ],
    '/console/billing/usage': [
        '套餐管理',
        '/console/billing/subscriptions',
        '用量查询',
    ],
    '/console/billing/orders': [
        '账户中心',
        '/console/account/profile',
        '消费记录',
    ],
    '/console/messages': ['账户中心', '/console/account/profile', '消息查询'],
    '/console/messages/subscriptions': [
        '账户中心',
        '/console/account/profile',
        '消息订阅',
    ],
    '/console/account/profile': [
        '账户中心',
        '/console/account/profile',
        '个人资料',
    ],
    '/console/account/certification': [
        '账户中心',
        '/console/account/profile',
        '实名认证',
    ],
    '/console/account/api-key': [
        '账户中心',
        '/console/account/profile',
        'API 密钥',
    ],
    '/console/account/login-logs': [
        '账户中心',
        '/console/account/profile',
        '日志查询',
    ],
    '/console/admin': ['管理员面板', '/console/admin', '管理概览'],
    '/console/admin/users': ['管理员面板', '/console/admin', '用户管理'],
    '/console/admin/packages': ['管理员面板', '/console/admin', '套餐管理'],
    '/console/admin/sites': ['管理员面板', '/console/admin', '网站管理'],
    '/console/admin/nodes': ['管理员面板', '/console/admin', '节点管理'],
    '/console/admin/dns': ['管理员面板', '/console/admin', 'DNS 管理'],
    '/console/admin/streams': ['管理员面板', '/console/admin', '四层转发'],
    '/console/admin/finance': ['管理员面板', '/console/admin', '财务管理'],
    '/console/admin/monitoring': ['管理员面板', '/console/admin', '监控日志'],
    '/console/admin/settings': ['管理员面板', '/console/admin', '系统配置'],
    '/console/admin/security': ['管理员面板', '/console/admin', '安全权限'],
};

const effectiveBreadcrumbs = computed(() => {
    const path = page.url.split('?')[0];
    const title = consoleNavigationTitle(path);

    if (title) {
        const group = adminNavItems.find((item) =>
            item.children?.some((child) => child.href === path),
        );

        if (group) {
            return [
                { title: group.title, href: group.href },
                { title, href: path },
            ];
        }

        return [
            {
                title: path.startsWith('/console/admin')
                    ? '全局管理'
                    : '个人控制台',
                href: path.startsWith('/console/admin')
                    ? '/console/admin'
                    : '/console',
            },
            { title, href: path },
        ];
    }

    const meta = consoleBreadcrumbMeta[path];

    if (meta) {
        return [
            { title: '首页', href: '/console' },
            { title: meta[0], href: meta[1] },
            { title: meta[2], href: path },
        ];
    }

    if (/^\/console\/sites\/[^/]+$/.test(path)) {
        return [
            { title: '首页', href: '/console' },
            { title: '网站管理', href: '/console/sites' },
            { title: '网站详情', href: path },
        ];
    }

    if (/^\/console\/streams\/\d+$/.test(path)) {
        return [
            { title: '首页', href: '/console' },
            { title: '四层转发', href: '/console/streams' },
            { title: '转发详情', href: path },
        ];
    }

    return props.breadcrumbs;
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
            <Button
                variant="ghost"
                size="icon-sm"
                type="button"
                class="flex size-8 items-center justify-center text-muted-foreground transition-colors hover:text-foreground"
                title="刷新页面"
                @click="reloadPage"
            >
                <RefreshCw class="size-4" />
            </Button>
            <template
                v-if="effectiveBreadcrumbs && effectiveBreadcrumbs.length > 0"
            >
                <Breadcrumbs :breadcrumbs="effectiveBreadcrumbs" />
            </template>
        </div>
        <AppearanceTabs compact />
        <DropdownMenu
            ><DropdownMenuTrigger as-child
                ><Button
                    variant="outline"
                    type="button"
                    aria-label="账户菜单"
                    class="flex h-9 items-center gap-2 rounded-lg border bg-card px-3 text-sm text-foreground hover:bg-accent"
                >
                    <span class="max-w-28 truncate">{{
                        page.props.auth.user.name
                    }}</span
                    ><ChevronDown
                        class="size-3.5" /></Button></DropdownMenuTrigger
            ><DropdownMenuContent align="end" class="min-w-60"
                ><UserMenuContent
                    :user="page.props.auth.user" /></DropdownMenuContent
        ></DropdownMenu>
    </header>
</template>
