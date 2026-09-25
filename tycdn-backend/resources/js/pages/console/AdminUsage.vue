<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import StreamMetricChart from '@/components/console/StreamMetricChart.vue';
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/date-picker/DatePicker.vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows } from '@/lib/cdnflyResponse';
import { getErrorMessage } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import { streamMetric } from '@/lib/streamAnalytics';
import type { StreamSeries } from '@/lib/streamAnalytics';

const tab = ref('bandwidth');
const tabs = [
    { key: 'bandwidth', label: '带宽' },
    { key: 'traffic', label: '流量' },
];
const presets = [
    { key: 'today', label: '今天' },
    { key: 'yesterday', label: '昨天' },
    { key: '7', label: '近7天' },
    { key: '30', label: '近30天' },
];
const period = ref('today');
function day(date: Date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
function offset(date: Date, days: number) {
    const result = new Date(date);
    result.setDate(result.getDate() + days);

    return result;
}
const customStart = ref(day(new Date())),
    customEnd = ref(day(new Date()));
function dateRange() {
    const today = new Date();

    if (period.value === 'custom') {
        const start = new Date(`${customStart.value}T00:00:00`),
            end = new Date(`${customEnd.value}T00:00:00`);

        if (
            !Number.isFinite(start.getTime()) ||
            !Number.isFinite(end.getTime()) ||
            day(start) !== customStart.value ||
            day(end) !== customEnd.value ||
            end < start
        ) {
            throw new Error('请选择有效日期，结束日期不能早于开始日期');
        }

        return { start: day(start), end: day(offset(end, 1)) };
    }

    if (period.value === 'today') {
        return { start: day(today), end: day(offset(today, 1)) };
    }

    const days = period.value === 'yesterday' ? 1 : Number(period.value);

    return { start: day(offset(today, -days)), end: day(today) };
}
const range = ref(dateRange());
const periodLabel = computed(() =>
    period.value === 'custom'
        ? `${range.value.start} 至 ${day(offset(new Date(`${range.value.end}T00:00:00`), -1))}`
        : presets.find((item) => item.key === period.value)?.label,
);
const filterTypes = [
    { key: 'user_package', label: '用户套餐ID' },
    { key: 'uid', label: '用户ID' },
    { key: 'domain', label: '域名' },
    { key: 'port', label: '转发端口' },
    { key: 'cate', label: '业务类型' },
    { key: 'date', label: '时间范围' },
];
const filterType = ref('user_package'),
    searchText = ref(''),
    category = ref('all');
const filters = reactive<Record<string, string>>({});
const filterError = ref('');
const placeholder = computed(
    () =>
        ({
            user_package: '用户套餐，多个空格分隔',
            uid: '请输入用户ID',
            domain: '域名，多个空格分隔',
            port: '转发端口，多个空格分隔',
        })[filterType.value] ?? '请输入筛选值',
);
watch(filterType, () => {
    searchText.value = filters[filterType.value] ?? '';
    category.value = filters.cate ?? 'all';
    filterError.value = '';
});
function applyFilter() {
    filterError.value = '';

    if (filterType.value === 'date') {
        period.value = 'custom';
        void load();

        return;
    }

    const key = filterType.value,
        value =
            key === 'cate'
                ? category.value === 'all'
                    ? ''
                    : category.value
                : searchText.value.trim().replace(/\s+/g, ' ');

    if (
        value &&
        ((key === 'uid' && !/^\d+$/.test(value)) ||
            (['user_package', 'port'].includes(key) &&
                !/^\d+( \d+)*$/.test(value)))
    ) {
        filterError.value =
            key === 'uid' ? '用户ID请填写数字' : '请填写数字，多个值用空格分隔';

        return;
    }

    if (key === 'domain' && value) {
        delete filters.port;
        filters.cate = 'site';
    }

    if (key === 'port' && value) {
        delete filters.domain;
        filters.cate = 'stream';
    }

    if (key === 'cate') {
        if (value !== 'site') {
            delete filters.domain;
        }

        if (value !== 'stream') {
            delete filters.port;
        }
    }

    if (value) {
        filters[key] = value;
    } else {
        delete filters[key];
    }

    void load();
}
function removeFilter(key: string) {
    delete filters[key];

    if (key === 'cate') {
        delete filters.domain;
        delete filters.port;
    }

    if (filterType.value === key) {
        searchText.value = '';
    }

    category.value = filters.cate ?? 'all';
    void load();
}
function choosePeriod(key: string) {
    period.value = key;
    void load();
}
const rows = ref<CdnflyRecord[]>([]),
    loading = ref(false),
    error = ref(''),
    updatedAt = ref('');
const maximum = ref<number | null>(null),
    percentile = ref<number | null>(null);
const metricType = computed(() =>
    tab.value === 'bandwidth'
        ? ('stream-bandwidth' as const)
        : ('stream-traffic' as const),
);
function numeric(value: unknown): number | null {
    if (value === null || value === undefined || String(value).trim() === '') {
        return null;
    }

    const result = Number(value);

    return Number.isFinite(result) && result >= 0 ? result : null;
}
const formatMetric = (value: unknown) =>
    streamMetric(numeric(value), metricType.value);
const series = computed<StreamSeries>(() => ({
    outbound: rows.value
        .flatMap((row) => {
            const rawDate = String(row.date);
            const timestamp = Date.parse(
                rawDate.length === 10
                    ? `${rawDate}T00:00:00`
                    : rawDate.replace(' ', 'T'),
            );
            const value = numeric(row.value);

            return Number.isFinite(timestamp) && value !== null
                ? [[timestamp, value] as [number, number]]
                : [];
        })
        .sort((a, b) => a[0] - b[0]),
    inbound: [],
}));
const trafficStats = computed(() => {
    const values = rows.value.map((row) => numeric(row.value));

    if (!values.length || values.some((value) => value === null)) {
        return { total: null, peak: null, average: null };
    }

    const valid = values as number[],
        total = valid.reduce((sum, value) => sum + value, 0);

    return { total, peak: Math.max(...valid), average: total / valid.length };
});
const columns = computed(() => [
    { key: 'date', label: '时间' },
    {
        key: 'value',
        label: tab.value === 'bandwidth' ? '带宽' : '流量',
        align: tab.value === 'traffic' ? ('right' as const) : ('left' as const),
        format: formatMetric,
    },
]);
let requestId = 0;
async function load() {
    const token = ++requestId;
    loading.value = true;
    error.value = '';
    rows.value = [];
    maximum.value = percentile.value = null;
    updatedAt.value = '';

    try {
        range.value = dateRange();
        const query = new URLSearchParams({ type: tab.value, ...range.value });

        for (const key of ['uid', 'user_package', 'cate']) {
            if (filters[key]) {
                query.set(key, filters[key]);
            }
        }

        const resource = filters.domain || filters.port;

        if (resource) {
            query.set('res', resource);
        }

        const result = await apiRequest<CdnflyRecord>(
            `/api/admin/workspace/usage?${query}`,
        );

        if (token !== requestId) {
            return;
        }

        if (
            result.code !== undefined &&
            Number(result.code) !== 0 &&
            Number(result.code) !== 200
        ) {
            throw new Error(String(result.msg || '加载用量数据失败'));
        }

        rows.value = extractCdnflyRows(result);
        maximum.value = numeric(result.max_value);
        percentile.value = numeric(result.max_95_value);
        updatedAt.value = new Date().toLocaleTimeString('zh-CN', {
            hour12: false,
        });
    } catch (e) {
        if (token === requestId) {
            error.value = getErrorMessage(e);
        }
    } finally {
        if (token === requestId) {
            loading.value = false;
        }
    }
}
watch(tab, load);
onMounted(load);
onUnmounted(() => ++requestId);
</script>

<template>
    <div class="usage-workspace min-w-0 p-4 md:p-6">
        <div
            class="console-panel space-y-5 rounded-xl border bg-card p-4 text-card-foreground md:p-5"
        >
            <ConsoleTabs v-model="tab" :tabs="tabs" />
            <div class="flex flex-wrap items-start gap-x-3 gap-y-3">
                <form
                    class="flex w-full min-w-0 flex-wrap gap-2 sm:w-auto"
                    @submit.prevent="applyFilter"
                >
                    <div class="flex w-full min-w-0 sm:w-auto">
                        <Select v-model="filterType">
                            <SelectTrigger
                                aria-label="筛选类型"
                                class="w-32 shrink-0 rounded-r-none bg-muted/40"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent
                                ><SelectItem
                                    v-for="item in filterTypes"
                                    :key="item.key"
                                    :value="item.key"
                                    >{{ item.label }}</SelectItem
                                ></SelectContent
                            >
                        </Select>
                        <template v-if="filterType === 'date'">
                            <div
                                class="flex min-w-0 flex-1 flex-wrap gap-2 pl-2"
                            >
                                <DatePicker
                                    v-model="customStart"
                                    type="date"
                                    aria-label="开始日期"
                                    class="w-40"
                                />
                                <DatePicker
                                    v-model="customEnd"
                                    type="date"
                                    aria-label="结束日期"
                                    class="w-40"
                                />
                            </div>
                        </template>
                        <Select
                            v-else-if="filterType === 'cate'"
                            v-model="category"
                        >
                            <SelectTrigger
                                aria-label="业务类型"
                                class="min-w-0 flex-1 rounded-l-none sm:w-64"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent
                                ><SelectItem value="all">全部业务</SelectItem
                                ><SelectItem value="site">网站</SelectItem
                                ><SelectItem value="stream"
                                    >转发</SelectItem
                                ></SelectContent
                            >
                        </Select>
                        <div v-else class="relative min-w-0 flex-1 sm:w-64">
                            <Input
                                v-model="searchText"
                                :aria-label="
                                    filterTypes.find(
                                        (item) => item.key === filterType,
                                    )?.label
                                "
                                :placeholder="placeholder"
                                class="rounded-l-none pr-10"
                            />
                            <Button
                                type="submit"
                                variant="ghost"
                                size="icon"
                                aria-label="搜索"
                                class="absolute top-0 right-0 size-9"
                                ><Search class="size-4"
                            /></Button>
                        </div>
                    </div>
                    <Button
                        v-if="['date', 'cate'].includes(filterType)"
                        type="submit"
                        variant="outline"
                        >应用</Button
                    >
                </form>
                <div class="flex flex-wrap gap-2" aria-label="日期范围">
                    <Button
                        v-for="item in presets"
                        :key="item.key"
                        variant="outline"
                        :aria-pressed="period === item.key"
                        :class="
                            period === item.key
                                ? 'border-primary bg-accent text-primary'
                                : ''
                        "
                        @click="choosePeriod(item.key)"
                        >{{ item.label }}</Button
                    >
                </div>
            </div>
            <p v-if="filterError" role="alert" class="text-sm text-destructive">
                {{ filterError }}
            </p>
            <div
                v-if="Object.keys(filters).length"
                class="flex flex-wrap gap-2"
            >
                <Button
                    v-for="(value, key) in filters"
                    :key="key"
                    variant="secondary"
                    size="sm"
                    :aria-label="`移除${filterTypes.find((item) => item.key === key)?.label}筛选`"
                    @click="removeFilter(String(key))"
                >
                    {{ filterTypes.find((item) => item.key === key)?.label }}:
                    {{
                        key === 'cate'
                            ? value === 'site'
                                ? '网站'
                                : '转发'
                            : value
                    }}<X class="ml-1 size-3" />
                </Button>
            </div>
            <div
                v-if="tab === 'bandwidth'"
                class="grid grid-cols-2 gap-4 px-3 py-2 sm:max-w-2xl"
                aria-label="带宽汇总"
                :aria-busy="loading"
            >
                <div>
                    <p class="mb-1 text-sm text-muted-foreground">峰值</p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ formatMetric(maximum) }}
                    </p>
                </div>
                <div>
                    <p class="mb-1 text-sm text-muted-foreground">95%值</p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ formatMetric(percentile) }}
                    </p>
                </div>
            </div>
            <div
                v-else
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="流量汇总"
                :aria-busy="loading"
            >
                <div
                    class="rounded-md border border-primary/30 bg-accent/30 p-4"
                >
                    <p class="text-sm text-muted-foreground">总流量</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ formatMetric(trafficStats.total) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ periodLabel }}
                    </p>
                </div>
                <div class="rounded-md border bg-card p-4">
                    <p class="text-sm text-muted-foreground">峰值</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ formatMetric(trafficStats.peak) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        单个统计点最大值
                    </p>
                </div>
                <div class="rounded-md border bg-card p-4">
                    <p class="text-sm text-muted-foreground">平均</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ formatMetric(trafficStats.average) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        按当前明细均值
                    </p>
                </div>
                <div class="rounded-md border bg-card p-4">
                    <p class="text-sm text-muted-foreground">数据点</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ loading || error ? '—' : `${rows.length} 条` }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        更新：{{ updatedAt || '—' }}
                    </p>
                </div>
            </div>
            <StreamMetricChart
                :type="metricType"
                :series="series"
                :range="range"
                :loading="loading"
                :error="error"
                :heading="tab === 'bandwidth' ? '带宽趋势' : '流量趋势'"
                area
                @retry="load"
            />
            <section
                class="overflow-hidden rounded-md border bg-card"
                aria-label="用量明细"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b px-4 py-3"
                >
                    <h3 class="text-sm font-semibold">
                        {{ tab === 'bandwidth' ? '明细数据' : '明细列表' }}
                    </h3>
                    <span class="text-xs text-muted-foreground">{{
                        loading || error ? '—' : `共 ${rows.length} 条`
                    }}</span>
                </div>
                <ConsoleDataTable
                    title="用量明细"
                    embedded
                    :show-actions="false"
                    :columns="columns"
                    :data="{
                        rows,
                        total: rows.length,
                        page: 1,
                        pageSize: Math.max(1, rows.length),
                        loading,
                    }"
                    :empty-text="
                        error ? '数据加载失败，请重试' : '暂无用量数据'
                    "
                    :get-row-key="(row) => String(row.date)"
                />
            </section>
        </div>
    </div>
</template>
