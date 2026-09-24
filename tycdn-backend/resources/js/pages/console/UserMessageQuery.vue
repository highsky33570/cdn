<script setup lang="ts">
import { RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { apiRequest } from '@/lib/apiRequest';
import {
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnflyResponse';
import { formatDate } from '@/lib/cdnRecord';
import { markUserMessageRead } from '@/lib/cdnUserApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const endpoint = '/api/cdn/proxy/v1/messages';
const initial = new URLSearchParams(
    typeof window === 'undefined' ? '' : window.location.search,
);
const filters = reactive({
    type: initial.get('type') || 'all',
    receive: initial.get('receive') || '',
    user_package_id: initial.get('user_package_id') || '',
    site_id: initial.get('site_id') || '',
});
const fields = [
    ['receive', '用户ID'],
    ['user_package_id', '用户套餐ID'],
    ['site_id', '网站ID'],
] as const;
const types: [string, string][] = [
    ['package-expiring', '套餐即将到期'],
    ['package-expire', '套餐已到期'],
    ['traffic-exceeding', '流量即将用尽'],
    ['traffic-exceed', '流量已用尽'],
    ['cc-switch', 'CC规则组自动切换'],
    ['bandwidth-exceed', '套餐带宽超限'],
    ['connection-exceed', '套餐连接数超限'],
    ['cert-expire', '证书已过期'],
    ['cert-expiring', '证书即将过期'],
    ['account-auth2', '二次实名'],
    ['announcement', '公告'],
    ['notice', '系统通知'],
];
const labels: Record<string, string> = {
    ...Object.fromEntries(types),
    announcement: '公告',
    notice: '系统通知',
};
const seenTypes = ref<string[]>([]);
const typeOptions = computed(() => [
    ...new Map([
        ...types,
        ...seenTypes.value.map(
            (key) => [key, labels[key] ?? key] as [string, string],
        ),
        ...(filters.type === 'all'
            ? []
            : [
                  [filters.type, labels[filters.type] ?? filters.type] as [
                      string,
                      string,
                  ],
              ]),
    ]).entries(),
]);
const rows = ref<CdnflyRecord[]>([]),
    total = ref(0),
    page = ref(1),
    size = ref(10),
    loading = ref(false),
    error = ref('');
const message = (e: unknown) => (e instanceof Error ? e.message : '请求失败');
let listToken = 0,
    detailToken = 0,
    timer: ReturnType<typeof setTimeout> | undefined;
async function load() {
    clearTimeout(timer);
    const token = ++listToken;
    loading.value = true;
    error.value = '';
    rows.value = [];

    try {
        const query = new URLSearchParams({
            page: String(page.value),
            limit: String(size.value),
        });

        if (filters.type !== 'all') {
            query.set('type', filters.type);
        }

        for (const [key, label] of fields) {
            const value = filters[key].trim();

            if (!value) {
                continue;
            }

            if (
                !/^\d+$/.test(value) ||
                !Number.isSafeInteger(Number(value)) ||
                Number(value) < 1
            ) {
                throw new Error(`${label}请输入正整数`);
            }

            query.set(key, value);
        }

        const result = await apiRequest(`${endpoint}?${query}`);

        if (token !== listToken) {
            return;
        }

        rows.value = extractCdnflyRows(result);
        total.value = extractCdnflyTotal(result, rows.value.length);
        seenTypes.value = [
            ...new Set([
                ...seenTypes.value,
                ...rows.value
                    .map((row) => String(row.type ?? ''))
                    .filter(Boolean),
            ]),
        ];
        const last = Math.max(1, Math.ceil(total.value / size.value));

        if (page.value > last) {
            page.value = last;
            void load();
        }
    } catch (e) {
        if (token === listToken) {
            error.value = message(e);
            total.value = 0;
        }
    } finally {
        if (token === listToken) {
            loading.value = false;
        }
    }
}
function applyFilters() {
    page.value = 1;
    void load();
}
watch(filters, () => {
    clearTimeout(timer);
    listToken++;
    loading.value = true;
    rows.value = [];
    error.value = '';
    timer = setTimeout(applyFilters, 300);
});
function changePage(value: number) {
    page.value = value;
    void load();
}
function changeSize(value: number) {
    size.value = value;
    applyFilters();
}
onMounted(() => void load());
onUnmounted(() => {
    clearTimeout(timer);
    listToken++;
    detailToken++;
});

const detailOpen = ref(false),
    detailLoading = ref(false),
    detailError = ref(''),
    detailId = ref(''),
    detail = ref<CdnflyRecord | null>(null);
watch(detailOpen, (open) => {
    if (!open) {
        detailToken++;
    }
});
function openDetail(row: CdnflyRecord) {
    if (
        !/^\d+$/.test(String(row.id)) ||
        !Number.isSafeInteger(Number(row.id)) ||
        Number(row.id) < 1
    ) {
        error.value = '消息 ID 缺失';

        return;
    }

    detailId.value = String(row.id);
    readError.value = '';
    detailOpen.value = true;
    void loadDetail();
}
async function loadDetail() {
    const token = ++detailToken;
    detailLoading.value = true;
    detailError.value = '';
    detail.value = null;

    try {
        const result = extractCdnflyRecord(
            await apiRequest(`${endpoint}/${detailId.value}`),
        );

        if (token !== detailToken) {
            return;
        }

        if (!result) {
            throw new Error('暂无消息详情');
        }

        detail.value = result;
    } catch (e) {
        if (token === detailToken) {
            detailError.value = message(e);
        }
    } finally {
        if (token === detailToken) {
            detailLoading.value = false;
        }
    }
}

const hasFilters = computed(
    () => filters.type !== 'all' || fields.some(([key]) => filters[key]),
);
function clear() {
    filters.type = 'all';

    for (const [key] of fields) {
        filters[key] = '';
    }
}
const marking = ref(false),
    readError = ref('');
const isRead = computed(() =>
    [1, '1', true, 'read'].includes(
        (detail.value?.read ??
            detail.value?.is_read ??
            detail.value?.status) as string | number | boolean,
    ),
);
async function markRead() {
    if (!detail.value || marking.value) {
        return;
    }

    const id = detailId.value;
    const token = detailToken;
    marking.value = true;
    readError.value = '';

    try {
        await markUserMessageRead(Number(id));

        if (token === detailToken && detail.value) {
            detail.value.read = 1;
            detail.value.is_read = 1;
        }
    } catch (cause) {
        if (token === detailToken) {
            readError.value = message(cause);
        }
    } finally {
        marking.value = false;
    }
}
const text = (value: unknown) =>
    value == null
        ? ''
        : typeof value === 'object'
          ? JSON.stringify(value)
          : String(value);
const title = (row: CdnflyRecord) =>
    text(row.title ?? row.name ?? row.subject) || '—';
const time = (row: CdnflyRecord) =>
    formatDate(
        row.create_at2 ?? row.create_at ?? row.created_at ?? row.send_at,
    );
const body = computed(() =>
    text(
        detail.value?.content ??
            detail.value?.message ??
            detail.value?.body ??
            detail.value?.text ??
            detail.value?.des,
    ),
);
</script>

<template>
    <section class="user-message-query" aria-label="消息查询">
        <form class="message-filters" @submit.prevent="applyFilters">
            <select v-model="filters.type" aria-label="消息类型">
                <option value="all">所有类型</option>
                <option
                    v-for="[value, label] in typeOptions"
                    :key="value"
                    :value="value"
                >
                    {{ label }}
                </option>
            </select>
            <label
                v-for="[key, label] in fields"
                :key="key"
                class="message-input"
                ><span>{{ label }}</span
                ><input
                    v-model="filters[key]"
                    :aria-label="label"
                    :placeholder="`请输入${label}`"
                    inputmode="numeric"
            /></label>
            <button
                v-if="hasFilters"
                type="button"
                class="text-link"
                @click="clear"
            >
                清除
            </button>
        </form>
        <Alert v-if="error" variant="destructive" class="mb-4"
            ><AlertDescription
                >{{ error
                }}<Button variant="outline" @click="load"
                    ><RefreshCw class="size-4" />重试</Button
                ></AlertDescription
            ></Alert
        >
        <div
            class="message-scroll"
            tabindex="0"
            aria-label="消息表格，可横向滚动"
            :aria-busy="loading"
        >
            <table>
                <colgroup>
                    <col style="width: 12.5%" />
                    <col style="width: 15.3%" />
                    <col style="width: 20.2%" />
                    <col style="width: 12.5%" />
                    <col style="width: 10.6%" />
                    <col style="width: 18.3%" />
                    <col style="width: 10.6%" />
                </colgroup>
                <thead>
                    <tr>
                        <th>所属用户ID</th>
                        <th>类型</th>
                        <th>标题</th>
                        <th>用户套餐ID</th>
                        <th>网站ID</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="7" class="empty">
                            <span
                                ><Spinner class="inline size-4" /> 加载中…</span
                            >
                        </td>
                    </tr>
                    <tr v-else-if="!rows.length">
                        <td colspan="7" class="empty">
                            <span>{{
                                error ? '加载失败，请重试' : '暂无数据'
                            }}</span>
                        </td>
                    </tr>
                    <template v-else
                        ><tr
                            v-for="(row, index) in rows"
                            :key="text(row.id) || index"
                        >
                            <td>
                                {{
                                    text(row.receive ?? row.uid ?? row.user_id)
                                }}
                            </td>
                            <td>
                                <span
                                    class="truncate-cell"
                                    :title="
                                        labels[String(row.type)] ||
                                        text(row.type)
                                    "
                                    >{{
                                        labels[String(row.type)] ||
                                        text(row.type)
                                    }}</span
                                >
                            </td>
                            <td>
                                <span
                                    class="truncate-cell"
                                    :title="title(row)"
                                    >{{ title(row) }}</span
                                >
                            </td>
                            <td>{{ text(row.user_package_id) }}</td>
                            <td>{{ text(row.site_id) }}</td>
                            <td>{{ time(row) }}</td>
                            <td>
                                <button
                                    class="text-link"
                                    :aria-label="`查看消息 ${text(row.id)} 详情`"
                                    @click="openDetail(row)"
                                >
                                    详情
                                </button>
                            </td>
                        </tr></template
                    >
                </tbody>
            </table>
        </div>
        <PackagePagination
            :page="page"
            :page-size="size"
            :total="total"
            :disabled="loading"
            numbered
            edge-links
            class="message-pagination"
            @update:page="changePage"
            @update:page-size="changeSize"
        />
        <Dialog v-model:open="detailOpen"
            ><DialogScrollContent class="max-h-[90dvh] sm:max-w-2xl"
                ><DialogHeader
                    ><DialogTitle>消息详情</DialogTitle
                    ><DialogDescription
                        >查看消息内容与通知信息。</DialogDescription
                    ></DialogHeader
                >
                <div v-if="detailLoading" class="flex items-center gap-2 py-8">
                    <Spinner />正在加载消息…
                </div>
                <Alert v-else-if="detailError" variant="destructive"
                    ><AlertDescription
                        >{{ detailError
                        }}<Button variant="outline" @click="loadDetail"
                            >重试</Button
                        ></AlertDescription
                    ></Alert
                >
                <div
                    v-else-if="detail"
                    class="min-h-0 space-y-5 overflow-y-auto"
                >
                    <div>
                        <h3 class="font-semibold break-words">
                            {{ title(detail) }}
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ time(detail) }} ·
                            {{
                                labels[String(detail.type)] || text(detail.type)
                            }}
                        </p>
                    </div>
                    <p class="text-sm break-words whitespace-pre-wrap">
                        {{ body || '暂无消息内容' }}
                    </p>
                    <div v-if="detail.phone_content">
                        <h4 class="mb-2 text-sm text-muted-foreground">
                            短信内容
                        </h4>
                        <p class="text-sm break-words whitespace-pre-wrap">
                            {{ text(detail.phone_content) }}
                        </p>
                    </div>
                </div>
                <Alert v-if="readError" variant="destructive"
                    ><AlertDescription>{{ readError }}</AlertDescription></Alert
                >
                <DialogFooter
                    ><Button variant="outline" @click="detailOpen = false"
                        >关闭</Button
                    ><Button
                        v-if="detail && !detailLoading && !detailError"
                        :disabled="marking || isRead"
                        @click="markRead"
                        ><Spinner v-if="marking" />{{
                            isRead ? '已读' : '标记已读'
                        }}</Button
                    ></DialogFooter
                >
            </DialogScrollContent></Dialog
        >
    </section>
</template>

<style scoped>
.user-message-query {
    min-width: 0;
    margin: 16px;
    padding: 12px 14px 20px;
    background: #fff;
    color: #526078;
    font-size: 14px;
}
.message-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-bottom: 12px;
}
.message-filters select {
    width: 250px;
    height: 40px;
    padding: 0 10px;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    background: transparent;
}
.message-input {
    display: flex;
    width: 250px;
    height: 40px;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    overflow: hidden;
}
.message-input:nth-of-type(2) {
    width: 275px;
}
.message-input span {
    display: flex;
    align-items: center;
    padding: 0 10px;
    border-right: 1px solid #dcdfe6;
    background: #f7f7f9;
    white-space: nowrap;
}
.message-input input {
    min-width: 0;
    width: 0;
    flex: 1;
    padding: 0 10px;
    background: transparent;
    outline: none;
}
.message-input:focus-within {
    border-color: #308cff;
}
.message-input input::placeholder {
    color: #bfc5ce;
}
.text-link {
    color: #308cff;
    cursor: pointer;
}
.text-link:hover {
    color: #66b1ff;
}
.message-scroll {
    overflow-x: auto;
    border-bottom: 1px solid #dcdfe6;
}
table {
    width: 100%;
    min-width: 1150px;
    table-layout: fixed;
    border-collapse: collapse;
}
th,
td {
    padding: 0 22px;
    border-bottom: 1px solid #e6e8ed;
    font-size: 14px;
    text-align: left;
}
th {
    height: 48px;
    background: #f7f7f9;
    font-weight: 600;
}
td {
    height: 60px;
    white-space: nowrap;
}
tbody tr:hover {
    background: #fafcff;
}
.truncate-cell {
    display: block;
    width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.empty {
    padding: 0;
    text-align: center;
}
.empty span {
    display: block;
    position: sticky;
    left: 0;
    width: min(100%, calc(100vw - 340px));
}
.message-pagination {
    justify-content: flex-start;
    margin-top: 26px;
    gap: 6px;
    font-size: 14px;
}
.message-pagination :deep(button),
.message-pagination :deep(select) {
    height: 40px;
    min-width: 40px;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    background: transparent;
    color: inherit;
    font-size: 14px;
    font-weight: 400;
    box-shadow: none;
}
.message-pagination :deep([aria-current='page']) {
    color: #308cff;
    border-color: #308cff;
}
.message-pagination :deep(select) {
    margin-left: 14px;
}
:global(.dark) .user-message-query {
    background: #18181b;
    color: #cbd5e1;
}
:global(.dark) th,
:global(.dark) .message-input span,
:global(.dark) tbody tr:hover {
    background: #27272a;
}
:global(.dark) th,
:global(.dark) td,
:global(.dark) .message-scroll,
:global(.dark) .message-input,
:global(.dark) .message-input span,
:global(.dark) .message-filters select {
    border-color: #3f3f46;
}
:global(.dark) .message-filters select option {
    background: #18181b;
}
@media (max-width: 640px) {
    .user-message-query {
        margin: 8px;
        padding: 12px 8px 16px;
    }
    .message-input,
    .message-input:nth-of-type(2),
    .message-filters select {
        flex: 1 1 250px;
        width: 100%;
    }
    .empty span {
        width: calc(100vw - 50px);
    }
}
</style>
