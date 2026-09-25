<script setup lang="ts">
import { RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import NodeMetricChart from '@/components/console/NodeMetricChart.vue';
import { Button } from '@/components/ui/button';
import CheckboxField from '@/components/ui/checkbox/CheckboxField.vue';
import DatePicker from '@/components/ui/date-picker/DatePicker.vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows } from '@/lib/cdnflyResponse';
import { masterGet } from '@/lib/masterApi';
import {
    nodeCharts,
    nodeDate,
    nodeTraffic,
    nodeValue,
} from '@/lib/nodeRealtime';
import type { NodeChart, NodeMetric, NodeUnit } from '@/lib/nodeRealtime';
import type { CdnflyRecord } from '@/lib/sharedTypes';
type Tab = 'top' | 'realtime' | 'traffic';
const tab = ref<Tab>('top'),
    node = ref(''),
    nodes = ref<CdnflyRecord[]>([]);
const metrics: { key: NodeMetric; label: string }[] = [
    { key: 'bandwidth', label: '带宽' },
    { key: 'tcp_conn', label: '连接' },
    { key: 'sys_load', label: '负载' },
    { key: 'disk_usage', label: '硬盘' },
];
const tabs: { key: Tab; label: string }[] = [
    { key: 'top', label: '资源排行' },
    { key: 'realtime', label: '监控指标' },
    { key: 'traffic', label: '节点流量' },
];
const settings = reactive({
    top: { metric: 'bandwidth' as NodeMetric, period: '1m' },
    realtime: {
        metric: 'bandwidth' as NodeMetric,
        period: '1',
        start: '',
        end: '',
    },
    traffic: { period: '24', start: '', end: '' },
});
const outbound = ref(true),
    inbound = ref(false),
    exclude = ref('');
const loading = ref(false),
    error = ref(''),
    nodesError = ref(''),
    rows = ref<CdnflyRecord[]>([]),
    charts = ref<NodeChart[]>([]),
    total = ref(0);
const range = ref({
    start: nodeDate(new Date(Date.now() - 3600000)),
    end: nodeDate(new Date()),
});
const sort = ref({ key: '', descending: true });
let request = 0;
let chartContext = '';
onUnmounted(() => {
    request++;
});
const metric = computed(() =>
    tab.value === 'top' ? settings.top.metric : settings.realtime.metric,
);
const current = computed(() => settings[tab.value]);
const custom = computed(() =>
    tab.value === 'traffic' ? settings.traffic : settings.realtime,
);
const periods = computed(() =>
    tab.value === 'top'
        ? [
              ['1m', '1分钟'],
              ['5m', '5分钟'],
              ['30m', '30分钟'],
              ['60m', '1小时'],
          ]
        : tab.value === 'realtime'
          ? [
                ['1', '1小时'],
                ['6', '6小时'],
                ['12', '12小时'],
                ['custom', '自定义'],
            ]
          : [
                ['24', '1天'],
                ['168', '7天'],
                ['720', '30天'],
                ['custom', '自定义'],
            ],
);
const columns = computed<{ key: string; label: string; unit?: NodeUnit }[]>(
    () =>
        (
            ({
                bandwidth: [
                    { key: 'nic', label: '网卡' },
                    { key: 'outbound', label: '出站带宽', unit: 'bps' },
                    { key: 'inbound', label: '入站带宽', unit: 'bps' },
                ],
                tcp_conn: [{ key: 'conn', label: '连接数', unit: 'count' }],
                sys_load: [
                    { key: 'cpu', label: 'CPU使用率', unit: '%' },
                    { key: 'mem', label: '内存使用率', unit: '%' },
                    { key: 'load', label: '系统负载', unit: 'load' },
                ],
                disk_usage: [
                    { key: 'path', label: '分区' },
                    { key: 'space', label: '空间使用率', unit: '%' },
                    { key: 'inode', label: 'inode使用率', unit: '%' },
                ],
            }) as Record<
                NodeMetric,
                { key: string; label: string; unit?: NodeUnit }[]
            >
        )[metric.value],
);
const ranked = computed(() =>
    sort.value.key
        ? [...rows.value].sort(
              (a, b) =>
                  (Number(a[sort.value.key] ?? 0) -
                      Number(b[sort.value.key] ?? 0)) *
                  (sort.value.descending ? -1 : 1),
          )
        : rows.value,
);
const nodeName = (row: CdnflyRecord) =>
    `${row.node_name ?? nodes.value.find((item) => String(item.id) === String(row.node_id))?.name ?? '节点'} (${row.node_id ?? '—'})`;
function formatCell(value: unknown, unit?: NodeUnit) {
    if (!unit) {
        return String(value ?? '—');
    }

    return value !== null &&
        value !== undefined &&
        Number.isFinite(Number(value))
        ? nodeValue(Number(value), unit, 1)
        : '—';
}
function sortBy(key: string) {
    sort.value = {
        key,
        descending: sort.value.key === key ? !sort.value.descending : true,
    };
}
function selectTab(value: Tab) {
    tab.value = value;
    void load();
}
function selectMetric(value: NodeMetric) {
    if (tab.value === 'traffic') {
        return;
    }

    settings[tab.value].metric = value;
    sort.value.key = '';
    void load();
}
function selectPeriod(value: string) {
    current.value.period = value;

    if (value === 'custom' && !custom.value.start) {
        custom.value.start = range.value.start.replace(' ', 'T');
        custom.value.end = range.value.end.replace(' ', 'T');
    }

    void load();
}
async function loadNodes() {
    nodesError.value = '';

    try {
        nodes.value = extractCdnflyRows(
            await apiRequest('/api/admin/nodes?limit=0'),
        );
        const selected =
            node.value ||
            new URLSearchParams(window.location.search).get('node_id');
        node.value = String(
            nodes.value.find((item) => String(item.id) === selected)?.id ??
                nodes.value[0]?.id ??
                '',
        );
    } catch (e) {
        nodesError.value = e instanceof Error ? e.message : '节点加载失败';
    }
}
async function retryNodes() {
    await loadNodes();
    await load();
}
async function load() {
    const id = ++request,
        active = tab.value;
    error.value = '';
    rows.value = [];
    const context = `${active}:${node.value}:${settings.realtime.metric}`;

    if (active !== 'realtime' || context !== chartContext) {
        charts.value = [];
    }

    chartContext = context;
    total.value = 0;
    loading.value = false;

    if (active !== 'top' && !node.value) {
        return;
    }

    if (active === 'traffic' && !outbound.value && !inbound.value) {
        error.value = '请选择至少一种流量类型';

        return;
    }

    let query: Record<string, string>;
    let nextRange = range.value;

    if (active === 'top') {
        query = { type: settings.top.metric, recent_time: settings.top.period };
    } else {
        const config = settings[active],
            end =
                config.period === 'custom' ? new Date(config.end) : new Date();
        const start =
            config.period === 'custom'
                ? new Date(config.start)
                : new Date(end.getTime() - Number(config.period) * 3600000);

        if (
            !Number.isFinite(start.getTime()) ||
            !Number.isFinite(end.getTime()) ||
            start >= end
        ) {
            error.value = '请选择有效的开始和结束时间';

            return;
        }

        nextRange = { start: nodeDate(start), end: nodeDate(end) };
        query = {
            node: node.value,
            ...nextRange,
            ...(active === 'traffic'
                ? { excl_nic: exclude.value.trim() }
                : { type: settings.realtime.metric }),
        };
    }

    loading.value = true;

    try {
        const payload = await masterGet(
            active === 'top'
                ? 'node-top'
                : active === 'traffic'
                  ? 'node-traffic'
                  : 'node-realtime',
            query,
        );

        if (id !== request) {
            return;
        }

        range.value = nextRange;

        if (active === 'top') {
            rows.value = extractCdnflyRows(payload);
        } else if (active === 'traffic') {
            const result = nodeTraffic(payload, outbound.value, inbound.value);
            charts.value = [result.chart];
            total.value = result.total;
        } else {
            charts.value = nodeCharts(payload, settings.realtime.metric);
        }
    } catch (e) {
        if (id === request) {
            error.value = e instanceof Error ? e.message : '加载失败';
        }
    } finally {
        if (id === request) {
            loading.value = false;
        }
    }
}
onMounted(async () => {
    const selected = new URLSearchParams(window.location.search).get('node_id');

    if (selected) {
        tab.value = 'realtime';
    }

    await Promise.allSettled([
        loadNodes(),
        selected ? Promise.resolve() : load(),
    ]);

    if (tab.value !== 'top') {
        await load();
    }
});
</script>
<template>
    <div class="console-admin-node-monitoring p-3 md:p-5">
        <section
            class="node-monitoring min-w-0 rounded-xl border bg-card p-4 shadow-sm md:p-5"
        >
            <h2 data-typography="page-title" class="mb-3 font-semibold">
                节点实时监控
            </h2>
            <div
                role="tablist"
                aria-label="节点实时监控"
                class="mb-4 flex flex-wrap gap-1"
            >
                <Button
                    variant="ghost"
                    type="button"
                    data-slot="console-tab"
                    v-for="item in tabs"
                    :key="item.key"
                    role="tab"
                    :aria-selected="tab === item.key"
                    :aria-controls="`node-${item.key}`"
                    class="rounded-md px-4 py-2 text-sm"
                    :class="
                        tab === item.key
                            ? 'bg-[#2d8cf0]/10 font-medium text-[#2d8cf0]'
                            : 'text-muted-foreground'
                    "
                    @click="selectTab(item.key)"
                >
                    {{ item.label }}
                </Button>
            </div>
            <div
                class="toolbar mb-4 flex flex-wrap items-center gap-x-8 gap-y-3 rounded-md border bg-muted/20 px-3 py-3 md:px-7"
            >
                <div v-if="tab !== 'traffic'" class="flex items-center gap-3">
                    <span>指标</span>
                    <div class="segments" role="group" aria-label="指标">
                        <Button
                            variant="ghost"
                            type="button"
                            data-slot="console-segment"
                            v-for="item in metrics"
                            :key="item.key"
                            :aria-pressed="metric === item.key"
                            @click="selectMetric(item.key)"
                        >
                            {{ item.label }}
                        </Button>
                    </div>
                </div>
                <div v-else class="flex items-center gap-3">
                    <span>类型</span
                    ><label class="flex items-center gap-1"
                        ><CheckboxField
                            v-model="outbound"
                            class="accent-[#2d8cf0]"
                            @change="load"
                        />出站流量</label
                    ><label class="flex items-center gap-1"
                        ><CheckboxField
                            v-model="inbound"
                            class="accent-[#2d8cf0]"
                            @change="load"
                        />入站流量</label
                    >
                </div>
                <div class="flex items-center gap-3">
                    <span>时间</span>
                    <div class="segments" role="group" aria-label="时间">
                        <Button
                            variant="ghost"
                            type="button"
                            data-slot="console-segment"
                            v-for="[value, label] in periods"
                            :key="value"
                            :aria-pressed="current.period === value"
                            @click="selectPeriod(value)"
                        >
                            {{ label }}
                        </Button>
                    </div>
                </div>
                <label v-if="tab !== 'top'" class="flex items-center gap-3"
                    >节点<SelectField
                        v-model="node"
                        aria-label="节点"
                        class="h-8 w-[200px] max-w-full rounded border bg-card px-2"
                        @change="load"
                    >
                        <SelectOption v-if="!nodes.length" value=""
                            >暂无节点</SelectOption
                        >
                        <SelectOption
                            v-for="item in nodes"
                            :key="String(item.id)"
                            :value="String(item.id)"
                        >
                            {{ item.name ?? item.id }}
                        </SelectOption>
                    </SelectField></label
                >
                <label v-if="tab === 'traffic'" class="flex items-center gap-3"
                    >排除网卡<Input
                        v-model="exclude"
                        aria-label="排除网卡"
                        class="h-8 w-[190px] rounded border bg-card px-2"
                        placeholder="排除网卡，多个网卡用空格分隔"
                        @keyup.enter="load"
                /></label>
                <Button
                    size="sm"
                    class="h-8 bg-[#2d8cf0] px-4 text-white hover:bg-[#57a3f3]"
                    :disabled="loading"
                    @click="load"
                    ><RefreshCw
                        class="mr-1 size-3"
                        :class="{ 'animate-spin': loading }"
                    />刷新</Button
                >
                <div
                    v-if="tab !== 'top' && current.period === 'custom'"
                    class="flex w-full flex-wrap items-center gap-3"
                >
                    <label
                        >开始时间
                        <DatePicker
                            v-model="custom.start"
                            type="datetime-local"
                            aria-label="开始时间"
                            class="rounded border bg-card p-1"
                            @change="load" /></label
                    ><label
                        >结束时间
                        <DatePicker
                            v-model="custom.end"
                            type="datetime-local"
                            aria-label="结束时间"
                            class="rounded border bg-card p-1"
                            @change="load"
                    /></label>
                </div>
            </div>
            <p
                data-typography="body"
                v-if="nodesError"
                role="alert"
                class="mb-3 text-destructive"
            >
                {{ nodesError }}
                <Button
                    variant="link"
                    size="inline"
                    type="button"
                    data-slot="console-link"
                    class="underline"
                    @click="retryNodes"
                >
                    重试加载节点
                </Button>
            </p>
            <div :id="`node-${tab}`" role="tabpanel" :aria-busy="loading">
                <p
                    data-typography="body"
                    v-if="error"
                    role="alert"
                    class="p-6 text-center text-destructive"
                >
                    {{ error }}
                    <Button
                        variant="link"
                        size="inline"
                        type="button"
                        data-slot="console-link"
                        class="underline"
                        @click="load"
                        >重试</Button
                    >
                </p>
                <div v-if="tab === 'top'" class="overflow-x-auto">
                    <table class="w-full min-w-[620px] text-left text-xs">
                        <thead class="bg-muted/20">
                            <tr>
                                <th>排行</th>
                                <th>节点</th>
                                <th
                                    v-for="column in columns"
                                    :key="column.key"
                                    :aria-sort="
                                        sort.key === column.key
                                            ? sort.descending
                                                ? 'descending'
                                                : 'ascending'
                                            : undefined
                                    "
                                >
                                    <Button
                                        variant="ghost"
                                        size="inline"
                                        type="button"
                                        data-slot="console-sort"
                                        v-if="column.unit"
                                        class="flex items-center gap-1"
                                        @click="sortBy(column.key)"
                                    >
                                        {{ column.label }}
                                        <span class="text-muted-foreground">{{
                                            sort.key === column.key
                                                ? sort.descending
                                                    ? '↓'
                                                    : '↑'
                                                : '↕'
                                        }}</span></Button
                                    ><span v-else>{{ column.label }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading || !ranked.length">
                                <td
                                    :colspan="columns.length + 2"
                                    class="text-center text-muted-foreground"
                                >
                                    {{
                                        loading
                                            ? '加载中…'
                                            : error
                                              ? '数据加载失败'
                                              : '暂无数据'
                                    }}
                                </td>
                            </tr>
                            <tr
                                v-for="(row, index) in ranked"
                                v-else
                                :key="`${row.node_id}-${row.nic ?? row.path ?? index}`"
                            >
                                <td>{{ index + 1 }}</td>
                                <td>{{ nodeName(row) }}</td>
                                <td v-for="column in columns" :key="column.key">
                                    {{
                                        formatCell(row[column.key], column.unit)
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <template v-else>
                    <p
                        data-typography="body"
                        v-if="loading && !charts.length"
                        class="p-12 text-center text-muted-foreground"
                    >
                        加载中…
                    </p>
                    <template v-else-if="!error || charts.length">
                        <p
                            data-typography="helper"
                            v-if="tab === 'traffic' && charts.length"
                            class="mb-3 inline-block rounded-full border px-3 py-1 font-semibold"
                        >
                            总流量: {{ nodeValue(total, 'MB') }}
                        </p>
                        <div class="grid min-w-0 gap-3">
                            <NodeMetricChart
                                v-for="chart in charts"
                                :key="chart.title"
                                :chart="chart"
                                :range="range"
                            />
                        </div>
                        <p
                            data-typography="body"
                            v-if="!charts.length"
                            class="p-12 text-center text-muted-foreground"
                        >
                            {{ nodes.length ? '暂无数据' : '暂无节点' }}
                        </p>
                    </template>
                </template>
            </div>
        </section>
    </div>
</template>
