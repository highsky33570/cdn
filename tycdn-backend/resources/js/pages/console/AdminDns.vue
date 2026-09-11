<script setup lang="ts">
import {
    AlertCircle,
    Globe,
    Settings2,
    ShieldCheck,
    Network,
    Pencil,
    Plus,
    Save,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import type { AdminDnsSetting } from '@/lib/adminModulesApi';
import {
    createAdminCnameDomain,
    createAdminDnsApi,
    deleteAdminCnameDomain,
    deleteAdminDnsApi,
    getAdminDnsSetting,
    listAdminCnameDomains,
    listAdminDnsApis,
    saveAdminDnsSetting,
    updateAdminCnameDomain,
    updateAdminDnsApi,
} from '@/lib/adminModulesApi';
import { getErrorMessage } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const AUTH_TEMPLATES: Record<string, Record<string, string>> = {
    CloudFlare: { CF_Key: '', CF_Email: '' },
    'DNSPod.cn': { DP_Id: '', DP_Key: '' },
    'GoDaddy.com': { GD_Key: '', GD_Secret: '' },
    Aliyun: { Ali_Key: '', Ali_Secret: '' },
    'cloudns.net': { CLOUDNS_SUB_AUTH_ID: '', CLOUDNS_AUTH_PASSWORD: '' },
    'Name.com': { Namecom_Username: '', Namecom_Token: '' },
    Namecheap: {
        NAMECHEAP_USERNAME: '',
        NAMECHEAP_API_KEY: '',
        NAMECHEAP_SOURCEIP: '',
    },
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
    { key: 'type', label: '服务商', width: '140px' },
    { key: 'des', label: '备注' },
];

const dnsTableRef = ref<InstanceType<typeof ConsoleDataTable> | null>(null);
const saving = ref(false);
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

const dialogTitle = computed(() =>
    editingRecord.value ? '编辑 DNS API' : '新增 DNS API',
);

/** Auth field keys for the currently selected provider. */
const authFieldKeys = computed(() =>
    Object.keys(AUTH_TEMPLATES[form.type] ?? {}),
);

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

    const rawAuth =
        typeof row.auth === 'string' ? tryParseJson(row.auth) : row.auth;
    const existing =
        rawAuth && typeof rawAuth === 'object'
            ? (rawAuth as Record<string, string>)
            : {};
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
    try {
        return JSON.parse(v);
    } catch {
        return null;
    }
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
    if (!deleteTarget.value) {
        return;
    }

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
// ─── CNAME 域名 ───────────────────────────────────────
/**
 * The zone customer CNAMEs resolve into.
 *
 * This is not cosmetic: the master refuses to generate its DNS line list until
 * a CNAME domain exists, so 线路分配 stays empty and no node is reachable until
 * one is added here. The master's own panel keeps it beside the DNS provider
 * credentials for that reason, and so do we.
 *
 * The record is only {id, domain, des} — verified against the master panel
 * (chunk-45f4d7f2, component "cnameDomain").
 */
type DnsTab = 'setting' | 'cnames' | 'apis';

const activeTab = ref<DnsTab>('setting');

const dnsTabs = [
    { key: 'setting' as const, label: 'DNS 设置', icon: Settings2 },
    { key: 'cnames' as const, label: 'CNAME 域名', icon: Globe },
    { key: 'apis' as const, label: '证书 DNS API', icon: ShieldCheck },
];

const cnameColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'domain', label: '域名' },
    { key: 'des', label: '备注' },
];

const cnameTableRef = ref<InstanceType<typeof ConsoleDataTable> | null>(null);
const cnameDialogOpen = ref(false);
const cnameDeleteOpen = ref(false);
const cnameSaving = ref(false);
const cnameDeleting = ref(false);
const cnameFormError = ref('');
const editingCname = ref<CdnflyRecord | null>(null);
const cnameDeleteTarget = ref<CdnflyRecord | null>(null);

const cnameForm = reactive({ domain: '', des: '' });

const cnameDialogTitle = computed(() =>
    editingCname.value ? '编辑 CNAME 域名' : '新增 CNAME 域名',
);

/** The table sends `search`; this endpoint filters on `domain`. */
function fetchCnameDomains(params: Record<string, string | number>) {
    const { search, ...rest } = params;

    return listAdminCnameDomains(search ? { ...rest, domain: search } : rest);
}

function openCnameCreate(): void {
    editingCname.value = null;
    cnameForm.domain = '';
    cnameForm.des = '';
    cnameFormError.value = '';
    cnameDialogOpen.value = true;
}

function openCnameEdit(row: CdnflyRecord): void {
    editingCname.value = row;
    cnameForm.domain = String(row.domain ?? '');
    cnameForm.des = String(row.des ?? '');
    cnameFormError.value = '';
    cnameDialogOpen.value = true;
}

async function submitCnameDomain(): Promise<void> {
    const domain = cnameForm.domain.trim();

    if (domain === '') {
        cnameFormError.value = '请填写域名';

        return;
    }

    cnameSaving.value = true;
    cnameFormError.value = '';

    const payload = { domain, des: cnameForm.des.trim() };

    try {
        if (editingCname.value) {
            await updateAdminCnameDomain(
                Number(editingCname.value.id),
                payload,
            );
            toast.success('CNAME 域名已更新');
        } else {
            await createAdminCnameDomain(payload);
            toast.success('CNAME 域名已创建');
        }

        cnameDialogOpen.value = false;
        cnameTableRef.value?.refresh();
    } catch (error) {
        cnameFormError.value = getErrorMessage(error);
    } finally {
        cnameSaving.value = false;
    }
}

function openCnameDelete(row: CdnflyRecord): void {
    cnameDeleteTarget.value = row;
    cnameFormError.value = '';
    cnameDeleteOpen.value = true;
}

async function confirmCnameDelete(): Promise<void> {
    if (!cnameDeleteTarget.value) {
        return;
    }

    cnameDeleting.value = true;
    cnameFormError.value = '';

    try {
        await deleteAdminCnameDomain(Number(cnameDeleteTarget.value.id));
        cnameDeleteOpen.value = false;
        toast.success('CNAME 域名已删除');
        cnameTableRef.value?.refresh();
    } catch (error) {
        cnameFormError.value = getErrorMessage(error);
    } finally {
        cnameDeleting.value = false;
    }
}
// ─── 全局 DNS 设置 ────────────────────────────────────
/**
 * The master's own DNS resolution settings — what it means by 请先设置DNS.
 *
 * This is the prerequisite for everything else on this page: without it the
 * master refuses to accept a CNAME domain and never generates a DNS line list,
 * so nodes register successfully but are never resolvable.
 *
 * Deliberately NOT the same thing as the 证书 DNS API tab, which holds per-user
 * ACME credentials for issuing certificates. The two take similar-looking
 * credentials for the same providers, which is exactly why they are easy to
 * confuse — the master keeps them on separate pages for that reason.
 *
 * Provider list and payload shape copied from the master panel
 * (chunk-45f4d7f2, component "dns-setting").
 */
const DNS_PROVIDERS = [
    {
        value: 'aliyun',
        label: '阿里云',
        domain: 'aliyun.com / alibabacloud.com',
    },
    { value: 'huaweicloud', label: '华为云', domain: 'huaweicloud.com' },
    { value: 'dns_la', label: 'DNSLA', domain: 'dns.la' },
    { value: 'dnspod_cn', label: 'DNSPod', domain: 'dnspod.cn' },
    { value: 'dnspod_com', label: 'DNSPod 国际版', domain: 'dnspod.com' },
    { value: 'dnsdotcom', label: '帝恩思', domain: '51dns.com' },
    { value: 'cloudflare', label: 'Cloudflare', domain: 'cloudflare.com' },
];

/** Each provider labels its two credential fields differently. */
const PROVIDER_FIELD_LABELS: Record<string, { id: string; token: string }> = {
    aliyun: { id: 'AccessKey ID', token: 'AccessKey Secret' },
    huaweicloud: { id: 'Access Key Id', token: 'Secret Access Key' },
    dns_la: { id: 'APIID', token: 'API 密钥' },
    dnspod_cn: { id: 'ID', token: 'Token' },
    dnspod_com: { id: 'ID', token: 'Token' },
    dnsdotcom: { id: 'API Key', token: 'API Secret' },
    cloudflare: { id: '账号邮箱', token: 'Global API Key' },
};

const settingLoading = ref(false);
const settingSaving = ref(false);
const settingError = ref('');
const setting = ref<AdminDnsSetting | null>(null);

const settingForm = reactive({
    dns: 'cloudflare',
    id: '',
    token: '',
    ttl: 600,
    weight_on: 1,
});

const providerLabels = computed(
    () =>
        PROVIDER_FIELD_LABELS[settingForm.dns] ?? {
            id: 'ID',
            token: 'Token',
        },
);

const currentProvider = computed(() =>
    DNS_PROVIDERS.find((provider) => provider.value === settingForm.dns),
);

async function loadDnsSetting(): Promise<void> {
    settingLoading.value = true;
    settingError.value = '';

    try {
        const data = await getAdminDnsSetting();
        setting.value = data;

        if (data.configured) {
            settingForm.dns = data.dns ?? 'cloudflare';
            settingForm.id = data.id ?? '';
            settingForm.token = data.token ?? '';
            settingForm.ttl = data.ttl ?? 600;
            settingForm.weight_on = data.weight_on ?? 1;
        }
    } catch (error) {
        settingError.value = getErrorMessage(error);
    } finally {
        settingLoading.value = false;
    }
}

async function submitDnsSetting(): Promise<void> {
    settingSaving.value = true;
    settingError.value = '';

    try {
        await saveAdminDnsSetting({
            dns: settingForm.dns,
            id: settingForm.id.trim(),
            token: settingForm.token.trim(),
            ttl: Number(settingForm.ttl),
            weight_on: settingForm.weight_on,
        });

        toast.success('DNS 设置已保存');
        await loadDnsSetting();
    } catch (error) {
        settingError.value = getErrorMessage(error);
    } finally {
        settingSaving.value = false;
    }
}

onMounted(loadDnsSetting);
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="DNS 管理"
            :icon="Network"
            :show-api-badge="false"
        />

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <!-- tab bar: same pattern as /console/admin/nodes -->
        <div class="flex flex-wrap gap-1 border-b pb-3">
            <button
                v-for="tab in dnsTabs"
                :key="tab.key"
                type="button"
                class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === tab.key
                        ? 'bg-primary/10 text-primary'
                        : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                "
                @click="activeTab = tab.key"
            >
                <component :is="tab.icon" class="size-4" />
                {{ tab.label }}
            </button>
        </div>

        <template v-if="activeTab === 'setting'">
            <!--
                Say plainly what this is and what it is not. The console used to
                show only the certificate DNS API, which takes credentials for
                the same providers — filling that in and expecting resolution to
                work is the obvious mistake, and the master's error for it
                (请先设置DNS) does not explain the difference.
            -->
            <Alert v-if="setting && !setting.configured">
                <AlertCircle data-icon="alert" />
                <AlertTitle>尚未设置 DNS</AlertTitle>
                <AlertDescription>
                    主控用这里的凭据把解析记录写入你的域名。未设置前，主控会拒绝添加
                    CNAME 域名（提示「请先设置DNS」），也不会生成任何 DNS
                    线路，节点即使注册成功也无法被解析。 这与「证书 DNS
                    API」标签页无关，后者只用于签发 SSL 证书。
                </AlertDescription>
            </Alert>

            <Alert v-else-if="setting && !setting.lines_configured">
                <AlertCircle data-icon="alert" />
                <AlertTitle>DNS 已设置，但尚未生成线路</AlertTitle>
                <AlertDescription>
                    还需要在「CNAME 域名」标签页添加一个域名，主控才会生成 DNS
                    线路。若已添加仍无线路，请重新保存本页以触发主控重新校验凭据。
                </AlertDescription>
            </Alert>

            <Alert v-if="settingError" variant="destructive">
                <AlertCircle data-icon="alert" />
                <AlertTitle>请求失败</AlertTitle>
                <AlertDescription>{{ settingError }}</AlertDescription>
            </Alert>

            <Card>
                <CardHeader
                    class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                >
                    <div class="grid gap-1">
                        <CardTitle class="text-base">全局 DNS 设置</CardTitle>
                        <CardDescription>
                            主控用于写入解析记录的 DNS 服务商凭据。
                        </CardDescription>
                    </div>
                    <Badge v-if="setting?.lines_configured" variant="secondary">
                        线路已生成
                    </Badge>
                    <Badge v-else-if="setting?.configured" variant="outline">
                        待生成线路
                    </Badge>
                </CardHeader>

                <CardContent class="grid max-w-xl gap-4">
                    <div class="grid gap-2">
                        <Label>DNS 服务商</Label>
                        <Select v-model="settingForm.dns">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="provider in DNS_PROVIDERS"
                                        :key="provider.value"
                                        :value="provider.value"
                                    >
                                        {{ provider.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="currentProvider"
                            class="text-xs text-muted-foreground"
                        >
                            域名需托管在 {{ currentProvider.domain }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="dns-setting-id">
                            {{ providerLabels.id }}
                        </Label>
                        <Input
                            id="dns-setting-id"
                            v-model="settingForm.id"
                            :placeholder="providerLabels.id"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="dns-setting-token">
                            {{ providerLabels.token }}
                        </Label>
                        <Input
                            id="dns-setting-token"
                            v-model="settingForm.token"
                            type="password"
                            :placeholder="providerLabels.token"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="dns-setting-ttl">TTL（秒）</Label>
                        <Input
                            id="dns-setting-ttl"
                            v-model.number="settingForm.ttl"
                            type="number"
                            min="60"
                            max="86400"
                        />
                        <p class="text-xs text-muted-foreground">
                            解析记录的缓存时间，默认
                            600。部分服务商对最小值有限制。
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label>权重解析</Label>
                        <Select
                            :model-value="String(settingForm.weight_on)"
                            @update:model-value="
                                settingForm.weight_on = Number($event)
                            "
                        >
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="1">开启</SelectItem>
                                    <SelectItem value="0">关闭</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            开启后按节点权重分配解析，需服务商支持。
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            :disabled="settingSaving || settingLoading"
                            @click="submitDnsSetting"
                        >
                            <Spinner
                                v-if="settingSaving"
                                data-icon="inline-start"
                            />
                            <Save v-else data-icon="inline-start" />
                            保存
                        </Button>
                        <Button
                            variant="outline"
                            :disabled="settingLoading"
                            @click="loadDnsSetting"
                        >
                            重新加载
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </template>

        <ConsoleDataTable
            v-if="activeTab === 'apis'"
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
            <template #cell-des="{ row }">
                <span v-if="row.des" class="text-muted-foreground">
                    {{ row.des }}
                </span>
                <Badge v-else variant="outline">无备注</Badge>
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
                    <Spinner
                        v-if="deletingId === Number(row.id)"
                        class="size-4"
                    />
                    <Trash2 v-else class="size-4 text-destructive" />
                </Button>
            </template>
        </ConsoleDataTable>

        <template v-if="activeTab === 'cnames'">
            <!--
                Spell out why this matters. An operator who skips it gets a
                console that looks configured but resolves nothing, and the
                failure surfaces much later as an empty 线路分配.
            -->
            <Alert>
                <AlertCircle data-icon="alert" />
                <AlertTitle>为什么需要 CNAME 域名</AlertTitle>
                <AlertDescription>
                    客户的域名通过 CNAME 指向这里配置的域名，主控再用 DNS API
                    把解析记录写入该域名所在的区域。请确保该域名的 NS
                    已托管在「DNS
                    API」标签页里配置的服务商处，否则记录无法写入。
                    主控在存在至少一个 CNAME 域名之前不会生成 DNS 线路，
                    节点也就无法被解析。
                </AlertDescription>
            </Alert>

            <ConsoleDataTable
                ref="cnameTableRef"
                title="CNAME 域名"
                :icon="Globe"
                :columns="cnameColumns"
                :fetch-fn="fetchCnameDomains"
                search-placeholder="搜索域名"
            >
                <template #toolbar>
                    <Button
                        variant="default"
                        size="sm"
                        @click="openCnameCreate"
                    >
                        <Plus data-icon="inline-start" class="size-4" />
                        新增
                    </Button>
                </template>
                <template #cell-des="{ row }">
                    <span v-if="row.des" class="text-muted-foreground">
                        {{ row.des }}
                    </span>
                    <Badge v-else variant="outline">无备注</Badge>
                </template>
                <template #row-actions="{ row }">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="openCnameEdit(row)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        :disabled="cnameDeleting"
                        @click="openCnameDelete(row)"
                    >
                        <Trash2 class="size-4 text-destructive" />
                    </Button>
                </template>
            </ConsoleDataTable>
        </template>

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
                        <Input
                            id="dns-name"
                            v-model="form.name"
                            placeholder="DNS API 名称"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>服务商</Label>
                        <Select
                            v-model="form.type"
                            @update:model-value="applyAuthTemplate"
                        >
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="(tpl, key) in AUTH_TEMPLATES"
                                        :key="key"
                                        :value="key"
                                    >
                                        {{ key }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>凭据</Label>
                        <div
                            class="grid gap-3 rounded-md border border-input bg-muted/30 p-3"
                        >
                            <div
                                v-for="fieldKey in authFieldKeys"
                                :key="fieldKey"
                                class="grid gap-1.5"
                            >
                                <Label
                                    :for="`dns-auth-${fieldKey}`"
                                    class="text-xs font-normal text-muted-foreground"
                                >
                                    {{
                                        AUTH_FIELD_LABELS[fieldKey] ?? fieldKey
                                    }}
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
                        <Input
                            id="dns-des"
                            v-model="form.des"
                            placeholder="可选备注"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="dialogOpen = false"
                        >取消</Button
                    >
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
                    <Button variant="outline" @click="deleteOpen = false"
                        >取消</Button
                    >
                    <Button
                        variant="destructive"
                        :disabled="deletingId !== null"
                        @click="confirmDelete"
                    >
                        <Spinner
                            v-if="deletingId !== null"
                            data-icon="inline-start"
                        />
                        <Trash2 v-else data-icon="inline-start" />
                        确认删除
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
        <!-- CNAME create/edit -->
        <Dialog v-model:open="cnameDialogOpen">
            <DialogScrollContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ cnameDialogTitle }}</DialogTitle>
                    <DialogDescription>
                        客户域名 CNAME 指向的区域，需托管在已配置的 DNS
                        服务商处。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="cnameFormError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>提交失败</AlertTitle>
                    <AlertDescription>{{ cnameFormError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="cname-domain">域名</Label>
                        <Input
                            id="cname-domain"
                            v-model="cnameForm.domain"
                            placeholder="例如 cdn.example.com"
                        />
                        <p class="text-xs text-muted-foreground">
                            建议使用子域名，主域名的其他解析不受影响。
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="cname-des">备注</Label>
                        <Input
                            id="cname-des"
                            v-model="cnameForm.des"
                            placeholder="可选备注"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="cnameDialogOpen = false">
                        取消
                    </Button>
                    <Button :disabled="cnameSaving" @click="submitCnameDomain">
                        <Spinner v-if="cnameSaving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        保存
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- CNAME delete confirm -->
        <Dialog v-model:open="cnameDeleteOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>确认删除</DialogTitle>
                    <DialogDescription>
                        确定要删除 CNAME 域名「{{
                            cnameDeleteTarget?.domain
                        }}」吗？ 已经指向该域名的客户站点将无法解析。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="cnameFormError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>删除失败</AlertTitle>
                    <AlertDescription>{{ cnameFormError }}</AlertDescription>
                </Alert>

                <DialogFooter>
                    <Button variant="outline" @click="cnameDeleteOpen = false">
                        取消
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="cnameDeleting"
                        @click="confirmCnameDelete"
                    >
                        <Spinner
                            v-if="cnameDeleting"
                            data-icon="inline-start"
                        />
                        <Trash2 v-else data-icon="inline-start" />
                        确认删除
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
