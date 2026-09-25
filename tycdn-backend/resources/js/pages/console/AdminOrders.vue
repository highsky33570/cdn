<script setup lang="ts">
import { CheckCircle2, CircleX, Plus, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CertificateUserPicker from '@/components/console/CertificateUserPicker.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/date-picker/DatePicker.vue';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import RadioGroup from '@/components/ui/radio-group/RadioGroup.vue';
import RadioGroupItem from '@/components/ui/radio-group/RadioGroupItem.vue';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyRecord } from '@/lib/cdnflyResponse';
import { getErrorMessage, numberValue } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const types = ['充值', '扣款', '升级', '购买', '续费', '更换套餐'];
const type = ref('all'),
    state = ref('all'),
    uid = ref(''),
    start = ref(''),
    end = ref('');
const page = ref(1),
    size = ref(10),
    total = ref(0),
    rows = ref<CdnflyRecord[]>([]);
const loading = ref(false),
    error = ref(''),
    selected = ref<(string | number)[]>([]);
const saving = ref(false),
    deleting = ref(false);
const busy = computed(() => saving.value || deleting.value);
const endpoint = '/api/admin/finance/orders';
const readEndpoint = '/api/admin/workspace/master-orders';
const cents = (value: unknown) => {
    const number = numberValue(value);

    return number === null ? '—' : `${Number((number / 100).toFixed(2))}元`;
};
const columns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'uid', label: '用户ID', width: '90px' },
    { key: 'type', label: '类型', width: '100px' },
    { key: 'des', label: '备注', width: '170px' },
    { key: 'amount', label: '原价', width: '110px', format: cents },
    { key: 'real_amount', label: '实际支付', width: '110px', format: cents },
    { key: 'data', label: '更多', width: '180px' },
    { key: 'pay_type', label: '支付方式', width: '110px' },
    { key: 'mch_order_no', label: '订单号', width: '180px' },
    { key: 'create_at', label: '创建时间', width: '180px' },
    { key: 'state', label: '已付款', width: '80px', align: 'center' },
];
function more(row: CdnflyRecord): string {
    let value = row.data;

    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (typeof value === 'string') {
        try {
            value = JSON.parse(value);
        } catch {
            return value as string;
        }
    }

    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return JSON.stringify(value);
    }

    const data = value as CdnflyRecord;
    const fields: Record<string, [string, string][]> = {
        购买: [
            ['package', '套餐'],
            ['start_at', '开始'],
            ['end_at', '到期'],
        ],
        升级: [
            ['user_package', '用户套餐'],
            ['package_up', '升级包'],
            ['amount', '数量'],
        ],
        续费: [
            ['user_package', '用户套餐'],
            ['end_at', '到期'],
        ],
        更换套餐: [
            ['user_package_id', '用户套餐'],
            ['old_package_id', '旧基础套餐'],
            ['to_package_id', '新基础套餐'],
        ],
        流量包: [
            ['traffic_package', '流量包'],
            ['start_at', '开始'],
            ['end_at', '到期'],
        ],
    };
    const parts = (fields[String(row.type)] ?? [])
        .filter(([key]) => data[key] !== undefined)
        .map(([key, label]) => `${label}: ${String(data[key])}`);

    return parts.length
        ? parts.join(' · ')
        : Object.keys(data).length
          ? JSON.stringify(data, null, 2)
          : '';
}
const moreOpen = ref(false),
    moreText = ref('');
function showMore(row: CdnflyRecord) {
    moreText.value = more(row);
    moreOpen.value = true;
}
let listToken = 0;
async function load() {
    const token = ++listToken;
    loading.value = true;
    error.value = '';
    rows.value = [];
    selected.value = [];

    try {
        const params = new URLSearchParams({
            page: String(page.value),
            limit: String(size.value),
        });

        if (type.value !== 'all') {
            params.set('type', type.value);
        }

        if (state.value !== 'all') {
            params.set('state', state.value);
        }

        if (uid.value) {
            params.set('uid', uid.value);
        }

        if (start.value && end.value) {
            params.set('start', start.value.slice(0, 10));
            const exclusive = new Date(`${end.value.slice(0, 10)}T00:00:00`);
            exclusive.setDate(exclusive.getDate() + 1);
            params.set(
                'end',
                `${exclusive.getFullYear()}-${String(exclusive.getMonth() + 1).padStart(2, '0')}-${String(exclusive.getDate()).padStart(2, '0')}`,
            );
        }

        const result = await apiRequest<CdnflyRecord>(
            `${readEndpoint}?${params}`,
        );

        if (token !== listToken) {
            return;
        }

        rows.value = extractCdnflyRows(result);
        // For orders, total is a money sum; count is the number of records.
        total.value = numberValue(result.count) ?? rows.value.length;
        const last = Math.max(1, Math.ceil(total.value / size.value));

        if (page.value > last) {
            page.value = last;
        }
    } catch (e) {
        if (token === listToken) {
            error.value = getErrorMessage(e);
            total.value = 0;
        }
    } finally {
        if (token === listToken) {
            loading.value = false;
        }
    }
}
function search() {
    if (page.value === 1) {
        void load();
    } else {
        page.value = 1;
    }
}
watch([type, state, uid, start, end, size], search);
watch(page, load);
onMounted(load);
const editOpen = ref(false),
    editId = ref<number | null>(null),
    detailLoading = ref(false),
    detailError = ref(''),
    formError = ref('');
const userDisplay = ref('');
const blank = () => ({
    uid: '',
    type: '',
    des: '',
    create_at: '',
    pay_at: '',
    amount: '',
    real_amount: '',
    pay_type: '',
    mch_order_no: '',
    transaction_id: '',
    state: '已付款',
});
const form = reactive(blank());
let initial: Record<string, string> = {},
    detailToken = 0;
const typeOptions = computed(() => [
    ...new Set([...types, ...(form.type ? [form.type] : [])]),
]);
async function loadDetail() {
    const token = ++detailToken;
    detailLoading.value = true;
    detailError.value = '';

    try {
        const record = extractCdnflyRecord(
            await apiRequest(`${readEndpoint}/${editId.value}`),
        );

        if (token !== detailToken) {
            return;
        }

        if (!record) {
            throw new Error('订单不存在');
        }

        for (const key of Object.keys(form) as (keyof typeof form)[]) {
            form[key] =
                record[key] === null || record[key] === undefined
                    ? ''
                    : String(record[key]);
        }

        form.create_at = form.create_at.replace(' ', 'T');
        form.pay_at = form.pay_at.replace(' ', 'T');
        userDisplay.value = `ID: ${form.uid}`;
        initial = { ...form };
    } catch (e) {
        if (token === detailToken) {
            detailError.value = getErrorMessage(e);
        }
    } finally {
        if (token === detailToken) {
            detailLoading.value = false;
        }
    }
}
function edit(row?: CdnflyRecord) {
    editId.value = row ? Number(row.id) : null;
    Object.assign(form, blank());
    initial = {};
    userDisplay.value = '';
    formError.value = '';
    detailError.value = '';
    editOpen.value = true;

    if (row) {
        void loadDetail();
    }
}
function closeEdit(open: boolean) {
    if (saving.value) {
        return;
    }

    editOpen.value = open;

    if (!open) {
        ++detailToken;
        detailLoading.value = false;
    }
}
const textFields = [
    { key: 'des', label: '备注', placeholder: '请输入备注' },
    { key: 'create_at', label: '创建时间', placeholder: '请选择创建时间' },
    { key: 'pay_at', label: '支付时间', placeholder: '请选择支付时间' },
    { key: 'amount', label: '原价', placeholder: '单位分，如100为1元' },
    {
        key: 'real_amount',
        label: '实际支付',
        placeholder: '单位分，如100为1元',
    },
    {
        key: 'pay_type',
        label: '支付方式',
        placeholder: '如微信、支付宝、银行转账',
    },
    { key: 'mch_order_no', label: '商家订单号', placeholder: '商家订单号' },
    { key: 'transaction_id', label: '交易号', placeholder: '交易号' },
] as const;
async function save() {
    if (saving.value || detailLoading.value || detailError.value) {
        return;
    }

    formError.value = '';

    try {
        if (!form.uid) {
            throw new Error('请选择用户');
        }

        if (!form.type) {
            throw new Error('请选择订单类型');
        }

        const changed = Object.fromEntries(
            Object.entries(form).filter(
                ([key, value]) =>
                    editId.value === null || value !== initial[key],
            ),
        );

        if (!Object.keys(changed).length) {
            throw new Error('未修改任何字段');
        }

        const payload: Record<string, string | number> = {};

        for (const [key, value] of Object.entries(changed)) {
            if (['amount', 'real_amount'].includes(key)) {
                if (value === '' && editId.value === null) {
                    continue;
                }

                if (
                    !/^-?\d+$/.test(value) ||
                    !Number.isSafeInteger(Number(value))
                ) {
                    throw new Error('金额单位为分，请输入整数');
                }

                payload[key] = Number(value);
            } else if (['create_at', 'pay_at'].includes(key)) {
                if (!value) {
                    throw new Error(
                        key === 'create_at'
                            ? '请选择创建时间'
                            : '请选择支付时间',
                    );
                }

                if (!Number.isFinite(Date.parse(value))) {
                    throw new Error('请选择有效时间');
                }

                payload[key] =
                    value.replace('T', ' ').length === 16
                        ? `${value.replace('T', ' ')}:00`
                        : value.replace('T', ' ');
            } else if (key === 'uid') {
                payload[key] = Number(value);
            } else {
                if (key === 'pay_type' && !value.trim()) {
                    throw new Error('请输入支付方式');
                }

                payload[key] = value;
            }
        }

        saving.value = true;
        await apiRequest(
            editId.value === null ? endpoint : `${endpoint}/${editId.value}`,
            {
                method: editId.value === null ? 'POST' : 'PUT',
                body: JSON.stringify(payload),
            },
        );
        editOpen.value = false;
        toast.success('保存成功');
        await load();
    } catch (e) {
        formError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}
const deleteOpen = ref(false),
    deleteIds = ref<(string | number)[]>([]),
    deleteError = ref('');
function confirmDelete() {
    deleteIds.value = [...selected.value];
    deleteError.value = '';
    deleteOpen.value = true;
}
async function remove() {
    if (deleting.value) {
        return;
    }

    deleting.value = true;
    deleteError.value = '';
    const failed: (string | number)[] = [];

    for (const id of deleteIds.value) {
        try {
            await apiRequest(`${endpoint}/${id}`, { method: 'DELETE' });
        } catch (e) {
            failed.push(id);
            deleteError.value = getErrorMessage(e);
        }
    }

    await load();
    deleteIds.value = failed;
    selected.value = failed;
    deleteOpen.value = failed.length > 0;

    if (!failed.length) {
        toast.success('删除成功');
    }

    deleting.value = false;
}
onUnmounted(() => {
    ++listToken;
    ++detailToken;
});
</script>

<template>
    <div class="orders-workspace min-w-0 p-4 md:p-6">
        <section
            class="console-panel rounded-xl border bg-card p-4 text-card-foreground md:p-5"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex gap-2">
                    <Button :disabled="busy" @click="edit()"
                        ><Plus class="mr-1 size-4" />录入订单</Button
                    >
                    <Button
                        variant="destructive"
                        :disabled="busy || !selected.length"
                        @click="confirmDelete"
                        ><Trash2 class="mr-1 size-4" />删除</Button
                    >
                </div>
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <Select v-model="type" :disabled="busy"
                        ><SelectTrigger class="w-36" aria-label="订单类型筛选"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent
                            ><SelectItem value="all">所有类型</SelectItem
                            ><SelectItem
                                v-for="item in [...types, '流量包']"
                                :key="item"
                                :value="item"
                                >{{ item }}</SelectItem
                            ></SelectContent
                        ></Select
                    >
                    <Select v-model="state" :disabled="busy"
                        ><SelectTrigger class="w-36" aria-label="付款状态筛选"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent
                            ><SelectItem value="all">所有状态</SelectItem
                            ><SelectItem value="已付款">已付款</SelectItem
                            ><SelectItem value="未付款"
                                >未付款</SelectItem
                            ></SelectContent
                        ></Select
                    >
                    <DateRangePicker
                        v-model:start="start"
                        v-model:end="end"
                        placeholder="请选择时间范围"
                        trigger-class="max-w-full"
                    />
                    <CertificateUserPicker
                        v-model="uid"
                        :disabled="busy"
                        style="width: 285px"
                    />
                    <Button
                        v-if="uid"
                        variant="ghost"
                        size="sm"
                        @click="uid = ''"
                        >清除用户</Button
                    >
                </div>
            </div>
            <Alert v-if="error" variant="destructive" class="mb-3"
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
            <div class="order-table min-w-0">
                <ConsoleDataTable
                    v-model:selected="selected"
                    title="订单"
                    embedded
                    selectable
                    :selection-disabled="busy || loading"
                    :columns="columns"
                    :data="{ rows, total, page, pageSize: size, loading }"
                    :empty-text="error ? '订单加载失败' : '暂无数据'"
                >
                    <template #actions-col
                        ><col style="width: 80px"
                    /></template>
                    <template #cell-des="{ row }"
                        ><span
                            class="block max-w-40 truncate"
                            :title="String(row.des ?? '')"
                            >{{ row.des || '—' }}</span
                        ></template
                    >
                    <template #cell-mch_order_no="{ row }"
                        ><span
                            class="block max-w-40 truncate"
                            :title="String(row.mch_order_no ?? '')"
                            >{{ row.mch_order_no || '—' }}</span
                        ></template
                    >
                    <template #cell-data="{ row }"
                        ><button
                            v-if="more(row)"
                            type="button"
                            class="block max-w-40 truncate text-left text-primary hover:underline"
                            :title="more(row)"
                            :aria-label="`订单 ${row.id} 更多信息`"
                            @click="showMore(row)"
                        >
                            {{ more(row) }}</button
                        ><span v-else>—</span></template
                    >
                    <template #cell-state="{ row }"
                        ><CheckCircle2
                            v-if="row.state === '已付款'"
                            role="img"
                            aria-label="已付款"
                            class="mx-auto size-4 text-primary"
                        /><CircleX
                            v-else-if="row.state === '未付款'"
                            role="img"
                            aria-label="未付款"
                            class="mx-auto size-4 text-muted-foreground"
                        /><span v-else>{{ row.state || '—' }}</span></template
                    >
                    <template #row-actions="{ row }"
                        ><Button
                            variant="link"
                            size="sm"
                            :disabled="busy"
                            @click="edit(row)"
                            >编辑</Button
                        ></template
                    >
                </ConsoleDataTable>
            </div>
            <PackagePagination
                v-model:page="page"
                v-model:page-size="size"
                :total="total"
                :disabled="loading || busy"
                numbered
            />
        </section>

        <Dialog :open="editOpen" @update:open="closeEdit">
            <DialogScrollContent
                class="max-h-[95dvh] max-w-[790px] grid-rows-[auto_minmax(0,1fr)_auto] gap-0 overflow-hidden p-0"
                @interact-outside.prevent
                @escape-key-down="saving && $event.preventDefault()"
            >
                <DialogHeader class="border-b px-5 py-4"
                    ><DialogTitle>{{
                        editId === null ? '订单录入' : '订单编辑'
                    }}</DialogTitle
                    ><DialogDescription class="sr-only"
                        >填写订单记录，金额单位为分。</DialogDescription
                    ></DialogHeader
                >
                <div class="min-h-0 overflow-y-auto px-5 py-5">
                    <div v-if="detailLoading" class="flex justify-center py-10">
                        <Spinner /><span class="sr-only">加载订单</span>
                    </div>
                    <Alert v-else-if="detailError" variant="destructive"
                        ><AlertDescription
                            >{{ detailError
                            }}<Button
                                variant="outline"
                                size="sm"
                                class="ml-2"
                                @click="loadDetail"
                                >重试</Button
                            ></AlertDescription
                        ></Alert
                    >
                    <form
                        v-else
                        id="order-form"
                        class="space-y-6"
                        @submit.prevent="save"
                    >
                        <fieldset :disabled="saving" class="space-y-6">
                            <div class="order-field">
                                <Label for="order-user">用户：</Label
                                ><CertificateUserPicker
                                    v-model="form.uid"
                                    input-id="order-user"
                                    :display="userDisplay"
                                    :disabled="saving"
                                    style="width: 100%"
                                />
                            </div>
                            <div class="order-field">
                                <span
                                    id="order-type-label"
                                    class="text-sm sm:text-right"
                                    >订单类型：</span
                                >
                                <RadioGroup
                                    v-model="form.type"
                                    name="order-type"
                                    aria-labelledby="order-type-label"
                                    class="flex flex-wrap gap-x-3 gap-y-2"
                                >
                                    <label
                                        v-for="item in typeOptions"
                                        :key="item"
                                        class="flex items-center gap-1 text-sm"
                                        ><RadioGroupItem
                                            :value="item"
                                            class="accent-primary"
                                        />{{ item }}</label
                                    >
                                </RadioGroup>
                            </div>
                            <div
                                v-for="field in textFields"
                                :key="field.key"
                                class="order-field"
                            >
                                <Label :for="`order-${field.key}`"
                                    >{{ field.label }}：</Label
                                >
                                <div class="flex min-w-0">
                                    <DatePicker
                                        :id="`order-${field.key}`"
                                        v-model="form[field.key]"
                                        :type="
                                            ['create_at', 'pay_at'].includes(
                                                field.key,
                                            )
                                                ? 'datetime-local'
                                                : 'text'
                                        "
                                        :step="
                                            ['create_at', 'pay_at'].includes(
                                                field.key,
                                            )
                                                ? 1
                                                : undefined
                                        "
                                        :inputmode="
                                            ['amount', 'real_amount'].includes(
                                                field.key,
                                            )
                                                ? 'numeric'
                                                : undefined
                                        "
                                        :placeholder="field.placeholder"
                                        :class="
                                            ['amount', 'real_amount'].includes(
                                                field.key,
                                            )
                                                ? 'rounded-r-none'
                                                : ''
                                        "
                                    /><span
                                        v-if="
                                            ['amount', 'real_amount'].includes(
                                                field.key,
                                            )
                                        "
                                        class="flex items-center rounded-r-md border border-l-0 bg-muted/40 px-3 text-sm"
                                        >分</span
                                    >
                                </div>
                            </div>
                            <div class="order-field">
                                <span
                                    id="order-state-label"
                                    class="text-sm sm:text-right"
                                    >状态：</span
                                >
                                <RadioGroup
                                    v-model="form.state"
                                    name="order-state"
                                    aria-labelledby="order-state-label"
                                    class="flex gap-3"
                                >
                                    <label
                                        v-for="item in [
                                            ...new Set([
                                                '已付款',
                                                '未付款',
                                                ...(form.state
                                                    ? [form.state]
                                                    : []),
                                            ]),
                                        ]"
                                        :key="item"
                                        class="flex items-center gap-1 text-sm"
                                        ><RadioGroupItem
                                            :value="item"
                                            class="accent-primary"
                                        />{{ item }}</label
                                    >
                                </RadioGroup>
                            </div>
                        </fieldset>
                        <Alert v-if="formError" variant="destructive"
                            ><AlertDescription>{{
                                formError
                            }}</AlertDescription></Alert
                        >
                    </form>
                </div>
                <DialogFooter class="border-t px-5 py-4"
                    ><Button
                        type="submit"
                        form="order-form"
                        :disabled="saving || detailLoading || !!detailError"
                        ><Spinner v-if="saving" class="mr-1" />确定</Button
                    ><Button
                        variant="outline"
                        :disabled="saving"
                        @click="closeEdit(false)"
                        >关闭</Button
                    ></DialogFooter
                >
            </DialogScrollContent>
        </Dialog>
        <Dialog
            :open="deleteOpen"
            @update:open="
                (value) => {
                    if (!deleting) deleteOpen = value;
                }
            "
            ><DialogScrollContent @interact-outside.prevent
                ><DialogHeader
                    ><DialogTitle>删除确认</DialogTitle
                    ><DialogDescription
                        >是否删除选中的
                        {{ deleteIds.length }} 条订单？</DialogDescription
                    ></DialogHeader
                ><Alert v-if="deleteError" variant="destructive"
                    ><AlertDescription>{{
                        deleteError
                    }}</AlertDescription></Alert
                ><DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="deleting"
                        @click="deleteOpen = false"
                        >取消</Button
                    ><Button
                        variant="destructive"
                        :disabled="deleting"
                        @click="remove"
                        ><Spinner
                            v-if="deleting"
                            class="mr-1"
                        />确认删除</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <Dialog v-model:open="moreOpen"
            ><DialogScrollContent
                ><DialogHeader
                    ><DialogTitle>订单更多信息</DialogTitle
                    ><DialogDescription class="sr-only"
                        >订单附加业务数据</DialogDescription
                    ></DialogHeader
                >
                <p
                    class="max-h-[60dvh] overflow-auto text-sm break-words whitespace-pre-wrap"
                >
                    {{ moreText }}
                </p>
                <DialogFooter
                    ><Button variant="outline" @click="moreOpen = false"
                        >关闭</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
    </div>
</template>
<style scoped>
.order-table :deep(table) {
    min-width: 1560px;
}
.order-field {
    display: grid;
    grid-template-columns: 110px minmax(0, 1fr);
    align-items: center;
    gap: 16px;
}
.order-field > label {
    justify-content: flex-end;
}
@media (max-width: 639px) {
    .order-field {
        grid-template-columns: minmax(0, 1fr);
        gap: 8px;
    }
    .order-field > label {
        justify-content: flex-start;
    }
}
</style>
