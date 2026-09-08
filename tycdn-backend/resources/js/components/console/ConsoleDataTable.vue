<script setup lang="ts">
import { AlertCircle, RefreshCw, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
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
import type { CdnflyListData, CdnflyRecord } from '@/lib/sharedTypes';

export type ColumnDef = {
    key: string;
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
        fetchFn: (
            params: Record<string, string | number>,
        ) => Promise<CdnflyListData | CdnflyRecord[]>;
        searchPlaceholder?: string;
        searchParams?: Record<string, string | number>;
        selectable?: boolean;
        pageSize?: number;
        pageSizeOptions?: number[];
        emptyText?: string;
        errorTitle?: string;
    }>(),
    {
        icon: undefined,
        searchPlaceholder: '搜索',
        searchParams: undefined,
        selectable: false,
        pageSize: 20,
        pageSizeOptions: () => [20, 50],
        emptyText: '暂无记录',
        errorTitle: '加载失败',
    },
);

const emit = defineEmits<{
    'row-click': [row: CdnflyRecord];
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
    void loadData();
});

watch(
    () => props.searchParams,
    () => {
        currentPage.value = 1;
        void loadData();
    },
    { deep: true },
);

async function loadData(targetPage = currentPage.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: effectivePerPage.value,
            ...(props.searchParams ?? {}),
        };

        const search = searchText.value.trim();

        if (search !== '') {
            params.search = search;
        }

        const result = await props.fetchFn(params);
        rows.value = extractRows(result);
        total.value = extractTotal(result, rows.value.length);
        currentPage.value = targetPage;
    } catch (error) {
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
}

function toggleRow(row: CdnflyRecord): void {
    const key = rowKey(row);

    if (selectedIds.value.has(key)) {
        selectedIds.value.delete(key);
    } else {
        selectedIds.value.add(key);
    }
}

function isRowSelected(row: CdnflyRecord): boolean {
    return selectedIds.value.has(rowKey(row));
}

function rowKey(row: CdnflyRecord): string | number {
    if (typeof row.id === 'number' || typeof row.id === 'string') {
        return row.id;
    }

    return JSON.stringify(row);
}

function cellValue(row: CdnflyRecord, col: ColumnDef): string {
    const raw = row[col.key];

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
        return col.badgeVariant(row[col.key], row);
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

function extractRows(result: unknown): CdnflyRecord[] {
    if (Array.isArray(result)) {
        return result.filter(isRecord);
    }

    if (!isRecord(result)) {
        return [];
    }

    for (const key of ['data', 'items', 'list', 'rows', 'records']) {
        const value = result[key];

        if (Array.isArray(value)) {
            return value.filter(isRecord);
        }

        if (isRecord(value)) {
            const nested = extractRows(value);

            if (nested.length > 0) {
                return nested;
            }
        }
    }

    return [];
}

function extractTotal(result: unknown, fallback: number): number {
    if (!isRecord(result)) {
        return fallback;
    }

    if (typeof result.total === 'number') {
        return result.total;
    }

    if (isRecord(result.meta) && typeof result.meta.total === 'number') {
        return result.meta.total;
    }

    if (isRecord(result.data) && typeof result.data.total === 'number') {
        return result.data.total;
    }

    return fallback;
}

function isRecord(value: unknown): value is CdnflyRecord {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
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

    <Card class="gap-0 overflow-hidden">
        <CardHeader class="space-y-4">
            <div
                class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        v-if="icon"
                        class="flex size-10 items-center justify-center rounded-md border bg-card"
                    >
                        <component :is="icon" class="size-5" />
                    </div>
                    <CardTitle class="text-base">{{ title }}</CardTitle>
                    <Badge v-if="total > 0" variant="outline">
                        {{ paginationText }}
                    </Badge>
                </div>
                <slot name="toolbar-end" :loading="loading" :refresh="refresh">
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

            <!--
                CardHeader is a single-column grid, so anything dropped straight
                into the toolbar slot stretched to the full width of the card — a
                lone 新增 button came out as a button the width of the screen.
                Wrapping the slot in a flex row gives every child its natural
                width and packs them to the left, whatever a page puts in here.
            -->
            <div class="flex flex-wrap items-center gap-2">
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
                        <slot name="actions-col">
                            <col style="width: 12%" />
                        </slot>
                    </colgroup>
                    <thead class="border-y bg-muted/50 text-muted-foreground">
                        <tr>
                            <th v-if="selectable" class="px-3 py-3 text-center">
                                <Checkbox
                                    :checked="allSelected"
                                    @update:checked="toggleSelectAll"
                                />
                            </th>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="px-4 py-3 font-medium"
                                :class="colAlign(col)"
                            >
                                {{ col.label }}
                            </th>
                            <slot name="actions-header">
                                <th class="px-4 py-3 text-right font-medium">
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
                                    columns.length + (selectable ? 1 : 0) + 1
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
                                class="px-3 py-4 text-center"
                                @click.stop
                            >
                                <Checkbox
                                    :checked="isRowSelected(row)"
                                    @update:checked="toggleRow(row)"
                                />
                            </td>
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                class="px-4 py-4"
                                :class="colAlign(col)"
                            >
                                <slot
                                    :name="`cell-${col.key}`"
                                    :row="row"
                                    :value="row[col.key]"
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
                            <td class="px-4 py-4" @click.stop>
                                <div class="flex justify-end gap-1.5">
                                    <slot name="row-actions" :row="row" />
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!loading && rows.length === 0">
                            <td
                                class="px-6 py-16 text-center text-muted-foreground"
                                :colspan="
                                    columns.length + (selectable ? 1 : 0) + 1
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

    <div
        v-if="total > effectivePerPage"
        class="flex items-center justify-end gap-2"
    >
        <Button
            variant="outline"
            size="sm"
            :disabled="!hasPreviousPage || loading"
            @click="prevPage"
        >
            上一页
        </Button>
        <span class="text-sm text-muted-foreground">
            第 {{ currentPage }} 页
        </span>
        <Button
            variant="outline"
            size="sm"
            :disabled="!hasNextPage || loading"
            @click="nextPage"
        >
            下一页
        </Button>
    </div>
</template>
