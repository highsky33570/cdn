<script setup lang="ts">
import {
    AlertCircle,
    CircleCheck,
    CreditCard,
    Eye,
    Package,
    Pencil,
    Plus,
    Receipt,
    Save,
    Trash2,
    TriangleAlert,
    Wallet,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsoleFormDialog from '@/components/console/ConsoleFormDialog.vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import type { ConsoleTab } from '@/components/console/ConsoleTabs.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { getAdminFinanceSummary } from '@/lib/adminModulesApi';
import {
    listAdminOrders,
    listAdminServices,
    listAdminCdnflyUserPackages,
    updateAdminOrderStatus,
    createAdminUserPackage,
    updateAdminUserPackage,
    deleteAdminUserPackage,
    listAdminUserPackageUpgrades,
    addAdminUserPackageUpgrade,
    removeAdminUserPackageUpgrade,
    listAdminPackageUps,
} from '@/lib/adminModulesApi';
import type { AdminFinanceSummary } from '@/lib/adminModulesApi';
import type {
    AdminOrderRecord,
    AdminServiceRecord,
    CdnflyRecord,
} from '@/lib/adminModulesApi';
import { getErrorMessage } from '@/lib/formatters';
import { formatDate, formatMoney } from '@/lib/formatters';

/**
 * The page used to open onto three stacked tables, each with its own toolbar
 * and its own 暂无记录 block, and answered nothing an operator arrives with.
 *
 * Now: the numbers first, then ONE panel with the three lists behind tabs, so
 * there is a single toolbar and a single empty state at a time.
 */
const summary = ref<AdminFinanceSummary | null>(null);
const summaryError = ref('');

async function loadSummary(): Promise<void> {
    summaryError.value = '';

    try {
        summary.value = await getAdminFinanceSummary();
    } catch (error) {
        summaryError.value = getErrorMessage(error);
    }
}

onMounted(loadSummary);

const money = (value: number | undefined): string =>
    value === undefined ? '—' : `${Number(value).toFixed(2)}`;

/**
 * Paid-but-undelivered is the only figure here that demands action today, so it
 * is styled as a warning rather than sitting quietly among the totals.
 */
const summaryCards = computed(() => [
    {
        key: 'revenue_month',
        label: '本月收入',
        value: money(summary.value?.revenue_month),
        hint: `累计 ${money(summary.value?.revenue_total)}`,
        icon: Wallet,
        tone: 'default' as const,
    },
    {
        key: 'orders_pending',
        label: '待支付订单',
        value: String(summary.value?.orders_pending ?? '—'),
        hint: `共 ${summary.value?.orders_total ?? '—'} 笔订单`,
        icon: Receipt,
        tone: 'default' as const,
    },
    {
        key: 'services_active',
        label: '运行中的服务',
        value: String(summary.value?.services_active ?? '—'),
        hint: `共 ${summary.value?.services_total ?? '—'} 个实例`,
        icon: CircleCheck,
        tone: 'default' as const,
    },
    {
        key: 'orders_failed',
        label: '开通失败',
        value: String(summary.value?.orders_failed ?? '—'),
        hint:
            (summary.value?.orders_failed ?? 0) > 0
                ? '已收款但未交付，需处理'
                : '无需处理',
        icon: TriangleAlert,
        tone:
            (summary.value?.orders_failed ?? 0) > 0
                ? ('warning' as const)
                : ('default' as const),
    },
]);

type FinanceTab = 'orders' | 'services' | 'packages';

const activeTab = ref<FinanceTab>('orders');

const financeTabs: ConsoleTab[] = [
    { key: 'orders' as const, label: '订单', icon: Receipt },
    { key: 'services' as const, label: '服务实例', icon: Package },
    { key: 'packages' as const, label: 'CDNfly 用户套餐', icon: Zap },
];

const STATUS_ALL = 'all';

// ─── Order columns ──────────────────────────────────────
const orderColumns: ColumnDef[] = [
    { key: 'order_no', label: '订单号', width: '160px' },
    { key: 'user_name', label: '用户' },
    {
        key: 'amount_usdt',
        label: '金额',
        width: '120px',
        format: (v) => `${formatMoney(v as string | number | null, 'USDT')}`,
    },
    {
        key: 'status',
        label: '状态',
        width: '100px',
        badge: true,
        badgeVariant: (v) => {
            if (v === 'paid' || v === 'provisioned') {
                return 'secondary';
            }

            if (v === 'failed' || v === 'expired') {
                return 'destructive';
            }

            return 'outline';
        },
    },
    {
        key: 'gateway_status',
        label: '网关状态',
        width: '100px',
        format: (v) => String(v ?? '-'),
    },
    {
        key: 'created_at',
        label: '创建时间',
        width: '140px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];

// ─── Service columns ────────────────────────────────────
const serviceColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'user_name', label: '用户' },
    {
        key: 'order_no',
        label: '订单号',
        width: '160px',
        format: (v) => String(v ?? '-'),
    },
    { key: 'service_name', label: '服务名称', format: (v) => String(v ?? '-') },
    {
        key: 'status',
        label: '状态',
        width: '100px',
        badge: true,
        badgeVariant: (v) => {
            if (v === 'active') {
                return 'secondary';
            }

            if (v === 'failed') {
                return 'destructive';
            }

            return 'outline';
        },
    },
    {
        key: 'created_at',
        label: '创建时间',
        width: '140px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];

// ─── CDNfly user package columns ────────────────────────
const userPackageColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'user_id', label: '用户 ID', width: '90px' },
    { key: 'package_id', label: '套餐 ID', width: '90px' },
    {
        key: 'status',
        label: '状态',
        badge: true,
        width: '100px',
        format: (v) => String(v ?? '-'),
    },
    {
        key: 'expire_time',
        label: '到期时间',
        width: '160px',
        format: (v) => formatDate(v as string | null | undefined),
    },
    {
        key: 'created_at',
        label: '创建时间',
        width: '160px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];

const ORDER_STATUSES = [
    { value: 'pending', label: '待支付' },
    { value: 'paid', label: '已支付' },
    { value: 'provisioning', label: '开通中' },
    { value: 'active', label: '已开通' },
    { value: 'failed', label: '失败' },
    { value: 'expired', label: '已过期' },
    { value: 'cancelled', label: '已取消' },
];

const ordersTableRef = ref<InstanceType<typeof ConsoleDataTable> | null>(null);

// ─── Order status update ───────────────────────────────
const statusDialogOpen = ref(false);
const statusSaving = ref(false);
const statusError = ref('');
const statusTarget = ref<AdminOrderRecord | null>(null);
const statusForm = reactive({ status: '' });

function openStatusDialog(row: Record<string, unknown>): void {
    const order = row as unknown as AdminOrderRecord;
    statusTarget.value = order;
    statusForm.status = order.status;
    statusError.value = '';
    statusDialogOpen.value = true;
}

async function submitStatusUpdate(): Promise<void> {
    if (!statusTarget.value) {
        return;
    }

    statusSaving.value = true;
    statusError.value = '';

    try {
        await updateAdminOrderStatus(statusTarget.value.id, statusForm.status);
        toast.success('订单状态已更新');
        statusDialogOpen.value = false;
        ordersTableRef.value?.refresh();
    } catch (error) {
        statusError.value =
            typeof error === 'object' && error !== null && 'message' in error
                ? String((error as { message: string }).message)
                : '更新失败';
    } finally {
        statusSaving.value = false;
    }
}

// ─── User Package CRUD ──────────────────────────────────
const upTableRef = ref<InstanceType<typeof ConsoleDataTable> | null>(null);
const upDialogOpen = ref(false);
const upSaving = ref(false);
const upError = ref('');
const upEditing = ref<CdnflyRecord | null>(null);
const upForm = reactive({ uid: '', package: '', duration: 'month', name: '' });
const upDeleteOpen = ref(false);
const upDeleteTarget = ref<CdnflyRecord | null>(null);
const upDeleting = ref(false);
const upDeleteError = ref('');

const upDialogTitle = computed(() =>
    upEditing.value ? '编辑用户套餐' : '新增用户套餐',
);

const DURATION_OPTIONS = [
    { value: 'month', label: '月付' },
    { value: 'quarter', label: '季付' },
    { value: 'year', label: '年付' },
];

function openUpCreate(): void {
    upEditing.value = null;
    upForm.uid = '';
    upForm.package = '';
    upForm.duration = 'month';
    upForm.name = '';
    upError.value = '';
    upDialogOpen.value = true;
}

function openUpEdit(row: Record<string, unknown>): void {
    const r = row as CdnflyRecord;
    upEditing.value = r;
    upForm.uid = String(r.user_id ?? r.uid ?? '');
    upForm.package = String(r.package_id ?? r.package ?? '');
    upForm.duration = String(r.duration ?? 'month');
    upForm.name = String(r.name ?? '');
    upError.value = '';
    upDialogOpen.value = true;
}

async function submitUp(): Promise<void> {
    upSaving.value = true;
    upError.value = '';

    try {
        if (upEditing.value) {
            await updateAdminUserPackage(Number(upEditing.value.id), {
                name: upForm.name.trim() || undefined,
                duration: upForm.duration,
            });
            toast.success('用户套餐已更新');
        } else {
            await createAdminUserPackage({
                uid: Number(upForm.uid),
                package: Number(upForm.package),
                duration: upForm.duration,
                name: upForm.name.trim() || undefined,
            });
            toast.success('用户套餐已创建');
        }

        upDialogOpen.value = false;
        upTableRef.value?.refresh();
    } catch (error) {
        upError.value = getErrorMessage(error);
    } finally {
        upSaving.value = false;
    }
}

function openUpDelete(row: Record<string, unknown>): void {
    upDeleteTarget.value = row as CdnflyRecord;
    upDeleteError.value = '';
    upDeleteOpen.value = true;
}

async function confirmUpDelete(): Promise<void> {
    if (!upDeleteTarget.value) {
        return;
    }

    upDeleting.value = true;
    upDeleteError.value = '';

    try {
        await deleteAdminUserPackage(Number(upDeleteTarget.value.id));
        upDeleteOpen.value = false;
        toast.success('用户套餐已删除');
        upTableRef.value?.refresh();
    } catch (error) {
        upDeleteError.value = getErrorMessage(error);
    } finally {
        upDeleting.value = false;
    }
}

// ─── Upgrade Management ─────────────────────────────────
const upgradeDialogOpen = ref(false);
const upgradeTarget = ref<CdnflyRecord | null>(null);
const upgrades = ref<CdnflyRecord[]>([]);
const upgradesLoading = ref(false);
const upgradeAdding = ref(false);
const upgradeRemovingId = ref<number | null>(null);
const upgradeError = ref('');
const upgradeAddForm = reactive({ package_up_id: '' });
const packageUpOptions = ref<CdnflyRecord[]>([]);

async function openUpgradeDialog(row: Record<string, unknown>): Promise<void> {
    upgradeTarget.value = row as CdnflyRecord;
    upgradeError.value = '';
    upgradeAddForm.package_up_id = '';
    upgradeDialogOpen.value = true;
    await Promise.all([loadUpgrades(), loadPackageUpOptions()]);
}

async function loadUpgrades(): Promise<void> {
    if (!upgradeTarget.value) {
        return;
    }

    upgradesLoading.value = true;

    try {
        const data = await listAdminUserPackageUpgrades(
            Number(upgradeTarget.value.id),
        );
        const raw = (data as unknown as { data?: unknown })?.data;
        upgrades.value = Array.isArray(raw) ? raw : [];
    } catch (error) {
        upgradeError.value = getErrorMessage(error);
        upgrades.value = [];
    } finally {
        upgradesLoading.value = false;
    }
}

async function loadPackageUpOptions(): Promise<void> {
    try {
        const data = await listAdminPackageUps({ page: 1, limit: 200 });
        const raw = (data as unknown as { data?: unknown })?.data;
        packageUpOptions.value = Array.isArray(raw) ? raw : [];
    } catch {
        packageUpOptions.value = [];
    }
}

async function submitAddUpgrade(): Promise<void> {
    if (!upgradeTarget.value || !upgradeAddForm.package_up_id) {
        return;
    }

    upgradeAdding.value = true;
    upgradeError.value = '';

    try {
        await addAdminUserPackageUpgrade(
            Number(upgradeTarget.value.id),
            Number(upgradeAddForm.package_up_id),
        );
        toast.success('升级包已添加');
        upgradeAddForm.package_up_id = '';
        await loadUpgrades();
    } catch (error) {
        upgradeError.value = getErrorMessage(error);
    } finally {
        upgradeAdding.value = false;
    }
}

async function removeUpgrade(upgradeId: number): Promise<void> {
    if (!upgradeTarget.value) {
        return;
    }

    upgradeRemovingId.value = upgradeId;
    upgradeError.value = '';

    try {
        await removeAdminUserPackageUpgrade(
            Number(upgradeTarget.value.id),
            upgradeId,
        );
        toast.success('升级包已移除');
        await loadUpgrades();
    } catch (error) {
        upgradeError.value = getErrorMessage(error);
    } finally {
        upgradeRemovingId.value = null;
    }
}

// ─── Filters ────────────────────────────────────────────
const orderFilters = reactive({ search: '', status: STATUS_ALL });
const serviceFilters = reactive({ search: '', status: STATUS_ALL });

const orderSearchParams = computed(() => {
    const p: Record<string, string | number> = {};
    const s = orderFilters.search.trim();

    if (s) {
        p.search = s;
    }

    if (orderFilters.status !== STATUS_ALL) {
        p.status = orderFilters.status;
    }

    return p;
});

const serviceSearchParams = computed(() => {
    const p: Record<string, string | number> = {};
    const s = serviceFilters.search.trim();

    if (s) {
        p.search = s;
    }

    if (serviceFilters.status !== STATUS_ALL) {
        p.status = serviceFilters.status;
    }

    return p;
});

// ─── Order detail ───────────────────────────────────────
const orderDetailOpen = ref(false);
const orderDetail = ref<AdminOrderRecord | null>(null);

function openOrderDetail(row: Record<string, unknown>): void {
    orderDetail.value = row as unknown as AdminOrderRecord;
    orderDetailOpen.value = true;
}

const orderDetailFields = computed<{ label: string; value: string }[]>(() => {
    const o = orderDetail.value;

    if (!o) {
        return [];
    }

    return [
        { label: '订单号', value: o.order_no || `#${o.id}` },
        {
            label: '用户',
            value: o.user_name
                ? `${o.user_name} (${o.user_email})`
                : String(o.user_id ?? '-'),
        },
        { label: '产品', value: o.product_name ?? '-' },
        { label: '类型', value: o.order_type ?? '-' },
        { label: '计费周期', value: o.billing_cycle ?? '-' },
        { label: '数量', value: String(o.quantity ?? '-') },
        { label: '金额 (USDT)', value: `${o.amount_usdt} USDT` },
        { label: '金额 (CNY)', value: o.amount_cny ? `¥${o.amount_cny}` : '-' },
        { label: '状态', value: o.status },
        { label: '网关状态', value: o.gateway_status ?? '-' },
        { label: 'EPUSDT 订单 ID', value: o.epusdt_order_id ?? '-' },
        { label: 'EPUSDT 交易 ID', value: o.epusdt_trade_id ?? '-' },
        { label: '创建时间', value: formatDate(o.created_at) },
        { label: '支付时间', value: formatDate(o.paid_at) },
        { label: '开通时间', value: formatDate(o.provisioned_at) },
        { label: '过期时间', value: formatDate(o.expire_at) },
    ];
});

// ─── Service detail ─────────────────────────────────────
const serviceDetailOpen = ref(false);
const serviceDetail = ref<AdminServiceRecord | null>(null);

function openServiceDetail(row: Record<string, unknown>): void {
    serviceDetail.value = row as unknown as AdminServiceRecord;
    serviceDetailOpen.value = true;
}

const serviceDetailFields = computed<{ label: string; value: string }[]>(() => {
    const s = serviceDetail.value;

    if (!s) {
        return [];
    }

    return [
        { label: 'ID', value: String(s.id) },
        {
            label: '用户',
            value: s.user_name
                ? `${s.user_name} (${s.user_email})`
                : String(s.user_id ?? '-'),
        },
        { label: '订单号', value: s.order_no ?? '-' },
        { label: '产品', value: s.product_name ?? '-' },
        { label: '服务名称', value: s.service_name ?? '-' },
        { label: 'CDNfly 用户 ID', value: String(s.cdnfly_user_id ?? '-') },
        { label: 'CDNfly 服务 ID', value: String(s.cdnfly_service_id ?? '-') },
        { label: '状态', value: s.status },
        { label: '错误信息', value: s.error_message ?? '-' },
        { label: '开通时间', value: formatDate(s.opened_at) },
        { label: '过期时间', value: formatDate(s.expired_at) },
        { label: '创建时间', value: formatDate(s.created_at) },
    ];
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="财务管理"
            :icon="CreditCard"
            :show-api-badge="false"
        />

        <!--
            The numbers first. Three empty tables told an operator nothing about
            whether the business was working.
        -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="card in summaryCards"
                :key="card.key"
                class="rounded-xl border p-4"
                :class="
                    card.tone === 'warning'
                        ? 'border-destructive/40 bg-destructive/5'
                        : 'bg-card'
                "
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs text-muted-foreground">
                        {{ card.label }}
                    </span>
                    <component
                        :is="card.icon"
                        class="size-4"
                        :class="
                            card.tone === 'warning'
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        "
                    />
                </div>
                <div
                    class="mt-2 text-2xl font-semibold"
                    :class="card.tone === 'warning' ? 'text-destructive' : ''"
                >
                    {{ card.value }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    {{ card.hint }}
                </div>
            </div>
        </div>

        <Alert v-if="summaryError" variant="destructive">
            <AlertCircle />
            <AlertTitle>统计加载失败</AlertTitle>
            <AlertDescription>{{ summaryError }}</AlertDescription>
        </Alert>

        <!--
            One panel, three lists. Previously each had its own search box,
            page-size select and refresh button stacked down the page.
        -->
        <ConsoleTabs v-model="activeTab" :tabs="financeTabs" />

        <ConsoleDataTable
            v-if="activeTab === 'orders'"
            ref="ordersTableRef"
            title="订单列表"
            :icon="Receipt"
            :columns="orderColumns"
            :fetch-fn="listAdminOrders"
            :search-params="orderSearchParams"
            search-placeholder="搜索订单号"
            @row-click="openOrderDetail"
        >
            <template #search-fields="{ submitSearch }">
                <div class="grid gap-3 sm:grid-cols-2">
                    <Input
                        v-model="orderFilters.search"
                        placeholder="搜索订单号"
                        @keyup.enter="submitSearch"
                    />
                    <Select v-model="orderFilters.status">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="全部状态" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="STATUS_ALL"
                                    >全部状态</SelectItem
                                >
                                <SelectItem value="pending">待支付</SelectItem>
                                <SelectItem value="paid">已支付</SelectItem>
                                <SelectItem value="provisioned"
                                    >已开通</SelectItem
                                >
                                <SelectItem value="failed">失败</SelectItem>
                                <SelectItem value="expired">已过期</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
            </template>

            <template #cell-user_name="{ row }">
                <div>{{ row.user_name ?? '-' }}</div>
                <div
                    v-if="row.user_email"
                    class="text-xs text-muted-foreground"
                >
                    {{ row.user_email }}
                </div>
            </template>

            <template #row-actions="{ row }">
                <Button variant="ghost" size="sm" @click="openOrderDetail(row)">
                    <Eye class="size-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="openStatusDialog(row)"
                >
                    <Pencil class="size-4" />
                </Button>
            </template>
        </ConsoleDataTable>

        <ConsoleDataTable
            v-else-if="activeTab === 'services'"
            title="服务实例"
            :icon="Package"
            :columns="serviceColumns"
            :fetch-fn="listAdminServices"
            :search-params="serviceSearchParams"
            search-placeholder="搜索服务名称 / 订单号"
            @row-click="openServiceDetail"
        >
            <template #search-fields="{ submitSearch }">
                <div class="grid gap-3 sm:grid-cols-2">
                    <Input
                        v-model="serviceFilters.search"
                        placeholder="搜索服务名称 / 订单号"
                        @keyup.enter="submitSearch"
                    />
                    <Select v-model="serviceFilters.status">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="全部状态" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="STATUS_ALL"
                                    >全部状态</SelectItem
                                >
                                <SelectItem value="pending">待开通</SelectItem>
                                <SelectItem value="active">运行中</SelectItem>
                                <SelectItem value="failed">失败</SelectItem>
                                <SelectItem value="expired">已过期</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
            </template>

            <template #cell-user_name="{ row }">
                <div>{{ row.user_name ?? row.user_id ?? '-' }}</div>
                <div
                    v-if="row.user_email"
                    class="text-xs text-muted-foreground"
                >
                    {{ row.user_email }}
                </div>
            </template>

            <template #row-actions="{ row }">
                <Button
                    variant="ghost"
                    size="sm"
                    @click="openServiceDetail(row)"
                >
                    <Eye class="size-4" />
                </Button>
            </template>
        </ConsoleDataTable>

        <ConsoleDataTable
            v-else
            ref="upTableRef"
            title="CDNfly 用户套餐"
            :icon="Package"
            :columns="userPackageColumns"
            :fetch-fn="listAdminCdnflyUserPackages"
            search-placeholder="搜索用户套餐"
        >
            <template #toolbar>
                <Button variant="default" size="sm" @click="openUpCreate">
                    <Plus data-icon="inline-start" class="size-4" />
                    新增
                </Button>
            </template>
            <template #row-actions="{ row }">
                <Button variant="ghost" size="sm" @click="openUpEdit(row)">
                    <Pencil class="size-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="openUpgradeDialog(row)"
                >
                    <Zap class="size-4" />
                </Button>
                <Button variant="ghost" size="sm" @click="openUpDelete(row)">
                    <Trash2 class="size-4 text-destructive" />
                </Button>
            </template>
        </ConsoleDataTable>

        <!-- Order detail dialog -->
        <ConsoleFormDialog
            v-model:open="orderDetailOpen"
            title="订单详情"
            :loading="false"
        >
            <template #footer>
                <Button variant="outline" @click="orderDetailOpen = false">
                    关闭
                </Button>
            </template>

            <div v-if="orderDetail" class="grid gap-3">
                <div
                    v-for="field in orderDetailFields"
                    :key="field.label"
                    class="grid grid-cols-[140px_1fr] gap-2"
                >
                    <Label class="text-muted-foreground">{{
                        field.label
                    }}</Label>
                    <Badge
                        v-if="field.label === '状态'"
                        :variant="
                            orderDetail.status === 'paid' ||
                            orderDetail.status === 'provisioned'
                                ? 'secondary'
                                : orderDetail.status === 'failed' ||
                                    orderDetail.status === 'expired'
                                  ? 'destructive'
                                  : 'outline'
                        "
                        class="w-fit"
                    >
                        {{ field.value }}
                    </Badge>
                    <span v-else class="break-all">{{ field.value }}</span>
                </div>
            </div>
        </ConsoleFormDialog>

        <!-- Service detail dialog -->
        <ConsoleFormDialog
            v-model:open="serviceDetailOpen"
            title="服务实例详情"
            :loading="false"
        >
            <template #footer>
                <Button variant="outline" @click="serviceDetailOpen = false">
                    关闭
                </Button>
            </template>

            <div v-if="serviceDetail" class="grid gap-3">
                <div
                    v-for="field in serviceDetailFields"
                    :key="field.label"
                    class="grid grid-cols-[140px_1fr] gap-2"
                >
                    <Label class="text-muted-foreground">{{
                        field.label
                    }}</Label>
                    <Badge
                        v-if="field.label === '状态'"
                        :variant="
                            serviceDetail.status === 'active'
                                ? 'secondary'
                                : serviceDetail.status === 'failed'
                                  ? 'destructive'
                                  : 'outline'
                        "
                        class="w-fit"
                    >
                        {{ field.value }}
                    </Badge>
                    <span
                        v-else
                        class="break-all"
                        :class="{
                            'text-destructive':
                                field.label === '错误信息' &&
                                field.value !== '-',
                        }"
                    >
                        {{ field.value }}
                    </span>
                </div>
            </div>
        </ConsoleFormDialog>

        <!-- User package create/edit dialog -->
        <Dialog v-model:open="upDialogOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ upDialogTitle }}</DialogTitle>
                    <DialogDescription
                        >管理 CDNfly 用户套餐。</DialogDescription
                    >
                </DialogHeader>

                <Alert v-if="upError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>提交失败</AlertTitle>
                    <AlertDescription>{{ upError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4">
                    <div v-if="!upEditing" class="grid gap-2">
                        <Label for="up-uid">CDNfly 用户 ID</Label>
                        <Input
                            id="up-uid"
                            v-model="upForm.uid"
                            placeholder="CDNfly uid"
                            inputmode="numeric"
                        />
                    </div>
                    <div v-if="!upEditing" class="grid gap-2">
                        <Label for="up-package">套餐 ID</Label>
                        <Input
                            id="up-package"
                            v-model="upForm.package"
                            placeholder="CDNfly package id"
                            inputmode="numeric"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>时长</Label>
                        <Select v-model="upForm.duration">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="d in DURATION_OPTIONS"
                                        :key="d.value"
                                        :value="d.value"
                                    >
                                        {{ d.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="up-name">名称（可选）</Label>
                        <Input
                            id="up-name"
                            v-model="upForm.name"
                            placeholder="自定义名称"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="upDialogOpen = false"
                        >取消</Button
                    >
                    <Button :disabled="upSaving" @click="submitUp">
                        <Spinner v-if="upSaving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        保存
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- User package delete confirm -->
        <Dialog v-model:open="upDeleteOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>确认删除</DialogTitle>
                    <DialogDescription>
                        确定要删除用户套餐 #{{ upDeleteTarget?.id }}？
                    </DialogDescription>
                </DialogHeader>
                <Alert v-if="upDeleteError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>删除失败</AlertTitle>
                    <AlertDescription>{{ upDeleteError }}</AlertDescription>
                </Alert>
                <DialogFooter>
                    <Button variant="outline" @click="upDeleteOpen = false"
                        >取消</Button
                    >
                    <Button
                        variant="destructive"
                        :disabled="upDeleting"
                        @click="confirmUpDelete"
                    >
                        <Spinner v-if="upDeleting" data-icon="inline-start" />
                        <Trash2 v-else data-icon="inline-start" />
                        确认删除
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- Upgrade management dialog -->
        <Dialog v-model:open="upgradeDialogOpen">
            <DialogScrollContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>升级包管理</DialogTitle>
                    <DialogDescription>
                        用户套餐 #{{ upgradeTarget?.id }} 的升级包。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="upgradeError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>操作失败</AlertTitle>
                    <AlertDescription>{{ upgradeError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4">
                    <div class="flex items-end gap-2">
                        <div class="grid flex-1 gap-2">
                            <Label>添加升级包</Label>
                            <Select v-model="upgradeAddForm.package_up_id">
                                <SelectTrigger
                                    ><SelectValue placeholder="选择升级包"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem
                                            v-for="opt in packageUpOptions"
                                            :key="String(opt.id)"
                                            :value="String(opt.id)"
                                        >
                                            {{ opt.name ?? `#${opt.id}` }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            size="sm"
                            :disabled="
                                upgradeAdding || !upgradeAddForm.package_up_id
                            "
                            @click="submitAddUpgrade"
                        >
                            <Spinner
                                v-if="upgradeAdding"
                                data-icon="inline-start"
                            />
                            <Plus
                                v-else
                                data-icon="inline-start"
                                class="size-4"
                            />
                            添加
                        </Button>
                    </div>

                    <div class="rounded-md border">
                        <div
                            v-if="upgradesLoading"
                            class="flex items-center justify-center gap-2 p-6 text-muted-foreground"
                        >
                            <Spinner />
                            加载中
                        </div>
                        <div
                            v-else-if="upgrades.length === 0"
                            class="p-6 text-center text-muted-foreground"
                        >
                            暂无升级包
                        </div>
                        <div v-else class="divide-y">
                            <div
                                v-for="up in upgrades"
                                :key="String(up.id)"
                                class="flex items-center justify-between px-4 py-3"
                            >
                                <div>
                                    <div class="text-sm font-medium">
                                        {{ up.name ?? `升级包 #${up.id}` }}
                                    </div>
                                    <div
                                        v-if="up.package_up_id"
                                        class="text-xs text-muted-foreground"
                                    >
                                        升级包 ID: {{ up.package_up_id }}
                                    </div>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :disabled="
                                        upgradeRemovingId === Number(up.id)
                                    "
                                    @click="removeUpgrade(Number(up.id))"
                                >
                                    <Spinner
                                        v-if="
                                            upgradeRemovingId === Number(up.id)
                                        "
                                        class="size-4"
                                    />
                                    <Trash2
                                        v-else
                                        class="size-4 text-destructive"
                                    />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="upgradeDialogOpen = false"
                        >关闭</Button
                    >
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- Order status update dialog -->
        <Dialog v-model:open="statusDialogOpen">
            <DialogScrollContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>修改订单状态</DialogTitle>
                    <DialogDescription>
                        订单：{{ statusTarget?.order_no ?? '' }}
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="statusError" variant="destructive">
                    <AlertTitle>更新失败</AlertTitle>
                    <AlertDescription>{{ statusError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label>状态</Label>
                        <Select v-model="statusForm.status">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="s in ORDER_STATUSES"
                                        :key="s.value"
                                        :value="s.value"
                                    >
                                        {{ s.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="statusDialogOpen = false"
                        >取消</Button
                    >
                    <Button
                        :disabled="statusSaving"
                        @click="submitStatusUpdate"
                    >
                        <Spinner v-if="statusSaving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        保存
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
