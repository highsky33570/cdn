<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows } from '@/lib/cdnflyResponse';
import { getErrorMessage, numberValue } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';

function date(value: Date) {
    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;
}
const today = new Date();
const previousMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
previousMonth.setDate(
    Math.min(
        today.getDate(),
        new Date(today.getFullYear(), today.getMonth(), 0).getDate(),
    ),
);
const uid = ref(''),
    group = ref('day'),
    start = ref(date(previousMonth)),
    end = ref(date(today));
const page = ref(1),
    size = ref(10),
    total = ref(0),
    rows = ref<CdnflyRecord[]>([]),
    loading = ref(false),
    error = ref('');
const columns = [
    { key: 'time', label: '时间', width: '53%' },
    {
        key: 'sum',
        label: '金额',
        format: (value: unknown) => {
            // This endpoint returns yuan, unlike the order list's integer cents.
            const amount = numberValue(value);

            return amount === null ? '—' : `${amount.toFixed(2)}元`;
        },
    },
];
const countLabel = computed(() =>
    loading.value || error.value ? '—' : total.value,
);
let token = 0,
    timer: ReturnType<typeof setTimeout> | undefined;
async function load() {
    clearTimeout(timer);
    const request = ++token;
    loading.value = true;
    error.value = '';
    rows.value = [];

    try {
        const user = uid.value.trim();

        if (user && !/^\d+$/.test(user)) {
            throw new Error('用户ID请填写数字');
        }

        const query = new URLSearchParams({
            page: String(page.value),
            limit: String(size.value),
            type: '充值',
            state: '已付款',
            group_by: group.value,
        });

        if (user) {
            query.set('uid', user);
        }

        if (start.value && end.value) {
            const first = start.value.slice(0, 10),
                last = end.value.slice(0, 10);
            const firstDay = new Date(`${first}T00:00:00`),
                lastDay = new Date(`${last}T00:00:00`);

            if (
                !Number.isFinite(firstDay.getTime()) ||
                !Number.isFinite(lastDay.getTime()) ||
                date(firstDay) !== first ||
                date(lastDay) !== last ||
                lastDay < firstDay
            ) {
                throw new Error('请选择有效的时间范围');
            }

            lastDay.setDate(lastDay.getDate() + 1);
            query.set('start', first);
            query.set('end', date(lastDay));
        }

        const result = await apiRequest<CdnflyRecord>(
            `/api/admin/workspace/recharge-count?${query}`,
        );

        if (request !== token) {
            return;
        }

        rows.value = extractCdnflyRows(result);
        total.value = numberValue(result.count) ?? rows.value.length;
        const lastPage = Math.max(1, Math.ceil(total.value / size.value));

        if (page.value > lastPage) {
            page.value = lastPage;
        }
    } catch (e) {
        if (request === token) {
            error.value = getErrorMessage(e);
            total.value = 0;
        }
    } finally {
        if (request === token) {
            loading.value = false;
        }
    }
}
function search() {
    clearTimeout(timer);

    if (page.value === 1) {
        void load();
    } else {
        page.value = 1;
    }
}
watch([group, start, end, size], search);
watch(page, load);
watch(uid, () => {
    ++token;
    loading.value = true;
    error.value = '';
    rows.value = [];
    clearTimeout(timer);
    timer = setTimeout(search, 300);
});
onMounted(load);
onUnmounted(() => {
    ++token;
    clearTimeout(timer);
});
</script>

<template>
    <div class="recharge-stats-workspace min-w-0 p-4 md:p-6">
        <section
            class="console-panel rounded-xl border bg-card p-4 text-card-foreground md:p-5"
            aria-labelledby="recharge-stats-title"
        >
            <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2
                        data-typography="page-title"
                        id="recharge-stats-title"
                        class="font-semibold"
                    >
                        充值统计
                    </h2>
                    <p
                        data-typography="description"
                        class="mt-1 text-muted-foreground"
                        aria-live="polite"
                    >
                        共 {{ countLabel }} 条汇总记录
                    </p>
                </div>
                <form
                    class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:w-auto"
                    @submit.prevent="search"
                >
                    <Input
                        v-model="uid"
                        aria-label="用户ID"
                        placeholder="用户ID"
                        inputmode="numeric"
                        autocomplete="off"
                        class="w-full sm:w-52"
                    />
                    <Select v-model="group"
                        ><SelectTrigger
                            aria-label="汇总方式"
                            class="w-full sm:w-52"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent
                            ><SelectItem value="day">按天汇总</SelectItem
                            ><SelectItem value="month">按月汇总</SelectItem
                            ><SelectItem value="year"
                                >按年汇总</SelectItem
                            ></SelectContent
                        ></Select
                    >
                    <DateRangePicker
                        v-model:start="start"
                        v-model:end="end"
                        placeholder="请选择时间范围"
                        trigger-class="h-9 w-full justify-between sm:w-auto"
                    />
                </form>
            </div>
            <Alert v-if="error" variant="destructive" class="mb-4"
                ><AlertDescription
                    >{{ error
                    }}<Button
                        variant="outline"
                        size="sm"
                        class="ml-2"
                        @click="load"
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <div class="stats-table min-w-0">
                <ConsoleDataTable
                    title="充值统计"
                    embedded
                    :show-actions="false"
                    :columns="columns"
                    :data="{ rows, total, page, pageSize: size, loading }"
                    :empty-text="error ? '统计加载失败' : '暂无数据'"
                    :get-row-key="(row) => String(row.time)"
                />
            </div>
            <PackagePagination
                v-model:page="page"
                v-model:page-size="size"
                :total="total"
                :disabled="loading || !!error"
                numbered
            />
        </section>
    </div>
</template>
<style scoped>
.stats-table :deep(table) {
    min-width: 0;
}
</style>
