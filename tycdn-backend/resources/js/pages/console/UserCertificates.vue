<script setup lang="ts">
import {
    AlertCircle,
    FileKey2,
    Pencil,
    Plus,
    RefreshCw,
    Save,
    Search,
    Trash2,
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
import Switch from '@/components/ui/switch/Switch.vue';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import {
    createUserCert,
    deleteUserCert,
    extractCdnflyRows,
    extractCdnflyTotal,
    listUserCerts,
    listUserDnsApis,
    updateUserCert,
} from '@/lib/cdnUserApi';
import type { CdnCertPayload, CdnflyRecord } from '@/lib/cdnUserApi';

const STATUS_ALL = 'all';

const loading = ref(false);
const saving = ref(false);
const togglingId = ref<number | null>(null);
const deleteOpen = ref(false);
const deleting = ref(false);
const deleteError = ref('');
const deleteTarget = ref<CdnflyRecord | null>(null);
const errorMessage = ref('');
const formError = ref('');
const certDialogOpen = ref(false);
const editingCert = ref<CdnflyRecord | null>(null);
const page = ref(1);
const total = ref(0);
const certs = ref<CdnflyRecord[]>([]);

const filters = reactive({
    search: '',
    status: STATUS_ALL,
    per_page: '20',
});

const certMode = ref<'single' | 'batch'>('single');

const form = reactive({
    name: '',
    type: 'custom',
    domain: '',
    dnsapi: '',
    cert: '',
    key: '',
    auto_renew: '1',
    enable: '1',
    reissue: '0',
    des: '',
});

const dnsApiOptions = ref<{ id: number; name: string }[]>([]);

const rows = computed(() => certs.value);
const dialogTitle = computed(() => (editingCert.value ? '编辑证书' : '上传证书'));
const certIsCustom = computed(() => certMode.value === 'single');
const hasPreviousPage = computed(() => page.value > 1);
const hasNextPage = computed(
    () => page.value * Number(filters.per_page) < total.value,
);
const paginationText = computed(() => {
    if (total.value === 0) {
        return '暂无证书';
    }

    return `${total.value} 张证书`;
});

onMounted(() => {
    void loadCerts();
});

async function loadCerts(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(filters.per_page),
        };
        const search = filters.search.trim();

        if (search !== '') {
            params.name = search;
        }

        if (filters.status !== STATUS_ALL) {
            params.enable = filters.status;
        }

        const result = await listUserCerts(params);
        const nextRows = extractCdnflyRows(result);

        certs.value = nextRows;
        total.value = extractCdnflyTotal(result, nextRows.length);
        page.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function loadDnsApis(): Promise<void> {
    if (dnsApiOptions.value.length > 0) return;
    try {
        const result = await listUserDnsApis({ limit: '500' });
        const rows = extractCdnflyRows(result);
        dnsApiOptions.value = rows.map((r) => ({
            id: Number(r.id),
            name: String(r.name ?? r.id),
        }));
    } catch {
        dnsApiOptions.value = [];
    }
}

function submitSearch(): void {
    page.value = 1;
    void loadCerts(1);
}

function openCreateDialog(): void {
    editingCert.value = null;
    resetForm();
    certMode.value = 'single';
    formError.value = '';
    certDialogOpen.value = true;
    void loadDnsApis();
}

function switchCertMode(mode: 'single' | 'batch'): void {
    certMode.value = mode;
    form.type = mode === 'single' ? 'custom' : 'lets';
}

function openEditDialog(cert: CdnflyRecord): void {
    editingCert.value = cert;
    form.name = textValue(cert.name);
    form.type = textValue(cert.type) || 'custom';
    form.domain = textValue(cert.domain);
    form.dnsapi = idField(cert.dnsapi);
    form.cert = '';
    form.key = '';
    form.auto_renew =
        cert.auto_renew === 0 || cert.auto_renew === false ? '0' : '1';
    form.enable = cert.enable === 0 || cert.enable === false ? '0' : '1';
    form.reissue = '0';
    form.des = textValue(cert.des ?? cert.remark);
    certMode.value = form.type === 'custom' ? 'single' : 'batch';
    formError.value = '';
    certDialogOpen.value = true;
    if (form.type !== 'custom') void loadDnsApis();
}

async function submitCert(): Promise<void> {
    const payload = buildPayload();

    if (payload.name.trim() === '') {
        formError.value = '证书名称不能为空';

        return;
    }

    if (!editingCert.value && payload.type === 'custom') {
        if (!payload.cert || !payload.key) {
            formError.value = 'custom 证书必须填写 cert 和 key';

            return;
        }
    }

    if (
        !editingCert.value &&
        (payload.type === 'lets' || payload.type === 'zerossl') &&
        !payload.domain
    ) {
        formError.value = 'lets/zerossl 证书必须填写 domain';

        return;
    }

    saving.value = true;
    formError.value = '';

    try {
        if (editingCert.value) {
            const id = asNumber(editingCert.value.id);

            if (!id) {
                formError.value = '证书 ID 缺失';

                return;
            }

            await updateUserCert(id, stripEmptySecretFields(payload));
            toast.success('证书更新请求已提交');
        } else {
            await createUserCert(payload);
            toast.success('证书上传请求已提交');
        }

        certDialogOpen.value = false;
        await loadCerts();
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

function openDeleteCert(cert: CdnflyRecord) {
    deleteTarget.value = cert;
    deleteError.value = '';
    deleteOpen.value = true;
}

async function confirmDeleteCert() {
    const id = asNumber(deleteTarget.value?.id);
    if (!id) return;
    deleting.value = true;
    try {
        await deleteUserCert(id);
        deleteOpen.value = false;
        toast.success('证书已删除');
        await loadCerts();
    } catch (error) {
        deleteError.value = getErrorMessage(error);
    } finally {
        deleting.value = false;
    }
}

async function toggleCertEnabled(cert: CdnflyRecord, checked: boolean) {
    const id = asNumber(cert.id);
    if (!id) return;
    togglingId.value = id;
    try {
        await updateUserCert(id, { enable: checked ? 1 : 0 });
        toast.success(checked ? '证书已启用' : '证书已停用');
        await loadCerts();
    } catch (error) {
        toast.error(getErrorMessage(error));
    } finally {
        togglingId.value = null;
    }
}

function buildPayload(): CdnCertPayload {
    return {
        name: form.name.trim(),
        type: form.type,
        domain: nullableText(form.domain),
        dnsapi: nullableNumber(form.dnsapi),
        cert: nullableText(form.cert),
        key: nullableText(form.key),
        auto_renew: form.auto_renew === '1' ? 1 : 0,
        enable: form.enable === '1' ? 1 : 0,
        reissue: form.reissue === '1' ? 1 : 0,
        des: nullableText(form.des),
    };
}

function stripEmptySecretFields(
    payload: CdnCertPayload,
): Partial<CdnCertPayload> {
    const next: Partial<CdnCertPayload> = { ...payload };

    if (!next.cert) {
        delete next.cert;
    }

    if (!next.key) {
        delete next.key;
    }

    return next;
}

function resetForm(): void {
    form.name = '';
    form.type = 'custom';
    form.domain = '';
    form.dnsapi = '';
    form.cert = '';
    form.key = '';
    form.auto_renew = '1';
    form.enable = '1';
    form.reissue = '0';
    form.des = '';
}

function nextPage(): void {
    if (hasNextPage.value) {
        void loadCerts(page.value + 1);
    }
}

function prevPage(): void {
    if (hasPreviousPage.value) {
        void loadCerts(page.value - 1);
    }
}

function certName(cert: CdnflyRecord): string {
    return textValue(cert.name ?? cert.domain) || `#${textValue(cert.id)}`;
}

function domainText(cert: CdnflyRecord): string {
    return textValue(cert.domain ?? cert.domains) || '-';
}

function statusLabel(value: unknown): string {
    if (value === 1 || value === '1' || value === 'normal') {
        return '正常';
    }

    if (value === 0 || value === '0' || value === false) {
        return '不可用';
    }

    return textValue(value) || '-';
}

function statusVariant(
    value: unknown,
): 'secondary' | 'outline' | 'destructive' {
    if (value === 1 || value === '1' || value === 'normal') {
        return 'secondary';
    }

    if (value === 0 || value === '0' || value === false) {
        return 'destructive';
    }

    return 'outline';
}

function formatDate(value: unknown): string {
    if (!value) {
        return '-';
    }

    return String(value).slice(0, 16);
}

function getErrorMessage(error: unknown): string {
    return error instanceof Error ? error.message : '请求失败';
}

function asNumber(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);

        return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
}

function nullableText(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function nullableNumber(value: string): number | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number(trimmed);

    return Number.isFinite(parsed) ? parsed : null;
}

function idField(value: unknown): string {
    const parsed = nullableNumber(textValue(value));

    return parsed === null ? '' : String(parsed);
}

function textValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="证书管理"
            :icon="FileKey2"
            :show-api-badge="false"
        />

        <Card class="gap-4">
            <CardContent class="pt-6">
                <div class="grid gap-3 xl:grid-cols-[1fr_160px_120px_auto]">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="filters.search"
                            class="pl-9"
                            placeholder="搜索证书名称、域名、ID"
                            @keyup.enter="submitSearch"
                        />
                    </div>
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="STATUS_ALL"
                                    >全部状态</SelectItem
                                >
                                <SelectItem value="1">正常</SelectItem>
                                <SelectItem value="0">不可用</SelectItem>
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
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <div class="flex flex-wrap gap-2">
                        <Button :disabled="loading" @click="submitSearch">
                            <Spinner v-if="loading" data-icon="inline-start" />
                            <Search v-else data-icon="inline-start" />
                            搜索
                        </Button>
                        <Button
                            variant="outline"
                            :disabled="loading"
                            @click="loadCerts()"
                        >
                            <RefreshCw data-icon="inline-start" />
                            刷新
                        </Button>
                        <Button @click="openCreateDialog">
                            <Plus data-icon="inline-start" />
                            上传证书
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>证书请求失败</AlertTitle>
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
                        <FileKey2 class="size-5" />
                    </div>
                    <CardTitle class="text-base">证书列表</CardTitle>
                </div>
                <div class="text-sm text-muted-foreground">
                    {{ paginationText }}
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] table-fixed text-sm">
                        <colgroup>
                            <col style="width: 22%" />
                            <col style="width: 28%" />
                            <col style="width: 10%" />
                            <col style="width: 14%" />
                            <col style="width: 12%" />
                            <col style="width: 14%" />
                        </colgroup>
                        <thead
                            class="border-y bg-muted/50 text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-2.5 text-left font-medium">
                                    证书
                                </th>
                                <th class="px-3 py-2.5 text-left font-medium">
                                    域名
                                </th>
                                <th class="px-2 py-2.5 text-center font-medium">
                                    状态
                                </th>
                                <th class="px-3 py-2.5 text-left font-medium">
                                    到期时间
                                </th>
                                <th class="px-3 py-2.5 text-left font-medium">
                                    创建时间
                                </th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    操作
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading && rows.length === 0">
                                <td class="px-6 py-16 text-center" colspan="6">
                                    <Spinner />
                                </td>
                            </tr>
                            <tr
                                v-for="cert in rows"
                                :key="textValue(cert.id) || certName(cert)"
                                class="border-b last:border-b-0"
                            >
                                <td class="px-4 py-3">
                                    <div class="truncate font-medium">
                                        {{ certName(cert) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        #{{ textValue(cert.id) || '-' }}
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="truncate">
                                        {{ domainText(cert) }}
                                    </div>
                                </td>
                                <td class="px-2 py-3 text-center">
                                    <Badge
                                    <Switch
                                        :checked="cert.enable === 1 || cert.enable === '1'"
                                        :disabled="togglingId === asNumber(cert.id)"
                                        @update:checked="toggleCertEnabled(cert, $event)"
                                    />
                                </td>
                                <td class="px-2 py-3 text-center" hidden>
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">
                                    {{
                                        formatDate(
                                            cert.expire_time ??
                                                cert.expire_time2 ??
                                                cert.expired_at ??
                                                cert.not_after,
                                        )
                                    }}
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">
                                    {{
                                        formatDate(
                                            cert.create_at2 ?? cert.created_at,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openEditDialog(cert)"
                                        >
                                            <Pencil data-icon="inline-start" />
                                            编辑
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            @click="openDeleteCert(cert)"
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
                                    colspan="6"
                                >
                                    暂无证书
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div class="flex items-center justify-end gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasPreviousPage || loading"
                @click="prevPage"
            >
                上一页
            </Button>
            <span class="text-sm text-muted-foreground">
                第 {{ page }} 页
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasNextPage || loading"
                @click="nextPage"
            >
                下一页
            </Button>
        </div>

        <Dialog v-model:open="certDialogOpen">
            <DialogScrollContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>
                        私钥通过安全通道提交，不在列表中展示。
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-5" @submit.prevent="submitCert">
                    <Alert v-if="formError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>提交失败</AlertTitle>
                        <AlertDescription>{{ formError }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-4">
                        <!-- 模式切换 -->
                        <div v-if="!editingCert" class="flex rounded-lg border p-1">
                            <button
                                type="button"
                                class="flex-1 rounded-md px-3 py-1.5 text-center text-sm font-medium transition-colors"
                                :class="certMode === 'single' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                @click="switchCertMode('single')"
                            >
                                单个上传
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-md px-3 py-1.5 text-center text-sm font-medium transition-colors"
                                :class="certMode === 'batch' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                @click="switchCertMode('batch')"
                            >
                                批量申请
                            </button>
                        </div>

                        <div class="grid gap-2">
                            <Label for="cert-name">证书名称</Label>
                            <Input
                                id="cert-name"
                                v-model="form.name"
                                autocomplete="off"
                                required
                            />
                        </div>

                        <!-- 自定义证书：私钥 + 证书 -->
                        <template v-if="certIsCustom">
                            <div class="grid gap-2">
                                <Label for="cert-body">证书 (CERT)</Label>
                                <textarea
                                    id="cert-body"
                                    v-model="form.cert"
                                    class="min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    :placeholder="editingCert ? '留空不修改' : '-----BEGIN CERTIFICATE-----'"
                                    spellcheck="false"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="cert-key">私钥 (KEY)</Label>
                                <textarea
                                    id="cert-key"
                                    v-model="form.key"
                                    class="min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    :placeholder="editingCert ? '留空不修改' : '-----BEGIN PRIVATE KEY-----'"
                                    spellcheck="false"
                                />
                            </div>
                        </template>

                        <!-- 自动申请：签发方式 + 域名 + DNS API -->
                        <template v-else>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label>签发方式</Label>
                                    <Select v-model="form.type">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="lets">Let's Encrypt</SelectItem>
                                                <SelectItem value="zerossl">ZeroSSL</SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="cert-domain">域名</Label>
                                    <Input
                                        id="cert-domain"
                                        v-model="form.domain"
                                        autocomplete="off"
                                        placeholder="example.com 或 *.example.com"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>DNS API</Label>
                                    <Select v-model="form.dnsapi">
                                        <SelectTrigger><SelectValue placeholder="选择 DNS API" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem
                                                    v-for="api in dnsApiOptions"
                                                    :key="api.id"
                                                    :value="String(api.id)"
                                                >
                                                    {{ api.name }} (#{{ api.id }})
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label>自动续期</Label>
                                    <Select v-model="form.auto_renew">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="1">开启</SelectItem>
                                                <SelectItem value="0">关闭</SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </template>

                        <!-- 编辑时显示启用/重签 -->
                        <div v-if="editingCert" class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label>启用</Label>
                                <Select v-model="form.enable">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="1">启用</SelectItem>
                                            <SelectItem value="0">禁用</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div v-if="!certIsCustom" class="grid gap-2">
                                <Label>重签</Label>
                                <Select v-model="form.reissue">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="0">不重签</SelectItem>
                                            <SelectItem value="1">重签</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="cert-des">备注</Label>
                            <Input id="cert-des" v-model="form.des" placeholder="可选备注" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            @click="certDialogOpen = false"
                        >
                            取消
                        </Button>
                        <Button :disabled="saving" type="submit">
                            <Spinner v-if="saving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            保存
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <ConfirmDeleteDialog
            :open="deleteOpen"
            :description="`确认删除证书「${deleteTarget ? certName(deleteTarget) : ''}」？`"
            :loading="deleting"
            :error="deleteError"
            @confirm="confirmDeleteCert"
            @cancel="deleteOpen = false"
        />
    </div>
</template>
