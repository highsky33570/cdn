<script setup lang="ts">
import { Copy, RefreshCw, Search } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import { Button } from '@/components/ui/button';
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
    <div class="console-admin-cache p-3 md:p-5">
        <section class="cache-workspace rounded-xl border bg-card shadow-sm">
            <nav class="tabs" role="tablist" aria-label="刷新预热">
                <Button
                    variant="ghost"
                    type="button"
                    data-slot="console-tab"
                    role="tab"
                    :aria-selected="tab === 'submit'"
                    :disabled="submitting"
                    @click="changeTab('submit')"
                >
                    刷新预热</Button
                ><Button
                    variant="ghost"
                    type="button"
                    data-slot="console-tab"
                    role="tab"
                    :aria-selected="tab === 'history'"
                    :disabled="submitting"
                    @click="changeTab('history')"
                >
                    操作记录
                </Button>
            </nav>
            <div v-if="tab === 'submit'" role="tabpanel" class="submit-layout">
                <div class="main-column">
                    <section class="mode-panel inset">
                        <div class="section-head">
                            <h2 data-typography="section-title" class="marked">
                                操作类型
                            </h2>
                            <span class="current-mode"
                                >当前：{{ currentMode.label }}</span
                            >
                        </div>
                        <p data-typography="helper" class="muted helper">
                            选择任务类型后会刷新对应的今日额度。
                        </p>
                        <div
                            class="mode-options"
                            role="group"
                            aria-label="操作类型"
                        >
                            <Button
                                variant="ghost"
                                type="button"
                                data-slot="console-option"
                                v-for="item in cacheModes"
                                :key="item.value"
                                :aria-pressed="mode === item.value"
                                :disabled="submitting"
                                @click="changeMode(item.value)"
                            >
                                {{ item.label }}
                            </Button>
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
                        <p data-typography="helper" class="helper muted">
                            {{ currentMode.helper }}
                        </p>
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
                            data-typography="helper"
                            class="input-hint inset"
                            :class="{ 'error-text': inputInvalid }"
                        >
                            <span class="dot">●</span> {{ inputHint }}
                        </p>
                        <p
                            data-typography="body"
                            v-if="formError"
                            role="alert"
                            class="error-text"
                        >
                            {{ formError }}
                        </p>
                        <div class="submit-footer">
                            <span class="muted"
                                >本次将提交
                                <b>{{ urls.length }}</b> 个任务</span
                            >
                            <div>
                                <Button
                                    variant="outline"
                                    data-slot="console-action"
                                    type="button"
                                    :disabled="!input || submitting"
                                    @click="
                                        input = '';
                                        formError = '';
                                        submitted = false;
                                    "
                                >
                                    清空</Button
                                ><Button
                                    variant="default"
                                    type="submit"
                                    data-slot="console-action"
                                    class="primary"
                                    :disabled="submitDisabled"
                                >
                                    {{ submitting ? '提交中…' : '提交任务' }}
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
                <aside>
                    <section
                        class="quota-panel inset"
                        :aria-busy="quotaLoading"
                    >
                        <h2 data-typography="section-title">今日额度</h2>
                        <div class="quota-value">
                            <strong data-typography="metric">{{
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
                        <p
                            data-typography="body"
                            v-if="quotaError"
                            role="alert"
                            class="error-text"
                        >
                            {{ quotaError }}
                            <Button
                                variant="link"
                                size="inline"
                                type="button"
                                data-slot="console-link"
                                class="link"
                                @click="loadQuota"
                            >
                                重试
                            </Button>
                        </p>
                    </section>
                    <section class="state-panel inset">
                        <h2 data-typography="section-title">提交状态</h2>
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
                        <p data-typography="helper" class="muted helper">
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
                    <Button
                        variant="outline"
                        type="button"
                        data-slot="console-action"
                        :class="{ primary: selected.length > 0 }"
                        :disabled="!selected.length || submitting || loading"
                        @click="resubmit()"
                    >
                        <RefreshCw />{{
                            submitting ? '提交中…' : '重新提交选中'
                        }}
                    </Button>
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
                        <div data-slot="console-input-group" class="keyword">
                            <Input
                                v-model="keyword"
                                aria-label="搜索 URL 或域名"
                                placeholder="搜索 URL 或域名"
                                :disabled="submitting"
                            /><Button
                                variant="outline"
                                data-slot="console-action"
                                type="submit"
                                aria-label="搜索"
                                :disabled="submitting"
                            >
                                <Search />
                            </Button>
                        </div>
                        <Button
                            variant="link"
                            size="inline"
                            data-slot="console-link"
                            type="button"
                            class="link"
                            :disabled="submitting"
                            @click="clearFilters"
                        >
                            清空
                        </Button>
                    </form>
                </div>
                <p
                    data-typography="body"
                    v-if="listError"
                    role="alert"
                    class="error-text list-error"
                >
                    {{ listError }}
                    <Button
                        variant="link"
                        size="inline"
                        type="button"
                        data-slot="console-link"
                        class="link"
                        :disabled="submitting"
                        @click="loadJobs()"
                    >
                        刷新记录
                    </Button>
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
                                        ><Button
                                            variant="ghost"
                                            size="icon-sm"
                                            type="button"
                                            data-slot="console-link"
                                            v-if="cacheJobUrl(row)"
                                            class="link copy"
                                            :aria-label="`复制 URL ${row.id}`"
                                            @click="copy(cacheJobUrl(row))"
                                        >
                                            <Copy />
                                        </Button>
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
                                        <Button
                                            variant="link"
                                            size="inline"
                                            type="button"
                                            data-slot="console-link"
                                            v-if="
                                                cacheJobStatus(row).group ===
                                                'process'
                                            "
                                            class="link"
                                            @click="showDetail(row, 'progress')"
                                        >
                                            查看进度</Button
                                        ><template v-else
                                            ><Button
                                                variant="link"
                                                size="inline"
                                                type="button"
                                                data-slot="console-link"
                                                class="link"
                                                :disabled="submitting"
                                                @click="resubmit([row])"
                                            >
                                                重新提交</Button
                                            ><Button
                                                variant="link"
                                                size="inline"
                                                type="button"
                                                data-slot="console-link"
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
                                            </Button></template
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ConsolePagination
                    :total="total"
                    :page="page"
                    :previous-disabled="loading || submitting || page <= 1"
                    :next-disabled="
                        loading || submitting || page * pageSize >= total
                    "
                    @previous="loadJobs(page - 1)"
                    @next="loadJobs(page + 1)"
                />
            </div>
            <Dialog
                :open="!!detail"
                @update:open="
                    (value) => {
                        if (!value) detail = null;
                    }
                "
                ><DialogScrollContent
                    class="console-admin-cache cache-job-detail w-[calc(100%_-_2rem)] bg-card sm:max-w-[480px]"
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
                        ><Button
                            variant="outline"
                            type="button"
                            data-slot="console-action"
                            v-if="detailKind === 'progress'"
                            :disabled="loading"
                            @click="loadJobs()"
                        >
                            刷新</Button
                        ><Button
                            variant="default"
                            type="button"
                            data-slot="console-action"
                            class="primary"
                            @click="detail = null"
                        >
                            关闭
                        </Button></DialogFooter
                    ></DialogScrollContent
                ></Dialog
            >
        </section>
    </div>
</template>
