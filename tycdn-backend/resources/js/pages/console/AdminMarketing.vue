<script setup lang="ts">
import { Plus, Trash2, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import PackageScopeSelect from '@/components/console/PackageScopeSelect.vue';
import RecordDetails from '@/components/console/RecordDetails.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import DatePicker from '@/components/ui/date-picker/DatePicker.vue';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
import Switch from '@/components/ui/switch/Switch.vue';
import { apiRequest } from '@/lib/apiRequest';
import {
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnflyResponse';
import { formatDate, formatMoney, getErrorMessage } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const props = withDefaults(defineProps<{ initialTab?: string }>(), {
    initialTab: 'discounts',
});
const tabs = [
    { key: 'discounts', label: '折扣配置' },
    { key: 'coupons', label: '优惠码管理' },
    { key: 'coupon-historys', label: '优惠码使用记录' },
];
const tab = ref(props.initialTab),
    page = ref(1),
    size = ref(10),
    total = ref(0);
const history = computed(() => tab.value === 'coupon-historys');
const loading = ref(false),
    busy = ref(false),
    error = ref('');
const rows = ref<CdnflyRecord[]>([]),
    selected = ref<(string | number)[]>([]);
const uid = ref(''),
    code = ref('');
const endpoint = (resource = tab.value) => `/api/admin/workspace/${resource}`;
const columns = computed(() =>
    history.value
        ? [
              { key: 'identity', label: '优惠码' },
              { key: 'uid', label: '用户ID' },
              { key: 'price', label: '金额' },
              { key: 'used_at', label: '使用时间' },
          ]
        : tab.value === 'discounts'
          ? [
                { key: 'identity', label: '折扣' },
                { key: 'category', label: '生效类别' },
                { key: 'offer', label: '折扣详情' },
                { key: 'dates', label: '有效期' },
                { key: 'status', label: '启用' },
            ]
          : [
                { key: 'identity', label: '优惠码' },
                { key: 'offer', label: '优惠与用量' },
                { key: 'dates', label: '有效期' },
                { key: 'status', label: '启用' },
            ],
);
const categoryLabels: Record<string, string> = {
    package: '基础套餐',
    package_up: '升级包',
    traffic: '流量包',
};
let listToken = 0;
async function loadRows() {
    const token = ++listToken;
    loading.value = true;
    error.value = '';
    rows.value = [];
    selected.value = [];

    try {
        const query = new URLSearchParams({
            page: String(page.value),
            limit: String(size.value),
        });

        if (history.value) {
            if (String(uid.value).trim()) {
                query.set('uid', String(uid.value).trim());
            }

            if (code.value.trim()) {
                query.set('code', code.value.trim());
            }
        }

        const response = await apiRequest(`${endpoint()}?${query}`);

        if (token !== listToken) {
            return;
        }

        rows.value = extractCdnflyRows(response);
        total.value = extractCdnflyTotal(response, rows.value.length);
        page.value = Math.min(
            page.value,
            Math.max(1, Math.ceil(total.value / size.value)),
        );
    } catch (e) {
        if (token === listToken) {
            error.value = getErrorMessage(e);
        }
    } finally {
        if (token === listToken) {
            loading.value = false;
        }
    }
}
function search() {
    if (page.value === 1) {
        void loadRows();
    } else {
        page.value = 1;
    }
}
watch(page, loadRows);
watch([tab, size], search);
watch(
    () => props.initialTab,
    (value) => {
        tab.value = value;
    },
);
let timer: ReturnType<typeof setTimeout> | undefined;
watch([uid, code], () => {
    clearTimeout(timer);
    timer = setTimeout(search, 300);
});
onUnmounted(() => {
    clearTimeout(timer);
    ++listToken;
});
type Option = { id: string | number; name: string };
const options = reactive<{
    groups: Option[];
    package: Option[];
    package_up: Option[];
    traffic: Option[];
}>({ groups: [], package: [], package_up: [], traffic: [] });
const optionsLoading = ref(false),
    optionsError = ref('');
async function loadOptions() {
    optionsLoading.value = true;
    optionsError.value = '';

    try {
        const responses = await Promise.all([
            apiRequest(`${endpoint('user-groups')}?limit=0`),
            apiRequest('/api/admin/packages?limit=0'),
            apiRequest('/api/admin/package-ups?limit=0'),
            apiRequest(`${endpoint('traffic-packages')}?limit=0`),
        ]);
        (['groups', 'package', 'package_up', 'traffic'] as const).forEach(
            (key, index) => {
                options[key] = extractCdnflyRows(responses[index]).map(
                    (row) => ({
                        id: String(row.id),
                        name: String(row.name ?? `#${row.id}`),
                    }),
                );
            },
        );
    } catch (e) {
        optionsError.value = getErrorMessage(e);
    } finally {
        optionsLoading.value = false;
    }
}
onMounted(() => {
    void loadRows();
    void loadOptions();
});
const initialForm = () => ({
    name: '',
    des: '',
    groups: [] as string[],
    cate: 'package',
    packages: [] as string[],
    upgrades: [] as string[],
    traffic: [] as string[],
    type: 'discount',
    discount: '',
    month: '',
    quarter: '',
    year: '',
    start: '',
    end: '',
    priority: '100',
    enable: true,
    code: '',
    minimum: '',
    amount: '',
    max: '',
    persist: true,
    categories: [] as string[],
});
const form = reactive(initialForm());
const editorOpen = ref(false),
    editorLoading = ref(false),
    editorReady = ref(false),
    editorError = ref(''),
    saving = ref(false);
const editingId = ref<number | null>(null),
    editingResource = ref('discounts'),
    originalPayload = ref('');
const couponEditor = computed(() => editingResource.value === 'coupons');
const scope = computed({
    get: () =>
        form.cate === 'package'
            ? form.packages
            : form.cate === 'package_up'
              ? form.upgrades
              : form.traffic,
    set: (value) => {
        if (form.cate === 'package') {
            form.packages = value;
        } else if (form.cate === 'package_up') {
            form.upgrades = value;
        } else {
            form.traffic = value;
        }
    },
});
const scopeOptions = computed(
    () => options[form.cate as 'package' | 'package_up' | 'traffic'] ?? [],
);
const ids = (value: unknown) =>
    (Array.isArray(value) ? value : String(value ?? '').split(','))
        .map(String)
        .filter(Boolean);
const text = (value: unknown) =>
    value === null || value === undefined ? '' : String(value);
const dateInput = (value: unknown) => text(value).replace(' ', 'T');
const datePayload = (value: string) =>
    value ? value.replace('T', ' ') + (value.length === 16 ? ':00' : '') : null;
function integer(value: unknown, label: string, optional = false) {
    if (!text(value).trim()) {
        if (optional) {
            return null;
        }

        throw new Error(`请填写${label}`);
    }

    const number = Number(value);

    if (!Number.isSafeInteger(number) || number < 0) {
        throw new Error(`${label}必须为非负整数`);
    }

    return number;
}
function payload() {
    if (!form.name.trim()) {
        throw new Error('请填写名称');
    }

    if (form.start && form.end && form.start >= form.end) {
        throw new Error('结束时间必须晚于开始时间');
    }

    if (
        form.type === 'discount' &&
        (!text(form.discount).trim() ||
            !Number.isFinite(Number(form.discount)) ||
            Number(form.discount) < 0 ||
            Number(form.discount) > 1)
    ) {
        throw new Error('折扣值应在 0 到 1 之间，1 为原价');
    }

    const data: Record<string, unknown> = {
        name: form.name.trim(),
        des: form.des,
        start_at: datePayload(form.start),
        end_at: datePayload(form.end),
        enable: form.enable ? 1 : 0,
        discount_value: form.type === 'discount' ? String(form.discount) : null,
    };

    if (couponEditor.value) {
        if (!form.code.trim() || form.code.length > 30) {
            throw new Error('请填写不超过 30 个字符的优惠码');
        }

        if (!form.categories.length) {
            throw new Error('请选择生效类别');
        }

        Object.assign(data, {
            code: form.code.trim(),
            coupon_type: form.type,
            price_gt: integer(form.minimum, '最低原价', true),
            price_value:
                form.type === 'price' ? integer(form.amount, '优惠金额') : null,
            max_times: integer(form.max, '发放数量', true),
            persist_discount: form.persist ? 1 : 0,
            cate: form.categories.join(','),
            package: form.categories.includes('package')
                ? form.packages.join(',')
                : '',
            traffic_package: form.categories.includes('traffic')
                ? form.traffic.join(',')
                : '',
        });
    } else {
        Object.assign(data, {
            user_group: form.groups.join(','),
            cate: form.cate,
            dis_type: form.type,
            package: form.cate === 'package' ? form.packages.join(',') : '',
            package_up:
                form.cate === 'package_up' ? form.upgrades.join(',') : '',
            traffic_package:
                form.cate === 'traffic' ? form.traffic.join(',') : '',
            priority: text(form.priority).trim()
                ? integer(form.priority, '优先级')
                : 100,
            month_price:
                form.type === 'price' ? integer(form.month, '月价') : null,
            quarter_price:
                form.type === 'price' && form.cate === 'package'
                    ? integer(form.quarter, '季价')
                    : null,
            year_price:
                form.type === 'price' && form.cate === 'package'
                    ? integer(form.year, '年价')
                    : null,
        });
    }

    return data;
}
let editorToken = 0;
async function edit(row?: CdnflyRecord) {
    const token = ++editorToken;
    editingResource.value = tab.value;
    editingId.value = row ? Number(row.id) : null;
    Object.assign(form, initialForm());
    originalPayload.value = '';
    editorError.value = '';
    editorReady.value = !row;
    editorLoading.value = !!row;
    editorOpen.value = true;

    if (!row) {
        return;
    }

    try {
        const item = extractCdnflyRecord(
            await apiRequest(`${endpoint(editingResource.value)}/${row.id}`),
        );

        if (token !== editorToken) {
            return;
        }

        if (!item?.id) {
            throw new Error('无法读取详情');
        }

        Object.assign(form, {
            name: text(item.name),
            des: text(item.des),
            groups: ids(item.user_group),
            cate: text(item.cate || 'package'),
            packages: ids(item.package),
            upgrades: ids(item.package_up),
            traffic: ids(item.traffic_package),
            type: text(couponEditor.value ? item.coupon_type : item.dis_type),
            discount: text(item.discount_value),
            month: text(item.month_price),
            quarter: text(item.quarter_price),
            year: text(item.year_price),
            start: dateInput(item.start_at),
            end: dateInput(item.end_at),
            priority: text(item.priority ?? 100),
            enable: String(item.enable) === '1',
            code: text(item.code),
            minimum: text(item.price_gt),
            amount: text(item.price_value),
            max: text(item.max_times),
            persist: String(item.persist_discount ?? 1) === '1',
            categories: ids(item.cate),
        });
        editorReady.value = true;

        try {
            originalPayload.value = JSON.stringify(payload());
        } catch {
            /* Existing invalid records stay editable. */
        }
    } catch (e) {
        if (token === editorToken) {
            editorError.value = getErrorMessage(e);
        }
    } finally {
        if (token === editorToken) {
            editorLoading.value = false;
        }
    }
}
async function save() {
    saving.value = true;
    editorError.value = '';

    try {
        const body = JSON.stringify(payload());

        if (editingId.value && body === originalPayload.value) {
            editorOpen.value = false;

            return;
        }

        await apiRequest(
            `${endpoint(editingResource.value)}${editingId.value ? `/${editingId.value}` : ''}`,
            { method: editingId.value ? 'PUT' : 'POST', body },
        );
        editorOpen.value = false;
        await loadRows();
        toast.success('保存成功');
    } catch (e) {
        editorError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}
function categoryChecked(value: string, checked: boolean) {
    form.categories = checked
        ? [...new Set([...form.categories, value])]
        : form.categories.filter((item) => item !== value);
}
const deleteOpen = ref(false),
    deleteError = ref(''),
    deleteIds = ref<number[]>([]),
    deleteResource = ref('discounts');
function askDelete(row?: CdnflyRecord) {
    deleteIds.value = row ? [Number(row.id)] : selected.value.map(Number);
    deleteResource.value = tab.value;
    deleteError.value = '';
    deleteOpen.value = true;
}
async function remove() {
    busy.value = true;
    deleteError.value = '';
    const failed: number[] = [];

    for (const id of deleteIds.value) {
        try {
            await apiRequest(`${endpoint(deleteResource.value)}/${id}`, {
                method: 'DELETE',
            });
        } catch (e) {
            failed.push(id);
            deleteError.value = getErrorMessage(e);
        }
    }

    await loadRows();
    deleteIds.value = failed;
    selected.value = failed;
    deleteOpen.value = !!failed.length;
    busy.value = false;

    if (!failed.length) {
        toast.success('删除成功');
    }
}
const detailsOpen = ref(false),
    details = ref<CdnflyRecord>({});
function showDetails(row: CdnflyRecord) {
    details.value = row;
    detailsOpen.value = true;
}
const detailLabels = {
    code: '优惠码',
    name: '名称',
    des: '备注',
    uid: '用户ID',
    coupon_id: '优惠码ID',
    orgin_price: '原价（USDT）',
    real_price: '实付金额（USDT）',
    create_at: '使用时间',
    package_name: '套餐',
    package_id: '基础套餐ID',
    user_package_id: '用户套餐ID',
    discount_value: '折扣值',
    price_value: '优惠价（USDT）',
    coupon_price_gt: '最低原价（USDT）',
};
</script>

<template>
    <div class="marketing-workspace min-w-0 p-4 md:p-6">
        <div
            class="console-panel rounded-xl border bg-card p-4 text-card-foreground md:p-5"
        >
            <ConsoleTabs v-model="tab" :tabs="tabs" />
            <div class="my-4 flex flex-wrap items-center gap-2">
                <template v-if="!history"
                    ><Button
                        size="sm"
                        :disabled="busy || optionsLoading || !!optionsError"
                        @click="edit()"
                        ><Plus />{{
                            tab === 'discounts' ? '新增折扣' : '新增优惠码'
                        }}</Button
                    ><Button
                        size="sm"
                        variant="destructive"
                        :disabled="busy || loading || !selected.length"
                        @click="askDelete()"
                        ><Trash2 />删除</Button
                    ></template
                >
                <template v-else
                    ><div class="flex w-full sm:w-64">
                        <Label for="marketing-uid" class="marketing-addon"
                            >用户ID</Label
                        ><Input
                            id="marketing-uid"
                            v-model="uid"
                            placeholder="请输入用户ID"
                            class="rounded-l-none"
                        />
                    </div>
                    <div class="flex w-full sm:w-64">
                        <Label
                            for="marketing-code-filter"
                            class="marketing-addon"
                            >优惠码</Label
                        ><Input
                            id="marketing-code-filter"
                            v-model="code"
                            placeholder="请输入优惠码"
                            class="rounded-l-none"
                        /></div
                ></template>
                <Button
                    size="sm"
                    variant="ghost"
                    class="ml-auto"
                    :disabled="loading || busy"
                    @click="loadRows"
                    ><RefreshCw />刷新</Button
                >
            </div>
            <Alert v-if="error" variant="destructive" class="mb-4"
                ><AlertDescription
                    >{{ error
                    }}<Button variant="link" @click="loadRows"
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <Alert
                v-if="optionsError && !history"
                variant="destructive"
                class="mb-4"
                ><AlertDescription
                    >{{ optionsError
                    }}<Button variant="link" @click="loadOptions"
                        >重新加载选项</Button
                    ></AlertDescription
                ></Alert
            >
            <ConsoleDataTable
                :key="tab"
                v-model:selected="selected"
                embedded
                :selectable="!history"
                :selection-disabled="busy"
                :title="tabs.find((item) => item.key === tab)?.label || ''"
                :columns="columns"
                :data="{ rows, total, page, pageSize: size, loading }"
                empty-text="暂无数据"
            >
                <template #cell-identity="{ row }"
                    ><Button
                        variant="link"
                        class="h-auto p-0 font-medium"
                        :disabled="busy"
                        @click="history ? showDetails(row) : edit(row)"
                        >{{ history ? row.code : row.name }}</Button
                    >
                    <p class="mt-1 text-xs text-muted-foreground">
                        ID: {{ history ? row.coupon_id : row.id
                        }}<template v-if="tab === 'coupons'">
                            / {{ row.code }}</template
                        >
                    </p>
                    <p
                        v-if="tab === 'discounts'"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        优先级: {{ row.priority ?? 100 }} / 用户组:
                        {{ row.user_group || '全部用户' }}
                    </p>
                    <p
                        v-if="!history && row.des"
                        class="mt-1 max-w-64 truncate text-xs text-muted-foreground"
                        :title="String(row.des)"
                    >
                        {{ row.des }}
                    </p></template
                >
                <template #cell-category="{ row }">{{
                    categoryLabels[String(row.cate)] || row.cate
                }}</template>
                <template #cell-offer="{ row }"
                    ><template
                        v-if="(row.dis_type || row.coupon_type) === 'discount'"
                        >折扣: {{ row.discount_value }}</template
                    ><template v-else
                        >{{ tab === 'coupons' ? '优惠价' : '月价' }}:
                        {{
                            formatMoney(
                                tab === 'coupons'
                                    ? row.price_value
                                    : row.month_price,
                            )
                        }}<template
                            v-if="tab === 'discounts' && row.cate === 'package'"
                            ><p class="mt-1 text-xs text-muted-foreground">
                                季价: {{ formatMoney(row.quarter_price) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                年价: {{ formatMoney(row.year_price) }}
                            </p></template
                        ></template
                    ><template v-if="tab === 'coupons'"
                        ><p class="mt-1 text-xs text-muted-foreground">
                            已用: {{ row.used_times ?? 0 }} /
                            {{ row.max_times ?? '不限' }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            原价门槛:
                            {{
                                row.price_gt === null ||
                                row.price_gt === undefined
                                    ? '不限'
                                    : formatMoney(row.price_gt)
                            }}
                        </p></template
                    ></template
                >
                <template #cell-dates="{ row }"
                    ><p class="text-xs">
                        开始:
                        {{
                            row.start_at
                                ? formatDate(String(row.start_at))
                                : '立即生效'
                        }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        结束:
                        {{
                            row.end_at
                                ? formatDate(String(row.end_at))
                                : '永久生效'
                        }}
                    </p></template
                >
                <template #cell-status="{ row }"
                    ><Badge
                        :variant="
                            String(row.enable) === '1' ? 'secondary' : 'outline'
                        "
                        >{{
                            String(row.enable) === '1' ? '启用' : '禁用'
                        }}</Badge
                    ></template
                >
                <template #cell-price="{ row }"
                    ><p>{{ formatMoney(row.real_price) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        原价: {{ formatMoney(row.orgin_price) }}
                    </p></template
                >
                <template #cell-used_at="{ row }">{{
                    formatDate(String(row.create_at || row.created_at || ''))
                }}</template>
                <template #row-actions="{ row }"
                    ><div class="flex justify-end gap-1">
                        <Button
                            v-if="history"
                            size="sm"
                            variant="ghost"
                            @click="showDetails(row)"
                            >详情</Button
                        ><template v-else
                            ><Button
                                size="sm"
                                variant="ghost"
                                :disabled="
                                    busy || optionsLoading || !!optionsError
                                "
                                @click="edit(row)"
                                >编辑</Button
                            ><Button
                                size="sm"
                                variant="ghost"
                                :disabled="busy"
                                @click="askDelete(row)"
                                >删除</Button
                            ></template
                        >
                    </div></template
                >
            </ConsoleDataTable>
            <PackagePagination
                v-model:page="page"
                v-model:page-size="size"
                :total="total"
                :disabled="loading || busy"
            />
        </div>
        <Dialog
            :open="editorOpen"
            @update:open="
                (value) => {
                    if (!saving) editorOpen = value;
                }
            "
            ><DialogScrollContent class="marketing-editor sm:max-w-xl">
                <DialogHeader
                    ><DialogTitle
                        >{{ editingId ? '编辑' : '新增'
                        }}{{ couponEditor ? '优惠码' : '折扣' }}</DialogTitle
                    ><DialogDescription class="sr-only"
                        >设置适用范围、优惠和有效期</DialogDescription
                    ></DialogHeader
                >
                <p
                    v-if="editorLoading"
                    class="py-8 text-center text-muted-foreground"
                >
                    加载中…
                </p>
                <Alert v-if="editorError" variant="destructive"
                    ><AlertDescription
                        >{{ editorError
                        }}<Button
                            v-if="!editorReady && editingId"
                            variant="link"
                            @click="edit({ id: editingId })"
                            >重试</Button
                        ></AlertDescription
                    ></Alert
                >
                <form
                    v-if="!editorLoading"
                    id="marketing-form"
                    class="py-4"
                    @submit.prevent="save"
                >
                    <fieldset
                        :disabled="saving || !editorReady"
                        class="space-y-6"
                    >
                        <div class="marketing-field">
                            <Label for="marketing-name">名称</Label
                            ><Input
                                id="marketing-name"
                                v-model="form.name"
                                :placeholder="
                                    couponEditor
                                        ? '请输入优惠码名称'
                                        : '请输入折扣名称'
                                "
                            />
                        </div>
                        <div class="marketing-field">
                            <Label for="marketing-des">备注</Label
                            ><Input
                                id="marketing-des"
                                v-model="form.des"
                                placeholder="请输入备注"
                            />
                        </div>
                        <template v-if="!couponEditor">
                            <div class="marketing-field">
                                <Label>生效用户组</Label
                                ><PackageScopeSelect
                                    v-model="form.groups"
                                    label="生效用户组"
                                    placeholder="为空则所有用户生效"
                                    :options="options.groups"
                                    :disabled="saving || optionsLoading"
                                />
                            </div>
                            <div class="marketing-field">
                                <Label for="marketing-category">生效类别</Label
                                ><Select v-model="form.cate" :disabled="saving"
                                    ><SelectTrigger
                                        id="marketing-category"
                                        class="w-full"
                                        ><SelectValue /></SelectTrigger
                                    ><SelectContent
                                        ><SelectItem
                                            v-for="(
                                                label, value
                                            ) in categoryLabels"
                                            :key="value"
                                            :value="value"
                                            >{{ label }}</SelectItem
                                        ></SelectContent
                                    ></Select
                                >
                            </div>
                            <div class="marketing-field">
                                <Label>生效套餐</Label
                                ><PackageScopeSelect
                                    v-model="scope"
                                    label="生效套餐"
                                    placeholder="为空则所有套餐生效"
                                    :options="scopeOptions"
                                    :disabled="saving || optionsLoading"
                                />
                            </div>
                        </template>
                        <template v-else>
                            <div class="marketing-field">
                                <Label for="marketing-code">优惠码</Label
                                ><Input
                                    id="marketing-code"
                                    v-model="form.code"
                                    maxlength="30"
                                    placeholder="最大30个字符"
                                />
                            </div>
                            <div class="marketing-field">
                                <Label for="marketing-minimum"
                                    >原价不低于</Label
                                >
                                <div
                                    data-slot="console-input-group"
                                    class="flex"
                                >
                                    <Input
                                        id="marketing-minimum"
                                        v-model="form.minimum"
                                        type="number"
                                        min="0"
                                        step="1"
                                        placeholder="原价大于此值才有优惠，留空不限制"
                                        class="rounded-r-none"
                                    /><span class="marketing-unit">USDT</span>
                                </div>
                            </div>
                        </template>
                        <div class="marketing-field">
                            <Label>{{
                                couponEditor ? '类型' : '折扣类型'
                            }}</Label>
                            <RadioGroup
                                v-model="form.type"
                                name="offer-type"
                                class="flex flex-wrap items-center gap-4"
                                :aria-label="couponEditor ? '类型' : '折扣类型'"
                            >
                                <label class="flex items-center gap-2 text-sm"
                                    ><RadioGroupItem
                                        value="discount"
                                        class="accent-primary"
                                    />折扣</label
                                ><label class="flex items-center gap-2 text-sm"
                                    ><RadioGroupItem
                                        value="price"
                                        class="accent-primary"
                                    />{{
                                        couponEditor ? '金额' : '价格'
                                    }}</label
                                ><Input
                                    v-if="couponEditor"
                                    v-model="
                                        form[
                                            form.type === 'discount'
                                                ? 'discount'
                                                : 'amount'
                                        ]
                                    "
                                    :aria-label="
                                        form.type === 'discount'
                                            ? '折扣值'
                                            : '优惠金额'
                                    "
                                    type="number"
                                    min="0"
                                    :step="
                                        form.type === 'discount' ? 'any' : '1'
                                    "
                                    :placeholder="
                                        form.type === 'discount'
                                            ? '1为原价,0.5为5折'
                                            : '优惠后价格（USDT）'
                                    "
                                    class="min-w-32 flex-1"
                                />
                            </RadioGroup>
                        </div>
                        <template v-if="!couponEditor"
                            ><div
                                v-if="form.type === 'discount'"
                                class="marketing-field"
                            >
                                <Label for="marketing-discount">折扣值</Label
                                ><Input
                                    id="marketing-discount"
                                    v-model="form.discount"
                                    type="number"
                                    min="0"
                                    max="1"
                                    step="any"
                                    placeholder="1为原价,0.5为5折"
                                />
                            </div>
                            <template v-else
                                ><div
                                    v-for="field in (form.cate === 'package'
                                        ? ['month', 'quarter', 'year']
                                        : ['month']) as (
                                        | 'month'
                                        | 'quarter'
                                        | 'year'
                                    )[]"
                                    :key="field"
                                    class="marketing-field"
                                >
                                    <Label :for="`marketing-${field}`">{{
                                        {
                                            month: '月价',
                                            quarter: '季价',
                                            year: '年价',
                                        }[field]
                                    }}</Label>
                                    <div
                                        data-slot="console-input-group"
                                        class="flex"
                                    >
                                        <Input
                                            :id="`marketing-${field}`"
                                            v-model="form[field]"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="rounded-r-none"
                                        /><span class="marketing-unit"
                                            >USDT</span
                                        >
                                    </div>
                                </div></template
                            ></template
                        >
                        <template v-else>
                            <div class="marketing-field items-start">
                                <Label for="marketing-persist">优惠同价</Label>
                                <div class="space-y-2">
                                    <Switch
                                        id="marketing-persist"
                                        :checked="form.persist"
                                        :disabled="saving"
                                        @update:checked="form.persist = $event"
                                    />
                                    <p
                                        class="text-xs leading-5 text-muted-foreground"
                                    >
                                        开启后，用户使用此优惠码购买或续费时，下次续费直接享受优惠价而无需输入优惠码。
                                    </p>
                                </div>
                            </div>
                            <div class="marketing-field">
                                <Label for="marketing-max">发放数量</Label
                                ><Input
                                    id="marketing-max"
                                    v-model="form.max"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="优惠码最大使用次数，留空不限制"
                                />
                            </div>
                            <div class="marketing-field">
                                <Label>生效类别</Label>
                                <div class="flex flex-wrap gap-4">
                                    <label
                                        v-for="category in [
                                            'package',
                                            'traffic',
                                        ]"
                                        :key="category"
                                        class="flex items-center gap-2 text-sm"
                                        ><Checkbox
                                            :model-value="
                                                form.categories.includes(
                                                    category,
                                                )
                                            "
                                            :disabled="saving"
                                            @update:model-value="
                                                (value) =>
                                                    categoryChecked(
                                                        category,
                                                        !!value,
                                                    )
                                            "
                                        />{{ categoryLabels[category] }}</label
                                    >
                                </div>
                            </div>
                            <details
                                v-if="form.categories.length"
                                class="rounded-md border p-3"
                            >
                                <summary
                                    class="cursor-pointer text-sm text-muted-foreground"
                                >
                                    适用套餐（可选）
                                </summary>
                                <div class="mt-4 space-y-4">
                                    <div
                                        v-if="
                                            form.categories.includes('package')
                                        "
                                        class="marketing-field"
                                    >
                                        <Label>基础套餐</Label
                                        ><PackageScopeSelect
                                            v-model="form.packages"
                                            label="适用基础套餐"
                                            placeholder="所有基础套餐"
                                            :options="options.package"
                                            :disabled="saving"
                                        />
                                    </div>
                                    <div
                                        v-if="
                                            form.categories.includes('traffic')
                                        "
                                        class="marketing-field"
                                    >
                                        <Label>流量包</Label
                                        ><PackageScopeSelect
                                            v-model="form.traffic"
                                            label="适用流量包"
                                            placeholder="所有流量包"
                                            :options="options.traffic"
                                            :disabled="saving"
                                        />
                                    </div>
                                </div>
                            </details>
                        </template>
                        <div class="marketing-field">
                            <Label for="marketing-start">开始时间</Label>
                            <div class="space-y-1">
                                <DatePicker
                                    id="marketing-start"
                                    v-model="form.start"
                                    type="datetime-local"
                                    step="1"
                                />
                                <p class="text-xs text-muted-foreground">
                                    留空立即生效
                                </p>
                            </div>
                        </div>
                        <div class="marketing-field">
                            <Label for="marketing-end">结束时间</Label>
                            <div class="space-y-1">
                                <DatePicker
                                    id="marketing-end"
                                    v-model="form.end"
                                    type="datetime-local"
                                    step="1"
                                />
                                <p class="text-xs text-muted-foreground">
                                    留空永久生效
                                </p>
                            </div>
                        </div>
                        <div v-if="!couponEditor" class="marketing-field">
                            <Label for="marketing-priority">优先级</Label
                            ><Input
                                id="marketing-priority"
                                v-model="form.priority"
                                type="number"
                                min="0"
                                step="1"
                                placeholder="默认100，数字越低优先级越高"
                            />
                        </div>
                        <div class="marketing-field">
                            <Label for="marketing-enable">启用</Label
                            ><Switch
                                id="marketing-enable"
                                :checked="form.enable"
                                :disabled="saving"
                                @update:checked="form.enable = $event"
                            />
                        </div>
                    </fieldset>
                </form>
                <DialogFooter
                    ><Button
                        type="submit"
                        form="marketing-form"
                        :disabled="
                            saving ||
                            !editorReady ||
                            optionsLoading ||
                            !!optionsError
                        "
                        >{{ saving ? '保存中…' : '确定' }}</Button
                    ><Button
                        variant="outline"
                        :disabled="saving"
                        @click="editorOpen = false"
                        >取消</Button
                    ></DialogFooter
                >
            </DialogScrollContent></Dialog
        >
        <ConfirmDeleteDialog
            :open="deleteOpen"
            :title="deleteResource === 'discounts' ? '删除折扣' : '删除优惠码'"
            :description="`确定删除所选 ${deleteIds.length} 条记录？`"
            :loading="busy"
            :error="deleteError"
            @confirm="remove"
            @cancel="!busy && (deleteOpen = false)"
        />
        <Dialog v-model:open="detailsOpen"
            ><DialogScrollContent class="sm:max-w-xl"
                ><DialogHeader
                    ><DialogTitle>优惠码使用详情</DialogTitle
                    ><DialogDescription>{{
                        details.code
                    }}</DialogDescription></DialogHeader
                ><RecordDetails
                    :value="details"
                    :labels="detailLabels"
                /><DialogFooter
                    ><Button variant="outline" @click="detailsOpen = false"
                        >关闭</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
    </div>
</template>

<style scoped>
.marketing-field {
    display: grid;
    grid-template-columns: 6rem minmax(0, 1fr);
    align-items: center;
    gap: 1rem;
}
.marketing-field > label {
    justify-content: flex-end;
}
.marketing-unit,
.marketing-addon {
    display: flex;
    align-items: center;
    white-space: nowrap;
    padding: 0 0.75rem;
    border: 1px solid var(--border);
    background: var(--muted);
    color: var(--muted-foreground);
    font-size: 0.875rem;
}
.marketing-unit {
    border-left: 0;
    border-radius: 0 var(--radius) var(--radius) 0;
}
.marketing-addon {
    border-right: 0;
    border-radius: var(--radius) 0 0 var(--radius);
}
@media (max-width: 480px) {
    .marketing-field {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    .marketing-field > label {
        justify-content: flex-start;
    }
}
</style>
