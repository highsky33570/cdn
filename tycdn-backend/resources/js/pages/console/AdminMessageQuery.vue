<script setup lang="ts">
import { RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { apiRequest } from '@/lib/apiRequest';
import {
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnflyResponse';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const endpoint = '/api/admin/workspace/message-query';
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
const columns: ColumnDef[] = [
    { key: 'receive', label: '所属用户ID', width: '130px' },
    {
        key: 'type',
        label: '类型',
        width: '190px',
        format: (value) => labels[String(value)] ?? String(value ?? '—'),
    },
    { key: 'title', label: '标题', width: '300px' },
    { key: 'user_package_id', label: '用户套餐ID', width: '145px' },
    { key: 'site_id', label: '网站ID', width: '120px' },
    {
        key: 'create_at2',
        altKeys: ['create_at'],
        label: '创建时间',
        width: '190px',
    },
];
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
    detailId.value = String(row.id);
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
</script>

<template>
    <div class="message-query-workspace min-w-0 p-4 md:p-6">
        <section class="min-w-0 rounded-xl border bg-card p-4 md:p-5">
            <form
                class="mb-4 flex flex-wrap items-center gap-2 border-b pb-4"
                @submit.prevent="applyFilters"
            >
                <Select v-model="filters.type"
                    ><SelectTrigger class="w-full sm:w-52" aria-label="消息类型"
                        ><SelectValue placeholder="所有类型" /></SelectTrigger
                    ><SelectContent class="max-h-80"
                        ><SelectItem value="all">所有类型</SelectItem
                        ><SelectItem
                            v-for="[value, label] in typeOptions"
                            :key="value"
                            :value="value"
                            >{{ label }}</SelectItem
                        ></SelectContent
                    ></Select
                >
                <label
                    data-slot="console-input-group"
                    v-for="[key, label] in fields"
                    :key="key"
                    class="flex h-9 w-full min-w-0 items-center rounded-md border border-input bg-background sm:w-auto"
                    ><span
                        class="flex h-full shrink-0 items-center rounded-l-md border-r bg-muted/40 px-2 text-sm"
                        >{{ label }}</span
                    ><Input
                        v-model="filters[key]"
                        :aria-label="label"
                        :placeholder="`请输入${label}`"
                        inputmode="numeric"
                        class="h-full min-w-0 flex-1 border-0 shadow-none sm:w-36"
                /></label>
                <Button
                    type="button"
                    variant="outline"
                    class="ml-auto"
                    :disabled="loading"
                    @click="load"
                    ><RefreshCw class="size-4" />刷新</Button
                >
            </form>
            <Alert v-if="error" variant="destructive" class="mb-4"
                ><AlertDescription
                    >{{ error
                    }}<Button variant="outline" size="sm" @click="load"
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <ConsoleDataTable
                title="消息查询"
                class="message-table"
                embedded
                :columns="columns"
                :data="{ rows, total, page, pageSize: size, loading }"
                empty-text="暂无消息"
            >
                <template #cell-title="{ row }"
                    ><span
                        class="block max-w-72 truncate"
                        :title="String(row.title ?? '')"
                        >{{ row.title || '—' }}</span
                    ></template
                >
                <template #row-actions="{ row }"
                    ><Button variant="link" size="sm" @click="openDetail(row)"
                        >详情</Button
                    ></template
                >
            </ConsoleDataTable>
            <PackagePagination
                :page="page"
                :page-size="size"
                :total="total"
                :disabled="loading"
                numbered
                @update:page="changePage"
                @update:page-size="changeSize"
            />
        </section>
        <Dialog v-model:open="detailOpen"
            ><DialogScrollContent
                class="max-h-[90dvh] max-w-2xl grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden"
                ><DialogHeader
                    ><DialogTitle>消息详情（{{ detailId }}）</DialogTitle
                    ><DialogDescription class="sr-only"
                        >查看消息标题、邮件内容和短信内容</DialogDescription
                    ></DialogHeader
                >
                <div class="min-h-0 overflow-y-auto">
                    <div
                        v-if="detailLoading"
                        class="flex items-center gap-2 py-8"
                    >
                        <Spinner />正在加载消息…
                    </div>
                    <Alert v-else-if="detailError" variant="destructive"
                        ><AlertDescription
                            >{{ detailError
                            }}<Button
                                variant="outline"
                                size="sm"
                                @click="loadDetail"
                                >重试</Button
                            ></AlertDescription
                        ></Alert
                    >
                    <dl v-else-if="detail" class="grid gap-5">
                        <div
                            v-for="[key, label] in [
                                ['title', '标题'],
                                ['content', '邮件内容'],
                                ['phone_content', '短信内容'],
                            ]"
                            :key="key"
                            class="grid gap-2 border-b pb-4 last:border-0"
                        >
                            <dt class="text-sm text-muted-foreground">
                                {{ label }}
                            </dt>
                            <dd class="text-sm break-words whitespace-pre-wrap">
                                {{ detail[key] || '暂无内容' }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <DialogFooter
                    ><Button variant="outline" @click="detailOpen = false"
                        >关闭</Button
                    ></DialogFooter
                >
            </DialogScrollContent></Dialog
        >
    </div>
</template>
<style scoped>
.message-table :deep(table) {
    min-width: 1160px;
}
.message-table :deep(td[colspan]) {
    padding-block: 18px;
    border-bottom: 1px solid var(--border);
}
</style>
