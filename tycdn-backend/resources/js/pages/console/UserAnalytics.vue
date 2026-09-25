<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    BarChart3,
    Download,
    FileText,
    RefreshCw,
    Search,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    reactive,
    ref,
    watch,
} from 'vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
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
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { Spinner } from '@/components/ui/spinner';
import { siteRankingRows, inclusiveUsageEnd } from '@/lib/cdnflyResponse';
import {
    formatDate,
    getErrorMessage,
    jsonText,
    textValue,
} from '@/lib/cdnRecord';
import type { CdnflyRecord } from '@/lib/cdnUserApi';
import {
    accessLogDownloadUrl,
    createAccessLogJob,
    extractCdnflyRows,
    extractCdnflyTotal,
    getUserSiteRealtime as personalgetUserSiteRealtime,
    getUserSiteTop as personalgetUserSiteTop,
    getUserUsage as personalgetUserUsage,
    listAccessLogJobs,
    listUserAccessLogs as personallistUserAccessLogs,
} from '@/lib/cdnUserApi';
import { masterGet } from '@/lib/masterApi';
import {
    topTabs,
    rankingLogUrl,
    formatRankingMetric,
} from '@/lib/siteAnalysis';
import type { TopTab } from '@/lib/siteAnalysis';
import { extractMetricSeries } from '@/lib/siteRealtime';
import type { MetricSeries } from '@/lib/siteRealtime';

type AnalyticsView = 'realtime' | 'top' | 'logs' | 'usage';

const props = defineProps<{
    view: AnalyticsView;
    scope?: 'admin' | 'user';
}>();

const getUserSiteRealtime = (params: Record<string, string | number>) =>
    props.scope === 'admin'
        ? masterGet('site-realtime', params)
        : personalgetUserSiteRealtime(params);

const getUserSiteTop = (params: Record<string, string | number>) =>
    props.scope === 'admin'
        ? masterGet('site-top', params)
        : personalgetUserSiteTop(params);

const getUserUsage = (params: Record<string, string | number>) =>
    props.scope === 'admin'
        ? masterGet('usage', params)
        : personalgetUserUsage(params);

const listUserAccessLogs = (params: Record<string, string | number>) =>
    props.scope === 'admin'
        ? masterGet('access-log', params)
        : personallistUserAccessLogs(params);
// ── 实时监控：指标分组 ────────────────────────────────
const metricGroups = [
    {
        key: 'basic',
        label: '基础数据',
        metrics: [
            {
                key: 'bandwidth',
                label: '带宽',
                unit: 'bits/s',
                color: '#4f72d8',
            },
            { key: 'traffic', label: '流量', unit: 'bytes', color: '#4f72d8' },
            { key: 'req', label: '访问次数', unit: 'count', color: '#4f72d8' },
            { key: 'qps', label: 'QPS', unit: 'count', color: '#4f72d8' },
        ],
    },
    {
        key: 'quality',
        label: '质量监控',
        metrics: [
            {
                key: 'req-cache-status',
                label: '请求命中率',
                unit: 'percent',
                color: '#4f72d8',
            },
            {
                key: 'byte-cache-status',
                label: '字节命中率',
                unit: 'percent',
                color: '#4f72d8',
            },
            {
                key: 'status-4xx',
                label: '4xx状态码',
                unit: 'count',
                color: '#4f72d8',
            },
            {
                key: 'status-5xx',
                label: '5xx状态码',
                unit: 'count',
                color: '#4f72d8',
            },
        ],
    },
    {
        key: 'origin',
        label: '回源监控',
        metrics: [
            {
                key: 'backend-bandwidth',
                label: '回源带宽',
                unit: 'bits/s',
                color: '#4f72d8',
            },
            {
                key: 'backend-traffic',
                label: '回源流量',
                unit: 'bytes',
                color: '#4f72d8',
            },
            {
                key: 'backend-resp-time',
                label: '回源耗时',
                unit: 'seconds',
                color: '#4f72d8',
            },
        ],
    },
] as const;

type MetricGroup = (typeof metricGroups)[number];
type MetricDef = MetricGroup['metrics'][number];

// ── 数据分析：Tab 定义 ────────────────────────────────
// ── 通用状态 ──────────────────────────────────────────
const errorMessage = ref('');

// ── 实时监控状态 ──────────────────────────────────────
const activeGroup = ref<'basic' | 'quality' | 'origin'>('basic');
const activeGroupDef = computed(
    () =>
        metricGroups.find((g) => g.key === activeGroup.value) ??
        metricGroups[0],
);
const metricPoints = ref<Record<string, [number, number][]>>({});
const metricSeries = ref<Record<string, MetricSeries[]>>({});
const metricLoading = ref<Record<string, boolean>>({});
const metricError = ref<Record<string, string>>({});
const canvasRefs = ref<Record<string, HTMLCanvasElement | null>>({});
const chartInstances: Record<string, unknown> = {};
const autoRefresh = ref('off');
const rtRangePreset = ref('60');
let countdownTimer: ReturnType<typeof setInterval> | null = null;
const countdown = ref(0);
const rtFilters = reactive({
    start: defaultStart(),
    end: defaultEnd(),
    host: '',
    server_port: '',
});

// ── 数据分析状态 ──────────────────────────────────────
const activeTopTab = ref('top-domain');
const activeTopTabDef = computed(
    () => topTabs.find((t) => t.key === activeTopTab.value) ?? topTabs[0],
);
const topLoading = ref(false);
const topRows = ref<CdnflyRecord[]>([]);
const topRecentTime = ref('10m'); // '10m' | '30m' | '60m' | 'custom'
const topFilters = reactive({
    start: defaultStart(),
    end: defaultEnd(),
    domain: '',
    server_port: '',
});
const topLoadedFilters = ref<Record<string, string>>({});
const topSearchType = ref<'domain' | 'server_port'>('domain');
let topRequestVersion = 0;
// 排序
const sortKey = ref('');
const sortDir = ref<'asc' | 'desc'>('desc');

// ── logs / usage 状态 ─────────────────────────────────
const loading = ref(false);
const rows = ref<CdnflyRecord[]>([]);
const total = ref(0);
const page = ref(1);
const rawPayload = ref('');
const otherFilters = reactive({
    type: 'traffic',
    start: defaultStart(),
    end: defaultEnd(),
    host: '',
    server_port: '',
    addr: '',
    req_uri: '',
    status: '',
    cache_status: 'all',
    per_page: '20',
});

// ── 访问日志专属状态 ──────────────────────────────────
const logsTab = ref<'query' | 'jobs'>('query');
const initialLogIp =
    new URLSearchParams(usePage().url.split('?')[1] ?? '').get('addr') ?? '';
const showAdvanced = ref(initialLogIp !== '');
const logsFilters = reactive({
    host: '',
    start: defaultStart(),
    end: defaultEnd(),
    addr: initialLogIp,
    req_uri_type: 'exact', // 'exact' | 'prefix'
    req_uri: '',
    method: '',
    status: '',
    cache_status: 'all', // 'all' | 'HIT' | 'MISS'
    server_port: '',
    tls_fp: '',
    referer: '',
    country: '',
    province: '',
    isp: '',
    node_id: '',
});
const logsLoading = ref(false);
const logsRows = ref<CdnflyRecord[]>([]);
const logsTotal = ref(0);
const logsPage = ref(1);
// 申请记录
const jobsLoading = ref(false);
const jobsRows = ref<CdnflyRecord[]>([]);
const jobsTotal = ref(0);
const jobsPage = ref(1);
// 申请下载对话框状态
const applyDialogOpen = ref(false);
const applyStart = ref(defaultStart());
const applyEnd = ref(defaultEnd());
const applyDomain = ref('');
const applyLoading = ref(false);
const applyError = ref('');

// ── 计算属性 ──────────────────────────────────────────
const title = computed(() => {
    if (props.view === 'top') {
        return '数据分析';
    }

    if (props.view === 'logs') {
        return '访问日志';
    }

    if (props.view === 'usage') {
        return '用量查询';
    }

    return '实时监控';
});
const description = computed(() => {
    if (props.view === 'top') {
        return '网站资源排行，分析域名、URL、IP、地区、运营商、来源分布。';
    }

    if (props.view === 'logs') {
        return '实时访问日志查询，支持域名、IP、URI、状态码过滤。';
    }

    if (props.view === 'usage') {
        return '带宽和流量用量查询。';
    }

    return '网站实时曲线监控，支持基础数据、质量监控、回源监控。';
});
const icon = computed(() =>
    props.view === 'logs'
        ? FileText
        : props.view === 'top'
          ? BarChart3
          : Activity,
);
const hasPreviousPage = computed(() => page.value > 1);
const hasNextPage = computed(
    () => page.value * Number(otherFilters.per_page) < total.value,
);
const groupLoading = computed(() =>
    activeGroupDef.value.metrics.some((m) => metricLoading.value[m.key]),
);

// 排序后的行
const sortedTopRows = computed(() => {
    if (!sortKey.value) {
        return topRows.value;
    }

    return [...topRows.value].sort((a, b) => {
        const av = numVal(a[sortKey.value]);
        const bv = numVal(b[sortKey.value]);

        return sortDir.value === 'desc' ? bv - av : av - bv;
    });
});

// ── 生命周期 ──────────────────────────────────────────
onMounted(() => {
    if (props.view === 'realtime') {
        watchThemeForCharts();
        void loadGroup();

        return;
    }

    if (props.view === 'top') {
        void loadTop();
    } else if (props.view === 'logs') {
        void loadLogs(1);
    } else {
        resetOtherType();
        void loadOtherData(1);
    }
});

onUnmounted(() => {
    stopAutoRefresh();
    destroyAllCharts();
    themeObserver?.disconnect();
    themeObserver = null;
});

watch(activeGroup, () => {
    destroyAllCharts();
    void loadGroup();
});
watch(activeTopTab, () => {
    sortKey.value = '';
    sortDir.value = 'desc';
    void loadTop();
});
watch(autoRefresh, (val) => {
    stopAutoRefresh();

    if (val === 'off') {
        return;
    }

    startAutoRefresh(val === '30s' ? 30 : 60);
});

// ── 实时监控 ──────────────────────────────────────────
function startAutoRefresh(seconds: number): void {
    countdown.value = seconds;
    countdownTimer = setInterval(() => {
        countdown.value -= 1;

        if (countdown.value <= 0) {
            countdown.value = seconds;
            void loadGroup();
        }
    }, 1000);
}
function stopAutoRefresh(): void {
    if (countdownTimer) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }

    countdown.value = 0;
}
function setRtTimeRange(minutes: number): void {
    rtRangePreset.value = String(minutes);
    const now = new Date();
    rtFilters.end = formatInputDate(now);
    rtFilters.start = formatInputDate(
        new Date(now.getTime() - minutes * 60 * 1000),
    );
    void loadGroup();
}

function useCustomRtRange(): void {
    rtRangePreset.value = 'custom';
}
async function loadGroup(): Promise<void> {
    await Promise.all(
        (activeGroupDef.value.metrics as readonly MetricDef[]).map((m) =>
            loadMetric(m),
        ),
    );
}
async function loadMetric(m: MetricDef): Promise<void> {
    metricLoading.value[m.key] = true;
    metricError.value[m.key] = '';

    try {
        const params: Record<string, string | number> = {
            type: m.key,
            start: rtFilters.start,
            end: rtFilters.end,
        };

        if (rtFilters.host.trim()) {
            params.domain = rtFilters.host.trim();
        }

        if (rtFilters.server_port.trim()) {
            params.server_port = rtFilters.server_port.trim();
        }

        const result = await getUserSiteRealtime(params);
        const series = extractMetricSeries(result, m);
        const points = series.flatMap((item) => item.points);
        metricSeries.value[m.key] = series;
        metricPoints.value[m.key] = points;
        await nextTick();
        await renderChart(m, series);
    } catch (err) {
        metricError.value[m.key] = getErrorMessage(err);
    } finally {
        metricLoading.value[m.key] = false;
    }
}

// ── 数据分析 ──────────────────────────────────────────
async function loadTop(): Promise<void> {
    const version = ++topRequestVersion;
    const type = activeTopTab.value;
    const recent = topRecentTime.value;
    const range =
        recent === 'custom'
            ? { start: topFilters.start, end: topFilters.end }
            : recentTopRange(recent);
    const context = {
        ...range,
        domain: topFilters.domain.trim(),
        server_port: topFilters.server_port.trim(),
    };
    topLoading.value = true;
    errorMessage.value = '';

    try {
        const duration =
            Date.parse(range.end.replace(' ', 'T')) -
            Date.parse(range.start.replace(' ', 'T'));

        if (!Number.isFinite(duration) || duration <= 0 || duration > 3600000) {
            throw new Error('请选择有效的时间范围，时间跨度不能超过1小时');
        }

        const params: Record<string, string | number> = { type };

        if (recent === 'custom') {
            Object.assign(params, range);
        } else {
            params.recent_time = recent;
        }

        if (context.domain) {
            params.domain = context.domain;
        }

        if (context.server_port) {
            params.server_port = context.server_port;
        }

        let nextRows: CdnflyRecord[] = [];

        try {
            const result = await getUserSiteTop(params);
            nextRows = siteRankingRows(result, type);
        } catch (error) {
            if (recent === 'custom') {
                throw error;
            }
        }

        if (version !== topRequestVersion) {
            return;
        }

        if (nextRows.length === 0 && recent !== 'custom') {
            const fallback = { ...params };
            delete fallback.recent_time;
            const result = await getUserSiteTop({ ...fallback, ...range });
            nextRows = siteRankingRows(result, type);
        }

        if (version !== topRequestVersion) {
            return;
        }

        topRows.value = nextRows;
        topLoadedFilters.value = context;
    } catch (err) {
        if (version !== topRequestVersion) {
            return;
        }

        topRows.value = [];
        errorMessage.value = getErrorMessage(err);
    } finally {
        if (version === topRequestVersion) {
            topLoading.value = false;
        }
    }
}

function recentTopRange(value: string): { start: string; end: string } {
    const minutes = value === '10m' ? 10 : value === '30m' ? 30 : 60;
    const end = new Date();
    const start = new Date(end.getTime() - minutes * 60 * 1000);

    return {
        start: formatInputDate(start),
        end: formatInputDate(end),
    };
}

function setTopRecentTime(t: string): void {
    topRecentTime.value = t;
    void loadTop();
}

function toggleSort(key: string): void {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc';
    } else {
        sortKey.value = key;
        sortDir.value = 'desc';
    }
}

function goToLogs(row: CdnflyRecord): void {
    router.visit(
        rankingLogUrl(
            props.scope ?? 'user',
            activeTopTab.value,
            rowDimension(row, activeTopTab.value),
            topLoadedFilters.value,
        ),
    );
}

function cellValue(row: CdnflyRecord, col: TopTab['cols'][number]): string {
    if (col.type === 'bytes' || col.type === 'count') {
        return formatRankingMetric(row[col.key], col.type);
    }

    // text: 尝试多个可能的字段名
    const v = row[col.key] ?? row.name ?? row.key ?? row.value;

    return textValue(v) || '-';
}

function rowDimension(row: CdnflyRecord, tabKey: string): string {
    switch (tabKey) {
        case 'top-domain':
            return (
                textValue(row.domain) ||
                textValue(row.host) ||
                textValue(row.name) ||
                '-'
            );
        case 'top-url':
            return (
                textValue(row.url) ||
                textValue(row.uri) ||
                textValue(row.req_uri) ||
                textValue(row.key) ||
                '-'
            );
        case 'top-tls-fp':
            return (
                textValue(row.fp) ||
                textValue(row.tls_fp) ||
                textValue(row.key) ||
                textValue(row.name) ||
                '-'
            );
        case 'top-ip':
            return (
                textValue(row.ip) ||
                textValue(row.addr) ||
                textValue(row.key) ||
                '-'
            );
        case 'top-country':
            return (
                textValue(row.country) ||
                textValue(row.key) ||
                textValue(row.name) ||
                '-'
            );
        case 'top-province':
            return (
                textValue(row.province) ||
                textValue(row.key) ||
                textValue(row.name) ||
                '-'
            );
        case 'top-isp':
            return (
                textValue(row.isp) ||
                textValue(row.key) ||
                textValue(row.name) ||
                '-'
            );
        case 'top-referer':
            return textValue(row.referer) || textValue(row.key) || '-';
        default:
            return textValue(row.key) || textValue(row.name) || '-';
    }
}

// ── Chart.js ──────────────────────────────────────────

/**
 * Axis and gridline colours, taken from the active theme.
 *
 * These used to be hardcoded light-mode slate values (#f1f5f9 gridlines,
 * #94a3b8 ticks). #f1f5f9 is very nearly white, so in dark mode every gridline
 * — the 1.0 MB/s rule and the rest — was drawn brighter than the data it sat
 * behind, and the chart read as a white grid with a faint line on it.
 *
 * Reading --border and --muted-foreground keeps the chart consistent with every
 * other divider in the console and makes it follow the theme for free. Both are
 * plain hsl() strings, which canvas accepts directly.
 */
function chartColors(): { grid: string; tick: string } {
    if (typeof window === 'undefined') {
        return { grid: 'hsl(0 0% 92.8%)', tick: 'hsl(0 0% 45.1%)' };
    }

    const styles = getComputedStyle(document.documentElement);
    const read = (name: string, fallback: string): string => {
        const value = styles.getPropertyValue(name).trim();

        return value !== '' ? value : fallback;
    };

    return {
        grid: read('--border', 'hsl(0 0% 92.8%)'),
        tick: read('--muted-foreground', 'hsl(0 0% 45.1%)'),
    };
}

/**
 * A theme switch repaints the DOM but not a canvas, so charts already on screen
 * keep the palette they were drawn with. Redraw them from the points they are
 * already holding.
 */
let themeObserver: MutationObserver | null = null;

function watchThemeForCharts(): void {
    if (typeof window === 'undefined' || themeObserver) {
        return;
    }

    themeObserver = new MutationObserver(() => {
        for (const m of activeGroupDef.value.metrics as readonly MetricDef[]) {
            const series = metricSeries.value[m.key];

            if (series && series.length > 0) {
                void renderChart(m, series);
            }
        }
    });

    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
}

let chartJsPromise: Promise<void> | null = null;
async function ensureChartJs(): Promise<void> {
    if ((window as unknown as Record<string, unknown>)['Chart']) {
        return;
    }

    // The four metrics load together; share one script and Chart instance registry.
    chartJsPromise ??= loadScript(
        'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js',
    ).catch((error) => {
        chartJsPromise = null;

        throw error;
    });
    await chartJsPromise;
}

async function renderChart(
    m: MetricDef,
    series: MetricSeries[],
): Promise<void> {
    await ensureChartJs();
    const Chart = (window as unknown as Record<string, unknown>)['Chart'] as {
        new (canvas: HTMLCanvasElement, config: unknown): unknown;
        getChart: (
            canvas: HTMLCanvasElement,
        ) => { destroy(): void } | undefined;
    };
    const canvas = canvasRefs.value[m.key];

    if (!canvas) {
        return;
    }

    const existing = Chart.getChart(canvas);

    if (existing) {
        existing.destroy();
    }

    const timestamps = [
        ...new Set(series.flatMap((item) => item.points.map(([ts]) => ts))),
    ].sort((a, b) => a - b);
    const labels = timestamps.map((ts) => {
        const d = new Date(ts);

        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    });
    const theme = chartColors();
    chartInstances[m.key] = new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: series.map((item) => {
                const values = new Map(item.points);

                return {
                    label: item.label,
                    data: timestamps.map((timestamp) => {
                        const value = values.get(timestamp);

                        return value === undefined
                            ? null
                            : toChartValue(value, m.unit);
                    }),
                    borderColor: item.color,
                    backgroundColor: `${item.color}18`,
                    borderWidth: 2,
                    pointRadius: timestamps.length > 60 ? 0 : 2.5,
                    pointHoverRadius: 5,
                    fill: series.length === 1,
                    tension: 0.25,
                    spanGaps: true,
                };
            }),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: series.length > 1 || m.key.startsWith('status-'),
                    position: 'top',
                    align: m.key.startsWith('status-') ? 'end' : 'center',
                    labels: {
                        color: theme.tick,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 7,
                        boxHeight: 7,
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (ctx: {
                            parsed: { y: number };
                            dataset: { label?: string };
                        }) => {
                            const scale =
                                m.unit === 'bits/s'
                                    ? 1000 / 8
                                    : m.unit === 'bytes'
                                      ? 1048576
                                      : m.unit === 'seconds'
                                        ? 1000
                                        : 1;
                            const raw = ctx.parsed.y * scale;

                            return ` ${ctx.dataset.label ?? m.label}: ${formatValue(raw, m.unit)}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        maxTicksLimit: 8,
                        maxRotation: 0,
                        color: theme.tick,
                        font: { size: 10 },
                    },
                    grid: { color: theme.grid },
                    border: { color: theme.grid },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: theme.tick,
                        font: { size: 10 },
                        callback: (val: number) =>
                            `${val.toFixed(val < 1 ? 2 : 1)} ${yAxisLabel(m.unit)}`,
                    },
                    grid: { color: theme.grid },
                    border: { color: theme.grid },
                },
            },
        },
    });
}
function destroyAllCharts(): void {
    for (const key of Object.keys(chartInstances)) {
        (chartInstances[key] as { destroy(): void }).destroy();
        delete chartInstances[key];
    }
}
function setCanvasRef(key: string, el: HTMLCanvasElement | null): void {
    canvasRefs.value[key] = el;
}

// ── logs / usage ──────────────────────────────────────
async function loadOtherData(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params = buildOtherParams(targetPage);
        let result;

        if (props.view === 'usage') {
            result = await getUserUsage(params);
        } else {
            result = await listUserAccessLogs(params);
        }

        const nextRows = extractCdnflyRows(result);
        rows.value =
            props.view === 'usage'
                ? nextRows.slice(
                      (targetPage - 1) * Number(otherFilters.per_page),
                      targetPage * Number(otherFilters.per_page),
                  )
                : nextRows;
        total.value = extractCdnflyTotal(result, nextRows.length);
        rawPayload.value = jsonText(result, '{}');
        page.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}
function resetOtherType(): void {
    if (props.view === 'usage') {
        otherFilters.type = 'traffic';

        return;
    }
}
function buildOtherParams(targetPage: number): Record<string, string | number> {
    const params: Record<string, string | number> = {
        page: targetPage,
        limit: Number(otherFilters.per_page),
    };

    if (props.view === 'usage') {
        params.type = otherFilters.type;
        params.start = otherFilters.start.slice(0, 10);
        params.end = inclusiveUsageEnd(otherFilters.end);
        delete params.page;
        delete params.limit;

        if (otherFilters.host.trim()) {
            params.res = otherFilters.host.trim();
        }
    } else {
        params.start = otherFilters.start;
        params.end = otherFilters.end;

        if (otherFilters.host.trim()) {
            params.host = otherFilters.host.trim();
        }

        if (otherFilters.server_port.trim()) {
            params.server_port = otherFilters.server_port.trim();
        }

        if (otherFilters.addr.trim()) {
            params.addr = otherFilters.addr.trim();
        }

        if (otherFilters.req_uri.trim()) {
            params.req_uri = otherFilters.req_uri.trim();
        }

        if (otherFilters.status.trim()) {
            params.status = otherFilters.status.trim();
        }

        if (otherFilters.cache_status !== 'all') {
            params.cache_status = otherFilters.cache_status;
        }
    }

    return params;
}
function prevPage(): void {
    if (hasPreviousPage.value) {
        void loadOtherData(page.value - 1);
    }
}
function nextPage(): void {
    if (hasNextPage.value) {
        void loadOtherData(page.value + 1);
    }
}

function rowPrimary(row: CdnflyRecord): string {
    return (
        textValue(row.date) ||
        textValue(row.time) ||
        textValue(row.key) ||
        textValue(row.name) ||
        textValue(row.host) ||
        textValue(row.domain) ||
        textValue(row.addr) ||
        textValue(row.ip) ||
        '-'
    );
}
function rowSecondary(row: CdnflyRecord): string {
    if (props.view === 'usage') {
        return otherFilters.type === 'traffic' ? '流量' : '带宽';
    }

    return (
        textValue(row.req_uri) ||
        textValue(row.referer) ||
        textValue(row.type) ||
        textValue(row.method) ||
        '-'
    );
}
function rowMetric(row: CdnflyRecord): string {
    if (props.view === 'usage') {
        return `${formatBytes(numVal(row.value))}${otherFilters.type === 'bandwidth' ? '/s' : ''}`;
    }

    return (
        textValue(row.value) ||
        textValue(row.count) ||
        textValue(row.traffic) ||
        textValue(row.bandwidth) ||
        textValue(row.status) ||
        '-'
    );
}

// ── 访问日志函数 ──────────────────────────────────────
async function loadLogs(targetPage = 1): Promise<void> {
    logsLoading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: 20,
            start: logsFilters.start,
            end: logsFilters.end,
        };

        if (logsFilters.host.trim()) {
            params.host = logsFilters.host.trim();
        }

        if (logsFilters.addr.trim()) {
            params.addr = logsFilters.addr.trim();
        }

        if (logsFilters.req_uri.trim()) {
            params.req_uri = logsFilters.req_uri.trim();
            params.uri_match_type = logsFilters.req_uri_type;
        }

        if (logsFilters.method.trim()) {
            params.method = logsFilters.method.trim();
        }

        if (logsFilters.status.trim()) {
            params.status = logsFilters.status.trim();
        }

        if (logsFilters.cache_status && logsFilters.cache_status !== 'all') {
            params.cache_status = logsFilters.cache_status;
        }

        if (logsFilters.server_port.trim()) {
            params.server_port = logsFilters.server_port.trim();
        }

        if (logsFilters.tls_fp.trim()) {
            params.tls_fp = logsFilters.tls_fp.trim();
        }

        if (logsFilters.referer.trim()) {
            params.referer = logsFilters.referer.trim();
        }

        if (logsFilters.country.trim()) {
            params.country = logsFilters.country.trim();
        }

        if (logsFilters.province.trim()) {
            params.province = logsFilters.province.trim();
        }

        if (logsFilters.isp.trim()) {
            params.isp = logsFilters.isp.trim();
        }

        if (logsFilters.node_id.trim()) {
            params.node_id = logsFilters.node_id.trim();
        }

        const result = await listUserAccessLogs(params);
        logsRows.value = extractCdnflyRows(result);
        logsTotal.value = extractCdnflyTotal(result, logsRows.value.length);
        logsPage.value = targetPage;
    } catch (err) {
        errorMessage.value = getErrorMessage(err);
    } finally {
        logsLoading.value = false;
    }
}

async function loadJobs(targetPage = 1): Promise<void> {
    jobsLoading.value = true;

    try {
        const result = await listAccessLogJobs({ page: targetPage, limit: 20 });
        jobsRows.value = extractCdnflyRows(result);
        jobsTotal.value = extractCdnflyTotal(result, jobsRows.value.length);
        jobsPage.value = targetPage;
    } catch (err) {
        errorMessage.value = getErrorMessage(err);
    } finally {
        jobsLoading.value = false;
    }
}

async function submitApplyJob(): Promise<void> {
    applyLoading.value = true;
    applyError.value = '';

    try {
        const start = applyStart.value || '';
        const end = (applyEnd.value || '').replace(' 00:00:00', ' 23:59:59');
        await createAccessLogJob(
            start,
            end,
            applyDomain.value.trim() || undefined,
        );
        applyDialogOpen.value = false;
        logsTab.value = 'jobs';
        await loadJobs(1);
    } catch (err) {
        applyError.value = getErrorMessage(err);
    } finally {
        applyLoading.value = false;
    }
}

function jobDownloadUrl(row: CdnflyRecord): string {
    // baseUrl comes from page props or window.location.origin
    const base = typeof window !== 'undefined' ? window.location.origin : '';
    const id = row.id ?? row.job_id ?? row.task_id ?? '';

    return accessLogDownloadUrl(String(id), base);
}

function jobStatusText(row: CdnflyRecord): string {
    const s = String(row.status ?? row.state ?? '').toLowerCase();

    if (
        s === 'done' ||
        s === 'completed' ||
        s === 'success' ||
        s === 'finish'
    ) {
        return 'done';
    }

    if (s === 'failed' || s === 'error') {
        return 'error';
    }

    if (s === 'running' || s === 'processing') {
        return 'running';
    }

    return s || 'pending';
}

function jobProgress(row: CdnflyRecord): number {
    const p = row.progress ?? row.percent ?? row.rate;

    if (typeof p === 'number') {
        return Math.round(p * (p <= 1 ? 100 : 1));
    }

    if (typeof p === 'string') {
        return Math.round(parseFloat(p) * (parseFloat(p) <= 1 ? 100 : 1));
    }

    return 0;
}

function switchLogsTab(tab: 'query' | 'jobs'): void {
    if (props.scope === 'admin' && tab === 'jobs') {
        return;
    }

    logsTab.value = tab;

    if (tab === 'jobs') {
        void loadJobs(1);
    }
}

// ── 工具函数 ──────────────────────────────────────────
function formatValue(value: number, unit: string): string {
    if (unit === 'bits/s') {
        const bits = value * 8;

        if (bits >= 1000000000) {
            return `${(bits / 1000000000).toFixed(2)} Gbps`;
        }

        if (bits >= 1000000) {
            return `${(bits / 1000000).toFixed(2)} Mbps`;
        }

        if (bits >= 1000) {
            return `${(bits / 1000).toFixed(2)} Kbps`;
        }

        return `${bits.toFixed(0)} bps`;
    }

    if (unit === 'bytes/s' || unit === 'bytes') {
        if (value >= 1073741824) {
            return `${(value / 1073741824).toFixed(2)} GB${unit === 'bytes/s' ? '/s' : ''}`;
        }

        if (value >= 1048576) {
            return `${(value / 1048576).toFixed(2)} MB${unit === 'bytes/s' ? '/s' : ''}`;
        }

        if (value >= 1024) {
            return `${(value / 1024).toFixed(2)} KB${unit === 'bytes/s' ? '/s' : ''}`;
        }

        return `${value.toFixed(0)} B${unit === 'bytes/s' ? '/s' : ''}`;
    }

    if (unit === 'percent') {
        return `${value.toFixed(1)}%`;
    }

    if (unit === 'ms') {
        return `${value.toFixed(0)} ms`;
    }

    if (unit === 'seconds') {
        return `${(value / 1000).toFixed(2)} 秒`;
    }

    if (value >= 100000000) {
        return `${(value / 100000000).toFixed(2)} 亿`;
    }

    if (value >= 10000) {
        return `${(value / 10000).toFixed(1)} 万`;
    }

    return `${value.toFixed(0)}`;
}
function formatBytes(value: number): string {
    if (!value) {
        return '-';
    }

    if (value >= 1073741824) {
        return `${(value / 1073741824).toFixed(2)} GB`;
    }

    if (value >= 1048576) {
        return `${(value / 1048576).toFixed(2)} MB`;
    }

    if (value >= 1024) {
        return `${(value / 1024).toFixed(2)} KB`;
    }

    return `${value.toFixed(0)} B`;
}
function numVal(v: unknown): number {
    if (typeof v === 'number') {
        return v;
    }

    if (typeof v === 'string') {
        return parseFloat(v) || 0;
    }

    return 0;
}
function yAxisLabel(unit: string): string {
    if (unit === 'bits/s') {
        return 'Kbps';
    }

    if (unit === 'bytes/s') {
        return 'MB/s';
    }

    if (unit === 'bytes') {
        return 'MB';
    }

    if (unit === 'percent') {
        return '%';
    }

    if (unit === 'ms') {
        return 'ms';
    }

    if (unit === 'seconds') {
        return '秒';
    }

    return '';
}
function toChartValue(value: number, unit: string): number {
    if (unit === 'bits/s') {
        return (value * 8) / 1000;
    }

    if (unit === 'bytes/s' || unit === 'bytes') {
        return value / 1048576;
    }

    if (unit === 'seconds') {
        return value / 1000;
    }

    return value;
}
function rowDataValue(row: CdnflyRecord, key: string): unknown {
    return row.data && typeof row.data === 'object' && !Array.isArray(row.data)
        ? (row.data as CdnflyRecord)[key]
        : undefined;
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
function defaultStart(): string {
    return formatInputDate(new Date(Date.now() - 60 * 60 * 1000));
}
function defaultEnd(): string {
    return formatInputDate(new Date());
}
function formatInputDate(date: Date): string {
    const pad = (v: number) => String(v).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <ConsolePageHeader
            v-if="props.view !== 'realtime' && props.view !== 'top'"
            eyebrow="用户端 / 访问数据"
            :title="title"
            :description="description"
            :icon="icon"
            :show-api-badge="false"
        />

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <!-- ═══════════════════════════════════════════════
             实时监控
        ════════════════════════════════════════════════ -->
        <template v-if="props.view === 'realtime'">
            <Card class="gap-0 overflow-hidden">
                <!-- 分组 Tab -->
                <div
                    class="flex flex-wrap gap-x-6 border-b px-5 pt-3"
                    data-realtime-tabs
                >
                    <button
                        v-for="g in metricGroups"
                        :key="g.key"
                        type="button"
                        class="border-b-2 px-1 py-3 text-sm font-medium transition-colors"
                        :aria-pressed="activeGroup === g.key"
                        :class="
                            activeGroup === g.key
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="
                            activeGroup = g.key as
                                | 'basic'
                                | 'quality'
                                | 'origin'
                        "
                    >
                        {{ g.label }}
                    </button>
                </div>

                <!-- 控制栏 -->
                <div class="border-b px-4 py-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <Input
                            id="rt-domain-filter"
                            v-model="rtFilters.host"
                            class="h-9 w-full text-sm sm:w-64"
                            placeholder="输入域名，多个空格分隔"
                            @keydown.enter="loadGroup"
                        />
                        <Input
                            id="rt-port-filter"
                            v-model="rtFilters.server_port"
                            class="h-9 w-full text-sm sm:w-40"
                            placeholder="输入监听端口"
                            inputmode="numeric"
                            @keydown.enter="loadGroup"
                        />
                        <div
                            class="flex h-9 max-w-full overflow-x-auto rounded-md border"
                        >
                            <button
                                v-for="range in [
                                    { minutes: 60, label: '近1小时' },
                                    { minutes: 360, label: '近6小时' },
                                    { minutes: 720, label: '近12小时' },
                                ]"
                                :key="range.minutes"
                                type="button"
                                class="border-r px-4 text-sm transition-colors"
                                :class="
                                    rtRangePreset === String(range.minutes)
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-background text-foreground hover:bg-muted'
                                "
                                @click="setRtTimeRange(range.minutes)"
                            >
                                {{ range.label }}
                            </button>
                            <button
                                type="button"
                                class="px-4 text-sm transition-colors"
                                :class="
                                    rtRangePreset === 'custom'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-background text-foreground hover:bg-muted'
                                "
                                @click="useCustomRtRange"
                            >
                                自定义
                            </button>
                        </div>
                        <DateRangePicker
                            v-if="rtRangePreset === 'custom'"
                            :start="rtFilters.start"
                            :end="rtFilters.end"
                            @update:start="rtFilters.start = $event"
                            @update:end="rtFilters.end = $event"
                        />
                        <Button
                            class="h-9"
                            :disabled="groupLoading"
                            @click="loadGroup"
                        >
                            <Spinner
                                v-if="groupLoading"
                                data-icon="inline-start"
                            />
                            <Search v-else data-icon="inline-start" />
                            查询
                        </Button>
                        <div class="ml-auto flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">
                                自动刷新
                                <span
                                    v-if="countdown > 0"
                                    class="ml-1 text-primary tabular-nums"
                                    >{{ countdown }}s</span
                                >
                            </span>
                            <Select v-model="autoRefresh">
                                <SelectTrigger class="h-9 w-24 text-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="off"
                                            >关闭</SelectItem
                                        >
                                        <SelectItem value="30s"
                                            >30 秒</SelectItem
                                        >
                                        <SelectItem value="60s"
                                            >60 秒</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                <!-- 指标图表网格 -->
                <div class="grid gap-0 lg:grid-cols-2">
                    <Card
                        v-for="m in activeGroupDef.metrics"
                        :key="m.key"
                        :data-metric="m.key"
                        class="min-w-0 gap-0 rounded-none border-0 shadow-none"
                    >
                        <CardHeader
                            class="flex flex-row items-center justify-between px-5 pt-5 pb-2"
                        >
                            <CardTitle class="text-sm font-medium">
                                {{ m.label }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="px-5 pb-6">
                            <div class="relative h-64 xl:h-72">
                                <div
                                    v-if="metricLoading[m.key]"
                                    class="absolute inset-0 flex items-center justify-center"
                                >
                                    <Spinner class="h-6 w-6" />
                                </div>
                                <div
                                    v-else-if="metricError[m.key]"
                                    class="absolute inset-0 flex items-center justify-center text-xs text-destructive"
                                >
                                    {{ metricError[m.key] }}
                                </div>
                                <div
                                    v-else-if="
                                        !(metricPoints[m.key] ?? []).length
                                    "
                                    class="absolute inset-0 flex items-center justify-center text-xs text-muted-foreground"
                                >
                                    暂无数据
                                </div>
                                <canvas
                                    :ref="
                                        (el) =>
                                            setCanvasRef(
                                                m.key,
                                                el as HTMLCanvasElement | null,
                                            )
                                    "
                                    class="h-full w-full"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </Card>
        </template>

        <!-- ═══════════════════════════════════════════════
             数据分析
        ════════════════════════════════════════════════ -->
        <template v-else-if="props.view === 'top'">
            <Card class="analytics-ranking-panel gap-0 overflow-hidden">
                <!-- Tab 栏 -->
                <div
                    class="flex flex-wrap gap-1 border-b px-4 pt-4"
                    role="tablist"
                    aria-label="Ranking dimensions"
                >
                    <button
                        v-for="tab in topTabs"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="activeTopTab === tab.key"
                        class="-mb-px rounded-t-sm border px-4 py-2 text-sm font-normal transition-colors"
                        :class="
                            activeTopTab === tab.key
                                ? 'border-border border-b-card bg-card text-primary'
                                : 'border-border bg-muted/30 text-foreground hover:text-primary'
                        "
                        @click="activeTopTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- 控制栏 -->
                <div
                    class="flex flex-wrap items-center gap-2 border-b px-4 py-4"
                >
                    <!-- 时间快捷 -->
                    <div class="flex overflow-hidden rounded-sm border">
                        <button
                            v-for="t in [
                                { v: '10m', label: '10分钟实时' },
                                { v: '30m', label: '近30分钟' },
                                { v: '60m', label: '近1小时' },
                                { v: 'custom', label: '自定义' },
                            ]"
                            :key="t.v"
                            type="button"
                            class="border-r px-4 py-1.5 text-sm font-normal transition-colors last:border-r-0"
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

                    <!-- 自定义时间（仅 custom 模式显示） -->
                    <template v-if="topRecentTime === 'custom'">
                        <DateRangePicker
                            :start="topFilters.start"
                            :end="topFilters.end"
                            @update:start="topFilters.start = $event"
                            @update:end="topFilters.end = $event"
                        />
                    </template>

                    <!-- 域名筛选 -->
                    <div class="flex min-w-0 items-center">
                        <SelectField
                            v-model="topSearchType"
                            aria-label="排行筛选类型"
                            class="h-8 rounded-l-sm border border-r-0 bg-card px-3 text-sm"
                        >
                            <SelectOption value="domain">域名</SelectOption>
                            <SelectOption value="server_port"
                                >监听端口</SelectOption
                            >
                        </SelectField>
                        <Input
                            v-model="topFilters[topSearchType]"
                            class="h-8 w-60 min-w-0 rounded-l-none text-sm"
                            :aria-label="
                                topSearchType === 'domain'
                                    ? '域名筛选'
                                    : '监听端口筛选'
                            "
                            :placeholder="
                                topSearchType === 'domain'
                                    ? '输入域名，多个空格分隔'
                                    : '输入监听端口'
                            "
                            @keydown.enter="loadTop"
                        />
                    </div>

                    <!-- 刷新 -->
                    <Button class="h-8" :disabled="topLoading" @click="loadTop">
                        <Spinner v-if="topLoading" data-icon="inline-start" />
                        <RefreshCw v-else data-icon="inline-start" />
                        刷新
                    </Button>
                    <button
                        v-if="topFilters.domain || topFilters.server_port"
                        type="button"
                        class="text-sm text-primary hover:underline"
                        @click="
                            topFilters.domain = '';
                            topFilters.server_port = '';
                            loadTop();
                        "
                    >
                        清除
                    </button>
                    <span
                        v-if="topFilters.domain"
                        class="rounded border px-2 py-1 text-xs text-muted-foreground"
                        >域名：{{ topFilters.domain }}</span
                    >
                    <span
                        v-if="topFilters.server_port"
                        class="rounded border px-2 py-1 text-xs text-muted-foreground"
                        >监听端口：{{ topFilters.server_port }}</span
                    >
                </div>

                <!-- 表格 -->
                <Card
                    class="analytics-ranking-table-card gap-0 rounded-none border-0 shadow-none"
                >
                    <CardContent class="p-4 pt-0">
                        <div class="w-full overflow-x-auto">
                            <table
                                class="analytics-ranking-table w-full min-w-[1000px]"
                            >
                                <thead>
                                    <tr>
                                        <th
                                            class="w-14 px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            排行
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            {{ activeTopTabDef.cols[0].label }}
                                        </th>
                                        <th
                                            v-for="col in activeTopTabDef.cols.slice(
                                                1,
                                            )"
                                            :key="col.key"
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                            :class="
                                                col.type !== 'action'
                                                    ? 'cursor-pointer select-none hover:text-foreground'
                                                    : ''
                                            "
                                            @click="
                                                col.type !== 'action' &&
                                                toggleSort(col.key)
                                            "
                                        >
                                            <span
                                                class="inline-flex items-center gap-1"
                                            >
                                                {{ col.label }}
                                                <template
                                                    v-if="col.type !== 'action'"
                                                >
                                                    <span
                                                        v-if="
                                                            sortKey === col.key
                                                        "
                                                        class="text-primary"
                                                    >
                                                        {{
                                                            sortDir === 'desc'
                                                                ? '↓'
                                                                : '↑'
                                                        }}
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="text-muted-foreground/40"
                                                        >↕</span
                                                    >
                                                </template>
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="topLoading">
                                        <td
                                            :colspan="
                                                activeTopTabDef.cols.length + 1
                                            "
                                            class="px-4 py-12 text-center"
                                        >
                                            <Spinner class="mx-auto" />
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(row, index) in topLoading
                                            ? []
                                            : sortedTopRows"
                                        :key="index"
                                        class="border-b transition-colors last:border-0 hover:bg-muted/20"
                                    >
                                        <!-- 排行 -->
                                        <td
                                            class="px-4 py-0 text-muted-foreground"
                                        >
                                            <span>{{ index + 1 }}</span>
                                        </td>
                                        <!-- 维度列（含进度条） -->
                                        <td class="max-w-xs px-4 py-0">
                                            <div
                                                class="truncate"
                                                :title="
                                                    rowDimension(
                                                        row,
                                                        activeTopTab,
                                                    )
                                                "
                                            >
                                                {{
                                                    rowDimension(
                                                        row,
                                                        activeTopTab,
                                                    )
                                                }}
                                            </div>
                                        </td>
                                        <!-- 其他列 -->
                                        <template
                                            v-for="col in activeTopTabDef.cols.slice(
                                                1,
                                            )"
                                            :key="col.key"
                                        >
                                            <td
                                                v-if="col.type === 'action'"
                                                class="px-4 py-0"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center text-sm text-primary hover:underline"
                                                    @click="goToLogs(row)"
                                                >
                                                    查看日志
                                                </button>
                                            </td>
                                            <td
                                                v-else
                                                class="px-4 py-0 text-muted-foreground tabular-nums"
                                            >
                                                {{ cellValue(row, col) }}
                                            </td>
                                        </template>
                                    </tr>
                                    <tr
                                        v-if="
                                            !topLoading &&
                                            sortedTopRows.length === 0
                                        "
                                    >
                                        <td
                                            :colspan="
                                                activeTopTabDef.cols.length + 1
                                            "
                                            class="px-4 py-16 text-center text-sm text-muted-foreground"
                                        >
                                            暂无数据
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </Card>
        </template>

        <!-- ═══════════════════════════════════════════════
             访问日志
        ════════════════════════════════════════════════ -->
        <template v-else-if="props.view === 'logs'">
            <!-- 二级 Tab -->
            <div class="flex w-fit gap-1 rounded-lg border bg-muted/40 p-1">
                <button
                    type="button"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        logsTab === 'query'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="switchLogsTab('query')"
                >
                    日志查询
                </button>
                <button
                    v-if="scope !== 'admin'"
                    type="button"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        logsTab === 'jobs'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="switchLogsTab('jobs')"
                >
                    申请记录
                </button>
            </div>

            <!-- ── 日志查询 Tab ── -->
            <template v-if="logsTab === 'query'">
                <Card>
                    <CardHeader class="pb-3">
                        <!-- 顶部工具栏 -->
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex min-w-0 flex-1 items-center gap-0">
                                <span
                                    class="inline-flex h-9 shrink-0 items-center rounded-l-md border border-r-0 bg-muted px-3 text-sm whitespace-nowrap text-muted-foreground"
                                >
                                    域名
                                </span>
                                <Input
                                    v-model="logsFilters.host"
                                    placeholder="输入域名"
                                    class="h-9 rounded-l-none"
                                    @keydown.enter="loadLogs(1)"
                                />
                            </div>
                            <Button
                                size="sm"
                                :disabled="logsLoading"
                                @click="loadLogs(1)"
                            >
                                <Spinner
                                    v-if="logsLoading"
                                    data-icon="inline-start"
                                />
                                <Search v-else data-icon="inline-start" />
                                查询
                            </Button>
                            <Button
                                v-if="scope !== 'admin'"
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="applyDialogOpen = true"
                            >
                                申请下载
                            </Button>
                            <button
                                type="button"
                                class="flex h-9 items-center gap-1 rounded-md px-3 text-sm font-medium transition-colors"
                                :class="
                                    showAdvanced
                                        ? 'text-primary underline underline-offset-2'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="showAdvanced = !showAdvanced"
                            >
                                高级搜索
                            </button>
                        </div>

                        <!-- 高级搜索面板 -->
                        <div
                            v-if="showAdvanced"
                            class="mt-4 rounded-lg border bg-muted/20 p-4"
                        >
                            <div
                                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                            >
                                <!-- 时间范围 -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >时间范围</Label
                                    >
                                    <DateRangePicker
                                        :start="logsFilters.start"
                                        :end="logsFilters.end"
                                        @update:start="
                                            logsFilters.start = $event
                                        "
                                        @update:end="logsFilters.end = $event"
                                    />
                                </div>

                                <!-- 域名 -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >域名</Label
                                    >
                                    <Input
                                        v-model="logsFilters.host"
                                        placeholder="多个域名空格分隔"
                                        class="h-8 text-xs"
                                    />
                                </div>

                                <!-- 客户端IP -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >客户端IP</Label
                                    >
                                    <Input
                                        v-model="logsFilters.addr"
                                        placeholder="请输入IP地址"
                                        class="h-8 text-xs"
                                    />
                                </div>

                                <!-- 请求地址（类型+URI） -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >请求地址</Label
                                    >
                                    <div class="flex min-w-0">
                                        <Select
                                            v-model="logsFilters.req_uri_type"
                                        >
                                            <SelectTrigger
                                                size="sm"
                                                class="w-[72px] shrink-0 rounded-r-none border-r-0 text-xs"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="exact"
                                                        >精确</SelectItem
                                                    >
                                                    <SelectItem value="prefix"
                                                        >前缀</SelectItem
                                                    >
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <Input
                                            v-model="logsFilters.req_uri"
                                            placeholder="不包含域名部分的URI"
                                            class="h-8 min-w-0 flex-1 rounded-l-none text-xs"
                                        />
                                    </div>
                                </div>

                                <!-- 请求方法 -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >请求方法</Label
                                    >
                                    <Input
                                        v-model="logsFilters.method"
                                        placeholder="请输入请求方法，如GET"
                                        class="h-8 text-xs"
                                    />
                                </div>

                                <!-- 状态码 -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >状态码</Label
                                    >
                                    <Input
                                        v-model="logsFilters.status"
                                        placeholder="请输入状态码"
                                        class="h-8 text-xs"
                                    />
                                </div>

                                <!-- 缓存状态 -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >缓存状态</Label
                                    >
                                    <Select v-model="logsFilters.cache_status">
                                        <SelectTrigger class="h-8 text-xs"
                                            ><SelectValue placeholder="不限"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="all"
                                                    >不限</SelectItem
                                                >
                                                <SelectItem value="HIT"
                                                    >命中</SelectItem
                                                >
                                                <SelectItem value="MISS"
                                                    >未命中</SelectItem
                                                >
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- 访问端口 -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >访问端口</Label
                                    >
                                    <Input
                                        v-model="logsFilters.server_port"
                                        placeholder="请输入访问端口"
                                        class="h-8 text-xs"
                                        inputmode="numeric"
                                    />
                                </div>

                                <!-- TLS指纹 -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >TLS指纹</Label
                                    >
                                    <Input
                                        v-model="logsFilters.tls_fp"
                                        placeholder="请输入TLS指纹"
                                        class="h-8 text-xs"
                                    />
                                </div>

                                <!-- 来源 -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >来源</Label
                                    >
                                    <Input
                                        v-model="logsFilters.referer"
                                        placeholder="请输入来源"
                                        class="h-8 text-xs"
                                    />
                                </div>

                                <!-- 国家 -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >国家</Label
                                    >
                                    <Input
                                        v-model="logsFilters.country"
                                        placeholder="请输入国家"
                                        class="h-8 text-xs"
                                    />
                                </div>

                                <!-- 省份 -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >省份</Label
                                    >
                                    <Input
                                        v-model="logsFilters.province"
                                        placeholder="请输入省份"
                                        class="h-8 text-xs"
                                    />
                                </div>

                                <!-- 运营商 -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >运营商</Label
                                    >
                                    <Input
                                        v-model="logsFilters.isp"
                                        placeholder="请输入运营商"
                                        class="h-8 text-xs"
                                    />
                                </div>

                                <!-- 节点ID -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs text-muted-foreground"
                                        >节点ID</Label
                                    >
                                    <Input
                                        v-model="logsFilters.node_id"
                                        placeholder="请输入节点ID"
                                        class="h-8 text-xs"
                                    />
                                </div>
                            </div>

                            <!-- 面板底部按钮 -->
                            <div
                                class="mt-4 flex items-center gap-2 border-t pt-4"
                            >
                                <Button
                                    size="sm"
                                    :disabled="logsLoading"
                                    @click="loadLogs(1)"
                                >
                                    <Spinner
                                        v-if="logsLoading"
                                        data-icon="inline-start"
                                    />
                                    <Search v-else data-icon="inline-start" />
                                    搜索
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        Object.assign(logsFilters, {
                                            host: '',
                                            addr: '',
                                            req_uri: '',
                                            req_uri_type: 'exact',
                                            method: '',
                                            status: '',
                                            cache_status: 'all',
                                            server_port: '',
                                            tls_fp: '',
                                            referer: '',
                                            country: '',
                                            province: '',
                                            isp: '',
                                            node_id: '',
                                            start: defaultStart(),
                                            end: defaultEnd(),
                                        })
                                    "
                                >
                                    重置
                                </Button>
                                <button
                                    type="button"
                                    class="ml-1 text-sm text-primary underline-offset-2 hover:underline"
                                    @click="showAdvanced = false"
                                >
                                    收起搜索
                                </button>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <div class="overflow-x-auto border-y">
                            <table class="w-full min-w-[1000px] text-sm">
                                <thead class="border-b text-muted-foreground">
                                    <tr>
                                        <th
                                            class="px-3 py-3 text-left font-medium whitespace-nowrap"
                                        >
                                            时间
                                        </th>
                                        <th
                                            class="px-3 py-3 text-left font-medium"
                                        >
                                            域名
                                        </th>
                                        <th
                                            class="px-3 py-3 text-left font-medium whitespace-nowrap"
                                        >
                                            端口
                                        </th>
                                        <th
                                            class="px-3 py-3 text-left font-medium whitespace-nowrap"
                                        >
                                            协议
                                        </th>
                                        <th
                                            class="px-3 py-3 text-left font-medium whitespace-nowrap"
                                        >
                                            方法
                                        </th>
                                        <th
                                            class="px-3 py-3 text-left font-medium"
                                        >
                                            URI
                                        </th>
                                        <th
                                            class="px-3 py-3 text-left font-medium whitespace-nowrap"
                                        >
                                            状态码
                                        </th>
                                        <th
                                            class="px-3 py-3 text-left font-medium whitespace-nowrap"
                                        >
                                            客户端IP
                                        </th>
                                        <th
                                            class="px-3 py-3 text-left font-medium whitespace-nowrap"
                                        >
                                            TLS指纹
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="logsLoading">
                                        <td
                                            colspan="9"
                                            class="px-4 py-12 text-center"
                                        >
                                            <Spinner class="mx-auto" />
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(row, idx) in logsRows"
                                        :key="`log-${idx}`"
                                        class="border-b hover:bg-muted/30"
                                    >
                                        <td
                                            class="px-3 py-2.5 text-xs whitespace-nowrap text-muted-foreground tabular-nums"
                                        >
                                            {{
                                                formatDate(
                                                    row.time ??
                                                        row.timestamp ??
                                                        row.create_at2,
                                                )
                                            }}
                                        </td>
                                        <td class="max-w-[160px] px-3 py-2.5">
                                            <div class="truncate">
                                                {{
                                                    textValue(
                                                        row.host ?? row.domain,
                                                    ) || '-'
                                                }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 tabular-nums">
                                            {{
                                                textValue(
                                                    row.server_port ?? row.port,
                                                ) || '-'
                                            }}
                                        </td>
                                        <td class="px-3 py-2.5">
                                            {{
                                                textValue(
                                                    row.ssl ??
                                                        row.protocol ??
                                                        row.scheme,
                                                ) || '-'
                                            }}
                                        </td>
                                        <td
                                            class="px-3 py-2.5 font-mono text-xs"
                                        >
                                            {{ textValue(row.method) || '-' }}
                                        </td>
                                        <td class="max-w-[200px] px-3 py-2.5">
                                            <div
                                                class="truncate font-mono text-xs"
                                            >
                                                {{
                                                    textValue(
                                                        row.req_uri ??
                                                            row.uri ??
                                                            row.url,
                                                    ) || '-'
                                                }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <span
                                                class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium tabular-nums"
                                                :class="{
                                                    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400':
                                                        String(
                                                            row.status ?? '',
                                                        ).startsWith('2'),
                                                    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400':
                                                        String(
                                                            row.status ?? '',
                                                        ).startsWith('3'),
                                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400':
                                                        String(
                                                            row.status ?? '',
                                                        ).startsWith('4') ||
                                                        String(
                                                            row.status ?? '',
                                                        ).startsWith('5'),
                                                    'bg-muted text-muted-foreground':
                                                        !String(
                                                            row.status ?? '',
                                                        ).match(/^[2345]/),
                                                }"
                                            >
                                                {{
                                                    textValue(row.status) || '-'
                                                }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-3 py-2.5 font-mono text-xs whitespace-nowrap"
                                        >
                                            {{
                                                textValue(
                                                    row.addr ??
                                                        row.ip ??
                                                        row.client_ip,
                                                ) || '-'
                                            }}
                                        </td>
                                        <td class="max-w-[120px] px-3 py-2.5">
                                            <div
                                                class="truncate font-mono text-xs text-muted-foreground"
                                            >
                                                {{
                                                    textValue(
                                                        row.tls_fp ?? row.fp,
                                                    ) || '-'
                                                }}
                                            </div>
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            !logsLoading &&
                                            logsRows.length === 0
                                        "
                                    >
                                        <td
                                            colspan="9"
                                            class="px-4 py-16 text-center text-sm text-muted-foreground"
                                        >
                                            暂无数据
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- 分页 -->
                <div class="flex items-center justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="logsPage <= 1 || logsLoading"
                        @click="loadLogs(logsPage - 1)"
                        >上一页</Button
                    >
                    <span class="text-sm text-muted-foreground"
                        >第 {{ logsPage }} 页</span
                    >
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="logsRows.length < 20 || logsLoading"
                        @click="loadLogs(logsPage + 1)"
                        >下一页</Button
                    >
                </div>
            </template>

            <!-- ── 申请记录 Tab ── -->
            <template v-else>
                <div class="flex justify-end">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="jobsLoading"
                        @click="loadJobs(1)"
                    >
                        <RefreshCw
                            data-icon="inline-start"
                            :class="{ 'animate-spin': jobsLoading }"
                        />
                        刷新
                    </Button>
                </div>

                <Card>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[800px] text-sm">
                                <thead class="border-b text-muted-foreground">
                                    <tr>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            Job ID
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            Task ID
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium whitespace-nowrap"
                                        >
                                            申请时间
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium whitespace-nowrap"
                                        >
                                            日志时间
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            日志域名
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            状态
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            进度
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            操作
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="jobsLoading">
                                        <td
                                            colspan="8"
                                            class="px-4 py-12 text-center"
                                        >
                                            <Spinner class="mx-auto" />
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(row, idx) in jobsRows"
                                        :key="`job-${idx}`"
                                        class="border-b hover:bg-muted/30"
                                    >
                                        <td class="px-4 py-3 tabular-nums">
                                            {{
                                                textValue(
                                                    row.id ?? row.job_id,
                                                ) || '-'
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground tabular-nums"
                                        >
                                            {{
                                                textValue(
                                                    row.task_id ?? row.tid,
                                                ) || '-'
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-xs whitespace-nowrap text-muted-foreground"
                                        >
                                            {{
                                                formatDate(
                                                    row.create_at ??
                                                        row.created_at ??
                                                        row.create_time,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-xs text-muted-foreground"
                                        >
                                            <div class="whitespace-nowrap">
                                                {{
                                                    textValue(
                                                        rowDataValue(
                                                            row,
                                                            'start',
                                                        ) ?? row.log_start,
                                                    ) || '-'
                                                }}
                                            </div>
                                            <div class="whitespace-nowrap">
                                                ~
                                                {{
                                                    textValue(
                                                        rowDataValue(
                                                            row,
                                                            'end',
                                                        ) ?? row.log_end,
                                                    ) || '-'
                                                }}
                                            </div>
                                        </td>
                                        <td class="max-w-[160px] px-4 py-3">
                                            <div class="truncate">
                                                {{
                                                    textValue(
                                                        rowDataValue(
                                                            row,
                                                            'domain',
                                                        ) ?? row.domain,
                                                    ) || '全部域名'
                                                }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex items-center gap-1.5 text-xs"
                                            >
                                                <span
                                                    class="h-2 w-2 rounded-full"
                                                    :class="{
                                                        'bg-green-500':
                                                            jobStatusText(
                                                                row,
                                                            ) === 'done',
                                                        'bg-red-500':
                                                            jobStatusText(
                                                                row,
                                                            ) === 'error',
                                                        'animate-pulse bg-blue-500':
                                                            jobStatusText(
                                                                row,
                                                            ) === 'running',
                                                        'bg-yellow-400':
                                                            jobStatusText(
                                                                row,
                                                            ) === 'pending',
                                                        'bg-muted-foreground':
                                                            ![
                                                                'done',
                                                                'error',
                                                                'running',
                                                                'pending',
                                                            ].includes(
                                                                jobStatusText(
                                                                    row,
                                                                ),
                                                            ),
                                                    }"
                                                />
                                                {{ jobStatusText(row) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <div
                                                    class="h-1.5 w-20 overflow-hidden rounded-full bg-muted"
                                                >
                                                    <div
                                                        class="h-full rounded-full bg-primary transition-all"
                                                        :style="{
                                                            width: `${jobProgress(row)}%`,
                                                        }"
                                                    />
                                                </div>
                                                <span
                                                    class="text-xs text-muted-foreground tabular-nums"
                                                    >{{
                                                        jobProgress(row)
                                                    }}%</span
                                                >
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a
                                                v-if="
                                                    jobStatusText(row) ===
                                                    'done'
                                                "
                                                :href="jobDownloadUrl(row)"
                                                target="_blank"
                                                rel="noreferrer"
                                                class="inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs transition-colors hover:bg-muted"
                                            >
                                                <Download class="h-3 w-3" />
                                                下载
                                            </a>
                                            <span
                                                v-else
                                                class="text-xs text-muted-foreground"
                                                >—</span
                                            >
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            !jobsLoading &&
                                            jobsRows.length === 0
                                        "
                                    >
                                        <td
                                            colspan="8"
                                            class="px-4 py-16 text-center text-sm text-muted-foreground"
                                        >
                                            暂无申请记录
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- 分页 -->
                <div class="flex items-center justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="jobsPage <= 1 || jobsLoading"
                        @click="loadJobs(jobsPage - 1)"
                        >上一页</Button
                    >
                    <span class="text-sm text-muted-foreground"
                        >第 {{ jobsPage }} 页</span
                    >
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="jobsRows.length < 20 || jobsLoading"
                        @click="loadJobs(jobsPage + 1)"
                        >下一页</Button
                    >
                </div>
            </template>

            <!-- ── 申请下载对话框（inline modal） ── -->
            <div
                v-if="applyDialogOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="applyDialogOpen = false"
            >
                <div
                    class="w-full max-w-md rounded-xl border bg-background shadow-lg"
                >
                    <div
                        class="flex items-center justify-between border-b px-5 py-4"
                    >
                        <h2 class="text-sm font-semibold">申请下载访问日志</h2>
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground"
                            @click="applyDialogOpen = false"
                        >
                            ✕
                        </button>
                    </div>
                    <form
                        class="grid gap-4 px-5 py-4"
                        @submit.prevent="submitApplyJob"
                    >
                        <div class="grid gap-2">
                            <Label
                                >日志域名
                                <span class="font-normal text-muted-foreground"
                                    >（可选，留空为全部）</span
                                ></Label
                            >
                            <Input
                                v-model="applyDomain"
                                placeholder="example.com"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>下载日期范围</Label>
                            <DateRangePicker
                                :start="applyStart"
                                :end="applyEnd"
                                @update:start="applyStart = $event"
                                @update:end="applyEnd = $event"
                            />
                        </div>
                        <p v-if="applyError" class="text-xs text-red-500">
                            {{ applyError }}
                        </p>
                        <div class="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="applyDialogOpen = false"
                                >取消</Button
                            >
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="applyLoading"
                            >
                                <Spinner
                                    v-if="applyLoading"
                                    data-icon="inline-start"
                                />
                                提交申请
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        <!-- ═══════════════════════════════════════════════
             用量查询
        ════════════════════════════════════════════════ -->
        <template v-else>
            <Card>
                <CardHeader>
                    <form
                        class="grid gap-3 xl:grid-cols-[150px_1fr_1fr_120px_auto]"
                        @submit.prevent="loadOtherData(1)"
                    >
                        <div class="grid gap-2">
                            <Label>类型</Label>
                            <Select v-model="otherFilters.type">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="traffic"
                                            >traffic</SelectItem
                                        >
                                        <SelectItem value="bandwidth"
                                            >bandwidth</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>时间范围</Label>
                            <DateRangePicker
                                :start="otherFilters.start"
                                :end="otherFilters.end"
                                @update:start="otherFilters.start = $event"
                                @update:end="otherFilters.end = $event"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="other-resource"
                                >资源（域名或转发端口）</Label
                            >
                            <Input
                                id="other-resource"
                                v-model="otherFilters.host"
                                placeholder="留空查询全部"
                            />
                        </div>
                        <div class="flex items-end">
                            <Button type="submit" :disabled="loading">
                                <Spinner
                                    v-if="loading"
                                    data-icon="inline-start"
                                />
                                <Search v-else data-icon="inline-start" />
                                查询
                            </Button>
                        </div>
                    </form>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto border-y">
                        <table class="w-full min-w-[860px] table-fixed text-sm">
                            <thead class="border-b text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium">
                                        时间
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        类型
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        指标
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loading" class="border-b">
                                    <td
                                        class="px-4 py-12 text-center"
                                        colspan="3"
                                    >
                                        <Spinner class="mx-auto" />
                                    </td>
                                </tr>
                                <tr
                                    v-for="(row, index) in rows"
                                    :key="`${rowPrimary(row)}-${index}`"
                                    class="border-b"
                                >
                                    <td class="px-4 py-3">
                                        <div class="truncate">
                                            {{ rowPrimary(row) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="truncate">
                                            {{ rowSecondary(row) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 tabular-nums">
                                        {{ rowMetric(row) }}
                                    </td>
                                </tr>
                                <tr v-if="!loading && rows.length === 0">
                                    <td
                                        class="px-6 py-16 text-center text-muted-foreground"
                                        colspan="3"
                                    >
                                        暂无数据
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
            <div class="flex items-center justify-end gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!hasPreviousPage || loading"
                    @click="prevPage"
                    >上一页</Button
                >
                <span class="text-sm text-muted-foreground"
                    >第 {{ page }} 页</span
                >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!hasNextPage || loading"
                    @click="nextPage"
                    >下一页</Button
                >
            </div>
        </template>
    </div>
</template>
