<script setup lang="ts">
import { AlertCircle, RefreshCw, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    extractCdnflyRows as extractRows,
    extractCdnflyTotal as extractTotal,
} from '@/lib/cdnflyResponse';
import type { CdnflyListData, CdnflyRecord } from '@/lib/sharedTypes';

export type ColumnDef = {
    /**
     * The field to read. Used as-is for the slot name and as the first
     * candidate when `altKeys` is present.
     */
    key: string;
    /**
     * Fallback field names, tried in order when `key` is absent.
     *
     * CDNfly and Laravel disagree on spelling for the same concepts —
     * create_at vs created_at, uid vs user_id, enable vs status — and a column
     * pointed at the wrong one renders a dash on a fully populated record
     * while looking like missing data. Listing the alternatives makes a table
     * survive either source instead of silently lying.
     */
    altKeys?: string[];
    label: string;
    width?: string;
    align?: 'left' | 'center' | 'right';
    format?: (value: unknown, row: CdnflyRecord) => string;
    badge?: boolean;
    badgeVariant?: (
        value: unknown,
        row: CdnflyRecord,
    ) => 'secondary' | 'outline' | 'destructive';
};

const props = withDefaults(
    defineProps<{
        title: string;
        icon?: object;
        columns: ColumnDef[];
        fetchFn?: (
            params: Record<string, string | number>,
        ) => Promise<CdnflyListData | CdnflyRecord[]>;
        searchPlaceholder?: string;
        searchParams?: Record<string, string | number>;
        searchKey?: string;
        clientSide?: boolean;
        selectable?: boolean;
        showActions?: boolean;
        pageSize?: number;
        pageSizeOptions?: number[];
        emptyText?: string;
        errorTitle?: string;
        /** Embedded tables use their page's existing fetch, filters and pagination. */
        embedded?: boolean;
        data?: {
            rows: CdnflyRecord[];
            total: number;
            page: number;
            pageSize: number;
            loading: boolean;
        };
        selected?: (string | number)[];
        selectionDisabled?: boolean;
        getRowKey?: (row: CdnflyRecord) => string | number;
    }>(),
    {
        icon: undefined,
        searchPlaceholder: '搜索',
        searchParams: undefined,
        searchKey: 'search',
        clientSide: false,
        selectable: false,
        showActions: true,
        pageSize: 20,
        pageSizeOptions: () => [20, 50],
        emptyText: '暂无记录',
        errorTitle: '加载失败',
        embedded: false,
        selectionDisabled: false,
    },
);

const emit = defineEmits<{
    'row-click': [row: CdnflyRecord];
    'update:selected': [keys: (string | number)[]];
}>();

const loading = ref(false);
const errorMessage = ref('');
const rows = ref<CdnflyRecord[]>([]);
const total = ref(0);
const currentPage = ref(1);
const perPage = ref(String(props.pageSize));
const searchText = ref('');
const selectedIds = ref<Set<string | number>>(new Set());

const effectivePerPage = computed(() => Number(perPage.value));
const hasPreviousPage = computed(() => currentPage.value > 1);
const hasNextPage = computed(
    () => currentPage.value * effectivePerPage.value < total.value,
);
const paginationText = computed(() =>
    total.value === 0 ? props.emptyText : `${total.value} 条记录`,
);
const allSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selectedIds.value.has(rowKey(row))),
);

onMounted(() => {
    if (!props.data) {
        void loadData();
    }
});

watch(
    () => props.data,
    (data) => {
        if (!data) {
            return;
        }

        rows.value = data.rows;
        total.value = data.total;
        currentPage.value = data.page;
        perPage.value = String(data.pageSize);
        loading.value = data.loading;
    },
    { immediate: true, deep: true },
);
watch(
    () => props.selected,
    (keys) => {
        if (keys) {
            selectedIds.value = new Set(keys);
        }
    },
    { immediate: true, deep: true },
);

watch(
    () => [props.searchParams, props.fetchFn],
    () => {
        currentPage.value = 1;
        void loadData();
    },
    { deep: true },
);

async function loadData(targetPage = currentPage.value): Promise<void> {
    if (props.data || !props.fetchFn) {
        return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            ...(props.clientSide
                ? {}
                : { page: targetPage, limit: effectivePerPage.value }),
            ...(props.searchParams ?? {}),
        };

        const search = searchText.value.trim();

        if (search !== '' && !props.clientSide) {
            params[props.searchKey] = search;
        }

        const result = await props.fetchFn(params);
        const records = extractRows(result);

        if (props.clientSide) {
            const matches = records.filter(
                (row) =>
                    !search ||
                    Object.values(row).some((value) =>
                        String(value ?? '')
                            .toLowerCase()
                            .includes(search.toLowerCase()),
                    ),
            );
            total.value = matches.length;
            rows.value = matches.slice(
                (targetPage - 1) * effectivePerPage.value,
                targetPage * effectivePerPage.value,
            );
        } else {
            rows.value = records;
            total.value = extractTotal(result, records.length);
        }

        currentPage.value = targetPage;
    } catch (error) {
        rows.value = [];
        total.value = 0;
        errorMessage.value =
            error instanceof Error ? error.message : '请求失败';
    } finally {
        loading.value = false;
    }
}

function submitSearch(): void {
    currentPage.value = 1;
    selectedIds.value.clear();
    void loadData(1);
}

function refresh(): void {
    selectedIds.value.clear();
    void loadData();
}

function nextPage(): void {
    if (hasNextPage.value) {
        void loadData(currentPage.value + 1);
    }
}

function prevPage(): void {
    if (hasPreviousPage.value) {
        void loadData(currentPage.value - 1);
    }
}

function toggleSelectAll(checked: boolean): void {
    if (checked) {
        rows.value.forEach((row) => selectedIds.value.add(rowKey(row)));
    } else {
        selectedIds.value.clear();
    }

    emit('update:selected', [...selectedIds.value]);
}

function toggleRow(row: CdnflyRecord): void {
    const key = rowKey(row);

    if (selectedIds.value.has(key)) {
        selectedIds.value.delete(key);
    } else {
        selectedIds.value.add(key);
    }

    emit('update:selected', [...selectedIds.value]);
}

function isRowSelected(row: CdnflyRecord): boolean {
    return selectedIds.value.has(rowKey(row));
}

function rowKey(row: CdnflyRecord): string | number {
    if (props.getRowKey) {
        return props.getRowKey(row);
    }

    if (typeof row.id === 'number' || typeof row.id === 'string') {
        return row.id;
    }

    return JSON.stringify(row);
}

/** The first of key/altKeys that the row actually carries. */
function rawValue(row: CdnflyRecord, col: ColumnDef): unknown {
    const value = row[col.key];

    if (value !== undefined && value !== null) {
        return value;
    }

    for (const alt of col.altKeys ?? []) {
        const fallback = row[alt];

        if (fallback !== undefined && fallback !== null) {
            return fallback;
        }
    }

    return value;
}

function cellValue(row: CdnflyRecord, col: ColumnDef): string {
    const raw = rawValue(row, col);

    if (col.format) {
        return col.format(raw, row);
    }

    if (raw === null || raw === undefined) {
        return '-';
    }

    return String(raw);
}

function cellBadgeVariant(
    row: CdnflyRecord,
    col: ColumnDef,
): 'secondary' | 'outline' | 'destructive' {
    if (col.badgeVariant) {
        return col.badgeVariant(rawValue(row, col), row);
    }

    return 'secondary';
}

function colStyle(col: ColumnDef): Record<string, string> {
    return col.width ? { width: col.width } : {};
}

function colAlign(col: ColumnDef): string {
    if (col.align === 'center') {
        return 'text-center';
    }

    if (col.align === 'right') {
        return 'text-right';
    }

    return 'text-left';
}

defineExpose({
    reload: loadData,
    refresh,
    rows,
    selectedIds,
    loading,
    errorMessage,
});
</script>

<template>
    <Alert v-if="errorMessage" variant="destructive">
        <AlertCircle data-icon="alert" />
        <AlertTitle>{{ errorTitle }}</AlertTitle>
        <AlertDescription>{{ errorMessage }}</AlertDescription>
    </Alert>

    <Card
        class="gap-0 overflow-hidden"
        :class="{ 'rounded-none border-0 py-0 shadow-none': embedded }"
    >
        <CardHeader v-if="!embedded">
            <!--
                One row, not two. Search and the page's own actions used to sit on
                a second line under the title while 刷新 sat alone on the first,
                which read as a stray strip of controls and wasted a whole row of
                height on every table in the console. Everything now shares the
                title row and packs to the right, wrapping under it only when the
                viewport is too narrow to hold it.
            -->
            <div
                class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex items-center gap-2">
                    <div
                        v-if="icon"
                        class="flex size-7 items-center justify-center rounded-sm border border-primary/20 bg-primary/5 text-primary"
                    >
                        <component :is="icon" class="size-3.5" />
                    </div>
                    <CardTitle class="text-base">{{ title }}</CardTitle>
                    <Badge v-if="total > 0" variant="outline">
                        {{ paginationText }}
                    </Badge>
                </div>
                <!--
                    CardHeader is a single-column grid, so anything dropped
                    straight into the toolbar slot became a grid item and
                    stretched to the full width of the card — a lone 新增 button
                    came out as wide as the screen. This flex row gives every
                    child its natural width instead.
                -->
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                    <slot
                        name="toolbar"
                        :loading="loading"
                        :submit-search="submitSearch"
                    >
                        <slot
                            name="search-fields"
                            :loading="loading"
                            :submit-search="submitSearch"
                        >
                            <!--
                            Capped rather than 1fr: on a wide monitor a full-width
                            search box left the 搜索 button stranded at the far
                            edge, far from the field it acts on.
                        -->
                            <div class="relative w-full sm:w-72">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    v-model="searchText"
                                    class="pl-9"
                                    :placeholder="searchPlaceholder"
                                    @keyup.enter="submitSearch"
                                />
                            </div>
                        </slot>
                        <Button :disabled="loading" @click="submitSearch">
                            <Spinner v-if="loading" data-icon="inline-start" />
                            <Search v-else data-icon="inline-start" />
                            搜索
                        </Button>
                        <Select v-model="perPage">
                            <SelectTrigger class="w-28">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="size in pageSizeOptions"
                                        :key="size"
                                        :value="String(size)"
                                    >
                                        {{ size }} 条
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <slot
                            name="toolbar-actions"
                            :loading="loading"
                            :selected-ids="selectedIds"
                            :refresh="refresh"
                        />
                    </slot>

                    <slot
                        name="toolbar-end"
                        :loading="loading"
                        :refresh="refresh"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="loading"
                            @click="refresh"
                        >
                            <Spinner v-if="loading" data-icon="inline-start" />
                            <RefreshCw v-else data-icon="inline-start" />
                            刷新
                        </Button>
                    </slot>
                </div>
            </div>
        </CardHeader>

        <CardContent class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[780px] text-sm">
                    <colgroup>
                        <col v-if="selectable" style="width: 48px" />
                        <col
                            v-for="col in columns"
                            :key="col.key"
                            :style="colStyle(col)"
                        />
                        <slot v-if="showActions" name="actions-col">
                            <col style="width: 12%" />
                        </slot>
                    </colgroup>
                    <thead class="border-y bg-muted/50 text-muted-foreground">
                        <tr>
                            <th v-if="selectable" class="px-3 py-2 text-center">
                                <Checkbox
                                    aria-label="选择全部"
                                    :model-value="allSelected"
                                    :disabled="
                                        loading ||
                                        selectionDisabled ||
                                        !rows.length
                                    "
                                    @update:model-value="
                                        toggleSelectAll($event === true)
                                    "
                                />
                            </th>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="px-3 py-2 font-medium"
                                :class="colAlign(col)"
                            >
                                {{ col.label }}
                            </th>
                            <slot v-if="showActions" name="actions-header">
                                <th class="px-3 py-2 text-right font-medium">
                                    操作
                                </th>
                            </slot>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading && rows.length === 0">
                            <td
                                class="px-6 py-16 text-center"
                                :colspan="
                                    columns.length +
                                    (selectable ? 1 : 0) +
                                    (showActions ? 1 : 0)
                                "
                            >
                                <Spinner />
                            </td>
                        </tr>
                        <tr
                            v-for="(row, idx) in rows"
                            :key="rowKey(row) ?? idx"
                            class="cursor-pointer border-b last:border-b-0 hover:bg-muted/30"
                            @click="emit('row-click', row)"
                        >
                            <td
                                v-if="selectable"
                                class="px-3 py-2.5 text-center"
                                @click.stop
                            >
                                <Checkbox
                                    :aria-label="`选择 ${rowKey(row)}`"
                                    :model-value="isRowSelected(row)"
                                    :disabled="loading || selectionDisabled"
                                    @update:model-value="toggleRow(row)"
                                />
                            </td>
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                class="px-3 py-2.5"
                                :class="colAlign(col)"
                            >
                                <slot
                                    :name="`cell-${col.key}`"
                                    :row="row"
                                    :value="rawValue(row, col)"
                                    :formatted="cellValue(row, col)"
                                >
                                    <Badge
                                        v-if="col.badge"
                                        :variant="cellBadgeVariant(row, col)"
                                    >
                                        {{ cellValue(row, col) }}
                                    </Badge>
                                    <span v-else>{{
                                        cellValue(row, col)
                                    }}</span>
                                </slot>
                            </td>
                            <td
                                v-if="showActions"
                                class="px-3 py-2.5"
                                @click.stop
                            >
                                <div class="flex justify-end gap-1.5">
                                    <slot name="row-actions" :row="row" />
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!loading && rows.length === 0">
                            <td
                                class="px-6 py-16 text-center text-muted-foreground"
                                :colspan="
                                    columns.length +
                                    (selectable ? 1 : 0) +
                                    (showActions ? 1 : 0)
                                "
                            >
                                {{ emptyText }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>

    <ConsolePagination
        v-if="!embedded"
        :total="total"
        :page="currentPage"
        :previous-disabled="!hasPreviousPage || loading"
        :next-disabled="!hasNextPage || loading"
        @previous="prevPage"
        @next="nextPage"
    />
</template>
