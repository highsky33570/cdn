<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    ChevronDown,
    LoaderCircle,
    Save,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
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
    siteCname,
    sitePorts,
    siteObject,
    siteStatus,
    csvCell,
} from '@/lib/adminSiteWorkspace';
import {
    formatDate,
    getErrorMessage,
    jsonText,
    numberValue,
    parseJsonObject,
    recordId,
    textValue,
} from '@/lib/cdnRecord';
import {
    createUserConfig,
    createUserCert,
    createUserJobs,
    getUserSite,
    createUserDnsApi,
    createUserSite,
    createUserSiteGroup,
    deleteUserConfig,
    deleteUserDnsApi,
    deleteUserSite,
    deleteUserSiteGroup,
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
    listUserConfigs,
    listUserDnsApis,
    listUserDomains,
    listUserPackages,
    listUserSiteGroups,
    listUserSites,
    postCnameCheck,
    syncUserDomains,
    updateUserConfig,
    updateUserDnsApi,
    updateUserSite,
    updateUserSiteGroup,
} from '@/lib/cdnUserApi';
import type {
    CdnDnsApiPayload,
    CdnSiteGroupPayload,
    CdnUserConfigPayload,
    CdnflyRecord,
} from '@/lib/cdnUserApi';

type SiteTab = 'sites' | 'groups' | 'defaults' | 'dnsapi' | 'resolve';

const props = withDefaults(
    defineProps<{ initialTab?: SiteTab; embedded?: boolean }>(),
    {
        initialTab: 'sites',
    },
);

const TABS: { key: SiteTab; label: string }[] = [
    { key: 'sites', label: '网站列表' },
    { key: 'groups', label: '分组管理' },
    { key: 'defaults', label: '默认设置' },
    { key: 'dnsapi', label: 'DNS API' },
    { key: 'resolve', label: '解析检测' },
];

const ENABLE_ALL = 'all';

const DNS_TYPES = [
    'CloudFlare',
    'DNSPod.cn',
    'GoDaddy.com',
    'Aliyun',
    'cloudns.net',
    'Name.com',
    'Namecheap',
    'jdcloud.com',
    'dnsdun',
] as const;

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

type ConfigValueType = 'number' | 'boolean' | 'select' | 'text' | 'json';
type ConfigMeta = {
    value: string;
    label: string;
    valueType: ConfigValueType;
    options?: string[];
};

const SITE_CONFIG_NAMES: ConfigMeta[] = [
    { value: 'http_listen-port', label: 'HTTP 监听端口', valueType: 'number' },
    {
        value: 'https_listen-port',
        label: 'HTTPS 监听端口',
        valueType: 'number',
    },
    {
        value: 'backend_http_port',
        label: '回源 HTTP 端口',
        valueType: 'number',
    },
    {
        value: 'backend_https_port',
        label: '回源 HTTPS 端口',
        valueType: 'number',
    },
    { value: 'proxy_timeout', label: '回源超时', valueType: 'number' },
    {
        value: 'ups_keepalive_conn',
        label: '连接池最大空闲数',
        valueType: 'number',
    },
    {
        value: 'ups_keepalive_timeout',
        label: '空闲连接超时',
        valueType: 'number',
    },
    { value: 'post_size_limit', label: '上传最大大小', valueType: 'number' },
    { value: 'recv_real_time', label: '实时接收', valueType: 'number' },
    { value: 'send_real_time', label: '实时发送', valueType: 'number' },
    { value: 'https_listen-hsts', label: '开启 HSTS', valueType: 'boolean' },
    { value: 'https_listen-http2', label: '开启 HTTP/2', valueType: 'boolean' },
    {
        value: 'https_listen-force_ssl_enable',
        label: '强制 HTTPS',
        valueType: 'boolean',
    },
    { value: 'ups_keepalive', label: '启用回源连接池', valueType: 'boolean' },
    { value: 'range', label: '分片回源', valueType: 'boolean' },
    { value: 'gzip_enable', label: '启用 Gzip', valueType: 'boolean' },
    {
        value: 'websocket_enable',
        label: '开启 WebSocket',
        valueType: 'boolean',
    },
    { value: 'block_proxy', label: '屏蔽代理', valueType: 'boolean' },
    {
        value: 'https_listen-ocsp_stapling',
        label: '开启 OCSP Stapling',
        valueType: 'boolean',
    },
    {
        value: 'https_listen-ssl_prefer_server_ciphers',
        label: 'SSL 优先服务器密码',
        valueType: 'boolean',
    },
    {
        value: 'backend_protocol',
        label: '回源协议',
        valueType: 'select',
        options: ['http', 'https'],
    },
    {
        value: 'proxy_http_version',
        label: '回源 HTTP 版本',
        valueType: 'select',
        options: ['1.0', '1.1'],
    },
    {
        value: 'balance_way',
        label: '负载方式',
        valueType: 'select',
        options: ['ip_hash', 'round_robin', 'least_conn'],
    },
    { value: 'proxy_ssl_protocols', label: '回源 SSL 协议', valueType: 'text' },
    {
        value: 'https_listen-ssl_protocols',
        label: 'SSL 协议',
        valueType: 'text',
    },
    { value: 'https_listen-ssl_ciphers', label: 'SSL 套件', valueType: 'text' },
    { value: 'gzip_types', label: 'Gzip 压缩类型', valueType: 'text' },
    { value: 'proxy_cache', label: '网站缓存', valueType: 'json' },
    { value: 'cc_default_rule', label: '默认 CC 规则', valueType: 'json' },
    { value: 'req_header', label: '请求头', valueType: 'json' },
    { value: 'extra_cc_rule', label: '自定义 CC 规则', valueType: 'json' },
];

const CONFIG_TYPE_OPTIONS = [
    { value: 'site', label: '网站' },
    { value: 'stream', label: '四层转发' },
    { value: 'cert', label: '证书' },
];

const activeTab = ref<SiteTab>(props.initialTab);
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const formError = ref('');
const deleteOpen = ref(false);
const deleting = ref(false);
const deleteError = ref('');
const deleteTarget = ref<CdnflyRecord | null>(null);
const deleteLabel = ref('');

// ── 网站列表 ──
const siteDialogOpen = ref(false);
const editingSite = ref<CdnflyRecord | null>(null);
const showAdvanced = ref(false);
const togglingId = ref<number | null>(null);
const sitePage = ref(1);
const siteTotal = ref(0);
const siteRows = ref<CdnflyRecord[]>([]);
const userPackages = ref<{ id: number; label: string; hint: string }[]>([]);
const userPackagesLoaded = ref(false);
const siteFilters = reactive({
    domain: '',
    id: '',
    cname: '',
    groups: '',
    user_package: '',
    enable: ENABLE_ALL,
    per_page: '10',
});
const siteForm = reactive({
    domain: '',
    user_package: '',
    backend_addr: '',
    groups: '',
    enable: '1',
});

// ── 分组管理 ──
const groupDialogOpen = ref(false);
const editingGroup = ref<CdnflyRecord | null>(null);
const groupPage = ref(1);
const groupSize = ref(10);
const groupTotal = ref(0);
const groupRows = ref<CdnflyRecord[]>([]);
const groupForm = reactive({ name: '', des: '' });

// ── 默认设置 ──
const configDialogOpen = ref(false);
const editingConfig = ref<CdnflyRecord | null>(null);
const configPage = ref(1);
const configTotal = ref(0);
const configRows = ref<CdnflyRecord[]>([]);
const configFilters = reactive({ type: 'site', per_page: '10' });
const configForm = reactive({
    type: 'site',
    name: '',
    value: '',
    scope_name: 'global',
    scope_id: '',
    enable: '1',
});
const siteGroupOptions = ref<{ id: string; name: string }[]>([]);

// ── DNS API ──
const dnsDialogOpen = ref(false);
const editingDns = ref<CdnflyRecord | null>(null);
const dnsPage = ref(1);
const dnsTotal = ref(0);
const dnsRows = ref<CdnflyRecord[]>([]);
const dnsFilters = reactive({ search: '', per_page: '10' });
const dnsForm = reactive({
    name: '',
    type: 'CloudFlare',
    auth: jsonText(AUTH_TEMPLATES.CloudFlare, '{}'),
    des: '',
});

// ── 解析检测 ──
const resolvePage = ref(1);
const resolveTotal = ref(0);
const resolveRows = ref<CdnflyRecord[]>([]);
const resolveFilters = reactive({
    domain: '',
    site_id: '',
    dnsapi_state: '',
    task_state: '',
    per_page: '10',
});
const resolveSelected = ref<Set<number>>(new Set());
const resolveChecking = ref(false);
const resolveSelectAll = ref(false);
type ResolveCheckState = 'checking' | 'resolved' | 'unresolved' | 'error';
const resolveCheckStates = ref<Record<string, ResolveCheckState>>({});

const currentConfigMeta = computed(() =>
    SITE_CONFIG_NAMES.find((c) => c.value === configForm.name),
);

const displayedDnsRows = computed(() => {
    const kw = dnsFilters.search.trim().toLowerCase();

    if (!kw) {
        return dnsRows.value;
    }

    return dnsRows.value.filter((r) =>
        [r.name, r.type, r.des]
            .map((v) => textValue(v).toLowerCase())
            .some((v) => v.includes(kw)),
    );
});

watch(activeTab, (tab) => {
    selected.value = [];
    errorMessage.value = '';

    if (tab === 'sites' && siteRows.value.length === 0) {
        void loadSites();
    }

    if (tab === 'groups' && groupRows.value.length === 0) {
        void loadGroups();
    }

    if (tab === 'defaults' && configRows.value.length === 0) {
        void loadConfigs();
    }

    if (tab === 'dnsapi' && dnsRows.value.length === 0) {
        void loadDns();
    }

    if (tab === 'resolve' && resolveRows.value.length === 0) {
        void loadResolve();
    }
});

onMounted(() => {
    void loadActive();
    void loadUserPackages();
});

// ── 网站列表 ──
async function loadSites(p = sitePage.value) {
    selected.value = [];
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: p,
            limit: Number(siteFilters.per_page),
        };

        for (const key of [
            'domain',
            'id',
            'cname',
            'groups',
            'user_package',
        ] as const) {
            if (siteFilters[key].trim()) {
                params[key] = siteFilters[key].trim();
            }
        }

        if (siteFilters.enable !== ENABLE_ALL) {
            params.enable = siteFilters.enable;
        }

        const r = await listUserSites(params);
        siteRows.value = extractCdnflyRows(r);
        siteTotal.value = extractCdnflyTotal(r, siteRows.value.length);
        sitePage.value = p;
    } catch (e) {
        errorMessage.value = getErrorMessage(e);
    } finally {
        loading.value = false;
    }
}

function isSiteEnabled(site: CdnflyRecord): boolean {
    const value = site.enable ?? site.enabled ?? site.status;

    if (value === true || value === 1) {
        return true;
    }

    const normalized = textValue(value).trim().toLowerCase();

    return [
        '1',
        'true',
        'enable',
        'enabled',
        'active',
        'running',
        'normal',
        'online',
        '启用',
        '正常',
        '运行中',
    ].includes(normalized);
}

/**
 * A readable label for the customer's own package.
 *
 * The record's `name` is a bare sequence number — "1" — so the picker offered
 * "1 (#1)", which tells a customer nothing about which plan they are attaching
 * the site to. The plan name and its expiry are what they recognise.
 */
function packageLabel(row: Record<string, unknown>): string {
    const tier = String(row.package_name ?? '').trim();

    return tier !== '' ? tier : `套餐 #${row.id ?? '?'}`;
}

function packageHint(row: Record<string, unknown>): string {
    const parts: string[] = [];
    const expires = String(row.end_at2 ?? row.end_at ?? '').slice(0, 10);

    if (expires !== '') {
        parts.push(`到期 ${expires}`);
    }

    // Traffic left is the thing a customer checks before adding another site.
    const limit = row.traffic;

    if (limit !== undefined && limit !== null && String(limit) !== '-1') {
        const used = Number(row.traffic_usage ?? 0);
        parts.push(`${used.toFixed(1)}/${limit} GB`);
    }

    return parts.join(' · ');
}

async function loadUserPackages() {
    try {
        const r = await listUserPackages({ limit: 200 });
        userPackages.value = extractCdnflyRows(r).map((row) => ({
            id: Number(row.id ?? 0),
            label: packageLabel(row),
            hint: packageHint(row),
        }));
    } catch {
        userPackages.value = [];
    } finally {
        userPackagesLoaded.value = true;
    }
}

function openSiteCreate() {
    editingSite.value = null;
    siteForm.domain = '';
    siteForm.user_package = '';
    siteForm.backend_addr = '';
    siteForm.groups = '';
    siteForm.enable = '1';
    formError.value = '';
    showAdvanced.value = false;
    siteDialogOpen.value = true;

    if (userPackages.value.length === 0) {
        void loadUserPackages();
    }
}

function sitePackageId(s: CdnflyRecord): string {
    const value = s.user_package ?? s.user_package_id ?? s.package_id;

    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return idField((value as CdnflyRecord).id);
    }

    return idField(value);
}

function sitePackageName(site: CdnflyRecord): string {
    const value = site.user_package;

    if (value && typeof value === 'object' && !Array.isArray(value)) {
        const packageRecord = value as CdnflyRecord;
        const nestedName = textValue(
            packageRecord.package_name ?? packageRecord.name,
        ).trim();

        if (nestedName) {
            return nestedName;
        }
    }

    const directName = textValue(
        site.package_name ?? site.user_package_name,
    ).trim();

    if (directName) {
        return directName;
    }

    const packageId = Number(sitePackageId(site));
    const userPackage = userPackages.value.find(
        (item) => item.id === packageId,
    );

    if (userPackage) {
        return userPackage.label;
    }

    return userPackagesLoaded.value ? '未知套餐' : '加载中…';
}

function openSiteEdit(s: CdnflyRecord) {
    router.visit(`/console/sites/${Number(s.id)}`);
}

async function submitSite() {
    const payload = {
        domain: siteForm.domain.trim(),
        user_package: Number(siteForm.user_package),
        backend: siteForm.backend_addr.trim()
            ? [{ addr: siteForm.backend_addr.trim() }]
            : [],
        groups: siteForm.groups.trim() || undefined,
        enable: siteForm.enable === '1' ? 1 : 0,
    };

    if (!editingSite.value) {
        if (!payload.domain) {
            formError.value = '域名不能为空';

            return;
        }

        if (!payload.user_package) {
            formError.value = '套餐 ID 必须填写';

            return;
        }

        if (!payload.backend.length) {
            formError.value = '源站地址不能为空';

            return;
        }
    }

    saving.value = true;
    formError.value = '';

    try {
        if (editingSite.value) {
            const id = numberValue(editingSite.value.id);

            if (!id) {
                formError.value = '站点 ID 缺失';

                return;
            }

            await updateUserSite(id, payload);
            toast.success('站点更新请求已提交');
        } else {
            await createUserSite(payload);
            toast.success('站点创建请求已提交');
        }

        siteDialogOpen.value = false;
        await loadSites();
    } catch (e) {
        formError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}

async function toggleSiteEnabled(s: CdnflyRecord, checked: boolean) {
    const id = numberValue(s.id);

    if (!id) {
        return;
    }

    togglingId.value = id;

    try {
        await updateUserSite(id, { enable: checked ? 1 : 0 });
        toast.success(checked ? '站点已启用' : '站点已停用');
        await loadSites();
    } catch (e) {
        toast.error(getErrorMessage(e));
    } finally {
        togglingId.value = null;
    }
}

// ── 分组管理 ──
async function loadGroups(p = groupPage.value) {
    selected.value = [];
    loading.value = true;
    errorMessage.value = '';

    try {
        const r = await listUserSiteGroups({ page: p, limit: groupSize.value });
        groupRows.value = extractCdnflyRows(r);
        groupTotal.value = extractCdnflyTotal(r, groupRows.value.length);
        groupPage.value = p;
    } catch (e) {
        errorMessage.value = getErrorMessage(e);
    } finally {
        loading.value = false;
    }
}

function openGroupCreate() {
    editingGroup.value = null;
    groupForm.name = '';
    groupForm.des = '';
    formError.value = '';
    groupDialogOpen.value = true;
}

function openGroupEdit(r: CdnflyRecord) {
    editingGroup.value = r;
    groupForm.name = textValue(r.name);
    groupForm.des = textValue(r.des);
    formError.value = '';
    groupDialogOpen.value = true;
}

async function submitGroup() {
    const name = groupForm.name.trim();

    if (!name) {
        formError.value = '分组名称不能为空';

        return;
    }

    const payload: CdnSiteGroupPayload = {
        name,
        des: groupForm.des.trim() || null,
    };
    saving.value = true;
    formError.value = '';

    try {
        if (editingGroup.value) {
            const id = recordId(editingGroup.value);

            if (!id) {
                formError.value = 'ID 缺失';

                return;
            }

            await updateUserSiteGroup(id, payload);
            toast.success('分组更新成功');
        } else {
            await createUserSiteGroup(payload);
            toast.success('分组创建成功');
        }

        groupDialogOpen.value = false;
        await loadGroups();
    } catch (e) {
        formError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}

// ── 默认设置 ──
async function loadConfigs(p = configPage.value) {
    selected.value = [];
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: p,
            limit: Number(configFilters.per_page),
        };

        if (configFilters.type !== 'all') {
            params.type = configFilters.type;
        }

        const r = await listUserConfigs(params);
        configRows.value = extractCdnflyRows(r);
        configTotal.value = extractCdnflyTotal(r, configRows.value.length);
        configPage.value = p;
    } catch (e) {
        errorMessage.value = getErrorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function loadSiteGroupOptions() {
    try {
        const r = await listUserSiteGroups({ limit: 200 });
        siteGroupOptions.value = extractCdnflyRows(r).map((row) => ({
            id: String(row.id),
            name: textValue(row.name),
        }));
    } catch {
        siteGroupOptions.value = [];
    }
}

function openConfigCreate() {
    editingConfig.value = null;
    configForm.type = 'site';
    configForm.name = '';
    configForm.value = '';
    configForm.scope_name = 'global';
    configForm.scope_id = '';
    configForm.enable = '1';
    formError.value = '';
    configDialogOpen.value = true;
    void loadSiteGroupOptions();
}

function openConfigEdit(r: CdnflyRecord) {
    editingConfig.value = r;
    configForm.type = textValue(r.type) || 'site';
    configForm.name = textValue(r.name);
    configForm.value = textValue(r.value);
    configForm.scope_name = textValue(r.scope_name) || 'global';
    configForm.scope_id = r.scope_id ? String(r.scope_id) : '';
    configForm.enable = r.enable === 0 || r.enable === false ? '0' : '1';
    formError.value = '';
    configDialogOpen.value = true;
    void loadSiteGroupOptions();
}

function configValueForSubmit(): string {
    const meta = currentConfigMeta.value;

    if (!meta) {
        return configForm.value;
    }

    if (meta.valueType === 'boolean') {
        return configForm.value === '1' ? '1' : '0';
    }

    return configForm.value;
}

async function submitConfig() {
    if (!configForm.name) {
        formError.value = '请选择配置项';

        return;
    }

    const payload: CdnUserConfigPayload = {
        type: configForm.type,
        name: configForm.name,
        value: configValueForSubmit(),
        scope_name: configForm.scope_name,
        scope_id:
            configForm.scope_name === 'group'
                ? Number(configForm.scope_id) || null
                : null,
        enable: configForm.enable === '1' ? 1 : 0,
    };
    saving.value = true;
    formError.value = '';

    try {
        if (editingConfig.value) {
            const id = recordId(editingConfig.value);

            if (!id) {
                formError.value = 'ID 缺失';

                return;
            }

            await updateUserConfig(id, payload);
            toast.success('配置更新成功');
        } else {
            await createUserConfig(payload);
            toast.success('配置创建成功');
        }

        configDialogOpen.value = false;
        await loadConfigs();
    } catch (e) {
        formError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}

function configLabel(name: string): string {
    return SITE_CONFIG_NAMES.find((c) => c.value === name)?.label ?? name;
}

// ── DNS API ──
async function loadDns(p = dnsPage.value) {
    selected.value = [];
    loading.value = true;
    errorMessage.value = '';

    try {
        const r = await listUserDnsApis({
            page: p,
            limit: Number(dnsFilters.per_page),
        });
        dnsRows.value = extractCdnflyRows(r);
        dnsTotal.value = extractCdnflyTotal(r, dnsRows.value.length);
        dnsPage.value = p;
    } catch (e) {
        errorMessage.value = getErrorMessage(e);
    } finally {
        loading.value = false;
    }
}

function openDnsCreate() {
    editingDns.value = null;
    dnsForm.name = '';
    dnsForm.type = 'CloudFlare';
    dnsForm.auth = jsonText(AUTH_TEMPLATES.CloudFlare, '{}');
    dnsForm.des = '';
    formError.value = '';
    dnsDialogOpen.value = true;
}

function openDnsEdit(r: CdnflyRecord) {
    editingDns.value = r;
    dnsForm.name = textValue(r.name);
    dnsForm.type = textValue(r.type) || 'CloudFlare';
    dnsForm.auth = jsonText(
        r.auth,
        jsonText(AUTH_TEMPLATES[dnsForm.type], '{}'),
    );
    dnsForm.des = textValue(r.des ?? r.remark);
    formError.value = '';
    dnsDialogOpen.value = true;
}

function applyAuthTemplate() {
    dnsForm.auth = jsonText(AUTH_TEMPLATES[dnsForm.type] ?? {}, '{}');
}

async function submitDns() {
    let payload: CdnDnsApiPayload;

    try {
        payload = {
            name: dnsForm.name.trim(),
            type: dnsForm.type,
            auth: parseJsonObject(dnsForm.auth),
            des: dnsForm.des.trim() || null,
        };
    } catch (e) {
        formError.value = getErrorMessage(e);

        return;
    }

    if (!payload.name) {
        formError.value = '名称不能为空';

        return;
    }

    saving.value = true;
    formError.value = '';

    try {
        if (editingDns.value) {
            const id = recordId(editingDns.value);

            if (!id) {
                formError.value = 'ID 缺失';

                return;
            }

            await updateUserDnsApi(id, payload);
            toast.success('DNS API 更新已提交');
        } else {
            await createUserDnsApi(payload);
            toast.success('DNS API 创建已提交');
        }

        dnsDialogOpen.value = false;
        await loadDns();
    } catch (e) {
        formError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}

async function loadResolve(p = resolvePage.value) {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: p,
            limit: Number(resolveFilters.per_page),
        };

        if (resolveFilters.domain.trim()) {
            params.domain = resolveFilters.domain.trim();
        }

        if (resolveFilters.site_id.trim()) {
            params.site_id = resolveFilters.site_id.trim();
        }

        if (resolveFilters.dnsapi_state) {
            params.dnsapi_state = resolveFilters.dnsapi_state;
        }

        if (resolveFilters.task_state) {
            params.task_state = resolveFilters.task_state;
        }

        const r = await listUserDomains(params);
        const rows = extractCdnflyRows(r);
        resolveRows.value = rows;
        resolveTotal.value = extractCdnflyTotal(r, resolveRows.value.length);
        resolvePage.value = p;
        resolveSelected.value.clear();
        resolveSelectAll.value = false;
        resolveCheckStates.value = Object.fromEntries(
            rows.map((row) => [String(row.id), 'checking']),
        );
        void checkResolveRows(rows);
    } catch (e) {
        errorMessage.value = getErrorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function checkResolveRows(rows: CdnflyRecord[]) {
    if (rows.length === 0) {
        return;
    }

    const payload: Record<string, { cname: string; domain: string }> = {};
    const domainIds: Record<string, string> = {};

    for (const row of rows) {
        const id = textValue(row.id);
        const cname = textValue(row.cname);
        const domain = textValue(row.domain);

        if (id && cname && domain) {
            // Numeric JSON object keys are converted to PHP array indexes by
            // Laravel. A prefixed key keeps the proxied payload an object, as
            // required by CDNFly; the API returns the same caller-defined key.
            const requestKey = `domain_${id}`;
            payload[requestKey] = { cname, domain };
            domainIds[requestKey] = id;
        } else if (id) {
            resolveCheckStates.value[id] = 'error';
        }
    }

    if (Object.keys(payload).length === 0) {
        return;
    }

    try {
        const response = await postCnameCheck(payload);
        const results = extractCdnflyRecord(response) ?? {};

        for (const requestKey of Object.keys(payload)) {
            const result = results[requestKey];
            resolveCheckStates.value[domainIds[requestKey]] =
                result === true || result === 1 || result === '1'
                    ? 'resolved'
                    : 'unresolved';
        }
    } catch (e) {
        for (const requestKey of Object.keys(payload)) {
            resolveCheckStates.value[domainIds[requestKey]] = 'error';
        }

        toast.error(`自动检测解析失败：${getErrorMessage(e)}`);
    }
}

function toggleResolveSelect(id: number) {
    const s = resolveSelected.value;

    if (s.has(id)) {
        s.delete(id);
    } else {
        s.add(id);
    }

    resolveSelectAll.value =
        resolveRows.value.length > 0 && s.size === resolveRows.value.length;
}

function toggleResolveSelectAll() {
    if (resolveSelectAll.value) {
        resolveSelected.value.clear();
        resolveSelectAll.value = false;
    } else {
        resolveSelected.value = new Set(
            resolveRows.value.map((r) => Number(r.id)),
        );
        resolveSelectAll.value = true;
    }
}

async function syncResolve() {
    if (resolveSelected.value.size === 0) {
        toast.error('请先选择域名');

        return;
    }

    resolveChecking.value = true;

    try {
        await syncUserDomains([...resolveSelected.value].map((id) => ({ id })));
        toast.success('同步解析任务已提交');
        await loadResolve();
    } catch (e) {
        errorMessage.value = getErrorMessage(e);
    } finally {
        resolveChecking.value = false;
    }
}

// ── 通用删除 ──
function openDelete(record: CdnflyRecord, label: string) {
    if (activeTab.value === 'sites' && isSiteEnabled(record)) {
        toast.error('请先关闭站点状态，等待配置同步完成后再删除');

        return;
    }

    deleteTarget.value = record;
    deleteLabel.value = label;
    deleteError.value = '';
    deleteOpen.value = true;
}

async function confirmDelete() {
    const id = recordId(deleteTarget.value!);

    if (!id) {
        return;
    }

    deleting.value = true;

    try {
        if (activeTab.value === 'sites') {
            await deleteUserSite(id);
            toast.success('站点已删除');
            deleteOpen.value = false;
            await loadSites();
        } else if (activeTab.value === 'groups') {
            await deleteUserSiteGroup(id);
            toast.success('分组已删除');
            deleteOpen.value = false;
            await loadGroups();
        } else if (activeTab.value === 'defaults') {
            await deleteUserConfig(id);
            toast.success('配置已删除');
            deleteOpen.value = false;
            await loadConfigs();
        } else if (activeTab.value === 'dnsapi') {
            await deleteUserDnsApi(id);
            toast.success('DNS API 已删除');
            deleteOpen.value = false;
            await loadDns();
        }
    } catch (e) {
        deleteError.value = getErrorMessage(e);
    } finally {
        deleting.value = false;
    }
}

// ── 工具 ──
function siteName(s: CdnflyRecord) {
    return textValue(s.domain) || `#${textValue(s.id)}`;
}
/**
 * Readable origin list.
 *
 * `backend` arrives as a JSON string of [{addr, state, weight}]. Printing it
 * raw showed customers `[{"addr":"1.2.3.4","state":"up","weight":1}]`; they
 * only want the address, with a mark when it is down or one of several.
 */
function backendText(s: CdnflyRecord): string {
    const raw = s.backend;

    let list: unknown = raw;

    if (typeof raw === 'string') {
        try {
            list = JSON.parse(raw);
        } catch {
            return textValue(raw) || '-';
        }
    }

    if (!Array.isArray(list) || list.length === 0) {
        return '-';
    }

    const addrs = list
        .map((b) => {
            const row = b as Record<string, unknown>;
            const addr = textValue(row.addr);

            if (addr === '') {
                return '';
            }

            // A backend that is not "up" is worth flagging; a healthy one needs
            // no decoration.
            const state = textValue(row.state);

            return state !== '' && state !== 'up'
                ? `${addr}（${state}）`
                : addr;
        })
        .filter((a) => a !== '');

    if (addrs.length === 0) {
        return '-';
    }

    // One address reads cleanest bare; several as "first +N".
    return addrs.length === 1 ? addrs[0] : `${addrs[0]} +${addrs.length - 1}`;
}
// Truncating to 16 chars left '2026-09-14T10:03'. The shared formatter
// renders the viewer's local time and keeps seconds.
const fmtDate = formatDate;
function idField(v: unknown) {
    const id = numberValue(v);

    return id === null ? '' : String(id);
}
function recordName(r: CdnflyRecord) {
    return textValue(r.name) || `#${textValue(r.id)}`;
}
function resolveCheckState(row: CdnflyRecord): ResolveCheckState {
    return resolveCheckStates.value[textValue(row.id)] ?? 'checking';
}

// 任务状态 comes from the domain's sync-task `state` field (verified against the
// panel's taskStateText: done/pending/process/failed, empty = 无任务).
const TASK_STATE_LABELS: Record<string, string> = {
    done: '已完成',
    pending: '待同步',
    process: '同步中',
    failed: '同步失败',
};

function taskStateLabel(v: unknown): string {
    const s = String(v ?? '').toLowerCase();

    if (s === '') {
        return '无任务';
    }

    return TASK_STATE_LABELS[s] ?? textValue(v);
}

const selected = ref<number[]>([]);
const busy = ref(false);
const exporting = ref(false);
const quickField = ref<'domain' | 'id' | 'cname'>('domain');
const activeRows = computed(
    () =>
        ({
            sites: siteRows.value,
            groups: groupRows.value,
            defaults: configRows.value,
            dnsapi: displayedDnsRows.value,
            resolve: resolveRows.value,
        })[activeTab.value],
);
const currentPage = computed(
    () =>
        ({
            sites: sitePage.value,
            groups: groupPage.value,
            defaults: configPage.value,
            dnsapi: dnsPage.value,
            resolve: resolvePage.value,
        })[activeTab.value],
);
const currentTotal = computed(
    () =>
        ({
            sites: siteTotal.value,
            groups: groupTotal.value,
            defaults: configTotal.value,
            dnsapi: dnsTotal.value,
            resolve: resolveTotal.value,
        })[activeTab.value],
);
const currentSize = computed(() =>
    Number(
        {
            sites: siteFilters.per_page,
            groups: groupSize.value,
            defaults: configFilters.per_page,
            dnsapi: dnsFilters.per_page,
            resolve: resolveFilters.per_page,
        }[activeTab.value],
    ),
);
const allSelected = computed(
    () =>
        activeRows.value.length > 0 &&
        activeRows.value.every((row) =>
            activeTab.value === 'resolve'
                ? resolveSelected.value.has(Number(row.id))
                : selected.value.includes(Number(row.id)),
        ),
);
type SiteColumn = { key: string; label: string; width?: number };
const tableColumns = computed<SiteColumn[]>(() => {
    if (activeTab.value === 'sites') {
        return [
            { key: 'id', label: 'ID', width: 90 },
            { key: 'domain', label: '域名', width: 190 },
            { key: 'ports', label: '监听端口', width: 125 },
            { key: 'origin', label: '源站', width: 125 },
            { key: 'cname', label: 'CNAME', width: 200 },
            { key: 'https', label: 'HTTPS', width: 125 },
            { key: 'package', label: '套餐', width: 125 },
            { key: 'groups', label: '分组', width: 125 },
            { key: 'status', label: '状态', width: 185 },
            { key: 'created', label: '添加时间', width: 210 },
            { key: 'actions', label: '操作', width: 160 },
        ];
    }

    if (activeTab.value === 'resolve') {
        return [
            { key: 'id', label: 'ID', width: 120 },
            { key: 'site_id', label: '网站ID', width: 130 },
            { key: 'domain', label: '域名' },
            { key: 'cname', label: 'CNAME' },
            { key: 'resolve', label: '解析状态', width: 160 },
            { key: 'dns_api', label: 'DNS API', width: 160 },
            { key: 'task', label: '任务状态', width: 160 },
        ];
    }

    return [
        { key: 'id', label: 'ID', width: 100 },
        ...(activeTab.value === 'defaults'
            ? [
                  { key: 'config', label: '设置项' },
                  { key: 'value', label: '设置值' },
                  { key: 'scope', label: '生效范围' },
              ]
            : [
                  { key: 'name', label: '名称' },
                  ...(activeTab.value === 'dnsapi'
                      ? [{ key: 'type', label: '类型' }]
                      : []),
                  { key: 'des', label: '备注' },
              ]),
        { key: 'actions', label: '操作', width: 180 },
    ];
});
function hasHttps(row: CdnflyRecord): boolean {
    return Object.keys(siteObject(row.https_listen)).length > 0;
}
function cellValue(row: CdnflyRecord, key: string): string {
    if (key === 'ports') {
        return sitePorts(row).join(' ');
    }

    if (key === 'origin') {
        return backendText(row);
    }

    if (key === 'cname') {
        return siteCname(row);
    }

    if (key === 'package') {
        return `${sitePackageName(row)} (id: ${sitePackageId(row)})`;
    }

    if (key === 'groups') {
        return textValue(row.group_name ?? row.groups);
    }

    if (key === 'created') {
        return fmtDate(row.create_at2 ?? row.create_at);
    }

    if (key === 'config') {
        return configLabel(textValue(row.name));
    }

    if (key === 'scope') {
        return row.scope_name === 'group'
            ? `网站分组 (${row.scope_id})`
            : '全局';
    }

    if (key === 'dns_api') {
        return row.dns_api && row.dns_api !== '0' ? '已配置' : '未配置';
    }

    if (key === 'task') {
        return row.state ? taskStateLabel(row.state) : '';
    }

    return textValue(row[key]);
}
async function loadActive(page = currentPage.value) {
    selected.value = [];
    await {
        sites: loadSites,
        groups: loadGroups,
        defaults: loadConfigs,
        dnsapi: loadDns,
        resolve: loadResolve,
    }[activeTab.value](page);
}
function changePageSize(size: number) {
    if (activeTab.value === 'groups') {
        groupSize.value = size;
    } else {
        ({
            sites: siteFilters,
            defaults: configFilters,
            dnsapi: dnsFilters,
            resolve: resolveFilters,
        })[activeTab.value].per_page = String(size);
    }

    void loadActive(1);
}
function selectRow(id: number) {
    if (activeTab.value === 'resolve') {
        toggleResolveSelect(id);

        return;
    }

    selected.value = selected.value.includes(id)
        ? selected.value.filter((item) => item !== id)
        : [...selected.value, id];
}
function selectAll() {
    if (activeTab.value === 'resolve') {
        toggleResolveSelectAll();

        return;
    }

    selected.value = allSelected.value
        ? []
        : activeRows.value.map((row) => Number(row.id));
}
function editRow(row: CdnflyRecord) {
    ({
        sites: openSiteEdit,
        groups: openGroupEdit,
        defaults: openConfigEdit,
        dnsapi: openDnsEdit,
        resolve: openSiteEdit,
    })[activeTab.value](row);
}
function clearSiteFilters() {
    Object.assign(siteFilters, {
        domain: '',
        id: '',
        cname: '',
        groups: '',
        user_package: '',
        enable: ENABLE_ALL,
    });
    void loadSites(1);
}
function clearResolveFilters() {
    Object.assign(resolveFilters, {
        domain: '',
        site_id: '',
        dnsapi_state: '',
        task_state: '',
    });
    void loadResolve(1);
}
type BulkAction =
    | 'enable'
    | 'disable'
    | 'delete'
    | 'edit'
    | 'certificate'
    | 'cache'
    | 'unlock';
const bulkOpen = ref(false),
    bulkError = ref(''),
    bulkAction = ref<BulkAction>('edit');
const bulkField = ref<'groups' | 'user_package'>('groups'),
    bulkValue = ref('');
const bulkTitle = computed(
    () =>
        ({
            enable: '启用网站',
            disable: '禁用网站',
            delete: '删除所选记录',
            edit: '批量修改',
            certificate: '申请证书',
            cache: '清空缓存',
            unlock: '解锁黑名单',
        })[bulkAction.value],
);
const pendingCertificates = new Map<number, number>();
function openBulk(action: BulkAction) {
    bulkAction.value = action;
    bulkError.value = '';
    bulkValue.value = '';
    bulkOpen.value = true;
}
async function runBulk(action: BulkAction) {
    if (busy.value || !selected.value.length) {
        return;
    }

    const tab = activeTab.value;
    const targets = [...selected.value];
    const failures: number[] = [];
    const messages: string[] = [];

    if (
        action === 'edit' &&
        bulkField.value === 'user_package' &&
        !/^[1-9]\d*$/.test(bulkValue.value.trim())
    ) {
        bulkError.value = '请输入有效的用户套餐ID';

        return;
    }

    busy.value = true;
    bulkError.value = '';

    for (const id of targets) {
        try {
            if (action === 'delete') {
                if (tab === 'sites') {
                    const site = extractCdnflyRecord(await getUserSite(id));

                    if (!site || isSiteEnabled(site)) {
                        throw new Error('请先禁用网站并等待配置同步完成');
                    }

                    await deleteUserSite(id);
                } else {
                    await {
                        groups: deleteUserSiteGroup,
                        defaults: deleteUserConfig,
                        dnsapi: deleteUserDnsApi,
                        resolve: deleteUserSite,
                    }[tab](id);
                }
            } else if (action === 'enable' || action === 'disable') {
                await updateUserSite(id, {
                    enable: action === 'enable' ? 1 : 0,
                });
            } else if (action === 'edit') {
                await updateUserSite(
                    id,
                    bulkField.value === 'groups'
                        ? { groups: bulkValue.value.trim() }
                        : { user_package: Number(bulkValue.value) },
                );
            } else if (action === 'unlock') {
                await createUserJobs([
                    {
                        type: 'unlock_ip',
                        data: { site_id: id, key1: 'site_id' },
                    },
                ]);
            } else {
                const site = extractCdnflyRecord(await getUserSite(id));

                if (!site || !textValue(site.domain)) {
                    throw new Error('网站域名缺失');
                }

                if (action === 'cache') {
                    await createUserJobs(
                        textValue(site.domain)
                            .trim()
                            .split(/\s+/)
                            .flatMap((domain) =>
                                ['http', 'https'].map((protocol) => ({
                                    type: 'clean_dir',
                                    data: { url: `${protocol}://${domain}/` },
                                })),
                            ),
                    );
                } else {
                    if (!isSiteEnabled(site)) {
                        throw new Error('请先启用网站');
                    }

                    if (hasHttps(site)) {
                        throw new Error('网站已开启HTTPS，无需重复申请');
                    }

                    let certId = pendingCertificates.get(id);

                    if (!certId) {
                        const domain = textValue(site.domain);
                        const result = await createUserCert({
                            name: `${domain.split(/\s+/)[0]}免费证书`,
                            domain,
                            des: '一键申请',
                        });
                        const record = extractCdnflyRecord(result);
                        certId = Number(record?.id ?? result.id ?? result.data);

                        if (!Number.isSafeInteger(certId) || certId <= 0) {
                            throw new Error(
                                '证书已申请，但未返回ID，请到证书列表手动绑定',
                            );
                        }

                        pendingCertificates.set(id, certId);
                    }

                    await updateUserSite(id, {
                        https_listen: { cert: certId },
                    });
                    pendingCertificates.delete(id);
                }
            }
        } catch (e) {
            failures.push(id);
            messages.push(`#${id}: ${getErrorMessage(e)}`);
        }
    }

    await loadActive();
    selected.value = failures;
    busy.value = false;

    if (failures.length) {
        bulkError.value = messages.join('；');
        errorMessage.value = bulkError.value;
    } else {
        bulkOpen.value = false;
        toast.success(`已处理 ${targets.length} 项`);
    }
}
async function exportSites() {
    exporting.value = true;

    try {
        const params: Record<string, string | number> = { limit: 200 };

        for (const key of [
            'domain',
            'id',
            'cname',
            'groups',
            'user_package',
        ] as const) {
            if (siteFilters[key].trim()) {
                params[key] = siteFilters[key].trim();
            }
        }

        if (siteFilters.enable !== ENABLE_ALL) {
            params.enable = siteFilters.enable;
        }

        const exported: CdnflyRecord[] = [];
        let total = 1;

        for (let page = 1; exported.length < total; page++) {
            const result = await listUserSites({ ...params, page });
            const rows = extractCdnflyRows(result);
            total = extractCdnflyTotal(result, rows.length);

            if (!rows.length) {
                break;
            }

            const fresh = rows.filter(
                (row) => !exported.some((previous) => previous.id === row.id),
            );

            if (!fresh.length) {
                throw new Error('导出分页未返回新数据，请重试');
            }

            exported.push(...fresh);
        }

        const columns = tableColumns.value.filter(
            (column) => column.key !== 'actions',
        );
        const csv = [
            columns.map((column) => csvCell(column.label)).join(','),
            ...exported.map((row) =>
                columns
                    .map((column) =>
                        csvCell(
                            column.key === 'https'
                                ? hasHttps(row)
                                    ? '是'
                                    : '否'
                                : column.key === 'status'
                                  ? siteStatus(row).text
                                  : cellValue(row, column.key),
                        ),
                    )
                    .join(','),
            ),
        ].join('\r\n');
        const url = URL.createObjectURL(
            new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' }),
        );
        const link = document.createElement('a');
        link.href = url;
        link.download = 'sites.csv';
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
        errorMessage.value = getErrorMessage(e);
    } finally {
        exporting.value = false;
    }
}
</script>

<template>
    <div
        class="console-user-sites console-page user-sites min-w-0"
        :class="props.embedded ? 'embedded-sites' : 'p-4 md:p-6'"
    >
        <section class="sites-workspace bg-card text-card-foreground">
            <div
                v-if="!props.embedded"
                class="sites-tabs"
                role="tablist"
                aria-label="网站管理"
            >
                <Button
                    variant="ghost"
                    type="button"
                    data-slot="console-tab"
                    v-for="tab in TABS"
                    :key="tab.key"
                    :id="`sites-tab-${tab.key}`"
                    role="tab"
                    :aria-selected="activeTab === tab.key"
                    aria-controls="sites-panel"
                    :disabled="busy || loading || exporting"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </Button>
            </div>
            <Alert v-if="errorMessage" variant="destructive" class="mb-4"
                ><AlertCircle /><AlertTitle>请求失败</AlertTitle
                ><AlertDescription
                    >{{ errorMessage }}
                    <Button variant="link" @click="loadActive()"
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <div
                id="sites-panel"
                role="tabpanel"
                :aria-labelledby="`sites-tab-${activeTab}`"
                :aria-busy="loading"
            >
                <div v-if="activeTab === 'sites'" class="sites-toolbar">
                    <Button @click="openSiteCreate">添加网站</Button>
                    <Button
                        variant="outline"
                        :disabled="!selected.length || busy"
                        @click="openBulk('edit')"
                        >批量修改</Button
                    >
                    <Button
                        variant="outline"
                        :disabled="!selected.length || busy"
                        @click="openBulk('certificate')"
                        >申请证书</Button
                    >
                    <DropdownMenu
                        ><DropdownMenuTrigger as-child
                            ><Button
                                variant="outline"
                                :disabled="!selected.length || busy"
                                >更多操作<ChevronDown
                                    class="size-4" /></Button></DropdownMenuTrigger
                        ><DropdownMenuContent class="console-user-sites">
                            <DropdownMenuItem @select="runBulk('enable')"
                                >启用</DropdownMenuItem
                            ><DropdownMenuItem @select="runBulk('disable')"
                                >禁用</DropdownMenuItem
                            ><DropdownMenuItem @select="openBulk('delete')"
                                >删除</DropdownMenuItem
                            ><DropdownMenuItem @select="openBulk('unlock')"
                                >解锁黑名单</DropdownMenuItem
                            ><DropdownMenuItem @select="openBulk('cache')"
                                >清空缓存</DropdownMenuItem
                            >
                        </DropdownMenuContent></DropdownMenu
                    >
                    <form
                        data-slot="console-input-group"
                        class="quick-search"
                        @submit.prevent="loadSites(1)"
                    >
                        <SelectField v-model="quickField" aria-label="搜索类型">
                            <SelectOption value="domain">域名</SelectOption>
                            <SelectOption value="id">ID</SelectOption>
                            <SelectOption value="cname"
                                >CNAME</SelectOption
                            ></SelectField
                        ><Input
                            v-model="siteFilters[quickField]"
                            aria-label="网站搜索"
                            :placeholder="
                                quickField === 'domain'
                                    ? '输入域名,模糊搜索'
                                    : `输入${quickField === 'id' ? 'ID' : 'CNAME'}`
                            "
                        /><Button :disabled="loading" type="submit"
                            >查询</Button
                        >
                    </form>
                    <Button
                        variant="outline"
                        :disabled="exporting || loading"
                        @click="exportSites"
                        >{{ exporting ? '导出中…' : '导出' }}</Button
                    >
                    <Button
                        variant="link"
                        :aria-expanded="showAdvanced"
                        @click="showAdvanced = !showAdvanced"
                        >高级搜索</Button
                    >
                </div>
                <form
                    v-if="activeTab === 'sites' && showAdvanced"
                    class="advanced-search"
                    aria-label="高级搜索"
                    @submit.prevent="loadSites(1)"
                >
                    <label
                        >启用状态<SelectField v-model="siteFilters.enable">
                            <SelectOption value="all">全部状态</SelectOption>
                            <SelectOption value="1">启用</SelectOption>
                            <SelectOption value="0">禁用</SelectOption>
                        </SelectField></label
                    >
                    <label>网站ID<Input v-model="siteFilters.id" /></label
                    ><label>CNAME<Input v-model="siteFilters.cname" /></label
                    ><label>分组ID<Input v-model="siteFilters.groups" /></label
                    ><label
                        >套餐ID<Input v-model="siteFilters.user_package"
                    /></label>
                    <Button type="submit" :disabled="loading">查询</Button
                    ><Button
                        variant="link"
                        type="button"
                        @click="clearSiteFilters"
                        >清除</Button
                    >
                </form>
                <div
                    v-if="['groups', 'defaults', 'dnsapi'].includes(activeTab)"
                    class="sites-toolbar"
                >
                    <Button
                        @click="
                            activeTab === 'groups'
                                ? openGroupCreate()
                                : activeTab === 'defaults'
                                  ? openConfigCreate()
                                  : openDnsCreate()
                        "
                        >{{
                            activeTab === 'groups'
                                ? '新增分组'
                                : activeTab === 'defaults'
                                  ? '新增设置'
                                  : '新增DNS API'
                        }}</Button
                    >
                    <Button
                        variant="outline"
                        :disabled="!selected.length || busy"
                        @click="openBulk('delete')"
                        >删除</Button
                    >
                </div>
                <form
                    v-if="activeTab === 'resolve'"
                    class="sites-toolbar resolve-toolbar"
                    @submit.prevent="loadResolve(1)"
                >
                    <Button
                        type="button"
                        :disabled="resolveChecking || !resolveSelected.size"
                        @click="syncResolve"
                        >同步解析</Button
                    >
                    <SelectField
                        v-model="resolveFilters.dnsapi_state"
                        aria-label="DNS API状态"
                        @change="loadResolve(1)"
                    >
                        <SelectOption value="">请选择DNS API状态</SelectOption>
                        <SelectOption value="set">DNS API已设置</SelectOption>
                        <SelectOption value="not_set"
                            >DNS API未设置</SelectOption
                        >
                    </SelectField>
                    <SelectField
                        v-model="resolveFilters.task_state"
                        aria-label="任务状态"
                        @change="loadResolve(1)"
                    >
                        <SelectOption value="">请选择任务状态</SelectOption>
                        <SelectOption value="done">任务已完成</SelectOption>
                        <SelectOption value="pending">任务待同步</SelectOption>
                        <SelectOption value="process">任务同步中</SelectOption>
                        <SelectOption value="failed">任务同步失败</SelectOption>
                    </SelectField>
                    <label data-slot="console-input-group" class="input-group"
                        ><span>域名</span
                        ><Input
                            v-model="resolveFilters.domain"
                            placeholder="请输入域名"
                            @change="loadResolve(1)" /></label
                    ><label data-slot="console-input-group" class="input-group"
                        ><span>网站ID</span
                        ><Input
                            v-model="resolveFilters.site_id"
                            placeholder="请输入网站ID"
                            @change="loadResolve(1)"
                    /></label>
                    <Button
                        type="button"
                        variant="link"
                        @click="clearResolveFilters"
                        >清除</Button
                    >
                </form>
                <div class="sites-table-scroll">
                    <table
                        class="sites-table"
                        :class="{
                            'site-list-table': activeTab === 'sites',
                            'resolve-table': activeTab === 'resolve',
                        }"
                    >
                        <colgroup>
                            <col style="width: 60px" />
                            <col
                                v-for="column in tableColumns"
                                :key="column.key"
                                :style="
                                    column.width
                                        ? { width: column.width + 'px' }
                                        : {}
                                "
                            />
                        </colgroup>
                        <thead>
                            <tr>
                                <th>
                                    <CheckboxField
                                        aria-label="选择当前页"
                                        :checked="allSelected"
                                        :disabled="
                                            loading ||
                                            busy ||
                                            !activeRows.length
                                        "
                                        @change="selectAll"
                                    />
                                </th>
                                <th
                                    v-for="column in tableColumns"
                                    :key="column.key"
                                >
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td
                                    :colspan="tableColumns.length + 1"
                                    class="empty-cell"
                                >
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <template v-else
                                ><tr
                                    v-for="row in activeRows"
                                    :key="textValue(row.id)"
                                >
                                    <td>
                                        <CheckboxField
                                            :aria-label="`选择 ${row.id}`"
                                            :checked="
                                                activeTab === 'resolve'
                                                    ? resolveSelected.has(
                                                          Number(row.id),
                                                      )
                                                    : selected.includes(
                                                          Number(row.id),
                                                      )
                                            "
                                            :disabled="busy"
                                            @change="selectRow(Number(row.id))"
                                        />
                                    </td>
                                    <td
                                        v-for="column in tableColumns"
                                        :key="column.key"
                                    >
                                        <template
                                            v-if="column.key === 'actions'"
                                            ><div class="row-actions">
                                                <Button
                                                    variant="link"
                                                    :disabled="busy"
                                                    @click="editRow(row)"
                                                    >{{
                                                        activeTab === 'sites'
                                                            ? '管理'
                                                            : '编辑'
                                                    }}</Button
                                                >
                                                <DropdownMenu
                                                    v-if="activeTab === 'sites'"
                                                    ><DropdownMenuTrigger
                                                        as-child
                                                        ><Button
                                                            variant="link"
                                                            :disabled="busy"
                                                            >更多<ChevronDown
                                                                class="size-4" /></Button></DropdownMenuTrigger
                                                    ><DropdownMenuContent
                                                        class="console-user-sites"
                                                        ><DropdownMenuItem
                                                            @select="
                                                                toggleSiteEnabled(
                                                                    row,
                                                                    false,
                                                                )
                                                            "
                                                            >禁用</DropdownMenuItem
                                                        ><DropdownMenuItem
                                                            @select="
                                                                toggleSiteEnabled(
                                                                    row,
                                                                    true,
                                                                )
                                                            "
                                                            >启用</DropdownMenuItem
                                                        ><DropdownMenuItem
                                                            @select="
                                                                openDelete(
                                                                    row,
                                                                    `网站「${siteName(row)}」`,
                                                                )
                                                            "
                                                            >删除</DropdownMenuItem
                                                        ></DropdownMenuContent
                                                    ></DropdownMenu
                                                >
                                                <Button
                                                    v-else
                                                    variant="link"
                                                    :disabled="busy"
                                                    @click="
                                                        openDelete(
                                                            row,
                                                            recordName(row),
                                                        )
                                                    "
                                                    >删除</Button
                                                >
                                            </div></template
                                        >
                                        <Button
                                            v-else-if="
                                                activeTab === 'sites' &&
                                                column.key === 'domain'
                                            "
                                            variant="link"
                                            class="domain-link"
                                            @click="openSiteEdit(row)"
                                            >{{ siteName(row) }}</Button
                                        >
                                        <template
                                            v-else-if="column.key === 'https'"
                                            ><CheckCircle2
                                                v-if="hasHttps(row)"
                                                class="status-icon text-emerald-500"
                                                aria-label="HTTPS已启用" /><XCircle
                                                v-else
                                                class="status-icon text-muted-foreground"
                                                aria-label="HTTPS未启用"
                                        /></template>
                                        <span
                                            v-else-if="column.key === 'status'"
                                            class="site-status"
                                            :data-tone="siteStatus(row).tone"
                                            ><i />{{
                                                siteStatus(row).text
                                            }}</span
                                        >
                                        <template
                                            v-else-if="column.key === 'resolve'"
                                            ><LoaderCircle
                                                v-if="
                                                    resolveCheckState(row) ===
                                                    'checking'
                                                "
                                                class="status-icon animate-spin"
                                                aria-label="检测中" /><CheckCircle2
                                                v-else-if="
                                                    resolveCheckState(row) ===
                                                    'resolved'
                                                "
                                                class="status-icon text-emerald-500"
                                                aria-label="解析正确" /><XCircle
                                                v-else
                                                class="status-icon text-red-500"
                                                :aria-label="
                                                    resolveCheckState(row) ===
                                                    'error'
                                                        ? '检测失败'
                                                        : '解析错误'
                                                "
                                        /></template>
                                        <span
                                            v-else
                                            class="cell-value"
                                            :title="cellValue(row, column.key)"
                                            >{{
                                                cellValue(row, column.key)
                                            }}</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!activeRows.length">
                                    <td
                                        class="empty-cell"
                                        :colspan="tableColumns.length + 1"
                                    >
                                        暂无数据
                                    </td>
                                </tr></template
                            >
                        </tbody>
                    </table>
                </div>
                <PackagePagination
                    class="sites-pagination"
                    :page="currentPage"
                    :page-size="currentSize"
                    :total="currentTotal"
                    :disabled="loading || busy"
                    numbered
                    edge-links
                    @update:page="loadActive($event)"
                    @update:page-size="changePageSize"
                />
            </div>
        </section>
        <Dialog v-model:open="bulkOpen"
            ><DialogScrollContent class="console-user-sites sm:max-w-lg"
                ><DialogHeader
                    ><DialogTitle>{{ bulkTitle }}</DialogTitle
                    ><DialogDescription
                        >已选择 {{ selected.length }} 项</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-4" @submit.prevent="runBulk(bulkAction)">
                    <p
                        data-typography="body"
                        v-if="bulkError"
                        role="alert"
                        class="text-destructive"
                    >
                        {{ bulkError }}
                    </p>
                    <template v-if="bulkAction === 'edit'"
                        ><Label for="bulk-site-field">修改项</Label
                        ><SelectField
                            id="bulk-site-field"
                            v-model="bulkField"
                            class="h-10 rounded border bg-background px-3"
                        >
                            <SelectOption value="groups"
                                >分组ID（逗号分隔）</SelectOption
                            >
                            <SelectOption value="user_package">
                                用户套餐ID
                            </SelectOption></SelectField
                        ><Label for="bulk-site-value">设置值</Label
                        ><Input
                            id="bulk-site-value"
                            v-model="bulkValue"
                            :required="bulkField !== 'groups'"
                    /></template>
                    <p data-typography="body" v-else>
                        {{
                            bulkAction === 'delete'
                                ? '确认删除所选记录？删除后不可恢复。网站需先禁用。'
                                : bulkAction === 'unlock'
                                  ? '解锁所选网站的全部黑名单IP。'
                                  : bulkAction === 'cache'
                                    ? '清空所选网站的HTTP和HTTPS缓存。'
                                    : '为未开启HTTPS的网站申请免费证书并绑定。'
                        }}
                    </p>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="busy"
                            @click="bulkOpen = false"
                            >取消</Button
                        ><Button type="submit" :disabled="busy">{{
                            busy ? '处理中…' : '确定'
                        }}</Button></DialogFooter
                    >
                </form>
            </DialogScrollContent></Dialog
        >

        <Dialog v-model:open="siteDialogOpen">
            <DialogScrollContent class="console-user-sites sm:max-w-3xl">
                <DialogHeader
                    ><DialogTitle>{{
                        editingSite ? '编辑站点' : '创建站点'
                    }}</DialogTitle
                    ><DialogDescription
                        >域名、套餐、源站地址等。</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-5" @submit.prevent="submitSite">
                    <Alert v-if="formError" variant="destructive"
                        ><AlertCircle data-icon="alert" /><AlertDescription>{{
                            formError
                        }}</AlertDescription></Alert
                    >
                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="site-domain">域名</Label
                            ><Input
                                id="site-domain"
                                v-model="siteForm.domain"
                                placeholder="www.example.com"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="site-package">套餐</Label>
                            <Select
                                v-model="siteForm.user_package"
                                @open-change="
                                    (open: boolean) =>
                                        open &&
                                        userPackages.length === 0 &&
                                        loadUserPackages()
                                "
                            >
                                <SelectTrigger id="site-package"
                                    ><SelectValue placeholder="选择已购套餐"
                                /></SelectTrigger>
                                <SelectContent class="console-user-sites"
                                    ><SelectGroup
                                        ><SelectItem
                                            v-for="pkg in userPackages"
                                            :key="pkg.id"
                                            :value="String(pkg.id)"
                                            ><span
                                                class="flex w-full items-center justify-between gap-3"
                                                ><span>{{ pkg.label }}</span
                                                ><span
                                                    class="text-xs text-muted-foreground"
                                                    >{{ pkg.hint }}</span
                                                ></span
                                            ></SelectItem
                                        ></SelectGroup
                                    ></SelectContent
                                >
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="site-backend">源站地址</Label
                            ><Input
                                id="site-backend"
                                v-model="siteForm.backend_addr"
                                placeholder="1.1.1.1"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>状态</Label
                            ><Select v-model="siteForm.enable"
                                ><SelectTrigger><SelectValue /></SelectTrigger
                                ><SelectContent class="console-user-sites"
                                    ><SelectGroup
                                        ><SelectItem value="1">启用</SelectItem
                                        ><SelectItem value="0"
                                            >禁用</SelectItem
                                        ></SelectGroup
                                    ></SelectContent
                                ></Select
                            >
                        </div>
                        <div class="border-t pt-3">
                            <Button
                                variant="ghost"
                                type="button"
                                class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                                @click="showAdvanced = !showAdvanced"
                            >
                                <span>{{ showAdvanced ? '▲' : '▼' }}</span
                                >可选配置
                            </Button>
                            <div v-if="showAdvanced" class="mt-3 grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="site-groups">所属分组</Label
                                    ><Input
                                        id="site-groups"
                                        v-model="siteForm.groups"
                                        placeholder="分组ID，多个逗号分隔"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            @click="siteDialogOpen = false"
                            >取消</Button
                        >
                        <Button :disabled="saving" type="submit"
                            ><Spinner
                                v-if="saving"
                                data-icon="inline-start"
                            /><Save
                                v-else
                                data-icon="inline-start"
                            />保存</Button
                        >
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ── 分组弹窗 ── -->
        <Dialog v-model:open="groupDialogOpen">
            <DialogScrollContent class="console-user-sites sm:max-w-lg">
                <DialogHeader
                    ><DialogTitle>{{
                        editingGroup ? '编辑分组' : '新建分组'
                    }}</DialogTitle></DialogHeader
                >
                <form class="grid gap-5" @submit.prevent="submitGroup">
                    <Alert v-if="formError" variant="destructive"
                        ><AlertCircle data-icon="alert" /><AlertDescription>{{
                            formError
                        }}</AlertDescription></Alert
                    >
                    <div class="grid gap-2">
                        <Label>分组名称</Label
                        ><Input v-model="groupForm.name" required />
                    </div>
                    <div class="grid gap-2">
                        <Label>备注</Label><Input v-model="groupForm.des" />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            @click="groupDialogOpen = false"
                            >取消</Button
                        >
                        <Button :disabled="saving" type="submit"
                            ><Spinner
                                v-if="saving"
                                data-icon="inline-start"
                            /><Save
                                v-else
                                data-icon="inline-start"
                            />保存</Button
                        >
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ── 默认设置弹窗 ── -->
        <Dialog v-model:open="configDialogOpen">
            <DialogScrollContent class="console-user-sites sm:max-w-2xl">
                <DialogHeader
                    ><DialogTitle>{{
                        editingConfig ? '编辑配置' : '添加配置'
                    }}</DialogTitle
                    ><DialogDescription
                        >新建站点时将自动应用这些默认值。</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-5" @submit.prevent="submitConfig">
                    <Alert v-if="formError" variant="destructive"
                        ><AlertCircle data-icon="alert" /><AlertDescription>{{
                            formError
                        }}</AlertDescription></Alert
                    >
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>配置类别</Label>
                            <Select v-model="configForm.type"
                                ><SelectTrigger><SelectValue /></SelectTrigger
                                ><SelectContent class="console-user-sites"
                                    ><SelectGroup
                                        ><SelectItem
                                            v-for="t in CONFIG_TYPE_OPTIONS"
                                            :key="t.value"
                                            :value="t.value"
                                            >{{ t.label }}</SelectItem
                                        ></SelectGroup
                                    ></SelectContent
                                ></Select
                            >
                        </div>
                        <div class="grid gap-2">
                            <Label>配置项</Label>
                            <Select v-model="configForm.name"
                                ><SelectTrigger
                                    ><SelectValue
                                        placeholder="选择配置项" /></SelectTrigger
                                ><SelectContent class="console-user-sites"
                                    ><SelectGroup
                                        ><SelectItem
                                            v-for="c in SITE_CONFIG_NAMES"
                                            :key="c.value"
                                            :value="c.value"
                                            >{{ c.label }}</SelectItem
                                        ></SelectGroup
                                    ></SelectContent
                                ></Select
                            >
                        </div>
                    </div>
                    <!-- 结构化 value 输入 -->
                    <div v-if="configForm.name" class="grid gap-2">
                        <Label
                            >配置值
                            <span
                                class="ml-1 text-xs font-normal text-muted-foreground"
                                >{{ configForm.name }}</span
                            ></Label
                        >
                        <template
                            v-if="currentConfigMeta?.valueType === 'boolean'"
                        >
                            <Select v-model="configForm.value"
                                ><SelectTrigger><SelectValue /></SelectTrigger
                                ><SelectContent class="console-user-sites"
                                    ><SelectGroup
                                        ><SelectItem value="1">开启</SelectItem
                                        ><SelectItem value="0"
                                            >关闭</SelectItem
                                        ></SelectGroup
                                    ></SelectContent
                                ></Select
                            >
                        </template>
                        <template
                            v-else-if="
                                currentConfigMeta?.valueType === 'number'
                            "
                        >
                            <Input
                                v-model="configForm.value"
                                inputmode="numeric"
                            />
                        </template>
                        <template
                            v-else-if="
                                currentConfigMeta?.valueType === 'select'
                            "
                        >
                            <Select v-model="configForm.value"
                                ><SelectTrigger><SelectValue /></SelectTrigger
                                ><SelectContent class="console-user-sites"
                                    ><SelectGroup
                                        ><SelectItem
                                            v-for="o in currentConfigMeta.options"
                                            :key="o"
                                            :value="o"
                                            >{{ o }}</SelectItem
                                        ></SelectGroup
                                    ></SelectContent
                                ></Select
                            >
                        </template>
                        <template
                            v-else-if="currentConfigMeta?.valueType === 'json'"
                        >
                            <Textarea
                                v-model="configForm.value"
                                class="min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                spellcheck="false"
                            />
                        </template>
                        <template v-else>
                            <Input v-model="configForm.value" />
                        </template>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label>生效范围</Label>
                            <Select v-model="configForm.scope_name"
                                ><SelectTrigger><SelectValue /></SelectTrigger
                                ><SelectContent class="console-user-sites"
                                    ><SelectGroup
                                        ><SelectItem value="global"
                                            >全局</SelectItem
                                        ><SelectItem value="group"
                                            >分组</SelectItem
                                        ></SelectGroup
                                    ></SelectContent
                                ></Select
                            >
                        </div>
                        <div
                            v-if="configForm.scope_name === 'group'"
                            class="grid gap-2"
                        >
                            <Label>分组</Label>
                            <Select v-model="configForm.scope_id"
                                ><SelectTrigger
                                    ><SelectValue
                                        placeholder="选择分组" /></SelectTrigger
                                ><SelectContent class="console-user-sites"
                                    ><SelectGroup
                                        ><SelectItem
                                            v-for="g in siteGroupOptions"
                                            :key="g.id"
                                            :value="g.id"
                                            >#{{ g.id }}
                                            {{ g.name }}</SelectItem
                                        ></SelectGroup
                                    ></SelectContent
                                ></Select
                            >
                        </div>
                        <div class="grid gap-2">
                            <Label>状态</Label>
                            <Select v-model="configForm.enable"
                                ><SelectTrigger><SelectValue /></SelectTrigger
                                ><SelectContent class="console-user-sites"
                                    ><SelectGroup
                                        ><SelectItem value="1">启用</SelectItem
                                        ><SelectItem value="0"
                                            >禁用</SelectItem
                                        ></SelectGroup
                                    ></SelectContent
                                ></Select
                            >
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            @click="configDialogOpen = false"
                            >取消</Button
                        >
                        <Button :disabled="saving" type="submit"
                            ><Spinner
                                v-if="saving"
                                data-icon="inline-start"
                            /><Save
                                v-else
                                data-icon="inline-start"
                            />保存</Button
                        >
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ── DNS API 弹窗 ── -->
        <Dialog v-model:open="dnsDialogOpen">
            <DialogScrollContent class="console-user-sites sm:max-w-2xl">
                <DialogHeader
                    ><DialogTitle>{{
                        editingDns ? '编辑 DNS API' : '新增 DNS API'
                    }}</DialogTitle
                    ><DialogDescription
                        >auth 字段以 JSON 格式提交。</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-5" @submit.prevent="submitDns">
                    <Alert v-if="formError" variant="destructive"
                        ><AlertCircle data-icon="alert" /><AlertDescription>{{
                            formError
                        }}</AlertDescription></Alert
                    >
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>名称</Label
                            ><Input v-model="dnsForm.name" required />
                        </div>
                        <div class="grid gap-2">
                            <Label>服务商</Label>
                            <Select
                                v-model="dnsForm.type"
                                @update:model-value="applyAuthTemplate"
                                ><SelectTrigger><SelectValue /></SelectTrigger
                                ><SelectContent class="console-user-sites"
                                    ><SelectGroup
                                        ><SelectItem
                                            v-for="t in DNS_TYPES"
                                            :key="t"
                                            :value="t"
                                            >{{ t }}</SelectItem
                                        ></SelectGroup
                                    ></SelectContent
                                ></Select
                            >
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>auth JSON</Label>
                        <Textarea
                            v-model="dnsForm.auth"
                            class="min-h-40 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            spellcheck="false"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>备注</Label><Input v-model="dnsForm.des" />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            @click="dnsDialogOpen = false"
                            >取消</Button
                        >
                        <Button :disabled="saving" type="submit"
                            ><Spinner
                                v-if="saving"
                                data-icon="inline-start"
                            /><Save
                                v-else
                                data-icon="inline-start"
                            />保存</Button
                        >
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ── 通用删除确认 ── -->
        <ConfirmDeleteDialog
            :open="deleteOpen"
            :description="`确认删除${deleteLabel}？删除后不可恢复。`"
            :loading="deleting"
            :error="deleteError"
            @confirm="confirmDelete"
            @cancel="deleteOpen = false"
        />
    </div>
</template>
