<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowDownUp, RefreshCw, Search } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import StreamMetricChart from '@/components/console/StreamMetricChart.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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
        const data = await masterGet(
            'stream-realtime',
            streamRealtimeParams(type, applied.value, applied.value.port),
        );

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
        const result = await masterGet('stream-top', {
            recent_time: recent.value,
            type: 'top-ports',
        });

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
    <div class="console-page min-w-0 p-4 md:p-6">
        <section
            class="min-w-0 rounded-xl border bg-card p-4 text-card-foreground shadow-sm"
            aria-label="四层实时监控"
        >
            <div
                class="mb-4 flex gap-1"
                role="tablist"
                aria-label="四层监控分类"
            >
                <button
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
                </button>
            </div>
            <div
                id="stream-panel"
                role="tabpanel"
                :aria-labelledby="`stream-tab-${active}`"
            >
                <template v-if="active === 'traffic'">
                    <form
                        class="mb-4 flex flex-wrap items-center gap-2 rounded-md border bg-muted/20 p-3"
                        aria-label="带宽流量查询"
                        @submit.prevent="loadCharts"
                    >
                        <Input
                            v-model="port"
                            aria-label="端口"
                            placeholder="输入端口，如88/TCP 99/UDP"
                            class="h-8 w-64 max-w-full text-sm"
                            @change="loadCharts()"
                        />
                        <div
                            class="inline-flex max-w-full rounded-md border bg-card"
                            role="group"
                            aria-label="时间范围"
                        >
                            <button
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
                            </button>
                        </div>
                        <template v-if="period === 'custom'"
                            ><div
                                class="flex min-w-0 flex-wrap items-center gap-2"
                            >
                                <Label for="stream-start" class="sr-only"
                                    >开始时间</Label
                                ><Input
                                    id="stream-start"
                                    v-model="custom.start"
                                    type="datetime-local"
                                    step="1"
                                    class="h-8 w-52"
                                /><span class="text-muted-foreground">—</span
                                ><Label for="stream-end" class="sr-only"
                                    >结束时间</Label
                                ><Input
                                    id="stream-end"
                                    v-model="custom.end"
                                    type="datetime-local"
                                    step="1"
                                    class="h-8 w-52"
                                /></div
                        ></template>
                        <Button size="sm" type="submit" :disabled="chartLoading"
                            ><Search class="size-3.5" />查询</Button
                        >
                    </form>
                    <p
                        v-if="validationError"
                        role="alert"
                        class="mb-3 text-sm text-destructive"
                    >
                        {{ validationError }}
                    </p>
                    <div class="grid min-w-0 gap-4 lg:grid-cols-2">
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
                        class="mb-4 flex flex-wrap items-center gap-2 rounded-md border bg-muted/20 p-3"
                    >
                        <div
                            class="inline-flex rounded-md border bg-card"
                            role="group"
                            aria-label="排行时间范围"
                        >
                            <button
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
                            </button>
                        </div>
                        <Button
                            size="sm"
                            :disabled="topLoading"
                            @click="loadTop"
                            ><RefreshCw class="size-3.5" />刷新</Button
                        >
                    </div>
                    <div
                        class="w-full max-w-[850px] overflow-x-auto rounded-md border"
                        :aria-busy="topLoading"
                    >
                        <table
                            class="w-full min-w-[540px] table-fixed text-left text-sm"
                            aria-label="端口排行"
                        >
                            <thead class="bg-muted/30 text-muted-foreground">
                                <tr>
                                    <th class="w-[16.5%] px-3 py-2 font-semibold">
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
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1"
                                            :aria-label="`按${col.label}排序`"
                                            @click="changeSort(col.key)"
                                        >
                                            {{ col.label
                                            }}<ArrowDownUp class="size-3" />
                                        </button>
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
