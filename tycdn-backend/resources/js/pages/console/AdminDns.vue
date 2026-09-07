<script setup lang="ts">
import {
    AlertCircle,
    Network,
    Pencil,
    Plus,
    Save,
    Trash2,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import Switch from '@/components/ui/switch/Switch.vue';
import {
    createAdminDnsApi,
    deleteAdminDnsApi,
    listAdminDnsApis,
    updateAdminDnsApi,
} from '@/lib/adminModulesApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import { formatDate, getErrorMessage } from '@/lib/formatters';

const AUTH_TEMPLATES: Record<string, Record<string, string>> = {
    CloudFlare: { CF_Key: '', CF_Email: '' },
    'DNSPod.cn': { DP_Id: '', DP_Key: '' },
    'GoDaddy.com': { GD_Key: '', GD_Secret: '' },
    Aliyun: { Ali_Key: '', Ali_Secret: '' },
    'cloudns.net': { CLOUDNS_SUB_AUTH_ID: '', CLOUDNS_AUTH_PASSWORD: '' },
    'Name.com': { Namecom_Username: '', Namecom_Token: '' },
    Namecheap: { NAMECHEAP_USERNAME: '', NAMECHEAP_API_KEY: '', NAMECHEAP_SOURCEIP: '' },
    'jdcloud.com': { JD_ACCESS_KEY_ID: '', JD_ACCESS_KEY_SECRET: '' },
    dnsdun: { Dnsdun_uid: '', Dnsdun_api_key: '' },
};

/** Friendly labels for each auth field key. Falls back to the raw key name. */
const AUTH_FIELD_LABELS: Record<string, string> = {
    CF_Key: 'API Key',
    CF_Email: '账号邮箱',
    DP_Id: 'ID',
    DP_Key: 'API Key',
    GD_Key: 'API Key',
    GD_Secret: 'API Secret',
    Ali_Key: 'Access Key ID',
    Ali_Secret: 'Access Key Secret',
    CLOUDNS_SUB_AUTH_ID: 'Sub Auth ID',
    CLOUDNS_AUTH_PASSWORD: 'Auth Password',
    Namecom_Username: '用户名',
    Namecom_Token: 'API Token',
    NAMECHEAP_USERNAME: '用户名',
    NAMECHEAP_API_KEY: 'API Key',
    NAMECHEAP_SOURCEIP: '源 IP 地址',
    JD_ACCESS_KEY_ID: 'Access Key ID',
    JD_ACCESS_KEY_SECRET: 'Access Key Secret',
    Dnsdun_uid: '用户 ID',
    Dnsdun_api_key: 'API Key',
};

const dnsApiColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'name', label: '名称' },
    { key: 'type', label: '服务商', width: '120px' },
    {
        key: 'enable',
        label: '状态',
        width: '90px',
        badge: true,
        format: (v) => (v === 1 || v === '1' ? '可用' : '已停用'),
        badgeVariant: (v) => (v === 1 || v === '1' ? 'secondary' : 'destructive'),
    },
    {
        key: 'created_at',
        label: '创建时间',
        width: '160px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];


const dnsTableRef = ref<InstanceType<typeof ConsoleDataTable> | null>(null);
const saving = ref(false);
const togglingId = ref<number | null>(null);
const deletingId = ref<number | null>(null);
const errorMessage = ref('');
const formError = ref('');
const dialogOpen = ref(false);
const deleteOpen = ref(false);
const editingRecord = ref<CdnflyRecord | null>(null);
const deleteTarget = ref<CdnflyRecord | null>(null);

const form = reactive({
    name: '',
    type: 'CloudFlare',
    auth: { ...AUTH_TEMPLATES.CloudFlare } as Record<string, string>,
    des: '',
});

const dialogTitle = computed(() => (editingRecord.value ? '编辑 DNS API' : '新增 DNS API'));

/** Auth field keys for the currently selected provider. */
const authFieldKeys = computed(() => Object.keys(AUTH_TEMPLATES[form.type] ?? {}));

async function toggleDnsApiEnabled(row: CdnflyRecord, checked: boolean): Promise<void> {
    const id = Number(row.id);
    if (!id) return;
    togglingId.value = id;
    try {
        await updateAdminDnsApi(id, { enable: checked ? 1 : 0 });
        toast.success(checked ? '已启用' : '已停用');
        dnsTableRef.value?.refresh();
    } catch (error) {
        toast.error(getErrorMessage(error));
    } finally {
        togglingId.value = null;
    }
}

function openCreateDialog(): void {
    editingRecord.value = null;
    form.name = '';
    form.type = 'CloudFlare';
    form.auth = { ...AUTH_TEMPLATES.CloudFlare };
    form.des = '';
    formError.value = '';
    dialogOpen.value = true;
}

function openEditDialog(row: CdnflyRecord): void {
    editingRecord.value = row;
    form.name = String(row.name ?? '');
    form.type = String(row.type ?? 'CloudFlare');

    const rawAuth = typeof row.auth === 'string' ? tryParseJson(row.auth) : row.auth;
    const existing = (rawAuth && typeof rawAuth === 'object') ? rawAuth as Record<string, string> : {};
    const template = AUTH_TEMPLATES[form.type] ?? {};
    const merged: Record<string, string> = {};
    for (const key of Object.keys(template)) {
        merged[key] = String(existing[key] ?? '');
    }
    form.auth = merged;

    form.des = String(row.des ?? '');
    formError.value = '';
    dialogOpen.value = true;
}

function applyAuthTemplate(): void {
    form.auth = { ...(AUTH_TEMPLATES[form.type] ?? {}) };
}

function tryParseJson(v: string): unknown {
    try { return JSON.parse(v); } catch { return null; }
}

async function submitDnsApi(): Promise<void> {
    saving.value = true;
    formError.value = '';

    const payload = {
        name: form.name.trim(),
        type: form.type,
        auth: { ...form.auth },
        des: form.des.trim() || undefined,
    };

    try {
        if (editingRecord.value) {
            await updateAdminDnsApi(Number(editingRecord.value.id), payload);
            toast.success('DNS API 已更新');
        } else {
            await createAdminDnsApi(payload);
            toast.success('DNS API 已创建');
        }
        dialogOpen.value = false;
        dnsTableRef.value?.refresh();
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

function openDeleteConfirm(row: CdnflyRecord): void {
    deleteTarget.value = row;
    formError.value = '';
    deleteOpen.value = true;
}

async function confirmDelete(): Promise<void> {
    if (!deleteTarget.value) return;
    deletingId.value = Number(deleteTarget.value.id);
    formError.value = '';

    try {
        await deleteAdminDnsApi(Number(deleteTarget.value.id));
        deleteOpen.value = false;
        toast.success('DNS API 已删除');
        dnsTableRef.value?.refresh();
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        deletingId.value = null;
    }
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader title="DNS 管理" :icon="Network" :show-api-badge="false" />

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <ConsoleDataTable
            ref="dnsTableRef"
            title="DNS API"
            :icon="Network"
            :columns="dnsApiColumns"
            :fetch-fn="listAdminDnsApis"
            search-placeholder="搜索 DNS API"
        >
            <template #toolbar>
                <Button variant="default" size="sm" @click="openCreateDialog">
                    <Plus data-icon="inline-start" class="size-4" />
                    新增
                </Button>
            </template>
            <template #cell-enable="{ row }">
                <Switch
                    :checked="row.enable === 1 || row.enable === '1'"
                    :disabled="togglingId === Number(row.id)"
                    @update:checked="toggleDnsApiEnabled(row, $event)"
                />
            </template>
            <template #row-actions="{ row }">
                <Button variant="ghost" size="sm" @click="openEditDialog(row)">
                    <Pencil class="size-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="deletingId === Number(row.id)"
                    @click="openDeleteConfirm(row)"
                >
                    <Spinner v-if="deletingId === Number(row.id)" class="size-4" />
                    <Trash2 v-else class="size-4 text-destructive" />
                </Button>
            </template>
        </ConsoleDataTable>

        <!-- Create/Edit dialog -->
        <Dialog v-model:open="dialogOpen">
            <DialogScrollContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>管理 DNS 服务商凭据。</DialogDescription>
                </DialogHeader>

                <Alert v-if="formError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>提交失败</AlertTitle>
                    <AlertDescription>{{ formError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="dns-name">名称</Label>
                        <Input id="dns-name" v-model="form.name" placeholder="DNS API 名称" />
                    </div>
                    <div class="grid gap-2">
                        <Label>服务商</Label>
                        <Select v-model="form.type" @update:model-value="applyAuthTemplate">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem v-for="(tpl, key) in AUTH_TEMPLATES" :key="key" :value="key">
                                        {{ key }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>凭据</Label>
                        <div class="grid gap-3 rounded-md border border-input bg-muted/30 p-3">
                            <div v-for="fieldKey in authFieldKeys" :key="fieldKey" class="grid gap-1.5">
                                <Label :for="`dns-auth-${fieldKey}`" class="text-xs font-normal text-muted-foreground">
                                    {{ AUTH_FIELD_LABELS[fieldKey] ?? fieldKey }}
                                </Label>
                                <Input
                                    :id="`dns-auth-${fieldKey}`"
                                    v-model="form.auth[fieldKey]"
                                    :placeholder="fieldKey"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="dns-des">备注</Label>
                        <Input id="dns-des" v-model="form.des" placeholder="可选备注" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="dialogOpen = false">取消</Button>
                    <Button :disabled="saving" @click="submitDnsApi">
                        <Spinner v-if="saving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        保存
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- Delete confirm dialog -->
        <Dialog v-model:open="deleteOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>确认删除</DialogTitle>
                    <DialogDescription>
                        确定要删除 DNS API「{{ deleteTarget?.name }}」吗？
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteOpen = false">取消</Button>
                    <Button variant="destructive" :disabled="deletingId !== null" @click="confirmDelete">
                        <Spinner v-if="deletingId !== null" data-icon="inline-start" />
                        <Trash2 v-else data-icon="inline-start" />
                        确认删除
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
