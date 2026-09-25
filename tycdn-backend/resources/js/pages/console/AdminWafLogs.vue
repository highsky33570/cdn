<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import WafRankingTable from '@/components/console/WafRankingTable.vue';
import WafTrendChart from '@/components/console/WafTrendChart.vue';
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/date-picker/DatePicker.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { Spinner } from '@/components/ui/spinner';
import { apiRequest } from '@/lib/apiRequest';
import {
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnflyResponse';
import { formatDate, getErrorMessage, textValue } from '@/lib/formatters';
import { masterGet } from '@/lib/masterApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import {
    defaultWafFilters,
    wafParams,
    wafFields,
    normalizeWafStats,
    wafSummary,
    wafRankings,
    wafRankRows,
    wafColumns,
    wafCell,
    wafTruthy,
    wafDetailSections,
    wafAllowTarget,
    mergeWafAllowRule,
} from '@/lib/wafLogs';

const query = new URLSearchParams(usePage().url.split('?')[1] ?? '');
const filters = reactive(defaultWafFilters());

for (const key of Object.keys(filters)) {
    const value = query.get(key);

    if (value !== null) {
        filters[key] = value;
    }
}

const applied = reactive({ ...filters });
const active = ref<'stats' | 'detail'>(
    query.get('tab') === 'detail' ? 'detail' : 'stats',
);
const advanced = ref(query.has('node_id') || query.has('client_ip'));
const loading = ref(false),
    error = ref('');
const stats = ref<ReturnType<typeof normalizeWafStats> | null>(null),
    rows = ref<CdnflyRecord[]>([]);
const page = ref(1),
    size = ref(10),
    total = ref(0);
let version = 0;
const typeRows = computed(() =>
    wafRankRows(stats.value?.top ?? {}, 'attack_type'),
);
const typeTotal = computed(() =>
    typeRows.value.reduce((sum, row) => sum + row.count, 0),
);
const pie = computed(() => {
    let offset = 0;

    return `conic-gradient(${typeRows.value
        .map((row) => {
            const start = offset;
            offset += row.percent;

            return `${row.color} ${start}% ${offset}%`;
        })
        .join(',')})`;
});
const lastPage = computed(() =>
    Math.max(1, Math.ceil(total.value / size.value)),
);
const pages = computed(() =>
    [
        ...new Set([
            1,
            2,
            3,
            page.value - 1,
            page.value,
            page.value + 1,
            lastPage.value,
        ]),
    ]
        .filter((p) => p > 0 && p <= lastPage.value)
        .sort((a, b) => a - b),
);

async function load(target = page.value): Promise<void> {
    const id = ++version,
        tab = active.value;
    loading.value = true;
    error.value = '';

    try {
        const params = wafParams(applied);
        const result = await masterGet(
            tab === 'stats' ? 'attack-stats' : 'attack-log',
            tab === 'stats'
                ? {
                      ...params,
                      top_size: 10,
                      types: 'domain,client_ip,country,province,isp,uri,attack_type',
                  }
                : { ...params, page: target, limit: size.value },
        );

        if (id !== version) {
            return;
        }

        if (tab === 'stats') {
            stats.value = normalizeWafStats(result);
        } else {
            rows.value = extractCdnflyRows(result);
            total.value = extractCdnflyTotal(result, rows.value.length);
            page.value = target;
        }
    } catch (e) {
        if (id === version) {
            error.value = getErrorMessage(e);

            if (tab === 'stats') {
                stats.value = null;
            } else {
                rows.value = [];
                total.value = 0;
            }
        }
    } finally {
        if (id === version) {
            loading.value = false;
        }
    }
}
function switchTab(tab: 'stats' | 'detail'): void {
    if (active.value !== tab) {
        active.value = tab;
        void load();
    }
}
function search(): void {
    try {
        wafParams(filters);
        Object.assign(applied, filters);
        page.value = 1;
        void load(1);
    } catch (e) {
        error.value = getErrorMessage(e);
    }
}
function reset(): void {
    Object.assign(filters, defaultWafFilters());
    search();
}
function drillDown(value: Record<string, string>): void {
    Object.assign(filters, applied, value);
    active.value = 'detail';
    advanced.value = true;
    search();
}
function datePreset(days: number): void {
    const range = defaultWafFilters();
    const start = new Date(range.start);
    start.setDate(start.getDate() - days + 1);
    filters.start = formatDate(start.toISOString()).replace(' ', 'T');
    filters.end = range.end;
}

const detailOpen = ref(false),
    detailLoading = ref(false),
    detailError = ref(''),
    detail = ref<CdnflyRecord>({});
const detailSections = computed(() => wafDetailSections(detail.value));
const allowTarget = computed(() => wafAllowTarget(detail.value));
let detailVersion = 0;
async function showDetail(row: CdnflyRecord): Promise<void> {
    const id = ++detailVersion;
    detail.value = {};
    detailError.value = '';
    detailLoading.value = true;
    detailOpen.value = true;

    try {
        const result = await apiRequest(
            `/api/admin/waf-logs/${encodeURIComponent(textValue(row._id))}`,
        );

        if (id === detailVersion) {
            detail.value = extractCdnflyRecord(result) ?? {};
        }
    } catch (e) {
        if (id === detailVersion) {
            detailError.value = getErrorMessage(e);
        }
    } finally {
        if (id === detailVersion) {
            detailLoading.value = false;
        }
    }
}
watch(detailOpen, (open) => {
    if (!open) {
        detailVersion++;
    }
});
const action = ref<'allow' | 'unlock' | null>(null),
    actionRow = ref<CdnflyRecord>({}),
    saving = ref(false),
    actionError = ref('');
const actionOpen = computed({
    get: () => action.value !== null,
    set: (value: boolean) => {
        if (!value) {
            action.value = null;
        }
    },
});
const actionTarget = computed(() => wafAllowTarget(actionRow.value));
function prepareAction(type: 'allow' | 'unlock', row: CdnflyRecord): void {
    if (
        type === 'unlock' &&
        Number(row.auto_block_exp) > 0 &&
        Number(row.auto_block_exp) <= Date.now() / 1000
    ) {
        toast.info('该自动封禁已到期');

        return;
    }

    if (
        type === 'unlock' &&
        (!/^\d+$/.test(textValue(row.site_id)) || !textValue(row.client_ip))
    ) {
        toast.error('缺少网站或IP信息，无法解锁');

        return;
    }

    actionRow.value = { ...row };
    actionError.value = '';
    action.value = type;
}
async function confirmAction(): Promise<void> {
    saving.value = true;
    actionError.value = '';

    try {
        if (action.value === 'unlock') {
            await apiRequest('/api/admin/waf-logs/unlock', {
                method: 'POST',
                body: JSON.stringify({
                    site_id: Number(actionRow.value.site_id),
                    ip: actionRow.value.client_ip,
                }),
            });
            toast.success('解除封禁任务已提交');
        } else {
            const target = actionTarget.value;

            if (!target) {
                throw new Error('缺少站点或请求信息，无法放行');
            }

            const site = extractCdnflyRecord(
                await apiRequest(`/api/admin/sites/${target.siteId}`),
            );

            if (!site) {
                throw new Error('未获取到网站配置，请重试');
            }

            const result = mergeWafAllowRule(site.waf_allow_rule, target);

            if (result.exists) {
                toast.info('已存在相同放行规则');
            } else {
                await apiRequest(`/api/admin/sites/${target.siteId}`, {
                    method: 'PUT',
                    body: JSON.stringify({ waf_allow_rule: result.rules }),
                });
                toast.success('放行规则已添加');
            }
        }

        action.value = null;
    } catch (e) {
        actionError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}
onMounted(() => void load());
onUnmounted(() => {
    version++;
    detailVersion++;
});
</script>

<template>
    <div class="console-page min-w-0 p-4 md:p-6">
        <section
            class="waf-card min-w-0 rounded-xl border bg-card p-4 text-card-foreground shadow-sm"
            aria-label="WAF日志"
            :aria-busy="loading"
        >
            <div
                class="mb-3 flex gap-1"
                role="tablist"
                aria-label="WAF日志分类"
            >
                <button
                    v-for="tab in [
                        { key: 'stats', label: '安全概览' },
                        { key: 'detail', label: '日志明细' },
                    ] as const"
                    :id="`waf-tab-${tab.key}`"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="active === tab.key"
                    aria-controls="waf-panel"
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
            <form
                class="mb-3 flex flex-wrap items-center gap-2"
                aria-label="域名查询"
                @submit.prevent="search"
            >
                <div class="flex max-w-full">
                    <Input
                        v-model="filters.host"
                        aria-label="域名查询"
                        placeholder="输入域名，多个空格分隔"
                        class="h-8 w-64 min-w-0 rounded-r-none"
                    /><Button
                        size="sm"
                        class="rounded-l-none"
                        :disabled="loading"
                        type="submit"
                        >查询</Button
                    >
                </div>
                <Button
                    type="button"
                    variant="link"
                    size="sm"
                    :aria-expanded="advanced"
                    @click="advanced = !advanced"
                    >{{ advanced ? '收起搜索' : '高级搜索' }}</Button
                >
            </form>
            <form
                v-if="advanced"
                class="mb-4 rounded-md border bg-muted/20 p-4"
                aria-label="高级搜索"
                @submit.prevent="search"
            >
                <div
                    class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <div class="grid gap-1.5">
                        <Label for="waf-start">开始时间</Label
                        ><DatePicker
                            id="waf-start"
                            v-model="filters.start"
                            type="datetime-local"
                            step="1"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="waf-end">结束时间</Label
                        ><DatePicker
                            id="waf-end"
                            v-model="filters.end"
                            type="datetime-local"
                            step="1"
                        />
                    </div>
                    <div class="flex items-end gap-2">
                        <Button
                            v-for="days in [1, 7, 30]"
                            :key="days"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="datePreset(days)"
                            >{{ days === 1 ? '今天' : `近${days}天` }}</Button
                        >
                    </div>
                    <div
                        v-for="field in wafFields"
                        :key="field.key"
                        class="grid min-w-0 gap-1.5"
                    >
                        <Label :for="`waf-${field.key}`">{{
                            field.label
                        }}</Label
                        ><SelectField
                            v-if="field.options"
                            :id="`waf-${field.key}`"
                            v-model="filters[field.key]"
                            class="h-8 rounded-md border bg-background px-2 text-sm"
                        >
                            <SelectOption value="">不限</SelectOption>
                            <SelectOption
                                v-for="(label, value) in field.options"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </SelectOption>
                        </SelectField>
                        <div
                            v-else-if="field.key === 'request_uri'"
                            class="flex min-w-0"
                        >
                            <SelectField
                                v-model="filters.uri_match_type"
                                aria-label="URI匹配方式"
                                class="w-20 shrink-0 rounded-l-md border border-r-0 bg-background px-2 text-sm"
                            >
                                <SelectOption value="exact">精确</SelectOption>
                                <SelectOption value="prefix"
                                    >前缀</SelectOption
                                ></SelectField
                            ><Input
                                :id="`waf-${field.key}`"
                                v-model="filters[field.key]"
                                class="min-w-0 rounded-l-none"
                                placeholder="不包含域名部分的URI"
                            />
                        </div>
                        <Input
                            v-else
                            :id="`waf-${field.key}`"
                            v-model="filters[field.key]"
                            :placeholder="
                                field.key === 'host'
                                    ? '多个域名空格分隔'
                                    : `输入${field.label}`
                            "
                        />
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button type="submit" size="sm" :disabled="loading"
                        >搜索</Button
                    ><Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="loading"
                        @click="reset"
                        >重置</Button
                    >
                </div>
            </form>
            <div
                v-if="error"
                role="alert"
                class="mb-4 rounded border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
            >
                {{ error
                }}<Button variant="link" size="sm" @click="load()">重试</Button>
            </div>
            <div
                id="waf-panel"
                role="tabpanel"
                :aria-labelledby="`waf-tab-${active}`"
            >
                <template v-if="active === 'stats'">
                    <div class="mb-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            v-for="item in wafSummary"
                            :key="item.key"
                            class="rounded-md border px-4 py-3"
                            :data-metric="item.key"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ item.label }}
                            </p>
                            <p class="mt-2 text-xl font-semibold tabular-nums">
                                {{
                                    loading
                                        ? '…'
                                        : stats
                                          ? stats[item.key].toLocaleString()
                                          : '—'
                                }}
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="loading"
                        class="flex h-64 items-center justify-center rounded-md border"
                    >
                        <Spinner /><span class="sr-only">加载安全概览</span>
                    </div>
                    <template v-else-if="stats">
                        <WafTrendChart :points="stats.trend" />
                        <section class="mt-3" aria-label="攻击类型分布">
                            <h3 class="mb-2 text-sm font-semibold">
                                攻击类型分布
                            </h3>
                            <div
                                class="grid gap-3 md:grid-cols-[260px_minmax(0,1fr)]"
                            >
                                <div
                                    class="flex min-h-52 items-center justify-center rounded-md border p-4"
                                >
                                    <div
                                        v-if="typeTotal"
                                        class="relative flex size-44 items-center justify-center rounded-full"
                                        :style="{ background: pie }"
                                        role="img"
                                        :aria-label="
                                            typeRows
                                                .map(
                                                    (r) =>
                                                        `${r.display} ${r.count}次 ${r.percent.toFixed(2)}%`,
                                                )
                                                .join('，')
                                        "
                                    >
                                        <div
                                            class="flex size-28 flex-col items-center justify-center rounded-full bg-card"
                                        >
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >攻击类型</span
                                            ><span
                                                class="mt-1 text-xl font-semibold"
                                                >{{
                                                    typeTotal.toLocaleString()
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                    <p
                                        v-else
                                        class="text-sm text-muted-foreground"
                                    >
                                        暂无攻击类型数据
                                    </p>
                                </div>
                                <WafRankingTable
                                    label="攻击类型"
                                    :rows="typeRows"
                                    share
                                    @filter="drillDown"
                                />
                            </div>
                        </section>
                        <div class="mt-3 grid gap-3 lg:grid-cols-2">
                            <section
                                v-for="ranking in wafRankings"
                                :key="ranking.key"
                                class="min-w-0"
                                :aria-label="ranking.title"
                            >
                                <h3 class="mb-2 text-sm font-semibold">
                                    {{ ranking.title }}
                                </h3>
                                <WafRankingTable
                                    :label="ranking.label"
                                    :rows="wafRankRows(stats.top, ranking.key)"
                                    @filter="drillDown"
                                />
                            </section>
                        </div>
                    </template>
                </template>
                <template v-else>
                    <div class="max-w-full overflow-x-auto rounded-md border">
                        <table
                            class="waf-log-table w-full min-w-[1890px] table-fixed text-left text-sm"
                            aria-label="日志明细"
                        >
                            <colgroup>
                                <col
                                    v-for="col in wafColumns"
                                    :key="col.key"
                                    :style="{ width: `${col.width}px` }"
                                />
                                <col style="width: 120px" />
                            </colgroup>
                            <thead class="bg-muted/30 text-muted-foreground">
                                <tr>
                                    <th
                                        v-for="col in wafColumns"
                                        :key="col.key"
                                    >
                                        {{ col.label }}
                                    </th>
                                    <th
                                        class="sticky right-0 bg-card shadow-sm"
                                    >
                                        操作
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loading">
                                    <td colspan="10" class="h-20">
                                        <Spinner /><span class="sr-only"
                                            >加载日志明细</span
                                        >
                                    </td>
                                </tr>
                                <template v-else
                                    ><tr
                                        v-for="(row, index) in rows"
                                        :key="textValue(row._id) || index"
                                    >
                                        <td
                                            v-for="col in wafColumns"
                                            :key="col.key"
                                            :title="wafCell(row, col.key)"
                                        >
                                            <template
                                                v-if="col.key === 'action'"
                                                ><span
                                                    class="rounded px-1.5 py-0.5 text-xs"
                                                    :class="
                                                        row.action === 'protect'
                                                            ? 'bg-red-500/10 text-red-600 dark:text-red-400'
                                                            : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                                    "
                                                    >{{
                                                        wafCell(row, col.key)
                                                    }}</span
                                                ><span
                                                    v-if="
                                                        wafTruthy(
                                                            row.auto_blocked,
                                                        )
                                                    "
                                                    class="mt-1 block text-xs text-orange-600 dark:text-orange-400"
                                                    >自动封禁</span
                                                ></template
                                            ><span
                                                v-else
                                                class="block truncate"
                                                >{{
                                                    wafCell(row, col.key)
                                                }}</span
                                            >
                                        </td>
                                        <td
                                            class="sticky right-0 bg-card shadow-sm"
                                        >
                                            <div class="flex gap-3">
                                                <Button
                                                    variant="link"
                                                    size="sm"
                                                    class="h-auto p-0"
                                                    :disabled="!row._id"
                                                    @click="showDetail(row)"
                                                    >详情</Button
                                                ><Button
                                                    v-if="
                                                        wafTruthy(
                                                            row.auto_blocked,
                                                        )
                                                    "
                                                    variant="link"
                                                    size="sm"
                                                    class="h-auto p-0"
                                                    @click="
                                                        prepareAction(
                                                            'unlock',
                                                            row,
                                                        )
                                                    "
                                                    >解锁</Button
                                                >
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!rows.length">
                                        <td
                                            colspan="10"
                                            class="h-16 text-center text-xs text-muted-foreground"
                                        >
                                            {{
                                                error
                                                    ? '查询失败，请重试'
                                                    : '暂无数据'
                                            }}
                                        </td>
                                    </tr></template
                                >
                            </tbody>
                        </table>
                    </div>
                    <nav
                        class="mt-4 flex flex-wrap items-center justify-end gap-2 text-sm text-muted-foreground"
                        aria-label="WAF日志分页"
                    >
                        <span aria-live="polite">共 {{ total }} 条</span
                        ><Button
                            variant="outline"
                            size="icon"
                            aria-label="上一页"
                            :disabled="loading || page <= 1"
                            @click="load(page - 1)"
                            ><ChevronLeft class="size-4" /></Button
                        ><template
                            v-for="(number, index) in pages"
                            :key="number"
                            ><span
                                v-if="
                                    index > 0 && number - pages[index - 1] > 1
                                "
                                >…</span
                            ><Button
                                variant="outline"
                                size="icon"
                                :aria-label="`第 ${number} 页`"
                                :aria-current="
                                    page === number ? 'page' : undefined
                                "
                                :class="{
                                    'border-primary text-primary':
                                        page === number,
                                }"
                                :disabled="loading"
                                @click="load(number)"
                                >{{ number }}</Button
                            ></template
                        ><Button
                            variant="outline"
                            size="icon"
                            aria-label="下一页"
                            :disabled="loading || page >= lastPage"
                            @click="load(page + 1)"
                            ><ChevronRight class="size-4" /></Button
                        ><SelectField
                            v-model.number="size"
                            aria-label="每页条数"
                            class="h-8 rounded-md border bg-background px-2"
                            :disabled="loading"
                            @change="load(1)"
                        >
                            <SelectOption
                                v-for="n in [10, 30, 100]"
                                :key="n"
                                :value="n"
                            >
                                {{ n }} 条/页
                            </SelectOption>
                        </SelectField>
                    </nav>
                </template>
            </div>
        </section>
        <Dialog v-model:open="detailOpen"
            ><DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-3xl"
                ><DialogHeader
                    ><DialogTitle>攻击日志详情</DialogTitle
                    ><DialogDescription
                        >攻击概览、请求信息与命中证据</DialogDescription
                    ></DialogHeader
                ><Spinner v-if="detailLoading" />
                <p
                    v-else-if="detailError"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ detailError }}
                </p>
                <template v-else
                    ><section
                        v-for="section in detailSections"
                        :key="section.title"
                    >
                        <h3 class="mb-3 text-sm font-semibold">
                            {{ section.title }}
                        </h3>
                        <dl
                            class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-2 rounded-md border p-3 text-sm"
                        >
                            <template
                                v-for="item in section.items"
                                :key="item.label"
                                ><dt class="text-muted-foreground">
                                    {{ item.label }}
                                </dt>
                                <dd
                                    class="min-w-0 break-all whitespace-pre-wrap"
                                >
                                    {{ item.value }}
                                </dd></template
                            >
                            <dd
                                v-if="!section.items.length"
                                class="text-muted-foreground"
                            >
                                暂无数据
                            </dd>
                        </dl>
                    </section></template
                ><DialogFooter class="flex-wrap gap-2"
                    ><Button
                        v-if="wafTruthy(detail.auto_blocked)"
                        variant="outline"
                        @click="prepareAction('unlock', detail)"
                        >解除自动封禁</Button
                    ><Button
                        variant="outline"
                        :disabled="
                            detailLoading || !!detailError || !allowTarget
                        "
                        @click="prepareAction('allow', detail)"
                        >这是误报，放行此请求</Button
                    ><Button variant="outline" @click="detailOpen = false"
                        >关闭</Button
                    ></DialogFooter
                ></DialogContent
            ></Dialog
        >
        <Dialog v-model:open="actionOpen"
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle>{{
                        action === 'unlock'
                            ? '确认解除自动封禁'
                            : '确认放行请求'
                    }}</DialogTitle
                    ><DialogDescription v-if="action === 'unlock'"
                        >解除网站 {{ actionRow.site_id }} 中 IP
                        {{ actionRow.client_ip }} 的 WAF
                        攻击自动封禁。</DialogDescription
                    ><DialogDescription v-else
                        >为网站 {{ actionTarget?.siteId }} 添加 WAF
                        放行规则：Host={{ actionTarget?.host }}，URI={{
                            actionTarget?.uri
                        }}</DialogDescription
                    ></DialogHeader
                >
                <p
                    v-if="actionError"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ actionError }}
                </p>
                <DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="saving"
                        @click="actionOpen = false"
                        >取消</Button
                    ><Button :disabled="saving" @click="confirmAction"
                        ><Spinner v-if="saving" />确定</Button
                    ></DialogFooter
                ></DialogContent
            ></Dialog
        >
    </div>
</template>
<style scoped>
.waf-log-table th {
    padding: 0.65rem 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}
.waf-log-table td {
    padding: 0.8rem 0.75rem;
}
.waf-log-table th,
.waf-log-table td {
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}
</style>
