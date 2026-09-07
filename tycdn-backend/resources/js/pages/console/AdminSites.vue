<script setup lang="ts">
import {
    Eye,
    Globe2,
    Pencil,
    Plus,
    Power,
    PowerOff,
    Save,
    Trash2,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner'
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsoleFormDialog from '@/components/console/ConsoleFormDialog.vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
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
import {
    createAdminCert,
    createAdminSite,
    deleteAdminCert,
    deleteAdminSite,
    getAdminSite,
    listAdminAllAcls,
    listAdminAllCerts,
    listAdminCdnflyUserPackages,
    listAdminDnsApis,
    listAdminSites,
    setAdminSiteEnabled,
    updateAdminCert,
    updateAdminSite,
} from '@/lib/adminModulesApi';
import type { AdminCertPayload, AdminSitePayload } from '@/lib/adminModulesApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import { formatDate, getErrorMessage } from '@/lib/formatters';

const STATUS_ALL = 'all';
const STATUS_RUNNING = '1';
const STATUS_STOPPED = '0';

const siteColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'name', label: '域名' },
    { key: 'user_id', label: '用户 ID', width: '90px' },
    {
        key: 'status',
        label: '状态',
        width: '100px',
        badge: true,
        format: (v) =>
            v === 1 || v === '1'
                ? '运行中'
                : v === 0 || v === '0'
                  ? '已停用'
                  : String(v ?? '-'),
        badgeVariant: (v) =>
            v === 1 || v === '1' ? 'secondary' : 'destructive',
    },
    { key: 'package_id', label: '套餐 ID', width: '90px' },
    {
        key: 'created_at',
        label: '创建时间',
        width: '160px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];

const certColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'name', label: '证书名称' },
    { key: 'domain', label: '域名' },
    { key: 'type', label: '类型', width: '90px' },
    {
        key: 'enable',
        label: '状态',
        badge: true,
        width: '90px',
        format: (v) => (v === 1 || v === '1' ? '可用' : '已停用'),
        badgeVariant: (v) => (v === 1 || v === '1' ? 'secondary' : 'destructive'),
    },
    {
        key: 'expire_time',
        label: '到期时间',
        width: '160px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];

const aclColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'name', label: '规则名称' },
    { key: 'type', label: '类型', width: '100px' },
    {
        key: 'status',
        label: '状态',
        badge: true,
        width: '100px',
        format: (v) => String(v ?? '-'),
    },
];

const sitesTableRef = ref<InstanceType<typeof ConsoleDataTable> | null>(null);
const togglingId = ref<number | null>(null);
const detailOpen = ref(false);
const detailLoading = ref(false);
const detailError = ref('');
const detailSite = ref<CdnflyRecord | null>(null);

const createOpen = ref(false);
const creating = ref(false);
const createError = ref('');
const showAdvancedCreate = ref(false);
const createForm = reactive({
    user_package: '',
    domain: '',
    backend_addr: '',
    groups: '',
});

const editOpen = ref(false);
const saving = ref(false);
const editError = ref('');
const editTargetId = ref<number | null>(null);
const showAdvancedEdit = ref(false);
const editForm = reactive({
    user_package: '',
    domain: '',
    backend_addr: '',
    groups: '',
});

const deleteOpen = ref(false);
const deleting = ref(false);
const deleteError = ref('');
const deleteTarget = ref<CdnflyRecord | null>(null);

const userPackages = ref<{ id: number; name: string }[]>([]);

async function loadUserPackages(): Promise<void> {
    try {
        const result = await listAdminCdnflyUserPackages({ limit: '500' });
        const rows = (result.data ?? result) as Array<Record<string, unknown>>;
        userPackages.value = Array.isArray(rows) ? rows.map((r) => ({
            id: Number(r.id ?? 0),
            name: String(r.name ?? r.package_name ?? r.id ?? '-'),
        })) : [];
    } catch {
        userPackages.value = [];
    }
}

const filters = reactive({
    search: '',
    user_id: '',
    status: STATUS_ALL,
});

const searchParams = computed(() => {
    const params: Record<string, string | number> = {};
    const search = filters.search.trim();
    if (search !== '') params.search = search;
    const uid = filters.user_id.trim();
    if (uid !== '') params.user_id = uid;
    if (filters.status !== STATUS_ALL) params.status = filters.status;
    return params;
});

function isSiteRunning(row: CdnflyRecord): boolean {
    return row.status === 1 || row.status === '1';
}

async function toggleSiteEnabled(row: CdnflyRecord): Promise<void> {
    const id = Number(row.id);
    if (!id) return;

    togglingId.value = id;
    try {
        await setAdminSiteEnabled(id, !isSiteRunning(row));
        sitesTableRef.value?.refresh();
    } catch {
        // table will re-render with current state
    } finally {
        togglingId.value = null;
    }
}

async function openDetail(row: CdnflyRecord): Promise<void> {
    const id = Number(row.id);
    if (!id) return;

    detailOpen.value = true;
    detailLoading.value = true;
    detailError.value = '';
    detailSite.value = null;

    try {
        const result = await getAdminSite(id);
        detailSite.value =
            result && typeof result === 'object' && !Array.isArray(result)
                ? (((result as Record<string, unknown>).data as CdnflyRecord) ??
                  result)
                : result;
    } catch (error) {
        detailError.value = getErrorMessage(error);
    } finally {
        detailLoading.value = false;
    }
}

function openCreateDialog(): void {
    createForm.user_package = '';
    createForm.domain = '';
    createForm.backend_addr = '';
    createForm.groups = '';
    createError.value = '';
    showAdvancedCreate.value = false;
    createOpen.value = true;
    if (userPackages.value.length === 0) {
        void loadUserPackages();
    }
}

async function submitCreate(): Promise<void> {
    creating.value = true;
    createError.value = '';

    try {
        await createAdminSite(toSitePayload(createForm));
        createOpen.value = false;
        toast.success('网站已创建');
        sitesTableRef.value?.refresh();
    } catch (error) {
        createError.value = getErrorMessage(error);
    } finally {
        creating.value = false;
    }
}

function openEditDialog(row: CdnflyRecord): void {
    editTargetId.value = Number(row.id);
    editForm.user_package = row.user_package != null ? String(row.user_package) : '';
    editForm.domain = String(row.domain ?? row.name ?? '');
    const backend = row.backend;
    if (Array.isArray(backend) && backend.length > 0) {
        editForm.backend_addr = String((backend[0] as Record<string, unknown>).addr ?? '');
    } else {
        editForm.backend_addr = '';
    }
    editForm.groups = row.groups != null ? String(row.groups) : '';
    editError.value = '';
    showAdvancedEdit.value = false;
    editOpen.value = true;
}

async function submitEdit(): Promise<void> {
    if (!editTargetId.value) return;

    saving.value = true;
    editError.value = '';

    try {
        await updateAdminSite(editTargetId.value, toSitePayload(editForm));
        editOpen.value = false;
        toast.success('网站已更新');
        sitesTableRef.value?.refresh();
    } catch (error) {
        editError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

function openDeleteConfirm(row: CdnflyRecord): void {
    deleteTarget.value = row;
    deleteError.value = '';
    deleteOpen.value = true;
}

async function confirmDelete(): Promise<void> {
    if (!deleteTarget.value) return;

    deleting.value = true;
    deleteError.value = '';

    try {
        await deleteAdminSite(Number(deleteTarget.value.id));
        deleteOpen.value = false;
        toast.success('网站已删除');
        sitesTableRef.value?.refresh();
    } catch (error) {
        deleteError.value = getErrorMessage(error);
    } finally {
        deleting.value = false;
    }
}

function toSitePayload(form: typeof createForm): AdminSitePayload {
    return {
        user_package: Number(form.user_package),
        domain: form.domain.trim(),
        backend: [{ addr: form.backend_addr.trim() }],
        groups: form.groups.trim() || undefined,
    };
}

const detailFields = computed<{ label: string; value: string }[]>(() => {
    const s = detailSite.value;
    if (!s) return [];
    return [
        { label: 'ID', value: String(s.id ?? '-') },
        { label: '域名', value: String(s.name ?? '-') },
        { label: '用户 ID', value: String(s.user_id ?? '-') },
        {
            label: '状态',
            value: isSiteRunning(s) ? '运行中' : '已停用',
        },
        { label: '套餐 ID', value: String(s.package_id ?? '-') },
        { label: 'HTTP 端口', value: String(s.http_port ?? '-') },
        { label: 'HTTPS 端口', value: String(s.https_port ?? '-') },
        {
            label: '源站地址',
            value: String(s.source_address ?? s.origin ?? '-'),
        },
        {
            label: '创建时间',
            value: formatDate(s.created_at as string | null | undefined),
        },
        {
            label: '更新时间',
            value: formatDate(s.updated_at as string | null | undefined),
        },
    ];
});

// ─── Certs CRUD ─────────────────────────────────────────
const certsTableRef = ref<InstanceType<typeof ConsoleDataTable> | null>(null);
const certDialogOpen = ref(false);
const certSaving = ref(false);
const certFormError = ref('');
const certDeleteOpen = ref(false);
const certDeleting = ref(false);
const certDeleteTarget = ref<CdnflyRecord | null>(null);
const certEditing = ref<CdnflyRecord | null>(null);
const dnsApiOptions = ref<{ id: number; name: string }[]>([]);

const certForm = reactive({
    name: '',
    type: 'custom' as 'custom' | 'lets' | 'zerossl',
    domain: '',
    dnsapi: '',
    key: '',
    cert: '',
    des: '',
});

const certDialogTitle = computed(() => {
    if (certEditing.value) return '编辑证书';
    return certForm.type === 'custom' ? '新增证书' : '批量申请证书';
});

const certShowTypeSelector = computed(() =>
    !certEditing.value && certForm.type !== 'custom',
);

async function loadDnsApis(): Promise<void> {
    if (dnsApiOptions.value.length > 0) return;
    try {
        const result = await listAdminDnsApis({ limit: '500' });
        const rows = Array.isArray(result) ? result : ((result as Record<string, unknown>).data ?? []);
        dnsApiOptions.value = (rows as Array<Record<string, unknown>>).map((r) => ({
            id: Number(r.id),
            name: String(r.name ?? r.id),
        }));
    } catch {
        dnsApiOptions.value = [];
    }
}

function resetCertForm(): void {
    certEditing.value = null;
    certForm.name = '';
    certForm.domain = '';
    certForm.dnsapi = '';
    certForm.key = '';
    certForm.cert = '';
    certForm.des = '';
    certFormError.value = '';
}

function openCertCreate(): void {
    resetCertForm();
    certForm.type = 'custom';
    certDialogOpen.value = true;
}

function openCertBatch(): void {
    resetCertForm();
    certForm.type = 'lets';
    certDialogOpen.value = true;
    void loadDnsApis();
}

function openCertEdit(row: CdnflyRecord): void {
    certEditing.value = row;
    certForm.name = String(row.name ?? '');
    certForm.type = (row.type as 'custom' | 'lets' | 'zerossl') ?? 'custom';
    certForm.domain = String(row.domain ?? '');
    certForm.dnsapi = String(row.dnsapi ?? '');
    certForm.key = '';
    certForm.cert = '';
    certForm.des = String(row.des ?? '');
    certFormError.value = '';
    certDialogOpen.value = true;
    if (certForm.type !== 'custom') void loadDnsApis();
}

async function submitCert(): Promise<void> {
    certSaving.value = true;
    certFormError.value = '';

    const payload: AdminCertPayload = {
        name: certForm.name.trim(),
        type: certForm.type,
        des: certForm.des.trim() || undefined,
    };

    if (certForm.type === 'custom') {
        if (certForm.key.trim()) payload.key = certForm.key.trim();
        if (certForm.cert.trim()) payload.cert = certForm.cert.trim();
    } else {
        if (certForm.domain.trim()) payload.domain = certForm.domain.trim();
        if (certForm.dnsapi) payload.dnsapi = certForm.dnsapi;
    }

    try {
        if (certEditing.value) {
            await updateAdminCert(Number(certEditing.value.id), payload);
            toast.success('证书已更新');
        } else {
            await createAdminCert(payload);
            toast.success('证书已创建');
        }
        certDialogOpen.value = false;
        certsTableRef.value?.refresh();
    } catch (error) {
        certFormError.value = getErrorMessage(error);
    } finally {
        certSaving.value = false;
    }
}

function openCertDelete(row: CdnflyRecord): void {
    certDeleteTarget.value = row;
    certFormError.value = '';
    certDeleteOpen.value = true;
}

async function confirmCertDelete(): Promise<void> {
    if (!certDeleteTarget.value) return;
    certDeleting.value = true;
    certFormError.value = '';

    try {
        await deleteAdminCert(Number(certDeleteTarget.value.id));
        certDeleteOpen.value = false;
        toast.success('证书已删除');
        certsTableRef.value?.refresh();
    } catch (error) {
        certFormError.value = getErrorMessage(error);
    } finally {
        certDeleting.value = false;
    }
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="全部网站"
            :icon="Globe2"
            :show-api-badge="false"
        />

        <div class="flex justify-end gap-2">
            <Button variant="default" @click="openCreateDialog">
                <Plus data-icon="inline-start" />
                新建网站
            </Button>
        </div>

        <ConsoleDataTable
            ref="sitesTableRef"
            title="站点列表"
            :icon="Globe2"
            :columns="siteColumns"
            :fetch-fn="listAdminSites"
            :search-params="searchParams"
            search-placeholder="搜索域名"
            @row-click="openDetail"
        >
            <template
                #search-fields="{ loading: l, submitSearch }"
            >
                <div class="grid gap-3 sm:grid-cols-3">
                    <Input
                        v-model="filters.search"
                        placeholder="搜索域名"
                        @keyup.enter="submitSearch"
                    />
                    <Input
                        v-model="filters.user_id"
                        placeholder="用户 ID"
                        @keyup.enter="submitSearch"
                    />
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="全部状态" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="STATUS_ALL">
                                    全部状态
                                </SelectItem>
                                <SelectItem :value="STATUS_RUNNING">
                                    运行中
                                </SelectItem>
                                <SelectItem :value="STATUS_STOPPED">
                                    已停用
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
            </template>

            <template #row-actions="{ row }">
                <Button
                    variant="ghost"
                    size="sm"
                    @click="openDetail(row)"
                >
                    <Eye class="size-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="openEditDialog(row)"
                >
                    <Pencil class="size-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="togglingId === Number(row.id)"
                    @click="toggleSiteEnabled(row)"
                >
                    <Spinner
                        v-if="togglingId === Number(row.id)"
                        class="size-4"
                    />
                    <Power
                        v-else-if="!isSiteRunning(row)"
                        class="size-4 text-green-600"
                    />
                    <PowerOff
                        v-else
                        class="size-4 text-destructive"
                    />
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="openDeleteConfirm(row)"
                >
                    <Trash2 class="size-4 text-destructive" />
                </Button>
            </template>
        </ConsoleDataTable>

        <ConsoleDataTable
            ref="certsTableRef"
            title="全部证书"
            :columns="certColumns"
            :fetch-fn="listAdminAllCerts"
            search-placeholder="搜索证书"
        >
            <template #toolbar>
                <Button variant="default" size="sm" @click="openCertCreate">
                    <Plus data-icon="inline-start" class="size-4" />
                    新增证书
                </Button>
                <Button variant="outline" size="sm" @click="openCertBatch">
                    <Plus data-icon="inline-start" class="size-4" />
                    批量申请
                </Button>
            </template>
            <template #row-actions="{ row }">
                <Button variant="ghost" size="sm" @click="openCertEdit(row)">
                    <Pencil class="size-4" />
                </Button>
                <Button variant="ghost" size="sm" @click="openCertDelete(row)">
                    <Trash2 class="size-4 text-destructive" />
                </Button>
            </template>
        </ConsoleDataTable>

        <ConsoleDataTable
            title="全部 ACL"
            :columns="aclColumns"
            :fetch-fn="listAdminAllAcls"
            search-placeholder="搜索 ACL"
        >
            <template #actions-col><col style="width: 0" /></template>
            <template #actions-header><th /></template>
        </ConsoleDataTable>

        <!-- Create site dialog -->
        <Dialog v-model:open="createOpen">
            <DialogScrollContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>新建网站</DialogTitle>
                    <DialogDescription>
                        输入必填字段后创建 CDNfly 网站。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="createError" variant="destructive">
                    <AlertTitle>创建失败</AlertTitle>
                    <AlertDescription>{{ createError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="site-create-package">套餐</Label>
                        <Select
                            v-model="createForm.user_package"
                            @open-change="(open: boolean) => open && userPackages.length === 0 && loadUserPackages()"
                        >
                            <SelectTrigger id="site-create-package">
                                <SelectValue placeholder="选择已购套餐" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="pkg in userPackages"
                                        :key="pkg.id"
                                        :value="String(pkg.id)"
                                    >
                                        {{ pkg.name }} (#{{ pkg.id }})
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="site-create-domain">域名</Label>
                        <Input
                            id="site-create-domain"
                            v-model="createForm.domain"
                            placeholder="example.com"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="site-create-backend">源站地址</Label>
                        <Input
                            id="site-create-backend"
                            v-model="createForm.backend_addr"
                            placeholder="1.1.1.1"
                        />
                    </div>
                    <!-- 可选配置展开 -->
                    <div class="border-t pt-3">
                        <button
                            type="button"
                            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                            @click="showAdvancedCreate = !showAdvancedCreate"
                        >
                            <span>{{ showAdvancedCreate ? '▲' : '▼' }}</span>
                            可选配置
                        </button>
                        <div v-if="showAdvancedCreate" class="mt-3 grid gap-4">
                            <div class="flex flex-col gap-2">
                                <Label for="site-create-groups">所属分组</Label>
                                <Input
                                    id="site-create-groups"
                                    v-model="createForm.groups"
                                    placeholder="分组ID，多个逗号分隔"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="createOpen = false">
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

        <!-- Edit site dialog -->
        <Dialog v-model:open="editOpen">
            <DialogScrollContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>编辑网站 #{{ editTargetId }}</DialogTitle>
                    <DialogDescription>
                        修改网站配置，仅提交有变化的字段。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="editError" variant="destructive">
                    <AlertTitle>保存失败</AlertTitle>
                    <AlertDescription>{{ editError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="site-edit-package">套餐</Label>
                        <Select
                            v-model="editForm.user_package"
                            @open-change="(open: boolean) => open && userPackages.length === 0 && loadUserPackages()"
                        >
                            <SelectTrigger id="site-edit-package">
                                <SelectValue placeholder="选择已购套餐" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="pkg in userPackages"
                                        :key="pkg.id"
                                        :value="String(pkg.id)"
                                    >
                                        {{ pkg.name }} (#{{ pkg.id }})
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="site-edit-domain">域名</Label>
                        <Input
                            id="site-edit-domain"
                            v-model="editForm.domain"
                            placeholder="example.com"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="site-edit-backend">源站地址</Label>
                        <Input
                            id="site-edit-backend"
                            v-model="editForm.backend_addr"
                            placeholder="1.1.1.1"
                        />
                    </div>
                    <!-- 可选配置展开 -->
                    <div class="border-t pt-3">
                        <button
                            type="button"
                            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                            @click="showAdvancedEdit = !showAdvancedEdit"
                        >
                            <span>{{ showAdvancedEdit ? '▲' : '▼' }}</span>
                            可选配置
                        </button>
                        <div v-if="showAdvancedEdit" class="mt-3 grid gap-4">
                            <div class="flex flex-col gap-2">
                                <Label for="site-edit-groups">所属分组</Label>
                                <Input
                                    id="site-edit-groups"
                                    v-model="editForm.groups"
                                    placeholder="分组ID，多个逗号分隔"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="editOpen = false">
                        取消
                    </Button>
                    <Button :disabled="saving" @click="submitEdit">
                        <Spinner v-if="saving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        保存
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- Delete confirmation dialog -->
        <Dialog v-model:open="deleteOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>确认删除</DialogTitle>
                    <DialogDescription>
                        确定要删除网站
                        <strong>{{ deleteTarget?.domain ?? deleteTarget?.name ?? '-' }}</strong>
                        （ID: {{ deleteTarget?.id }}）吗？此操作不可撤销。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="deleteError" variant="destructive">
                    <AlertTitle>删除失败</AlertTitle>
                    <AlertDescription>{{ deleteError }}</AlertDescription>
                </Alert>

                <DialogFooter>
                    <Button variant="outline" @click="deleteOpen = false">
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

        <!-- Cert create/edit dialog -->
        <Dialog v-model:open="certDialogOpen">
            <DialogScrollContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ certDialogTitle }}</DialogTitle>
                    <DialogDescription>管理 SSL 证书。</DialogDescription>
                </DialogHeader>

                <Alert v-if="certFormError" variant="destructive">
                    <AlertTitle>提交失败</AlertTitle>
                    <AlertDescription>{{ certFormError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="cert-name">名称</Label>
                        <Input id="cert-name" v-model="certForm.name" placeholder="证书名称" />
                    </div>

                    <!-- 批量申请或编辑非 custom 证书时显示类型选择 -->
                    <div v-if="certShowTypeSelector || (certEditing && certForm.type !== 'custom')" class="grid gap-2">
                        <Label>签发方式</Label>
                        <Select v-model="certForm.type" @update:model-value="(v) => { certForm.type = String(v) as 'custom' | 'lets' | 'zerossl'; }">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="lets">Let's Encrypt</SelectItem>
                                    <SelectItem value="zerossl">ZeroSSL</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- 自定义证书：私钥 + 证书 -->
                    <template v-if="certForm.type === 'custom'">
                        <div class="grid gap-2">
                            <Label for="cert-key">私钥 (KEY)</Label>
                            <textarea
                                id="cert-key"
                                v-model="certForm.key"
                                class="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                :placeholder="certEditing ? '留空不修改' : '-----BEGIN RSA PRIVATE KEY-----'"
                                spellcheck="false"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="cert-cert">证书 (CERT)</Label>
                            <textarea
                                id="cert-cert"
                                v-model="certForm.cert"
                                class="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                :placeholder="certEditing ? '留空不修改' : '-----BEGIN CERTIFICATE-----'"
                                spellcheck="false"
                            />
                        </div>
                    </template>

                    <!-- 自动申请：域名 + DNS API -->
                    <template v-else>
                        <div class="grid gap-2">
                            <Label for="cert-domain">域名</Label>
                            <Input id="cert-domain" v-model="certForm.domain" placeholder="example.com 或 *.example.com" />
                        </div>
                        <div class="grid gap-2">
                            <Label>DNS API</Label>
                            <Select v-model="certForm.dnsapi">
                                <SelectTrigger><SelectValue placeholder="选择 DNS API" /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem v-for="api in dnsApiOptions" :key="api.id" :value="String(api.id)">
                                            {{ api.name }} (#{{ api.id }})
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </template>

                    <div class="grid gap-2">
                        <Label for="cert-des">备注</Label>
                        <Input id="cert-des" v-model="certForm.des" placeholder="可选备注" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="certDialogOpen = false">取消</Button>
                    <Button :disabled="certSaving" @click="submitCert">
                        <Spinner v-if="certSaving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        保存
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- Cert delete confirm dialog -->
        <Dialog v-model:open="certDeleteOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>确认删除</DialogTitle>
                    <DialogDescription>
                        确定要删除证书「{{ certDeleteTarget?.name }}」吗？
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="certFormError" variant="destructive">
                    <AlertTitle>删除失败</AlertTitle>
                    <AlertDescription>{{ certFormError }}</AlertDescription>
                </Alert>

                <DialogFooter>
                    <Button variant="outline" @click="certDeleteOpen = false">取消</Button>
                    <Button variant="destructive" :disabled="certDeleting" @click="confirmCertDelete">
                        <Spinner v-if="certDeleting" data-icon="inline-start" />
                        <Trash2 v-else data-icon="inline-start" />
                        确认删除
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- Site detail dialog -->
        <ConsoleFormDialog
            v-model:open="detailOpen"
            title="站点详情"
            :loading="detailLoading"
            :error="detailError"
            save-label="关闭"
            @save="detailOpen = false"
            @cancel="detailOpen = false"
        >
            <template #footer>
                <Button variant="outline" @click="detailOpen = false">
                    关闭
                </Button>
            </template>

            <div v-if="detailLoading" class="flex justify-center py-8">
                <Spinner />
            </div>
            <div v-else-if="detailSite" class="grid gap-4">
                <div
                    v-for="field in detailFields"
                    :key="field.label"
                    class="grid grid-cols-[120px_1fr] gap-2"
                >
                    <Label class="text-muted-foreground">
                        {{ field.label }}
                    </Label>
                    <Badge
                        v-if="field.label === '状态'"
                        :variant="
                            field.value === '运行中'
                                ? 'secondary'
                                : 'destructive'
                        "
                        class="w-fit"
                    >
                        {{ field.value }}
                    </Badge>
                    <span v-else class="break-all">{{ field.value }}</span>
                </div>
            </div>
        </ConsoleFormDialog>
    </div>
</template>
