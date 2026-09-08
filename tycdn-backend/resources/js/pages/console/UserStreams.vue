<script setup lang="ts">
import {
    AlertCircle,
    BarChart3,
    ExternalLink,
    Network,
    Plus,
    RefreshCw,
    Save,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { router } from '@inertiajs/vue3';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { DateRangePicker } from '@/components/ui/date-range-picker';
import {
    formatDate,
    getErrorMessage,
    jsonText,
    numberValue,
    recordId,
    textValue,
    yesNo,
} from '@/lib/cdnRecord';
import {
    createUserStream,
    deleteUserStream,
    extractCdnflyRows,
    extractCdnflyTotal,
    getUserStreamRealtime,
    getUserStreamTop,
    listUserPackages,
    listUserStreamGroups,
    listUserStreams,
} from '@/lib/cdnUserApi';
import type { CdnStreamPayload, CdnflyRecord } from '@/lib/cdnUserApi';

type StreamsView = 'list' | 'analytics';

const props = defineProps<{
    view: StreamsView;
}>();

const packages = ref<CdnflyRecord[]>([]);
const streamGroups = ref<CdnflyRecord[]>([]);

const loading = ref(false);
const saving = ref(false);
const deletingId = ref<number | null>(null);
const errorMessage = ref('');
const formError = ref('');
const dialogOpen = ref(false);

const page = ref(1);
const total = ref(0);
const streams = ref<CdnflyRecord[]>([]);

const filters = reactive({
    listen_port: '',
    group: '',
    id: '',
    user_package: '',
    enable: 'all',
    per_page: '20',
});

// ── 实时曲线状态 ──────────────────────────────────────
const rtLoading = ref(false);
const rtType = ref('stream-bandwidth');
const rtMode = ref<'preset' | 'custom'>('preset');
const rtMinutes = ref(30);
const rtStart = ref(defaultStart());
const rtEnd = ref(defaultEnd());
const rtPort = ref('');
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
const hasPreviousPage = computed(() => page.value > 1);
const hasNextPage = computed(
    () => page.value * Number(filters.per_page) < total.value,
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

async function loadCurrent(targetPage = page.value): Promise<void> {
    if (props.view === 'analytics') {
        await loadRealtime();

        return;
    }

    await loadStreams(targetPage);
}

async function loadStreams(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(filters.per_page),
        };

        setOptional(params, 'listen_port', filters.listen_port);
        setOptional(params, 'group', filters.group);
        setOptional(params, 'id', filters.id);
        setOptional(params, 'user_package', filters.user_package);

        if (filters.enable !== 'all') {
            params.enable = filters.enable;
        }

        const result = await listUserStreams(params);
        const nextRows = extractCdnflyRows(result);

        streams.value = nextRows;
        total.value = extractCdnflyTotal(result, nextRows.length);
        page.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
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
        if (rtPort.value.trim()) params.port = rtPort.value.trim();

        const result = await getUserStreamRealtime(params);
        const raw = (result as { data?: unknown }).data;
        rtPoints.value = Array.isArray(raw)
            ? (raw as [number, number][]).filter(Array.isArray)
            : [];
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
    if (!canvas) return;
    if (rtChart) {
        rtChart.destroy();
        rtChart = null;
    }
    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();

    const labels = rtPoints.value.map(([ts]) => {
        const d = new Date(ts);
        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    });
    const data = rtPoints.value.map(([, v]) => v / 1048576);

    const label = rtType.value === 'stream-bandwidth' ? '带宽' : '流量';
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    rtChart = new (Chart as any)(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label,
                    data,
                    borderColor: '#6366f1',
                    backgroundColor: '#6366f118',
                    borderWidth: 2,
                    pointRadius: data.length > 60 ? 0 : 2,
                    pointHoverRadius: 4,
                    fill: true,
                    tension: 0.3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false } },
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
        if (value >= 1073741824) return `${(value / 1073741824).toFixed(2)} GB`;
        if (value >= 1048576) return `${(value / 1048576).toFixed(2)} MB`;
        if (value >= 1024) return `${(value / 1024).toFixed(1)} KB`;
        return `${value} B`;
    }
    if (value >= 100000000) return `${(value / 100000000).toFixed(2)} 亿`;
    if (value >= 10000) return `${(value / 10000).toFixed(1)} 万`;
    return String(value);
}

function openCreateDialog(): void {
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

    const backendPort = requiredNumber(form.backend_port, '回源端口');
    if (backendPort === null) return;

    let userPackage: number;
    try {
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
                    port: form.listen_port.trim(),
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

async function removeStream(record: CdnflyRecord): Promise<void> {
    const id = recordId(record);

    if (!id) {
        errorMessage.value = '转发 ID 缺失';

        return;
    }

    deletingId.value = id;
    errorMessage.value = '';

    try {
        await deleteUserStream(id);
        toast.success('转发删除请求已提交');
        await loadStreams();
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        deletingId.value = null;
    }
}

function prevPage(): void {
    if (hasPreviousPage.value) {
        void loadStreams(page.value - 1);
    }
}

function nextPage(): void {
    if (hasNextPage.value) {
        void loadStreams(page.value + 1);
    }
}

function streamCname(record: CdnflyRecord): string {
    const hostname = textValue(record.cname_hostname);
    const domain = textValue(record.cname_domain);

    if (hostname && domain) {
        return `${hostname}.${domain}`;
    }

    return '';
}

function listenText(record: CdnflyRecord): string {
    return jsonText(record.listen, '-').replace(/\s+/g, ' ');
}

function backendText(record: CdnflyRecord): string {
    return jsonText(record.backend, '-').replace(/\s+/g, ' ');
}

function requiredNumber(value: string, label: string): number {
    const parsed = optionalNumber(value);

    if (parsed === null) {
        throw new Error(`${label} 必须是数字`);
    }

    return parsed;
}

function optionalNumber(value: string): number | null {
    return numberValue(value);
}

function setOptional(
    params: Record<string, string | number>,
    key: string,
    value: string,
): void {
    if (value.trim() !== '') {
        params[key] = value.trim();
    }
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
    <div class="space-y-6">
        <ConsolePageHeader
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
                <button
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
                </button>
                <button
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
                </button>
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
                            <button
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
                            </button>
                            <button
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
                            </button>
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
                        v-if="rtLoading && rtPoints.length === 0"
                        class="flex justify-center py-16"
                    >
                        <Spinner />
                    </div>
                    <div
                        v-else-if="rtPoints.length === 0"
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
                            <button
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
                            </button>
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
                                    <th
                                        class="w-16 px-4 py-2.5 text-left font-medium"
                                    >
                                        排名
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        端口
                                    </th>
                                    <th
                                        class="w-36 px-4 py-2.5 text-right font-medium"
                                    >
                                        数值
                                    </th>
                                    <th
                                        class="w-32 px-4 py-2.5 text-right font-medium"
                                    >
                                        时间
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, i) in topRows"
                                    :key="i"
                                    class="border-b last:border-b-0"
                                >
                                    <td
                                        class="px-4 py-2.5 text-muted-foreground"
                                    >
                                        {{ i + 1 }}
                                    </td>
                                    <td class="px-4 py-2.5 font-medium">
                                        {{
                                            textValue(row.port) ||
                                            textValue(row.key) ||
                                            textValue(row.name) ||
                                            '-'
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right tabular-nums"
                                    >
                                        {{
                                            formatMetric(
                                                Number(
                                                    textValue(row['value']) ||
                                                        textValue(row.count) ||
                                                        textValue(
                                                            row.traffic,
                                                        ) ||
                                                        0,
                                                ),
                                                rtType === 'stream-traffic'
                                                    ? 'bytes'
                                                    : 'count',
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right text-muted-foreground"
                                    >
                                        {{
                                            formatDate(
                                                row.time ?? row.timestamp,
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

        <Card v-else>
            <CardHeader class="space-y-4">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <CardTitle>转发列表</CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ total === 0 ? '暂无转发' : `${total} 条转发` }}
                        </p>
                    </div>
                    <Button @click="openCreateDialog">
                        <Plus data-icon="inline-start" />
                        新增转发
                    </Button>
                </div>
                <form
                    class="flex flex-wrap items-center gap-2"
                    @submit.prevent="loadStreams(1)"
                >
                    <Input
                        v-model="filters.listen_port"
                        class="w-32"
                        placeholder="监听端口"
                    />
                    <Input
                        v-model="filters.group"
                        class="w-32"
                        placeholder="转发组"
                    />
                    <Input
                        v-model="filters.id"
                        class="w-32"
                        placeholder="转发 ID"
                    />
                    <Input
                        v-model="filters.user_package"
                        class="w-32"
                        placeholder="套餐 ID"
                    />
                    <Select v-model="filters.enable">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">全部状态</SelectItem>
                                <SelectItem value="1">启用</SelectItem>
                                <SelectItem value="0">禁用</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Button type="submit" :disabled="loading">
                        <Spinner v-if="loading" data-icon="inline-start" />
                        <Search v-else data-icon="inline-start" />
                        查询
                    </Button>
                </form>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto border-y">
                    <table class="w-full min-w-[1100px] table-fixed text-sm">
                        <colgroup>
                            <col style="width: 7%" />
                            <col style="width: 15%" />
                            <col style="width: 18%" />
                            <col style="width: 20%" />
                            <col style="width: 8%" />
                            <col style="width: 8%" />
                            <col style="width: 12%" />
                            <col style="width: 12%" />
                        </colgroup>
                        <thead class="border-b text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">
                                    ID
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    监听
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    回源
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    CNAME
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    套餐
                                </th>
                                <th class="px-4 py-3 text-center font-medium">
                                    状态
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    更新时间
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    操作
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading" class="border-b">
                                <td class="px-4 py-12 text-center" colspan="8">
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <tr
                                v-for="stream in streams"
                                :key="textValue(stream.id)"
                                class="border-b"
                            >
                                <td class="px-4 py-3">
                                    #{{ textValue(stream.id) || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="truncate font-mono text-xs">
                                        {{ listenText(stream) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="truncate font-mono text-xs">
                                        {{ backendText(stream) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        v-if="streamCname(stream)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            class="truncate font-mono text-xs"
                                        >
                                            {{ streamCname(stream) }}
                                        </span>
                                    </div>
                                    <span v-else class="text-muted-foreground"
                                        >-</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    {{ textValue(stream.user_package) || '-' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Badge variant="secondary">
                                        {{ yesNo(stream.enable) }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{
                                        formatDate(
                                            stream.update_at2 ??
                                                stream.create_at2,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="goToDetail(stream)"
                                        >
                                            <ExternalLink
                                                data-icon="inline-start"
                                            />
                                            详情
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            :disabled="
                                                deletingId === recordId(stream)
                                            "
                                            @click="removeStream(stream)"
                                        >
                                            <Spinner
                                                v-if="
                                                    deletingId ===
                                                    recordId(stream)
                                                "
                                                data-icon="inline-start"
                                            />
                                            <Trash2
                                                v-else
                                                data-icon="inline-start"
                                            />
                                            删除
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && streams.length === 0">
                                <td
                                    class="px-6 py-16 text-center text-muted-foreground"
                                    colspan="8"
                                >
                                    暂无转发
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="props.view === 'list'"
            class="flex items-center justify-end gap-2"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasPreviousPage || loading"
                @click="prevPage"
            >
                上一页
            </Button>
            <span class="text-sm text-muted-foreground">第 {{ page }} 页</span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasNextPage || loading"
                @click="nextPage"
            >
                下一页
            </Button>
        </div>

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
                        <button
                            type="button"
                            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                            @click="showAdvancedCreate = !showAdvancedCreate"
                        >
                            <span>{{ showAdvancedCreate ? '▲' : '▼' }}</span>
                            可选配置
                        </button>
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

                    <p class="text-xs text-muted-foreground">
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
