<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { extractCdnflyRecord } from '@/lib/cdnflyResponse';
import { masterGet } from '@/lib/masterApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const state = ref<CdnflyRecord>({}),
    usage = ref<CdnflyRecord>({}),
    license = ref<CdnflyRecord>({});
const errors = ref<string[]>([]),
    loading = ref(false),
    period = ref('24h');
const stats = computed(() => [
    { label: '网站域名', value: state.value.domain_count },
    { label: '四层端口', value: state.value.stream_port_count },
    { label: '证书', value: state.value.cert_count },
    { label: '已售套餐', value: state.value.user_package_count },
    { label: '节点', value: state.value.node_count },
]);
function format(value: unknown, unit: string) {
    if (value === undefined || value === null) {
        return '—';
    }

    let n = Number(value);

    if (!Number.isFinite(n)) {
        return String(value);
    }

    if (unit === 'count') {
        return n.toLocaleString();
    }

    const units =
        unit === 'bits'
            ? ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps']
            : ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;

    while (n >= 1000 && i < units.length - 1) {
        n /= 1000;
        i++;
    }

    return `${n.toFixed(i ? 2 : 0)} ${units[i]}`;
}
const status = computed(() => [
    {
        label: 'Elasticsearch',
        value:
            (state.value.es_status as CdnflyRecord)?.msg ??
            ((state.value.es_status as CdnflyRecord)?.code === 0
                ? '正常'
                : '—'),
    },
    {
        label: 'Agent 检查',
        value:
            (
                {
                    done: '正常',
                    pending: '待检查',
                    process: '检查中',
                    failed: '检查失败',
                } as Record<string, string>
            )[String((state.value.agent_status as CdnflyRecord)?.state)] ??
            String((state.value.agent_status as CdnflyRecord)?.state ?? '—'),
    },
    {
        label: '最近检查',
        value: (state.value.agent_status as CdnflyRecord)?.check_at ?? '—',
    },
]);
async function load() {
    loading.value = true;
    errors.value = [];
    const end = new Date(),
        start = new Date(
            end.getTime() - (period.value === '7d' ? 7 : 1) * 86400000,
        );
    const date = (d: Date) =>
        `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')}`;
    const results = await Promise.allSettled([
        masterGet('overview'),
        masterGet('usage-count', { start: date(start), end: date(end) }),
        masterGet('license'),
    ]);
    [state, usage, license].forEach((target, i) => {
        const r = results[i];

        if (r.status === 'fulfilled') {
            target.value = extractCdnflyRecord(r.value) ?? {};
        } else {
            target.value = {};
            errors.value.push(
                `${['系统状态', '网络统计', '授权信息'][i]}：${r.reason instanceof Error ? r.reason.message : '加载失败'}`,
            );
        }
    });
    loading.value = false;
}
onMounted(load);
</script>
<template>
    <section class="grid gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">全局运行概况</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    主控资源与全网统计
                </p>
            </div>
            <div class="flex gap-2">
                <select
                    v-model="period"
                    aria-label="统计周期"
                    class="h-9 rounded-md border bg-card px-3"
                    @change="load"
                >
                    <option value="24h">近 24 小时</option>
                    <option value="7d">近 7 天</option></select
                ><Button variant="outline" :disabled="loading" @click="load">{{
                    loading ? '加载中…' : '刷新'
                }}</Button>
            </div>
        </div>
        <p
            v-for="error in errors"
            :key="error"
            class="rounded-lg border border-destructive/25 bg-destructive/5 p-3 text-sm text-destructive"
            role="alert"
        >
            {{ error }}
        </p>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="item in [
                    { label: '峰值带宽', key: 'bandwidth_value', unit: 'bits' },
                    { label: '请求次数', key: 'req_value', unit: 'count' },
                    { label: '传输流量', key: 'traffic_value', unit: 'bytes' },
                    { label: '拦截 IP', key: 'blackip_value', unit: 'count' },
                ]"
                :key="item.key"
                class="rounded-xl border bg-card p-5"
            >
                <p class="text-sm text-muted-foreground">{{ item.label }}</p>
                <p class="mt-3 text-2xl font-semibold tabular-nums">
                    {{ format(usage[item.key], item.unit) }}
                </p>
            </div>
        </div>
        <div class="grid gap-5 xl:grid-cols-[2fr_1fr]">
            <div class="rounded-xl border bg-card p-5">
                <h3 class="mb-5 font-semibold">资源总览</h3>
                <div class="grid grid-cols-2 gap-5 sm:grid-cols-5">
                    <div v-for="item in stats" :key="item.label">
                        <div class="text-2xl font-semibold">
                            {{ item.value ?? '—' }}
                        </div>
                        <div class="mt-2 text-sm text-muted-foreground">
                            {{ item.label }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <h3 class="mb-4 font-semibold">系统状态</h3>
                <dl class="grid gap-3 text-sm">
                    <div
                        v-for="item in status"
                        :key="item.label"
                        class="flex justify-between gap-4"
                    >
                        <dt class="text-muted-foreground">{{ item.label }}</dt>
                        <dd class="text-right break-all">{{ item.value }}</dd>
                    </div>
                </dl>
                <Link
                    href="/console/admin/maintenance"
                    class="mt-4 inline-block text-sm text-primary"
                    >版本与维护详情 →</Link
                >
            </div>
        </div>
        <div
            v-if="Object.keys(license).length"
            class="rounded-xl border bg-card p-5"
        >
            <h3 class="mb-3 font-semibold">授权信息</h3>
            <dl class="flex flex-wrap gap-x-8 gap-y-3 text-sm">
                <template
                    v-for="(label, key) in {
                        expire: '到期时间',
                        expire_at: '到期时间',
                        version: '版本',
                        max_nodes: '节点上限',
                        node_limit: '节点上限',
                        license_type: '授权类型',
                    }"
                    :key="key"
                    ><div v-if="license[key] !== undefined">
                        <dt class="text-muted-foreground">{{ label }}</dt>
                        <dd class="mt-1">{{ license[key] }}</dd>
                    </div></template
                >
            </dl>
        </div>
    </section>
</template>
