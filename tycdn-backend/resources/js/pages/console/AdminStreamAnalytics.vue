<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowDownUp, RefreshCw, Search } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import StreamMetricChart from '@/components/console/StreamMetricChart.vue';
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/date-picker/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { getUserStreamRealtime, getUserStreamTop } from '@/lib/cdnUserApi';
import { getErrorMessage } from '@/lib/formatters';
import { masterGet } from '@/lib/masterApi';
import {
    sortStreamRanks,
    streamBytes,
    streamRange,
    streamRanks,
    streamRealtimeParams,
    streamSeries,
} from '@/lib/streamAnalytics';
import type {
    StreamMetric,
    StreamPeriod,
    StreamRank,
    StreamSeries,
} from '@/lib/streamAnalytics';

const props = withDefaults(defineProps<{ scope?: 'admin' | 'user' }>(), {
    scope: 'admin',
});
const userScope = computed(() => props.scope === 'user');
const query = new URLSearchParams(usePage().url.split('?')[1] ?? '');
const active = ref<'traffic' | 'ranking'>(
    query.get('tab') === 'ranking' ? 'ranking' : 'traffic',
);
const period = ref<StreamPeriod>('1'),
    port = ref(query.get('port') ?? '');
const initial = streamRange('1', { start: '', end: '' });
const custom = reactive({
    start: initial.start.replace(' ', 'T'),
    end: initial.end.replace(' ', 'T'),
});
const applied = ref({ ...initial, port: port.value });
const validationError = ref('');
const emptySeries = (): StreamSeries => ({ outbound: [], inbound: [] });
const charts = reactive<
    Record<
        StreamMetric,
        { series: StreamSeries; loading: boolean; error: string }
    >
>({
    'stream-bandwidth': { series: emptySeries(), loading: false, error: '' },
    'stream-traffic': { series: emptySeries(), loading: false, error: '' },
});
const versions: Record<StreamMetric, number> = {
    'stream-bandwidth': 0,
    'stream-traffic': 0,
};
const chartLoading = computed(() =>
    Object.values(charts).some((chart) => chart.loading),
);
const metrics = ['stream-bandwidth', 'stream-traffic'] as const;
let disposed = false;
async function loadMetric(type: StreamMetric): Promise<void> {
    const version = ++versions[type],
        state = charts[type];
    state.loading = true;
    state.error = '';
    state.series = emptySeries();

    try {
        const params = streamRealtimeParams(
            type,
            applied.value,
            applied.value.port,
        );
        const data = userScope.value
            ? await getUserStreamRealtime(params)
            : await masterGet('stream-realtime', params);

        if (!disposed && version === versions[type]) {
            state.series = streamSeries(data);
        }
    } catch (e) {
        if (!disposed && version === versions[type]) {
            state.error = getErrorMessage(e);
        }
    } finally {
        if (!disposed && version === versions[type]) {
            state.loading = false;
        }
    }
}
async function loadCharts(): Promise<void> {
    try {
        applied.value = {
            ...streamRange(period.value, custom),
            port: port.value.trim(),
        };
        validationError.value = '';
        await Promise.allSettled(metrics.map(loadMetric));
    } catch (e) {
        validationError.value = getErrorMessage(e);
    }
}
function selectPeriod(value: StreamPeriod): void {
    period.value = value;

    if (value !== 'custom') {
        void loadCharts();
    }
}
const recent = ref<'10m' | '30m' | '60m'>('10m'),
    topLoading = ref(false),
    topError = ref(''),
    topRows = ref<StreamRank[]>([]);
const sort = ref<{
    key: 'count' | 'traffic';
    direction: 'asc' | 'desc';
} | null>(null);
const sortedRows = computed(() =>
    sort.value
        ? sortStreamRanks(topRows.value, sort.value.key, sort.value.direction)
        : topRows.value,
);
let topVersion = 0;
async function loadTop(): Promise<void> {
    const version = ++topVersion;
    topLoading.value = true;
    topError.value = '';
    topRows.value = [];

    try {
        const params = {
            recent_time: recent.value,
            type: 'top-ports',
        };
        const result = userScope.value
            ? await getUserStreamTop(params)
            : await masterGet('stream-top', params);

        if (!disposed && version === topVersion) {
            topRows.value = streamRanks(result);
        }
    } catch (e) {
        if (!disposed && version === topVersion) {
            topError.value = getErrorMessage(e);
        }
    } finally {
        if (!disposed && version === topVersion) {
            topLoading.value = false;
        }
    }
}
function selectRecent(value: '10m' | '30m' | '60m'): void {
    recent.value = value;
    void loadTop();
}
function changeSort(key: 'count' | 'traffic'): void {
    sort.value = {
        key,
        direction:
            sort.value?.key === key && sort.value.direction === 'desc'
                ? 'asc'
                : 'desc',
    };
}
function switchTab(tab: 'traffic' | 'ranking'): void {
    if (active.value === tab) {
        return;
    }

    active.value = tab;

    if (tab === 'ranking') {
        void loadTop();
    } else {
        void loadCharts();
    }
}
onMounted(() => {
    if (active.value === 'traffic') {
        void loadCharts();
    } else {
        void loadTop();
    }
});
onUnmounted(() => {
    disposed = true;
    topVersion++;

    for (const type of metrics) {
        versions[type]++;
    }
});
</script>
<template>
    <div
        class="console-page min-w-0 p-4 md:p-6"
        :class="{ 'user-stream-analytics': userScope }"
    >
        <section
            class="stream-analytics-panel min-w-0 rounded-xl border bg-card p-4 text-card-foreground shadow-sm"
            aria-label="四层实时监控"
        >
            <div
                class="mb-4 flex gap-1"
                role="tablist"
                aria-label="四层监控分类"
            >
                <Button
                    variant="ghost"
                    data-slot="console-tab"
                    v-for="tab in [
                        { key: 'traffic', label: '带宽流量' },
                        { key: 'ranking', label: '端口排行' },
                    ] as const"
                    :id="`stream-tab-${tab.key}`"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="active === tab.key"
                    aria-controls="stream-panel"
                    class="rounded-md px-4 py-2 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-primary"
                    :class="
                        active === tab.key
                            ? 'bg-primary/10 text-primary'
                            : 'text-muted-foreground hover:bg-muted'
                    "
                    @click="switchTab(tab.key)"
                >
                    {{ tab.label }}
                </Button>
            </div>
            <div
                id="stream-panel"
                role="tabpanel"
                :aria-labelledby="`stream-tab-${active}`"
            >
                <template v-if="active === 'traffic'">
                    <form
                        class="stream-toolbar mb-4 flex flex-wrap items-center gap-2 rounded-md border bg-muted/20 p-3"
                        aria-label="带宽流量查询"
                        @submit.prevent="loadCharts"
                    >
                        <Input
                            v-model="port"
                            aria-label="端口"
                            placeholder="输入端口，如88/TCP 99/UDP"
                            class="h-8 w-64 max-w-full text-sm"
                            @change="!userScope && loadCharts()"
                        />
                        <div
                            data-slot="console-segment-group"
                            class="inline-flex max-w-full rounded-md border bg-card"
                            role="group"
                            aria-label="时间范围"
                        >
                            <Button
                                variant="ghost"
                                data-slot="console-segment"
                                v-for="option in [
                                    { value: '1', label: '近1小时' },
                                    { value: '6', label: '近6小时' },
                                    { value: '12', label: '近12小时' },
                                    { value: 'custom', label: '自定义' },
                                ] as const"
                                :key="option.value"
                                type="button"
                                :aria-pressed="period === option.value"
                                class="h-8 border-r px-3 text-sm first:rounded-l-md last:rounded-r-md last:border-r-0"
                                :class="
                                    period === option.value
                                        ? 'relative -m-px h-[34px] rounded border border-primary bg-primary/5 text-primary'
                                        : 'text-muted-foreground hover:bg-muted'
                                "
                                @click="selectPeriod(option.value)"
                            >
                                {{ option.label }}
                            </Button>
                        </div>
                        <template v-if="period === 'custom'"
                            ><div
                                class="flex min-w-0 flex-wrap items-center gap-2"
                            >
                                <Label for="stream-start" class="sr-only"
                                    >开始时间</Label
                                ><DatePicker
                                    id="stream-start"
                                    v-model="custom.start"
                                    type="datetime-local"
                                    step="1"
                                    class="h-8 w-52"
                                /><span class="text-muted-foreground">—</span
                                ><Label for="stream-end" class="sr-only"
                                    >结束时间</Label
                                ><DatePicker
                                    id="stream-end"
                                    v-model="custom.end"
                                    type="datetime-local"
                                    step="1"
                                    class="h-8 w-52"
                                /></div
                        ></template>
                        <Button size="sm" type="submit" :disabled="chartLoading"
                            ><Search
                                v-if="!userScope"
                                class="size-3.5"
                            />查询</Button
                        >
                    </form>
                    <p
                        data-typography="body"
                        v-if="validationError"
                        role="alert"
                        class="mb-3 text-destructive"
                    >
                        {{ validationError }}
                    </p>
                    <div
                        class="stream-charts grid min-w-0 gap-4 lg:grid-cols-2"
                    >
                        <StreamMetricChart
                            v-for="type in metrics"
                            :key="type"
                            :type="type"
                            :series="charts[type].series"
                            :loading="charts[type].loading"
                            :error="charts[type].error"
                            :range="applied"
                            @retry="loadMetric(type)"
                        />
                    </div>
                </template>
                <template v-else>
                    <div
                        class="stream-toolbar mb-4 flex flex-wrap items-center gap-2 rounded-md border bg-muted/20 p-3"
                    >
                        <div
                            data-slot="console-segment-group"
                            class="inline-flex rounded-md border bg-card"
                            role="group"
                            aria-label="排行时间范围"
                        >
                            <Button
                                variant="ghost"
                                data-slot="console-segment"
                                v-for="option in [
                                    { value: '10m', label: '10分钟实时' },
                                    { value: '30m', label: '近30分钟' },
                                    { value: '60m', label: '近1小时' },
                                ] as const"
                                :key="option.value"
                                type="button"
                                :aria-pressed="recent === option.value"
                                class="h-8 border-r px-3 text-sm first:rounded-l-md last:rounded-r-md last:border-r-0"
                                :class="
                                    recent === option.value
                                        ? 'relative -m-px h-[34px] rounded border border-primary bg-primary/5 text-primary'
                                        : 'text-muted-foreground hover:bg-muted'
                                "
                                @click="selectRecent(option.value)"
                            >
                                {{ option.label }}
                            </Button>
                        </div>
                        <Button
                            size="sm"
                            :disabled="topLoading"
                            @click="loadTop"
                            ><RefreshCw
                                v-if="!userScope"
                                class="size-3.5"
                            />刷新</Button
                        >
                    </div>
                    <div
                        class="stream-ranking w-full max-w-[850px] overflow-x-auto rounded-md border"
                        :aria-busy="topLoading"
                    >
                        <table
                            class="w-full min-w-[540px] table-fixed text-left text-sm"
                            aria-label="端口排行"
                        >
                            <thead class="bg-muted/30 text-muted-foreground">
                                <tr>
                                    <th
                                        class="w-[16.5%] px-3 py-2 font-semibold"
                                    >
                                        排行
                                    </th>
                                    <th class="px-3 py-2 font-semibold">
                                        端口
                                    </th>
                                    <th
                                        v-for="col in [
                                            { key: 'count', label: '连接数' },
                                            { key: 'traffic', label: '流量' },
                                        ] as const"
                                        :key="col.key"
                                        class="w-[25.75%] px-3 py-2 font-semibold"
                                        :aria-sort="
                                            sort?.key === col.key
                                                ? sort.direction === 'asc'
                                                    ? 'ascending'
                                                    : 'descending'
                                                : 'none'
                                        "
                                    >
                                        <Button
                                            variant="ghost"
                                            size="inline"
                                            data-slot="console-sort"
                                            type="button"
                                            class="inline-flex items-center gap-1"
                                            :aria-label="`按${col.label}排序`"
                                            @click="changeSort(col.key)"
                                        >
                                            {{ col.label
                                            }}<ArrowDownUp class="size-3" />
                                        </Button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="topLoading" class="border-t">
                                    <td colspan="4" class="h-14 px-3">
                                        <Spinner /><span class="sr-only"
                                            >加载端口排行</span
                                        >
                                    </td>
                                </tr>
                                <tr v-else-if="topError" class="border-t">
                                    <td
                                        colspan="4"
                                        class="h-16 px-3 text-destructive"
                                    >
                                        <span role="alert">{{ topError }}</span
                                        ><Button
                                            variant="link"
                                            size="sm"
                                            @click="loadTop"
                                            >重试</Button
                                        >
                                    </td>
                                </tr>
                                <template v-else
                                    ><tr
                                        v-for="(row, index) in sortedRows"
                                        :key="`${row.port}-${index}`"
                                        class="border-t"
                                    >
                                        <td class="px-3 py-3 tabular-nums">
                                            {{ index + 1 }}
                                        </td>
                                        <td
                                            class="truncate px-3 py-3"
                                            :title="row.port"
                                        >
                                            {{ row.port || '—' }}
                                        </td>
                                        <td class="px-3 py-3 tabular-nums">
                                            {{
                                                row.count?.toLocaleString() ??
                                                '—'
                                            }}
                                        </td>
                                        <td class="px-3 py-3 tabular-nums">
                                            {{ streamBytes(row.traffic) }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="!sortedRows.length"
                                        class="border-t"
                                    >
                                        <td
                                            colspan="4"
                                            class="h-14 text-center text-sm text-muted-foreground"
                                        >
                                            暂无数据
                                        </td>
                                    </tr></template
                                >
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </section>
    </div>
</template>

<style scoped>
.user-stream-analytics .stream-analytics-panel {
    border: 0;
    border-radius: 0;
    box-shadow: none;
}
.user-stream-analytics [role='tablist'] {
    gap: 20px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border);
}
.user-stream-analytics [role='tab'] {
    margin-bottom: -1px;
    padding: 12px 20px;
    border-radius: 0;
    border-bottom: 2px solid transparent;
    background: transparent;
    font-size: var(--console-text-body);
    font-weight: 400;
}
.user-stream-analytics [role='tab'][aria-selected='true'] {
    color: var(--primary);
    border-bottom-color: var(--primary);
}
.user-stream-analytics .stream-toolbar {
    gap: 10px;
    margin-bottom: 20px;
    padding: 0;
    border: 0;
    background: transparent;
}
.user-stream-analytics .stream-toolbar input {
    height: 40px;
    font-size: var(--console-text-body);
}
.user-stream-analytics input[aria-label='端口'] {
    width: 313px;
}
.user-stream-analytics .stream-toolbar button {
    height: 40px;
    padding: 0 19px;
    font-size: var(--console-text-body);
    font-weight: 400;
}
.user-stream-analytics .stream-toolbar [role='group'] {
    flex-wrap: wrap;
}
.user-stream-analytics .stream-toolbar button[aria-pressed='true'] {
    height: 42px;
    background: transparent;
    border-radius: 0;
}
.user-stream-analytics .stream-toolbar button[aria-pressed='true']:first-child {
    border-radius: 4px 0 0 4px;
}
.user-stream-analytics .stream-toolbar button[aria-pressed='true']:last-child {
    border-radius: 0 4px 4px 0;
}
.user-stream-analytics :deep(.stream-chart) {
    min-height: 420px;
    padding: 4px;
    border: 0;
    border-radius: 0;
}
.user-stream-analytics :deep(.stream-chart h3) {
    font-size: var(--console-text-section-title);
}
.user-stream-analytics :deep(.stream-chart svg) {
    height: 350px;
}
.user-stream-analytics :deep(.stream-chart svg text) {
    font-size: var(--console-text-helper);
}
.user-stream-analytics .stream-ranking {
    max-width: 1000px;
    border: 0;
    border-radius: 0;
    border-bottom: 1px solid var(--border);
}
.user-stream-analytics .stream-ranking th {
    height: 48px;
    padding: 10px 22px;
    font-size: var(--console-text-body);
}
.user-stream-analytics .stream-ranking td {
    height: 60px;
    padding: 12px 22px;
    font-size: var(--console-text-body);
}
@media (max-width: 640px) {
    .user-stream-analytics .stream-analytics-panel {
        padding: 12px;
    }
    .user-stream-analytics .stream-toolbar button {
        padding: 0 10px;
        font-size: var(--console-text-body);
    }
    .user-stream-analytics :deep(.stream-chart) {
        min-height: 350px;
    }
    .user-stream-analytics :deep(.stream-chart svg) {
        height: 300px;
    }
}
</style>
