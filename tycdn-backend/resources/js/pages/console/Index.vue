<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    ArrowRight,
    BellRing,
    FileKey2,
    Globe2,
    PackageCheck,
    RefreshCw,
    ShoppingCart,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { siteRankingRows } from '@/lib/cdnflyResponse';
import { formatDate, formatMoney, getErrorMessage, textValue } from '@/lib/cdnRecord';
import {
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
    getUserOverview,
    getUserPackageUsage,
    getUserSiteRealtime,
    getUserSiteTop,
    listUserCerts,
    listUserMessages,
    listUserPackages,
    listUserSites,
    listUserStreams,
} from '@/lib/cdnUserApi';
import type { CdnflyListData, CdnflyRecord } from '@/lib/cdnUserApi';
import type { User } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: '服务概况', href: '/console' }],
    },
});

type Point = [number, number];
type Period = 'today' | 'yesterday' | '7d' | '30d';

const page = usePage();
const user = computed(() => page.props.auth.user as User);
const loading = ref(false);
const errorMessage = ref('');
const period = ref<Period>('today');
const overview = ref<CdnflyRecord>({});
const packages = ref<CdnflyRecord[]>([]);
const sitesTotal = ref(0);
const certsTotal = ref(0);
const streamsTotal = ref(0);
const messages = ref<CdnflyRecord[]>([]);
const usage = ref<CdnflyRecord>({});
const bandwidth = ref<Point[]>([]);
const requests = ref<Point[]>([]);
const traffic = ref<Point[]>([]);
const topDomains = ref<CdnflyRecord[]>([]);

const periods: { value: Period; label: string }[] = [
    { value: 'today', label: '今日' },
    { value: 'yesterday', label: '昨日' },
    { value: '7d', label: '近7日' },
    { value: '30d', label: '近30日' },
];

const activePackage = computed(() => packages.value[0] ?? null);
const packageName = computed(() =>
    activePackage.value
        ? textValue(
              activePackage.value.package_name ??
                  activePackage.value.name ??
                  activePackage.value.title,
          ) || '已购套餐'
        : '暂无套餐',
);
const balance = computed(() =>
    money(overview.value.balance ?? user.value.balance),
);
const currentSeries = computed(() => {
    if (chartMetric.value === 'request') {
        return requests.value;
    }

    if (chartMetric.value === 'traffic') {
        return traffic.value;
    }

    return bandwidth.value;
});
const chartMetric = ref<'bandwidth' | 'request' | 'traffic'>('bandwidth');
const chartValues = computed(() => currentSeries.value.map((point) => point[1]));
const chartMax = computed(() => Math.max(...chartValues.value, 1));
const chartPoints = computed(() => {
    const rows = currentSeries.value;

    if (rows.length === 0) {
        return '';
    }

    return rows
        .map((point, index) => {
            const x = rows.length === 1 ? 0 : (index / (rows.length - 1)) * 100;
            const y = 92 - (point[1] / chartMax.value) * 82;

            return `${x.toFixed(2)},${y.toFixed(2)}`;
        })
        .join(' ');
});
const chartArea = computed(() =>
    chartPoints.value ? `0,100 ${chartPoints.value} 100,100` : '',
);
const chartLabels = computed(() => {
    const rows = currentSeries.value;

    if (rows.length === 0) {
        return [];
    }

    const picks = [0, Math.floor((rows.length - 1) / 2), rows.length - 1];

    return [...new Set(picks)].map((index) => ({
        x: rows.length === 1 ? 0 : (index / (rows.length - 1)) * 100,
        text: timeLabel(rows[index][0]),
    }));
});

const peakBandwidth = computed(() => formatBandwidth(Math.max(...bandwidth.value.map((item) => item[1]), 0)));
const requestTotal = computed(() => formatCount(requests.value.reduce((sum, item) => sum + item[1], 0)));
const trafficTotal = computed(() => formatBytes(traffic.value.reduce((sum, item) => sum + item[1], 0)));
const blockedIps = computed(() =>
    formatCount(numberValue(overview.value.black_ip_count ?? overview.value.blocked_ip_count)),
);
const trafficQuota = computed(() => numberValue(activePackage.value?.traffic));
const trafficUsed = computed(() => {
    const raw = usage.value.traffic_usage ?? usage.value.used_traffic ?? activePackage.value?.traffic_usage;
    const value = numberValue(raw);

    return value > trafficQuota.value * 1024 ? value / 1024 ** 3 : value;
});
const trafficPercent = computed(() => {
    if (trafficQuota.value <= 0) {
        return 0;
    }

    return Math.min(100, Math.max(0, (trafficUsed.value / trafficQuota.value) * 100));
});

const summaryCards = computed(() => [
    { label: '带宽峰值', value: peakBandwidth.value },
    { label: '请求数', value: requestTotal.value },
    { label: '总流量', value: trafficTotal.value },
    { label: '拉黑 IP 数', value: blockedIps.value },
]);
const resourceRows = computed(() => [
    { label: '域名数', value: sitesTotal.value, href: '/console/sites' },
    { label: '转发数', value: streamsTotal.value, href: '/console/streams' },
    { label: '证书', value: certsTotal.value, href: '/console/certificates' },
    {
        label: '已购套餐',
        value: packages.value.length,
        href: '/console/billing/subscriptions',
    },
]);

onMounted(() => void loadDashboard());

async function loadDashboard(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    const range = timeRange(period.value);
    const tasks = await Promise.allSettled([
        getUserOverview(),
        listUserPackages({ page: 1, limit: 50 }),
        listUserSites({ page: 1, limit: 1 }),
        listUserCerts({ page: 1, limit: 1 }),
        listUserStreams({ page: 1, limit: 1 }),
        listUserMessages({ page: 1, limit: 5 }),
        getUserSiteRealtime({ type: 'bandwidth', ...range }),
        getUserSiteRealtime({ type: 'req', ...range }),
        getUserSiteRealtime({ type: 'traffic', ...range }),
        getUserSiteTop({ type: 'top-domain', recent_time: '30m' }),
    ]);

    overview.value = extractCdnflyRecord(value(tasks[0])) ?? {};
    packages.value = rows(value(tasks[1]));
    sitesTotal.value = total(value(tasks[2]));
    certsTotal.value = total(value(tasks[3]));
    streamsTotal.value = total(value(tasks[4]));
    messages.value = rows(value(tasks[5]));
    bandwidth.value = points(value(tasks[6]));
    requests.value = points(value(tasks[7]));
    traffic.value = points(value(tasks[8]));
    topDomains.value = siteRankingRows(value(tasks[9]), 'top-domain').slice(
        0,
        10,
    );

    const packageId = Number(activePackage.value?.id);

    if (packageId > 0) {
        try {
            const result = await getUserPackageUsage(packageId);
            const usageRows = extractCdnflyRows(result);
            usage.value = usageRows.length
                ? Object.assign({}, ...usageRows)
                : extractCdnflyRecord(result) ?? {};
        } catch {
            usage.value = {};
        }
    }

    const failed = tasks.find((task) => task.status === 'rejected');

    if (tasks.every((task) => task.status === 'rejected') && failed?.status === 'rejected') {
        errorMessage.value = getErrorMessage(failed.reason);
    }

    loading.value = false;
}

function changePeriod(next: Period): void {
    if (period.value === next) {
        return;
    }

    period.value = next;
    void loadDashboard();
}

function value<T>(result: PromiseSettledResult<T>): T | null {
    return result.status === 'fulfilled' ? result.value : null;
}

function rows(payload: unknown): CdnflyRecord[] {
    return payload ? extractCdnflyRows(payload as CdnflyListData) : [];
}

function total(payload: unknown): number {
    if (!payload) {
        return 0;
    }

    const found = rows(payload);

    return extractCdnflyTotal(payload as CdnflyListData, found.length);
}

function points(payload: unknown): Point[] {
    const raw = (payload as { data?: unknown } | null)?.data;

    if (!Array.isArray(raw)) {
        return [];
    }

    return raw
        .filter((item): item is unknown[] => Array.isArray(item) && item.length >= 2)
        .map((item) => [Number(item[0]), Number(item[1])] as Point)
        .filter((item) => Number.isFinite(item[0]) && Number.isFinite(item[1]));
}

function timeRange(selected: Period): { start: string; end: string } {
    const now = new Date();
    let start = new Date(now);
    let end = now;

    if (selected === 'today') {
        start.setHours(0, 0, 0, 0);
    }

    if (selected === 'yesterday') {
        end = new Date(now);
        end.setHours(0, 0, 0, 0);
        start = new Date(end.getTime() - 24 * 60 * 60 * 1000);
    }

    if (selected === '7d') {
        start = new Date(now.getTime() - 7 * 86400000);
    }

    if (selected === '30d') {
        start = new Date(now.getTime() - 30 * 86400000);
    }

    return { start: apiDate(start), end: apiDate(end) };
}

function apiDate(date: Date): string {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

function numberValue(value: unknown): number {
    const result = Number(value);

    return Number.isFinite(result) ? result : 0;
}

function money(value: unknown): string {
    return formatMoney(value);
}

function formatCount(value: number): string {
    if (value >= 10000) {
        return `${trim(value / 10000)}万次`;
    }

    return `${new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 0 }).format(value)}次`;
}

function formatBytes(value: number): string {
    if (value <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1);

    return `${trim(value / 1024 ** index)} ${units[index]}`;
}

function formatBandwidth(value: number): string {
    if (value <= 0) {
        return '0 bps';
    }

    const units = ['bps', 'Kbps', 'Mbps', 'Gbps'];
    let current = value * 8;
    let index = 0;

    while (current >= 1000 && index < units.length - 1) {
        current /= 1000;
        index++;
    }

    return `${trim(current)} ${units[index]}`;
}

function trim(value: number): string {
    return new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 2 }).format(value);
}

function timeLabel(value: number): string {
    const date = new Date(value < 10_000_000_000 ? value * 1000 : value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return `${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function topName(row: CdnflyRecord): string {
    return textValue(row.domain ?? row.host ?? row.name ?? row.key) || '-';
}

function topRequests(row: CdnflyRecord): string {
    return formatCount(numberValue(row.req ?? row.requests ?? row.count));
}

function topTraffic(row: CdnflyRecord): string {
    return formatBytes(numberValue(row.traffic ?? row.bytes ?? row.size));
}

function messageTitle(row: CdnflyRecord): string {
    return textValue(row.title ?? row.subject ?? row.content) || '系统消息';
}
</script>

<template>
    <Head title="服务概况" />

    <div class="flex flex-1 flex-col gap-3 p-4 md:p-6">
        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>概况加载失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <Card class="gap-0 overflow-hidden">
            <CardContent class="flex flex-col gap-5 p-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary text-xl font-semibold text-primary-foreground">
                        {{ user.name.slice(0, 1).toUpperCase() }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="truncate text-lg font-semibold">{{ user.name }}</h1>
                            <Badge variant="outline">ID {{ user.id }}</Badge>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">欢迎回来，祝你今天工作顺利。</p>
                        <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-xs text-muted-foreground">
                            <span>账户余额 <strong class="ml-1 text-foreground">{{ balance }}</strong></span>
                            <span>注册时间 <strong class="ml-1 font-normal text-foreground">{{ formatDate(user.created_at) }}</strong></span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" :disabled="loading" @click="loadDashboard">
                        <Spinner v-if="loading" data-icon="inline-start" />
                        <RefreshCw v-else data-icon="inline-start" />
                        刷新数据
                    </Button>
                    <Button size="sm" as-child><Link href="/console/sites">创建站点</Link></Button>
                </div>
            </CardContent>
        </Card>

        <Card class="gap-0">
            <CardHeader class="flex flex-row items-center justify-between border-b px-5 py-3">
                <CardTitle class="flex items-center gap-2 text-sm"><TrendingUp class="size-4 text-primary" />网络概览</CardTitle>
                <div class="flex rounded-md border bg-background p-0.5">
                    <button v-for="item in periods" :key="item.value" class="rounded px-3 py-1 text-xs transition-colors" :class="period === item.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'" @click="changePeriod(item.value)">{{ item.label }}</button>
                </div>
            </CardHeader>
            <CardContent class="grid gap-px bg-border p-0 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="item in summaryCards" :key="item.label" class="bg-card px-5 py-5">
                    <div class="text-xs text-muted-foreground">{{ item.label }}</div>
                    <div class="mt-2 text-2xl font-medium tracking-tight">{{ item.value }}</div>
                </div>
            </CardContent>
        </Card>

        <div class="grid min-w-0 gap-3 xl:grid-cols-[minmax(0,1fr)_300px]">
            <div class="grid min-w-0 gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
                <Card class="min-w-0 gap-0">
                    <CardHeader class="flex flex-row items-center justify-between border-b px-5 py-3">
                        <CardTitle class="flex items-center gap-2 text-sm"><Activity class="size-4 text-primary" />监控趋势</CardTitle>
                        <div class="flex gap-1">
                            <Button v-for="item in [{ key: 'bandwidth', label: '带宽' }, { key: 'request', label: '请求数' }, { key: 'traffic', label: '流量' }]" :key="item.key" size="sm" :variant="chartMetric === item.key ? 'secondary' : 'ghost'" class="h-7 px-3 text-xs" @click="chartMetric = item.key as typeof chartMetric">{{ item.label }}</Button>
                        </div>
                    </CardHeader>
                    <CardContent class="p-5">
                        <div v-if="loading && currentSeries.length === 0" class="flex h-72 items-center justify-center"><Spinner /></div>
                        <div v-else-if="currentSeries.length === 0" class="flex h-72 flex-col items-center justify-center gap-2 text-sm text-muted-foreground"><Activity class="size-8 opacity-30" />当前时段暂无监控数据</div>
                        <div v-else class="relative h-72 pt-2">
                            <div class="pointer-events-none absolute inset-x-0 top-3 bottom-7 flex flex-col justify-between">
                                <span v-for="line in 5" :key="line" class="border-t border-dashed"></span>
                            </div>
                            <svg class="relative h-[calc(100%-1.75rem)] w-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" aria-label="监控趋势图">
                                <polygon :points="chartArea" class="fill-primary/10" />
                                <polyline :points="chartPoints" fill="none" stroke="currentColor" stroke-width="1.2" vector-effect="non-scaling-stroke" class="text-primary" />
                            </svg>
                            <div class="relative mt-2 h-5 text-[10px] text-muted-foreground">
                                <span v-for="label in chartLabels" :key="label.x" class="absolute -translate-x-1/2 whitespace-nowrap" :style="{ left: `${label.x}%` }">{{ label.text }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="gap-0">
                    <CardHeader class="border-b px-5 py-3"><CardTitle class="flex items-center gap-2 text-sm"><TrendingUp class="size-4 text-primary" />TOP10 域名（近30分钟）</CardTitle></CardHeader>
                    <CardContent class="p-0">
                        <div class="grid grid-cols-[minmax(0,1fr)_64px_76px] border-b px-4 py-2 text-xs text-muted-foreground"><span>域名</span><span>请求</span><span class="text-right">流量</span></div>
                        <div v-if="topDomains.length === 0" class="flex h-64 items-center justify-center text-sm text-muted-foreground">暂无排行数据</div>
                        <div v-else v-for="row in topDomains" :key="topName(row)" class="grid grid-cols-[minmax(0,1fr)_64px_76px] items-center border-b px-4 py-3 text-xs last:border-b-0">
                            <span class="truncate pr-2 font-medium" :title="topName(row)">{{ topName(row) }}</span><span class="text-muted-foreground">{{ topRequests(row) }}</span><span class="text-right text-muted-foreground">{{ topTraffic(row) }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="grid content-start gap-3">
                <Card class="gap-0">
                    <CardHeader class="flex flex-row items-center justify-between border-b px-4 py-3"><CardTitle class="flex items-center gap-2 text-sm"><BellRing class="size-4 text-primary" />系统公告</CardTitle><Link href="/console/messages" class="text-xs text-primary">全部</Link></CardHeader>
                    <CardContent class="p-0">
                        <div v-if="messages.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">暂无公告</div>
                        <Link v-for="message in messages" v-else :key="textValue(message.id)" href="/console/messages" class="flex items-center justify-between gap-3 border-b px-4 py-3 text-xs last:border-b-0 hover:bg-muted/40">
                            <span class="truncate font-medium">{{ messageTitle(message) }}</span><span class="shrink-0 text-muted-foreground">{{ formatDate(message.create_at ?? message.created_at) }}</span>
                        </Link>
                    </CardContent>
                </Card>

                <Card class="gap-0">
                    <CardHeader class="border-b px-4 py-3"><CardTitle class="flex items-center gap-2 text-sm"><PackageCheck class="size-4 text-primary" />套餐流量</CardTitle></CardHeader>
                    <CardContent class="space-y-3 p-4">
                        <div class="flex items-center justify-between gap-3 text-sm"><span class="truncate font-medium">{{ packageName }}</span><Link href="/console/billing/subscriptions" class="shrink-0 text-xs text-primary">查看</Link></div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${trafficPercent}%` }"></div></div>
                        <div class="flex justify-between text-xs text-muted-foreground"><span>已用 {{ trim(trafficUsed) }} GB</span><span>{{ trafficQuota > 0 ? `${trim(trafficQuota)} GB` : '不限' }}</span></div>
                    </CardContent>
                </Card>

                <Card class="gap-0">
                    <CardHeader class="border-b px-4 py-3"><CardTitle class="flex items-center gap-2 text-sm"><Globe2 class="size-4 text-primary" />使用统计</CardTitle></CardHeader>
                    <CardContent class="p-0"><Link v-for="row in resourceRows" :key="row.label" :href="row.href" class="flex items-center justify-between border-b px-4 py-3 text-sm last:border-b-0 hover:bg-muted/40"><span class="text-muted-foreground">{{ row.label }}</span><strong class="font-medium">{{ row.value }}</strong></Link></CardContent>
                </Card>

                <div class="grid grid-cols-2 gap-2">
                    <Button variant="outline" class="justify-between" as-child><Link href="/console/certificates"><FileKey2 data-icon="inline-start" />证书<ArrowRight data-icon="inline-end" /></Link></Button>
                    <Button variant="outline" class="justify-between" as-child><Link href="/console/billing/packages"><ShoppingCart data-icon="inline-start" />套餐<ArrowRight data-icon="inline-end" /></Link></Button>
                </div>
            </div>
        </div>
    </div>
</template>
