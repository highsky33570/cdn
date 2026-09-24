<script setup lang="ts">
import { Plus, Play, Pause, Trash2, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CertificateUserPicker from '@/components/console/CertificateUserPicker.vue';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
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
import {
    listAdminPackageUps,
    createAdminPackageUp,
    updateAdminPackageUp,
    listAdminCdnflyUserPackages,
} from '@/lib/adminModulesApi';
import { listAdminPackages } from '@/lib/adminPackagesApi';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import { getErrorMessage, formatMoney, formatDate } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false });
import { recordData } from '@/lib/soldPackages';

const tabs = [
    { key: 'manage', label: '升级包管理' },
    { key: 'sold', label: '已售升级包' },
];
const tab = ref('manage'),
    page = ref(1),
    size = ref(10),
    total = ref(0),
    loading = ref(false),
    error = ref(''),
    busy = ref(false);
const rows = ref<CdnflyRecord[]>([]),
    selected = ref<(string | number)[]>([]),
    uid = ref(''),
    filterUpgrade = ref('all');
const choices = ref<CdnflyRecord[]>([]),
    bases = ref<CdnflyRecord[]>([]),
    optionsError = ref('');
const types: Record<string, string> = {
    traffic: '流量',
    domain: '域名数',
    main_domain: '主域名数',
    http_port: 'HTTP端口数',
    stream_port: '转发端口数',
    custom_cc_rule: '自定义CC规则',
    waf_protect: 'WAF 防护',
};
const managementColumns = [
    { key: 'upgrade', label: '升级包' },
    { key: 'spec', label: '规格' },
    { key: 'status', label: '状态' },
];
const soldColumns = [
    { key: 'user', label: '用户' },
    { key: 'user_package', label: '关联用户套餐' },
    { key: 'upgrade', label: '升级包' },
];
let listToken = 0;
async function loadRows() {
    const token = ++listToken;
    loading.value = true;
    error.value = '';
    selected.value = [];

    try {
        const query: Record<string, string | number> = {
            page: page.value,
            limit: size.value,
        };

        if (uid.value) {
            query.uid = uid.value;
        }

        if (filterUpgrade.value !== 'all') {
            query.package_up = filterUpgrade.value;
        }

        const response =
            tab.value === 'manage'
                ? await listAdminPackageUps({
                      page: page.value,
                      limit: size.value,
                  })
                : await apiRequest(
                      `/api/admin/package-upgrades/sold?${new URLSearchParams(Object.entries(query).map(([k, v]) => [k, String(v)]))}`,
                  );

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
watch([tab, size, uid, filterUpgrade], search);
async function loadOptions() {
    optionsError.value = '';

    try {
        const [up, packages] = await Promise.all([
            listAdminPackageUps({ limit: 0 }),
            listAdminPackages({ limit: 0 }),
        ]);
        choices.value = extractCdnflyRows(up);
        bases.value = extractCdnflyRows(packages);
    } catch (e) {
        optionsError.value = getErrorMessage(e);
    }
}
onMounted(() => {
    void loadRows();
    void loadOptions();
});
async function status(enable: number) {
    busy.value = true;
    error.value = '';

    try {
        await apiRequest('/api/admin/package-upgrades/status', {
            method: 'PUT',
            body: JSON.stringify({ ids: selected.value.map(Number), enable }),
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

    try {
        await apiRequest(
            deleteSold.value
                ? `/api/admin/package-upgrades/sold/${deleteIds.value[0]}`
                : '/api/admin/package-upgrades/batch',
            {
                method: 'DELETE',
                ...(deleteSold.value
                    ? {}
                    : { body: JSON.stringify({ ids: deleteIds.value }) }),
            },
        );
        deleteOpen.value = false;
        await loadRows();
        await loadOptions();
        toast.success('已删除');
    } catch (e) {
        deleteError.value = getErrorMessage(e);
    } finally {
        busy.value = false;
    }
}

const editorOpen = ref(false),
    editorReady = ref(false),
    editorLoading = ref(false),
    saving = ref(false),
    editorError = ref(''),
    editingId = ref(0);
const form = reactive({
    name: '',
    des: '',
    type: '',
    amount: '1',
    price: '',
    packages: [] as string[],
    enable: true,
});
let editToken = 0;
function create() {
    ++editToken;
    editorReady.value = true;
    editingId.value = 0;
    editorError.value = '';
    editorLoading.value = false;
    Object.assign(form, {
        name: '',
        des: '',
        type: '',
        amount: '1',
        price: '',
        packages: [],
        enable: true,
    });
    editorOpen.value = true;
}
async function edit(row: CdnflyRecord) {
    const token = ++editToken;
    editorReady.value = false;
    editingId.value = Number(row.id);
    editorError.value = '';
    editorOpen.value = true;
    editorLoading.value = true;

    try {
        const data = recordData(
            await apiRequest(`/api/admin/package-upgrades/${row.id}`),
        );

        if (token !== editToken) {
            return;
        }

        Object.assign(form, {
            name: String(data.name ?? ''),
            des: String(data.des ?? ''),
            type: String(data.type ?? ''),
            amount: String(data.amount ?? 1),
            price: String(data.price ?? ''),
            packages: String(data.bind_package ?? '')
                .split(',')
                .filter(Boolean),
            enable: String(data.enable) === '1',
        });
        editorReady.value = true;
    } catch (e) {
        if (token === editToken) {
            editorError.value = getErrorMessage(e);
        }
    } finally {
        if (token === editToken) {
            editorLoading.value = false;
        }
    }
}
const bindings = computed(() =>
    form.packages
        .map((id) =>
            String(
                bases.value.find((p) => String(p.id) === id)?.name ?? `#${id}`,
            ),
        )
        .join('、'),
);
function bind(id: string, checked: boolean | 'indeterminate') {
    form.packages = form.packages.filter((p) => p !== id);

    if (checked === true) {
        form.packages.push(id);
    }
}
async function save() {
    saving.value = true;
    editorError.value = '';

    try {
        const amount = form.type === 'waf_protect' ? 1 : Number(form.amount);

        if (
            !form.name.trim() ||
            !form.type ||
            form.amount === '' ||
            !Number.isSafeInteger(amount) ||
            amount < 0 ||
            form.price === '' ||
            !Number.isFinite(Number(form.price)) ||
            Number(form.price) < 0
        ) {
            throw new Error('请填写名称、类型、有效数量和价格');
        }

        const payload = {
            name: form.name.trim(),
            des: form.des,
            amount,
            price: Number(form.price),
            bind_package: form.packages.join(','),
            enable: form.enable ? 1 : 0,
        };

        if (editingId.value) {
            await updateAdminPackageUp(editingId.value, payload);
        } else {
            await createAdminPackageUp({ ...payload, type: form.type });
        }

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

const quantityOpen = ref(false),
    quantityReady = ref(false),
    quantity = ref('1'),
    quantityId = ref(0),
    quantityLoading = ref(false),
    quantityError = ref('');
let quantityToken = 0;
async function editQuantity(row: CdnflyRecord) {
    const token = ++quantityToken;
    quantityReady.value = false;
    quantityId.value = Number(row.id);
    quantityOpen.value = true;
    quantityLoading.value = true;
    quantityError.value = '';

    try {
        const data = recordData(
            await apiRequest(`/api/admin/package-upgrades/sold/${row.id}`),
        );

        if (token === quantityToken) {
            quantity.value = String(data.amount);
            quantityReady.value = true;
        }
    } catch (e) {
        if (token === quantityToken) {
            quantityError.value = getErrorMessage(e);
        }
    } finally {
        if (token === quantityToken) {
            quantityLoading.value = false;
        }
    }
}
async function saveQuantity() {
    saving.value = true;
    quantityError.value = '';

    try {
        if (
            !Number.isSafeInteger(Number(quantity.value)) ||
            Number(quantity.value) < 1
        ) {
            throw new Error('请输入正整数数量');
        }

        await apiRequest(
            `/api/admin/package-upgrades/sold/${quantityId.value}`,
            {
                method: 'PUT',
                body: JSON.stringify({ amount: Number(quantity.value) }),
            },
        );
        quantityOpen.value = false;
        await loadRows();
        toast.success('数量已更新');
    } catch (e) {
        quantityError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}

const assignOpen = ref(false),
    assignTarget = ref<CdnflyRecord>({}),
    assignUid = ref(''),
    assignPackage = ref(''),
    assignAmount = ref('1'),
    assignError = ref(''),
    assignLoading = ref(false),
    userPackages = ref<CdnflyRecord[]>([]);
let assignToken = 0;
function openAssign(row: CdnflyRecord) {
    ++assignToken;
    assignTarget.value = row;
    assignUid.value = '';
    assignPackage.value = '';
    assignAmount.value = '1';
    assignError.value = '';
    userPackages.value = [];
    assignLoading.value = false;
    assignOpen.value = true;
}
async function loadUserPackages() {
    const token = ++assignToken;
    assignPackage.value = '';
    userPackages.value = [];
    assignError.value = '';
    assignLoading.value = false;

    if (!assignUid.value) {
        return;
    }

    assignLoading.value = true;

    try {
        const result = await listAdminCdnflyUserPackages({
            limit: 0,
            uid: assignUid.value,
        });

        if (token === assignToken) {
            userPackages.value = extractCdnflyRows(result);
        }
    } catch (e) {
        if (token === assignToken) {
            assignError.value = getErrorMessage(e);
        }
    } finally {
        if (token === assignToken) {
            assignLoading.value = false;
        }
    }
}
watch(assignUid, loadUserPackages);
async function assign() {
    saving.value = true;
    assignError.value = '';

    try {
        const amount =
            assignTarget.value.type === 'waf_protect'
                ? 1
                : Number(assignAmount.value);

        if (
            !assignUid.value ||
            !assignPackage.value ||
            !Number.isSafeInteger(amount) ||
            amount < 1
        ) {
            throw new Error('请选择用户、用户套餐和有效数量');
        }

        await apiRequest('/api/admin/package-upgrades/assign', {
            method: 'POST',
            body: JSON.stringify({
                uid: Number(assignUid.value),
                user_package: Number(assignPackage.value),
                package_up: Number(assignTarget.value.id),
                amount,
            }),
        });
        assignOpen.value = false;
        await loadRows();
        toast.success('分配成功');
    } catch (e) {
        assignError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div
        class="upgrade-workspace min-w-0 space-y-4"
        :class="{ 'p-4 md:p-6': !embedded }"
    >
        <div
            class="console-panel rounded-xl border bg-card p-4 text-card-foreground md:p-5"
        >
            <ConsoleTabs v-model="tab" :tabs="tabs" />
            <div v-if="tab === 'manage'" class="my-4 flex flex-wrap gap-2">
                <Button size="sm" @click="create"><Plus />新增升级包</Button>
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
                    size="sm"
                    variant="destructive"
                    :disabled="busy || loading || !selected.length"
                    @click="askDelete()"
                    ><Trash2 />删除</Button
                >
                <Button
                    size="sm"
                    variant="ghost"
                    class="ml-auto"
                    :disabled="busy || loading"
                    @click="loadRows"
                    ><RefreshCw />刷新</Button
                >
            </div>
            <div
                v-else
                class="my-4 flex flex-wrap items-center gap-3 rounded-lg border bg-muted/20 p-3"
            >
                <CertificateUserPicker v-model="uid" />
                <Select v-model="filterUpgrade"
                    ><SelectTrigger aria-label="升级包筛选" class="w-56"
                        ><SelectValue /></SelectTrigger
                    ><SelectContent
                        ><SelectItem value="all">所有升级包</SelectItem
                        ><SelectItem
                            v-for="up in choices"
                            :key="String(up.id)"
                            :value="String(up.id)"
                            >{{ up.name }}</SelectItem
                        ></SelectContent
                    ></Select
                >
                <Button
                    variant="link"
                    @click="
                        uid = '';
                        filterUpgrade = 'all';
                    "
                    >清除</Button
                >
                <Button
                    size="sm"
                    variant="ghost"
                    class="ml-auto"
                    :disabled="loading"
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
                embedded
                :title="tab === 'manage' ? '升级包管理' : '已售升级包'"
                :columns="tab === 'manage' ? managementColumns : soldColumns"
                :data="{ rows, total, page, pageSize: size, loading }"
                :selectable="tab === 'manage'"
                v-model:selected="selected"
                :selection-disabled="busy"
                empty-text="暂无数据"
            >
                <template #cell-upgrade="{ row }">
                    <template v-if="tab === 'manage'"
                        ><Button
                            variant="link"
                            class="h-auto p-0 font-medium"
                            :disabled="busy"
                            @click="edit(row)"
                            >{{ row.name }}</Button
                        >
                        <p class="mt-1 text-xs text-muted-foreground">
                            ID: {{ row.id }} / 绑定:
                            {{ row.bind_package || '所有套餐' }}
                        </p></template
                    >
                    <template v-else
                        ><p class="font-medium">{{ row.package_up_name }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            升级包 ID: {{ row.package_up }} /
                            {{
                                types[String(row.package_up_type)] ??
                                row.package_up_type
                            }}
                        </p></template
                    >
                </template>
                <template #cell-spec="{ row }"
                    ><p>{{ types[String(row.type)] ?? row.type }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        数量:
                        {{
                            row.type === 'waf_protect'
                                ? '固定 1 份'
                                : row.type === 'custom_cc_rule'
                                  ? '允许'
                                  : `${row.amount}${row.type === 'traffic' ? 'GB' : '个'}`
                        }}
                        / 价格: {{ formatMoney(row.price) }}/月
                    </p>
                    <p
                        v-if="row.create_at"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        创建: {{ formatDate(String(row.create_at)) }}
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
                <template #cell-user="{ row }"
                    ><p class="font-medium">{{ row.user_name }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        用户 ID: {{ row.uid }} / 记录 ID: {{ row.id }}
                    </p></template
                >
                <template #cell-user_package="{ row }"
                    ><p class="font-medium">{{ row.user_package_name }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        用户套餐 ID: {{ row.user_package }} /
                        {{
                            row.package_up_type === 'waf_protect'
                                ? '固定 1 份'
                                : `数量: ${row.amount}`
                        }}
                    </p></template
                >
                <template #row-actions="{ row }"
                    ><div class="flex justify-end gap-1">
                        <template v-if="tab === 'manage'"
                            ><Button
                                size="sm"
                                variant="ghost"
                                :disabled="busy"
                                @click="edit(row)"
                                >编辑</Button
                            ><Button
                                size="sm"
                                variant="ghost"
                                :disabled="busy || String(row.enable) !== '1'"
                                @click="openAssign(row)"
                                >分配</Button
                            ></template
                        ><Button
                            v-else-if="row.package_up_type !== 'waf_protect'"
                            size="sm"
                            variant="ghost"
                            :disabled="busy"
                            @click="editQuantity(row)"
                            >编辑</Button
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
            @update:open="!saving && (editorOpen = $event)"
            ><DialogScrollContent
                class="my-3 flex max-h-[calc(100dvh-24px)] w-[calc(100%-24px)] max-w-xl flex-col gap-0 overflow-hidden p-0"
            >
                <DialogHeader class="shrink-0 border-b p-5"
                    ><DialogTitle>{{
                        editingId ? '编辑升级包' : '新增升级包'
                    }}</DialogTitle
                    ><DialogDescription class="sr-only"
                        >配置升级包资源、价格和适用套餐。</DialogDescription
                    ></DialogHeader
                >
                <div class="min-h-0 space-y-6 overflow-y-auto p-6">
                    <p v-if="editorLoading">加载中…</p>
                    <Alert v-if="editorError" variant="destructive"
                        ><AlertDescription
                            >{{ editorError
                            }}<Button
                                v-if="editingId"
                                variant="link"
                                @click="edit({ id: editingId })"
                                >重新加载</Button
                            ></AlertDescription
                        ></Alert
                    >
                    <template v-if="!editorLoading && editorReady">
                        <div class="upgrade-field">
                            <Label for="upgrade-name">名称</Label
                            ><Input
                                id="upgrade-name"
                                v-model="form.name"
                                placeholder="请输入升级包名称"
                                :disabled="saving"
                            />
                        </div>
                        <div class="upgrade-field">
                            <Label for="upgrade-des">备注</Label
                            ><Input
                                id="upgrade-des"
                                v-model="form.des"
                                placeholder="请输入备注"
                                :disabled="saving"
                            />
                        </div>
                        <div class="upgrade-field">
                            <Label for="upgrade-type">类型</Label
                            ><Select
                                v-model="form.type"
                                :disabled="saving || !!editingId"
                                ><SelectTrigger id="upgrade-type" class="w-full"
                                    ><SelectValue
                                        placeholder="请选择类型" /></SelectTrigger
                                ><SelectContent
                                    ><SelectItem
                                        v-for="(label, key) in types"
                                        :key="key"
                                        :value="key"
                                        >{{ label
                                        }}{{
                                            key === 'traffic'
                                                ? '（不推荐，请使用流量包）'
                                                : ''
                                        }}</SelectItem
                                    ></SelectContent
                                ></Select
                            >
                        </div>
                        <div class="upgrade-field">
                            <Label for="upgrade-amount">数量</Label
                            ><Input
                                id="upgrade-amount"
                                :model-value="
                                    form.type === 'waf_protect'
                                        ? '1'
                                        : form.amount
                                "
                                @update:model-value="
                                    form.amount = String($event)
                                "
                                type="number"
                                min="0"
                                step="1"
                                :disabled="
                                    saving || form.type === 'waf_protect'
                                "
                            />
                        </div>
                        <div class="upgrade-field">
                            <Label for="upgrade-price">价格</Label>
                            <div class="flex min-w-0 items-center">
                                <Input
                                    id="upgrade-price"
                                    v-model="form.price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="请输入价格"
                                    :disabled="saving"
                                    class="rounded-r-none"
                                /><span
                                    class="shrink-0 rounded-r-md border border-l-0 bg-muted px-3 py-2 text-xs text-muted-foreground"
                                    >USDT/月</span
                                >
                            </div>
                        </div>
                        <div class="upgrade-field">
                            <Label>绑定套餐</Label
                            ><DropdownMenu
                                ><DropdownMenuTrigger as-child
                                    ><Button
                                        variant="outline"
                                        aria-label="绑定套餐"
                                        :disabled="saving || !!optionsError"
                                        class="h-auto min-h-9 justify-between text-left whitespace-normal"
                                        >{{ bindings || '不选择则所有套餐可用'
                                        }}<span class="ml-2">⌄</span></Button
                                    ></DropdownMenuTrigger
                                ><DropdownMenuContent
                                    class="max-h-64 overflow-y-auto"
                                    ><DropdownMenuCheckboxItem
                                        v-for="item in bases"
                                        :key="String(item.id)"
                                        :model-value="
                                            form.packages.includes(
                                                String(item.id),
                                            )
                                        "
                                        @update:model-value="
                                            bind(String(item.id), $event)
                                        "
                                        @select.prevent
                                        >{{ item.name }} (#{{
                                            item.id
                                        }})</DropdownMenuCheckboxItem
                                    ></DropdownMenuContent
                                ></DropdownMenu
                            >
                        </div>
                        <div class="upgrade-field">
                            <Label for="upgrade-enable">状态</Label>
                            <div class="flex items-center gap-2">
                                <Switch
                                    id="upgrade-enable"
                                    :checked="form.enable"
                                    @update:checked="form.enable = $event"
                                    :disabled="saving"
                                /><span class="text-sm">{{
                                    form.enable ? '启用' : '禁用'
                                }}</span>
                            </div>
                        </div>
                    </template>
                </div>
                <DialogFooter class="shrink-0 border-t bg-background p-4"
                    ><Button
                        :disabled="
                            saving ||
                            editorLoading ||
                            !editorReady ||
                            !form.type
                        "
                        @click="save"
                        >确定</Button
                    ><Button
                        variant="outline"
                        :disabled="saving"
                        @click="editorOpen = false"
                        >取消</Button
                    ></DialogFooter
                >
            </DialogScrollContent></Dialog
        >

        <Dialog
            :open="quantityOpen"
            @update:open="!saving && (quantityOpen = $event)"
            ><DialogScrollContent class="max-w-md"
                ><DialogHeader
                    ><DialogTitle>编辑用户升级包</DialogTitle
                    ><DialogDescription
                        >修改此用户套餐的升级数量。</DialogDescription
                    ></DialogHeader
                ><Alert v-if="quantityError" variant="destructive"
                    ><AlertDescription
                        >{{ quantityError
                        }}<Button
                            v-if="!quantityReady"
                            variant="link"
                            @click="editQuantity({ id: quantityId })"
                            >重试</Button
                        ></AlertDescription
                    ></Alert
                >
                <div class="upgrade-field">
                    <Label for="sold-amount">数量</Label
                    ><Input
                        id="sold-amount"
                        v-model="quantity"
                        type="number"
                        min="1"
                        step="1"
                        :disabled="quantityLoading || saving"
                    />
                </div>
                <DialogFooter
                    ><Button
                        :disabled="saving || quantityLoading || !quantityReady"
                        @click="saveQuantity"
                        >确定</Button
                    ><Button
                        variant="outline"
                        :disabled="saving"
                        @click="quantityOpen = false"
                        >取消</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >

        <Dialog
            :open="assignOpen"
            @update:open="!saving && (assignOpen = $event)"
            ><DialogScrollContent class="max-w-xl"
                ><DialogHeader
                    ><DialogTitle>分配升级包</DialogTitle
                    ><DialogDescription>{{
                        assignTarget.name
                    }}</DialogDescription></DialogHeader
                ><Alert v-if="assignError" variant="destructive"
                    ><AlertDescription
                        >{{ assignError
                        }}<Button variant="link" @click="loadUserPackages"
                            >重新加载套餐</Button
                        ></AlertDescription
                    ></Alert
                >
                <div class="space-y-5">
                    <div class="upgrade-field">
                        <Label>用户</Label
                        ><CertificateUserPicker
                            v-model="assignUid"
                            :disabled="saving"
                        />
                    </div>
                    <div class="upgrade-field">
                        <Label for="assign-package">用户套餐</Label
                        ><Select
                            v-model="assignPackage"
                            :disabled="saving || assignLoading || !assignUid"
                            ><SelectTrigger id="assign-package" class="w-full"
                                ><SelectValue
                                    :placeholder="
                                        assignLoading
                                            ? '加载中…'
                                            : '请选择用户套餐'
                                    " /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="item in userPackages"
                                    :key="String(item.id)"
                                    :value="String(item.id)"
                                    >{{ item.name }} (#{{
                                        item.id
                                    }})</SelectItem
                                ></SelectContent
                            ></Select
                        >
                    </div>
                    <div class="upgrade-field">
                        <Label for="assign-amount">数量</Label
                        ><Input
                            id="assign-amount"
                            v-model="assignAmount"
                            type="number"
                            min="1"
                            step="1"
                            :disabled="
                                saving || assignTarget.type === 'waf_protect'
                            "
                        />
                    </div>
                </div>
                <DialogFooter
                    ><Button
                        :disabled="saving || assignLoading || !assignPackage"
                        @click="assign"
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
        <ConfirmDeleteDialog
            :open="deleteOpen"
            :description="`确认删除所选 ${deleteIds.length} 项${deleteSold ? '已售升级包' : '升级包'}？`"
            :loading="busy"
            :error="deleteError"
            @confirm="remove"
            @cancel="!busy && (deleteOpen = false)"
        />
    </div>
</template>

<style scoped>
.upgrade-field {
    display: grid;
    grid-template-columns: 90px minmax(0, 1fr);
    align-items: center;
    gap: 14px;
}
.upgrade-field > label {
    justify-content: flex-end;
    text-align: right;
}
@media (max-width: 480px) {
    .upgrade-field {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .upgrade-field > label {
        justify-content: flex-start;
        text-align: left;
    }
}
</style>
