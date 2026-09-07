<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    ArrowUpRight,
    FileKey2,
    Gauge,
    Globe2,
    MessageSquare,
    PackageCheck,
    RefreshCw,
    ShieldCheck,
    ShoppingCart,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    formatDate as formatCdnDate,
    getErrorMessage,
    textValue,
} from '@/lib/cdnRecord';
import {
    extractCdnflyRows,
    extractCdnflyTotal,
    getUserOverview,
    listUserAcls,
    listUserCerts,
    listUserDnsApis,
    listUserJobs,
    listUserMessages,
    listUserOrders,
    listUserPackages,
    listUserSites,
    listUserStreams,
} from '@/lib/cdnUserApi';
import type { CdnflyListData, CdnflyRecord } from '@/lib/cdnUserApi';
import type { User } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Console',
                href: '/console',
            },
        ],
    },
});

type OverviewTotals = {
    userPackages: number | null;
    sites: number | null;
    certs: number | null;
    dnsApis: number | null;
    acls: number | null;
    streams: number | null;
    jobs: number | null;
    orders: number | null;
    messages: number | null;
};

type ListSummary = {
    rows: CdnflyRecord[];
    total: number | null;
};

const page = usePage();
const user = computed(() => page.props.auth.user as User);
const isAdmin = computed(
    () => user.value.is_admin === true || user.value.role === 'admin',
);

const loading = ref(false);
const errorMessage = ref('');
const userOverviewLoaded = ref(false);
const packageRows = ref<CdnflyRecord[]>([]);
const selectedPackageId = ref('');
const totals = ref<OverviewTotals>({
    userPackages: null,
    sites: null,
    certs: null,
    dnsApis: null,
    acls: null,
    streams: null,
    jobs: null,
    orders: null,
    messages: null,
});

const packageOptions = computed(() =>
    packageRows.value.map((record, index) => ({
        label: packageName(record),
        value: optionValue(record, index),
        record,
    })),
);

const selectedPackage = computed<CdnflyRecord | null>(() => {
    const matched = packageOptions.value.find(
        (item) => item.value === selectedPackageId.value,
    );

    return matched?.record ?? packageRows.value[0] ?? null;
});

const cdnflyConnected = computed(
    () =>
        userOverviewLoaded.value ||
        Object.values(totals.value).some((value) => value !== null),
);

const hasLoadedData = computed(
    () => cdnflyConnected.value || packageRows.value.length > 0,
);

const accountMetrics = computed(() => [
    {
        label: '我的套餐',
        value: totals.value.userPackages,
        href: '/console/billing/subscriptions',
        icon: PackageCheck,
    },
    {
        label: '订单',
        value: totals.value.orders,
        href: '/console/billing/orders',
        icon: ShoppingCart,
    },
    {
        label: '消息',
        value: totals.value.messages,
        href: '/console/messages',
        icon: MessageSquare,
    },
    {
        label: '任务',
        value: totals.value.jobs,
        href: '/console/cache/jobs',
        icon: RefreshCw,
    },
]);

const resourceMetrics = computed(() => [
    {
        label: '站点',
        value: totals.value.sites,
        href: '/console/sites',
        icon: Globe2,
    },
    {
        label: '证书',
        value: totals.value.certs,
        href: '/console/certificates',
        icon: FileKey2,
    },
    {
        label: 'DNS API',
        value: totals.value.dnsApis,
        href: '/console/dnsapis',
        icon: Gauge,
    },
    {
        label: 'ACL',
        value: totals.value.acls,
        href: '/console/security/acls',
        icon: ShieldCheck,
    },
    {
        label: '四层转发',
        value: totals.value.streams,
        href: '/console/streams',
        icon: Activity,
    },
]);

const quickActions = computed(() => {
    const actions = [
        {
            title: '创建站点',
            href: '/console/sites',
            icon: Globe2,
        },
        {
            title: '刷新缓存',
            href: '/console/cache/jobs',
            icon: RefreshCw,
        },
        {
            title: '申请证书',
            href: '/console/certificates',
            icon: FileKey2,
        },
        {
            title: '消息中心',
            href: '/console/messages',
            icon: MessageSquare,
        },
    ];

    if (isAdmin.value) {
        actions.push({
            title: '管理端',
            href: '/console/admin',
            icon: PackageCheck,
        });
    }

    return actions;
});

onMounted(() => {
    void loadOverview();
});

async function loadOverview(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const results = await Promise.allSettled([
            getUserOverview(),
            listUserPackages({ page: 1, limit: 50 }),
            listUserSites({ page: 1, limit: 1 }),
            listUserCerts({ page: 1, limit: 1 }),
            listUserDnsApis({ page: 1, limit: 1 }),
            listUserAcls({ page: 1, limit: 1 }),
            listUserStreams({ page: 1, limit: 1 }),
            listUserJobs({ page: 1, limit: 1 }),
            listUserOrders({ page: 1, limit: 1 }),
            listUserMessages({ page: 1, limit: 1 }),
        ]);

        userOverviewLoaded.value = results[0].status === 'fulfilled';

        const packages = listSummary(fulfilledValue(results[1]));
        packageRows.value = packages.rows;

        const nextOptions = packageRows.value.map((record, index) =>
            optionValue(record, index),
        );

        if (
            nextOptions.length > 0 &&
            !nextOptions.includes(selectedPackageId.value)
        ) {
            selectedPackageId.value = nextOptions[0];
        }

        if (nextOptions.length === 0) {
            selectedPackageId.value = '';
        }

        totals.value = {
            userPackages: packages.total,
            sites: listSummary(fulfilledValue(results[2])).total,
            certs: listSummary(fulfilledValue(results[3])).total,
            dnsApis: listSummary(fulfilledValue(results[4])).total,
            acls: listSummary(fulfilledValue(results[5])).total,
            streams: listSummary(fulfilledValue(results[6])).total,
            jobs: listSummary(fulfilledValue(results[7])).total,
            orders: listSummary(fulfilledValue(results[8])).total,
            messages: listSummary(fulfilledValue(results[9])).total,
        };

        if (results.every((result) => result.status === 'rejected')) {
            const failed = results.find(
                (result): result is PromiseRejectedResult =>
                    result.status === 'rejected',
            );
            errorMessage.value = getErrorMessage(failed?.reason);
        }
    } finally {
        loading.value = false;
    }
}

function fulfilledValue<T>(result: PromiseSettledResult<T>): T | null {
    return result.status === 'fulfilled' ? result.value : null;
}

function listSummary(payload: CdnflyListData | null): ListSummary {
    if (!payload) {
        return {
            rows: [],
            total: null,
        };
    }

    const rows = extractCdnflyRows(payload);

    return {
        rows,
        total: extractCdnflyTotal(payload, rows.length),
    };
}

function formatNumber(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-';
    }

    return new Intl.NumberFormat('zh-CN').format(value);
}

function optionValue(record: CdnflyRecord, index: number): string {
    return textValue(record.id) || `row-${index}`;
}

function packageName(record: CdnflyRecord | null): string {
    if (!record) {
        return '-';
    }

    return textValue(record.name ?? record.title ?? record.package_name) || '-';
}

function packageId(record: CdnflyRecord | null): string {
    if (!record) {
        return '-';
    }

    return textValue(record.id ?? record.package ?? record.package_id) || '-';
}

function packageMetric(record: CdnflyRecord | null): string {
    if (!record) {
        return '-';
    }

    return (
        textValue(record.traffic) ||
        textValue(record.bandwidth) ||
        textValue(record.flow) ||
        textValue(record.size) ||
        '-'
    );
}

function packageStatus(record: CdnflyRecord | null): string {
    if (!record) {
        return '-';
    }

    return (
        textValue(record.status) ||
        textValue(record.state) ||
        textValue(record.enable) ||
        '-'
    );
}

function packageExpireAt(record: CdnflyRecord | null): string {
    if (!record) {
        return '-';
    }

    return formatCdnDate(
        record.expire_at ??
            record.expire_time ??
            record.end_at ??
            record.end_time,
    );
}
</script>

<template>
    <Head title="Console" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="欢迎回来"
            primary-action="创建站点"
            primary-href="/console/sites"
            :show-api-badge="false"
        />

        <div class="flex flex-wrap items-center gap-2">
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
            <Badge :variant="cdnflyConnected ? 'secondary' : 'outline'">
                CDNfly {{ cdnflyConnected ? '已连接' : '未连接' }}
            </Badge>
        </div>

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>概览加载失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <div
            v-if="loading && !hasLoadedData"
            class="flex min-h-64 items-center justify-center rounded-lg border"
        >
            <Spinner />
        </div>

        <template v-else>
            <div
                class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]"
            >
                <Card class="gap-4">
                    <CardHeader class="gap-3">
                        <div
                            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <div
                                    class="flex size-10 items-center justify-center rounded-md border bg-card"
                                >
                                    <PackageCheck class="size-5" />
                                </div>
                                <CardTitle class="text-base">
                                    我的套餐
                                </CardTitle>
                            </div>
                            <Select
                                v-if="packageOptions.length > 0"
                                v-model="selectedPackageId"
                            >
                                <SelectTrigger class="w-full md:w-56">
                                    <SelectValue placeholder="选择套餐" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="item in packageOptions"
                                        :key="item.value"
                                        :value="item.value"
                                    >
                                        {{ item.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <div
                            v-if="selectedPackage"
                            class="flex flex-col gap-3 rounded-md border bg-muted/20 p-4 md:flex-row md:items-center md:justify-between"
                        >
                            <div class="min-w-0">
                                <div
                                    class="flex flex-wrap items-center gap-2 text-xl font-semibold"
                                >
                                    <span>{{
                                        packageName(selectedPackage)
                                    }}</span>
                                    <Badge variant="secondary">
                                        {{ packageStatus(selectedPackage) }}
                                    </Badge>
                                </div>
                                <div class="mt-1 text-sm text-muted-foreground">
                                    #{{ packageId(selectedPackage) }}
                                </div>
                            </div>
                            <Button variant="outline" size="sm" as-child>
                                <Link href="/console/billing/subscriptions">
                                    套餐管理
                                    <ArrowUpRight data-icon="inline-end" />
                                </Link>
                            </Button>
                        </div>

                        <div
                            v-else
                            class="flex flex-col gap-3 rounded-md border bg-muted/20 p-4 md:flex-row md:items-center md:justify-between"
                        >
                            <div class="font-medium">暂无已开通套餐</div>
                            <Button variant="outline" size="sm" as-child>
                                <Link href="/console/billing/packages">
                                    购买套餐
                                    <ArrowUpRight data-icon="inline-end" />
                                </Link>
                            </Button>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="rounded-md border bg-muted/20 p-4">
                                <div class="text-sm text-muted-foreground">
                                    到期时间
                                </div>
                                <div class="mt-2 font-medium">
                                    {{ packageExpireAt(selectedPackage) }}
                                </div>
                            </div>
                            <div class="rounded-md border bg-muted/20 p-4">
                                <div class="text-sm text-muted-foreground">
                                    套餐指标
                                </div>
                                <div class="mt-2 font-medium">
                                    {{ packageMetric(selectedPackage) }}
                                </div>
                            </div>
                            <div class="rounded-md border bg-muted/20 p-4">
                                <div class="text-sm text-muted-foreground">
                                    绑定站点
                                </div>
                                <div class="mt-2 font-medium">
                                    {{ formatNumber(totals.sites) }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="gap-4">
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-md border bg-card"
                        >
                            <Gauge class="size-5" />
                        </div>
                        <CardTitle class="text-base">账户概况</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <Link
                            v-for="item in accountMetrics"
                            :key="item.label"
                            :href="item.href"
                            class="group flex items-center justify-between gap-3 rounded-md border bg-muted/20 p-3 transition-colors hover:bg-muted/40"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <component
                                    :is="item.icon"
                                    class="size-4 text-muted-foreground"
                                />
                                <span class="font-medium">{{
                                    item.label
                                }}</span>
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="font-semibold">
                                    {{ formatNumber(item.value) }}
                                </span>
                                <ArrowUpRight
                                    class="size-4 opacity-40 transition-opacity group-hover:opacity-100"
                                />
                            </span>
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <Card class="gap-4">
                <CardHeader class="flex flex-row items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-md border bg-card"
                    >
                        <Activity class="size-5" />
                    </div>
                    <CardTitle class="text-base">资源概况</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <Link
                            v-for="item in resourceMetrics"
                            :key="item.label"
                            :href="item.href"
                            class="group flex min-h-28 flex-col justify-between rounded-md border bg-muted/20 p-4 transition-colors hover:bg-muted/40"
                        >
                            <span
                                class="flex items-center justify-between gap-3 text-sm text-muted-foreground"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <component
                                        :is="item.icon"
                                        class="size-4 shrink-0"
                                    />
                                    <span>{{ item.label }}</span>
                                </span>
                                <ArrowUpRight
                                    class="size-4 opacity-40 transition-opacity group-hover:opacity-100"
                                />
                            </span>
                            <span class="text-3xl font-semibold">
                                {{ formatNumber(item.value) }}
                            </span>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <Card class="gap-4">
                <CardHeader class="flex flex-row items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-md border bg-card"
                    >
                        <RefreshCw class="size-5" />
                    </div>
                    <CardTitle class="text-base">快捷操作</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <Button
                        v-for="action in quickActions"
                        :key="action.title"
                        variant="outline"
                        class="justify-start"
                        as-child
                    >
                        <Link :href="action.href">
                            <component
                                :is="action.icon"
                                data-icon="inline-start"
                            />
                            {{ action.title }}
                            <ArrowUpRight
                                class="ml-auto"
                                data-icon="inline-end"
                            />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
