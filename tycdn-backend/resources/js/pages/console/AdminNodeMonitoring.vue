<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import MetricChart from '@/components/console/MetricChart.vue';
import { Button } from '@/components/ui/button';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows } from '@/lib/cdnflyResponse';
import { masterGet } from '@/lib/masterApi';
import { monitorSeries } from '@/lib/monitorSeries';
import type { MonitorSeries } from '@/lib/monitorSeries';
import type { CdnflyRecord } from '@/lib/sharedTypes';
const tab = ref('realtime'),
    node = ref(''),
    metric = ref('bandwidth'),
    hours = ref('1');
const nodes = ref<CdnflyRecord[]>([]),
    series = ref<MonitorSeries[]>([]),
    error = ref(''),
    loading = ref(false);
const tabs = [
    { key: 'top', label: '资源排行' },
    { key: 'realtime', label: '监控指标' },
    { key: 'traffic', label: '节点流量' },
];
const metrics = {
    bandwidth: '带宽',
    tcp_conn: 'TCP 连接数',
    sys_load: '系统负载',
    disk_usage: '磁盘使用率',
};
const topParams = computed(() => ({ type: metric.value, recent_time: '5m' }));
const topColumns = computed(() => [
    {
        key: 'node_id',
        label: '节点',
        format: (value: unknown) => {
            const name = nodes.value.find(
                (row) => String(row.id) === String(value),
            )?.name;

            return name ? `${name} · #${value}` : String(value ?? '—');
        },
    },
    ...Object.entries(
        (
            {
                bandwidth: {
                    nic: '网卡',
                    outbound: '出站带宽（bps）',
                    inbound: '入站带宽（bps）',
                },
                tcp_conn: { conn: '连接数' },
                sys_load: { cpu: 'CPU（%）', mem: '内存（%）', load: '负载' },
                disk_usage: {
                    path: '分区',
                    space: '空间使用率（%）',
                    inode: 'inode 使用率（%）',
                },
            } as Record<string, Record<string, string>>
        )[metric.value],
    ).map(([key, label]) => ({ key, label })),
]);
const top = (params: Record<string, string | number>) =>
    masterGet('node-top', params);
const date = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')}`;
async function load() {
    if (!node.value || tab.value === 'top') {
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const end = new Date(),
            start = new Date(end.getTime() - Number(hours.value) * 3600000);
        series.value = monitorSeries(
            await masterGet(
                tab.value === 'traffic' ? 'node-traffic' : 'node-realtime',
                {
                    node: node.value,
                    start: date(start),
                    end: date(end),
                    ...(tab.value === 'traffic' ? {} : { type: metric.value }),
                },
            ),
        );
    } catch (e) {
        series.value = [];
        error.value = e instanceof Error ? e.message : '加载失败';
    } finally {
        loading.value = false;
    }
}
onMounted(async () => {
    try {
        nodes.value = extractCdnflyRows(
            await apiRequest('/api/admin/nodes?limit=0'),
        );
        const selected = new URLSearchParams(window.location.search).get(
            'node_id',
        );
        node.value = String(
            nodes.value.find((item) => String(item.id) === selected)?.id ??
                nodes.value[0]?.id ??
                '',
        );
        await load();
    } catch (e) {
        error.value = e instanceof Error ? e.message : '节点加载失败';
    }
});
</script>
<template>
    <div class="grid gap-5 p-4 md:p-6">
        <ConsoleTabs v-model="tab" :tabs="tabs" @update:model-value="load" />
        <div class="flex flex-wrap gap-3 rounded-xl border bg-card p-4">
            <select
                v-if="tab !== 'traffic'"
                v-model="metric"
                class="h-9 rounded-md border bg-background px-3"
                aria-label="监控指标"
                @change="load"
            >
                <option v-for="(label, key) in metrics" :key="key" :value="key">
                    {{ label }}
                </option></select
            ><select
                v-if="tab !== 'top'"
                v-model="node"
                aria-label="选择节点"
                class="h-9 rounded-md border bg-background px-3"
                @change="load"
            >
                <option
                    v-for="item in nodes"
                    :key="String(item.id)"
                    :value="String(item.id)"
                >
                    {{ item.name ?? item.id }}
                </option></select
            ><select
                v-if="tab !== 'top'"
                v-model="hours"
                aria-label="时间范围"
                class="h-9 rounded-md border bg-background px-3"
                @change="load"
            >
                <option value="1">近一小时</option>
                <option value="6">近六小时</option>
                <option value="24">近一天</option>
                <option value="168">近七天</option></select
            ><Button
                v-if="tab !== 'top'"
                variant="outline"
                :disabled="loading"
                @click="load"
                >刷新</Button
            >
        </div>
        <p v-if="error" class="text-sm text-destructive" role="alert">
            {{ error }}
        </p>
        <ConsoleDataTable
            v-if="tab === 'top'"
            title="节点资源排行"
            :fetch-fn="top"
            :search-params="topParams"
            :columns="topColumns"
            client-side
        /><template v-else
            ><p v-if="loading" class="p-10 text-center text-muted-foreground">
                加载中…
            </p>
            <div v-else-if="series.length" class="grid gap-5 xl:grid-cols-2">
                <MetricChart
                    v-for="(item, index) in series"
                    :key="index"
                    :series="item"
                />
            </div>
            <p
                v-else-if="!error"
                class="rounded-xl border bg-card p-12 text-center text-muted-foreground"
            >
                {{ nodes.length ? '所选时段暂无监测样本' : '暂无节点' }}
            </p></template
        >
    </div>
</template>
