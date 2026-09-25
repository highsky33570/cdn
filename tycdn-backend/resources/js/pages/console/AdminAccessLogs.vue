<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { RefreshCw, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    accessFilterFields,
    accessLogColumns,
    accessLogParams,
    accessLogCell,
    accessJobData,
    accessJobState,
    accessHeaderText,
    decodeAccessBody,
    defaultAccessFilters,
} from '@/lib/accessLogs';
import type { AccessFilterKey, AccessLogFilters } from '@/lib/accessLogs';
import { apiRequest } from '@/lib/apiRequest';
import {
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnflyResponse';
import {
    listUserAccessLogs,
    listAccessLogJobs,
    getUserAccessLog,
    createFilteredAccessLogJob,
} from '@/lib/cdnUserApi';
import { formatDate, getErrorMessage, textValue } from '@/lib/formatters';
import { masterGet } from '@/lib/masterApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const props = withDefaults(defineProps<{ scope?: 'admin' | 'user' }>(), {
    scope: 'admin',
});
const userScope = computed(() => props.scope === 'user');
const columns = computed(() =>
    userScope.value
        ? accessLogColumns.slice(0, 18).map((column) => ({
              ...column,
              width: Math.round(column.width * 1.25),
          }))
        : accessLogColumns,
);
const filters = reactive(defaultAccessFilters());
const query = new URLSearchParams(usePage().url.split('?')[1] ?? '');

for (const { key } of accessFilterFields) {
    const value = query.get(key === 'host' ? 'domain' : key) ?? query.get(key);

    if (value !== null) {
        filters[key] = value;
    }
}

if (query.get('uri_match_type') === 'prefix') {
    filters.uri_match_type = 'prefix';
}

for (const key of ['start', 'end'] as const) {
    const value = query.get(key);

    if (value !== null) {
        filters[key] = value.replace(' ', 'T');
    }
}

const draft = reactive({ ...filters });
const active = ref<'query' | 'jobs'>(
    query.get('tab') === 'jobs' ? 'jobs' : 'query',
);
const initialQuickField =
    accessFilterFields.find(({ key }) => key === query.get('filter')) ??
    accessFilterFields.find(({ key }) => filters[key] !== '');
const quickType = ref<AccessFilterKey | 'timeRange' | 'uri_match_type'>(
    initialQuickField?.key ?? 'host',
);
const quickValue = ref(filters[quickType.value as AccessFilterKey]);
const advanced = ref(false);
const rows = ref<CdnflyRecord[]>([]),
    jobs = ref<CdnflyRecord[]>([]);
const logTotal = ref(0),
    jobTotal = ref(0),
    logPage = ref(1),
    jobPage = ref(1);
const logSize = ref(10),
    jobSize = ref(10);
const loading = ref(false),
    error = ref(''),
    applying = ref(false),
    downloading = ref<string | null>(null);
const total = computed(() =>
    active.value === 'query' ? logTotal.value : jobTotal.value,
);
const currentPage = computed(() =>
    active.value === 'query' ? logPage.value : jobPage.value,
);
const pageSize = computed({
    get: () => (active.value === 'query' ? logSize.value : jobSize.value),
    set: (value: number) => {
        if (active.value === 'query') {
            logSize.value = value;
        } else {
            jobSize.value = value;
        }
    },
});
const lastPage = computed(() =>
    Math.max(1, Math.ceil(total.value / pageSize.value)),
);

const tags = computed(() =>
    accessFilterFields
        .filter((field) => filters[field.key] !== '')
        .map((field) => ({
            key: field.key,
            label: field.label,
            value:
                field.key === 'cache_status'
                    ? filters.cache_status === 'HIT'
                        ? '命中'
                        : '未命中'
                    : filters[field.key],
        })),
);
let requestVersion = 0;

async function load(target = currentPage.value): Promise<void> {
    const version = ++requestVersion,
        tab = active.value;
    loading.value = true;
    error.value = '';

    try {
        if (tab === 'query') {
            const params = {
                ...accessLogParams(filters),
                page: target,
                limit: logSize.value,
            };
            const result = userScope.value
                ? await listUserAccessLogs(params)
                : await masterGet('access-log', params);

            if (version !== requestVersion) {
                return;
            }

            rows.value = extractCdnflyRows(result);
            logTotal.value = extractCdnflyTotal(result, rows.value.length);
            logPage.value = target;
        } else {
            const result = userScope.value
                ? await listAccessLogJobs({
                      page: target,
                      limit: jobSize.value,
                  })
                : await apiRequest(
                      `/api/admin/access-log-jobs?page=${target}&limit=${jobSize.value}`,
                  );

            if (version !== requestVersion) {
                return;
            }

            jobs.value = extractCdnflyRows(result);
            jobTotal.value = extractCdnflyTotal(result, jobs.value.length);
            jobPage.value = target;
        }
    } catch (e) {
        if (version !== requestVersion) {
            return;
        }

        error.value = getErrorMessage(e);

        if (tab === 'query') {
            rows.value = [];
            logTotal.value = 0;
        } else {
            jobs.value = [];
            jobTotal.value = 0;
        }
    } finally {
        if (version === requestVersion) {
            loading.value = false;
        }
    }
}
function switchTab(tab: 'query' | 'jobs'): void {
    if (active.value !== tab) {
        active.value = tab;
        void load();
    }
}
function clearFilters(): void {
    Object.assign(filters, defaultAccessFilters());
    Object.assign(draft, filters);
    quickValue.value = '';
    void load(1);
}
function removeFilter(key: AccessFilterKey): void {
    filters[key] = '';
    draft[key] = '';

    if (quickType.value === key) {
        quickValue.value = '';
    }

    void load(1);
}
function openAdvanced(): void {
    Object.assign(draft, filters);
    advanced.value = !advanced.value;
    error.value = '';
}
function applyAdvanced(): void {
    try {
        accessLogParams(draft);
        Object.assign(filters, draft);
        void load(1);
    } catch (e) {
        error.value = getErrorMessage(e);
    }
}
watch(quickType, (key) => {
    if (key === 'timeRange') {
        Object.assign(draft, filters);
        advanced.value = true;
    } else {
        quickValue.value = filters[key];
    }
});
function quickSearch(): void {
    if (quickType.value === 'timeRange') {
        applyAdvanced();

        return;
    }

    const next: AccessLogFilters = {
        ...filters,
        [quickType.value]: quickValue.value.trim(),
    };

    try {
        accessLogParams(next);
        Object.assign(filters, next);
        Object.assign(draft, next);
        void load(1);
    } catch (e) {
        error.value = getErrorMessage(e);
    }
}
function datePreset(days: number): void {
    const range = defaultAccessFilters();
    const start = new Date(range.start);
    start.setDate(start.getDate() - days + 1);
    draft.start = formatDate(start.toISOString()).replace(' ', 'T');
    draft.end = range.end;
}
async function applyDownload(): Promise<void> {
    applying.value = true;

    try {
        const params = accessLogParams(filters);

        if (userScope.value) {
            await createFilteredAccessLogJob(params);
        } else {
            await apiRequest('/api/admin/access-log-jobs', {
                method: 'POST',
                body: JSON.stringify(params),
            });
        }

        toast.success('申请成功，下载链接请到申请记录中等待获取');
        active.value = 'jobs';
        await load(1);
    } catch (e) {
        toast.error(getErrorMessage(e));
    } finally {
        applying.value = false;
    }
}
async function download(row: CdnflyRecord): Promise<void> {
    const id = textValue(row.id);

    if (!/^\d+$/.test(id)) {
        return;
    }

    downloading.value = id;

    try {
        const response = await fetch(
            userScope.value
                ? `/api/cdn/access-log-downloads/${id}`
                : `/api/admin/access-log-jobs/${id}/download`,
            {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/gzip, application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (
            !response.ok ||
            !/application\/(?:x-)?gzip|application\/octet-stream/.test(
                response.headers.get('Content-Type') ?? '',
            )
        ) {
            const body = await response.json().catch(() => null);

            throw new Error(body?.message || '日志文件尚未生成或已过期');
        }

        const url = URL.createObjectURL(await response.blob()),
            link = document.createElement('a');
        link.href = url;
        link.download = `access-log-${id}.gz`;
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
        toast.error(getErrorMessage(e));
    } finally {
        downloading.value = null;
    }
}

const detailOpen = ref(false),
    detailLoading = ref(false),
    detailError = ref('');
const detail = ref<CdnflyRecord>({}),
    detailTab = ref<'req_header' | 'resp_header' | 'req_body'>('req_header');
const showBase64 = ref(false);
let detailVersion = 0;
const detailText = computed(() => {
    if (
        userScope.value &&
        detailTab.value !== 'req_body' &&
        (!detail.value[detailTab.value] ||
            detail.value[detailTab.value] === '-')
    ) {
        return detailTab.value === 'req_header'
            ? '未开启记录请求头'
            : '未开启记录响应头';
    }

    return detailTab.value === 'req_body'
        ? showBase64.value
            ? textValue(detail.value.req_body)
            : decodeAccessBody(textValue(detail.value.req_body))
        : accessHeaderText(detail.value[detailTab.value]);
});
async function showDetail(row: CdnflyRecord): Promise<void> {
    const id = ++detailVersion;
    detail.value = {};
    detailTab.value = 'req_header';
    showBase64.value = false;
    detailError.value = '';
    detailOpen.value = true;
    detailLoading.value = true;

    try {
        const result = userScope.value
            ? await getUserAccessLog(textValue(row._id))
            : await apiRequest(
                  `/api/admin/access-logs/${encodeURIComponent(textValue(row._id))}`,
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
onMounted(() => void load());
onUnmounted(() => {
    requestVersion++;
    detailVersion++;
});
</script>

<template>
    <div
        class="console-page min-w-0 p-4 md:p-6"
        :class="{ 'user-access-log': userScope }"
    >
        <section
            class="access-log-card min-w-0 rounded-xl border bg-card p-4 text-card-foreground shadow-sm"
            aria-label="访问日志"
        >
            <div
                class="mb-4 flex gap-1"
                role="tablist"
                aria-label="访问日志分类"
            >
                <button
                    v-for="tab in [
                        { key: 'query', label: '日志查询' },
                        { key: 'jobs', label: '申请记录' },
                    ] as const"
                    :id="`access-tab-${tab.key}`"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="active === tab.key"
                    aria-controls="access-log-panel"
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
                id="access-log-panel"
                role="tabpanel"
                :aria-labelledby="`access-tab-${active}`"
            >
                <template v-if="active === 'query'">
                    <div
                        class="access-toolbar mb-3 flex flex-wrap items-center justify-between gap-3"
                    >
                        <form
                            class="flex max-w-full items-stretch"
                            @submit.prevent="quickSearch"
                        >
                            <SelectField
                                v-model="quickType"
                                aria-label="搜索类型"
                                class="w-28 shrink-0 rounded-l-md border border-r-0 bg-muted px-2 text-sm text-muted-foreground"
                            >
                                <SelectOption
                                    v-for="field in accessFilterFields"
                                    :key="field.key"
                                    :value="field.key"
                                >
                                    {{ field.label }}
                                </SelectOption>
                                <SelectOption value="timeRange"
                                    >时间范围</SelectOption
                                >
                                <SelectOption value="uri_match_type">
                                    URI搜索模式
                                </SelectOption>
                            </SelectField>
                            <SelectField
                                v-if="
                                    quickType === 'cache_status' ||
                                    quickType === 'uri_match_type'
                                "
                                v-model="quickValue"
                                aria-label="搜索值"
                                class="h-8 w-44 min-w-0 border bg-background px-2 text-sm"
                            >
                                <template v-if="quickType === 'cache_status'"
                                    ><SelectOption value="">全部</SelectOption>
                                    <SelectOption value="HIT"
                                        >命中</SelectOption
                                    >
                                    <SelectOption value="MISS">
                                        未命中
                                    </SelectOption></template
                                ><template v-else
                                    ><SelectOption value="exact"
                                        >精确</SelectOption
                                    >
                                    <SelectOption value="prefix">
                                        前缀
                                    </SelectOption></template
                                >
                            </SelectField>
                            <Input
                                v-else
                                v-model="quickValue"
                                aria-label="搜索值"
                                class="h-8 w-44 min-w-0 rounded-none"
                                :placeholder="
                                    quickType === 'timeRange'
                                        ? '在高级搜索中选择时间'
                                        : accessFilterFields.find(
                                              (field) =>
                                                  field.key === quickType,
                                          )?.placeholder
                                "
                                :disabled="quickType === 'timeRange'"
                            />
                            <Button
                                type="submit"
                                size="sm"
                                class="rounded-l-none"
                                :disabled="loading"
                                >查询</Button
                            >
                        </form>
                        <div class="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="applying || loading"
                                @click="applyDownload"
                                ><Spinner
                                    v-if="applying"
                                    data-icon="inline-start"
                                />申请下载</Button
                            ><Button
                                size="sm"
                                variant="link"
                                :aria-expanded="advanced"
                                @click="openAdvanced"
                                >高级搜索</Button
                            >
                        </div>
                    </div>

                    <form
                        v-if="advanced"
                        class="mb-4 rounded-lg border bg-muted/20 p-4"
                        aria-label="高级搜索"
                        @submit.prevent="applyAdvanced"
                    >
                        <div
                            class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                        >
                            <div class="grid gap-1.5">
                                <Label for="access-start">开始时间</Label
                                ><DatePicker
                                    id="access-start"
                                    v-model="draft.start"
                                    type="datetime-local"
                                    step="1"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="access-end">结束时间</Label
                                ><DatePicker
                                    id="access-end"
                                    v-model="draft.end"
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
                                    >{{
                                        days === 1 ? '今天' : `近${days}天`
                                    }}</Button
                                >
                            </div>
                            <div
                                v-for="field in accessFilterFields"
                                :key="field.key"
                                class="grid gap-1.5"
                            >
                                <Label :for="`access-${field.key}`">{{
                                    field.label
                                }}</Label>
                                <SelectField
                                    v-if="field.key === 'cache_status'"
                                    :id="`access-${field.key}`"
                                    v-model="draft.cache_status"
                                    class="h-8 rounded-md border bg-background px-2 text-sm"
                                >
                                    <SelectOption value="">全部</SelectOption>
                                    <SelectOption value="HIT"
                                        >命中</SelectOption
                                    >
                                    <SelectOption value="MISS"
                                        >未命中</SelectOption
                                    >
                                </SelectField>
                                <div
                                    data-slot="console-input-group"
                                    v-else-if="field.key === 'req_uri'"
                                    class="flex min-w-0"
                                >
                                    <SelectField
                                        v-model="draft.uri_match_type"
                                        aria-label="URI匹配方式"
                                        class="w-20 shrink-0 rounded-l-md border border-r-0 bg-background px-2 text-sm"
                                    >
                                        <SelectOption value="exact"
                                            >精确</SelectOption
                                        >
                                        <SelectOption value="prefix">
                                            前缀
                                        </SelectOption></SelectField
                                    ><Input
                                        :id="`access-${field.key}`"
                                        v-model="draft.req_uri"
                                        class="min-w-0 rounded-l-none"
                                        :placeholder="field.placeholder"
                                    />
                                </div>
                                <Input
                                    v-else
                                    :id="`access-${field.key}`"
                                    v-model="draft[field.key]"
                                    :placeholder="field.placeholder"
                                />
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button size="sm" type="submit" :disabled="loading"
                                >搜索</Button
                            ><Button
                                size="sm"
                                type="button"
                                variant="outline"
                                @click="clearFilters"
                                >重置</Button
                            >
                        </div>
                    </form>
                    <div
                        class="mb-3 flex flex-wrap items-center gap-2 text-xs"
                        aria-label="已应用筛选"
                    >
                        <button
                            data-slot="console-action"
                            type="button"
                            class="rounded border px-2 py-1 text-muted-foreground hover:text-primary"
                            @click="
                                Object.assign(draft, filters);
                                advanced = true;
                            "
                        >
                            时间范围：{{ filters.start.replace('T', ' ') }} -
                            {{ filters.end.replace('T', ' ') }}
                        </button>
                        <span
                            v-for="tag in tags"
                            :key="tag.key"
                            class="inline-flex max-w-full items-center gap-2 rounded border px-2 py-1 text-muted-foreground"
                            ><span class="truncate"
                                >{{ tag.label }}：{{ tag.value
                                }}{{
                                    tag.key === 'req_uri'
                                        ? `（${filters.uri_match_type === 'prefix' ? '前缀' : '精确'}）`
                                        : ''
                                }}</span
                            ><button
                                type="button"
                                :aria-label="`移除${tag.label}筛选`"
                                class="shrink-0 hover:text-foreground"
                                @click="removeFilter(tag.key)"
                            >
                                <X class="size-3" /></button
                        ></span>
                        <Button
                            variant="link"
                            size="sm"
                            class="h-auto p-0"
                            @click="clearFilters"
                            >清除</Button
                        >
                    </div>
                </template>
                <div v-else class="access-refresh mb-3 flex justify-end">
                    <Button size="sm" :disabled="loading" @click="load()"
                        ><RefreshCw data-icon="inline-start" />刷新</Button
                    >
                </div>

                <div class="max-w-full overflow-x-auto" :aria-busy="loading">
                    <table
                        v-if="active === 'query'"
                        class="access-table access-query-table table-fixed text-left text-sm"
                        :style="{
                            width: `${columns.reduce((sum, col) => sum + col.width, 130)}px`,
                        }"
                    >
                        <caption class="sr-only">
                            访问日志查询
                        </caption>
                        <colgroup>
                            <col
                                v-for="col in columns"
                                :key="col.key"
                                :style="{ width: `${col.width}px` }"
                            />
                            <col style="width: 130px" />
                        </colgroup>
                        <thead class="bg-muted/40 text-muted-foreground">
                            <tr>
                                <th v-for="col in columns" :key="col.key">
                                    {{ col.label }}
                                </th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="error">
                                <td
                                    :colspan="columns.length + 1"
                                    class="text-destructive"
                                >
                                    <span role="alert">{{ error }}</span
                                    ><Button
                                        variant="link"
                                        size="sm"
                                        @click="load()"
                                        >重试</Button
                                    >
                                </td>
                            </tr>
                            <tr v-else-if="loading">
                                <td :colspan="columns.length + 1" class="h-24">
                                    <Spinner /><span class="sr-only"
                                        >加载中</span
                                    >
                                </td>
                            </tr>
                            <template v-else
                                ><tr
                                    v-for="(row, index) in rows"
                                    :key="textValue(row._id) || index"
                                >
                                    <td
                                        v-for="col in columns"
                                        :key="col.key"
                                        :title="accessLogCell(row, col.key)"
                                    >
                                        <span
                                            :class="
                                                userScope &&
                                                [
                                                    'host',
                                                    'tls_fp',
                                                    'country',
                                                    'isp',
                                                    'sip',
                                                ].includes(col.key)
                                                    ? 'block break-words whitespace-normal'
                                                    : 'block truncate'
                                            "
                                            >{{
                                                accessLogCell(row, col.key)
                                            }}</span
                                        >
                                    </td>
                                    <td>
                                        <Button
                                            variant="link"
                                            size="sm"
                                            class="h-auto p-0"
                                            :disabled="!row._id"
                                            @click="showDetail(row)"
                                            >查看更多</Button
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!rows.length">
                                    <td
                                        :colspan="columns.length + 1"
                                        class="h-24 text-muted-foreground"
                                    >
                                        暂无数据
                                    </td>
                                </tr></template
                            >
                        </tbody>
                    </table>
                    <table
                        v-else
                        class="access-table w-full min-w-[1100px] text-left text-sm"
                    >
                        <caption class="sr-only">
                            申请记录
                        </caption>
                        <thead class="bg-muted/40 text-muted-foreground">
                            <tr>
                                <th>JobId / TaskId</th>
                                <th>申请时间</th>
                                <th>日志时间</th>
                                <th>日志域名</th>
                                <th>状态</th>
                                <th>进度</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="error">
                                <td
                                    colspan="7"
                                    class="h-24 text-center text-destructive"
                                >
                                    <span role="alert">{{ error }}</span
                                    ><Button
                                        variant="link"
                                        size="sm"
                                        @click="load()"
                                        >重试</Button
                                    >
                                </td>
                            </tr>
                            <tr v-else-if="loading">
                                <td colspan="7" class="h-24">
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <template v-else>
                                <tr
                                    v-for="row in jobs"
                                    :key="textValue(row.id)"
                                >
                                    <td>
                                        {{ row.id ?? '-' }} /
                                        {{ row.task_id ?? '-' }}
                                    </td>
                                    <td class="whitespace-nowrap">
                                        {{ formatDate(row.create_at2) }}
                                    </td>
                                    <td class="whitespace-nowrap">
                                        {{
                                            textValue(
                                                accessJobData(row).start,
                                            ) || '-'
                                        }}
                                        -
                                        {{
                                            textValue(accessJobData(row).end) ||
                                            '-'
                                        }}
                                    </td>
                                    <td>{{ accessJobData(row).host ?? '' }}</td>
                                    <td
                                        :class="
                                            row.state === 'failed'
                                                ? 'text-destructive'
                                                : row.state === 'done'
                                                  ? 'text-emerald-600 dark:text-emerald-400'
                                                  : 'text-muted-foreground'
                                        "
                                    >
                                        {{ accessJobState(row.state) }}
                                    </td>
                                    <td>{{ row.progress ?? '-' }}</td>
                                    <td>
                                        <Button
                                            variant="link"
                                            size="sm"
                                            class="h-auto p-0"
                                            :disabled="downloading !== null"
                                            @click="download(row)"
                                            >{{
                                                downloading === String(row.id)
                                                    ? '下载中…'
                                                    : '下载'
                                            }}</Button
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!jobs.length">
                                    <td
                                        colspan="7"
                                        class="h-24 text-center text-muted-foreground"
                                    >
                                        暂无数据
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <ConsolePagination
                    aria-label="访问日志分页"
                    :total="total"
                    :page="currentPage"
                    :previous-disabled="loading || currentPage <= 1"
                    :next-disabled="loading || currentPage >= lastPage"
                    @previous="load(currentPage - 1)"
                    @next="load(currentPage + 1)"
                />
            </div>
        </section>
        <Dialog v-model:open="detailOpen"
            ><DialogContent
                class="max-h-[85vh] overflow-y-auto sm:max-w-2xl"
                :class="{ 'user-access-detail': userScope }"
                ><DialogHeader
                    ><DialogTitle>更多日志</DialogTitle
                    ><DialogDescription :class="{ 'sr-only': userScope }"
                        >请求头、响应头与请求体</DialogDescription
                    ></DialogHeader
                >
                <div
                    class="flex flex-wrap gap-2"
                    role="tablist"
                    aria-label="日志详情"
                >
                    <button
                        v-for="tab in [
                            { key: 'req_header', label: '请求头' },
                            { key: 'resp_header', label: '响应头' },
                            { key: 'req_body', label: '请求体' },
                        ] as const"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="detailTab === tab.key"
                        class="rounded px-3 py-2 text-sm"
                        :class="
                            detailTab === tab.key
                                ? 'bg-primary/10 text-primary'
                                : 'text-muted-foreground'
                        "
                        @click="detailTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <Spinner v-if="detailLoading" />
                <p
                    v-else-if="detailError"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ detailError }}
                </p>
                <pre
                    v-else
                    class="max-h-96 overflow-auto rounded-md border bg-muted/20 p-3 text-xs break-all whitespace-pre-wrap"
                    >{{ detailText }}</pre
                >
                <label
                    v-if="
                        detailTab === 'req_body' &&
                        detail.req_body &&
                        !detailLoading
                    "
                    class="flex items-center gap-2 text-sm"
                    ><Checkbox
                        v-model="showBase64"
                    />转为base64，用于复制二进制数据</label
                ><DialogFooter
                    ><Button variant="outline" @click="detailOpen = false"
                        >关闭</Button
                    ></DialogFooter
                >
            </DialogContent></Dialog
        >
    </div>
</template>

<style scoped>
.access-table th {
    padding: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}
.access-table td {
    padding: 0.875rem 0.75rem;
}
.access-table tr {
    border-bottom: 1px solid var(--border);
}
.access-query-table th,
.access-query-table td {
    border: 1px solid var(--border);
}
.user-access-log .access-log-card {
    border-radius: 0;
    border: 0;
    box-shadow: none;
}
.user-access-log [role='tablist'],
.user-access-detail [role='tablist'] {
    gap: 1.25rem;
    border-bottom: 1px solid var(--border);
    margin-bottom: 1.25rem;
}
.user-access-log [role='tab'],
.user-access-detail [role='tab'] {
    border-radius: 0;
    border-bottom: 2px solid transparent;
    background: transparent;
    padding: 0.75rem 1.25rem;
    margin-bottom: -1px;
    font-weight: 400;
    font-size: 1rem;
}
.user-access-log [role='tab'][aria-selected='true'],
.user-access-detail [role='tab'][aria-selected='true'] {
    border-bottom-color: var(--primary);
    color: var(--primary);
}
.user-access-log .access-toolbar,
.user-access-log .access-refresh,
.user-access-log nav {
    justify-content: flex-start;
}
.user-access-log
    .access-toolbar
    form
    :deep([data-slot='select-trigger']):first-child {
    width: 5rem;
}
.user-access-log .access-toolbar form input {
    width: 13rem;
    flex: 1 1 auto;
}
.user-access-log .access-toolbar input,
.user-access-log .access-toolbar button,
.user-access-log .access-toolbar :deep([data-slot='select-trigger']),
.user-access-log .access-refresh button,
.user-access-log nav button,
.user-access-log nav :deep([data-slot='select-trigger']) {
    height: 2.5rem;
    font-size: 1rem;
}
.user-access-log nav button {
    min-width: 2.5rem;
}
.user-access-log nav {
    margin-top: 1.5rem;
}
.user-access-log .access-table {
    font-size: 1rem;
}
.user-access-log .access-table th {
    height: 3rem;
    padding: 0.75rem 1.375rem;
}
.user-access-log .access-table td {
    height: 3.75rem;
    padding: 0.5rem 1.375rem;
    line-height: 1.625;
}
.user-access-log .access-table td[colspan] {
    height: 3.75rem;
}
.user-access-log [aria-busy] {
    scrollbar-color: #909090 var(--muted);
    scrollbar-width: auto;
    overflow-x: scroll;
}
.user-access-log .access-table:not(.access-query-table) {
    min-width: 1450px;
}
.user-access-log .access-table,
.user-access-log nav,
.user-access-log [role='tab'],
.user-access-detail [role='tab'] {
    font-size: 16px;
}
.user-access-log .access-table th {
    height: 48px;
}
.user-access-log .access-table td,
.user-access-log .access-table td[colspan] {
    height: 60px;
}
.user-access-log [aria-label='已应用筛选'] {
    font-size: 14px;
}
.user-access-log .access-toolbar input,
.user-access-log .access-toolbar button,
.user-access-log .access-toolbar :deep([data-slot='select-trigger']),
.user-access-log .access-refresh button,
.user-access-log nav button,
.user-access-log nav :deep([data-slot='select-trigger']) {
    height: 40px;
    font-size: 16px;
}
.user-access-log .access-toolbar form input,
.user-access-log
    .access-toolbar
    form
    :deep([data-slot='select-trigger']):not(:first-child) {
    border-radius: 0;
}
.user-access-log .access-toolbar form button {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}
.user-access-detail {
    gap: 0;
    padding: 0;
    border-radius: 0.375rem;
}
.user-access-detail > :deep([data-slot='dialog-header']) {
    padding: 1.25rem;
    border-bottom: 1px solid var(--border);
}
.user-access-detail [role='tablist'] {
    margin: 1rem 1rem 0;
}
.user-access-detail pre {
    margin: 0;
    padding: 1.25rem 1rem;
    border: 0;
    background: transparent;
    font-family: inherit;
    font-size: 1rem;
    line-height: 1.65;
}
.user-access-detail > label {
    padding: 0 1rem 1rem;
}
.user-access-detail > :deep([data-slot='dialog-footer']) {
    padding: 1rem;
    border-top: 1px solid var(--border);
}
@media (max-width: 640px) {
    .user-access-log .access-log-card {
        padding: 0.75rem;
    }
    .user-access-log .access-toolbar form {
        width: 100%;
    }
    .user-access-log .access-toolbar form input {
        width: 0;
    }
}
</style>
