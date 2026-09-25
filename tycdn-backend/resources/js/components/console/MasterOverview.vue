<script setup lang="ts">
import {
    Activity,
    BarChart3,
    CheckCircle2,
    Grid2X2,
    Volume2,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import DashboardChart from '@/components/console/DashboardChart.vue';
import { Button } from '@/components/ui/button';
import {
    dashboardMetric,
    dashboardPoints,
    dashboardRange,
} from '@/lib/adminDashboard';
import type { DashboardMetric, DashboardPeriod } from '@/lib/adminDashboard';
import { listAdminLoginLogs } from '@/lib/adminModulesApi';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRecord, extractCdnflyRows } from '@/lib/cdnflyResponse';
import { masterGet } from '@/lib/masterApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import { streamBytes } from '@/lib/streamAnalytics';

function remote() {
    return reactive({
        data: null as unknown,
        loading: false,
        error: '',
        ticket: 0,
    });
}
type Remote = ReturnType<typeof remote>;
const system = remote(),
    license = remote(),
    account = remote(),
    login = remote(),
    usage = remote(),
    trend = remote(),
    ranking = remote();
const operation = { users: remote(), packages: remote(), recharge: remote() };
const operationPeriod = ref<DashboardPeriod>('last7'),
    usagePeriod = ref<DashboardPeriod>('today'),
    trendPeriod = ref<DashboardPeriod>('today');
const metric = ref<DashboardMetric>('bandwidth'),
    topType = ref('top-domain');
const checking = ref(false),
    refreshingLicense = ref(false),
    actionError = ref('');
let poll: ReturnType<typeof setTimeout> | undefined,
    disposed = false;
const operations = [
    { key: 'users', title: '注册用户', resource: 'new-user-count' },
    { key: 'packages', title: '开通套餐', resource: 'package-sold-count' },
    { key: 'recharge', title: '充值金额', resource: 'recharge-count' },
] as const;
const periods: { key: DashboardPeriod; label: string }[] = [
    { key: 'today', label: '今日' },
    { key: 'yesterday', label: '昨日' },
    { key: 'last7', label: '近7日' },
    { key: 'last30', label: '近30日' },
];
const operationPeriods: { key: DashboardPeriod; label: string }[] = [
    { key: 'last7', label: '近7日' },
    { key: 'last30', label: '近30日' },
    { key: 'lastMonth', label: '上个月' },
];
const metrics: { key: DashboardMetric; label: string; field: string }[] = [
    { key: 'bandwidth', label: '带宽', field: 'bandwidth_value' },
    { key: 'req', label: '请求数', field: 'req_value' },
    { key: 'traffic', label: '流量', field: 'traffic_value' },
    { key: 'blackip', label: '拉黑IP数', field: 'blackip_value' },
];
const tops = [
    { key: 'top-domain', label: '域名' },
    { key: 'top-url', label: 'URL' },
    { key: 'top-ip', label: 'IP' },
    { key: 'top-country', label: '国家' },
];
const record = (state: Remote): CdnflyRecord =>
    extractCdnflyRecord(state.data) ?? {};
const sys = computed(() => record(system)),
    auth = computed(() => record(license)),
    user = computed(() => record(account));
const agent = computed(() => extractCdnflyRecord(sys.value.agent_status) ?? {}),
    es = computed(() => extractCdnflyRecord(sys.value.es_status) ?? {});
const lastLogin = computed(() => {
    const rows = extractCdnflyRows(login.data);

    return rows[1] ?? rows[0] ?? {};
});
const greeting = computed(() => {
    const hour = new Date().getHours();

    return hour < 6
        ? '凌晨好，夜深了，请注意休息'
        : hour < 12
          ? '早上好，一天之计在于晨'
          : hour < 18
            ? '下午好，工作顺利吗？不要忘记休息'
            : '晚上好，放松一下，为自己倒杯茶吧';
});
const agentLabel = computed(
    () =>
        ({
            done: '正常',
            failed: '检查失败',
            pending: '待检查',
            process: '检查中',
        })[String(agent.value.state)] ?? '—',
);
const usageStats = [
    { label: '域名数', field: 'domain_count' },
    { label: '转发数', field: 'stream_port_count' },
    { label: '证书', field: 'cert_count' },
    { label: '已售套餐', field: 'user_package_count' },
];
const errorText = (e: unknown) => (e instanceof Error ? e.message : '请求失败');
async function load(
    target: Remote,
    request: () => Promise<unknown>,
): Promise<boolean> {
    const ticket = ++target.ticket;
    target.loading = true;
    target.error = '';
    target.data = null;

    try {
        const result = await request();

        if (!disposed && ticket === target.ticket) {
            target.data = result;
        }

        return true;
    } catch (e) {
        if (!disposed && ticket === target.ticket) {
            target.error = errorText(e);
        }

        return false;
    } finally {
        if (!disposed && ticket === target.ticket) {
            target.loading = false;
        }
    }
}
function loadOperation() {
    const range = dashboardRange(operationPeriod.value);

    return Promise.all(
        operations.map((item) =>
            load(operation[item.key], () =>
                masterGet(item.resource, {
                    ...range,
                    ...(item.key === 'recharge'
                        ? {
                              limit: 0,
                              state: '已付款',
                              type: '充值',
                              group_by: 'day',
                          }
                        : {}),
                }),
            ),
        ),
    );
}
const loadUsage = () =>
    load(usage, () =>
        masterGet('usage-count', dashboardRange(usagePeriod.value)),
    );
const loadTrend = () =>
    load(trend, () =>
        masterGet('usage', {
            ...dashboardRange(trendPeriod.value),
            type: metric.value,
        }),
    );
const loadRanking = () =>
    load(ranking, () =>
        masterGet('site-top', { recent_time: '30m', type: topType.value }),
    );
const loadSystem = () => load(system, () => masterGet('overview'));
const loadLicense = () => load(license, () => masterGet('license'));
async function loadAccount() {
    const ok = await load(account, () => masterGet('master-account'));

    if (ok && user.value.id !== undefined) {
        await load(login, () =>
            listAdminLoginLogs({
                uid: String(user.value.id),
                limit: 2,
                page: 1,
            }),
        );
    }
}
async function checkAgent() {
    if (checking.value) {
        return;
    }

    checking.value = true;
    actionError.value = '';
    clearTimeout(poll);

    try {
        await apiRequest('/api/admin/workspace/agent-check', {
            method: 'POST',
        });
        toast.success('已发送检查指令，等待检查结果');
        await loadSystem();
        const check = async (remaining: number) => {
            if (disposed) {
                return;
            }

            await loadSystem();

            if (
                remaining > 1 &&
                (agent.value.state === 'pending' ||
                    agent.value.state === 'process')
            ) {
                poll = setTimeout(() => void check(remaining - 1), 10000);
            }
        };

        if (!disposed) {
            poll = setTimeout(() => void check(6), 10000);
        }
    } catch (e) {
        actionError.value = errorText(e);
    } finally {
        checking.value = false;
    }
}
async function refreshLicense() {
    if (refreshingLicense.value) {
        return;
    }

    refreshingLicense.value = true;
    actionError.value = '';

    try {
        await apiRequest('/api/admin/workspace/license', { method: 'POST' });
        await loadLicense();

        if (!license.error) {
            toast.success('授权已刷新');
        }
    } catch (e) {
        actionError.value = errorText(e);
    } finally {
        refreshingLicense.value = false;
    }
}
watch(operationPeriod, loadOperation);
watch(usagePeriod, loadUsage);
watch([trendPeriod, metric], loadTrend);
watch(topType, loadRanking);
onMounted(() => {
    void Promise.all([
        loadOperation(),
        loadUsage(),
        loadTrend(),
        loadRanking(),
        loadSystem(),
        loadLicense(),
        loadAccount(),
    ]);
});
onUnmounted(() => {
    disposed = true;
    clearTimeout(poll);
});
</script>

<template>
    <div
        class="admin-home grid min-w-0 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]"
    >
        <div class="grid min-w-0 content-start gap-4">
            <section class="dashboard-card" aria-label="运营数据">
                <header>
                    <h2 data-typography="section-title">
                        <BarChart3 />运营数据
                    </h2>
                    <div class="periods" role="group" aria-label="运营数据周期">
                        <Button
                            variant="ghost"
                            type="button"
                            data-slot="console-segment"
                            v-for="period in operationPeriods"
                            :key="period.key"
                            :aria-pressed="operationPeriod === period.key"
                            @click="operationPeriod = period.key"
                        >
                            {{ period.label }}
                        </Button>
                    </div>
                </header>
                <div class="grid gap-3 p-4 sm:grid-cols-3">
                    <DashboardChart
                        v-for="item in operations"
                        :key="item.key"
                        class="rounded-md border bg-muted/30"
                        :title="item.title"
                        :metric="item.key"
                        kind="bar"
                        :points="
                            dashboardPoints(operation[item.key].data, item.key)
                        "
                        :loading="operation[item.key].loading"
                        :error="operation[item.key].error"
                        @retry="loadOperation"
                    />
                </div>
            </section>
            <section
                class="dashboard-card"
                aria-label="网络概览"
                :aria-busy="usage.loading"
            >
                <header>
                    <h2 data-typography="section-title">
                        <BarChart3 />网络概览
                    </h2>
                    <div class="periods" role="group" aria-label="网络概览周期">
                        <Button
                            variant="ghost"
                            type="button"
                            data-slot="console-segment"
                            v-for="period in periods"
                            :key="period.key"
                            :aria-pressed="usagePeriod === period.key"
                            @click="usagePeriod = period.key"
                        >
                            {{ period.label }}
                        </Button>
                    </div>
                </header>
                <p
                    data-typography="body"
                    v-if="usage.error"
                    role="alert"
                    class="error"
                >
                    {{ usage.error }}
                    <Button
                        variant="link"
                        size="inline"
                        type="button"
                        data-slot="console-link"
                        @click="loadUsage"
                        >重试</Button
                    >
                </p>
                <div class="grid grid-cols-2 gap-3 p-4 lg:grid-cols-4">
                    <div
                        v-for="item in metrics"
                        :key="item.key"
                        class="rounded-md border bg-muted/30 p-3"
                    >
                        <p
                            data-typography="label"
                            class="text-muted-foreground"
                        >
                            {{
                                item.key === 'bandwidth'
                                    ? '带宽峰值'
                                    : item.key === 'traffic'
                                      ? '总流量'
                                      : item.label
                            }}
                        </p>
                        <p
                            data-typography="metric"
                            class="mt-2 font-semibold tabular-nums"
                        >
                            {{
                                usage.loading
                                    ? '加载中…'
                                    : dashboardMetric(
                                          record(usage)[item.field],
                                          item.key,
                                      )
                            }}
                        </p>
                    </div>
                </div>
            </section>
            <div
                class="grid min-w-0 gap-4 min-[1450px]:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
            >
                <section class="dashboard-card min-w-0" aria-label="监控趋势">
                    <header>
                        <h2 data-typography="section-title">
                            <Activity />监控趋势
                        </h2>
                        <div
                            class="periods"
                            role="group"
                            aria-label="监控趋势周期"
                        >
                            <Button
                                variant="ghost"
                                type="button"
                                data-slot="console-segment"
                                v-for="period in periods"
                                :key="period.key"
                                :aria-pressed="trendPeriod === period.key"
                                @click="trendPeriod = period.key"
                            >
                                {{ period.label }}
                            </Button>
                        </div>
                    </header>
                    <div class="p-4">
                        <div
                            class="tabs mb-4"
                            role="tablist"
                            aria-label="监控指标"
                        >
                            <Button
                                variant="ghost"
                                type="button"
                                data-slot="console-tab"
                                v-for="item in metrics"
                                :key="item.key"
                                role="tab"
                                :aria-selected="metric === item.key"
                                @click="metric = item.key"
                            >
                                {{ item.label }}
                            </Button>
                        </div>
                        <DashboardChart
                            :title="
                                metrics.find((item) => item.key === metric)
                                    ?.label ?? ''
                            "
                            :metric="metric"
                            kind="line"
                            :points="dashboardPoints(trend.data, metric)"
                            :loading="trend.loading"
                            :error="trend.error"
                            @retry="loadTrend"
                        />
                    </div>
                </section>
                <section
                    class="dashboard-card min-w-0"
                    aria-label="TOP10 数据"
                    :aria-busy="ranking.loading"
                >
                    <header>
                        <h2 data-typography="section-title">
                            <Activity />TOP10 数据 (近30分钟)
                        </h2>
                    </header>
                    <div class="p-4">
                        <div
                            class="tabs mb-3"
                            role="tablist"
                            aria-label="TOP10 类型"
                        >
                            <Button
                                variant="ghost"
                                type="button"
                                data-slot="console-tab"
                                v-for="item in tops"
                                :key="item.key"
                                role="tab"
                                :aria-selected="topType === item.key"
                                @click="topType = item.key"
                            >
                                {{ item.label }}
                            </Button>
                        </div>
                        <p
                            data-typography="body"
                            v-if="ranking.error"
                            role="alert"
                            class="error"
                        >
                            {{ ranking.error }}
                            <Button
                                variant="link"
                                size="inline"
                                type="button"
                                data-slot="console-link"
                                @click="loadRanking"
                                >重试</Button
                            >
                        </p>
                        <div class="overflow-x-auto">
                            <table
                                class="w-full text-xs"
                                aria-label="TOP10 排行"
                            >
                                <thead class="sr-only">
                                    <tr>
                                        <th>
                                            {{
                                                tops.find(
                                                    (item) =>
                                                        item.key === topType,
                                                )?.label
                                            }}
                                        </th>
                                        <th>请求次数</th>
                                        <th>出站流量</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(
                                            row, index
                                        ) in extractCdnflyRows(
                                            ranking.data,
                                        ).slice(0, 10)"
                                        :key="index"
                                    >
                                        <td>
                                            <div
                                                class="max-w-32 truncate"
                                                :title="String(row.res)"
                                            >
                                                {{ row.res }}
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            {{
                                                row.count == null
                                                    ? '—'
                                                    : `${row.count}次`
                                            }}
                                        </td>
                                        <td
                                            class="text-right whitespace-nowrap"
                                        >
                                            {{ streamBytes(row.traffic) }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            !extractCdnflyRows(ranking.data)
                                                .length
                                        "
                                    >
                                        <td
                                            colspan="3"
                                            class="h-32 text-center text-muted-foreground"
                                        >
                                            {{
                                                ranking.loading
                                                    ? '加载中…'
                                                    : ranking.error
                                                      ? '数据加载失败'
                                                      : '暂无数据'
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <aside class="grid min-w-0 content-start gap-4">
            <section class="dashboard-card p-4" aria-label="管理员账号">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-14 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/5 text-2xl font-semibold text-primary"
                    >
                        {{
                            String(user.name ?? '—')
                                .slice(0, 1)
                                .toUpperCase()
                        }}
                    </div>
                    <div class="min-w-0 text-xs">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <strong class="text-sm">{{
                                user.name ?? '—'
                            }}</strong
                            ><span class="text-muted-foreground">{{
                                greeting
                            }}</span>
                        </div>
                        <dl
                            class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-2"
                        >
                            <dt class="text-muted-foreground">账号ID:</dt>
                            <dd>{{ user.id ?? '—' }}</dd>
                            <dt class="text-muted-foreground">最近登录:</dt>
                            <dd>
                                {{
                                    lastLogin.create_at2 ??
                                    lastLogin.create_at ??
                                    '—'
                                }}
                            </dd>
                            <dt class="text-muted-foreground">登录IP:</dt>
                            <dd class="break-words">
                                {{ lastLogin.ip ?? '—'
                                }}<template v-if="lastLogin.ip_location">
                                    ({{ lastLogin.ip_location }})</template
                                >
                            </dd>
                        </dl>
                    </div>
                </div>
                <p
                    data-typography="body"
                    v-if="account.error || login.error"
                    role="alert"
                    class="error"
                >
                    {{ account.error || login.error }}
                    <Button
                        variant="link"
                        size="inline"
                        type="button"
                        data-slot="console-link"
                        @click="loadAccount"
                        >重试</Button
                    >
                </p>
            </section>
            <section
                class="dashboard-card"
                aria-label="系统状态"
                :aria-busy="system.loading"
            >
                <header>
                    <h2 data-typography="section-title"><Volume2 />系统状态</h2>
                </header>
                <div class="p-4">
                    <p
                        data-typography="body"
                        v-if="system.error"
                        role="alert"
                        class="error"
                    >
                        {{ system.error }}
                        <Button
                            variant="link"
                            size="inline"
                            type="button"
                            data-slot="console-link"
                            @click="loadSystem"
                            >重试</Button
                        >
                    </p>
                    <table class="w-full text-xs" aria-label="系统状态">
                        <tbody>
                            <tr>
                                <td>主控状态</td>
                                <td>
                                    <CheckCircle2
                                        v-if="system.data && !system.error"
                                        class="status-ok"
                                        aria-label="正常"
                                    /><span v-else>{{
                                        system.loading ? '检查中' : '—'
                                    }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td>Elasticsearch</td>
                                <td>
                                    <CheckCircle2
                                        v-if="es.code === 0"
                                        class="status-ok"
                                        aria-label="正常"
                                    /><span v-else>{{ es.msg || '—' }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td>Agent状态</td>
                                <td>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <CheckCircle2
                                            v-if="agent.state === 'done'"
                                            class="status-ok"
                                            aria-label="正常"
                                        /><span v-else>{{ agentLabel }}</span
                                        ><Button
                                            class="h-6 px-2 text-xs"
                                            :disabled="
                                                checking || system.loading
                                            "
                                            @click="checkAgent"
                                            >{{
                                                checking
                                                    ? '提交中…'
                                                    : '立即检查'
                                            }}</Button
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        data-typography="helper"
                        class="mt-3 text-muted-foreground"
                    >
                        Agent状态上次检查时间 {{ agent.check_at ?? '—' }}。
                    </p>
                    <p
                        data-typography="body"
                        v-if="actionError"
                        role="alert"
                        class="error"
                    >
                        {{ actionError }}
                    </p>
                </div>
            </section>
            <section
                class="dashboard-card"
                aria-label="系统授权"
                :aria-busy="license.loading"
            >
                <header>
                    <h2 data-typography="section-title"><Volume2 />系统授权</h2>
                </header>
                <div class="p-4">
                    <p
                        data-typography="body"
                        v-if="license.error"
                        role="alert"
                        class="error"
                    >
                        {{ license.error }}
                        <Button
                            variant="link"
                            size="inline"
                            type="button"
                            data-slot="console-link"
                            @click="loadLicense"
                            >重试</Button
                        >
                    </p>
                    <table class="w-full text-xs" aria-label="系统授权">
                        <tbody>
                            <tr>
                                <td>授权节点</td>
                                <td>
                                    {{
                                        Number(auth.nodes) === -1
                                            ? '不限制'
                                            : (auth.nodes ?? '—')
                                    }}
                                </td>
                            </tr>
                            <tr>
                                <td>当前节点</td>
                                <td>{{ sys.node_count ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td>到期时间</td>
                                <td>
                                    {{
                                        Number(auth.nodes) === -1
                                            ? '不限制'
                                            : (auth.end_at ?? '—')
                                    }}
                                </td>
                            </tr>
                            <tr>
                                <td>操作</td>
                                <td>
                                    <Button
                                        class="h-6 px-2 text-xs"
                                        :disabled="
                                            refreshingLicense || license.loading
                                        "
                                        @click="refreshLicense"
                                        >{{
                                            refreshingLicense
                                                ? '刷新中…'
                                                : '刷新授权'
                                        }}</Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="dashboard-card" aria-label="使用统计">
                <header>
                    <h2 data-typography="section-title"><Grid2X2 />使用统计</h2>
                </header>
                <div class="p-4">
                    <table class="w-full text-xs" aria-label="使用统计">
                        <tbody>
                            <tr v-for="item in usageStats" :key="item.field">
                                <td>{{ item.label }}</td>
                                <td>{{ sys[item.field] ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </aside>
    </div>
</template>

<style scoped>
@reference '../../../css/app.css';
.dashboard-card {
    @apply min-w-0 overflow-hidden rounded-lg border bg-card text-card-foreground shadow-sm;
}
.dashboard-card > header {
    @apply flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3;
}
h2 {
    @apply flex items-center gap-2 text-sm font-medium;
}
h2 svg {
    @apply size-3.5 text-primary;
}
.periods {
    @apply inline-flex overflow-hidden rounded border text-xs;
}
.periods button {
    @apply border-r px-2 py-1 text-muted-foreground last:border-r-0 hover:bg-muted;
}
.periods button[aria-pressed='true'] {
    @apply bg-primary/5 text-primary outline -outline-offset-1 outline-primary;
}
.tabs {
    @apply flex flex-wrap gap-1 text-xs;
}
.tabs button {
    @apply rounded-md px-4 py-2 text-muted-foreground hover:bg-muted;
}
.tabs button[aria-selected='true'] {
    @apply bg-primary/10 font-medium text-primary;
}
td {
    @apply border-b px-2 py-3 text-muted-foreground;
}
aside td:first-child {
    @apply w-1/2;
}
.status-ok {
    @apply size-3.5 text-emerald-500;
}
.error {
    @apply p-3 text-xs text-destructive;
}
.error button {
    @apply underline;
}
</style>
