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
    <div class="console-user-cache min-w-0 p-4 md:p-6">
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
            <DialogScrollContent class="console-user-cache"
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
