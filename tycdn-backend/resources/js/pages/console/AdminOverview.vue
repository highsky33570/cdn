<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowUpRight,
    CheckCircle2,
    Cloud,
    KeyRound,
    Package as PackageIcon,
    RefreshCw,
    Server,
    ShoppingCart,
    Users,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { getAdminOverview } from '@/lib/adminConsoleApi';
import type {
    AdminOverview,
    AdminOverviewAlert,
    AdminRecentOrder,
    AdminUserRecord,
} from '@/lib/adminConsoleApi';

const loading = ref(false);
const errorMessage = ref('');
const overview = ref<AdminOverview | null>(null);

const metrics = computed(() => overview.value?.metrics ?? null);
const cdnfly = computed(() => overview.value?.cdnfly ?? null);
const alerts = computed(() => overview.value?.alerts ?? []);
const recentUsers = computed(() => overview.value?.recent_users ?? []);
const recentOrders = computed(() => overview.value?.recent_orders ?? []);

type OverviewStat = {
    label: string;
    value: number | null | undefined;
    href?: string;
};

const cdnflyResourceStats = computed<OverviewStat[]>(() => [
    {
        label: 'CDNfly 用户',
        value: cdnfly.value?.users_total,
        href: '/console/admin/users',
    },
    {
        label: '基础套餐',
        value: cdnfly.value?.packages_total,
        href: '/console/admin/packages',
    },
    {
        label: '用户套餐',
        value: cdnfly.value?.user_packages_total,
        href: '/console/admin/finance',
    },
    {
        label: '节点',
        value: cdnfly.value?.nodes_total,
        href: '/console/admin/nodes',
    },
    {
        label: '待初始化节点',
        value: cdnfly.value?.pending_nodes_total,
        href: '/console/admin/nodes',
    },
    {
        label: '站点',
        value: cdnfly.value?.sites_total,
        href: '/console/admin/sites',
    },
    {
        label: '流',
        value: cdnfly.value?.streams_total,
        href: '/console/admin/streams',
    },
    {
        label: '证书',
        value: cdnfly.value?.certs_total,
        href: '/console/admin/security',
    },
    {
        label: 'ACL',
        value: cdnfly.value?.acls_total,
        href: '/console/admin/security',
    },
    {
        label: 'DNS API',
        value: cdnfly.value?.dns_apis_total,
        href: '/console/admin/dns',
    },
]);

const cdnflyConfigStats = computed<OverviewStat[]>(() => [
    {
        label: '区域',
        value: cdnfly.value?.regions_total,
        href: '/console/admin/settings',
    },
    {
        label: '节点组',
        value: cdnfly.value?.node_groups_total,
        href: '/console/admin/settings',
    },
    {
        label: '套餐组',
        value: cdnfly.value?.package_groups_total,
        href: '/console/admin/packages',
    },
    {
        label: 'CNAME 域名',
        value: cdnfly.value?.cname_domains_total,
        href: '/console/admin/dns',
    },
    {
        label: '流分组',
        value: cdnfly.value?.stream_groups_total,
        href: '/console/admin/streams',
    },
    {
        label: '线路',
        value: cdnfly.value?.lines_total,
        href: '/console/admin/settings',
    },
]);

onMounted(() => {
    void loadOverview();
});

async function loadOverview(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        overview.value = await getAdminOverview();
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

function getErrorMessage(error: unknown): string {
    return error instanceof Error ? error.message : '请求失败';
}

function formatNumber(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-';
    }

    return new Intl.NumberFormat('zh-CN').format(value);
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('zh-CN', {
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function alertVariant(alert: AdminOverviewAlert): 'default' | 'destructive' {
    return alert.level === 'danger' ? 'destructive' : 'default';
}

function roleLabel(role: string): string {
    return role === 'admin' ? '管理员' : '用户';
}

function orderTitle(order: AdminRecentOrder): string {
    return order.order_no || `#${order.id}`;
}

function userTitle(user: AdminUserRecord): string {
    return user.name || user.email || `#${user.id}`;
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="管理概览"
            :icon="Server"
            :show-api-badge="false"
        />

        <div class="flex flex-wrap gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="loading"
                @click="loadOverview"
            >
                <Spinner v-if="loading" data-icon="inline-start" />
                <RefreshCw v-else data-icon="inline-start" />
                刷新
            </Button>
            <Button size="sm" as-child>
                <Link href="/console/admin/users">
                    用户管理
                    <ArrowUpRight data-icon="inline-end" />
                </Link>
            </Button>
        </div>

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>管理概览加载失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <div
            v-if="loading && !overview"
            class="flex min-h-64 items-center justify-center rounded-lg border"
        >
            <Spinner />
        </div>

        <template v-else>
            <div class="grid gap-4 xl:grid-cols-4">
                <Card class="gap-4">
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-md border bg-card"
                        >
                            <Users class="size-5" />
                        </div>
                        <div>
                            <CardTitle class="text-base">本地用户</CardTitle>
                            <div class="mt-1 text-3xl font-semibold">
                                {{ formatNumber(metrics?.users_total) }}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="grid gap-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">管理员</span>
                            <span>{{
                                formatNumber(metrics?.admins_total)
                            }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">
                                CDNfly 映射
                            </span>
                            <span>
                                {{ formatNumber(metrics?.cdnfly_mapped_users) }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card class="gap-4">
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-md border bg-card"
                        >
                            <KeyRound class="size-5" />
                        </div>
                        <div>
                            <CardTitle class="text-base">API Key</CardTitle>
                            <div class="mt-1 text-3xl font-semibold">
                                {{ formatNumber(metrics?.api_key_ready_users) }}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="text-sm text-muted-foreground">
                        可直接调用 CDNfly 用户端 API 的账号数量
                    </CardContent>
                </Card>

                <Card class="gap-4">
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-md border bg-card"
                        >
                            <ShoppingCart class="size-5" />
                        </div>
                        <div>
                            <CardTitle class="text-base">本地订单</CardTitle>
                            <div class="mt-1 text-3xl font-semibold">
                                {{ formatNumber(metrics?.orders_total) }}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="grid gap-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">待支付</span>
                            <span>
                                {{ formatNumber(metrics?.orders_pending) }}
                            </span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">已支付</span>
                            <span>{{
                                formatNumber(metrics?.orders_paid)
                            }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card class="gap-4">
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-md border bg-card"
                        >
                            <Cloud class="size-5" />
                        </div>
                        <div>
                            <CardTitle class="text-base">CDNfly</CardTitle>
                            <div class="mt-1 text-3xl font-semibold">
                                {{ formatNumber(cdnfly?.users_total) }}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="grid gap-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">基础套餐</span>
                            <span>
                                {{ formatNumber(cdnfly?.packages_total) }}
                            </span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">用户套餐</span>
                            <span>
                                {{ formatNumber(cdnfly?.user_packages_total) }}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="gap-0 overflow-hidden">
                <CardHeader class="flex flex-row items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-md border bg-card"
                    >
                        <Cloud class="size-5" />
                    </div>
                    <CardTitle class="text-base">CDNfly 资源总览</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-6 p-4 md:p-6">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <Link
                            v-for="stat in cdnflyResourceStats"
                            :key="stat.label"
                            :href="stat.href || '/console/admin'"
                            class="group flex min-h-28 flex-col justify-between rounded-md border bg-card px-4 py-3 transition-colors hover:border-primary/60 hover:bg-muted/40"
                        >
                            <div
                                class="flex items-center justify-between gap-3 text-sm text-muted-foreground"
                            >
                                <span>{{ stat.label }}</span>
                                <ArrowUpRight
                                    class="size-4 opacity-40 transition-opacity group-hover:opacity-100"
                                />
                            </div>
                            <div class="text-3xl font-semibold">
                                {{ formatNumber(stat.value) }}
                            </div>
                        </Link>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                        <Link
                            v-for="stat in cdnflyConfigStats"
                            :key="stat.label"
                            :href="stat.href || '/console/admin'"
                            class="group flex min-h-24 flex-col justify-between rounded-md border bg-card px-4 py-3 transition-colors hover:border-primary/60 hover:bg-muted/40"
                        >
                            <div
                                class="flex items-center justify-between gap-3 text-sm text-muted-foreground"
                            >
                                <span>{{ stat.label }}</span>
                                <ArrowUpRight
                                    class="size-4 opacity-40 transition-opacity group-hover:opacity-100"
                                />
                            </div>
                            <div class="text-2xl font-semibold">
                                {{ formatNumber(stat.value) }}
                            </div>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-4 xl:grid-cols-[1fr_420px]">
                <Card class="gap-0 overflow-hidden">
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-md border bg-card"
                        >
                            <Users class="size-5" />
                        </div>
                        <CardTitle class="text-base">最近用户</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[780px] text-sm">
                                <thead
                                    class="border-y bg-muted/50 text-muted-foreground"
                                >
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left font-medium"
                                        >
                                            用户
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            角色
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            CDNfly ID
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            API Key
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            注册时间
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="user in recentUsers"
                                        :key="user.id"
                                        class="border-b last:border-b-0"
                                    >
                                        <td class="px-6 py-4">
                                            <div class="font-medium">
                                                {{ userTitle(user) }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ user.email }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <Badge
                                                :variant="
                                                    user.role === 'admin'
                                                        ? 'default'
                                                        : 'secondary'
                                                "
                                            >
                                                {{ roleLabel(user.role) }}
                                            </Badge>
                                        </td>
                                        <td
                                            class="px-4 py-4 text-muted-foreground"
                                        >
                                            {{ user.cdnfly_user_id ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4">
                                            <Badge
                                                :variant="
                                                    user.has_api_key
                                                        ? 'secondary'
                                                        : 'outline'
                                                "
                                            >
                                                {{
                                                    user.has_api_key
                                                        ? '已就绪'
                                                        : '未就绪'
                                                }}
                                            </Badge>
                                        </td>
                                        <td
                                            class="px-4 py-4 text-muted-foreground"
                                        >
                                            {{ formatDate(user.created_at) }}
                                        </td>
                                    </tr>
                                    <tr v-if="recentUsers.length === 0">
                                        <td
                                            class="px-6 py-10 text-center text-muted-foreground"
                                            colspan="5"
                                        >
                                            暂无用户
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card class="gap-4">
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-md border bg-card"
                        >
                            <AlertCircle class="size-5" />
                        </div>
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <CardTitle class="text-base">
                                待处理事项
                            </CardTitle>
                            <Badge variant="outline">
                                {{ alerts.length }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <Alert
                            v-for="alert in alerts"
                            :key="`${alert.title}-${alert.detail}`"
                            :variant="alertVariant(alert)"
                        >
                            <AlertCircle data-icon="alert" />
                            <AlertTitle>{{ alert.title }}</AlertTitle>
                            <AlertDescription>
                                {{ alert.detail }}
                            </AlertDescription>
                        </Alert>
                        <div
                            v-if="alerts.length === 0"
                            class="flex items-center gap-2 rounded-md border px-3 py-3 text-sm text-muted-foreground"
                        >
                            <CheckCircle2 class="size-4" />
                            暂无待处理事项
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 xl:grid-cols-[1fr_420px]">
                <Card class="gap-0 overflow-hidden">
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-md border bg-card"
                        >
                            <ShoppingCart class="size-5" />
                        </div>
                        <CardTitle class="text-base">最近订单</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[780px] text-sm">
                                <thead
                                    class="border-y bg-muted/50 text-muted-foreground"
                                >
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left font-medium"
                                        >
                                            订单
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            用户 ID
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            金额
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            状态
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            创建时间
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="order in recentOrders"
                                        :key="order.id"
                                        class="border-b last:border-b-0"
                                    >
                                        <td class="px-6 py-4 font-medium">
                                            {{ orderTitle(order) }}
                                        </td>
                                        <td
                                            class="px-4 py-4 text-muted-foreground"
                                        >
                                            {{ order.user_id ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4">
                                            {{ order.amount_usdt }} USDT
                                        </td>
                                        <td class="px-4 py-4">
                                            <Badge variant="secondary">
                                                {{ order.status }}
                                            </Badge>
                                        </td>
                                        <td
                                            class="px-4 py-4 text-muted-foreground"
                                        >
                                            {{ formatDate(order.created_at) }}
                                        </td>
                                    </tr>
                                    <tr v-if="recentOrders.length === 0">
                                        <td
                                            class="px-6 py-10 text-center text-muted-foreground"
                                            colspan="5"
                                        >
                                            暂无订单
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card class="gap-4">
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-md border bg-card"
                        >
                            <PackageIcon class="size-5" />
                        </div>
                        <CardTitle class="text-base">快捷入口</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <Button
                            variant="outline"
                            class="justify-between"
                            as-child
                        >
                            <Link href="/console/admin/packages">
                                套餐管理
                                <ArrowUpRight data-icon="inline-end" />
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            class="justify-between"
                            as-child
                        >
                            <Link href="/console/admin/users">
                                用户管理
                                <ArrowUpRight data-icon="inline-end" />
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            class="justify-between"
                            as-child
                        >
                            <Link href="/console/admin/sites">
                                网站管理
                                <ArrowUpRight data-icon="inline-end" />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </template>
    </div>
</template>
