<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ChartBar,
    CreditCard,
    Globe2,
    KeyRound,
    LayoutGrid,
    Network,
    Package,
    Server,
    Settings,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
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
import type { NavItem, User } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user as User);
const isAdmin = computed(
    () => user.value.is_admin === true || user.value.role === 'admin',
);

const mainNavItems: NavItem[] = [
    {
        title: '服务概览',
        href: '/console',
        icon: LayoutGrid,
    },
    {
        title: '统计分析',
        href: '/console/analytics/realtime',
        icon: ChartBar,
        children: [
            { title: '实时监控', href: '/console/analytics/realtime' },
            { title: '数据分析', href: '/console/analytics/top' },
            { title: '拉黑日志', href: '/console/security/blackip' },
            { title: '访问日志', href: '/console/analytics/logs' },
            { title: '四层实时监控', href: '/console/streams/analytics' },
        ],
    },
    {
        title: '网站管理',
        href: '/console/sites',
        icon: Globe2,
        children: [
            { title: '网站列表', href: '/console/sites' },
            { title: '证书管理', href: '/console/certificates' },
            { title: '刷新预热', href: '/console/cache/jobs' },
            { title: 'CC 规则', href: '/console/security/cc' },
            { title: 'ACL 规则', href: '/console/security/acls' },
        ],
    },
    {
        title: '四层转发',
        href: '/console/streams',
        icon: Network,
        children: [{ title: '转发列表', href: '/console/streams' }],
    },
    {
        title: '套餐管理',
        href: '/console/billing/subscriptions',
        icon: CreditCard,
        children: [
            { title: '我的套餐', href: '/console/billing/subscriptions' },
            { title: '套餐购买', href: '/console/billing/packages' },
            { title: '流量包', href: '/console/billing/traffic-packs' },
            { title: '用量查询', href: '/console/billing/usage' },
        ],
    },
    {
        title: '账户中心',
        href: '/console/account/profile',
        icon: Settings,
        children: [
            { title: '个人资料', href: '/console/account/profile' },
            { title: '消费记录', href: '/console/billing/orders' },
            { title: '日志查询', href: '/console/account/login-logs' },
            { title: '消息查询', href: '/console/messages' },
            { title: '消息订阅', href: '/console/messages/subscriptions' },
            { title: 'API 密钥', href: '/console/account/api-key' },
        ],
    },
];

const adminNavItems: NavItem[] = [
    {
        title: '管理概览',
        href: '/console/admin',
        icon: Activity,
    },
    {
        title: '用户管理',
        href: '/console/admin/users',
        icon: Users,
    },
    {
        title: '套餐管理',
        href: '/console/admin/packages',
        icon: Package,
    },
    {
        title: '网站管理',
        href: '/console/admin/sites',
        icon: Globe2,
    },
    {
        title: '节点管理',
        href: '/console/admin/nodes',
        icon: Server,
    },
    {
        title: 'DNS 管理',
        href: '/console/admin/dns',
        icon: Network,
    },
    {
        title: '四层转发',
        href: '/console/admin/streams',
        icon: Network,
    },
    {
        title: '财务管理',
        href: '/console/admin/finance',
        icon: CreditCard,
    },
    {
        title: '监控日志',
        href: '/console/admin/monitoring',
        icon: ChartBar,
    },
    {
        title: '系统配置',
        href: '/console/admin/settings',
        icon: Settings,
    },
    {
        title: '安全权限',
        href: '/console/admin/security',
        icon: KeyRound,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/console">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="用户控制台" :items="mainNavItems" />
            <NavMain v-if="isAdmin" label="管理员面板" :items="adminNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>

        <SidebarRail />
    </Sidebar>
    <slot />
</template>
