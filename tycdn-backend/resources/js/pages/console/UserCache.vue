<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import PackagePagination from '@/components/console/PackagePagination.vue';
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
import RadioGroup from '@/components/ui/radio-group/RadioGroup.vue';
import RadioGroupItem from '@/components/ui/radio-group/RadioGroupItem.vue';
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
    cacheJobUrl,
} from '@/lib/cacheJobs';
import type { CacheMode } from '@/lib/cacheJobs';
import { getErrorMessage, textValue } from '@/lib/cdnRecord';
import {
    createUserJobs,
    listUserJobs,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnUserApi';
import type { CdnflyRecord } from '@/lib/cdnUserApi';

const tab = ref<'submit' | 'history'>('submit');
const mode = ref<CacheMode>('clean_url'),
    input = ref('');
const submitting = ref(false),
    formError = ref(''),
    listError = ref('');
const quota = ref<{ total: number | null; used: number | null }>({
    total: null,
    used: null,
});
const quotaLoading = ref(false),
    quotaError = ref('');
const remaining = computed(() =>
    quota.value.total === null || quota.value.used === null
        ? null
        : Math.max(0, quota.value.total - quota.value.used),
);
const rows = ref<CdnflyRecord[]>([]),
    selected = ref<string[]>([]);
const page = ref(1),
    pageSize = ref(10),
    total = ref(0),
    loading = ref(false);
const typeFilter = ref(''),
    keyword = ref('');
const allSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selected.value.includes(textValue(row.id))),
);
const detail = ref<CdnflyRecord | null>(null);
const detailText = computed(() => {
    if (!detail.value) {
        return '';
    }

    const value =
        detail.value.ret ||
        detail.value.msg ||
        detail.value.error ||
        detail.value.result ||
        detail.value.progress ||
        cacheJobStatus(detail.value).label;

    return typeof value === 'object'
        ? JSON.stringify(value, null, 2)
        : String(value);
});
let quotaRequest = 0,
    listRequest = 0;
onMounted(() => void loadQuota());
onUnmounted(() => {
    quotaRequest++;
    listRequest++;
});

async function loadQuota() {
    const request = ++quotaRequest;
    quotaLoading.value = true;
    quotaError.value = '';
    quota.value = { total: null, used: null };
    const date = new Date();
    const start = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

    try {
        const result = await apiRequest<{
            total: number | null;
            used: number | null;
        }>(
            `/api/cdn/cache-quota?${new URLSearchParams({ type: mode.value, start })}`,
        );

        if (request === quotaRequest) {
            quota.value = result;
        }
    } catch (error) {
        if (request === quotaRequest) {
            quotaError.value = getErrorMessage(error);
        }
    } finally {
        if (request === quotaRequest) {
            quotaLoading.value = false;
        }
    }
}
function changeMode(value: CacheMode) {
    if (submitting.value || mode.value === value) {
        return;
    }

    mode.value = value;
    formError.value = '';
    void loadQuota();
}
function changeTab(value: 'submit' | 'history') {
    if (submitting.value || tab.value === value) {
        return;
    }

    tab.value = value;

    if (value === 'history') {
        void loadJobs(1);
    } else {
        void loadQuota();
    }
}
function tabKey(event: KeyboardEvent) {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
        return;
    }

    event.preventDefault();
    changeTab(
        event.key === 'Home'
            ? 'submit'
            : event.key === 'End'
              ? 'history'
              : tab.value === 'submit'
                ? 'history'
                : 'submit',
    );
    document.getElementById(`cache-tab-${tab.value}`)?.focus();
}
async function submit() {
    if (submitting.value) {
        return;
    }

    const urls = cacheUrls(input.value);
    formError.value = !urls.length
        ? '请填写至少一个 URL'
        : urls.some((url) => !validCacheUrl(url))
          ? 'URL 需要以 http:// 或 https:// 开头，且不能包含空格'
          : urls.length > 1000
            ? '单次最多提交 1000 个任务'
            : quota.value.total &&
                remaining.value !== null &&
                urls.length > remaining.value
              ? '输入条数超过今日剩余限额'
              : '';

    if (formError.value) {
        return;
    }

    submitting.value = true;

    try {
        await createUserJobs(
            urls.map((url) => ({ type: mode.value, data: { url } })),
        );
        input.value = '';
        toast.success('提交成功，请到操作记录里查看进度。');
        void loadQuota();
    } catch (error) {
        formError.value = getErrorMessage(error);
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
        const result = await listUserJobs(
            cacheJobQuery(
                typeFilter.value,
                keyword.value,
                target,
                pageSize.value,
            ),
        );

        if (request !== listRequest) {
            return;
        }

        rows.value = extractCdnflyRows(result);
        total.value = extractCdnflyTotal(result, rows.value.length);

        if (target > 1 && !rows.value.length) {
            void loadJobs(Math.max(1, Math.ceil(total.value / pageSize.value)));
        }
    } catch (error) {
        if (request === listRequest) {
            listError.value = getErrorMessage(error);
            rows.value = [];
            total.value = 0;
        }
    } finally {
        if (request === listRequest) {
            loading.value = false;
        }
    }
}
async function resubmit(
    jobs = rows.value.filter((row) =>
        selected.value.includes(textValue(row.id)),
    ),
) {
    if (submitting.value || loading.value) {
        return;
    }

    if (!jobs.length) {
        toast.warning('请选择要重新提交的任务');

        return;
    }

    submitting.value = true;
    listError.value = '';

    try {
        await createUserJobs(jobs.map(cacheJobPayload));
        toast.success('重新提交成功');
        await loadJobs();
    } catch (error) {
        listError.value = getErrorMessage(error);
    } finally {
        submitting.value = false;
    }
}
function typeLabel(type: unknown) {
    return (
        cacheModes
            .find((item) => item.value === type)
            ?.label.replace('刷新 URL', '刷新URL') ||
        textValue(type) ||
        '-'
    );
}
function createdAt(row: CdnflyRecord) {
    return (
        textValue(row.create_at2 || row.created_at || row.create_at)
            .replace('T', ' ')
            .slice(0, 19) || '-'
    );
}
</script>

<template>
    <div class="min-w-0 p-4 md:p-6">
        <section class="user-cache-workspace">
            <nav
                class="cache-tabs"
                role="tablist"
                aria-label="刷新预热"
                @keydown="tabKey"
            >
                <Button
                    variant="ghost"
                    data-slot="console-tab"
                    v-for="item in [
                        { value: 'submit', label: '刷新预热' },
                        { value: 'history', label: '操作记录' },
                    ] as const"
                    :id="`cache-tab-${item.value}`"
                    :key="item.value"
                    type="button"
                    role="tab"
                    :aria-selected="tab === item.value"
                    :aria-controls="`cache-panel-${item.value}`"
                    :tabindex="tab === item.value ? 0 : -1"
                    :disabled="submitting"
                    @click="changeTab(item.value)"
                >
                    {{ item.label }}
                </Button>
            </nav>
            <div
                v-if="tab === 'submit'"
                id="cache-panel-submit"
                role="tabpanel"
                aria-labelledby="cache-tab-submit"
            >
                <form
                    class="cache-form"
                    :aria-busy="submitting"
                    @submit.prevent="submit"
                >
                    <fieldset
                        class="cache-form-row mode-row"
                        :disabled="submitting"
                    >
                        <legend class="sr-only">操作类型</legend>
                        <span class="row-label" aria-hidden="true"
                            >操作类型:</span
                        >
                        <RadioGroup
                            :model-value="mode"
                            aria-label="操作类型"
                            @update:model-value="
                                changeMode($event as CacheMode)
                            "
                            name="cache-mode"
                            class="cache-radios"
                        >
                            <label v-for="item in cacheModes" :key="item.value"
                                ><RadioGroupItem :value="item.value" />{{
                                    typeLabel(item.value)
                                }}</label
                            >
                        </RadioGroup>
                    </fieldset>
                    <div class="cache-form-row">
                        <label class="row-label url-label" for="cache-urls"
                            >URL:</label
                        >
                        <div class="url-field">
                            <Textarea
                                id="cache-urls"
                                v-model="input"
                                placeholder="一行一条URL"
                                spellcheck="false"
                                :disabled="submitting"
                                :aria-invalid="!!formError"
                                aria-describedby="cache-quota cache-form-error"
                            />
                            <p
                                data-typography="helper"
                                id="cache-quota"
                                class="quota-hint"
                                :aria-busy="quotaLoading"
                                aria-live="polite"
                            >
                                <template v-if="quotaLoading"
                                    >正在读取今日额度…</template
                                >
                                <template v-else-if="quotaError"
                                    >额度暂不可用
                                    <Button
                                        variant="link"
                                        size="inline"
                                        data-slot="console-link"
                                        type="button"
                                        class="text-action"
                                        :title="quotaError"
                                        @click="loadQuota"
                                    >
                                        重试
                                    </Button></template
                                >
                                <template v-else-if="quota.total === 0"
                                    >每日不限额</template
                                >
                                <template v-else
                                    >每日限额{{
                                        quota.total ?? '未知'
                                    }}次，今日剩余{{
                                        remaining ?? '未知'
                                    }}次</template
                                >
                            </p>
                            <p
                                data-typography="body"
                                v-if="formError"
                                id="cache-form-error"
                                class="error-message"
                                role="alert"
                            >
                                {{ formError }}
                            </p>
                            <Button
                                variant="default"
                                data-slot="console-action"
                                class="cache-button primary submit-button"
                                type="submit"
                                :disabled="submitting"
                            >
                                {{ submitting ? '提交中…' : '提交' }}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
            <div
                v-else
                id="cache-panel-history"
                role="tabpanel"
                aria-labelledby="cache-tab-history"
                :aria-busy="loading || submitting"
            >
                <form class="cache-toolbar" @submit.prevent="loadJobs(1)">
                    <Button
                        variant="default"
                        data-slot="console-action"
                        type="button"
                        class="cache-button primary"
                        :disabled="loading || submitting"
                        @click="resubmit()"
                    >
                        {{ submitting ? '提交中…' : '重新提交' }}
                    </Button>
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
                            {{ typeLabel(item.value) }}
                        </SelectOption>
                    </SelectField>
                    <div data-slot="console-input-group" class="cache-search">
                        <Input
                            v-model="keyword"
                            aria-label="URL或域名"
                            placeholder="URL或域名"
                            :disabled="submitting"
                        /><Button
                            variant="ghost"
                            size="icon-sm"
                            type="submit"
                            aria-label="查询"
                            :disabled="loading || submitting"
                        >
                            <Search :size="18" />
                        </Button>
                    </div>
                </form>
                <p
                    data-typography="body"
                    v-if="listError"
                    role="alert"
                    class="error-message"
                >
                    {{ listError }}
                    <Button
                        variant="link"
                        size="inline"
                        data-slot="console-link"
                        class="text-action"
                        type="button"
                        :disabled="loading || submitting"
                        @click="loadJobs()"
                    >
                        重试
                    </Button>
                </p>
                <div class="cache-table-scroll">
                    <table>
                        <colgroup>
                            <col style="width: 6%" />
                            <col style="width: 17%" />
                            <col style="width: 12%" />
                            <col style="width: 22%" />
                            <col style="width: 12%" />
                            <col style="width: 20%" />
                            <col style="width: 11%" />
                        </colgroup>
                        <thead>
                            <tr>
                                <th>
                                    <CheckboxField
                                        aria-label="全选当前页"
                                        :checked="allSelected"
                                        :indeterminate="
                                            selected.length > 0 && !allSelected
                                        "
                                        :disabled="
                                            loading ||
                                            submitting ||
                                            !rows.length
                                        "
                                        @change="
                                            selected = allSelected
                                                ? []
                                                : rows.map((row) =>
                                                      textValue(row.id),
                                                  )
                                        "
                                    />
                                </th>
                                <th>JobId / TaskId</th>
                                <th>类型</th>
                                <th>URL</th>
                                <th>状态</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="7" class="empty">加载中…</td>
                            </tr>
                            <template v-else>
                                <tr
                                    v-for="row in rows"
                                    :key="textValue(row.id)"
                                >
                                    <td>
                                        <CheckboxField
                                            v-model="selected"
                                            :value="textValue(row.id)"
                                            :aria-label="`选择 ${row.id}`"
                                            :disabled="submitting"
                                        />
                                    </td>
                                    <td>
                                        {{ row.id
                                        }}<span
                                            v-if="row.task_id"
                                            class="task-id"
                                            >{{ row.task_id }}</span
                                        >
                                    </td>
                                    <td>{{ typeLabel(row.type) }}</td>
                                    <td
                                        class="url-cell"
                                        :title="cacheJobUrl(row)"
                                    >
                                        {{ cacheJobUrl(row) || '-' }}
                                    </td>
                                    <td>
                                        <Button
                                            variant="link"
                                            size="inline"
                                            data-slot="console-link"
                                            type="button"
                                            class="job-status"
                                            :data-tone="
                                                cacheJobStatus(row).tone
                                            "
                                            @click="detail = row"
                                        >
                                            {{ cacheJobStatus(row).label }}
                                        </Button>
                                    </td>
                                    <td>{{ createdAt(row) }}</td>
                                    <td>
                                        <Button
                                            variant="link"
                                            size="inline"
                                            data-slot="console-link"
                                            class="text-action"
                                            type="button"
                                            :disabled="submitting"
                                            @click="resubmit([row])"
                                        >
                                            重新提交
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="!rows.length">
                                    <td colspan="7" class="empty">
                                        {{
                                            listError
                                                ? '加载失败，请重试'
                                                : '暂无数据'
                                        }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <PackagePagination
                    v-model:page="page"
                    v-model:page-size="pageSize"
                    class="cache-pagination"
                    :total="total"
                    :disabled="loading || submitting"
                    numbered
                    edge-links
                    @update:page="loadJobs($event)"
                    @update:page-size="loadJobs(1)"
                />
            </div>
        </section>
        <Dialog :open="!!detail" @update:open="!$event && (detail = null)">
            <DialogScrollContent
                ><DialogHeader
                    ><DialogTitle>任务详情</DialogTitle
                    ><DialogDescription
                        >JobId: {{ detail?.id }}</DialogDescription
                    ></DialogHeader
                >
                <pre class="text-sm break-all whitespace-pre-wrap">{{
                    detailText
                }}</pre>
                <DialogFooter
                    ><Button
                        variant="outline"
                        data-slot="console-action"
                        class="cache-button"
                        type="button"
                        @click="detail = null"
                    >
                        关闭
                    </Button></DialogFooter
                ></DialogScrollContent
            >
        </Dialog>
    </div>
</template>

<style scoped>
.user-cache-workspace {
    background: var(--card);
    color: var(--foreground);
    padding: 0 14px 24px;
    min-width: 0;
    font-size: var(--console-text-body);
}
.cache-tabs {
    display: flex;
    gap: 20px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 20px;
}
.cache-tabs button {
    padding: 14px 20px;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    white-space: nowrap;
}
.cache-tabs button[aria-selected='true'] {
    color: var(--primary);
    border-bottom-color: var(--primary);
}
.cache-form {
    padding: 8px 0 20px;
}
.cache-form-row {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}
.row-label {
    width: 96px;
    flex-shrink: 0;
    text-align: right;
}
.mode-row {
    margin-bottom: 38px;
}
.cache-radios {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}
.cache-radios label {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}
:deep([data-slot='radio-group-item']),
:deep([data-slot='checkbox']) {
    width: 19px;
    height: 19px;
    accent-color: var(--primary);
    vertical-align: middle;
}
.url-label {
    padding-top: 8px;
}
.url-field {
    width: 625px;
    max-width: 100%;
    min-width: 0;
}
:deep([data-slot='textarea']) {
    width: 100%;
    height: 275px;
    display: block;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--card);
    padding: 8px 10px;
    resize: vertical;
    outline: none;
}
:deep([data-slot='textarea'])::placeholder,
.cache-search input::placeholder {
    color: var(--muted-foreground);
    opacity: 0.55;
}
:deep([data-slot='textarea']):focus,
.cache-search:focus-within {
    border-color: var(--primary);
}
.quota-hint {
    margin-top: 4px;
    color: var(--muted-foreground);
    font-size: var(--console-text-helper);
    line-height: 22px;
}
.cache-button {
    height: 40px;
    padding: 0 19px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--card);
    font-size: var(--console-text-body);
    white-space: nowrap;
}
.cache-button.primary {
    background: var(--primary);
    color: var(--primary-foreground);
    border-color: var(--primary);
}
.submit-button {
    margin-top: 34px;
}
button:not(:disabled) {
    cursor: pointer;
}
button:disabled,
:deep([data-slot='select-trigger']):disabled,
input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
button:focus-visible,
input:focus-visible,
:deep([data-slot='select-trigger']):focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}
.cache-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
}
.cache-toolbar :deep([data-slot='select-trigger']) {
    width: 187px;
    height: 40px;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--card);
}
.cache-search {
    display: flex;
    width: 269px;
    height: 40px;
    border: 1px solid var(--border);
    border-radius: 4px;
}
.cache-search input {
    min-width: 0;
    flex: 1;
    padding: 0 10px;
    outline: none;
}
.cache-search button {
    width: 40px;
    display: grid;
    place-items: center;
    color: var(--muted-foreground);
}
.cache-table-scroll {
    overflow-x: auto;
}
table {
    width: 100%;
    font-size: var(--console-text-body);
    color: var(--muted-foreground);
    min-width: 1000px;
    table-layout: fixed;
    border-collapse: collapse;
}
th {
    background: color-mix(in srgb, var(--muted) 55%, var(--card));
    height: 48px;
    font-weight: 600;
    text-align: left;
}
th,
td {
    padding: 10px 20px;
    border-bottom: 1px solid var(--border);
}
td {
    height: 60px;
    overflow-wrap: anywhere;
}
th:first-child,
td:first-child {
    text-align: center;
}
tbody tr:hover {
    background: color-mix(in srgb, var(--primary) 4%, var(--card));
}
.url-cell {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.task-id {
    display: block;
    font-size: var(--console-text-helper);
    color: var(--muted-foreground);
}
.empty {
    text-align: center;
    color: var(--muted-foreground);
}
.text-action {
    color: var(--primary);
}
.job-status {
    text-align: left;
}
.job-status[data-tone='success'] {
    color: #19be6b;
}
.job-status[data-tone='danger'],
.error-message {
    color: var(--destructive);
}
.job-status[data-tone='warning'] {
    color: #b7791f;
}
.job-status[data-tone='info'] {
    color: var(--primary);
}
.error-message {
    margin: 12px 0;
    font-size: var(--console-text-body);
}
@media (max-width: 640px) {
    .user-cache-workspace {
        padding: 0 10px 20px;
    }
    .cache-form-row {
        gap: 10px;
    }
    .row-label {
        width: 72px;
        font-size: var(--console-text-body);
    }
    .cache-radios {
        gap: 12px;
        font-size: var(--console-text-body);
    }
    .cache-tabs {
        gap: 0;
    }
    .cache-tabs button {
        padding: 12px 16px;
    }
    .cache-search {
        width: 100%;
    }
    .url-field {
        flex: 1;
    }
}
</style>
