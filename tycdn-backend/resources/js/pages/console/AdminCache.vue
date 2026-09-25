<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    Copy,
    RefreshCw,
    Search,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import CheckboxField from '@/components/ui/checkbox/CheckboxField.vue';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { apiRequest } from '@/lib/apiRequest';
import {
    cacheModes,
    cacheUrls,
    validCacheUrl,
    cacheJobPayload,
    cacheJobQuery,
    cacheJobStatus,
    cacheJobSummary,
    cacheJobUrl,
} from '@/lib/cacheJobs';
import type { CacheMode, CacheJobPayload } from '@/lib/cacheJobs';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import { masterGet } from '@/lib/masterApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const tab = ref('submit'),
    mode = ref<CacheMode>('clean_url'),
    input = ref(''),
    submitting = ref(false),
    submitted = ref(false),
    formError = ref('');
const currentMode = computed(
    () => cacheModes.find((item) => item.value === mode.value)!,
);
const urls = computed(() => cacheUrls(input.value));
const invalidCount = computed(
    () => urls.value.filter((url) => !validCacheUrl(url)).length,
);
const quota = ref<{ total: number | null; used: number | null }>({
        total: null,
        used: null,
    }),
    quotaLoading = ref(false),
    quotaError = ref('');
const remaining = computed(() =>
    quota.value.total === null || quota.value.used === null
        ? null
        : Math.max(0, quota.value.total - quota.value.used),
);
const overQuota = computed(
    () =>
        quota.value.total !== null &&
        quota.value.total > 0 &&
        remaining.value !== null &&
        urls.value.length > remaining.value,
);
const usagePercent = computed(() =>
    quota.value.total && quota.value.used !== null
        ? Math.min(
              100,
              Math.round((quota.value.used / quota.value.total) * 100),
          )
        : 0,
);
const inputHint = computed(() =>
    invalidCount.value
        ? 'URL 需要以 http:// 或 https:// 开头，且不能包含空格'
        : urls.value.length > 1000
          ? '单次最多提交 1000 个任务'
          : overQuota.value
            ? '输入条数超过今日剩余限额'
            : mode.value === 'clean_dir'
              ? '目录刷新建议填写以 / 结尾的目录地址'
              : '支持批量粘贴，空行会自动忽略',
);
const inputInvalid = computed(
    () => invalidCount.value > 0 || overQuota.value || urls.value.length > 1000,
);
const submitDisabled = computed(
    () => !urls.value.length || inputInvalid.value || submitting.value,
);
const submitState = computed(() =>
    submitting.value
        ? '提交中'
        : formError.value
          ? '提交失败'
          : invalidCount.value
            ? '需修正'
            : overQuota.value || urls.value.length > 1000
              ? '已超限'
              : urls.value.length
                ? '可提交'
                : submitted.value
                  ? '已提交'
                  : '待输入',
);
const rows = ref<CdnflyRecord[]>([]),
    selected = ref<number[]>([]),
    page = ref(1),
    pageSize = ref(10),
    total = ref(0),
    loading = ref(false),
    listError = ref(''),
    typeFilter = ref(''),
    keyword = ref('');
const allSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selected.value.includes(Number(row.id))),
);
const summary = computed(() => cacheJobSummary(rows.value));
const summaryItems = [
    { key: 'all', label: '全部' },
    { key: 'done', label: '完成' },
    { key: 'failed', label: '失败' },
    { key: 'process', label: '处理中' },
] as const;
let quotaRequest = 0,
    listRequest = 0;
const detail = ref<CdnflyRecord | null>(null),
    detailKind = ref<'reason' | 'progress'>('reason');
const detailText = computed(() => {
    const row = detail.value;

    if (!row) {
        return '';
    }

    const value =
        detailKind.value === 'progress'
            ? row.progress || cacheJobStatus(row).label
            : row.ret ||
              row.msg ||
              row.error ||
              row.result ||
              row.progress ||
              '暂无错误详情';

    return typeof value === 'object'
        ? JSON.stringify(value, null, 2)
        : String(value);
});
const errorMessage = (error: unknown) =>
    error instanceof Error ? error.message : '请求失败，请重试';
onMounted(() => void loadQuota());
onUnmounted(() => {
    quotaRequest++;
    listRequest++;
});
function today() {
    const date = new Date();

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
async function loadQuota() {
    const request = ++quotaRequest;
    quotaLoading.value = true;
    quotaError.value = '';
    quota.value = { total: null, used: null };

    try {
        const data = await apiRequest<{
            total: number | null;
            used: number | null;
        }>(
            `/api/admin/cache-quota?${new URLSearchParams({ type: mode.value, start: today() })}`,
        );

        if (request === quotaRequest) {
            quota.value = data;
        }
    } catch (error) {
        if (request === quotaRequest) {
            quotaError.value = errorMessage(error);
        }
    } finally {
        if (request === quotaRequest) {
            quotaLoading.value = false;
        }
    }
}
function changeMode(value: CacheMode) {
    if (mode.value === value) {
        return;
    }

    mode.value = value;
    formError.value = '';
    submitted.value = false;
    void loadQuota();
}
function changeTab(value: string) {
    tab.value = value;

    if (value === 'history') {
        void loadJobs(1);
    } else {
        void loadQuota();
    }
}
async function postJobs(payload: CacheJobPayload[]) {
    await apiRequest('/api/admin/workspace/cache-jobs', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}
async function submit() {
    if (submitDisabled.value) {
        return;
    }

    submitting.value = true;
    formError.value = '';

    try {
        await postJobs(
            urls.value.map((url) => ({ type: mode.value, data: { url } })),
        );
        input.value = '';
        submitted.value = true;
        toast.success('提交成功，请到操作记录里查看进度。');
        void loadQuota();
    } catch (error) {
        formError.value = errorMessage(error);
    } finally {
        submitting.value = false;
    }
}
async function loadJobs(target = page.value) {
    const request = ++listRequest;
    loading.value = true;
    listError.value = '';
    selected.value = [];
    page.value = target;

    try {
        const data = await masterGet(
            'cache-jobs',
            cacheJobQuery(
                typeFilter.value,
                keyword.value,
                page.value,
                pageSize.value,
            ),
        );

        if (request !== listRequest) {
            return;
        }

        rows.value = extractCdnflyRows(data);
        total.value = extractCdnflyTotal(data, rows.value.length);

        if (page.value > 1 && !rows.value.length) {
            void loadJobs(Math.max(1, Math.ceil(total.value / pageSize.value)));

            return;
        }

        if (detail.value) {
            const updated = rows.value.find(
                (row) => row.id === detail.value?.id,
            );

            if (updated) {
                detail.value = updated;
            }
        }
    } catch (error) {
        if (request === listRequest) {
            listError.value = errorMessage(error);
            rows.value = [];
            total.value = 0;
        }
    } finally {
        if (request === listRequest) {
            loading.value = false;
        }
    }
}
function clearFilters() {
    typeFilter.value = '';
    keyword.value = '';
    void loadJobs(1);
}
function toggle(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((value) => value !== id)
        : [...selected.value, id];
}
async function resubmit(
    jobs = rows.value.filter((row) => selected.value.includes(Number(row.id))),
) {
    if (!jobs.length || submitting.value) {
        return;
    }

    listError.value = '';
    submitting.value = true;

    try {
        const payload = jobs.map(cacheJobPayload);
        await postJobs(payload);
        toast.success('重新提交成功');
        await loadJobs();
        void loadQuota();
    } catch (error) {
        listError.value = errorMessage(error);
    } finally {
        submitting.value = false;
    }
}
async function copy(url: string) {
    try {
        await navigator.clipboard.writeText(url);
        toast.success('已复制');
    } catch {
        toast.error('复制失败');
    }
}
function showDetail(row: CdnflyRecord, kind: 'reason' | 'progress') {
    detail.value = row;
    detailKind.value = kind;
}
</script>

<template>
    <div class="p-3 md:p-5">
        <section class="cache-workspace rounded-xl border bg-card shadow-sm">
            <nav class="tabs" role="tablist" aria-label="刷新预热">
                <button
                    role="tab"
                    :aria-selected="tab === 'submit'"
                    :disabled="submitting"
                    @click="changeTab('submit')"
                >
                    刷新预热</button
                ><button
                    role="tab"
                    :aria-selected="tab === 'history'"
                    :disabled="submitting"
                    @click="changeTab('history')"
                >
                    操作记录
                </button>
            </nav>
            <div v-if="tab === 'submit'" role="tabpanel" class="submit-layout">
                <div class="main-column">
                    <section class="mode-panel inset">
                        <div class="section-head">
                            <h2 class="marked">操作类型</h2>
                            <span class="current-mode"
                                >当前：{{ currentMode.label }}</span
                            >
                        </div>
                        <p class="muted helper">
                            选择任务类型后会刷新对应的今日额度。
                        </p>
                        <div
                            class="mode-options"
                            role="group"
                            aria-label="操作类型"
                        >
                            <button
                                v-for="item in cacheModes"
                                :key="item.value"
                                :aria-pressed="mode === item.value"
                                :disabled="submitting"
                                @click="changeMode(item.value)"
                            >
                                {{ item.label }}
                            </button>
                        </div>
                    </section>
                    <form class="url-panel" @submit.prevent="submit">
                        <div class="section-head">
                            <label for="admin-cache-urls">{{
                                currentMode.title
                            }}</label
                            ><span class="muted"
                                >已输入 <b>{{ urls.length }}</b> 条</span
                            >
                        </div>
                        <p class="helper muted">{{ currentMode.helper }}</p>
                        <Textarea
                            id="admin-cache-urls"
                            v-model="input"
                            :placeholder="currentMode.placeholder"
                            :disabled="submitting"
                            :aria-invalid="inputInvalid"
                            spellcheck="false"
                            @input="
                                submitted = false;
                                formError = '';
                            "
                        />
                        <p
                            class="input-hint inset"
                            :class="{ 'error-text': inputInvalid }"
                        >
                            <span class="dot">●</span> {{ inputHint }}
                        </p>
                        <p v-if="formError" role="alert" class="error-text">
                            {{ formError }}
                        </p>
                        <div class="submit-footer">
                            <span class="muted"
                                >本次将提交
                                <b>{{ urls.length }}</b> 个任务</span
                            >
                            <div>
                                <button
                                    type="button"
                                    :disabled="!input || submitting"
                                    @click="
                                        input = '';
                                        formError = '';
                                        submitted = false;
                                    "
                                >
                                    清空</button
                                ><button
                                    class="primary"
                                    :disabled="submitDisabled"
                                >
                                    {{ submitting ? '提交中…' : '提交任务' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <aside>
                    <section
                        class="quota-panel inset"
                        :aria-busy="quotaLoading"
                    >
                        <h2>今日额度</h2>
                        <div class="quota-value">
                            <strong>{{
                                quotaLoading ? '…' : (remaining ?? '—')
                            }}</strong
                            ><span class="muted"
                                >剩余 /
                                {{
                                    quotaLoading ? '…' : quota.total || '—'
                                }}</span
                            >
                        </div>
                        <div class="quota-caption muted">
                            <span
                                >已用
                                {{ quotaLoading ? '…' : (quota.used ?? '—') }}
                                条</span
                            ><span
                                >总量
                                {{
                                    quotaLoading ? '…' : (quota.total ?? '—')
                                }}</span
                            >
                        </div>
                        <div
                            class="usage-track"
                            role="progressbar"
                            aria-label="今日额度使用率"
                            :aria-valuenow="usagePercent"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <span :style="{ width: usagePercent + '%' }" />
                        </div>
                        <p v-if="quotaError" role="alert" class="error-text">
                            {{ quotaError }}
                            <button class="link" @click="loadQuota">
                                重试
                            </button>
                        </p>
                    </section>
                    <section class="state-panel inset">
                        <h2>提交状态</h2>
                        <span
                            class="status-pill"
                            :class="
                                inputInvalid || formError
                                    ? 'danger'
                                    : urls.length || submitted
                                      ? 'success'
                                      : 'muted'
                            "
                            >● {{ submitState }}</span
                        >
                        <p class="muted helper">
                            提交成功后可在操作记录里查看进度。
                        </p>
                    </section>
                </aside>
            </div>
            <div
                v-else
                role="tabpanel"
                class="history-panel"
                :aria-busy="loading"
            >
                <div class="history-toolbar">
                    <button
                        :class="{ primary: selected.length > 0 }"
                        :disabled="!selected.length || submitting || loading"
                        @click="resubmit()"
                    >
                        <RefreshCw />{{
                            submitting ? '提交中…' : '重新提交选中'
                        }}
                    </button>
                    <form class="history-filters" @submit.prevent="loadJobs(1)">
                        <SelectField
                            v-model="typeFilter"
                            aria-label="任务类型"
                            :disabled="submitting"
                            @change="loadJobs(1)"
                        >
                            <SelectOption value="">所有类型</SelectOption>
                            <SelectOption
                                v-for="item in cacheModes"
                                :key="item.value"
                                :value="item.value"
                            >
                                {{ item.label }}
                            </SelectOption>
                        </SelectField>
                        <div class="keyword">
                            <Input
                                v-model="keyword"
                                aria-label="搜索 URL 或域名"
                                placeholder="搜索 URL 或域名"
                                :disabled="submitting"
                            /><button
                                type="submit"
                                aria-label="搜索"
                                :disabled="submitting"
                            >
                                <Search />
                            </button>
                        </div>
                        <button
                            type="button"
                            class="link"
                            :disabled="submitting"
                            @click="clearFilters"
                        >
                            清空
                        </button>
                    </form>
                </div>
                <p v-if="listError" role="alert" class="error-text list-error">
                    {{ listError }}
                    <button
                        class="link"
                        :disabled="submitting"
                        @click="loadJobs()"
                    >
                        刷新记录
                    </button>
                </p>
                <div class="history-summary inset">
                    <div>
                        <span
                            v-for="item in summaryItems"
                            :key="item.key"
                            class="summary-pill"
                            >{{ item.label }}
                            <b
                                :class="
                                    item.key === 'failed'
                                        ? 'error-text'
                                        : item.key === 'done'
                                          ? 'success-text'
                                          : ''
                                "
                                >{{ summary[item.key] }}</b
                            ></span
                        >
                    </div>
                    <span class="muted"
                        >当前页 {{ rows.length }} 条 / 共 {{ total }} 条</span
                    >
                </div>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th class="selection">
                                    <CheckboxField
                                        aria-label="选择本页全部"
                                        :checked="allSelected"
                                        :disabled="
                                            !rows.length ||
                                            loading ||
                                            submitting
                                        "
                                        @change="
                                            selected = allSelected
                                                ? []
                                                : rows.map((row) =>
                                                      Number(row.id),
                                                  )
                                        "
                                    />
                                </th>
                                <th>任务编号</th>
                                <th>类型</th>
                                <th>URL</th>
                                <th>状态</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading || !rows.length">
                                <td colspan="7" class="empty">
                                    {{ loading ? '加载中…' : '暂无操作日志' }}
                                </td>
                            </tr>
                            <tr
                                v-for="row in loading ? [] : rows"
                                :key="Number(row.id)"
                            >
                                <td class="selection">
                                    <CheckboxField
                                        :aria-label="`选择 ${row.id}`"
                                        :checked="
                                            selected.includes(Number(row.id))
                                        "
                                        :disabled="submitting"
                                        @change="toggle(Number(row.id))"
                                    />
                                </td>
                                <td class="job-id">
                                    <div>
                                        <span class="muted">Job</span>
                                        {{ row.id ?? '未记录' }}
                                    </div>
                                    <div>
                                        <span class="muted">Task</span>
                                        {{ row.task_id ?? '未生成' }}
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="type-pill"
                                        :class="String(row.type)"
                                        >{{
                                            cacheModes.find(
                                                (item) =>
                                                    item.value === row.type,
                                            )?.label ?? row.type
                                        }}</span
                                    >
                                </td>
                                <td class="url-cell">
                                    <div>
                                        <span :title="cacheJobUrl(row)">{{
                                            cacheJobUrl(row) || '未记录 URL'
                                        }}</span
                                        ><button
                                            v-if="cacheJobUrl(row)"
                                            class="link copy"
                                            :aria-label="`复制 URL ${row.id}`"
                                            @click="copy(cacheJobUrl(row))"
                                        >
                                            <Copy />
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="status-pill"
                                        :class="cacheJobStatus(row).tone"
                                        :title="cacheJobStatus(row).label"
                                        >● {{ cacheJobStatus(row).label }}</span
                                    >
                                </td>
                                <td>
                                    {{
                                        row.create_at2 ??
                                        row.create_at ??
                                        '未记录'
                                    }}
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button
                                            v-if="
                                                cacheJobStatus(row).group ===
                                                'process'
                                            "
                                            class="link"
                                            @click="showDetail(row, 'progress')"
                                        >
                                            查看进度</button
                                        ><template v-else
                                            ><button
                                                class="link"
                                                :disabled="submitting"
                                                @click="resubmit([row])"
                                            >
                                                重新提交</button
                                            ><button
                                                v-if="
                                                    cacheJobStatus(row)
                                                        .group === 'failed'
                                                "
                                                class="link"
                                                @click="
                                                    showDetail(row, 'reason')
                                                "
                                            >
                                                查看原因
                                            </button></template
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <footer class="pagination">
                    <span>共 {{ total }} 条</span
                    ><button
                        aria-label="上一页"
                        :disabled="loading || submitting || page <= 1"
                        @click="loadJobs(page - 1)"
                    >
                        <ChevronLeft /></button
                    ><button class="current" aria-current="page">
                        {{ page }}</button
                    ><button
                        aria-label="下一页"
                        :disabled="
                            loading || submitting || page * pageSize >= total
                        "
                        @click="loadJobs(page + 1)"
                    >
                        <ChevronRight /></button
                    ><SelectField
                        v-model="pageSize"
                        aria-label="每页条数"
                        :disabled="submitting"
                        @change="loadJobs(1)"
                    >
                        <SelectOption
                            v-for="size in [10, 30, 100, 300]"
                            :key="size"
                            :value="size"
                        >
                            {{ size }} 条/页
                        </SelectOption>
                    </SelectField>
                </footer>
            </div>
            <Dialog
                :open="!!detail"
                @update:open="
                    (value) => {
                        if (!value) detail = null;
                    }
                "
                ><DialogScrollContent
                    class="cache-job-detail w-[calc(100%_-_2rem)] bg-card sm:max-w-[480px]"
                    ><DialogHeader
                        ><DialogTitle>{{
                            detailKind === 'reason' ? '失败原因' : '任务进度'
                        }}</DialogTitle
                        ><DialogDescription
                            >Job {{ detail?.id }} · Task
                            {{ detail?.task_id ?? '未生成' }}</DialogDescription
                        ></DialogHeader
                    >
                    <pre class="detail-text">{{ detailText }}</pre>
                    <DialogFooter
                        ><button
                            v-if="detailKind === 'progress'"
                            :disabled="loading"
                            @click="loadJobs()"
                        >
                            刷新</button
                        ><button class="primary" @click="detail = null">
                            关闭
                        </button></DialogFooter
                    ></DialogScrollContent
                ></Dialog
            >
        </section>
    </div>
</template>

<style scoped>
.cache-workspace {
    font-size: 12px;
    min-width: 0;
}
.tabs {
    display: flex;
    gap: 5px;
    padding: 10px 12px;
    border-bottom: 1px solid var(--border);
}
button,
input,
:deep([data-slot='select-trigger']),
:deep([data-slot='textarea']) {
    font-size: 12px;
    border: 1px solid var(--border);
    background: var(--card);
    border-radius: 4px;
}
button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    min-height: 28px;
    padding: 4px 12px;
    cursor: pointer;
}
button:disabled {
    background: var(--muted);
    color: var(--muted-foreground);
    opacity: 0.55;
    cursor: not-allowed;
}
button svg {
    height: 12px;
    width: 12px;
}
button:focus-visible,
input:focus-visible,
:deep([data-slot='select-trigger']):focus-visible,
:deep([data-slot='textarea']):focus-visible {
    outline: 2px solid #2d8cf0;
    outline-offset: 1px;
}
.tabs button {
    border: 0;
    background: transparent;
}
.tabs button[aria-selected='true'] {
    color: #2d8cf0;
    background: #2d8cf014;
    font-weight: 600;
}
.primary {
    background: #2d8cf0;
    color: white;
    border-color: #2d8cf0;
}
.link {
    border: 0;
    background: transparent;
    padding: 0;
    min-height: 0;
    color: #2d8cf0;
}
.muted {
    color: var(--muted-foreground);
}
h2 {
    font-weight: 600;
    font-size: 12px;
}
.inset {
    border: 1px solid color-mix(in srgb, var(--border) 65%, var(--card));
    background: color-mix(in srgb, var(--muted) 20%, var(--card));
    border-radius: 6px;
}
.submit-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 224px;
    gap: 14px;
    padding: 13px 10px 10px;
}
.main-column {
    min-width: 0;
}
.mode-panel {
    padding: 12px 13px;
    margin-bottom: 12px;
}
.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.section-head > span {
    font-size: 11px;
}
.marked {
    border-left: 3px solid #2d8cf0;
    line-height: 13px;
    padding-left: 7px;
}
.helper {
    font-size: 11px;
    margin: 5px 0 8px;
}
.current-mode {
    color: #2d8cf0;
    border: 1px solid #2d8cf025;
    border-radius: 14px;
    padding: 2px 8px;
    background: #2d8cf008;
    font-size: 10px !important;
}
.mode-options {
    display: flex;
    gap: 7px;
}
.mode-options button {
    min-width: 83px;
    font-size: 11px;
}
.mode-options button[aria-pressed='true'] {
    color: #2d8cf0;
    border-color: #2d8cf0;
    background: #2d8cf008;
}
.url-panel {
    padding: 12px 13px 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
}
.url-panel label {
    font-weight: 600;
}
.url-panel :deep([data-slot='textarea']) {
    display: block;
    width: 100%;
    height: 178px;
    min-height: 130px;
    resize: vertical;
    padding: 7px;
    border-color: #79afff;
}
.input-hint {
    font-size: 11px;
    padding: 7px 8px;
    margin-top: 8px;
    color: var(--muted-foreground);
}
.dot,
b {
    color: #2d8cf0;
    font-weight: 400;
}
.submit-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 10px;
    font-size: 11px;
}
.submit-footer > div {
    display: flex;
    gap: 7px;
}
.submit-footer button {
    min-height: 26px;
    font-size: 11px;
}
.quota-panel,
.state-panel {
    padding: 12px;
}
.quota-value {
    display: flex;
    align-items: baseline;
    gap: 5px;
    margin: 6px 0 8px;
}
.quota-value strong {
    font-family: Georgia, serif;
    font-size: 26px;
    line-height: 28px;
}
.quota-value span {
    font-size: 11px;
}
.quota-caption {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
}
.usage-track {
    height: 5px;
    background: var(--border);
    border-radius: 8px;
    margin-top: 7px;
    overflow: hidden;
}
.usage-track span {
    display: block;
    height: 100%;
    background: #2d8cf0;
    transition: width 0.2s;
}
.state-panel {
    margin-top: 10px;
}
.state-panel .status-pill {
    margin: 9px 0 3px;
}
.state-panel .helper {
    margin-bottom: 0;
}
.status-pill,
.summary-pill,
.type-pill {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 14px;
    font-size: 11px;
    white-space: nowrap;
    background: var(--muted);
}
.status-pill {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle;
}
.success,
.success-text {
    color: #19be6b;
}
.status-pill.success {
    background: #19be6b0d;
}
.danger,
.error-text {
    color: #ed4014;
}
.status-pill.danger {
    background: #ed40140d;
}
.warning {
    color: #ff9900;
    background: #ff99000d;
}
.info {
    color: #2d8cf0;
    background: #2d8cf00d;
}
.error-text {
    font-size: 12px;
    margin-top: 8px;
    overflow-wrap: anywhere;
}
.history-panel {
    padding: 13px 12px 14px;
}
.history-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.history-filters {
    display: flex;
    align-items: center;
    gap: 7px;
}
.history-filters :deep([data-slot='select-trigger']) {
    width: 127px;
    height: 28px;
    padding: 4px 7px;
}
.keyword {
    display: flex;
    align-items: center;
    border: 1px solid var(--border);
    border-radius: 4px;
    overflow: hidden;
}
.keyword input {
    border: 0;
    width: 220px;
    height: 26px;
    padding: 4px 7px;
    min-width: 0;
}
.keyword button {
    border: 0;
    min-height: 26px;
    padding: 4px 7px;
    color: var(--muted-foreground);
}
.history-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 6px 10px;
    margin-bottom: 12px;
    font-size: 11px;
}
.history-summary > div {
    display: flex;
    gap: 5px;
}
.summary-pill {
    background: var(--card);
    padding: 3px 8px;
}
.list-error {
    margin: 0 0 12px;
}
.table-scroll {
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    white-space: nowrap;
}
th {
    padding: 8px;
    font-size: 12px;
    font-weight: 500;
    background: color-mix(in srgb, var(--muted) 20%, var(--card));
    border-bottom: 1px solid var(--border);
}
td {
    padding: 8px;
    border-bottom: 1px solid var(--border);
    font-size: 11px;
}
th.selection {
    width: 44px;
}
.selection {
    width: 44px;
    text-align: left;
}
.selection input {
    width: 14px;
    height: 14px;
    accent-color: #2d8cf0;
    vertical-align: middle;
}
.empty {
    text-align: center;
    color: var(--muted-foreground);
    height: 40px;
}
.job-id {
    min-width: 145px;
}
.job-id span {
    display: inline-block;
    width: 30px;
    font-size: 10px;
}
.url-cell {
    min-width: 260px;
    max-width: 440px;
}
.url-cell > div {
    display: flex;
    align-items: center;
    gap: 8px;
}
.url-cell span {
    overflow: hidden;
    text-overflow: ellipsis;
}
.copy {
    flex-shrink: 0;
}
.row-actions {
    display: flex;
    gap: 10px;
}
.type-pill {
    border-radius: 4px;
    color: #2d8cf0;
    background: #2d8cf00a;
}
.type-pill.clean_dir {
    color: #ed960d;
    background: #ed960d0a;
}
.type-pill.pre_cache_url {
    color: #8a61d7;
    background: #8a61d70a;
}
.pagination {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 5px;
    margin-top: 14px;
    font-size: 12px;
}
.pagination button {
    padding: 4px 7px;
}
.pagination :deep([data-slot='select-trigger']) {
    height: 28px;
    margin-left: 8px;
    padding: 4px 7px;
}
.current {
    color: #2d8cf0;
    border-color: #2d8cf0;
}
.detail-text {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    font-family: inherit;
    font-size: 13px;
    line-height: 1.6;
    max-height: 50vh;
    overflow-y: auto;
}
:global(.cache-job-detail [data-slot='dialog-footer'] button) {
    padding: 5px 12px;
    border: 1px solid var(--border);
    border-radius: 4px;
    font-size: 12px;
}
:global(.cache-job-detail [data-slot='dialog-footer'] button.primary) {
    background: #2d8cf0;
    color: white;
}
@media (max-width: 800px) {
    .submit-layout {
        grid-template-columns: 1fr;
    }
    .submit-layout aside {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .state-panel {
        margin-top: 0;
    }
    .history-filters {
        flex-wrap: wrap;
        width: 100%;
    }
    .keyword {
        flex: 1;
        min-width: 160px;
    }
    .keyword input {
        width: 100%;
    }
    .history-summary {
        flex-wrap: wrap;
    }
    .history-summary > div {
        flex-wrap: wrap;
    }
    .pagination {
        flex-wrap: wrap;
    }
}
@media (max-width: 440px) {
    .submit-layout aside {
        grid-template-columns: 1fr;
    }
    .submit-footer {
        flex-wrap: wrap;
    }
    .current-mode {
        display: none;
    }
}
</style>
