<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Download,
    RefreshCw,
    Unlock,
} from 'lucide-vue-next';
import {
    PopoverRoot,
    PopoverTrigger,
    PopoverPortal,
    PopoverContent,
} from 'reka-ui';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Spinner } from '@/components/ui/spinner';
import { apiRequest } from '@/lib/apiRequest';
import {
    blockFilterLabel,
    blockLogQuery,
    blockRowKey,
    blockTimestamp,
    manualUnlockLabel,
} from '@/lib/blockLogs';
import type { BlockLogFilters, BlockLogTab } from '@/lib/blockLogs';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import { getErrorMessage } from '@/lib/formatters';
import { masterGet } from '@/lib/masterApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const tabs: { key: BlockLogTab; label: string }[] = [
    { key: 'current', label: '当前拉黑' },
    { key: 'stats', label: '拉黑统计' },
    { key: 'history', label: '历史拉黑' },
];
const initialTab = new URLSearchParams(usePage().url.split('?')[1] ?? '').get('tab');
const active = ref<BlockLogTab>(
    initialTab === 'history' || initialTab === 'stats' ? initialTab : 'current',
);
const emptyFilters = (): BlockLogFilters => ({
    ip: '',
    site_id: '',
    filter_name: '',
    start: '',
    end: '',
});
const currentFilters = reactive(emptyFilters());
const historyFilters = reactive(emptyFilters());
const filters = computed(() =>
    active.value === 'history' ? historyFilters : currentFilters,
);
const resource = computed(
    () =>
        ({
            current: 'blackip',
            stats: 'blackip-count',
            history: 'history-blackip',
        })[active.value],
);
const rows = ref<CdnflyRecord[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = ref(10);
const loading = ref(false);
const error = ref('');
const exporting = ref(false);
const unlocking = ref(false);
const selection = ref(new Set<string>());
const visibleRows = computed(() =>
    active.value === 'stats'
        ? rows.value.slice(
              (page.value - 1) * pageSize.value,
              page.value * pageSize.value,
          )
        : rows.value,
);
const allSelected = computed(
    () =>
        visibleRows.value.length > 0 &&
        visibleRows.value.every((row) => selection.value.has(blockRowKey(row))),
);
const lastPage = computed(() =>
    Math.max(1, Math.ceil(total.value / pageSize.value)),
);
const pageButtons = computed(() => {
    const first = Math.max(1, Math.min(page.value - 2, lastPage.value - 4));

    return Array.from(
        { length: Math.min(5, lastPage.value) },
        (_, i) => first + i,
    );
});
const colSpan = computed(() =>
    active.value === 'current' ? 9 : active.value === 'stats' ? 3 : 7,
);
let requestId = 0;
let searchTimer: ReturnType<typeof setTimeout> | undefined;

async function load(target = page.value): Promise<void> {
    clearTimeout(searchTimer);
    const id = ++requestId;
    const tab = active.value;
    loading.value = true;
    error.value = '';
    selection.value.clear();

    try {
        const query = blockLogQuery(tab, filters.value);
        const result = await masterGet(
            resource.value,
            tab === 'stats'
                ? {}
                : { ...query, page: target, limit: pageSize.value },
        );

        if (id !== requestId) {
            return;
        }

        rows.value = extractCdnflyRows(result);
        // The master returns the complete ranking without page/limit parameters.
        total.value =
            tab === 'stats'
                ? rows.value.length
                : extractCdnflyTotal(result, rows.value.length);
        page.value = Math.min(target, lastPage.value);

        if (tab !== 'stats' && target > lastPage.value) {
            await load(lastPage.value);
        }
    } catch (e) {
        if (id !== requestId) {
            return;
        }

        error.value = getErrorMessage(e);
        rows.value = [];
        total.value = 0;
    } finally {
        if (id === requestId) {
            loading.value = false;
        }
    }
}

function switchTab(tab: BlockLogTab): void {
    if (tab === active.value) {
        return;
    }

    active.value = tab;
    rows.value = [];
    total.value = 0;
    page.value = 1;
    void load(1);
}
function changePage(target: number): void {
    if (loading.value || target < 1 || target > lastPage.value) {
        return;
    }

    if (active.value === 'stats') {
        page.value = target;
    } else {
        void load(target);
    }
}
function clearFilters(): void {
    Object.assign(filters.value, emptyFilters());
}
watch(
    () => [active.value, ...Object.values(filters.value)],
    (next, previous) => {
        if (next[0] !== previous[0] || active.value === 'stats') {
            return;
        }

        clearTimeout(searchTimer);
        // Invalidate a response for filters the user has already changed.
        requestId++;
        searchTimer = setTimeout(() => void load(1), 350);
    },
);
onMounted(() => void load(1));
onUnmounted(() => {
    requestId++;
    clearTimeout(searchTimer);
});

function selectAll(checked: boolean | 'indeterminate'): void {
    selection.value = new Set(
        checked === true ? visibleRows.value.map(blockRowKey) : [],
    );
}
function selectRow(
    row: CdnflyRecord,
    checked: boolean | 'indeterminate',
): void {
    if (checked === true) {
        selection.value.add(blockRowKey(row));
    } else {
        selection.value.delete(blockRowKey(row));
    }
}
async function unlock(
    items: { site_id: number; ip?: string }[],
): Promise<void> {
    if (!items.length || unlocking.value) {
        return;
    }

    unlocking.value = true;
    unlockError.value = '';

    try {
        await apiRequest('/api/admin/workspace/blackip/unlock', {
            method: 'POST',
            body: JSON.stringify({ items }),
        });
        toast.success('解锁IP任务提交成功，请稍等几分钟左右');
        siteDialog.value = false;
        selection.value.clear();
        await load();
    } catch (e) {
        unlockError.value = getErrorMessage(e);

        if (!siteDialog.value) {
            toast.error(unlockError.value);
        }
    } finally {
        unlocking.value = false;
    }
}
function unlockRows(records: CdnflyRecord[]): void {
    if (
        records.some(
            (row) =>
                row.site_id === null ||
                row.site_id === undefined ||
                String(row.site_id).trim() === '' ||
                !Number.isSafeInteger(Number(row.site_id)) ||
                Number(row.site_id) < 0 ||
                !row.ip,
        )
    ) {
        toast.error('记录缺少有效的网站ID或IP地址');

        return;
    }

    void unlock(
        records.map((row) => ({
            site_id: Number(row.site_id),
            ip: String(row.ip),
        })),
    );
}
const siteDialog = ref(false);
const siteId = ref('');
const unlockError = ref('');
function unlockSite(): void {
    if (
        !/^\d+$/.test(siteId.value.trim()) ||
        !Number.isSafeInteger(Number(siteId.value))
    ) {
        unlockError.value = '请输入有效的网站ID';

        return;
    }

    void unlock([{ site_id: Number(siteId.value) }]);
}

async function exportIps(): Promise<void> {
    exporting.value = true;
    const tab = active.value;
    const exportResource = resource.value;

    try {
        const query = new URLSearchParams(
            Object.entries(blockLogQuery(tab, filters.value)).map(
                ([key, value]) => [key, String(value)],
            ),
        );
        const response = await fetch(
            `/api/admin/workspace/${exportResource}/export?${query}`,
            {
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/plain, application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (
            !response.ok ||
            !response.headers.get('Content-Type')?.startsWith('text/plain')
        ) {
            const body = await response.json().catch(() => null);

            throw new Error(body?.message || '导出失败，请重试');
        }

        const url = URL.createObjectURL(await response.blob());
        const link = document.createElement('a');
        link.href = url;
        link.download =
            tab === 'history' ? 'history_black_ip.txt' : 'black_ip.txt';
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
        toast.error(getErrorMessage(e));
    } finally {
        exporting.value = false;
    }
}

const rangeOpen = ref(false);
const rangeStart = ref('');
const rangeEnd = ref('');
const rangeError = ref('');
watch(rangeOpen, (open) => {
    if (open) {
        rangeStart.value = historyFilters.start;
        rangeEnd.value = historyFilters.end;
        rangeError.value = '';
    }
});
function applyRange(): void {
    try {
        blockLogQuery('history', {
            ...historyFilters,
            start: rangeStart.value,
            end: rangeEnd.value,
        });
        historyFilters.start = rangeStart.value;
        historyFilters.end = rangeEnd.value;
        rangeOpen.value = false;
    } catch (e) {
        rangeError.value = getErrorMessage(e);
    }
}
function presetRange(days: number): void {
    const end = new Date();
    const start = new Date(end);
    start.setHours(0, 0, 0, 0);
    start.setDate(start.getDate() - days + 1);
    const local = (date: Date) =>
        blockTimestamp(date.getTime() / 1000).replace(' ', 'T');
    rangeStart.value = local(start);
    rangeEnd.value = local(end);
}
</script>

<template>
    <div class="console-page min-w-0 p-4 md:p-6">
        <section
            class="block-log-card min-w-0 rounded-xl border bg-card p-4 text-card-foreground shadow-sm"
            aria-label="拉黑日志"
        >
            <div
                class="mb-4 flex flex-wrap gap-1"
                role="tablist"
                aria-label="拉黑日志分类"
            >
                <button
                    v-for="tab in tabs"
                    :id="`block-tab-${tab.key}`"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="active === tab.key"
                    aria-controls="block-log-panel"
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
                id="block-log-panel"
                role="tabpanel"
                :aria-labelledby="`block-tab-${active}`"
            >
                <div
                    class="mb-3 flex flex-wrap items-center justify-between gap-3"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <template v-if="active === 'current'">
                            <Button
                                size="sm"
                                :disabled="
                                    !selection.size || loading || unlocking
                                "
                                @click="
                                    unlockRows(
                                        rows.filter((row) =>
                                            selection.has(blockRowKey(row)),
                                        ),
                                    )
                                "
                                ><Unlock
                                    data-icon="inline-start"
                                />解锁IP</Button
                            >
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="unlocking"
                                @click="
                                    siteId = '';
                                    unlockError = '';
                                    siteDialog = true;
                                "
                                >解锁网站</Button
                            >
                        </template>
                        <Button
                            v-if="active !== 'stats'"
                            size="sm"
                            :variant="
                                active === 'history' ? 'default' : 'outline'
                            "
                            :disabled="exporting || loading"
                            @click="exportIps"
                            ><Spinner
                                v-if="exporting"
                                data-icon="inline-start"
                            /><Download
                                v-else
                                data-icon="inline-start"
                            />导出黑名单</Button
                        >
                        <Button
                            v-if="active === 'stats'"
                            size="sm"
                            :disabled="loading"
                            @click="load()"
                            ><RefreshCw data-icon="inline-start" />刷新</Button
                        >
                    </div>

                    <form
                        v-if="active !== 'stats'"
                        class="flex w-full min-w-0 flex-wrap items-center gap-2 xl:w-auto"
                        @submit.prevent="load(1)"
                    >
                        <div class="block-log-filter">
                            <Label for="block-ip">IP地址</Label
                            ><Input
                                id="block-ip"
                                v-model="filters.ip"
                                placeholder="请输入IP地址"
                            />
                        </div>
                        <div class="block-log-filter">
                            <Label for="block-site">网站ID</Label
                            ><Input
                                id="block-site"
                                v-model="filters.site_id"
                                inputmode="numeric"
                                placeholder="请输入网站ID"
                            />
                        </div>
                        <div
                            v-if="active === 'current'"
                            class="block-log-filter"
                        >
                            <Label for="block-filter">过滤器</Label
                            ><Input
                                id="block-filter"
                                v-model="filters.filter_name"
                                placeholder="请输入过滤器ID"
                            />
                        </div>
                        <PopoverRoot v-else v-model:open="rangeOpen">
                            <PopoverTrigger as-child
                                ><Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="max-w-full font-normal"
                                    aria-label="选择时间范围"
                                    ><CalendarDays
                                        data-icon="inline-start"
                                    /><span class="truncate">{{
                                        historyFilters.start &&
                                        historyFilters.end
                                            ? `${historyFilters.start.replace('T', ' ')} — ${historyFilters.end.replace('T', ' ')}`
                                            : '选择时间范围'
                                    }}</span></Button
                                ></PopoverTrigger
                            >
                            <PopoverPortal
                                ><PopoverContent
                                    align="end"
                                    :side-offset="8"
                                    class="z-50 w-80 max-w-[calc(100vw-2rem)] rounded-lg border bg-popover p-4 text-popover-foreground shadow-lg"
                                    aria-label="历史拉黑时间范围"
                                >
                                    <div class="mb-4 flex gap-2">
                                        <Button
                                            v-for="days in [1, 7, 30]"
                                            :key="days"
                                            size="sm"
                                            variant="outline"
                                            @click="presetRange(days)"
                                            >{{
                                                days === 1
                                                    ? '今天'
                                                    : `近${days}天`
                                            }}</Button
                                        >
                                    </div>
                                    <div class="grid gap-3">
                                        <div class="grid gap-1.5">
                                            <Label for="block-start"
                                                >开始时间</Label
                                            ><Input
                                                id="block-start"
                                                v-model="rangeStart"
                                                type="datetime-local"
                                                step="1"
                                            />
                                        </div>
                                        <div class="grid gap-1.5">
                                            <Label for="block-end"
                                                >结束时间</Label
                                            ><Input
                                                id="block-end"
                                                v-model="rangeEnd"
                                                type="datetime-local"
                                                step="1"
                                            />
                                        </div>
                                    </div>
                                    <p
                                        v-if="rangeError"
                                        role="alert"
                                        class="mt-3 text-sm text-destructive"
                                    >
                                        {{ rangeError }}
                                    </p>
                                    <div class="mt-4 flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="
                                                rangeStart = '';
                                                rangeEnd = '';
                                                applyRange();
                                            "
                                            >清除时间</Button
                                        ><Button size="sm" @click="applyRange"
                                            >应用时间</Button
                                        >
                                    </div>
                                </PopoverContent></PopoverPortal
                            >
                        </PopoverRoot>
                        <Button
                            type="button"
                            variant="link"
                            size="sm"
                            @click="clearFilters"
                            >清除</Button
                        >
                        <Button
                            type="submit"
                            variant="ghost"
                            size="icon"
                            aria-label="刷新拉黑日志"
                            :disabled="loading"
                            ><RefreshCw
                                class="size-4"
                                :class="{ 'animate-spin': loading }"
                        /></Button>
                    </form>
                </div>

                <div class="max-w-full overflow-x-auto" :aria-busy="loading">
                    <table
                        class="block-log-table w-full text-left text-sm"
                        :class="
                            active === 'stats'
                                ? 'min-w-[480px]'
                                : 'min-w-[1100px]'
                        "
                    >
                        <caption class="sr-only">
                            {{
                                tabs.find((tab) => tab.key === active)?.label
                            }}
                        </caption>
                        <thead class="bg-muted/40 text-muted-foreground">
                            <tr>
                                <template v-if="active === 'stats'"
                                    ><th>排行</th>
                                    <th>网站ID</th>
                                    <th>黑名单数量</th></template
                                >
                                <template v-else>
                                    <th
                                        v-if="active === 'current'"
                                        class="w-10 text-center"
                                    >
                                        <Checkbox
                                            :model-value="allSelected"
                                            aria-label="选择全部IP"
                                            :disabled="loading"
                                            @update:model-value="selectAll"
                                        />
                                    </th>
                                    <th>网站ID</th>
                                    <th>域名</th>
                                    <th>IP</th>
                                    <th>位置</th>
                                    <th>过滤器</th>
                                    <th>拉黑时间</th>
                                    <template v-if="active === 'current'"
                                        ><th>解锁时间</th>
                                        <th>操作</th></template
                                    >
                                    <th v-else>手动解锁?</th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="error">
                                <td
                                    :colspan="colSpan"
                                    class="h-24 text-center text-destructive"
                                    role="alert"
                                >
                                    {{ error }}
                                    <Button
                                        variant="link"
                                        size="sm"
                                        @click="load()"
                                        >重试</Button
                                    >
                                </td>
                            </tr>
                            <tr v-else-if="loading">
                                <td :colspan="colSpan" class="h-24 text-center">
                                    <Spinner class="mx-auto" /><span
                                        class="sr-only"
                                        >加载中</span
                                    >
                                </td>
                            </tr>
                            <template v-else>
                                <tr
                                    v-for="(row, index) in visibleRows"
                                    :key="
                                        active === 'stats'
                                            ? String(row.site_id)
                                            : blockRowKey(row)
                                    "
                                    class="hover:bg-muted/25"
                                >
                                    <template v-if="active === 'stats'"
                                        ><td>
                                            {{
                                                (page - 1) * pageSize +
                                                index +
                                                1
                                            }}
                                        </td>
                                        <td>{{ row.site_id ?? '-' }}</td>
                                        <td>
                                            {{ row.count ?? '-' }}
                                        </td></template
                                    >
                                    <template v-else>
                                        <td
                                            v-if="active === 'current'"
                                            class="text-center"
                                        >
                                            <Checkbox
                                                :model-value="
                                                    selection.has(
                                                        blockRowKey(row),
                                                    )
                                                "
                                                :aria-label="`选择IP ${row.ip}`"
                                                :disabled="unlocking"
                                                @update:model-value="
                                                    selectRow(row, $event)
                                                "
                                            />
                                        </td>
                                        <td>{{ row.site_id ?? '-' }}</td>
                                        <td>
                                            <span
                                                class="block max-w-52 truncate"
                                                :title="
                                                    String(row.domain ?? '')
                                                "
                                                >{{ row.domain ?? '' }}</span
                                            >
                                        </td>
                                        <td class="tabular-nums">
                                            {{ row.ip ?? '-' }}
                                        </td>
                                        <td>{{ row.position ?? '-' }}</td>
                                        <td>
                                            {{
                                                blockFilterLabel(
                                                    row,
                                                    active === 'history',
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap tabular-nums"
                                        >
                                            {{ blockTimestamp(row.create_at) }}
                                        </td>
                                        <template v-if="active === 'current'"
                                            ><td
                                                class="whitespace-nowrap tabular-nums"
                                            >
                                                {{ blockTimestamp(row.exp) }}
                                            </td>
                                            <td>
                                                <div
                                                    class="flex items-center gap-2 whitespace-nowrap"
                                                >
                                                    <Button
                                                        variant="link"
                                                        size="sm"
                                                        class="h-auto p-0"
                                                        :disabled="unlocking"
                                                        @click="
                                                            unlockRows([row])
                                                        "
                                                        >解锁</Button
                                                    ><Link
                                                        :href="`/console/admin/analytics/logs?addr=${encodeURIComponent(String(row.ip ?? ''))}`"
                                                        class="text-primary hover:underline"
                                                        >查看日志</Link
                                                    >
                                                </div>
                                            </td></template
                                        >
                                        <td v-else>
                                            {{
                                                manualUnlockLabel(
                                                    row.auto_unlock,
                                                )
                                            }}
                                        </td>
                                    </template>
                                </tr>
                                <tr v-if="!visibleRows.length">
                                    <td
                                        :colspan="colSpan"
                                        class="h-24 text-center text-muted-foreground"
                                    >
                                        暂无记录
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <nav
                    class="mt-4 flex flex-wrap items-center justify-end gap-2 text-sm text-muted-foreground"
                    aria-label="拉黑日志分页"
                >
                    <span class="mr-1" aria-live="polite"
                        >共 {{ total }} 条</span
                    >
                    <Button
                        size="icon"
                        variant="outline"
                        aria-label="上一页"
                        :disabled="loading || page <= 1"
                        @click="changePage(page - 1)"
                        ><ChevronLeft class="size-4"
                    /></Button>
                    <Button
                        v-for="number in pageButtons"
                        :key="number"
                        size="icon"
                        variant="outline"
                        :aria-label="`第 ${number} 页`"
                        :aria-current="page === number ? 'page' : undefined"
                        :disabled="loading"
                        :class="{
                            'border-primary text-primary': page === number,
                        }"
                        @click="changePage(number)"
                        >{{ number }}</Button
                    >
                    <Button
                        size="icon"
                        variant="outline"
                        aria-label="下一页"
                        :disabled="loading || page >= lastPage"
                        @click="changePage(page + 1)"
                        ><ChevronRight class="size-4"
                    /></Button>
                    <select
                        v-model.number="pageSize"
                        aria-label="每页条数"
                        class="h-8 rounded-md border border-input bg-background px-2 text-foreground"
                        :disabled="loading"
                        @change="load(1)"
                    >
                        <option
                            v-for="size in [10, 30, 100, 300]"
                            :key="size"
                            :value="size"
                        >
                            {{ size }} 条/页
                        </option>
                    </select>
                </nav>
            </div>
        </section>
        <Dialog v-model:open="siteDialog"
            ><DialogContent class="sm:max-w-sm"
                ><DialogHeader
                    ><DialogTitle>解锁指定网站黑名单</DialogTitle
                    ><DialogDescription
                        >提交该网站全部被拉黑IP的解锁任务。</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-4" @submit.prevent="unlockSite">
                    <div class="grid gap-2">
                        <Label for="unlock-site-id">网站ID</Label
                        ><Input
                            id="unlock-site-id"
                            v-model="siteId"
                            placeholder="请输入网站ID"
                            inputmode="numeric"
                            :disabled="unlocking"
                        />
                    </div>
                    <p
                        v-if="unlockError"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ unlockError }}
                    </p>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="unlocking"
                            @click="siteDialog = false"
                            >取消</Button
                        ><Button type="submit" :disabled="unlocking"
                            ><Spinner
                                v-if="unlocking"
                                data-icon="inline-start"
                            />确定</Button
                        ></DialogFooter
                    >
                </form></DialogContent
            ></Dialog
        >
    </div>
</template>

<style scoped>
.block-log-table th {
    padding: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}
.block-log-table td {
    padding: 0.875rem 0.75rem;
}
.block-log-table tr {
    border-bottom: 1px solid var(--border);
}
.block-log-filter {
    display: flex;
    align-items: center;
    width: 14rem;
    max-width: 100%;
    border: 1px solid var(--input);
    border-radius: 0.375rem;
    overflow: hidden;
    background: var(--background);
}
.block-log-filter label {
    flex-shrink: 0;
    padding: 0.5rem;
    border-right: 1px solid var(--input);
    background: var(--muted);
    color: var(--muted-foreground);
    font-weight: 400;
}
.block-log-filter :deep(input) {
    min-width: 0;
    height: 2rem;
    border: 0;
    border-radius: 0;
    box-shadow: none;
}
.block-log-filter:focus-within {
    outline: 2px solid var(--ring);
    outline-offset: 1px;
}
@media (max-width: 639px) {
    .block-log-filter {
        width: 100%;
    }
}
</style>
