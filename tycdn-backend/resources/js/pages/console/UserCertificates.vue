<script setup lang="ts">
import {
    AlertCircle,
    CheckCircle2,
    XCircle,
    ChevronDown,
    Save,
    Search,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import CheckboxField from '@/components/ui/checkbox/CheckboxField.vue';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RadioGroup from '@/components/ui/radio-group/RadioGroup.vue';
import RadioGroupItem from '@/components/ui/radio-group/RadioGroupItem.vue';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { Spinner } from '@/components/ui/spinner';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import {
    createUserCert,
    deleteUserCert,
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
    getUserCert,
    listUserCerts,
    listUserDnsApis,
    updateUserCert,
    listUserConfigs,
    createUserConfig,
    updateUserConfig,
    deleteUserConfig,
} from '@/lib/cdnUserApi';
import type { CdnCertPayload, CdnflyRecord } from '@/lib/cdnUserApi';
import UserSites from './UserSites.vue';

const STATUS_ALL = 'all';

const loading = ref(false);
const saving = ref(false);
const certDetailsLoading = ref(false);
const certDetailsReady = ref(true);
const certDetailsRequest = ref(0);
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
    type: '',
    auto_renew: '',
    status: STATUS_ALL,
    per_page: '10',
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
const dialogTitle = computed(() =>
    editingCert.value ? '编辑证书' : '上传证书',
);
const certIsCustom = computed(() => certMode.value === 'single');
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
            params[searchField.value] = search;
        }

        if (filters.type) {
            params.type = filters.type;
        }

        if (filters.auto_renew) {
            params.auto_renew = filters.auto_renew;
        }

        if (filters.status !== STATUS_ALL) {
            params.enable = filters.status;
        }

        const result = await listUserCerts(params);
        const nextRows = extractCdnflyRows(result);

        certs.value = nextRows;
        selected.value = [];
        total.value = extractCdnflyTotal(result, nextRows.length);
        page.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function loadDnsApis(): Promise<void> {
    if (dnsApiOptions.value.length > 0) {
        return;
    }

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
    certDetailsRequest.value += 1;
    certDetailsLoading.value = false;
    editingCert.value = null;
    certDetailsReady.value = true;
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

async function openEditDialog(cert: CdnflyRecord): Promise<void> {
    editingCert.value = cert;
    certDetailsReady.value = false;
    fillCertForm(cert);
    formError.value = '';
    certDialogOpen.value = true;

    const id = asNumber(cert.id);

    if (!id) {
        formError.value = '证书 ID 缺失';

        return;
    }

    certDetailsLoading.value = true;
    const requestNumber = ++certDetailsRequest.value;

    try {
        const response = await getUserCert(id);
        const details = extractCdnflyRecord(response);

        if (!details) {
            throw new Error('证书详情为空');
        }

        // Ignore a late response if the user has already opened another record.
        if (
            certDetailsRequest.value !== requestNumber ||
            asNumber(editingCert.value?.id) !== id
        ) {
            return;
        }

        editingCert.value = { ...cert, ...details };
        fillCertForm(editingCert.value);
        certDetailsReady.value = true;

        if (form.type !== 'custom') {
            void loadDnsApis();
        }
    } catch (error) {
        if (certDetailsRequest.value === requestNumber) {
            formError.value = `读取证书详情失败：${getErrorMessage(error)}`;
        }
    } finally {
        if (certDetailsRequest.value === requestNumber) {
            certDetailsLoading.value = false;
        }
    }
}

function fillCertForm(cert: CdnflyRecord): void {
    form.name = textValue(cert.name);
    form.type = textValue(cert.type) || 'custom';
    form.domain = textValue(cert.domain);
    form.dnsapi = relatedId(cert.dnsapi);
    form.cert = textValue(cert.cert ?? cert.certificate ?? cert.pem);
    form.key = textValue(cert.key ?? cert.private_key);
    form.auto_renew = booleanFormValue(cert.auto_renew, '1');
    form.enable = booleanFormValue(cert.enable, '1');
    form.reissue = '0';
    form.des = textValue(cert.des ?? cert.remark);
    certMode.value = form.type === 'custom' ? 'single' : 'batch';
}

function relatedId(value: unknown): string {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return idField((value as CdnflyRecord).id);
    }

    return idField(value);
}

function booleanFormValue(value: unknown, fallback: '0' | '1'): '0' | '1' {
    if (value === undefined || value === null || value === '') {
        return fallback;
    }

    if (value === false || value === 0) {
        return '0';
    }

    const normalized = textValue(value).trim().toLowerCase();

    return ['0', 'false', 'disable', 'disabled', 'off'].includes(normalized)
        ? '0'
        : '1';
}

async function submitCert(): Promise<void> {
    if (editingCert.value && !certDetailsReady.value) {
        formError.value = '证书详情尚未加载完成，暂时无法保存';

        return;
    }

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

    if (!id) {
        return;
    }

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

    if (!id) {
        return;
    }

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

function certName(cert: CdnflyRecord): string {
    return textValue(cert.name ?? cert.domain) || `#${textValue(cert.id)}`;
}

function domainText(cert: CdnflyRecord): string {
    return textValue(cert.domain ?? cert.domains) || '-';
}

function formatDate(value: unknown): string {
    if (!value) {
        return '-';
    }

    return String(value).replace('T', ' ').slice(0, 19);
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

const activeTab = ref<'list' | 'defaults' | 'dnsapi'>('list');
const searchField = ref<'domain' | 'name' | 'id'>('domain');
const advanced = ref(false);
const selected = ref<number[]>([]);
const busy = ref(false);
const bulkDelete = ref(false);
const bulkError = ref('');
const selectedAll = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selected.value.includes(Number(row.id))),
);
const settingsLoading = ref(false),
    settingsSaving = ref(false),
    settingsReady = ref(false);
const settings = reactive({ cert_default_type: 'system', dnsapi: '' });
const savedSettings = reactive({ ...settings });
const settingsMessage = ref('');
async function switchTab(tab: 'list' | 'defaults' | 'dnsapi') {
    if (busy.value || settingsSaving.value) {
        return;
    }

    activeTab.value = tab;
    errorMessage.value = '';

    if (tab === 'defaults') {
        await loadDefaults();
    }
}
function toggleSelected(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((value) => value !== id)
        : [...selected.value, id];
}
function toggleAll() {
    selected.value = selectedAll.value
        ? []
        : rows.value.map((row) => Number(row.id));
}
function typeLabel(value: unknown): string {
    const type = textValue(value);

    return (
        (
            {
                custom: '自己上传',
                lets: "Let's Encrypt",
                zerossl: 'ZeroSSL',
                buypass: 'BuyPass',
            } as Record<string, string>
        )[type] ??
        (type || '—')
    );
}
function isOn(value: unknown): boolean {
    return value === true || value === 1 || value === '1';
}
function certStatus(row: CdnflyRecord): { text: string; tone: string } {
    if (row.enable !== undefined && !isOn(row.enable)) {
        return { text: '禁用', tone: 'muted' };
    }

    if (
        row.type !== 'custom' &&
        row.task_enable !== undefined &&
        !isOn(row.task_enable)
    ) {
        return { text: '签发失败，已取消', tone: 'error' };
    }

    if (
        row.type !== 'custom' &&
        row.issue_state &&
        row.issue_state !== 'done'
    ) {
        return {
            text: row.issue_state === 'failed' ? '签发失败，重试中' : '签发中',
            tone: row.issue_state === 'failed' ? 'error' : 'pending',
        };
    }

    if (row.sync_state && row.sync_state !== 'done') {
        return {
            text: row.sync_state === 'failed' ? '同步失败' : '同步中',
            tone: row.sync_state === 'failed' ? 'error' : 'pending',
        };
    }

    return { text: '正常', tone: 'success' };
}
type CertAction =
    | 'reissue'
    | 'enable'
    | 'disable'
    | 'renew'
    | 'no-renew'
    | 'delete';
async function runAction(action: CertAction, ids = [...selected.value]) {
    if (busy.value || !ids.length) {
        return;
    }

    busy.value = true;
    bulkError.value = '';
    const failures: number[] = [],
        messages: string[] = [];

    for (const id of ids) {
        try {
            if (action === 'delete') {
                await deleteUserCert(id);
            } else {
                await updateUserCert(
                    id,
                    action === 'reissue'
                        ? { reissue: 1 }
                        : action === 'renew' || action === 'no-renew'
                          ? { auto_renew: action === 'renew' ? 1 : 0 }
                          : { enable: action === 'enable' ? 1 : 0 },
                );
            }
        } catch (error) {
            failures.push(id);
            messages.push(`#${id}: ${getErrorMessage(error)}`);
        }
    }

    await loadCerts();
    selected.value = failures;
    busy.value = false;

    if (failures.length) {
        bulkError.value = messages.join('；');
        errorMessage.value = bulkError.value;
    } else {
        bulkDelete.value = false;
        toast.success(action === 'reissue' ? '重签申请已提交' : '操作成功');
    }
}
async function loadDefaults() {
    settingsLoading.value = true;
    settingsReady.value = false;
    settingsMessage.value = '';
    errorMessage.value = '';

    try {
        const [configs, dns] = await Promise.all([
            listUserConfigs({ type: 'cert', limit: 0 }),
            listUserDnsApis({ limit: 0 }),
        ]);
        const records = extractCdnflyRows(configs).filter(
            (row) => !row.scope_name || row.scope_name === 'global',
        );
        settings.cert_default_type =
            textValue(
                records.find((row) => row.name === 'cert_default_type')?.value,
            ) || 'system';
        settings.dnsapi = textValue(
            records.find((row) => row.name === 'dnsapi')?.value,
        );
        Object.assign(savedSettings, settings);
        dnsApiOptions.value = extractCdnflyRows(dns).map((row) => ({
            id: Number(row.id),
            name: textValue(row.name),
        }));
        settingsReady.value = true;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        settingsLoading.value = false;
    }
}
async function saveDefault(name: 'cert_default_type' | 'dnsapi') {
    if (!settingsReady.value || settingsSaving.value) {
        return;
    }

    settingsSaving.value = true;
    settingsMessage.value = '';
    errorMessage.value = '';
    const value = settings[name];

    try {
        const result = await listUserConfigs({ type: 'cert', name, limit: 0 });
        const record = extractCdnflyRows(result).find(
            (row) =>
                (!row.scope_name || row.scope_name === 'global') &&
                row.name === name,
        );
        const id = Number(record?.id);

        if (
            value === '' ||
            (name === 'cert_default_type' && value === 'system')
        ) {
            if (id > 0) {
                await deleteUserConfig(id);
            }
        } else {
            const payload = {
                type: 'cert',
                name,
                value,
                scope_name: 'global',
                scope_id: 0,
            };

            if (id > 0) {
                await updateUserConfig(id, payload);
            } else {
                await createUserConfig(payload);
            }
        }

        savedSettings[name] = value;
        settingsMessage.value = '已保存';
    } catch (error) {
        settings[name] = savedSettings[name];
        errorMessage.value = getErrorMessage(error);
    } finally {
        settingsSaving.value = false;
    }
}
</script>

<template>
    <div class="console-page user-certificates min-w-0 p-4 md:p-6">
        <section class="cert-workspace bg-card text-card-foreground">
            <div class="cert-tabs" role="tablist" aria-label="证书管理">
                <button
                    v-for="tab in [
                        { key: 'list', label: '证书列表' },
                        { key: 'defaults', label: '默认设置' },
                        { key: 'dnsapi', label: 'DNS API' },
                    ] as const"
                    :key="tab.key"
                    :id="`cert-tab-${tab.key}`"
                    role="tab"
                    :aria-selected="activeTab === tab.key"
                    aria-controls="cert-panel"
                    :disabled="
                        busy || loading || settingsLoading || settingsSaving
                    "
                    @click="switchTab(tab.key)"
                >
                    {{ tab.label }}
                </button>
            </div>
            <Alert v-if="errorMessage" variant="destructive" class="mb-4"
                ><AlertCircle /><AlertTitle>请求失败</AlertTitle
                ><AlertDescription
                    >{{ errorMessage
                    }}<Button
                        variant="link"
                        @click="
                            activeTab === 'defaults'
                                ? loadDefaults()
                                : loadCerts()
                        "
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <div
                id="cert-panel"
                role="tabpanel"
                :aria-labelledby="`cert-tab-${activeTab}`"
            >
                <template v-if="activeTab === 'list'">
                    <div class="cert-toolbar">
                        <Button @click="openCreateDialog">添加证书</Button
                        ><Button
                            variant="outline"
                            :disabled="!selected.length || busy"
                            @click="runAction('reissue')"
                            >重新申请</Button
                        >
                        <DropdownMenu
                            ><DropdownMenuTrigger as-child
                                ><Button
                                    variant="outline"
                                    :disabled="!selected.length || busy"
                                    >更多操作<ChevronDown
                                        class="size-4" /></Button></DropdownMenuTrigger
                            ><DropdownMenuContent
                                ><DropdownMenuItem @select="runAction('enable')"
                                    >启用</DropdownMenuItem
                                ><DropdownMenuItem
                                    @select="runAction('disable')"
                                    >禁用</DropdownMenuItem
                                ><DropdownMenuItem
                                    @select="
                                        bulkError = '';
                                        bulkDelete = true;
                                    "
                                    >删除</DropdownMenuItem
                                ><DropdownMenuItem @select="runAction('renew')"
                                    >开启续签</DropdownMenuItem
                                ><DropdownMenuItem
                                    @select="runAction('no-renew')"
                                    >关闭续签</DropdownMenuItem
                                ></DropdownMenuContent
                            ></DropdownMenu
                        >
                        <form
                            data-slot="console-input-group"
                            class="cert-search"
                            @submit.prevent="submitSearch"
                        >
                            <SelectField
                                v-model="searchField"
                                aria-label="搜索类型"
                            >
                                <SelectOption value="domain">域名</SelectOption>
                                <SelectOption value="name">名称</SelectOption>
                                <SelectOption value="id"
                                    >ID</SelectOption
                                ></SelectField
                            ><Input
                                v-model="filters.search"
                                aria-label="证书搜索"
                                :placeholder="
                                    searchField === 'domain'
                                        ? '输入域名,模糊搜索'
                                        : searchField === 'name'
                                          ? '输入名称,模糊搜索'
                                          : '输入证书ID'
                                "
                            /><Button
                                variant="ghost"
                                type="submit"
                                aria-label="查询"
                                :disabled="loading"
                                ><Search class="size-4"
                            /></Button>
                        </form>
                        <Button
                            variant="link"
                            :aria-expanded="advanced"
                            @click="advanced = !advanced"
                            >高级搜索</Button
                        >
                    </div>
                    <form
                        v-if="advanced"
                        class="advanced-cert"
                        aria-label="高级搜索"
                        @submit.prevent="submitSearch"
                    >
                        <label
                            >类型<SelectField v-model="filters.type">
                                <SelectOption value="">所有类型</SelectOption>
                                <SelectOption value="custom"
                                    >自己上传</SelectOption
                                >
                                <SelectOption value="lets"
                                    >Let's Encrypt</SelectOption
                                >
                                <SelectOption value="zerossl"
                                    >ZeroSSL</SelectOption
                                >
                                <SelectOption value="buypass"
                                    >BuyPass</SelectOption
                                >
                            </SelectField></label
                        ><label
                            >启用状态<SelectField v-model="filters.status">
                                <SelectOption value="all"
                                    >全部状态</SelectOption
                                >
                                <SelectOption value="1">启用</SelectOption>
                                <SelectOption value="0">禁用</SelectOption>
                            </SelectField></label
                        ><label
                            >自动续签<SelectField v-model="filters.auto_renew">
                                <SelectOption value="">全部</SelectOption>
                                <SelectOption value="1">已开启</SelectOption>
                                <SelectOption value="0">已关闭</SelectOption>
                            </SelectField></label
                        ><Button type="submit" :disabled="loading">查询</Button
                        ><Button
                            type="button"
                            variant="link"
                            @click="
                                Object.assign(filters, {
                                    search: '',
                                    status: STATUS_ALL,
                                    type: '',
                                    auto_renew: '',
                                });
                                submitSearch();
                            "
                            >清除</Button
                        >
                    </form>
                    <div class="cert-table-scroll" :aria-busy="loading">
                        <table class="cert-table">
                            <colgroup>
                                <col style="width: 60px" />
                                <col style="width: 85px" />
                                <col style="width: 240px" />
                                <col style="width: 175px" />
                                <col style="width: 200px" />
                                <col style="width: 225px" />
                                <col style="width: 225px" />
                                <col style="width: 210px" />
                                <col style="width: 210px" />
                                <col style="width: 170px" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>
                                        <CheckboxField
                                            aria-label="选择当前页"
                                            :checked="selectedAll"
                                            :disabled="
                                                loading || busy || !rows.length
                                            "
                                            @change="toggleAll"
                                        />
                                    </th>
                                    <th>ID</th>
                                    <th>名称</th>
                                    <th>类型</th>
                                    <th>域名</th>
                                    <th>创建时间</th>
                                    <th>到期时间</th>
                                    <th>自动续签</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loading">
                                    <td colspan="10" class="empty">
                                        <Spinner class="mx-auto" />
                                    </td>
                                </tr>
                                <template v-else
                                    ><tr
                                        v-for="cert in rows"
                                        :key="textValue(cert.id)"
                                    >
                                        <td>
                                            <CheckboxField
                                                :aria-label="`选择 ${cert.id}`"
                                                :checked="
                                                    selected.includes(
                                                        Number(cert.id),
                                                    )
                                                "
                                                :disabled="busy"
                                                @change="
                                                    toggleSelected(
                                                        Number(cert.id),
                                                    )
                                                "
                                            />
                                        </td>
                                        <td>{{ cert.id }}</td>
                                        <td>
                                            <Button
                                                variant="link"
                                                class="cert-name"
                                                :title="certName(cert)"
                                                @click="openEditDialog(cert)"
                                                >{{ certName(cert) }}</Button
                                            >
                                        </td>
                                        <td>{{ typeLabel(cert.type) }}</td>
                                        <td>
                                            <span
                                                class="block truncate"
                                                :title="domainText(cert)"
                                                >{{ domainText(cert) }}</span
                                            >
                                        </td>
                                        <td>
                                            {{
                                                formatDate(
                                                    cert.create_at2 ??
                                                        cert.create_at,
                                                )
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                formatDate(
                                                    cert.expire_time2 ??
                                                        cert.expire_time,
                                                )
                                            }}
                                        </td>
                                        <td>
                                            <CheckCircle2
                                                v-if="isOn(cert.auto_renew)"
                                                class="size-4 text-emerald-500"
                                                aria-label="续签已开启"
                                            /><XCircle
                                                v-else
                                                class="size-4 text-muted-foreground"
                                                aria-label="续签已关闭"
                                            />
                                        </td>
                                        <td>
                                            <span
                                                class="cert-status"
                                                :data-tone="
                                                    certStatus(cert).tone
                                                "
                                                :title="
                                                    textValue(cert.task_ret)
                                                "
                                                ><i />{{
                                                    certStatus(cert).text
                                                }}</span
                                            >
                                        </td>
                                        <td>
                                            <div class="cert-actions">
                                                <Button
                                                    variant="link"
                                                    @click="
                                                        openEditDialog(cert)
                                                    "
                                                    >管理</Button
                                                ><DropdownMenu
                                                    ><DropdownMenuTrigger
                                                        as-child
                                                        ><Button
                                                            variant="link"
                                                            :disabled="busy"
                                                            >更多<ChevronDown
                                                                class="size-4" /></Button></DropdownMenuTrigger
                                                    ><DropdownMenuContent
                                                        ><DropdownMenuItem
                                                            @select="
                                                                runAction(
                                                                    'reissue',
                                                                    [
                                                                        Number(
                                                                            cert.id,
                                                                        ),
                                                                    ],
                                                                )
                                                            "
                                                            >重新申请</DropdownMenuItem
                                                        ><DropdownMenuItem
                                                            @select="
                                                                toggleCertEnabled(
                                                                    cert,
                                                                    false,
                                                                )
                                                            "
                                                            >禁用</DropdownMenuItem
                                                        ><DropdownMenuItem
                                                            @select="
                                                                toggleCertEnabled(
                                                                    cert,
                                                                    true,
                                                                )
                                                            "
                                                            >启用</DropdownMenuItem
                                                        ><DropdownMenuItem
                                                            @select="
                                                                openDeleteCert(
                                                                    cert,
                                                                )
                                                            "
                                                            >删除</DropdownMenuItem
                                                        ></DropdownMenuContent
                                                    ></DropdownMenu
                                                >
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!rows.length">
                                        <td colspan="10" class="empty">
                                            暂无数据
                                        </td>
                                    </tr></template
                                >
                            </tbody>
                        </table>
                    </div>
                    <PackagePagination
                        class="cert-pagination"
                        :page="page"
                        :page-size="Number(filters.per_page)"
                        :total="total"
                        numbered
                        edge-links
                        :disabled="loading || busy"
                        @update:page="loadCerts($event)"
                        @update:page-size="
                            filters.per_page = String($event);
                            loadCerts(1);
                        "
                    />
                </template>
                <div
                    v-else-if="activeTab === 'defaults'"
                    class="cert-defaults"
                    :aria-busy="settingsLoading || settingsSaving"
                >
                    <Spinner v-if="settingsLoading" />
                    <fieldset :disabled="!settingsReady || settingsSaving">
                        <legend class="sr-only">证书默认设置</legend>
                        <div class="default-row">
                            <span>证书类型</span>
                            <RadioGroup
                                v-model="settings.cert_default_type"
                                aria-label="证书类型"
                                @update:model-value="
                                    saveDefault('cert_default_type')
                                "
                                name="default-cert-type"
                                class="default-radios"
                            >
                                <label
                                    v-for="option in [
                                        {
                                            value: 'system',
                                            label: '系统默认设置',
                                        },
                                        {
                                            value: 'zerossl',
                                            label: 'ZeroSSL(推荐)',
                                        },
                                        {
                                            value: 'lets',
                                            label: `Let's Encrypt`,
                                        },
                                        { value: 'buypass', label: 'BuyPass' },
                                    ]"
                                    :key="option.value"
                                    ><RadioGroupItem :value="option.value" />{{
                                        option.label
                                    }}</label
                                >
                            </RadioGroup>
                        </div>
                        <div class="default-row">
                            <label for="default-cert-dns">DNS API</label>
                            <div class="default-dns">
                                <SelectField
                                    id="default-cert-dns"
                                    v-model="settings.dnsapi"
                                    @change="saveDefault('dnsapi')"
                                >
                                    <SelectOption value="">请选择</SelectOption>
                                    <SelectOption
                                        v-for="option in dnsApiOptions"
                                        :key="option.id"
                                        :value="String(option.id)"
                                    >
                                        {{ option.name }}
                                    </SelectOption>
                                </SelectField>
                                <p>
                                    设置后，在网站列表一键申请证书时将使用此DNS
                                    API申请证书。
                                </p>
                            </div>
                        </div>
                    </fieldset>
                    <p class="text-sm text-muted-foreground" role="status">
                        {{ settingsSaving ? '保存中…' : settingsMessage }}
                    </p>
                </div>
                <UserSites v-else initial-tab="dnsapi" embedded />
            </div>
        </section>
        <ConfirmDeleteDialog
            :open="bulkDelete"
            :description="`确认删除所选 ${selected.length} 张证书？删除后不可恢复。`"
            :loading="busy"
            :error="bulkError"
            @confirm="runAction('delete')"
            @cancel="bulkDelete = false"
        />

        <Dialog v-model:open="certDialogOpen">
            <DialogScrollContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>
                        编辑时会通过安全接口读取完整证书内容和私钥。
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-5" @submit.prevent="submitCert">
                    <Alert v-if="formError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>提交失败</AlertTitle>
                        <AlertDescription>{{ formError }}</AlertDescription>
                    </Alert>

                    <div
                        v-if="certDetailsLoading"
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Spinner />正在读取证书详情…
                    </div>

                    <div class="grid gap-4">
                        <!-- 模式切换 -->
                        <div
                            v-if="!editingCert"
                            class="flex rounded-lg border p-1"
                        >
                            <button
                                type="button"
                                class="flex-1 rounded-md px-3 py-1.5 text-center text-sm font-medium transition-colors"
                                :class="
                                    certMode === 'single'
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="switchCertMode('single')"
                            >
                                单个上传
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-md px-3 py-1.5 text-center text-sm font-medium transition-colors"
                                :class="
                                    certMode === 'batch'
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
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
                                <Textarea
                                    id="cert-body"
                                    v-model="form.cert"
                                    class="min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    placeholder="-----BEGIN CERTIFICATE-----"
                                    spellcheck="false"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="cert-key">私钥 (KEY)</Label>
                                <Textarea
                                    id="cert-key"
                                    v-model="form.key"
                                    class="min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    placeholder="-----BEGIN PRIVATE KEY-----"
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
                                        <SelectTrigger
                                            ><SelectValue
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="lets"
                                                    >Let's Encrypt</SelectItem
                                                >
                                                <SelectItem value="zerossl"
                                                    >ZeroSSL</SelectItem
                                                >
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
                                        <SelectTrigger
                                            ><SelectValue
                                                placeholder="选择 DNS API"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem
                                                    v-for="api in dnsApiOptions"
                                                    :key="api.id"
                                                    :value="String(api.id)"
                                                >
                                                    {{ api.name }} (#{{
                                                        api.id
                                                    }})
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label>自动续期</Label>
                                    <Select v-model="form.auto_renew">
                                        <SelectTrigger
                                            ><SelectValue
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="1"
                                                    >开启</SelectItem
                                                >
                                                <SelectItem value="0"
                                                    >关闭</SelectItem
                                                >
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </template>

                        <!-- 编辑时显示启用/重签 -->
                        <div
                            v-if="editingCert"
                            class="grid gap-4 md:grid-cols-2"
                        >
                            <div class="grid gap-2">
                                <Label>启用</Label>
                                <Select v-model="form.enable">
                                    <SelectTrigger
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="1"
                                                >启用</SelectItem
                                            >
                                            <SelectItem value="0"
                                                >禁用</SelectItem
                                            >
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div v-if="!certIsCustom" class="grid gap-2">
                                <Label>重签</Label>
                                <Select v-model="form.reissue">
                                    <SelectTrigger
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="0"
                                                >不重签</SelectItem
                                            >
                                            <SelectItem value="1"
                                                >重签</SelectItem
                                            >
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="cert-des">备注</Label>
                            <Input
                                id="cert-des"
                                v-model="form.des"
                                placeholder="可选备注"
                            />
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
                        <Button
                            :disabled="
                                saving ||
                                certDetailsLoading ||
                                (Boolean(editingCert) && !certDetailsReady)
                            "
                            type="submit"
                        >
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

<style scoped>
.cert-workspace {
    padding: 16px;
    min-width: 0;
}
.cert-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border);
}
.cert-tabs button {
    margin-bottom: -1px;
    padding: 12px 20px;
    border-bottom: 2px solid transparent;
    font-size: 16px;
    color: var(--muted-foreground);
}
.cert-tabs button[aria-selected='true'] {
    color: var(--primary);
    border-color: var(--primary);
}
.cert-toolbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
}
.cert-toolbar button,
.cert-toolbar input,
.cert-toolbar :deep([data-slot='select-trigger']) {
    height: 40px;
    font-size: 16px;
}
.cert-search {
    display: flex;
    max-width: 100%;
    min-width: 0;
    border: 1px solid var(--border);
    border-radius: 4px;
}
.cert-search :deep([data-slot='select-trigger']) {
    width: 75px;
    flex-shrink: 0;
    padding: 0 10px;
    background: var(--muted);
    border-radius: 4px 0 0 4px;
}
.cert-search input {
    width: 240px;
    min-width: 0;
    border: 0;
    border-left: 1px solid var(--border);
    border-radius: 0;
    box-shadow: none;
}
.cert-search button {
    padding: 0 10px;
}
.advanced-cert {
    display: flex;
    flex-wrap: wrap;
    align-items: end;
    gap: 12px;
    margin-bottom: 18px;
    padding: 16px;
    background: var(--muted);
    border: 1px solid var(--border);
}
.advanced-cert label {
    display: grid;
    gap: 6px;
}
.advanced-cert :deep([data-slot='select-trigger']) {
    height: 36px;
    padding: 0 12px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 4px;
}
.cert-table-scroll {
    overflow-x: auto;
    max-width: 100%;
    scrollbar-width: auto;
    scrollbar-color: #909090 var(--muted);
}
.cert-table {
    width: 100%;
    min-width: 1800px;
    table-layout: fixed;
    text-align: left;
    font-size: 16px;
}
.cert-table th {
    height: 48px;
    padding: 10px 18px;
    color: var(--muted-foreground);
    background: var(--muted);
    font-weight: 600;
}
.cert-table td {
    height: 60px;
    padding: 6px 18px;
    border-bottom: 1px solid var(--border);
}
.cert-table tbody tr:hover {
    background: color-mix(in srgb, var(--muted) 40%, transparent);
}
.cert-table :deep([data-slot='checkbox']) {
    width: 19px;
    height: 19px;
    accent-color: var(--primary);
    vertical-align: middle;
}
.cert-name {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    white-space: normal;
    height: auto;
    padding: 0;
    text-align: left;
    line-height: 24px;
    font-size: 16px;
    overflow-wrap: anywhere;
}
.cert-actions {
    display: flex;
    align-items: center;
    gap: 14px;
    white-space: nowrap;
}
.cert-actions button {
    padding: 0;
    height: auto;
    font-size: 16px;
}
.cert-status {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    font-size: 14px;
}
.cert-status i {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
    border-radius: 50%;
    background: #19be6b;
}
.cert-status[data-tone='muted'] i {
    background: #9ca3af;
}
.cert-status[data-tone='error'] i {
    background: #f43f5e;
}
.cert-status[data-tone='pending'] i {
    background: #f59e0b;
}
.empty {
    text-align: center;
    color: var(--muted-foreground);
}
.cert-defaults {
    padding: 8px 4px 20px;
    min-height: 200px;
    font-size: 16px;
}
.default-row {
    display: flex;
    align-items: start;
    gap: 16px;
    margin-bottom: 40px;
}
.default-row > :first-child {
    width: 70px;
    flex-shrink: 0;
    padding-top: 8px;
}
.default-radios {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    padding-top: 8px;
}
.default-radios label {
    display: flex;
    align-items: center;
    gap: 6px;
}
.default-radios input {
    width: 19px;
    height: 19px;
    accent-color: var(--primary);
}
.default-dns {
    width: 500px;
    max-width: 100%;
    min-width: 0;
}
.default-dns :deep([data-slot='select-trigger']) {
    height: 40px;
    width: 100%;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 0 12px;
}
.default-dns p {
    margin-top: 16px;
    font-size: 14px;
    line-height: 1.6;
    color: var(--muted-foreground);
}
@media (max-width: 640px) {
    .cert-workspace {
        padding: 12px;
    }
    .cert-tabs {
        gap: 0;
    }
    .cert-tabs button {
        padding: 10px 12px;
        font-size: 14px;
    }
    .cert-search {
        width: 100%;
    }
    .cert-search input {
        width: 0;
        flex: 1;
    }
    .default-row {
        gap: 10px;
    }
}
</style>
