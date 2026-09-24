<script setup lang="ts">
import { Plus, Play, Pause, Trash2, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CertificateUserPicker from '@/components/console/CertificateUserPicker.vue';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import RecordDetails from '@/components/console/RecordDetails.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuCheckboxItem,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import Switch from '@/components/ui/switch/Switch.vue';
import { listAdminPackages } from '@/lib/adminPackagesApi';
import { apiRequest } from '@/lib/apiRequest';
import {
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnflyResponse';
import { getErrorMessage, formatMoney, formatDate } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const props = withDefaults(defineProps<{ initialTab?: string }>(), {
    initialTab: 'manage',
});
const tabs = [
    { key: 'manage', label: '流量包管理' },
    { key: 'sold', label: '已售流量包' },
];
const tab = ref(props.initialTab),
    page = ref(1),
    size = ref(10),
    total = ref(0);
const rows = ref<CdnflyRecord[]>([]),
    selected = ref<(string | number)[]>([]);
const loading = ref(false),
    busy = ref(false),
    error = ref('');
const uid = ref(''),
    filterPackage = ref('all'),
    filterStatus = ref('all');
const choices = ref<CdnflyRecord[]>([]),
    bases = ref<CdnflyRecord[]>([]);
const optionsError = ref(''),
    optionsLoading = ref(false);
const endpoint = (sold = tab.value === 'sold') =>
    `/api/admin/workspace/${sold ? 'user-traffic-packages' : 'traffic-packages'}`;
const managementColumns = [
    { key: 'package', label: '流量包' },
    { key: 'price', label: '流量与价格' },
    { key: 'scope', label: '适用与有效期' },
    { key: 'status', label: '状态' },
];
const soldColumns = [
    { key: 'user', label: '用户' },
    { key: 'package', label: '流量包' },
    { key: 'scope', label: '适用与时间' },
    { key: 'status', label: '状态' },
];
let listToken = 0;
async function loadRows() {
    const token = ++listToken;
    loading.value = true;
    error.value = '';
    selected.value = [];
    rows.value = [];

    try {
        const query = new URLSearchParams({
            page: String(page.value),
            limit: String(size.value),
        });

        if (tab.value === 'sold') {
            if (uid.value) {
                query.set('uid', uid.value);
            }

            if (filterPackage.value !== 'all') {
                query.set('traffic_package_id', filterPackage.value);
            }

            if (filterStatus.value !== 'all') {
                query.set('enable', filterStatus.value);
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
watch([tab, size, uid, filterPackage, filterStatus], search);
watch(
    () => props.initialTab,
    (value) => {
        tab.value = value;
    },
);
async function loadOptions() {
    optionsLoading.value = true;
    optionsError.value = '';

    try {
        const [traffic, packages] = await Promise.all([
            apiRequest(`${endpoint(false)}?limit=0`),
            listAdminPackages({ limit: 0 }),
        ]);
        choices.value = extractCdnflyRows(traffic);
        bases.value = extractCdnflyRows(packages);
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
function bindingIds(value: unknown): string[] {
    return (Array.isArray(value) ? value : String(value ?? '').split(','))
        .map(String)
        .filter(Boolean);
}
function bindingLabel(row: CdnflyRecord) {
    if (row.bind_package_name) {
        return String(row.bind_package_name);
    }

    return (
        bindingIds(row.bind_package)
            .map(
                (id) =>
                    bases.value.find((base) => String(base.id) === id)?.name ??
                    `#${id}`,
            )
            .join('、') || '所有套餐'
    );
}
function date(value: unknown) {
    return value ? formatDate(String(value)) : '—';
}
async function status(enable: number) {
    if (!selected.value.length || busy.value) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        await apiRequest(endpoint(), {
            method: 'PUT',
            body: JSON.stringify(
                selected.value.map((id) => ({
                    id: Number(id),
                    enable,
                    ...(tab.value === 'sold' && !enable
                        ? { reason: '其他' }
                        : {}),
                })),
            ),
        });
        await loadRows();
        await loadOptions();
        toast.success('状态已更新');
    } catch (e) {
        error.value = getErrorMessage(e);
    } finally {
        busy.value = false;
    }
}
const editorOpen = ref(false),
    editorReady = ref(false),
    editorLoading = ref(false),
    saving = ref(false),
    editorError = ref('');
const editingId = ref<number | null>(null),
    editingSold = ref(false);
const form = reactive({
    name: '',
    des: '',
    amount: '',
    unit: 'GB',
    packages: [] as string[],
    valid_days: '',
    price: '',
    enable: true,
    expire: '',
    reason: '其他',
});
const bindingOptions = computed(() => [
    ...bases.value,
    ...form.packages
        .filter((id) => !bases.value.some((base) => String(base.id) === id))
        .map((id) => ({ id, name: `#${id}` })),
]);
const bindingSummary = computed(() =>
    form.packages
        .map(
            (id) =>
                bindingOptions.value.find((base) => String(base.id) === id)
                    ?.name ?? id,
        )
        .join('、'),
);
function selectBinding(id: string, checked: boolean) {
    form.packages = checked
        ? [...new Set([...form.packages, id])]
        : form.packages.filter((value) => value !== id);
}
let editorToken = 0;
async function edit(row?: CdnflyRecord) {
    const token = ++editorToken;
    editingId.value = row ? Number(row.id) : null;
    editingSold.value = !!row && tab.value === 'sold';
    editorError.value = '';
    editorLoading.value = !!row;
    editorReady.value = !row;
    Object.assign(form, {
        name: '',
        des: '',
        amount: '',
        unit: 'GB',
        packages: [],
        valid_days: '',
        price: '',
        enable: true,
        expire: '',
        reason: '其他',
    });
    editorOpen.value = true;

    if (!row) {
        return;
    }

    try {
        const record = extractCdnflyRecord(
            await apiRequest(`${endpoint(editingSold.value)}/${row.id}`),
        );

        if (token !== editorToken) {
            return;
        }

        if (!record) {
            throw new Error('无法读取流量包详情');
        }

        const amount = String(record.amount ?? '').match(/^(\d+)(GB|TB|PB)$/i);
        Object.assign(form, {
            name: String(record.name ?? ''),
            des: String(record.des ?? ''),
            amount: amount?.[1] ?? String(record.amount ?? ''),
            unit: amount?.[2].toUpperCase() ?? 'GB',
            packages: bindingIds(record.bind_package),
            valid_days: String(record.valid_days ?? ''),
            price: String(record.price ?? ''),
            enable: String(record.enable) === '1',
            expire: String(record.expire ?? '').replace(' ', 'T'),
            reason: String(record.reason || '其他'),
        });
        editorReady.value = true;
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
const detailReady = computed(() => !editorLoading.value && editorReady.value);
async function save() {
    saving.value = true;
    editorError.value = '';

    try {
        if (
            !form.name.trim() ||
            !/^\d+$/.test(form.amount) ||
            !Number.isSafeInteger(Number(form.amount)) ||
            Number(form.amount) < 1
        ) {
            throw new Error('请填写名称和正整数流量');
        }

        const payload: Record<string, unknown> = {
            name: form.name.trim(),
            amount: `${form.amount}${form.unit}`,
            bind_package: form.packages.join(','),
            enable: form.enable ? 1 : 0,
        };

        if (editingSold.value) {
            if (!form.expire) {
                throw new Error('请选择到期时间');
            }

            if (!form.enable && !form.reason.trim()) {
                throw new Error('请填写禁用原因');
            }

            payload.expire =
                form.expire.replace('T', ' ') +
                (form.expire.length === 16 ? ':00' : '');

            if (!form.enable) {
                payload.reason = form.reason.trim();
            }
        } else {
            if (
                !/^\d+$/.test(form.valid_days) ||
                !Number.isSafeInteger(Number(form.valid_days)) ||
                Number(form.valid_days) < 1
            ) {
                throw new Error('请输入正整数有效天数');
            }

            if (
                !String(form.price).trim() ||
                !Number.isFinite(Number(form.price)) ||
                Number(form.price) < 0
            ) {
                throw new Error('请输入有效价格');
            }

            Object.assign(payload, {
                des: form.des,
                valid_days: Number(form.valid_days),
                price: Number(form.price),
            });
        }

        await apiRequest(
            `${endpoint(editingSold.value)}${editingId.value ? `/${editingId.value}` : ''}`,
            {
                method: editingId.value ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            },
        );
        editorOpen.value = false;
        await loadRows();
        await loadOptions();
        toast.success('保存成功');
    } catch (e) {
        editorError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}
const deleteOpen = ref(false),
    deleteError = ref(''),
    deleteIds = ref<number[]>([]),
    deleteSold = ref(false);
function askDelete(row?: CdnflyRecord) {
    deleteIds.value = row ? [Number(row.id)] : selected.value.map(Number);
    deleteSold.value = tab.value === 'sold';
    deleteError.value = '';
    deleteOpen.value = true;
}
async function remove() {
    busy.value = true;
    deleteError.value = '';
    const failed: number[] = [];

    for (const id of deleteIds.value) {
        try {
            await apiRequest(`${endpoint(deleteSold.value)}/${id}`, {
                method: 'DELETE',
            });
        } catch (e) {
            failed.push(id);
            deleteError.value = getErrorMessage(e);
        }
    }

    await loadRows();
    await loadOptions();
    deleteIds.value = failed;
    selected.value = failed;
    deleteOpen.value = failed.length > 0;

    if (!failed.length) {
        toast.success('删除成功');
    }

    busy.value = false;
}
const detailsOpen = ref(false),
    details = ref<CdnflyRecord>({});
async function showDetails(row: CdnflyRecord) {
    busy.value = true;
    error.value = '';

    try {
        details.value =
            extractCdnflyRecord(await apiRequest(`${endpoint()}/${row.id}`)) ??
            row;
        detailsOpen.value = true;
    } catch (e) {
        error.value = getErrorMessage(e);
    } finally {
        busy.value = false;
    }
}
const assignOpen = ref(false),
    assignUid = ref(''),
    assignId = ref(0),
    assignError = ref('');
function openAssign(row: CdnflyRecord) {
    assignUid.value = '';
    assignId.value = Number(row.id);
    assignError.value = '';
    assignOpen.value = true;
}
async function assign() {
    saving.value = true;
    assignError.value = '';

    try {
        if (!assignUid.value) {
            throw new Error('请选择用户');
        }

        await apiRequest(endpoint(true), {
            method: 'POST',
            body: JSON.stringify({
                uid: Number(assignUid.value),
                traffic_package_id: assignId.value,
            }),
        });
        assignOpen.value = false;
        toast.success('分配成功');
    } catch (e) {
        assignError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="traffic-workspace min-w-0 p-4 md:p-6">
        <div
            class="console-panel rounded-xl border bg-card p-4 text-card-foreground md:p-5"
        >
            <ConsoleTabs v-model="tab" :tabs="tabs" />
            <div
                class="my-4 flex flex-wrap items-center gap-2"
                :class="{ 'rounded-lg border bg-muted/20 p-3': tab === 'sold' }"
            >
                <Button
                    v-if="tab === 'manage'"
                    size="sm"
                    :disabled="busy || optionsLoading || !!optionsError"
                    @click="edit()"
                    ><Plus />添加流量包</Button
                >
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="busy || loading || !selected.length"
                    @click="status(1)"
                    ><Play />启用</Button
                >
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="busy || loading || !selected.length"
                    @click="status(0)"
                    ><Pause />禁用</Button
                >
                <Button
                    v-if="tab === 'manage'"
                    size="sm"
                    variant="destructive"
                    :disabled="busy || loading || !selected.length"
                    @click="askDelete()"
                    ><Trash2 />删除</Button
                >
                <template v-else>
                    <CertificateUserPicker v-model="uid" :disabled="busy" />
                    <Select v-model="filterPackage" :disabled="busy"
                        ><SelectTrigger
                            aria-label="流量包筛选"
                            class="w-full sm:w-52"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent
                            ><SelectItem value="all">所有流量包</SelectItem
                            ><SelectItem
                                v-for="item in choices"
                                :key="String(item.id)"
                                :value="String(item.id)"
                                >{{ item.name }}</SelectItem
                            ></SelectContent
                        ></Select
                    >
                    <Select v-model="filterStatus" :disabled="busy"
                        ><SelectTrigger
                            aria-label="状态筛选"
                            class="w-full sm:w-40"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent
                            ><SelectItem value="all">所有状态</SelectItem
                            ><SelectItem value="1">启用</SelectItem
                            ><SelectItem value="0"
                                >禁用</SelectItem
                            ></SelectContent
                        ></Select
                    >
                    <Button
                        variant="link"
                        :disabled="busy"
                        @click="
                            uid = '';
                            filterPackage = 'all';
                            filterStatus = 'all';
                        "
                        >清除</Button
                    >
                </template>
                <Button
                    size="sm"
                    variant="ghost"
                    class="ml-auto"
                    :disabled="busy || loading"
                    @click="loadRows"
                    ><RefreshCw />刷新</Button
                >
            </div>
            <Alert v-if="error" variant="destructive" class="mb-4"
                ><AlertDescription
                    >{{ error
                    }}<Button variant="link" :disabled="busy" @click="loadRows"
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <Alert v-if="optionsError" variant="destructive" class="mb-4"
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
                selectable
                :selection-disabled="busy"
                :title="tab === 'manage' ? '流量包管理' : '已售流量包'"
                :columns="tab === 'manage' ? managementColumns : soldColumns"
                :data="{ rows, total, page, pageSize: size, loading }"
                empty-text="暂无数据"
            >
                <template #cell-package="{ row }">
                    <Button
                        variant="link"
                        class="h-auto p-0 font-medium"
                        :disabled="busy"
                        @click="edit(row)"
                        >{{ row.name }}</Button
                    >
                    <p class="mt-1 text-xs text-muted-foreground">
                        ID: {{ row.id
                        }}<template v-if="tab === 'sold'">
                            / 流量包 ID: {{ row.traffic_package_id }}</template
                        >
                    </p>
                    <p
                        v-if="tab === 'manage' && row.des"
                        class="mt-1 max-w-64 truncate text-xs text-muted-foreground"
                        :title="String(row.des)"
                    >
                        {{ row.des }}
                    </p>
                    <p
                        v-if="tab === 'sold'"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ row.amount }}
                    </p>
                </template>
                <template #cell-price="{ row }"
                    ><p class="font-medium">{{ row.amount }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ formatMoney(row.price) }}
                    </p></template
                >
                <template #cell-scope="{ row }"
                    ><p>{{ bindingLabel(row) }}</p>
                    <p
                        v-if="tab === 'manage'"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        有效期: {{ row.valid_days }} 天
                    </p>
                    <template v-else
                        ><p class="mt-1 text-xs text-muted-foreground">
                            购买: {{ date(row.create_at) }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            到期: {{ date(row.expire) }}
                        </p></template
                    ></template
                >
                <template #cell-user="{ row }"
                    ><p class="font-medium">
                        {{ row.user_name || row.username || `#${row.uid}` }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        用户 ID: {{ row.uid }}
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
                    >
                    <p
                        v-if="
                            tab === 'sold' &&
                            String(row.enable) !== '1' &&
                            row.reason
                        "
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ row.reason }}
                    </p></template
                >
                <template #row-actions="{ row }"
                    ><div class="flex justify-end gap-1">
                        <Button
                            v-if="tab === 'sold'"
                            size="sm"
                            variant="ghost"
                            :disabled="busy"
                            @click="showDetails(row)"
                            >详情</Button
                        ><Button
                            size="sm"
                            variant="ghost"
                            :disabled="busy || optionsLoading || !!optionsError"
                            @click="edit(row)"
                            >编辑</Button
                        ><Button
                            v-if="tab === 'manage'"
                            size="sm"
                            variant="ghost"
                            :disabled="busy || String(row.enable) !== '1'"
                            @click="openAssign(row)"
                            >分配</Button
                        ><Button
                            size="sm"
                            variant="ghost"
                            :disabled="busy"
                            @click="askDelete(row)"
                            >删除</Button
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
        >
            <DialogScrollContent class="traffic-editor sm:max-w-xl">
                <DialogHeader
                    ><DialogTitle>{{
                        editingId ? '编辑流量包' : '添加流量包'
                    }}</DialogTitle
                    ><DialogDescription class="sr-only"
                        >设置流量、适用套餐和{{
                            editingSold ? '到期时间' : '有效期及价格'
                        }}</DialogDescription
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
                    id="traffic-form"
                    class="space-y-6 py-4"
                    @submit.prevent="save"
                >
                    <fieldset
                        :disabled="saving || !detailReady"
                        class="space-y-6"
                    >
                        <div class="traffic-field">
                            <Label for="traffic-name">名称</Label
                            ><Input
                                id="traffic-name"
                                v-model="form.name"
                                placeholder="请输入流量包名称"
                            />
                        </div>
                        <div v-if="!editingSold" class="traffic-field">
                            <Label for="traffic-des">备注</Label
                            ><Input
                                id="traffic-des"
                                v-model="form.des"
                                placeholder="请输入备注"
                            />
                        </div>
                        <div class="traffic-field">
                            <Label for="traffic-amount">流量</Label>
                            <div class="flex min-w-0">
                                <Input
                                    id="traffic-amount"
                                    v-model="form.amount"
                                    type="number"
                                    min="1"
                                    step="1"
                                    placeholder="请输入流量"
                                    class="rounded-r-none"
                                /><Select v-model="form.unit" :disabled="saving"
                                    ><SelectTrigger
                                        aria-label="流量单位"
                                        class="w-24 shrink-0 rounded-l-none border-l-0"
                                        ><SelectValue /></SelectTrigger
                                    ><SelectContent
                                        ><SelectItem
                                            v-for="unit in ['GB', 'TB', 'PB']"
                                            :key="unit"
                                            :value="unit"
                                            >{{ unit }}</SelectItem
                                        ></SelectContent
                                    ></Select
                                >
                            </div>
                        </div>
                        <div class="traffic-field">
                            <Label>适用套餐</Label
                            ><DropdownMenu
                                ><DropdownMenuTrigger as-child
                                    ><Button
                                        variant="outline"
                                        aria-label="适用套餐"
                                        class="w-full justify-start overflow-hidden"
                                        :disabled="
                                            saving ||
                                            optionsLoading ||
                                            !!optionsError
                                        "
                                        ><span
                                            class="truncate"
                                            :class="{
                                                'text-muted-foreground':
                                                    !form.packages.length,
                                            }"
                                            >{{
                                                bindingSummary ||
                                                '不选择则所有套餐可用'
                                            }}</span
                                        ></Button
                                    ></DropdownMenuTrigger
                                ><DropdownMenuContent
                                    class="max-h-64 w-72 overflow-auto"
                                    ><DropdownMenuCheckboxItem
                                        v-for="item in bindingOptions"
                                        :key="String(item.id)"
                                        :model-value="
                                            form.packages.includes(
                                                String(item.id),
                                            )
                                        "
                                        @update:model-value="
                                            (value) =>
                                                selectBinding(
                                                    String(item.id),
                                                    !!value,
                                                )
                                        "
                                        @select.prevent
                                        >{{
                                            item.name
                                        }}</DropdownMenuCheckboxItem
                                    >
                                    <p
                                        v-if="!bindingOptions.length"
                                        class="p-3 text-sm text-muted-foreground"
                                    >
                                        暂无套餐
                                    </p></DropdownMenuContent
                                ></DropdownMenu
                            >
                        </div>
                        <template v-if="!editingSold">
                            <div class="traffic-field">
                                <Label for="traffic-days">有效期</Label>
                                <div class="flex">
                                    <Input
                                        id="traffic-days"
                                        v-model="form.valid_days"
                                        type="number"
                                        min="1"
                                        step="1"
                                        placeholder="请输入有效期"
                                        class="rounded-r-none"
                                    /><span class="traffic-unit">天</span>
                                </div>
                            </div>
                            <div class="traffic-field">
                                <Label for="traffic-price">价格</Label>
                                <div class="flex">
                                    <Input
                                        id="traffic-price"
                                        v-model="form.price"
                                        type="number"
                                        min="0"
                                        step="any"
                                        placeholder="请输入价格"
                                        class="rounded-r-none"
                                    /><span class="traffic-unit">USDT</span>
                                </div>
                            </div>
                        </template>
                        <div v-else class="traffic-field">
                            <Label for="traffic-expire">到期时间</Label
                            ><Input
                                id="traffic-expire"
                                v-model="form.expire"
                                type="datetime-local"
                                step="1"
                            />
                        </div>
                        <div class="traffic-field">
                            <Label for="traffic-enable">状态</Label>
                            <div class="flex items-center gap-2">
                                <Switch
                                    id="traffic-enable"
                                    :checked="form.enable"
                                    :disabled="saving"
                                    @update:checked="form.enable = $event"
                                /><span class="text-sm text-muted-foreground">{{
                                    form.enable ? '启用' : '禁用'
                                }}</span>
                            </div>
                        </div>
                        <div
                            v-if="editingSold && !form.enable"
                            class="traffic-field"
                        >
                            <Label for="traffic-reason">禁用原因</Label
                            ><Input id="traffic-reason" v-model="form.reason" />
                        </div>
                    </fieldset>
                </form>
                <DialogFooter
                    ><Button
                        type="submit"
                        form="traffic-form"
                        :disabled="
                            saving ||
                            !detailReady ||
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
            </DialogScrollContent>
        </Dialog>
        <ConfirmDeleteDialog
            :open="deleteOpen"
            title="删除流量包"
            :description="`确定删除所选 ${deleteIds.length} 个流量包？`"
            :loading="busy"
            :error="deleteError"
            @confirm="remove"
            @cancel="!busy && (deleteOpen = false)"
        />
        <Dialog v-model:open="detailsOpen"
            ><DialogScrollContent class="sm:max-w-2xl"
                ><DialogHeader
                    ><DialogTitle>流量包详情</DialogTitle
                    ><DialogDescription>{{
                        details.name
                    }}</DialogDescription></DialogHeader
                ><RecordDetails :value="details" /><DialogFooter
                    ><Button variant="outline" @click="detailsOpen = false"
                        >关闭</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <Dialog v-model:open="assignOpen"
            ><DialogScrollContent class="sm:max-w-md"
                ><DialogHeader
                    ><DialogTitle>分配流量包</DialogTitle
                    ><DialogDescription
                        >选择接收此流量包的用户。</DialogDescription
                    ></DialogHeader
                ><Alert v-if="assignError" variant="destructive"
                    ><AlertDescription>{{
                        assignError
                    }}</AlertDescription></Alert
                ><CertificateUserPicker
                    v-model="assignUid"
                    :disabled="saving"
                /><DialogFooter
                    ><Button :disabled="saving || !assignUid" @click="assign"
                        >确定</Button
                    ><Button
                        variant="outline"
                        :disabled="saving"
                        @click="assignOpen = false"
                        >取消</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
    </div>
</template>

<style scoped>
.traffic-field {
    display: grid;
    grid-template-columns: 5.5rem minmax(0, 1fr);
    align-items: center;
    gap: 1rem;
}
.traffic-field > label {
    justify-content: flex-end;
}
.traffic-unit {
    display: flex;
    align-items: center;
    padding: 0 0.75rem;
    border: 1px solid var(--border);
    border-left: 0;
    border-radius: 0 var(--radius) var(--radius) 0;
    background: var(--muted);
    color: var(--muted-foreground);
    font-size: 0.875rem;
}
@media (max-width: 480px) {
    .traffic-field {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    .traffic-field > label {
        justify-content: flex-start;
    }
}
</style>
