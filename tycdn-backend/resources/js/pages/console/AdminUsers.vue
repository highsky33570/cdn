<script setup lang="ts">
import {
    AlertCircle,
    KeyRound,
    PackageCheck,
    Pencil,
    Plus,
    RefreshCw,
    ReceiptText,
    Save,
    Search,
    ShieldCheck,
    Trash2,
    UserRound,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner'
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    createAdminUser,
    deleteAdminUser,
    listAdminUsers,
    rechargeAdminUser,
    syncAdminUserApiKey,
    updateAdminUser,
} from '@/lib/adminConsoleApi';
import type {
    AdminRole,
    AdminUserRecord,
    Paginated,
} from '@/lib/adminConsoleApi';
import { listAdminOrders, listAdminServices } from '@/lib/adminModulesApi';
import type {
    AdminOrderRecord,
    AdminServiceRecord,
} from '@/lib/adminModulesApi';

const ROLE_ALL = 'all';

const loading = ref(false);
const saving = ref(false);
const syncingId = ref<number | null>(null);
const recharging = ref(false);
const errorMessage = ref('');
const formError = ref('');
const createDialogOpen = ref(false);
const creating = ref(false);
const editDialogOpen = ref(false);
const rechargeDialogOpen = ref(false);
const detailDialogOpen = ref(false);
const deleteConfirmOpen = ref(false);
const deleting = ref(false);
const editingUser = ref<AdminUserRecord | null>(null);
const rechargeUser = ref<AdminUserRecord | null>(null);
const detailUser = ref<AdminUserRecord | null>(null);
const deleteTargetUser = ref<AdminUserRecord | null>(null);
const page = ref(1);
const users = ref<Paginated<AdminUserRecord> | null>(null);
const detailOrders = ref<Paginated<AdminOrderRecord> | null>(null);
const detailServices = ref<Paginated<AdminServiceRecord> | null>(null);
const detailOrderPage = ref(1);
const detailServicePage = ref(1);
const detailOrdersLoading = ref(false);
const detailServicesLoading = ref(false);
const detailError = ref('');

const filters = reactive({
    search: '',
    role: ROLE_ALL,
    per_page: '20',
});

const createForm = reactive({
    name: '',
    email: '',
    password: '',
    role: 'user' as AdminRole,
});

const form = reactive({
    name: '',
    email: '',
    role: 'user' as AdminRole,
    cdnfly_user_id: '',
    email_verified: 'true',
});

const rechargeForm = reactive({
    amount: '',
});

const rows = computed(() => users.value?.data ?? []);
const detailOrderRows = computed(() => detailOrders.value?.data ?? []);
const detailServiceRows = computed(() => detailServices.value?.data ?? []);
const hasPreviousPage = computed(() => (users.value?.current_page ?? 1) > 1);
const hasNextPage = computed(
    () => (users.value?.current_page ?? 1) < (users.value?.last_page ?? 1),
);
const paginationText = computed(() => {
    if (!users.value || users.value.total === 0) {
        return '暂无用户';
    }

    return `${users.value.from ?? 0}-${users.value.to ?? 0} / ${users.value.total}`;
});

onMounted(() => {
    void loadUsers();
});

async function loadUsers(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const role =
            filters.role === ROLE_ALL ? undefined : (filters.role as AdminRole);
        users.value = await listAdminUsers({
            page: targetPage,
            per_page: Number(filters.per_page),
            search: filters.search.trim(),
            role,
        });
        page.value = users.value.current_page;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

function submitSearch(): void {
    page.value = 1;
    void loadUsers(1);
}

function openCreateDialog(): void {
    createForm.name = '';
    createForm.email = '';
    createForm.password = '';
    createForm.role = 'user';
    formError.value = '';
    createDialogOpen.value = true;
}

async function submitCreate(): Promise<void> {
    creating.value = true;
    formError.value = '';

    try {
        await createAdminUser({
            name: createForm.name.trim(),
            email: createForm.email.trim(),
            password: createForm.password,
            role: createForm.role,
        });
        createDialogOpen.value = false;
        toast.success('用户已创建');
        await loadUsers();
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        creating.value = false;
    }
}

function openDeleteConfirm(user: AdminUserRecord): void {
    deleteTargetUser.value = user;
    formError.value = '';
    deleteConfirmOpen.value = true;
}

async function confirmDelete(): Promise<void> {
    if (!deleteTargetUser.value) {
        return;
    }

    deleting.value = true;
    formError.value = '';

    try {
        await deleteAdminUser(deleteTargetUser.value.id);
        deleteConfirmOpen.value = false;
        toast.success('用户已删除');
        await loadUsers();
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        deleting.value = false;
    }
}

function openEditDialog(user: AdminUserRecord): void {
    editingUser.value = user;
    form.name = user.name;
    form.email = user.email;
    form.role = user.role === 'admin' ? 'admin' : 'user';
    form.cdnfly_user_id = user.cdnfly_user_id
        ? String(user.cdnfly_user_id)
        : '';
    form.email_verified = user.email_verified ? 'true' : 'false';
    formError.value = '';
    editDialogOpen.value = true;
}

async function submitUser(): Promise<void> {
    if (!editingUser.value) {
        return;
    }

    saving.value = true;
    formError.value = '';

    try {
        const updated = await updateAdminUser(editingUser.value.id, {
            name: form.name.trim(),
            email: form.email.trim(),
            role: form.role,
            cdnfly_user_id:
                form.cdnfly_user_id.trim() === ''
                    ? null
                    : Number(form.cdnfly_user_id),
            email_verified: form.email_verified === 'true',
        });

        replaceUser(updated);
        editDialogOpen.value = false;
        toast.success('用户资料已保存');
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

async function syncApiKey(user: AdminUserRecord): Promise<void> {
    syncingId.value = user.id;
    errorMessage.value = '';

    try {
        await syncAdminUserApiKey(user.id);
        await loadUsers();
        toast.success('API Key 已同步');
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        syncingId.value = null;
    }
}

function openRechargeDialog(user: AdminUserRecord): void {
    rechargeUser.value = user;
    rechargeForm.amount = '';
    formError.value = '';
    rechargeDialogOpen.value = true;
}

async function submitRecharge(): Promise<void> {
    if (!rechargeUser.value) {
        return;
    }

    recharging.value = true;
    formError.value = '';

    try {
        await rechargeAdminUser(rechargeUser.value.id, rechargeForm.amount);
        rechargeDialogOpen.value = false;
        toast.success('充值请求已提交到 CDNfly');
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        recharging.value = false;
    }
}

function openDetailDialog(user: AdminUserRecord): void {
    detailUser.value = user;
    detailOrders.value = null;
    detailServices.value = null;
    detailOrderPage.value = 1;
    detailServicePage.value = 1;
    detailError.value = '';
    detailDialogOpen.value = true;
    void Promise.all([loadUserOrders(1), loadUserServices(1)]);
}

async function loadUserOrders(
    targetPage = detailOrderPage.value,
): Promise<void> {
    if (!detailUser.value) {
        return;
    }

    detailOrdersLoading.value = true;
    detailError.value = '';

    try {
        detailOrders.value = await listAdminOrders({
            user_id: detailUser.value.id,
            page: targetPage,
            per_page: 8,
        });
        detailOrderPage.value = detailOrders.value.current_page;
    } catch (error) {
        detailError.value = getErrorMessage(error);
    } finally {
        detailOrdersLoading.value = false;
    }
}

async function loadUserServices(
    targetPage = detailServicePage.value,
): Promise<void> {
    if (!detailUser.value) {
        return;
    }

    detailServicesLoading.value = true;
    detailError.value = '';

    try {
        detailServices.value = await listAdminServices({
            user_id: detailUser.value.id,
            page: targetPage,
            per_page: 8,
        });
        detailServicePage.value = detailServices.value.current_page;
    } catch (error) {
        detailError.value = getErrorMessage(error);
    } finally {
        detailServicesLoading.value = false;
    }
}

function replaceUser(user: AdminUserRecord): void {
    if (!users.value) {
        return;
    }

    users.value.data = users.value.data.map((item) =>
        item.id === user.id ? user : item,
    );
}

function getErrorMessage(error: unknown): string {
    return error instanceof Error ? error.message : '请求失败';
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function roleLabel(role: string): string {
    return role === 'admin' ? '管理员' : '用户';
}

function userDisplayName(user: AdminUserRecord): string {
    return user.name || user.email || `#${user.id}`;
}

function orderProductName(order: AdminOrderRecord): string {
    if (order.product_name) {
        return order.product_name;
    }

    const snapshot = order.product_snapshot;

    if (
        snapshot &&
        typeof snapshot === 'object' &&
        'name' in snapshot &&
        typeof snapshot.name === 'string'
    ) {
        return snapshot.name;
    }

    return order.product_id ? `产品 #${order.product_id}` : '-';
}

function orderCycleText(order: AdminOrderRecord): string {
    return [order.order_type, order.billing_cycle, order.quantity]
        .filter((item) => item !== null && item !== '' && item !== undefined)
        .join(' / ');
}

function serviceName(service: AdminServiceRecord): string {
    return (
        service.service_name ||
        service.product_name ||
        (service.product_id
            ? `产品 #${service.product_id}`
            : `服务 #${service.id}`)
    );
}

function detailPaginationText<T>(payload: Paginated<T> | null): string {
    if (!payload || payload.total === 0) {
        return '0 / 0';
    }

    return `${payload.from ?? 0}-${payload.to ?? 0} / ${payload.total}`;
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="用户管理"
            :icon="Users"
            :show-api-badge="false"
        />

        <Card class="gap-4">
            <CardContent class="pt-6">
                <div class="grid gap-3 lg:grid-cols-[1fr_180px_120px_auto]">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="filters.search"
                            class="pl-9"
                            placeholder="搜索姓名、邮箱、用户 ID、CDNfly ID"
                            @keyup.enter="submitSearch"
                        />
                    </div>
                    <Select v-model="filters.role">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="ROLE_ALL"
                                    >全部角色</SelectItem
                                >
                                <SelectItem value="admin">管理员</SelectItem>
                                <SelectItem value="user">普通用户</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.per_page">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="20">20 条</SelectItem>
                                <SelectItem value="50">50 条</SelectItem>
                                <SelectItem value="100">100 条</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <div class="flex gap-2">
                        <Button :disabled="loading" @click="submitSearch">
                            <Spinner v-if="loading" data-icon="inline-start" />
                            <Search v-else data-icon="inline-start" />
                            搜索
                        </Button>
                        <Button
                            variant="outline"
                            :disabled="loading"
                            @click="loadUsers()"
                        >
                            <RefreshCw data-icon="inline-start" />
                            刷新
                        </Button>
                        <Button
                            variant="default"
                            :disabled="loading"
                            @click="openCreateDialog"
                        >
                            <Plus data-icon="inline-start" />
                            新建用户
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>用户管理请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>
        <Card class="gap-0 overflow-hidden">
            <CardHeader
                class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-md border bg-card"
                    >
                        <UserRound class="size-5" />
                    </div>
                    <CardTitle class="text-base">用户列表</CardTitle>
                </div>
                <div class="text-sm text-muted-foreground">
                    {{ paginationText }}
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1280px] table-fixed text-sm">
                        <colgroup>
                            <col style="width: 160px" />
                            <col style="width: 84px" />
                            <col style="width: 280px" />
                            <col style="width: 104px" />
                            <col style="width: 104px" />
                            <col style="width: 72px" />
                            <col style="width: 72px" />
                            <col style="width: 172px" />
                            <col style="width: 232px" />
                        </colgroup>
                        <thead
                            class="border-y bg-muted/50 text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-2.5 text-left font-medium">
                                    用户
                                </th>
                                <th class="px-2 py-2.5 text-center font-medium">
                                    角色
                                </th>
                                <th class="px-3 py-2.5 text-left font-medium">
                                    邮箱
                                </th>
                                <th class="px-2 py-2.5 text-center font-medium">
                                    CDNfly ID
                                </th>
                                <th class="px-2 py-2.5 text-center font-medium">
                                    API Key
                                </th>
                                <th class="px-2 py-2.5 text-center font-medium">
                                    订单
                                </th>
                                <th class="px-2 py-2.5 text-center font-medium">
                                    服务
                                </th>
                                <th class="px-3 py-2.5 text-left font-medium">
                                    注册时间
                                </th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    操作
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading && rows.length === 0">
                                <td class="px-6 py-16 text-center" colspan="9">
                                    <Spinner />
                                </td>
                            </tr>
                            <tr
                                v-for="user in rows"
                                :key="user.id"
                                class="border-b last:border-b-0"
                            >
                                <td class="px-4 py-3 align-middle">
                                    <div class="truncate font-medium">
                                        {{ userDisplayName(user) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        #{{ user.id }}
                                    </div>
                                </td>
                                <td class="px-2 py-3 text-center align-middle">
                                    <Badge
                                        :variant="
                                            user.role === 'admin'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{ roleLabel(user.role) }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-3 align-middle">
                                    <div class="truncate">{{ user.email }}</div>
                                    <Badge
                                        class="mt-1"
                                        :variant="
                                            user.email_verified
                                                ? 'secondary'
                                                : 'outline'
                                        "
                                    >
                                        {{
                                            user.email_verified
                                                ? '已验证'
                                                : '未验证'
                                        }}
                                    </Badge>
                                </td>
                                <td
                                    class="px-2 py-3 text-center align-middle text-muted-foreground"
                                >
                                    {{ user.cdnfly_user_id ?? '-' }}
                                </td>
                                <td class="px-2 py-3 text-center align-middle">
                                    <Badge
                                        :variant="
                                            user.has_api_key
                                                ? 'secondary'
                                                : 'outline'
                                        "
                                    >
                                        {{
                                            user.has_api_key
                                                ? '已就绪'
                                                : '未就绪'
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-2 py-3 text-center align-middle">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 px-2"
                                        :aria-label="`查看用户 #${user.id} 的订单`"
                                        :title="`查看用户 #${user.id} 的订单`"
                                        @click="openDetailDialog(user)"
                                    >
                                        <ReceiptText data-icon="inline-start" />
                                        {{ user.orders_count }}
                                    </Button>
                                </td>
                                <td class="px-2 py-3 text-center align-middle">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 px-2"
                                        :aria-label="`查看用户 #${user.id} 的已有套餐`"
                                        :title="`查看用户 #${user.id} 的已有套餐`"
                                        @click="openDetailDialog(user)"
                                    >
                                        <PackageCheck
                                            data-icon="inline-start"
                                        />
                                        {{ user.service_instances_count }}
                                    </Button>
                                </td>
                                <td
                                    class="px-3 py-3 align-middle text-muted-foreground"
                                >
                                    {{ formatDate(user.created_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openEditDialog(user)"
                                        >
                                            <Pencil data-icon="inline-start" />
                                            编辑
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :disabled="
                                                !user.cdnfly_user_id ||
                                                syncingId === user.id
                                            "
                                            @click="syncApiKey(user)"
                                        >
                                            <Spinner
                                                v-if="syncingId === user.id"
                                                data-icon="inline-start"
                                            />
                                            <KeyRound
                                                v-else
                                                data-icon="inline-start"
                                            />
                                            同步
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :disabled="!user.cdnfly_user_id"
                                            @click="openRechargeDialog(user)"
                                        >
                                            <Wallet data-icon="inline-start" />
                                            充值
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :disabled="deleting"
                                            @click="openDeleteConfirm(user)"
                                        >
                                            <Trash2 data-icon="inline-start" />
                                            删除
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && rows.length === 0">
                                <td
                                    class="px-6 py-16 text-center text-muted-foreground"
                                    colspan="9"
                                >
                                    暂无用户
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasPreviousPage || loading"
                @click="loadUsers(page - 1)"
            >
                上一页
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasNextPage || loading"
                @click="loadUsers(page + 1)"
            >
                下一页
            </Button>
        </div>

        <Dialog v-model:open="detailDialogOpen">
            <DialogScrollContent class="max-w-6xl">
                <DialogHeader>
                    <DialogTitle>用户订单与已有套餐</DialogTitle>
                    <DialogDescription>
                        {{
                            detailUser
                                ? `${userDisplayName(detailUser)}（#${detailUser.id}）的本地订单和已开通服务`
                                : ''
                        }}
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="detailError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>加载失败</AlertTitle>
                    <AlertDescription>{{ detailError }}</AlertDescription>
                </Alert>

                <div v-if="detailUser" class="grid gap-3 md:grid-cols-3">
                    <div class="rounded-md border p-4">
                        <div class="text-xs text-muted-foreground">订单</div>
                        <div class="mt-1 text-2xl font-semibold">
                            {{ detailUser.orders_count }}
                        </div>
                    </div>
                    <div class="rounded-md border p-4">
                        <div class="text-xs text-muted-foreground">
                            已有套餐 / 服务
                        </div>
                        <div class="mt-1 text-2xl font-semibold">
                            {{ detailUser.service_instances_count }}
                        </div>
                    </div>
                    <div class="rounded-md border p-4">
                        <div class="text-xs text-muted-foreground">
                            CDNfly 用户 ID
                        </div>
                        <div class="mt-1 text-2xl font-semibold">
                            {{ detailUser.cdnfly_user_id ?? '-' }}
                        </div>
                    </div>
                </div>

                <section class="overflow-hidden rounded-md border">
                    <div
                        class="flex flex-col gap-2 border-b bg-muted/40 px-4 py-3 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="flex items-center gap-2 font-medium">
                            <ReceiptText class="size-4" />
                            订单
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ detailPaginationText(detailOrders) }}
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[860px] text-sm">
                            <thead class="border-b text-muted-foreground">
                                <tr>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        订单号
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        产品
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        金额
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        状态
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        支付网关
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        创建时间
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="detailOrdersLoading">
                                    <td
                                        class="px-4 py-10 text-center"
                                        colspan="6"
                                    >
                                        <Spinner />
                                    </td>
                                </tr>
                                <tr
                                    v-for="order in detailOrderRows"
                                    :key="order.id"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="px-4 py-3 font-medium">
                                        {{ order.order_no || `#${order.id}` }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>{{ orderProductName(order) }}</div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ orderCycleText(order) || '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ order.amount_usdt }} USDT
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge variant="secondary">
                                            {{ order.status }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ order.gateway_status ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(order.created_at) }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        !detailOrdersLoading &&
                                        detailOrderRows.length === 0
                                    "
                                >
                                    <td
                                        class="px-4 py-10 text-center text-muted-foreground"
                                        colspan="6"
                                    >
                                        暂无订单
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        v-if="detailOrders && detailOrders.last_page > 1"
                        class="flex justify-end gap-2 border-t px-4 py-3"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="
                                detailOrderPage <= 1 || detailOrdersLoading
                            "
                            @click="loadUserOrders(detailOrderPage - 1)"
                        >
                            上一页
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="
                                detailOrderPage >= detailOrders.last_page ||
                                detailOrdersLoading
                            "
                            @click="loadUserOrders(detailOrderPage + 1)"
                        >
                            下一页
                        </Button>
                    </div>
                </section>

                <section class="overflow-hidden rounded-md border">
                    <div
                        class="flex flex-col gap-2 border-b bg-muted/40 px-4 py-3 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="flex items-center gap-2 font-medium">
                            <PackageCheck class="size-4" />
                            已有套餐 / 服务
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ detailPaginationText(detailServices) }}
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[920px] text-sm">
                            <thead class="border-b text-muted-foreground">
                                <tr>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        服务
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        来源订单
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        CDNfly 服务 ID
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        状态
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        到期时间
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium"
                                    >
                                        错误
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="detailServicesLoading">
                                    <td
                                        class="px-4 py-10 text-center"
                                        colspan="6"
                                    >
                                        <Spinner />
                                    </td>
                                </tr>
                                <tr
                                    v-for="service in detailServiceRows"
                                    :key="service.id"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="px-4 py-3 font-medium">
                                        <div>{{ serviceName(service) }}</div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            #{{ service.id }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ service.order_no ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ service.cdnfly_service_id ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge variant="secondary">
                                            {{ service.status }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(service.expired_at) }}
                                    </td>
                                    <td
                                        class="max-w-[220px] truncate px-4 py-3 text-muted-foreground"
                                    >
                                        {{ service.error_message ?? '-' }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        !detailServicesLoading &&
                                        detailServiceRows.length === 0
                                    "
                                >
                                    <td
                                        class="px-4 py-10 text-center text-muted-foreground"
                                        colspan="6"
                                    >
                                        暂无已开通服务
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        v-if="detailServices && detailServices.last_page > 1"
                        class="flex justify-end gap-2 border-t px-4 py-3"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="
                                detailServicePage <= 1 || detailServicesLoading
                            "
                            @click="loadUserServices(detailServicePage - 1)"
                        >
                            上一页
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="
                                detailServicePage >= detailServices.last_page ||
                                detailServicesLoading
                            "
                            @click="loadUserServices(detailServicePage + 1)"
                        >
                            下一页
                        </Button>
                    </div>
                </section>
            </DialogScrollContent>
        </Dialog>

        <Dialog v-model:open="editDialogOpen">
            <DialogScrollContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>编辑用户</DialogTitle>
                    <DialogDescription>
                        修改本地用户资料和 CDNfly 用户映射。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="formError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>保存失败</AlertTitle>
                    <AlertDescription>{{ formError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <Label for="admin-user-name">姓名</Label>
                        <Input id="admin-user-name" v-model="form.name" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="admin-user-email">邮箱</Label>
                        <Input id="admin-user-email" v-model="form.email" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>角色</Label>
                        <Select v-model="form.role">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="user"
                                        >普通用户</SelectItem
                                    >
                                    <SelectItem value="admin"
                                        >管理员</SelectItem
                                    >
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>邮箱状态</Label>
                        <Select v-model="form.email_verified">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="true">已验证</SelectItem>
                                    <SelectItem value="false"
                                        >未验证</SelectItem
                                    >
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2 md:col-span-2">
                        <Label for="admin-user-cdnfly-id">CDNfly 用户 ID</Label>
                        <Input
                            id="admin-user-cdnfly-id"
                            v-model="form.cdnfly_user_id"
                            inputmode="numeric"
                            placeholder="留空表示未映射"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="editDialogOpen = false">
                        取消
                    </Button>
                    <Button :disabled="saving" @click="submitUser">
                        <Spinner v-if="saving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        保存
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <Dialog v-model:open="createDialogOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>新建用户</DialogTitle>
                    <DialogDescription>
                        创建本地用户账号，后续可映射到 CDNfly 用户。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="formError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>创建失败</AlertTitle>
                    <AlertDescription>{{ formError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="admin-user-create-name">姓名</Label>
                        <Input
                            id="admin-user-create-name"
                            v-model="createForm.name"
                            placeholder="用户姓名"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="admin-user-create-email">邮箱</Label>
                        <Input
                            id="admin-user-create-email"
                            v-model="createForm.email"
                            type="email"
                            placeholder="user@example.com"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="admin-user-create-password">密码</Label>
                        <Input
                            id="admin-user-create-password"
                            v-model="createForm.password"
                            type="password"
                            placeholder="至少 8 位"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>角色</Label>
                        <Select v-model="createForm.role">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="user"
                                        >普通用户</SelectItem
                                    >
                                    <SelectItem value="admin"
                                        >管理员</SelectItem
                                    >
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="createDialogOpen = false"
                    >
                        取消
                    </Button>
                    <Button :disabled="creating" @click="submitCreate">
                        <Spinner v-if="creating" data-icon="inline-start" />
                        <Plus v-else data-icon="inline-start" />
                        创建
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <Dialog v-model:open="deleteConfirmOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>确认删除</DialogTitle>
                    <DialogDescription>
                        确定要删除用户
                        <strong>{{
                            deleteTargetUser
                                ? userDisplayName(deleteTargetUser)
                                : ''
                        }}</strong>（#{{
                            deleteTargetUser?.id ?? ''
                        }}）吗？此操作不可撤销。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="formError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>删除失败</AlertTitle>
                    <AlertDescription>{{ formError }}</AlertDescription>
                </Alert>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="deleteConfirmOpen = false"
                    >
                        取消
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleting"
                        @click="confirmDelete"
                    >
                        <Spinner v-if="deleting" data-icon="inline-start" />
                        <Trash2 v-else data-icon="inline-start" />
                        确认删除
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <Dialog v-model:open="rechargeDialogOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>用户充值</DialogTitle>
                    <DialogDescription>
                        金额会提交到 CDNfly 管理端充值接口。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="formError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>充值失败</AlertTitle>
                    <AlertDescription>{{ formError }}</AlertDescription>
                </Alert>

                <div class="flex flex-col gap-2">
                    <Label for="admin-user-recharge">充值金额</Label>
                    <Input
                        id="admin-user-recharge"
                        v-model="rechargeForm.amount"
                        inputmode="decimal"
                        placeholder="如 100"
                    />
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="rechargeDialogOpen = false"
                    >
                        取消
                    </Button>
                    <Button :disabled="recharging" @click="submitRecharge">
                        <Spinner v-if="recharging" data-icon="inline-start" />
                        <Wallet v-else data-icon="inline-start" />
                        提交
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
