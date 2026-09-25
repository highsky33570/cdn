<script setup lang="ts">
import {
    Play,
    Pause,
    RefreshCw,
    Trash2,
    ShoppingCart,
    Activity,
    ChartColumn,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import Switch from '@/components/ui/switch/Switch.vue';
import {
    listAdminCdnflyUserPackages,
    updateAdminUserPackage,
    deleteAdminUserPackage,
    listAdminUserPackageUpgrades,
    removeAdminUserPackageUpgrade,
    listAdminPackageUps,
} from '@/lib/adminModulesApi';
import {
    listAdminPackages,
    listAdminPackageOptions,
} from '@/lib/adminPackagesApi';
import type { AdminPackageOptions } from '@/lib/adminPackagesApi';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import { formatDate, formatMoney, getErrorMessage } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import {
    soldLimits,
    soldCapabilities,
    recordData,
    resources,
} from '@/lib/soldPackages';

const rows = ref<CdnflyRecord[]>([]),
    total = ref(0),
    page = ref(1),
    size = ref(10),
    loading = ref(false),
    error = ref(''),
    selected = ref<number[]>([]);
const filters = reactive({
    expire: 'all',
    base_package: 'all',
    order_by: 'id',
    enable: 'all',
    traffic_exceed: 'all',
    uid: '',
    user_package: '',
    cname_hostname: '',
});
const basePackages = ref<CdnflyRecord[]>([]),
    options = ref<AdminPackageOptions>({
        regions: [],
        node_groups: [],
        package_groups: [],
        cname_domains: [],
    }),
    optionsError = ref('');
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

        for (const [key, value] of Object.entries(filters)) {
            if (value !== '' && value !== 'all') {
                query[key] = value;
            }
        }

        const data = await listAdminCdnflyUserPackages(query);

        if (token !== listToken) {
            return;
        }

        rows.value = extractCdnflyRows(data);
        total.value = extractCdnflyTotal(data, rows.value.length);
        const last = Math.max(1, Math.ceil(total.value / size.value));

        if (page.value > last) {
            page.value = last;
        }
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
function clear() {
    Object.assign(filters, {
        expire: 'all',
        base_package: 'all',
        order_by: 'id',
        enable: 'all',
        traffic_exceed: 'all',
        uid: '',
        user_package: '',
        cname_hostname: '',
    });
    search();
}
watch(page, loadRows);
watch(size, search);
async function loadOptions() {
    optionsError.value = '';

    try {
        const [bases, opts] = await Promise.all([
            listAdminPackages({ limit: 0 }),
            listAdminPackageOptions(),
        ]);
        basePackages.value = extractCdnflyRows(bases);
        options.value = opts;
    } catch (e) {
        optionsError.value = getErrorMessage(e);
    }
}
onMounted(() => {
    void loadRows();
    void loadOptions();
});
const columns = [
    { key: 'sold', label: '已售套餐' },
    { key: 'base', label: '基础套餐' },
    { key: 'period', label: '周期' },
    { key: 'traffic', label: '已用 / 总流量' },
    { key: 'status', label: '状态' },
];
const baseId = (row: CdnflyRecord) => row.package ?? row.package_id;
const baseName = (row: CdnflyRecord) =>
    row.package_name ??
    basePackages.value.find((p) => String(p.id) === String(baseId(row)))
        ?.name ??
    '—';
function status(row: CdnflyRecord) {
    if (String(row.enable) === '0') {
        return row.reason ? `禁用 (${row.reason})` : '禁用';
    }

    if (String(row.traffic_exceed) === '1') {
        return '消耗流量包中';
    }

    return '正常';
}
const valueText = (value: unknown) =>
    value === undefined || value === null || value === ''
        ? '—'
        : String(value) === '-1'
          ? '不限'
          : String(value);
function trafficTotal(row: CdnflyRecord) {
    return String(row.traffic) === '-1'
        ? '不限'
        : row.traffic === undefined
          ? '—'
          : `${Number(row.traffic) + Number(row.traffic_upgrade ?? 0)}GB`;
}
const busy = ref(false),
    actionOpen = ref(false),
    actionKind = ref<'sync' | 'disable'>('sync'),
    actionError = ref(''),
    reason = ref(''),
    syncFields = ref<string[]>([]);
function openAction(kind: 'sync' | 'disable') {
    actionKind.value = kind;
    reason.value = '';
    syncFields.value = [];
    actionError.value = '';
    actionOpen.value = true;
}
function selectSync(key: string, on: boolean | 'indeterminate') {
    if (on === true) {
        syncFields.value =
            key === 'cname_mode'
                ? [key]
                : [...syncFields.value.filter((k) => k !== 'cname_mode'), key];
    } else {
        syncFields.value = syncFields.value.filter((k) => k !== key);
    }
}
async function bulkUpdate(patch: Record<string, unknown>) {
    if (!selected.value.length) {
        return;
    }

    busy.value = true;
    actionError.value = '';
    error.value = '';
    const failed: number[] = [];
    let message = '';

    for (const id of selected.value) {
        try {
            await updateAdminUserPackage(id, patch);
        } catch (e) {
            failed.push(id);
            message = getErrorMessage(e);
        }
    }

    await loadRows();
    selected.value = failed;
    busy.value = false;

    if (failed.length) {
        actionError.value = message;
        error.value = message;
    } else {
        actionOpen.value = false;
        toast.success('操作成功');
    }
}
const deleteOpen = ref(false),
    deleteError = ref('');
async function removeSelected() {
    busy.value = true;
    deleteError.value = '';
    const failed: number[] = [];

    for (const id of selected.value) {
        try {
            await deleteAdminUserPackage(id);
        } catch (e) {
            failed.push(id);
            deleteError.value = getErrorMessage(e);
        }
    }

    await loadRows();
    selected.value = failed;
    busy.value = false;

    if (!failed.length) {
        deleteOpen.value = false;
        toast.success('删除成功');
    }
}

const editorOpen = ref(false),
    editorBusy = ref(false),
    editorLoading = ref(false),
    editorError = ref(''),
    editingId = ref(0),
    form = reactive<Record<string, string>>({});
let original: Record<string, string> = {};
let editorToken = 0;
const editKeys = [
    ...soldLimits.map((f) => f.key),
    ...soldCapabilities.map((f) => f.key),
    'region_id',
    'node_group_id',
    'backup_node_group',
    'end_at',
    'month_price',
    'quarter_price',
    'year_price',
    'cname_hostname',
    'cname_domain',
    'cname_mode',
];
async function edit(row: CdnflyRecord) {
    const token = ++editorToken;
    editingId.value = Number(row.id);
    editorOpen.value = true;
    editorLoading.value = true;
    editorError.value = '';
    Object.keys(form).forEach((key) => delete form[key]);

    try {
        const data = recordData(
            await apiRequest(`/api/admin/sold-packages/${row.id}`),
        );

        if (token !== editorToken) {
            return;
        }

        for (const key of editKeys) {
            form[key] = String(data[key] ?? row[key] ?? '');
        }

        original = { ...form };
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
async function saveEdit() {
    editorBusy.value = true;
    editorError.value = '';

    try {
        const payload: Record<string, unknown> = {};

        for (const key of editKeys) {
            if (form[key] === original[key]) {
                continue;
            }

            let value: unknown = form[key];

            if (
                ![
                    'bandwidth',
                    'end_at',
                    'cname_hostname',
                    'cname_mode',
                ].includes(key)
            ) {
                if (value === '' && key === 'backup_node_group') {
                    value = '';
                } else {
                    if (
                        value === '' ||
                        !Number.isFinite(Number(value)) ||
                        Number(value) <
                            (soldLimits.some((f) => f.key === key) ? -1 : 0)
                    ) {
                        throw new Error('请输入有效的配置值');
                    }

                    value = Number(value);
                }
            }

            payload[key] = value;
        }

        if (Object.keys(payload).length) {
            await updateAdminUserPackage(editingId.value, payload);
        }

        editorOpen.value = false;
        toast.success('保存成功');
        await loadRows();
    } catch (e) {
        editorError.value = getErrorMessage(e);
    } finally {
        editorBusy.value = false;
    }
}

const detailOpen = ref(false),
    detailLoading = ref(false),
    detailError = ref(''),
    detailTab = ref('usage'),
    detail = ref<CdnflyRecord>({}),
    usage = ref<CdnflyRecord>({}),
    detailUpgrades = ref<CdnflyRecord[]>([]);
let detailToken = 0;
async function showDetail(row: CdnflyRecord) {
    const token = ++detailToken;
    detailOpen.value = true;
    detailLoading.value = true;
    detailError.value = '';
    detailTab.value = 'usage';
    detail.value = { ...row };
    usage.value = {};
    detailUpgrades.value = [];

    try {
        const [d, u, up] = await Promise.all([
            apiRequest(`/api/admin/sold-packages/${row.id}`),
            apiRequest(`/api/admin/sold-packages/${row.id}/usage`),
            listAdminUserPackageUpgrades(Number(row.id)),
        ]);

        if (token !== detailToken) {
            return;
        }

        detail.value = recordData(d);
        usage.value = recordData(u);
        detailUpgrades.value = extractCdnflyRows(up);
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
const resourceRows = computed(() =>
    resources(detail.value, usage.value, detailUpgrades.value),
);
const resourceColumns = [
    { key: 'label', label: '资源' },
    { key: 'total', label: '总额度', format: valueText },
    { key: 'used', label: '已使用', format: valueText },
    {
        key: 'remaining',
        label: '剩余',
        format: (value: unknown, row: CdnflyRecord) =>
            row.unlimited ? '不限' : valueText(value),
    },
    { key: 'status', label: '状态', badge: true },
];

const upgradeOpen = ref(false),
    upgradeTab = ref('upgrades'),
    upgradeTarget = ref<CdnflyRecord>({}),
    upgrades = ref<CdnflyRecord[]>([]),
    available = ref<CdnflyRecord[]>([]),
    upgradeLoading = ref(false),
    upgradeBusy = ref(false),
    upgradeError = ref(''),
    toPackage = ref(''),
    quote = ref<CdnflyRecord | null>(null),
    quoteLoading = ref(false);
const quantities = reactive<Record<string, string>>({});
let upgradeToken = 0,
    quoteToken = 0;
async function showUpgrades(row: CdnflyRecord) {
    ++quoteToken;
    quoteLoading.value = false;
    upgradeTarget.value = { ...row };
    upgradeTab.value = 'upgrades';
    toPackage.value = '';
    quote.value = null;
    upgradeOpen.value = true;
    await loadUpgrades();
}
async function loadUpgrades() {
    const token = ++upgradeToken;
    upgradeLoading.value = true;
    upgradeError.value = '';
    upgrades.value = [];
    available.value = [];

    try {
        const [owned, choices] = await Promise.all([
            listAdminUserPackageUpgrades(Number(upgradeTarget.value.id)),
            listAdminPackageUps({
                limit: 0,
                user_package: Number(upgradeTarget.value.id),
            }),
        ]);

        if (token !== upgradeToken) {
            return;
        }

        upgrades.value = extractCdnflyRows(owned);
        available.value = extractCdnflyRows(choices).filter(
            (row) =>
                String(row.enable ?? 1) !== '0' &&
                (row.type !== 'waf_protect' ||
                    (String(upgradeTarget.value.waf_protect) !== '1' &&
                        !upgrades.value.some(
                            (up) => up.type === 'waf_protect',
                        ))),
        );
    } catch (e) {
        if (token === upgradeToken) {
            upgradeError.value = getErrorMessage(e);
        }
    } finally {
        if (token === upgradeToken) {
            upgradeLoading.value = false;
        }
    }
}
async function addUpgrade(row: CdnflyRecord) {
    const amount =
        row.type === 'waf_protect'
            ? 1
            : Number(quantities[String(row.id)] ?? 1);

    if (!Number.isSafeInteger(amount) || amount < 1) {
        upgradeError.value = '请输入正整数数量';

        return;
    }

    upgradeBusy.value = true;
    upgradeError.value = '';

    try {
        await apiRequest(
            `/api/admin/sold-packages/${upgradeTarget.value.id}/upgrades`,
            {
                method: 'POST',
                body: JSON.stringify({ package_up: Number(row.id), amount }),
            },
        );
        await loadUpgrades();
        await loadRows();
        toast.success('升级包已添加');
    } catch (e) {
        upgradeError.value = getErrorMessage(e);
    } finally {
        upgradeBusy.value = false;
    }
}
const removeUpgradeOpen = ref(false),
    removeUpgradeId = ref(0);
async function removeUpgrade() {
    upgradeBusy.value = true;
    upgradeError.value = '';

    try {
        await removeAdminUserPackageUpgrade(
            Number(upgradeTarget.value.id),
            removeUpgradeId.value,
        );
        removeUpgradeOpen.value = false;
        await loadUpgrades();
        await loadRows();
    } catch (e) {
        upgradeError.value = getErrorMessage(e);
    } finally {
        upgradeBusy.value = false;
    }
}
async function loadQuote() {
    const token = ++quoteToken;
    quote.value = null;
    upgradeError.value = '';

    if (!toPackage.value) {
        quoteLoading.value = false;

        return;
    }

    quoteLoading.value = true;

    try {
        const data = recordData(
            await apiRequest(
                `/api/admin/sold-packages/${upgradeTarget.value.id}?to_package=${toPackage.value}`,
            ),
        );

        if (
            data.diff_price === undefined ||
            data.diff_price === null ||
            data.diff_price === '' ||
            !Number.isFinite(Number(data.diff_price))
        ) {
            throw new Error('套餐差价数据不完整，请重试');
        }

        if (token === quoteToken) {
            quote.value = data;
        }
    } catch (e) {
        if (token === quoteToken) {
            upgradeError.value = getErrorMessage(e);
        }
    } finally {
        if (token === quoteToken) {
            quoteLoading.value = false;
        }
    }
}
async function changePackage() {
    if (!quote.value) {
        return;
    }

    upgradeBusy.value = true;
    upgradeError.value = '';

    try {
        await updateAdminUserPackage(Number(upgradeTarget.value.id), {
            package: Number(toPackage.value),
        });
        upgradeTarget.value.package = Number(toPackage.value);
        upgradeOpen.value = false;
        await loadRows();
        toast.success('套餐已更换');
    } catch (e) {
        upgradeError.value = getErrorMessage(e);
    } finally {
        upgradeBusy.value = false;
    }
}
</script>

<template>
    <div
        class="console-admin-sold-packages sold-packages-workspace flex flex-1 flex-col p-4 md:p-6"
    >
        <section
            class="console-panel rounded-xl border bg-card p-4 text-card-foreground md:p-5"
        >
            <div class="mb-3 flex flex-wrap gap-2">
                <Button
                    size="sm"
                    :disabled="!selected.length || busy"
                    @click="openAction('sync')"
                    ><RefreshCw />同步数据</Button
                ><Button
                    size="sm"
                    :disabled="!selected.length || busy"
                    @click="bulkUpdate({ enable: 1 })"
                    ><Play />启用</Button
                ><Button
                    size="sm"
                    variant="outline"
                    :disabled="!selected.length || busy"
                    @click="openAction('disable')"
                    ><Pause />禁用</Button
                ><Button
                    size="sm"
                    variant="destructive"
                    :disabled="!selected.length || busy"
                    @click="
                        deleteError = '';
                        deleteOpen = true;
                    "
                    ><Trash2 />删除</Button
                ><Button
                    size="sm"
                    variant="ghost"
                    class="ml-auto"
                    :disabled="loading || busy"
                    @click="loadRows"
                    ><RefreshCw />刷新</Button
                >
            </div>
            <div class="mb-3 flex flex-wrap gap-2">
                <Select v-model="filters.expire" @update:model-value="search"
                    ><SelectTrigger class="w-44" aria-label="到期时间"
                        ><SelectValue /></SelectTrigger
                    ><SelectContent class="console-admin-sold-packages"
                        ><SelectItem value="all">所有到期时间</SelectItem
                        ><SelectItem value="30">一个月内到期</SelectItem
                        ><SelectItem value="7">一周内到期</SelectItem
                        ><SelectItem value="0"
                            >已到期</SelectItem
                        ></SelectContent
                    ></Select
                >
                <Select
                    v-model="filters.base_package"
                    @update:model-value="search"
                    ><SelectTrigger class="w-44" aria-label="基础套餐"
                        ><SelectValue
                            placeholder="所有基础套餐" /></SelectTrigger
                    ><SelectContent class="console-admin-sold-packages"
                        ><SelectItem value="all">所有基础套餐</SelectItem
                        ><SelectItem
                            v-for="row in basePackages"
                            :key="String(row.id)"
                            :value="String(row.id)"
                            >{{ row.name }}</SelectItem
                        ></SelectContent
                    ></Select
                >
                <Select v-model="filters.order_by" @update:model-value="search"
                    ><SelectTrigger class="w-44" aria-label="排序"
                        ><SelectValue /></SelectTrigger
                    ><SelectContent class="console-admin-sold-packages"
                        ><SelectItem value="id">按购买时间排序</SelectItem
                        ><SelectItem value="end_at"
                            >按到期时间排序</SelectItem
                        ></SelectContent
                    ></Select
                >
                <Select v-model="filters.enable" @update:model-value="search"
                    ><SelectTrigger class="w-44" aria-label="状态"
                        ><SelectValue /></SelectTrigger
                    ><SelectContent class="console-admin-sold-packages"
                        ><SelectItem value="all">所有状态</SelectItem
                        ><SelectItem value="1">启用</SelectItem
                        ><SelectItem value="0">禁用</SelectItem></SelectContent
                    ></Select
                >
                <Select
                    v-model="filters.traffic_exceed"
                    @update:model-value="search"
                    ><SelectTrigger class="w-44" aria-label="流量使用情况"
                        ><SelectValue /></SelectTrigger
                    ><SelectContent class="console-admin-sold-packages"
                        ><SelectItem value="all">套餐流量使用情况</SelectItem
                        ><SelectItem value="1">套餐流量用完</SelectItem
                        ><SelectItem value="0"
                            >套餐流量未用完</SelectItem
                        ></SelectContent
                    ></Select
                >
            </div>
            <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="search">
                <label
                    data-slot="console-input-group"
                    v-for="field in [
                        { key: 'uid', label: '用户ID' },
                        { key: 'user_package', label: '用户套餐' },
                        { key: 'cname_hostname', label: '解析值' },
                    ] as const"
                    :key="field.key"
                    class="flex min-w-0 items-center"
                    ><span
                        class="flex h-9 shrink-0 items-center rounded-l-md border border-r-0 bg-muted px-2 text-sm"
                        >{{ field.label }}</span
                    ><Input
                        v-model="filters[field.key]"
                        :aria-label="field.label"
                        :placeholder="`输入${field.label}`"
                        class="w-40 rounded-l-none" /></label
                ><Button type="submit" size="sm" variant="outline">查询</Button
                ><Button type="button" size="sm" variant="link" @click="clear"
                    >清除</Button
                >
            </form>
            <Alert
                v-if="error || optionsError"
                variant="destructive"
                class="mb-4"
                ><AlertDescription
                    >{{ error || optionsError
                    }}<Button
                        variant="link"
                        @click="
                            loadRows();
                            loadOptions();
                        "
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <ConsoleDataTable
                embedded
                selectable
                title="已售套餐"
                :columns="columns"
                :data="{ rows, total, page, pageSize: size, loading }"
                :selected="selected"
                :get-row-key="(row) => Number(row.id)"
                :selection-disabled="busy"
                empty-text="暂无套餐数据"
                @update:selected="selected = $event.map(Number)"
            >
                <template #cell-sold="{ row }"
                    ><Button
                        variant="link"
                        class="h-auto p-0 font-medium"
                        @click="showDetail(row)"
                        >{{ row.name ?? baseName(row) }}</Button
                    >
                    <p
                        data-typography="helper"
                        class="mt-1 text-muted-foreground"
                    >
                        ID: {{ row.id }} / 用户: {{ row.user_name ?? '—' }} ({{
                            row.uid ?? row.user_id
                        }})
                    </p></template
                >
                <template #cell-base="{ row }"
                    ><span class="font-medium">{{ baseName(row) }}</span>
                    <p
                        data-typography="helper"
                        class="mt-1 text-muted-foreground"
                    >
                        基础套餐 ID: {{ baseId(row) }}
                    </p></template
                >
                <template #cell-period="{ row }"
                    ><div class="space-y-1 text-xs text-muted-foreground">
                        <p data-typography="body">
                            购买: {{ formatDate(String(row.create_at ?? '')) }}
                        </p>
                        <p data-typography="body">
                            到期: {{ formatDate(String(row.end_at ?? '')) }}
                        </p>
                    </div></template
                >
                <template #cell-traffic="{ row }"
                    >{{ valueText(row.traffic_usage) }}GB /
                    {{ trafficTotal(row) }}</template
                >
                <template #cell-status="{ row }"
                    ><Badge
                        :variant="
                            String(row.enable) === '0'
                                ? 'destructive'
                                : 'secondary'
                        "
                        >{{ status(row) }}</Badge
                    ></template
                >
                <template #row-actions="{ row }"
                    ><Button size="sm" variant="ghost" @click="showDetail(row)"
                        >详情</Button
                    ><Button size="sm" variant="ghost" @click="edit(row)"
                        >编辑</Button
                    ><Button
                        size="sm"
                        variant="ghost"
                        @click="showUpgrades(row)"
                        >升降配</Button
                    ></template
                > </ConsoleDataTable
            ><PackagePagination
                v-model:page="page"
                v-model:page-size="size"
                :total="total"
                :disabled="loading || busy"
            />
        </section>
        <Dialog :open="actionOpen" @update:open="!busy && (actionOpen = $event)"
            ><DialogScrollContent class="console-admin-sold-packages"
                ><DialogHeader
                    ><DialogTitle>{{
                        actionKind === 'sync' ? '同步数据' : '禁用套餐'
                    }}</DialogTitle
                    ><DialogDescription>{{
                        actionKind === 'sync'
                            ? '将选中套餐的配置同步到相关网站；CNAME模式需单独同步。'
                            : '为选中的套餐填写禁用原因。'
                    }}</DialogDescription></DialogHeader
                ><Alert v-if="actionError" variant="destructive"
                    ><AlertDescription>{{
                        actionError
                    }}</AlertDescription></Alert
                >
                <div v-if="actionKind === 'sync'" class="space-y-3">
                    <label
                        v-for="field in [
                            { key: 'node_group_id', label: '线路分组' },
                            { key: 'cname_domain', label: 'CNAME域名' },
                            { key: 'cname_mode', label: 'CNAME模式' },
                        ]"
                        :key="field.key"
                        class="flex gap-2 text-sm"
                        ><Checkbox
                            :model-value="syncFields.includes(field.key)"
                            @update:model-value="selectSync(field.key, $event)"
                        />{{ field.label }}</label
                    >
                </div>
                <Input
                    v-else
                    v-model="reason"
                    aria-label="禁用原因"
                    placeholder="禁用原因"
                /><DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="busy"
                        @click="actionOpen = false"
                        >关闭</Button
                    ><Button
                        :disabled="
                            busy ||
                            (actionKind === 'sync' && !syncFields.length)
                        "
                        @click="
                            bulkUpdate(
                                actionKind === 'sync'
                                    ? { 'sync-item': syncFields.join(',') }
                                    : { enable: 0, reason },
                            )
                        "
                        >{{ actionKind === 'sync' ? '同步' : '确定' }}</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <ConfirmDeleteDialog
            :open="deleteOpen"
            :description="`确认删除选中的 ${selected.length} 个已售套餐？`"
            :loading="busy"
            :error="deleteError"
            @confirm="removeSelected"
            @cancel="!busy && (deleteOpen = false)"
        />
        <Dialog
            :open="editorOpen"
            @update:open="!editorBusy && (editorOpen = $event)"
            ><DialogScrollContent
                class="console-admin-sold-packages sold-editor my-3 flex max-h-[calc(100dvh-24px)] w-[calc(100%-24px)] max-w-6xl flex-col gap-0 overflow-hidden p-0"
                ><DialogHeader class="shrink-0 border-b p-5"
                    ><DialogTitle>套餐编辑</DialogTitle
                    ><DialogDescription class="sr-only"
                        >配置线路、资源、功能、续费和CNAME。</DialogDescription
                    ></DialogHeader
                >
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                    <Alert v-if="editorError" variant="destructive"
                        ><AlertDescription>{{
                            editorError
                        }}</AlertDescription></Alert
                    >
                    <p
                        data-typography="body"
                        v-if="editorLoading"
                        class="text-muted-foreground"
                    >
                        加载中…
                    </p>
                    <fieldset
                        v-else
                        :disabled="
                            editorBusy ||
                            (!!editorError && !Object.keys(form).length)
                        "
                        class="min-w-0 space-y-4"
                    >
                        <section class="rounded-lg border bg-card p-4">
                            <h3
                                data-typography="section-title"
                                class="section-title"
                            >
                                线路分组
                            </h3>
                            <div class="sold-grid">
                                <div
                                    v-for="field in [
                                        {
                                            key: 'region_id',
                                            label: '区域',
                                            options: options.regions,
                                        },
                                        {
                                            key: 'node_group_id',
                                            label: '线路分组',
                                            options: options.node_groups,
                                        },
                                        {
                                            key: 'backup_node_group',
                                            label: '备用分组',
                                            options: options.node_groups,
                                        },
                                    ]"
                                    :key="field.key"
                                    class="sold-field"
                                >
                                    <Label>{{ field.label }}</Label
                                    ><Select v-model="form[field.key]"
                                        ><SelectTrigger
                                            class="w-full"
                                            :aria-label="field.label"
                                            ><SelectValue
                                                placeholder="请选择" /></SelectTrigger
                                        ><SelectContent
                                            class="console-admin-sold-packages"
                                            ><SelectItem
                                                v-if="
                                                    field.key ===
                                                    'backup_node_group'
                                                "
                                                value="0"
                                                >无</SelectItem
                                            ><SelectItem
                                                v-for="option in field.options"
                                                :key="String(option.id)"
                                                :value="String(option.id)"
                                                >{{ option.name }}</SelectItem
                                            ></SelectContent
                                        ></Select
                                    >
                                </div>
                            </div>
                        </section>
                        <section class="rounded-lg border bg-card p-4">
                            <h3
                                data-typography="section-title"
                                class="section-title"
                            >
                                资源限制
                            </h3>
                            <div class="sold-grid">
                                <div
                                    v-for="field in soldLimits"
                                    :key="field.key"
                                    class="sold-field"
                                >
                                    <Label :for="`sold-${field.key}`">{{
                                        field.label
                                    }}</Label>
                                    <div
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <Switch
                                            :aria-label="`${field.label}限制`"
                                            :checked="form[field.key] !== '-1'"
                                            @update:checked="
                                                form[field.key] = $event
                                                    ? ''
                                                    : '-1'
                                            "
                                        /><span
                                            v-if="form[field.key] === '-1'"
                                            class="text-xs text-muted-foreground"
                                            >不限</span
                                        ><Input
                                            v-else
                                            :id="`sold-${field.key}`"
                                            v-model="form[field.key]"
                                            :placeholder="
                                                field.key === 'bandwidth'
                                                    ? '如100Mbps'
                                                    : ''
                                            "
                                        /><span
                                            v-if="field.unit"
                                            class="text-xs text-muted-foreground"
                                            >{{ field.unit }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section class="rounded-lg border bg-card p-4">
                            <h3
                                data-typography="section-title"
                                class="section-title"
                            >
                                功能能力
                            </h3>
                            <div class="sold-grid">
                                <div
                                    v-for="field in soldCapabilities"
                                    :key="field.key"
                                    class="sold-field"
                                >
                                    <Label :for="`sold-${field.key}`">{{
                                        field.label
                                    }}</Label>
                                    <div class="flex items-center gap-2">
                                        <Switch
                                            :id="`sold-${field.key}`"
                                            :checked="form[field.key] === '1'"
                                            @update:checked="
                                                form[field.key] = $event
                                                    ? '1'
                                                    : '0'
                                            "
                                        /><span
                                            class="text-xs text-muted-foreground"
                                            >{{
                                                form[field.key] === '1'
                                                    ? '允许'
                                                    : '禁止'
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section class="rounded-lg border bg-card p-4">
                            <h3
                                data-typography="section-title"
                                class="section-title"
                            >
                                到期与续费
                            </h3>
                            <div class="sold-grid">
                                <div
                                    v-for="field in [
                                        { key: 'end_at', label: '到期时间' },
                                        { key: 'month_price', label: '月付' },
                                        {
                                            key: 'quarter_price',
                                            label: '季度付',
                                        },
                                        { key: 'year_price', label: '年付' },
                                    ]"
                                    :key="field.key"
                                    class="sold-field"
                                >
                                    <Label :for="`sold-${field.key}`">{{
                                        field.label
                                    }}</Label
                                    ><Input
                                        :id="`sold-${field.key}`"
                                        v-model="form[field.key]"
                                        :placeholder="
                                            field.key === 'end_at'
                                                ? 'YYYY-MM-DD HH:mm:ss'
                                                : 'USDT'
                                        "
                                    />
                                </div>
                            </div>
                        </section>
                        <section class="rounded-lg border bg-card p-4">
                            <h3
                                data-typography="section-title"
                                class="section-title"
                            >
                                CNAME设置
                            </h3>
                            <div class="sold-grid">
                                <div class="sold-field">
                                    <Label for="sold-cname">主机名</Label
                                    ><Input
                                        id="sold-cname"
                                        v-model="form.cname_hostname"
                                    />
                                </div>
                                <div class="sold-field">
                                    <Label>CNAME域名</Label
                                    ><Select v-model="form.cname_domain"
                                        ><SelectTrigger
                                            class="w-full"
                                            aria-label="CNAME域名"
                                            ><SelectValue
                                                placeholder="请选择" /></SelectTrigger
                                        ><SelectContent
                                            class="console-admin-sold-packages"
                                            ><SelectItem
                                                v-for="option in options.cname_domains"
                                                :key="String(option.id)"
                                                :value="String(option.id)"
                                                >{{ option.name }}</SelectItem
                                            ></SelectContent
                                        ></Select
                                    >
                                </div>
                                <div class="sold-field">
                                    <Label>CNAME模式</Label
                                    ><Select v-model="form.cname_mode"
                                        ><SelectTrigger
                                            class="w-full"
                                            aria-label="CNAME模式"
                                            ><SelectValue /></SelectTrigger
                                        ><SelectContent
                                            class="console-admin-sold-packages"
                                            ><SelectItem value="site"
                                                >按网站生成（推荐）</SelectItem
                                            ><SelectItem value="package"
                                                >按套餐生成</SelectItem
                                            ></SelectContent
                                        ></Select
                                    >
                                </div>
                            </div>
                        </section>
                    </fieldset>
                </div>
                <DialogFooter class="shrink-0 border-t bg-background p-4"
                    ><Button
                        :disabled="
                            editorBusy ||
                            editorLoading ||
                            !Object.keys(form).length
                        "
                        @click="saveEdit"
                        >确定</Button
                    ><Button
                        variant="outline"
                        :disabled="editorBusy"
                        @click="editorOpen = false"
                        >关闭</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <Dialog v-model:open="detailOpen"
            ><DialogScrollContent
                class="console-admin-sold-packages my-3 flex max-h-[calc(100dvh-24px)] w-[calc(100%-24px)] max-w-3xl flex-col gap-0 overflow-hidden p-0"
                ><DialogHeader class="shrink-0 border-b p-5"
                    ><DialogTitle>套餐详情</DialogTitle
                    ><DialogDescription class="sr-only"
                        >查看套餐用量和配置信息。</DialogDescription
                    ></DialogHeader
                >
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                    <p data-typography="body" v-if="detailLoading">加载中…</p>
                    <Alert v-else-if="detailError" variant="destructive"
                        ><AlertDescription
                            >{{ detailError
                            }}<Button variant="link" @click="showDetail(detail)"
                                >重试</Button
                            ></AlertDescription
                        ></Alert
                    ><template v-else>
                        <section class="rounded-lg border bg-card p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p
                                        data-typography="helper"
                                        class="text-muted-foreground"
                                    >
                                        当前套餐
                                    </p>
                                    <h2
                                        data-typography="section-title"
                                        class="mt-1 font-semibold"
                                    >
                                        {{ detail.name ?? baseName(detail) }}
                                    </h2>
                                </div>
                                <Badge variant="secondary">{{
                                    status(detail)
                                }}</Badge>
                            </div>
                            <div class="my-4 grid gap-3 sm:grid-cols-3">
                                <div
                                    v-for="item in [
                                        {
                                            label: '总流量',
                                            value: valueText(
                                                resourceRows[0].total,
                                            ),
                                        },
                                        {
                                            label: '到期时间',
                                            value: detail.end_at,
                                        },
                                        {
                                            label: '升级包',
                                            value:
                                                detailUpgrades.length + ' 个',
                                        },
                                    ]"
                                    :key="item.label"
                                    class="rounded-lg border p-3"
                                >
                                    <p
                                        data-typography="helper"
                                        class="text-muted-foreground"
                                    >
                                        {{ item.label }}
                                    </p>
                                    <p
                                        data-typography="body"
                                        class="mt-1 font-semibold"
                                    >
                                        {{ item.value }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    @click="
                                        detailOpen = false;
                                        showUpgrades(detail);
                                        upgradeTab = 'change';
                                    "
                                    ><ShoppingCart />更换套餐</Button
                                ><Button
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        detailOpen = false;
                                        showUpgrades(detail);
                                    "
                                    ><Activity />升级包</Button
                                ><Button
                                    size="sm"
                                    variant="outline"
                                    @click="detailTab = 'usage'"
                                    ><ChartColumn />用量统计</Button
                                >
                            </div>
                        </section>
                        <ConsoleTabs
                            v-model="detailTab"
                            :tabs="[
                                { key: 'usage', label: '使用情况' },
                                { key: 'details', label: '套餐详情' },
                            ]"
                        />
                        <template v-if="detailTab === 'usage'"
                            ><div>
                                <h3
                                    data-typography="section-title"
                                    class="section-title"
                                >
                                    资源用量
                                </h3>
                                <p
                                    data-typography="description"
                                    class="text-muted-foreground"
                                >
                                    展示当前套餐叠加升级包后的额度、已用和剩余。
                                </p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <article
                                    v-for="resource in resourceRows"
                                    :key="resource.key"
                                    class="rounded-lg border bg-card p-4"
                                >
                                    <div class="flex justify-between gap-2">
                                        <h4
                                            data-typography="section-title"
                                            class="font-medium"
                                        >
                                            {{ resource.label }}
                                        </h4>
                                        <Badge
                                            :variant="
                                                resource.status === '超额'
                                                    ? 'destructive'
                                                    : 'secondary'
                                            "
                                            >{{ resource.status }}</Badge
                                        >
                                    </div>
                                    <p data-typography="body" class="my-3">
                                        <strong
                                            data-typography="metric"
                                            class=""
                                            >{{
                                                valueText(resource.used)
                                            }}</strong
                                        ><span
                                            class="text-sm text-muted-foreground"
                                        >
                                            /
                                            {{
                                                valueText(resource.total)
                                            }}</span
                                        >
                                    </p>
                                    <div
                                        class="h-1.5 overflow-hidden rounded-full bg-muted"
                                        role="progressbar"
                                        :aria-label="resource.label"
                                        :aria-valuenow="resource.percent"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                    >
                                        <div
                                            class="h-full bg-primary"
                                            :style="{
                                                width: resource.percent + '%',
                                            }"
                                        />
                                    </div>
                                    <p
                                        data-typography="helper"
                                        class="mt-3 flex justify-between text-muted-foreground"
                                    >
                                        <span
                                            >剩余
                                            {{
                                                resource.unlimited
                                                    ? '不限'
                                                    : valueText(
                                                          resource.remaining,
                                                      )
                                            }}</span
                                        ><span
                                            >{{
                                                resource.percent.toFixed(0)
                                            }}%</span
                                        >
                                    </p>
                                </article>
                            </div>
                            <ConsoleDataTable
                                embedded
                                title="资源用量"
                                :columns="resourceColumns"
                                :data="{
                                    rows: resourceRows,
                                    total: 5,
                                    page: 1,
                                    pageSize: 5,
                                    loading: false,
                                }"
                        /></template>
                        <dl v-else class="grid gap-4 sm:grid-cols-2">
                            <div
                                v-for="field in [
                                    ...soldLimits,
                                    ...soldCapabilities,
                                    { key: 'end_at', label: '到期时间' },
                                    { key: 'cname_hostname', label: '主机名' },
                                    { key: 'cname_mode', label: 'CNAME模式' },
                                ]"
                                :key="field.key"
                                class="rounded-md border p-3"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    {{ field.label }}
                                </dt>
                                <dd class="mt-1 break-all">
                                    {{ valueText(detail[field.key]) }}
                                </dd>
                            </div>
                        </dl>
                    </template>
                </div>
                <DialogFooter class="shrink-0 border-t bg-background p-4"
                    ><Button variant="outline" @click="detailOpen = false"
                        >关闭</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <Dialog
            :open="upgradeOpen"
            @update:open="!upgradeBusy && (upgradeOpen = $event)"
            ><DialogScrollContent
                class="console-admin-sold-packages my-3 max-h-[calc(100dvh-24px)] w-[calc(100%-24px)] max-w-3xl overflow-y-auto"
                ><DialogHeader
                    ><DialogTitle>升降配</DialogTitle
                    ><DialogDescription
                        >管理升级包或更换当前基础套餐。</DialogDescription
                    ></DialogHeader
                >
                <section class="space-y-5 rounded-lg border bg-card p-4">
                    <h3 data-typography="section-title" class="section-title">
                        套餐升级
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            class="rounded-lg border border-l-4 border-l-primary p-3"
                        >
                            <p
                                data-typography="helper"
                                class="text-muted-foreground"
                            >
                                用户套餐ID
                            </p>
                            <strong>#{{ upgradeTarget.id }}</strong>
                        </div>
                        <div
                            class="rounded-lg border border-l-4 border-l-primary p-3"
                        >
                            <p
                                data-typography="helper"
                                class="text-muted-foreground"
                            >
                                当前套餐ID
                            </p>
                            <strong>#{{ baseId(upgradeTarget) }}</strong>
                        </div>
                    </div>
                    <ConsoleTabs
                        v-model="upgradeTab"
                        :tabs="[
                            { key: 'upgrades', label: '升级包' },
                            { key: 'change', label: '更换套餐' },
                        ]"
                    /><Alert v-if="upgradeError" variant="destructive"
                        ><AlertDescription
                            >{{ upgradeError
                            }}<Button
                                variant="link"
                                :disabled="upgradeBusy"
                                @click="
                                    upgradeTab === 'change'
                                        ? loadQuote()
                                        : loadUpgrades()
                                "
                                >重试</Button
                            ></AlertDescription
                        ></Alert
                    >
                    <template v-if="upgradeTab === 'upgrades'"
                        ><p data-typography="body" v-if="upgradeLoading">
                            加载中…
                        </p>
                        <template v-else-if="!upgradeError"
                            ><div class="flex justify-between">
                                <h4
                                    data-typography="section-title"
                                    class="font-semibold"
                                >
                                    当前升级包
                                </h4>
                                <Badge variant="outline"
                                    >已购买 {{ upgrades.length }} 项</Badge
                                >
                            </div>
                            <p
                                data-typography="body"
                                v-if="!upgrades.length"
                                class="rounded-lg border border-dashed py-6 text-center text-muted-foreground"
                            >
                                暂无已购买升级包
                            </p>
                            <article
                                v-for="up in upgrades"
                                :key="String(up.id ?? up.package_up)"
                                class="flex items-center justify-between gap-2 rounded-lg border p-3"
                            >
                                <span
                                    >{{
                                        up.name ?? up.package_up_name ?? up.type
                                    }}
                                    × {{ up.amount ?? 1 }}</span
                                ><Button
                                    size="sm"
                                    variant="ghost"
                                    :disabled="upgradeBusy"
                                    @click="
                                        removeUpgradeId = Number(
                                            up.package_up ?? up.id,
                                        );
                                        removeUpgradeOpen = true;
                                    "
                                    >移除</Button
                                >
                            </article>
                            <div class="flex justify-between border-t pt-5">
                                <h4
                                    data-typography="section-title"
                                    class="font-semibold"
                                >
                                    可购买升级包
                                </h4>
                                <Badge variant="outline"
                                    >可选 {{ available.length }} 项</Badge
                                >
                            </div>
                            <p
                                data-typography="body"
                                v-if="!available.length"
                                class="rounded-lg border border-dashed py-6 text-center text-muted-foreground"
                            >
                                暂无可购买升级包
                            </p>
                            <article
                                v-for="up in available"
                                :key="String(up.id)"
                                class="flex flex-wrap items-center gap-3 rounded-lg border p-3"
                            >
                                <span class="mr-auto"
                                    >{{ up.name }}
                                    <span class="text-xs text-muted-foreground"
                                        >{{
                                            formatMoney(up.month_price)
                                        }}/月</span
                                    ></span
                                ><Input
                                    v-if="up.type !== 'waf_protect'"
                                    v-model="quantities[String(up.id)]"
                                    type="number"
                                    min="1"
                                    placeholder="1"
                                    :aria-label="`数量 ${up.id}`"
                                    class="w-20"
                                /><Button
                                    size="sm"
                                    :disabled="upgradeBusy"
                                    @click="addUpgrade(up)"
                                    >添加</Button
                                >
                            </article></template
                        ></template
                    >
                    <div v-else class="space-y-4">
                        <Label>选择套餐</Label
                        ><Select
                            v-model="toPackage"
                            :disabled="upgradeBusy"
                            @update:model-value="loadQuote"
                            ><SelectTrigger class="w-full" aria-label="更换套餐"
                                ><SelectValue
                                    placeholder="请选择基础套餐" /></SelectTrigger
                            ><SelectContent class="console-admin-sold-packages"
                                ><SelectItem
                                    v-for="row in basePackages.filter(
                                        (p) =>
                                            String(p.id) !==
                                            String(baseId(upgradeTarget)),
                                    )"
                                    :key="String(row.id)"
                                    :value="String(row.id)"
                                    >{{ row.name }}</SelectItem
                                ></SelectContent
                            ></Select
                        >
                        <p data-typography="body" v-if="quoteLoading">
                            计算差价中…
                        </p>
                        <div
                            v-if="quote"
                            class="rounded-lg border bg-muted/30 p-3 text-sm"
                        >
                            <p data-typography="body">
                                当前价格 {{ valueText(quote.curr_price) }} /
                                新价格 {{ valueText(quote.new_price) }}
                            </p>
                            <p data-typography="body">
                                剩余 {{ valueText(quote.remain_days) }} 天
                            </p>
                            <p data-typography="body">
                                {{
                                    Number(quote.diff_price) >= 0
                                        ? '需补差价'
                                        : '退回差价'
                                }}:
                                {{
                                    formatMoney(
                                        Math.abs(Number(quote.diff_price)),
                                    )
                                }}
                            </p>
                        </div>
                        <Button
                            :disabled="!quote || quoteLoading || upgradeBusy"
                            @click="changePackage"
                            >确认更换</Button
                        >
                    </div>
                </section></DialogScrollContent
            ></Dialog
        >
        <ConfirmDeleteDialog
            :open="removeUpgradeOpen"
            description="确认移除此升级包？"
            :loading="upgradeBusy"
            :error="upgradeError"
            @confirm="removeUpgrade"
            @cancel="!upgradeBusy && (removeUpgradeOpen = false)"
        />
    </div>
</template>
