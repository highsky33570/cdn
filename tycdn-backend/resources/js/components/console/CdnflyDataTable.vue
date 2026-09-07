<script setup lang="ts">
import { AlertCircle, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

export type ColumnDef = {
    key: string;
    label: string;
    format?: (value: unknown, row: Record<string, unknown>) => string;
    badge?: boolean;
};

const props = defineProps<{
    title: string;
    columns: ColumnDef[];
    fetchFn: (params: Record<string, string | number>) => Promise<unknown>;
    pageSize?: number;
}>();

const loading = ref(false);
const errorMessage = ref('');
const rows = ref<Record<string, unknown>[]>([]);
const total = ref<number | null>(null);
const currentPage = ref(1);

const effectivePageSize = computed(() => props.pageSize ?? 20);

onMounted(() => {
    void loadData();
});

async function loadData(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const result = (await props.fetchFn({
            page: currentPage.value,
            limit: effectivePageSize.value,
        })) as Record<string, unknown>;

        // CDNfly responses vary: { data: [...], total } or just [...]
        if (Array.isArray(result)) {
            rows.value = result;
            total.value = result.length;
        } else if (result && typeof result === 'object') {
            const list =
                (result.data as Record<string, unknown>[]) ??
                (result.items as Record<string, unknown>[]) ??
                (result.list as Record<string, unknown>[]) ??
                (result.rows as Record<string, unknown>[]) ??
                [];
            rows.value = Array.isArray(list) ? list : [];
            total.value =
                typeof result.total === 'number'
                    ? result.total
                    : rows.value.length;
        }
    } catch (error) {
        errorMessage.value =
            error instanceof Error ? error.message : '请求失败';
    } finally {
        loading.value = false;
    }
}

function cellValue(row: Record<string, unknown>, col: ColumnDef): string {
    const raw = row[col.key];

    if (col.format) {
        return col.format(raw, row);
    }

    if (raw === null || raw === undefined) {
        return '-';
    }

    return String(raw);
}

function nextPage(): void {
    currentPage.value++;
    void loadData();
}

function prevPage(): void {
    if (currentPage.value > 1) {
        currentPage.value--;
        void loadData();
    }
}

defineExpose({ reload: loadData });
</script>

<template>
    <Alert v-if="errorMessage" variant="destructive">
        <AlertCircle data-icon="alert" />
        <AlertTitle>{{ title }}加载失败</AlertTitle>
        <AlertDescription>{{ errorMessage }}</AlertDescription>
    </Alert>

    <Card class="gap-0 overflow-hidden">
        <CardHeader
            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex items-center gap-3">
                <CardTitle class="text-base">{{ title }}</CardTitle>
                <Badge v-if="total !== null" variant="outline">
                    {{ total }} 条
                </Badge>
            </div>
            <Button
                variant="outline"
                size="sm"
                :disabled="loading"
                @click="loadData"
            >
                <Spinner v-if="loading" data-icon="inline-start" />
                <RefreshCw v-else data-icon="inline-start" />
                刷新
            </Button>
        </CardHeader>
        <CardContent class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[780px] text-sm">
                    <thead class="border-y bg-muted/50 text-muted-foreground">
                        <tr>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="px-4 py-3 text-left font-medium first:px-6"
                            >
                                {{ col.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading && rows.length === 0">
                            <td
                                class="px-6 py-16 text-center"
                                :colspan="columns.length"
                            >
                                <Spinner />
                            </td>
                        </tr>
                        <tr
                            v-for="(row, idx) in rows"
                            :key="(row.id as string) ?? idx"
                            class="border-b last:border-b-0"
                        >
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                class="px-4 py-4 first:px-6"
                            >
                                <Badge v-if="col.badge" variant="secondary">
                                    {{ cellValue(row, col) }}
                                </Badge>
                                <span v-else>{{ cellValue(row, col) }}</span>
                            </td>
                        </tr>
                        <tr v-if="!loading && rows.length === 0">
                            <td
                                class="px-6 py-16 text-center text-muted-foreground"
                                :colspan="columns.length"
                            >
                                暂无记录
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>

    <div
        v-if="total !== null && total > effectivePageSize"
        class="flex items-center justify-end gap-2"
    >
        <Button
            variant="outline"
            size="sm"
            :disabled="currentPage <= 1 || loading"
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
            :disabled="rows.length < effectivePageSize || loading"
            @click="nextPage"
        >
            下一页
        </Button>
    </div>
</template>
