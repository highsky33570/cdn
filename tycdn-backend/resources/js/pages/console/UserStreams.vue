<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    BarChart3,
    Network,
    RefreshCw,
    Save,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import UserStreamWorkspace from '@/components/console/UserStreamWorkspace.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { cdnflyStreamSeries } from '@/lib/cdnflyResponse';
import {
    formatDate,
    getErrorMessage,
    recordId,
    textValue,
} from '@/lib/cdnRecord';
import {
    createUserStream,
    extractCdnflyRows,
    getUserStreamRealtime,
    getUserStreamTop,
    listUserPackages,
    listUserStreamGroups,
} from '@/lib/cdnUserApi';
import type { CdnflyRecord } from '@/lib/cdnUserApi';

import { masterGet } from '@/lib/masterApi';

type StreamsView = 'list' | 'analytics';

const props = defineProps<{
    view: StreamsView;
    scope?: 'admin' | 'user';
}>();

const workspace = ref<InstanceType<typeof UserStreamWorkspace> | null>(null);
const packages = ref<CdnflyRecord[]>([]);
const streamGroups = ref<CdnflyRecord[]>([]);

const saving = ref(false);
const errorMessage = ref('');
const formError = ref('');
const dialogOpen = ref(false);

// ── 实时曲线状态 ──────────────────────────────────────
const rtLoading = ref(false);
const rtType = ref('stream-bandwidth');
const rtMode = ref<'preset' | 'custom'>('preset');
const rtMinutes = ref(30);
const rtStart = ref(defaultStart());
const rtEnd = ref(defaultEnd());
const rtPort = ref('');
const rtInboundPoints = ref<[number, number][]>([]);
const rtPoints = ref<[number, number][]>([]);
const rtCanvasRef = ref<HTMLCanvasElement | null>(null);
let rtChart: { destroy(): void } | null = null;

// ── 排行统计状态 ──────────────────────────────────────
const topActive = ref<'top-ports'>('top-ports');
const topRecentTime = ref('10m');
const topLoading = ref(false);
const topRows = ref<CdnflyRecord[]>([]);

// ── 当前 analytics tab ────────────────────────────────
const analyticsTab = ref<'realtime' | 'top'>('realtime');

// 新增弹窗极简表单：套餐 + 监听协议/端口 + 回源地址/端口
const form = reactive({
    user_package: '',
    listen_protocol: 'tcp' as 'tcp' | 'udp',
    listen_port: '',
    backend_addr: '',
    backend_port: '',
    groups: '',
    des: '',
});

const showAdvancedCreate = ref(false);

const title = computed(() =>
    props.view === 'analytics' ? '四层实时监控' : '转发列表',
);
const description = computed(() =>
    props.view === 'analytics'
        ? '四层转发实时带宽/流量曲线与端口排行统计。'
        : '维护 TCP/UDP 四层转发规则。',
);
const dialogTitle = computed(() => '新增转发');

onMounted(() => {
    void loadCurrent();
    void loadPackages();
    void loadStreamGroups();
});

async function loadPackages(): Promise<void> {
    try {
        const result = await listUserPackages({ page: 1, limit: 100 });
        packages.value = extractCdnflyRows(result);
    } catch {
        // 静默处理
    }
}

async function loadStreamGroups(): Promise<void> {
    try {
        const result = await listUserStreamGroups({ page: 1, limit: 100 });
        streamGroups.value = extractCdnflyRows(result);
    } catch {
        // 静默处理
    }
}

async function loadCurrent(): Promise<void> {
    if (props.view === 'analytics') {
        await loadRealtime();
    }
}
async function loadStreams(): Promise<void> {
    await workspace.value?.refresh();
}

// ── 实时曲线 ─────────────────────────────────────────
async function loadRealtime(): Promise<void> {
    rtLoading.value = true;
    errorMessage.value = '';

    try {
        const start =
            rtMode.value === 'custom'
                ? rtStart.value
                : formatInputDate(
                      new Date(Date.now() - rtMinutes.value * 60 * 1000),
                  );
        const end =
            rtMode.value === 'custom'
                ? rtEnd.value
                : formatInputDate(new Date());
        const params: Record<string, string | number> = {
            type: rtType.value,
            start,
            end,
        };

        if (rtPort.value.trim()) {
            params.port = rtPort.value.trim();
        }

        const result = await (props.scope === 'admin'
            ? masterGet('stream-realtime', params)
            : getUserStreamRealtime(params));
        const series = cdnflyStreamSeries(result);
        rtPoints.value = series.outbound;
        rtInboundPoints.value = series.inbound;
        await nextTick();
        await renderRtChart();
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        rtLoading.value = false;
    }
}

function setRtMinutes(minutes: number): void {
    rtMode.value = 'preset';
    rtMinutes.value = minutes;
    void loadRealtime();
}

function setRtCustom(): void {
    rtMode.value = 'custom';
}

function confirmRtCustom(): void {
    if (rtStart.value && rtEnd.value) {
        void loadRealtime();
    }
}

async function renderRtChart(): Promise<void> {
    await ensureChartJs();
    const Chart = (window as unknown as Record<string, unknown>)['Chart'] as {
        new (canvas: HTMLCanvasElement, config: unknown): unknown;
        getChart: (
            canvas: HTMLCanvasElement,
        ) => { destroy(): void } | undefined;
    };
    const canvas = rtCanvasRef.value;

    if (!canvas) {
        return;
    }

    if (rtChart) {
        rtChart.destroy();
        rtChart = null;
    }

    const existing = Chart.getChart(canvas);

    if (existing) {
        existing.destroy();
    }

    const timestamps = [
        ...new Set(
            [...rtPoints.value, ...rtInboundPoints.value].map(([ts]) => ts),
        ),
    ].sort((a, b) => a - b);
    const labels = timestamps.map((ts) => {
        const d = new Date(ts);

        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    });
    const label = rtType.value === 'stream-bandwidth' ? '带宽' : '流量';
    const datasets = [
        { points: rtPoints.value, label: `出站${label}`, color: '#6366f1' },
        {
            points: rtInboundPoints.value,
            label: `入站${label}`,
            color: '#06b6d4',
        },
    ]
        .filter((series) => series.points.length > 0)
        .map((series) => {
            const byTime = new Map(series.points);

            return {
                label: series.label,
                data: timestamps.map((ts) =>
                    byTime.has(ts) ? byTime.get(ts)! / 1048576 : null,
                ),
                borderColor: series.color,
                backgroundColor: `${series.color}18`,
                borderWidth: 2,
                pointRadius: timestamps.length > 60 ? 0 : 2,
                pointHoverRadius: 4,
                fill: true,
                tension: 0.3,
            };
        });

    rtChart = new (Chart as any)(canvas, {
        type: 'line',
        data: {
            labels,
            datasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: datasets.length > 1 } },
            scales: {
                x: {
                    ticks: {
                        maxTicksLimit: 6,
                        maxRotation: 0,
                        color: '#94a3b8',
                        font: { size: 10 },
                    },
                    grid: { color: '#f1f5f9' },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 10 },
                        callback: (val: number) =>
                            `${val.toFixed(val < 1 ? 2 : 1)} MB${rtType.value === 'stream-bandwidth' ? '/s' : ''}`,
                    },
                    grid: { color: '#f1f5f9' },
                },
            },
        },
    });
}

// ── 排行统计 ─────────────────────────────────────────
async function loadTop(): Promise<void> {
    topLoading.value = true;
    errorMessage.value = '';

    try {
        const result = await getUserStreamTop({
            type: topActive.value,
            recent_time: topRecentTime.value,
        });
        topRows.value = extractCdnflyRows(result);
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        topLoading.value = false;
    }
}

function setTopRecentTime(t: string): void {
    topRecentTime.value = t;
    void loadTop();
}

// ── Chart.js lazy load ────────────────────────────────
let chartJsLoaded = false;
async function ensureChartJs(): Promise<void> {
    if (
        chartJsLoaded ||
        (window as unknown as Record<string, unknown>)['Chart']
    ) {
        chartJsLoaded = true;

        return;
    }

    await loadScript(
        'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js',
    );
    chartJsLoaded = true;
}
function loadScript(src: string): Promise<void> {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Failed to load ${src}`));
        document.head.appendChild(script);
    });
}

// ── 工具 ─────────────────────────────────────────────
function formatMetric(value: number, unit: 'bytes' | 'count'): string {
    if (unit === 'bytes') {
        if (value >= 1073741824) {
            return `${(value / 1073741824).toFixed(2)} GB`;
        }

        if (value >= 1048576) {
            return `${(value / 1048576).toFixed(2)} MB`;
        }

        if (value >= 1024) {
            return `${(value / 1024).toFixed(1)} KB`;
        }

        return `${value} B`;
    }

    if (value >= 100000000) {
        return `${(value / 100000000).toFixed(2)} 亿`;
    }

    if (value >= 10000) {
        return `${(value / 10000).toFixed(1)} 万`;
    }

    return String(value);
}

function openCreateDialog(): void {
    void loadStreamGroups();
    form.user_package = '';
    form.listen_protocol = 'tcp';
    form.listen_port = '';
    form.backend_addr = '';
    form.backend_port = '';
    form.groups = '__none__';
    form.des = '';
    showAdvancedCreate.value = false;
    formError.value = '';
    dialogOpen.value = true;
}

function goToDetail(record: CdnflyRecord): void {
    const id = recordId(record);

    if (id) {
        router.visit(`/console/streams/${id}`);
    }
}

async function submitStream(): Promise<void> {
    formError.value = '';

    if (!form.listen_port.trim()) {
        formError.value = '监听端口不能为空';

        return;
    }

    if (!form.backend_addr.trim()) {
        formError.value = '回源地址不能为空';

        return;
    }

    let backendPort: number;
    let listenPort: number;
    let userPackage: number;

    try {
        backendPort = requiredNumber(form.backend_port, '回源端口');
        listenPort = requiredNumber(form.listen_port, '监听端口');

        if (
            ![backendPort, listenPort].every(
                (port) => Number.isInteger(port) && port >= 1 && port <= 65535,
            )
        ) {
            throw new Error('端口必须是 1 到 65535 之间的整数');
        }

        userPackage = requiredNumber(form.user_package, '套餐');
    } catch (error) {
        formError.value = getErrorMessage(error);

        return;
    }

    saving.value = true;

    try {
        const result = await createUserStream({
            user_package: userPackage,
            listen: [
                {
                    protocol: form.listen_protocol,
                    port: listenPort,
                },
            ],
            backend_port: backendPort,
            backend: [
                { addr: form.backend_addr.trim(), weight: 1, state: 'up' },
            ],
            ...(form.groups && form.groups !== '__none__'
                ? { groups: form.groups }
                : {}),
            ...(form.des.trim() ? { des: form.des.trim() } : {}),
        });
        dialogOpen.value = false;
        // 新增成功后跳转详情页
        const newId = recordId(result);

        if (newId) {
            router.visit(`/console/streams/${newId}`);
        } else {
            await loadStreams();
        }
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

function requiredNumber(value: string, label: string): number {
    const parsed = Number(value);

    if (!value.trim() || !Number.isFinite(parsed)) {
        throw new Error(`${label} 必须是数字`);
    }

    return parsed;
}

function defaultStart(): string {
    const date = new Date(Date.now() - 60 * 60 * 1000);

    return formatInputDate(date);
}

function defaultEnd(): string {
    return formatInputDate(new Date());
}

function formatInputDate(date: Date): string {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
        date.getDate(),
    )} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(
        date.getSeconds(),
    )}`;
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            v-if="props.view === 'analytics'"
            eyebrow="用户端 / 四层转发"
            :title="title"
            :description="description"
            :icon="props.view === 'analytics' ? BarChart3 : Network"
            :show-api-badge="false"
        />

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>
        <template v-if="props.view === 'analytics'">
            <!-- Tab 切换 -->
            <div class="flex w-fit gap-1 rounded-md border p-0.5">
                <Button
                    variant="ghost"
                    data-slot="console-tab"
                    type="button"
                    class="rounded px-4 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        analyticsTab === 'realtime'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="analyticsTab = 'realtime'"
                >
                    实时曲线
                </Button>
                <Button
                    variant="ghost"
                    data-slot="console-tab"
                    type="button"
                    class="rounded px-4 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        analyticsTab === 'top'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="
                        analyticsTab = 'top';
                        loadTop();
                    "
                >
                    排行统计
                </Button>
            </div>

            <!-- ═══ 实时曲线 ═══ -->
            <Card v-if="analyticsTab === 'realtime'">
                <CardHeader class="pb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <Select
                            v-model="rtType"
                            @update:model-value="loadRealtime()"
                        >
                            <SelectTrigger class="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="stream-bandwidth"
                                        >带宽</SelectItem
                                    >
                                    <SelectItem value="stream-traffic"
                                        >流量</SelectItem
                                    >
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <div class="flex gap-1 rounded-md border p-0.5">
                            <Button
                                variant="ghost"
                                v-for="t in [
                                    { m: 10, label: '10m' },
                                    { m: 30, label: '30m' },
                                    { m: 60, label: '1h' },
                                    { m: 360, label: '6h' },
                                    { m: 1440, label: '24h' },
                                ]"
                                :key="t.m"
                                type="button"
                                class="rounded px-3 py-1 text-xs font-medium transition-colors"
                                :class="
                                    rtMode === 'preset' && rtMinutes === t.m
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="setRtMinutes(t.m)"
                            >
                                {{ t.label }}
                            </Button>
                            <Button
                                variant="ghost"
                                type="button"
                                class="rounded px-3 py-1 text-xs font-medium transition-colors"
                                :class="
                                    rtMode === 'custom'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="setRtCustom()"
                            >
                                自定义
                            </Button>
                        </div>
                        <template v-if="rtMode === 'custom'">
                            <DateRangePicker
                                :start="rtStart"
                                :end="rtEnd"
                                @update:start="rtStart = $event"
                                @update:end="rtEnd = $event"
                            />
                            <Button
                                variant="default"
                                size="sm"
                                :disabled="rtLoading"
                                @click="confirmRtCustom"
                            >
                                <Search
                                    data-icon="inline-start"
                                    class="size-3.5"
                                />
                                查询
                            </Button>
                        </template>
                        <div class="flex items-center gap-1.5">
                            <Label
                                class="text-xs whitespace-nowrap text-muted-foreground"
                                >端口</Label
                            >
                            <Input
                                v-model="rtPort"
                                class="h-8 w-24 text-xs"
                                placeholder="可选"
                                @keyup.enter="loadRealtime()"
                            />
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="rtLoading"
                            @click="loadRealtime()"
                        >
                            <Spinner
                                v-if="rtLoading"
                                data-icon="inline-start"
                            />
                            <RefreshCw
                                v-else
                                data-icon="inline-start"
                                class="size-3.5"
                            />
                            刷新
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="
                            rtLoading &&
                            rtPoints.length === 0 &&
                            rtInboundPoints.length === 0
                        "
                        class="flex justify-center py-16"
                    >
                        <Spinner />
                    </div>
                    <div
                        v-else-if="
                            rtPoints.length === 0 &&
                            rtInboundPoints.length === 0
                        "
                        class="py-16 text-center text-sm text-muted-foreground"
                    >
                        暂无数据
                    </div>
                    <div v-else class="relative" style="height: 320px">
                        <canvas ref="rtCanvasRef" />
                    </div>
                </CardContent>
            </Card>

            <!-- ═══ 排行统计 ═══ -->
            <Card v-else>
                <CardHeader class="pb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex gap-1 rounded-md border p-0.5">
                            <Button
                                variant="ghost"
                                v-for="t in [
                                    { v: '10m', label: '10m' },
                                    { v: '30m', label: '30m' },
                                    { v: '60m', label: '1h' },
                                ]"
                                :key="t.v"
                                type="button"
                                class="rounded px-3 py-1 text-xs font-medium transition-colors"
                                :class="
                                    topRecentTime === t.v
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="setTopRecentTime(t.v)"
                            >
                                {{ t.label }}
                            </Button>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="topLoading"
                            @click="loadTop()"
                        >
                            <Spinner
                                v-if="topLoading"
                                data-icon="inline-start"
                            />
                            <RefreshCw
                                v-else
                                data-icon="inline-start"
                                class="size-3.5"
                            />
                            刷新
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="topLoading && topRows.length === 0"
                        class="flex justify-center py-12"
                    >
                        <Spinner />
                    </div>
                    <div
                        v-else-if="topRows.length === 0"
                        class="py-12 text-center text-sm text-muted-foreground"
                    >
                        暂无排行数据
                    </div>
                    <div v-else class="overflow-x-auto border-y">
                        <table class="w-full text-sm">
                            <thead class="border-b text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 text-left">排名</th>
                                    <th class="px-4 py-3 text-left">端口</th>
                                    <th class="px-4 py-3 text-right">连接数</th>
                                    <th class="px-4 py-3 text-right">
                                        出站流量
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, i) in topRows"
                                    :key="i"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="px-4 py-3">{{ i + 1 }}</td>
                                    <td class="px-4 py-3">
                                        {{ row.res ?? row.port ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{
                                            formatMetric(
                                                Number(
                                                    row.new_connections ??
                                                        row.count ??
                                                        0,
                                                ),
                                                'count',
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{
                                            formatMetric(
                                                Number(
                                                    row.outbound_traffic ??
                                                        row.traffic ??
                                                        0,
                                                ),
                                                'bytes',
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </template>

        <UserStreamWorkspace
            v-else
            ref="workspace"
            :packages="packages"
            @create="openCreateDialog"
            @manage="goToDetail"
        />

        <Dialog v-model:open="dialogOpen">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>
                        填写基本信息即可创建，更多配置在详情页完成。
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitStream">
                    <Alert v-if="formError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>提交失败</AlertTitle>
                        <AlertDescription>{{ formError }}</AlertDescription>
                    </Alert>

                    <!-- 套餐 -->
                    <div class="grid gap-2">
                        <Label>套餐</Label>
                        <Select v-model="form.user_package" required>
                            <SelectTrigger>
                                <SelectValue placeholder="选择套餐" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="pkg in packages"
                                        :key="textValue(pkg.id)"
                                        :value="textValue(pkg.id)"
                                    >
                                        {{
                                            textValue(pkg.user_package_name) ||
                                            textValue(pkg.name)
                                        }}
                                        <span
                                            class="ml-1 text-xs text-muted-foreground"
                                        >
                                            · 到期 {{ formatDate(pkg.end_at2) }}
                                        </span>
                                    </SelectItem>
                                </SelectGroup>
                                <div
                                    v-if="packages.length === 0"
                                    class="px-3 py-4 text-center text-xs text-muted-foreground"
                                >
                                    暂无套餐
                                </div>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- 监听 -->
                    <div class="grid gap-2">
                        <Label>监听</Label>
                        <div class="flex gap-2">
                            <Select
                                v-model="form.listen_protocol"
                                class="w-28 shrink-0"
                            >
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="tcp">TCP</SelectItem>
                                        <SelectItem value="udp">UDP</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <Input
                                v-model="form.listen_port"
                                inputmode="numeric"
                                placeholder="端口，如 80"
                                required
                            />
                        </div>
                    </div>

                    <!-- 回源 -->
                    <div class="grid gap-2">
                        <Label>回源</Label>
                        <div class="flex gap-2">
                            <Input
                                v-model="form.backend_addr"
                                placeholder="地址，如 1.2.3.4"
                                class="flex-1"
                                required
                            />
                            <Input
                                v-model="form.backend_port"
                                inputmode="numeric"
                                placeholder="端口"
                                class="w-24 shrink-0"
                                required
                            />
                        </div>
                    </div>

                    <!-- 可选配置展开 -->
                    <div class="border-t pt-3">
                        <Button
                            variant="ghost"
                            type="button"
                            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                            @click="showAdvancedCreate = !showAdvancedCreate"
                        >
                            <span>{{ showAdvancedCreate ? '▲' : '▼' }}</span>
                            可选配置
                        </Button>
                        <div v-if="showAdvancedCreate" class="mt-3 grid gap-3">
                            <div class="grid gap-2">
                                <Label>转发组</Label>
                                <Select v-model="form.groups">
                                    <SelectTrigger>
                                        <SelectValue placeholder="不分组" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="__none__"
                                                >不分组</SelectItem
                                            >
                                            <SelectItem
                                                v-for="group in streamGroups"
                                                :key="textValue(group.id)"
                                                :value="textValue(group.id)"
                                            >
                                                {{ textValue(group.name) }}
                                                <span
                                                    class="ml-1 text-xs text-muted-foreground"
                                                >
                                                    #{{ textValue(group.id) }}
                                                </span>
                                            </SelectItem>
                                        </SelectGroup>
                                        <div
                                            v-if="streamGroups.length === 0"
                                            class="px-3 py-4 text-center text-xs text-muted-foreground"
                                        >
                                            暂无转发组
                                        </div>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="create-des">备注</Label>
                                <Input
                                    id="create-des"
                                    v-model="form.des"
                                    placeholder="用于标识此转发，如 game-server-tcp"
                                />
                            </div>
                        </div>
                    </div>

                    <p data-typography="helper" class="text-muted-foreground">
                        创建后将跳转详情页，可继续配置负载均衡、ACL 等高级选项。
                    </p>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="dialogOpen = false"
                        >
                            取消
                        </Button>
                        <Button type="submit" :disabled="saving">
                            <Spinner v-if="saving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            创建
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
