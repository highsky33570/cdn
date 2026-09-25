<script setup lang="ts">
import { CircleX, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import { getErrorMessage } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const endpoint = '/api/admin/workspace/tasks';
const initial = new URLSearchParams(
    typeof window === 'undefined' ? '' : window.location.search,
);
const type = ref(initial.get('type') || 'all'),
    state = ref(initial.get('state') || 'all');
const taskId = ref(initial.get('id') ?? ''),
    resource = ref(initial.get('res') ?? '');
const page = ref(1),
    size = ref(10),
    total = ref(0),
    rows = ref<CdnflyRecord[]>([]),
    selected = ref<(string | number)[]>([]);
const loading = ref(false),
    error = ref(''),
    busy = ref(false);
const knownTypes: [string, string][] = [
    ['record_repair', 'DNS记录修复'],
    ['DNS记录同步', 'DNS记录同步'],
    ['网站', '网站配置'],
    ['四层转发', '四层转发'],
    ['证书', '证书同步'],
    ['签发证书', '证书签发'],
    ['unlock_ip', '解锁IP'],
    ['down_http_access_log', '下载网站日志'],
    ['clean_dir', '缓存清理目录'],
    ['clean_url', '缓存清理URL'],
    ['pre_cache_url', 'URL预热'],
    ['允许端口范围配置', '端口范围配置'],
    ['nginx全局配置', 'nginx全局配置'],
    ['openresty配置', 'openresty配置'],
    ['默认80站点配置', '默认站点配置'],
    ['cc_rule', 'CC规则'],
    ['cc_match', 'CC匹配器'],
    ['cc_filter', 'CC过滤器'],
    ['获取黑名单', '获取黑名单'],
    ['网站带宽流量统计', '网站带宽流量统计'],
    ['用户套餐网站带宽流量统计', '用户套餐网站带宽流量统计'],
    ['转发带宽流量统计', '转发带宽流量统计'],
    ['node-config', '节点设置'],
    ['agent检查', 'agent检查'],
    ['备份', '备份'],
];
const seenTypes = ref<string[]>([]);
const typeOptions = computed(() => [
    ...new Map([
        ...seenTypes.value.map((value) => [value, value] as [string, string]),
        ...knownTypes,
        ...(type.value !== 'all'
            ? [
                  [
                      type.value,
                      knownTypes.find(([key]) => key === type.value)?.[1] ??
                          type.value,
                  ] as [string, string],
              ]
            : []),
    ]).entries(),
]);
const states: Record<string, string> = {
    done: '成功',
    failed: '失败',
    pending: '待执行',
    process: '执行中',
};
const columns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '90px' },
    { key: 'pry', label: '优先级', width: '80px', align: 'center' },
    { key: 'name', label: '名称', width: '200px' },
    { key: 'type', label: '类型', width: '180px' },
    { key: 'res', label: '资源ID', width: '115px' },
    { key: 'depend', label: '依赖', width: '120px' },
    { key: 'start_at2', label: '开始时间', width: '190px' },
    { key: 'duration', label: '耗时', width: '90px' },
    { key: 'state', label: '状态', width: '120px' },
    { key: 'err_times', label: '失败次数', width: '95px', align: 'center' },
];
const subColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '90px' },
    { key: 'res', label: '资源', width: '110px' },
    { key: 'data', label: 'data', width: '150px' },
    { key: 'start_at2', label: '开始时间', width: '190px' },
    { key: 'duration', label: '耗时', width: '90px' },
    { key: 'state', label: '状态', width: '110px' },
    { key: 'err_times', label: '失败次数', width: '100px' },
];
const empty = (value: unknown) =>
    value === null || value === undefined || String(value).trim() === '';
const text = (value: unknown, fallback = '—') =>
    empty(value) ? fallback : String(value);
const cancelled = (row: CdnflyRecord) => row.enable === 0 || row.enable === '0';
const stateLabel = (row: CdnflyRecord) =>
    cancelled(row)
        ? '已取消'
        : (states[String(row.state)] ?? text(row.state, '未知'));
const stateVariant = (row: CdnflyRecord) =>
    cancelled(row)
        ? ('outline' as const)
        : row.state === 'failed'
          ? ('destructive' as const)
          : ('secondary' as const);
function duration(row: CdnflyRecord) {
    if (empty(row.start_at2)) {
        return '未开始';
    }

    if (empty(row.end_at2)) {
        return !cancelled(row) && row.state === 'process' ? '进行中' : '—';
    }

    const first = Date.parse(String(row.start_at2).replace(' ', 'T')),
        last = Date.parse(String(row.end_at2).replace(' ', 'T'));

    return Number.isFinite(first) && Number.isFinite(last) && last >= first
        ? `${Math.floor((last - first) / 1000)}秒`
        : '—';
}
function content(value: unknown) {
    if (empty(value)) {
        return '无结果';
    }

    if (typeof value === 'string') {
        try {
            return JSON.stringify(JSON.parse(value), null, 2);
        } catch {
            return value;
        }
    }

    return JSON.stringify(value, null, 2);
}
let mainToken = 0,
    debounce: ReturnType<typeof setTimeout> | undefined;
async function load() {
    clearTimeout(debounce);
    const token = ++mainToken;
    loading.value = true;
    error.value = '';
    rows.value = [];
    selected.value = [];

    try {
        const id = taskId.value.trim().replace(/\s*,\s*/g, ',');

        if (id && !/^\d+(,\d+)*$/.test(id)) {
            throw new Error('任务ID请填写数字，多个ID用英文逗号分隔');
        }

        const query = new URLSearchParams({
            page: String(page.value),
            limit: String(size.value),
        });

        if (id) {
            query.set('id', id);
        } else {
            query.set('pid', '0');
        }

        if (type.value !== 'all') {
            query.set('type', type.value);
        }

        if (state.value !== 'all') {
            query.set('state', state.value);
        }

        if (resource.value.trim()) {
            query.set('res', resource.value.trim());
        }

        const result = await apiRequest(`${endpoint}?${query}`);

        if (token !== mainToken) {
            return;
        }

        rows.value = extractCdnflyRows(result);
        total.value = extractCdnflyTotal(result, rows.value.length);
        seenTypes.value = [
            ...new Set([
                ...seenTypes.value,
                ...rows.value.map((row) => text(row.type, '')).filter(Boolean),
            ]),
        ];
        const last = Math.max(1, Math.ceil(total.value / size.value));

        if (page.value > last) {
            page.value = last;
        }
    } catch (e) {
        if (token === mainToken) {
            error.value = getErrorMessage(e);
            total.value = 0;
        }
    } finally {
        if (token === mainToken) {
            loading.value = false;
        }
    }
}
function search() {
    clearTimeout(debounce);

    if (page.value === 1) {
        void load();
    } else {
        page.value = 1;
    }
}
watch([type, state, size], search);
watch(page, load);
watch([taskId, resource], () => {
    ++mainToken;
    loading.value = true;
    rows.value = [];
    selected.value = [];
    error.value = '';
    clearTimeout(debounce);
    debounce = setTimeout(search, 300);
});
onMounted(load);
const subOpen = ref(false),
    parentId = ref(''),
    subPage = ref(1),
    subSize = ref(10),
    subTotal = ref(0),
    subRows = ref<CdnflyRecord[]>([]),
    subLoading = ref(false),
    subError = ref('');
let subToken = 0;
async function loadSub() {
    if (!subOpen.value) {
        return;
    }

    const token = ++subToken;
    subLoading.value = true;
    subRows.value = [];
    subError.value = '';

    try {
        const result = await apiRequest(
            `${endpoint}?${new URLSearchParams({ pid: parentId.value, page: String(subPage.value), limit: String(subSize.value) })}`,
        );

        if (token !== subToken) {
            return;
        }

        subRows.value = extractCdnflyRows(result);
        subTotal.value = extractCdnflyTotal(result, subRows.value.length);
        const last = Math.max(1, Math.ceil(subTotal.value / subSize.value));

        if (subPage.value > last) {
            subPage.value = last;
        }
    } catch (e) {
        if (token === subToken) {
            subError.value = getErrorMessage(e);
            subTotal.value = 0;
        }
    } finally {
        if (token === subToken) {
            subLoading.value = false;
        }
    }
}
watch(subPage, loadSub);
watch(subSize, () => {
    if (subPage.value === 1) {
        void loadSub();
    } else {
        subPage.value = 1;
    }
});
const outputOpen = ref(false),
    outputTitle = ref('结果'),
    output = ref('');
function showOutput(value: unknown, title = '结果') {
    output.value = content(value);
    outputTitle.value = title;
    outputOpen.value = true;
}
function details(row: CdnflyRecord) {
    if (!empty(row.ret)) {
        showOutput(row.ret, '任务详情');

        return;
    }

    parentId.value = String(row.id);

    if (subPage.value !== 1) {
        subPage.value = 1;
        subOpen.value = true;
    } else {
        subOpen.value = true;
        void loadSub();
    }
}
function closeSub(open: boolean) {
    if (busy.value) {
        return;
    }

    subOpen.value = open;

    if (!open) {
        ++subToken;
        subLoading.value = false;
    }
}
const cancelOpen = ref(false),
    cancelIds = ref<(string | number)[]>([]),
    cancelError = ref('');
const cancellableIds = computed(() =>
    selected.value.filter((id) =>
        rows.value.some(
            (row) => String(row.id) === String(id) && !cancelled(row),
        ),
    ),
);
function confirmCancel(ids: (string | number)[]) {
    cancelIds.value = [...ids];
    cancelError.value = '';
    cancelOpen.value = true;
}
async function cancel() {
    if (busy.value) {
        return;
    }

    busy.value = true;
    cancelError.value = '';
    const failed: (string | number)[] = [];

    for (const id of cancelIds.value) {
        try {
            await apiRequest(`${endpoint}/${id}`, {
                method: 'PUT',
                body: JSON.stringify({ enable: 0 }),
            });
        } catch (e) {
            failed.push(id);
            cancelError.value = getErrorMessage(e);
        }
    }

    await Promise.all([load(), loadSub()]);
    cancelIds.value = failed;
    selected.value = failed;
    cancelOpen.value = failed.length > 0;

    if (!failed.length) {
        toast.success('取消成功');
    }

    busy.value = false;
}
onUnmounted(() => {
    ++mainToken;
    ++subToken;
    clearTimeout(debounce);
});
</script>

<template>
    <div class="console-admin-tasks tasks-workspace min-w-0 p-4 md:p-6">
        <section
            class="console-panel rounded-xl border bg-card p-4 text-card-foreground md:p-5"
        >
            <div
                class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-4"
            >
                <Button
                    :disabled="busy || loading || !cancellableIds.length"
                    @click="confirmCancel(cancellableIds)"
                    ><CircleX class="mr-1 size-4" />取消任务</Button
                >
                <form
                    class="flex min-w-0 flex-wrap items-center gap-2"
                    @submit.prevent="search"
                >
                    <Select v-model="type" :disabled="busy"
                        ><SelectTrigger
                            class="w-full sm:w-48"
                            aria-label="任务类型"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent class="console-admin-tasks"
                            ><SelectItem value="all">所有类型</SelectItem
                            ><SelectItem
                                v-for="[key, label] in typeOptions"
                                :key="key"
                                :value="key"
                                >{{ label }}</SelectItem
                            ></SelectContent
                        ></Select
                    >
                    <Select v-model="state" :disabled="busy"
                        ><SelectTrigger
                            class="w-full sm:w-48"
                            aria-label="任务状态"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent class="console-admin-tasks"
                            ><SelectItem value="all">所有状态</SelectItem
                            ><SelectItem
                                v-for="(label, key) in states"
                                :key="key"
                                :value="key"
                                >{{ label }}</SelectItem
                            ><SelectItem
                                v-if="state !== 'all' && !states[state]"
                                :value="state"
                                >{{ state }}</SelectItem
                            ></SelectContent
                        ></Select
                    >
                    <div class="flex w-full min-w-0 sm:w-48">
                        <Label
                            for="tasks-id"
                            class="shrink-0 rounded-l-md border border-r-0 bg-muted/40 px-2"
                            >任务ID</Label
                        ><Input
                            id="tasks-id"
                            v-model="taskId"
                            :disabled="busy"
                            placeholder="任务ID"
                            class="min-w-0 rounded-l-none"
                        />
                    </div>
                    <div class="flex w-full min-w-0 sm:w-48">
                        <Label
                            for="tasks-res"
                            class="shrink-0 rounded-l-md border border-r-0 bg-muted/40 px-2"
                            >资源ID</Label
                        ><Input
                            id="tasks-res"
                            v-model="resource"
                            :disabled="busy"
                            placeholder="资源ID"
                            class="min-w-0 rounded-l-none"
                        />
                    </div>
                </form>
            </div>
            <Alert v-if="error" variant="destructive" class="mb-4"
                ><AlertDescription
                    >{{ error
                    }}<Button
                        size="sm"
                        variant="outline"
                        class="ml-2"
                        @click="load"
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <div class="task-table min-w-0">
                <ConsoleDataTable
                    v-model:selected="selected"
                    title="任务"
                    embedded
                    selectable
                    :selection-disabled="busy || loading"
                    :columns="columns"
                    :data="{ rows, total, page, pageSize: size, loading }"
                    :empty-text="error ? '任务加载失败' : '暂无数据'"
                >
                    <template #actions-col
                        ><col style="width: 130px"
                    /></template>
                    <template #cell-pry="{ row }"
                        ><Badge variant="outline" class="rounded-full">{{
                            text(row.pry)
                        }}</Badge></template
                    >
                    <template #cell-name="{ row }"
                        ><span
                            class="block max-w-44 truncate"
                            :title="text(row.name, '未命名任务')"
                            >{{ text(row.name, '未命名任务') }}</span
                        ></template
                    >
                    <template #cell-type="{ row }"
                        ><span
                            class="block max-w-40 truncate"
                            :title="text(row.type, '未分类')"
                            >{{ text(row.type, '未分类') }}</span
                        ></template
                    >
                    <template #cell-res="{ row }"
                        ><span
                            :class="
                                empty(row.res) ? 'text-muted-foreground' : ''
                            "
                            >{{ text(row.res, '未绑定') }}</span
                        ></template
                    >
                    <template #cell-depend="{ row }"
                        ><span
                            class="block max-w-28 truncate"
                            :title="text(row.depend, '无依赖')"
                            :class="
                                empty(row.depend) ? 'text-muted-foreground' : ''
                            "
                            >{{ text(row.depend, '无依赖') }}</span
                        ></template
                    >
                    <template #cell-start_at2="{ row }"
                        ><p data-typography="body" class="whitespace-nowrap">
                            {{ text(row.start_at2, '未开始') }}
                        </p>
                        <p
                            data-typography="helper"
                            v-if="!empty(row.end_at2)"
                            class="mt-1 whitespace-nowrap text-muted-foreground"
                        >
                            结束 {{ row.end_at2 }}
                        </p></template
                    >
                    <template #cell-duration="{ row }"
                        ><Badge variant="secondary" class="rounded-full">{{
                            duration(row)
                        }}</Badge></template
                    >
                    <template #cell-state="{ row }"
                        ><Badge
                            :variant="stateVariant(row)"
                            class="gap-1 rounded-full"
                            ><span class="size-1.5 rounded-full bg-current" />{{
                                stateLabel(row)
                            }}</Badge
                        >
                        <p
                            data-typography="helper"
                            v-if="!empty(row.progress)"
                            class="mt-1 text-muted-foreground"
                        >
                            进度：{{ row.progress }}
                        </p></template
                    >
                    <template #cell-err_times="{ row }"
                        ><span
                            :class="
                                Number(row.err_times) > 0
                                    ? 'text-destructive'
                                    : ''
                            "
                            >{{ text(row.err_times) }}</span
                        ></template
                    >
                    <template #row-actions="{ row }"
                        ><Button
                            size="sm"
                            variant="link"
                            :disabled="busy"
                            @click="details(row)"
                            >详情</Button
                        ><Button
                            size="sm"
                            variant="link"
                            :disabled="busy || cancelled(row)"
                            @click="confirmCancel([String(row.id)])"
                            >取消</Button
                        ></template
                    >
                </ConsoleDataTable>
            </div>
            <PackagePagination
                v-model:page="page"
                v-model:page-size="size"
                :total="total"
                :disabled="loading || busy || !!error"
                numbered
                edge-links
            />
        </section>
        <Dialog :open="subOpen" @update:open="closeSub"
            ><DialogScrollContent
                class="console-admin-tasks max-h-[95dvh] max-w-[1230px] grid-rows-[auto_minmax(0,1fr)_auto] gap-0 overflow-hidden p-0"
            >
                <DialogHeader class="border-b px-5 py-4"
                    ><DialogTitle>子任务详情</DialogTitle
                    ><DialogDescription class="sr-only"
                        >任务 {{ parentId }} 的子任务</DialogDescription
                    ></DialogHeader
                >
                <div class="min-h-0 min-w-0 overflow-y-auto p-4">
                    <Button
                        class="mb-4"
                        :disabled="subLoading || busy"
                        @click="loadSub"
                        ><RefreshCw class="mr-1 size-4" />刷新</Button
                    >
                    <Alert v-if="subError" variant="destructive" class="mb-3"
                        ><AlertDescription
                            >{{ subError
                            }}<Button
                                size="sm"
                                variant="outline"
                                class="ml-2"
                                @click="loadSub"
                                >重试</Button
                            ></AlertDescription
                        ></Alert
                    >
                    <div class="subtask-table min-w-0">
                        <ConsoleDataTable
                            title="子任务"
                            embedded
                            :columns="subColumns"
                            :data="{
                                rows: subRows,
                                total: subTotal,
                                page: subPage,
                                pageSize: subSize,
                                loading: subLoading,
                            }"
                            :empty-text="
                                subError ? '子任务加载失败' : '暂无子任务'
                            "
                        >
                            <template #actions-col
                                ><col style="width: 120px"
                            /></template>
                            <template #cell-res="{ row }"
                                ><span
                                    class="block max-w-28 truncate"
                                    :title="text(row.res, '未绑定')"
                                    >{{ text(row.res, '未绑定') }}</span
                                ></template
                            >
                            <template #cell-data="{ row }"
                                ><Button
                                    variant="link"
                                    size="inline"
                                    type="button"
                                    data-slot="console-link"
                                    v-if="!empty(row.data)"
                                    class="block max-w-32 truncate text-left hover:underline"
                                    :title="content(row.data)"
                                    :aria-label="`子任务 ${row.id} 数据`"
                                    @click="showOutput(row.data, '任务数据')"
                                >
                                    {{
                                        typeof row.data === 'string'
                                            ? row.data
                                            : content(row.data)
                                    }}</Button
                                ><span v-else>—</span></template
                            >
                            <template #cell-start_at2="{ row }">{{
                                text(row.start_at2, '未开始')
                            }}</template>
                            <template #cell-duration="{ row }">{{
                                duration(row)
                            }}</template>
                            <template #cell-state="{ row }"
                                ><Badge :variant="stateVariant(row)">{{
                                    stateLabel(row)
                                }}</Badge></template
                            >
                            <template #row-actions="{ row }"
                                ><Button
                                    variant="link"
                                    size="sm"
                                    @click="showOutput(row.ret)"
                                    >结果</Button
                                ><Button
                                    variant="link"
                                    size="sm"
                                    :disabled="busy || cancelled(row)"
                                    @click="confirmCancel([String(row.id)])"
                                    >取消</Button
                                ></template
                            >
                        </ConsoleDataTable>
                    </div>
                    <PackagePagination
                        v-model:page="subPage"
                        v-model:page-size="subSize"
                        :total="subTotal"
                        :disabled="subLoading || busy || !!subError"
                        numbered
                        edge-links
                    />
                </div>
                <DialogFooter class="border-t px-5 py-4"
                    ><Button
                        variant="outline"
                        :disabled="busy"
                        @click="closeSub(false)"
                        >关闭</Button
                    ></DialogFooter
                >
            </DialogScrollContent></Dialog
        >
        <Dialog v-model:open="outputOpen"
            ><DialogScrollContent
                class="console-admin-tasks max-h-[90dvh] max-w-2xl grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden"
                ><DialogHeader
                    ><DialogTitle>{{ outputTitle }}</DialogTitle
                    ><DialogDescription class="sr-only"
                        >任务执行数据或返回内容</DialogDescription
                    ></DialogHeader
                >
                <pre
                    class="min-h-0 overflow-auto text-sm break-words whitespace-pre-wrap"
                    >{{ output }}</pre
                >
                <DialogFooter
                    ><Button variant="outline" @click="outputOpen = false"
                        >关闭</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <Dialog
            :open="cancelOpen"
            @update:open="
                (value) => {
                    if (!busy) cancelOpen = value;
                }
            "
            ><DialogScrollContent
                class="console-admin-tasks"
                @interact-outside.prevent
                ><DialogHeader
                    ><DialogTitle>取消确认</DialogTitle
                    ><DialogDescription
                        >是否取消选中的
                        {{ cancelIds.length }} 个任务？</DialogDescription
                    ></DialogHeader
                ><Alert v-if="cancelError" variant="destructive"
                    ><AlertDescription>{{
                        cancelError
                    }}</AlertDescription></Alert
                ><DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="busy"
                        @click="cancelOpen = false"
                        >返回</Button
                    ><Button :disabled="busy" @click="cancel"
                        ><Spinner v-if="busy" class="mr-1" />确认取消</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
    </div>
</template>
