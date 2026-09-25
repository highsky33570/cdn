<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import RecordDetails from '@/components/console/RecordDetails.vue';
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/date-picker/DatePicker.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRecord } from '@/lib/cdnflyResponse';
import {
    formatDate,
    formatMoney,
    numberValue,
    getErrorMessage,
} from '@/lib/formatters';
import { masterGet } from '@/lib/masterApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const props = defineProps<{ view: 'orders' | 'recharge-count' | 'messages' }>();
const title = computed(
    () =>
        ({
            orders: '所有订单',
            'recharge-count': '充值统计',
            messages: '消息查询',
        })[props.view],
);
const today = new Date(),
    start = new Date(today);
start.setMonth(start.getMonth() - 1);
const tomorrow = new Date(today);
tomorrow.setDate(tomorrow.getDate() + 1);
const date = (value: Date) => formatDate(value.toISOString()).slice(0, 10);
const filters = reactive({
    uid: '',
    type: '',
    state: '',
    start: props.view === 'recharge-count' ? date(start) : '',
    end: props.view === 'recharge-count' ? date(tomorrow) : '',
    group_by: 'day',
    receive: '',
    site_id: '',
    user_package_id: '',
});
const applied = ref<Record<string, string>>({});
const error = ref('');
function apply(): void {
    error.value = '';

    if (filters.start && filters.end && filters.end <= filters.start) {
        error.value = '结束日期必须晚于开始日期';

        return;
    }

    const keys =
        props.view === 'messages'
            ? ['receive', 'type', 'site_id', 'user_package_id']
            : ['uid', 'type', 'state', 'start', 'end'];
    applied.value = Object.fromEntries(
        keys
            .map((key) => [key, filters[key as keyof typeof filters].trim()])
            .filter(([, value]) => value !== ''),
    );

    if (props.view === 'recharge-count') {
        Object.assign(applied.value, {
            type: '充值',
            state: '已付款',
            group_by: filters.group_by,
        });
    }
}
apply();
const cents = (value: unknown) => {
    const amount = numberValue(value);

    return formatMoney(amount === null ? null : amount / 100);
};
const columns = computed<ColumnDef[]>(() =>
    props.view === 'orders'
        ? [
              { key: 'id', label: 'ID' },
              { key: 'uid', label: '用户ID' },
              { key: 'type', label: '类型' },
              { key: 'des', label: '备注' },
              { key: 'amount', label: '原价', format: cents },
              { key: 'real_amount', label: '实际支付', format: cents },
              { key: 'pay_type', label: '支付方式' },
              { key: 'mch_order_no', label: '订单号' },
              { key: 'create_at', label: '创建时间' },
              { key: 'state', label: '状态' },
          ]
        : props.view === 'recharge-count'
          ? [
                { key: 'time', label: '时间' },
                {
                    key: 'sum',
                    label: '金额',
                    format: (value) => formatMoney(value),
                },
            ]
          : [
                { key: 'receive', label: '所属用户ID' },
                { key: 'type', label: '类型' },
                { key: 'title', label: '标题' },
                { key: 'user_package_id', label: '用户套餐ID' },
                { key: 'site_id', label: '网站ID' },
                {
                    key: 'create_at2',
                    altKeys: ['create_at'],
                    label: '创建时间',
                },
            ],
);
const resource = computed(
    () =>
        ({
            orders: 'master-orders',
            'recharge-count': 'recharge-count',
            messages: 'message-query',
        })[props.view],
);
const fetchRecords = (params: Record<string, string | number>) =>
    masterGet(resource.value, params);
const detail = ref<CdnflyRecord | null>(null),
    detailOpen = ref(false),
    detailError = ref('');
async function showDetail(row: CdnflyRecord): Promise<void> {
    detail.value = null;
    detailError.value = '';
    detailOpen.value = true;

    try {
        detail.value = extractCdnflyRecord(
            await apiRequest(
                `/api/admin/workspace/${resource.value}/${row.id}`,
            ),
        );
    } catch (e) {
        detailError.value = getErrorMessage(e);
    }
}
</script>
<template>
    <div class="console-page min-w-0 space-y-4 p-4 md:p-6">
        <form
            class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4"
            @submit.prevent="apply"
        >
            <label class="flex flex-col gap-1 text-sm"
                >用户ID<Input
                    v-if="view === 'messages'"
                    v-model="filters.receive"
                    aria-label="用户ID"
                    class="w-36" /><Input
                    v-else
                    v-model="filters.uid"
                    aria-label="用户ID"
                    class="w-36"
            /></label>
            <template v-if="view !== 'recharge-count'">
                <label class="flex flex-col gap-1 text-sm"
                    >类型<Input
                        v-model="filters.type"
                        aria-label="类型"
                        class="w-36"
                /></label>
            </template>
            <template v-if="view === 'messages'">
                <label class="flex flex-col gap-1 text-sm"
                    >网站ID<Input
                        v-model="filters.site_id"
                        aria-label="网站ID"
                        class="w-36"
                /></label>
                <label class="flex flex-col gap-1 text-sm"
                    >用户套餐ID<Input
                        v-model="filters.user_package_id"
                        aria-label="用户套餐ID"
                        class="w-36"
                /></label>
            </template>
            <template v-else>
                <label
                    v-if="view === 'orders'"
                    class="flex flex-col gap-1 text-sm"
                    >状态<SelectField
                        v-model="filters.state"
                        aria-label="状态"
                        class="block h-9 rounded-md border bg-background px-3"
                    >
                        <SelectOption value="">全部</SelectOption>
                        <SelectOption value="已付款">已付款</SelectOption>
                        <SelectOption value="未付款">未付款</SelectOption>
                    </SelectField></label
                >
                <label class="flex flex-col gap-1 text-sm"
                    >开始日期<DatePicker
                        v-model="filters.start"
                        type="date"
                        aria-label="开始日期"
                /></label>
                <label class="flex flex-col gap-1 text-sm"
                    >结束日期<DatePicker
                        v-model="filters.end"
                        type="date"
                        aria-label="结束日期"
                /></label>
                <label
                    v-if="view === 'recharge-count'"
                    class="flex flex-col gap-1 text-sm"
                    >汇总方式<SelectField
                        v-model="filters.group_by"
                        aria-label="汇总方式"
                        class="block h-9 rounded-md border bg-background px-3"
                    >
                        <SelectOption value="day">按天汇总</SelectOption>
                        <SelectOption value="month">按月汇总</SelectOption>
                        <SelectOption value="year">按年汇总</SelectOption>
                    </SelectField></label
                >
            </template>
            <Button type="submit">查询</Button>
            <p
                data-typography="body"
                v-if="error"
                role="alert"
                class="w-full text-destructive"
            >
                {{ error }}
            </p>
        </form>
        <ConsoleDataTable
            :title="title"
            :columns="columns"
            :fetch-fn="fetchRecords"
            :search-params="applied"
            :search-key="view === 'messages' ? 'receive' : 'uid'"
            search-placeholder="搜索用户ID"
            :page-size="10"
            :page-size-options="[10, 30, 100]"
        >
            <template #row-actions="{ row }"
                ><Button
                    v-if="view !== 'recharge-count'"
                    variant="link"
                    size="sm"
                    @click="showDetail(row)"
                    >详情</Button
                ></template
            >
        </ConsoleDataTable>
        <Dialog v-model:open="detailOpen"
            ><DialogContent class="max-h-[80vh] overflow-auto sm:max-w-2xl"
                ><DialogHeader
                    ><DialogTitle>记录详情</DialogTitle
                    ><DialogDescription>{{
                        title
                    }}</DialogDescription></DialogHeader
                >
                <p
                    data-typography="body"
                    v-if="detailError"
                    role="alert"
                    class="text-destructive"
                >
                    {{ detailError }}
                </p>
                <RecordDetails v-else-if="detail" :value="detail" />
                <p data-typography="body" v-else class="text-muted-foreground">
                    加载中…
                </p></DialogContent
            ></Dialog
        >
    </div>
</template>
