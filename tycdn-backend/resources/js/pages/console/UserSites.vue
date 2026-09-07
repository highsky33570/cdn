<script setup lang="ts">
import {
    AlertCircle,
    CheckCircle2,
    FolderOpen,
    Globe2,
    KeyRound,
    Pencil,
    Plus,
    RefreshCw,
    Save,
    Search,
    Settings,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
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
    createUserDnsApi,
    createUserSite,
    createUserSiteGroup,
    deleteUserConfig,
    deleteUserDnsApi,
    deleteUserSite,
    deleteUserSiteGroup,
    extractCdnflyRows,
    extractCdnflyTotal,
    listUserConfigs,
    listUserDnsApis,
    listUserDomains,
    listUserPackages,
    listUserSiteGroups,
    listUserSites,
    postCnameCheck,
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

const props = withDefaults(defineProps<{ initialTab?: SiteTab }>(), { initialTab: 'sites' });

const TABS: { key: SiteTab; label: string }[] = [
    { key: 'sites', label: '网站列表' },
    { key: 'groups', label: '分组管理' },
    { key: 'defaults', label: '默认设置' },
    { key: 'dnsapi', label: 'DNS API' },
    { key: 'resolve', label: '解析检测' },
];

const ENABLE_ALL = 'all';

const DNS_TYPES = [
    'CloudFlare', 'DNSPod.cn', 'GoDaddy.com', 'Aliyun',
    'cloudns.net', 'Name.com', 'Namecheap', 'jdcloud.com', 'dnsdun',
] as const;

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

type ConfigValueType = 'number' | 'boolean' | 'select' | 'text' | 'json';
type ConfigMeta = { value: string; label: string; valueType: ConfigValueType; options?: string[] };

const SITE_CONFIG_NAMES: ConfigMeta[] = [
    { value: 'http_listen-port', label: 'HTTP 监听端口', valueType: 'number' },
    { value: 'https_listen-port', label: 'HTTPS 监听端口', valueType: 'number' },
    { value: 'backend_http_port', label: '回源 HTTP 端口', valueType: 'number' },
    { value: 'backend_https_port', label: '回源 HTTPS 端口', valueType: 'number' },
    { value: 'proxy_timeout', label: '回源超时', valueType: 'number' },
    { value: 'ups_keepalive_conn', label: '连接池最大空闲数', valueType: 'number' },
    { value: 'ups_keepalive_timeout', label: '空闲连接超时', valueType: 'number' },
    { value: 'post_size_limit', label: '上传最大大小', valueType: 'number' },
    { value: 'recv_real_time', label: '实时接收', valueType: 'number' },
    { value: 'send_real_time', label: '实时发送', valueType: 'number' },
    { value: 'https_listen-hsts', label: '开启 HSTS', valueType: 'boolean' },
    { value: 'https_listen-http2', label: '开启 HTTP/2', valueType: 'boolean' },
    { value: 'https_listen-force_ssl_enable', label: '强制 HTTPS', valueType: 'boolean' },
    { value: 'ups_keepalive', label: '启用回源连接池', valueType: 'boolean' },
    { value: 'range', label: '分片回源', valueType: 'boolean' },
    { value: 'gzip_enable', label: '启用 Gzip', valueType: 'boolean' },
    { value: 'websocket_enable', label: '开启 WebSocket', valueType: 'boolean' },
    { value: 'block_proxy', label: '屏蔽代理', valueType: 'boolean' },
    { value: 'https_listen-ocsp_stapling', label: '开启 OCSP Stapling', valueType: 'boolean' },
    { value: 'https_listen-ssl_prefer_server_ciphers', label: 'SSL 优先服务器密码', valueType: 'boolean' },
    { value: 'backend_protocol', label: '回源协议', valueType: 'select', options: ['http', 'https'] },
    { value: 'proxy_http_version', label: '回源 HTTP 版本', valueType: 'select', options: ['1.0', '1.1'] },
    { value: 'balance_way', label: '负载方式', valueType: 'select', options: ['ip_hash', 'round_robin', 'least_conn'] },
    { value: 'proxy_ssl_protocols', label: '回源 SSL 协议', valueType: 'text' },
    { value: 'https_listen-ssl_protocols', label: 'SSL 协议', valueType: 'text' },
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
const userPackages = ref<{ id: number; name: string }[]>([]);
const siteFilters = reactive({ domain: '', enable: ENABLE_ALL, per_page: '20' });
const siteForm = reactive({ domain: '', user_package: '', backend_addr: '', groups: '', enable: '1' });

// ── 分组管理 ──
const groupDialogOpen = ref(false);
const editingGroup = ref<CdnflyRecord | null>(null);
const groupPage = ref(1);
const groupTotal = ref(0);
const groupRows = ref<CdnflyRecord[]>([]);
const groupForm = reactive({ name: '', des: '' });

// ── 默认设置 ──
const configDialogOpen = ref(false);
const editingConfig = ref<CdnflyRecord | null>(null);
const configPage = ref(1);
const configTotal = ref(0);
const configRows = ref<CdnflyRecord[]>([]);
const configFilters = reactive({ type: 'all', per_page: '20' });
const configForm = reactive({ type: 'site', name: '', value: '', scope_name: 'global', scope_id: '', enable: '1' });
const siteGroupOptions = ref<{ id: string; name: string }[]>([]);

// ── DNS API ──
const dnsDialogOpen = ref(false);
const editingDns = ref<CdnflyRecord | null>(null);
const dnsPage = ref(1);
const dnsTotal = ref(0);
const dnsRows = ref<CdnflyRecord[]>([]);
const dnsFilters = reactive({ search: '', per_page: '20' });
const dnsForm = reactive({ name: '', type: 'CloudFlare', auth: jsonText(AUTH_TEMPLATES.CloudFlare, '{}'), des: '' });

// ── 解析检测 ──
const resolvePage = ref(1);
const resolveTotal = ref(0);
const resolveRows = ref<CdnflyRecord[]>([]);
const resolveFilters = reactive({ domain: '', site_id: '', per_page: '20' });
const resolveSelected = ref<Set<number>>(new Set());
const resolveChecking = ref(false);
const resolveSelectAll = ref(false);

const currentConfigMeta = computed(() =>
    SITE_CONFIG_NAMES.find(c => c.value === configForm.name),
);

const displayedDnsRows = computed(() => {
    const kw = dnsFilters.search.trim().toLowerCase();
    if (!kw) return dnsRows.value;
    return dnsRows.value.filter(r =>
        [r.name, r.type, r.des].map(v => textValue(v).toLowerCase()).some(v => v.includes(kw)),
    );
});

watch(activeTab, (tab) => {
    errorMessage.value = '';
    if (tab === 'sites' && siteRows.value.length === 0) void loadSites();
    if (tab === 'groups' && groupRows.value.length === 0) void loadGroups();
    if (tab === 'defaults' && configRows.value.length === 0) void loadConfigs();
    if (tab === 'dnsapi' && dnsRows.value.length === 0) void loadDns();
    if (tab === 'resolve' && resolveRows.value.length === 0) void loadResolve();
});

onMounted(() => { void loadSites(); });

// ── 网站列表 ──
async function loadSites(p = sitePage.value) {
    loading.value = true; errorMessage.value = '';
    try {
        const params: Record<string, string | number> = { page: p, limit: Number(siteFilters.per_page) };
        if (siteFilters.domain.trim()) params.domain = siteFilters.domain.trim();
        if (siteFilters.enable !== ENABLE_ALL) params.enable = siteFilters.enable;
        const r = await listUserSites(params);
        siteRows.value = extractCdnflyRows(r);
        siteTotal.value = extractCdnflyTotal(r, siteRows.value.length);
        sitePage.value = p;
    } catch (e) { errorMessage.value = getErrorMessage(e); }
    finally { loading.value = false; }
}

async function loadUserPackages() {
    try {
        const r = await listUserPackages({ limit: 200 });
        userPackages.value = extractCdnflyRows(r).map(row => ({
            id: Number(row.id ?? 0),
            name: String(row.name ?? row.package_name ?? row.id ?? '-'),
        }));
    } catch { userPackages.value = []; }
}

function openSiteCreate() {
    editingSite.value = null;
    siteForm.domain = ''; siteForm.user_package = ''; siteForm.backend_addr = ''; siteForm.groups = ''; siteForm.enable = '1';
    formError.value = ''; showAdvanced.value = false; siteDialogOpen.value = true;
    if (userPackages.value.length === 0) void loadUserPackages();
}

function openSiteEdit(s: CdnflyRecord) {
    editingSite.value = s;
    siteForm.domain = textValue(s.domain);
    siteForm.user_package = idField(s.user_package);
    const b = s.backend;
    siteForm.backend_addr = Array.isArray(b) && b.length > 0 ? String((b[0] as Record<string, unknown>).addr ?? '') : '';
    siteForm.groups = textValue(s.groups);
    siteForm.enable = s.enable === 0 || s.enable === false ? '0' : '1';
    formError.value = ''; showAdvanced.value = false; siteDialogOpen.value = true;
}

async function submitSite() {
    const payload = {
        domain: siteForm.domain.trim(),
        user_package: Number(siteForm.user_package),
        backend: siteForm.backend_addr.trim() ? [{ addr: siteForm.backend_addr.trim() }] : [],
        groups: siteForm.groups.trim() || undefined,
        enable: siteForm.enable === '1' ? 1 : 0,
    };
    if (!editingSite.value) {
        if (!payload.domain) { formError.value = '域名不能为空'; return; }
        if (!payload.user_package) { formError.value = '套餐 ID 必须填写'; return; }
        if (!payload.backend.length) { formError.value = '源站地址不能为空'; return; }
    }
    saving.value = true; formError.value = '';
    try {
        if (editingSite.value) {
            const id = numberValue(editingSite.value.id);
            if (!id) { formError.value = '站点 ID 缺失'; return; }
            await updateUserSite(id, payload);
            toast.success('站点更新请求已提交');
        } else {
            await createUserSite(payload);
            toast.success('站点创建请求已提交');
        }
        siteDialogOpen.value = false; await loadSites();
    } catch (e) { formError.value = getErrorMessage(e); }
    finally { saving.value = false; }
}

async function toggleSiteEnabled(s: CdnflyRecord, checked: boolean) {
    const id = numberValue(s.id); if (!id) return;
    togglingId.value = id;
    try { await updateUserSite(id, { enable: checked ? 1 : 0 }); toast.success(checked ? '站点已启用' : '站点已停用'); await loadSites(); }
    catch (e) { toast.error(getErrorMessage(e)); }
    finally { togglingId.value = null; }
}

// ── 分组管理 ──
async function loadGroups(p = groupPage.value) {
    loading.value = true; errorMessage.value = '';
    try {
        const r = await listUserSiteGroups({ page: p, limit: 20 });
        groupRows.value = extractCdnflyRows(r);
        groupTotal.value = extractCdnflyTotal(r, groupRows.value.length);
        groupPage.value = p;
    } catch (e) { errorMessage.value = getErrorMessage(e); }
    finally { loading.value = false; }
}

function openGroupCreate() {
    editingGroup.value = null; groupForm.name = ''; groupForm.des = '';
    formError.value = ''; groupDialogOpen.value = true;
}

function openGroupEdit(r: CdnflyRecord) {
    editingGroup.value = r; groupForm.name = textValue(r.name); groupForm.des = textValue(r.des);
    formError.value = ''; groupDialogOpen.value = true;
}

async function submitGroup() {
    const name = groupForm.name.trim();
    if (!name) { formError.value = '分组名称不能为空'; return; }
    const payload: CdnSiteGroupPayload = { name, des: groupForm.des.trim() || null };
    saving.value = true; formError.value = '';
    try {
        if (editingGroup.value) {
            const id = recordId(editingGroup.value);
            if (!id) { formError.value = 'ID 缺失'; return; }
            await updateUserSiteGroup(id, payload);
            toast.success('分组更新成功');
        } else {
            await createUserSiteGroup(payload);
            toast.success('分组创建成功');
        }
        groupDialogOpen.value = false; await loadGroups();
    } catch (e) { formError.value = getErrorMessage(e); }
    finally { saving.value = false; }
}

// ── 默认设置 ──
async function loadConfigs(p = configPage.value) {
    loading.value = true; errorMessage.value = '';
    try {
        const params: Record<string, string | number> = { page: p, limit: Number(configFilters.per_page) };
        if (configFilters.type !== 'all') params.type = configFilters.type;
        const r = await listUserConfigs(params);
        configRows.value = extractCdnflyRows(r);
        configTotal.value = extractCdnflyTotal(r, configRows.value.length);
        configPage.value = p;
    } catch (e) { errorMessage.value = getErrorMessage(e); }
    finally { loading.value = false; }
}

async function loadSiteGroupOptions() {
    try {
        const r = await listUserSiteGroups({ limit: 200 });
        siteGroupOptions.value = extractCdnflyRows(r).map(row => ({ id: String(row.id), name: textValue(row.name) }));
    } catch { siteGroupOptions.value = []; }
}

function openConfigCreate() {
    editingConfig.value = null;
    configForm.type = 'site'; configForm.name = ''; configForm.value = '';
    configForm.scope_name = 'global'; configForm.scope_id = ''; configForm.enable = '1';
    formError.value = ''; configDialogOpen.value = true;
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
    formError.value = ''; configDialogOpen.value = true;
    void loadSiteGroupOptions();
}

function configValueForSubmit(): string {
    const meta = currentConfigMeta.value;
    if (!meta) return configForm.value;
    if (meta.valueType === 'boolean') return configForm.value === '1' ? '1' : '0';
    return configForm.value;
}

async function submitConfig() {
    if (!configForm.name) { formError.value = '请选择配置项'; return; }
    const payload: CdnUserConfigPayload = {
        type: configForm.type,
        name: configForm.name,
        value: configValueForSubmit(),
        scope_name: configForm.scope_name,
        scope_id: configForm.scope_name === 'group' ? Number(configForm.scope_id) || null : null,
        enable: configForm.enable === '1' ? 1 : 0,
    };
    saving.value = true; formError.value = '';
    try {
        if (editingConfig.value) {
            const id = recordId(editingConfig.value);
            if (!id) { formError.value = 'ID 缺失'; return; }
            await updateUserConfig(id, payload);
            toast.success('配置更新成功');
        } else {
            await createUserConfig(payload);
            toast.success('配置创建成功');
        }
        configDialogOpen.value = false; await loadConfigs();
    } catch (e) { formError.value = getErrorMessage(e); }
    finally { saving.value = false; }
}

function configLabel(name: string): string {
    return SITE_CONFIG_NAMES.find(c => c.value === name)?.label ?? name;
}

// ── DNS API ──
async function loadDns(p = dnsPage.value) {
    loading.value = true; errorMessage.value = '';
    try {
        const r = await listUserDnsApis({ page: p, limit: Number(dnsFilters.per_page) });
        dnsRows.value = extractCdnflyRows(r);
        dnsTotal.value = extractCdnflyTotal(r, dnsRows.value.length);
        dnsPage.value = p;
    } catch (e) { errorMessage.value = getErrorMessage(e); }
    finally { loading.value = false; }
}

function openDnsCreate() {
    editingDns.value = null;
    dnsForm.name = ''; dnsForm.type = 'CloudFlare';
    dnsForm.auth = jsonText(AUTH_TEMPLATES.CloudFlare, '{}'); dnsForm.des = '';
    formError.value = ''; dnsDialogOpen.value = true;
}

function openDnsEdit(r: CdnflyRecord) {
    editingDns.value = r;
    dnsForm.name = textValue(r.name);
    dnsForm.type = textValue(r.type) || 'CloudFlare';
    dnsForm.auth = jsonText(r.auth, jsonText(AUTH_TEMPLATES[dnsForm.type], '{}'));
    dnsForm.des = textValue(r.des ?? r.remark);
    formError.value = ''; dnsDialogOpen.value = true;
}

function applyAuthTemplate() {
    dnsForm.auth = jsonText(AUTH_TEMPLATES[dnsForm.type] ?? {}, '{}');
}

async function submitDns() {
    let payload: CdnDnsApiPayload;
    try {
        payload = { name: dnsForm.name.trim(), type: dnsForm.type, auth: parseJsonObject(dnsForm.auth), des: dnsForm.des.trim() || null };
    } catch (e) { formError.value = getErrorMessage(e); return; }
    if (!payload.name) { formError.value = '名称不能为空'; return; }
    saving.value = true; formError.value = '';
    try {
        if (editingDns.value) {
            const id = recordId(editingDns.value);
            if (!id) { formError.value = 'ID 缺失'; return; }
            await updateUserDnsApi(id, payload);
            toast.success('DNS API 更新已提交');
        } else {
            await createUserDnsApi(payload);
            toast.success('DNS API 创建已提交');
        }
        dnsDialogOpen.value = false; await loadDns();
    } catch (e) { formError.value = getErrorMessage(e); }
    finally { saving.value = false; }
}

function maskedAuth(r: CdnflyRecord): string {
    const auth = r.auth;
    if (!auth) return '-';
    if (typeof auth === 'string') { try { return Object.keys(JSON.parse(auth)).join(', ') || '-'; } catch { return '已配置'; } }
    if (typeof auth === 'object' && !Array.isArray(auth)) return Object.keys(auth).join(', ') || '-';
    return '已配置';
}

// ── 解析检测 ──
async function loadResolve(p = resolvePage.value) {
    loading.value = true; errorMessage.value = '';
    try {
        const params: Record<string, string | number> = { page: p, limit: Number(resolveFilters.per_page) };
        if (resolveFilters.domain.trim()) params.domain = resolveFilters.domain.trim();
        if (resolveFilters.site_id.trim()) params.site_id = resolveFilters.site_id.trim();
        const r = await listUserDomains(params);
        resolveRows.value = extractCdnflyRows(r);
        resolveTotal.value = extractCdnflyTotal(r, resolveRows.value.length);
        resolvePage.value = p;
        resolveSelected.value.clear();
        resolveSelectAll.value = false;
    } catch (e) { errorMessage.value = getErrorMessage(e); }
    finally { loading.value = false; }
}

function toggleResolveSelect(id: number) {
    const s = resolveSelected.value;
    if (s.has(id)) s.delete(id); else s.add(id);
    resolveSelectAll.value = resolveRows.value.length > 0 && s.size === resolveRows.value.length;
}

function toggleResolveSelectAll() {
    if (resolveSelectAll.value) {
        resolveSelected.value.clear(); resolveSelectAll.value = false;
    } else {
        resolveSelected.value = new Set(resolveRows.value.map(r => Number(r.id)));
        resolveSelectAll.value = true;
    }
}

async function syncResolve() {
    if (resolveSelected.value.size === 0) { toast.error('请先选择域名'); return; }
    const data: Record<string, { cname: string; domain: string }> = {};
    for (const id of resolveSelected.value) {
        const row = resolveRows.value.find(r => Number(r.id) === id);
        if (row) data[String(id)] = { cname: textValue(row.cname), domain: textValue(row.domain) };
    }
    resolveChecking.value = true;
    try {
        await postCnameCheck(data);
        toast.success('同步解析任务已提交');
        await loadResolve();
    } catch (e) { errorMessage.value = getErrorMessage(e); }
    finally { resolveChecking.value = false; }
}

// ── 通用删除 ──
function openDelete(record: CdnflyRecord, label: string) {
    deleteTarget.value = record; deleteLabel.value = label;
    deleteError.value = ''; deleteOpen.value = true;
}

async function confirmDelete() {
    const id = recordId(deleteTarget.value!); if (!id) return;
    deleting.value = true;
    try {
        if (activeTab.value === 'sites') { await deleteUserSite(id); toast.success('站点已删除'); deleteOpen.value = false; await loadSites(); }
        else if (activeTab.value === 'groups') { await deleteUserSiteGroup(id); toast.success('分组已删除'); deleteOpen.value = false; await loadGroups(); }
        else if (activeTab.value === 'defaults') { await deleteUserConfig(id); toast.success('配置已删除'); deleteOpen.value = false; await loadConfigs(); }
        else if (activeTab.value === 'dnsapi') { await deleteUserDnsApi(id); toast.success('DNS API 已删除'); deleteOpen.value = false; await loadDns(); }
    } catch (e) { deleteError.value = getErrorMessage(e); }
    finally { deleting.value = false; }
}

// ── 工具 ──
function siteName(s: CdnflyRecord) { return textValue(s.domain) || `#${textValue(s.id)}`; }
function backendText(s: CdnflyRecord) { return jsonText(s.backend, '-').replace(/\s+/g, ' '); }
function fmtDate(v: unknown) { return textValue(v).slice(0, 16) || '-'; }
function idField(v: unknown) { const id = numberValue(v); return id === null ? '' : String(id); }
function recordName(r: CdnflyRecord) { return textValue(r.name) || `#${textValue(r.id)}`; }
function resolveStateLabel(v: unknown) {
    if (v === 1 || v === '1') return '已解析';
    if (v === 0 || v === '0') return '未解析';
    return textValue(v) || '-';
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader title="站点管理" :icon="Globe2" :show-api-badge="false" />

        <!-- Tab bar -->
        <div class="flex gap-1 border-b">
            <button
                v-for="tab in TABS" :key="tab.key" type="button"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
                :class="activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                @click="activeTab = tab.key"
            >{{ tab.label }}</button>
        </div>

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <!-- ═══ 网站列表 ═══ -->
        <template v-if="activeTab === 'sites'">
            <Card class="gap-4">
                <CardContent class="pt-6">
                    <div class="grid gap-3 xl:grid-cols-[1fr_160px_120px_auto]">
                        <div class="relative">
                            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="siteFilters.domain" class="pl-9" placeholder="搜索域名" @keyup.enter="loadSites(1)" />
                        </div>
                        <Select v-model="siteFilters.enable">
                            <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                            <SelectContent><SelectGroup>
                                <SelectItem :value="ENABLE_ALL">全部状态</SelectItem>
                                <SelectItem value="1">启用</SelectItem>
                                <SelectItem value="0">禁用</SelectItem>
                            </SelectGroup></SelectContent>
                        </Select>
                        <Select v-model="siteFilters.per_page">
                            <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                            <SelectContent><SelectGroup>
                                <SelectItem value="20">20 条</SelectItem>
                                <SelectItem value="50">50 条</SelectItem>
                            </SelectGroup></SelectContent>
                        </Select>
                        <div class="flex flex-wrap gap-2">
                            <Button :disabled="loading" @click="loadSites(1)"><Spinner v-if="loading" data-icon="inline-start" /><Search v-else data-icon="inline-start" />搜索</Button>
                            <Button variant="outline" :disabled="loading" @click="loadSites()"><RefreshCw data-icon="inline-start" />刷新</Button>
                            <Button @click="openSiteCreate"><Plus data-icon="inline-start" />创建站点</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="gap-0 overflow-hidden">
                <CardHeader class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-md border bg-card"><Globe2 class="size-5" /></div>
                        <CardTitle class="text-base">站点列表</CardTitle>
                    </div>
                    <div class="text-sm text-muted-foreground">{{ siteTotal === 0 ? '暂无站点' : `${siteTotal} 个站点` }}</div>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[960px] table-fixed text-sm">
                            <colgroup><col style="width:24%"/><col style="width:10%"/><col style="width:12%"/><col style="width:24%"/><col style="width:10%"/><col style="width:10%"/><col style="width:10%"/></colgroup>
                            <thead class="border-y bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-2.5 text-left font-medium">域名</th>
                                    <th class="px-2 py-2.5 text-center font-medium">状态</th>
                                    <th class="px-2 py-2.5 text-center font-medium">用户套餐</th>
                                    <th class="px-3 py-2.5 text-left font-medium">源站</th>
                                    <th class="px-2 py-2.5 text-center font-medium">分组</th>
                                    <th class="px-3 py-2.5 text-left font-medium">创建时间</th>
                                    <th class="px-4 py-2.5 text-right font-medium">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loading && siteRows.length === 0"><td class="px-6 py-16 text-center" colspan="7"><Spinner /></td></tr>
                                <tr v-for="site in siteRows" :key="textValue(site.id)" class="border-b last:border-b-0">
                                    <td class="px-4 py-3"><div class="truncate font-medium">{{ siteName(site) }}</div><div class="text-xs text-muted-foreground">#{{ textValue(site.id) || '-' }}</div></td>
                                    <td class="px-2 py-3 text-center"><Switch :checked="site.enable === 1 || site.enable === '1'" :disabled="togglingId === numberValue(site.id)" @update:checked="toggleSiteEnabled(site, $event)" /></td>
                                    <td class="px-2 py-3 text-center text-muted-foreground">{{ textValue(site.user_package) || '-' }}</td>
                                    <td class="px-3 py-3"><div class="truncate font-mono text-xs">{{ backendText(site) }}</div></td>
                                    <td class="px-2 py-3 text-center text-muted-foreground">{{ textValue(site.groups) || '-' }}</td>
                                    <td class="px-3 py-3 text-muted-foreground">{{ fmtDate(site.create_at2) }}</td>
                                    <td class="px-4 py-3"><div class="flex justify-end gap-1.5">
                                        <Button variant="outline" size="sm" @click="openSiteEdit(site)"><Pencil data-icon="inline-start" />编辑</Button>
                                        <Button variant="destructive" size="sm" @click="openDelete(site, `站点「${siteName(site)}」`)"><Trash2 data-icon="inline-start" />删除</Button>
                                    </div></td>
                                </tr>
                                <tr v-if="!loading && siteRows.length === 0"><td class="px-6 py-16 text-center text-muted-foreground" colspan="7">暂无站点</td></tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
            <div class="flex items-center justify-end gap-2">
                <Button variant="outline" size="sm" :disabled="sitePage <= 1 || loading" @click="loadSites(sitePage - 1)">上一页</Button>
                <span class="text-sm text-muted-foreground">第 {{ sitePage }} 页</span>
                <Button variant="outline" size="sm" :disabled="sitePage * Number(siteFilters.per_page) >= siteTotal || loading" @click="loadSites(sitePage + 1)">下一页</Button>
            </div>
        </template>

        <!-- ═══ 分组管理 ═══ -->
        <template v-else-if="activeTab === 'groups'">
            <div class="flex items-center justify-between">
                <p class="text-sm text-muted-foreground">{{ groupTotal === 0 ? '暂无分组' : `${groupTotal} 个分组` }}</p>
                <div class="flex gap-2">
                    <Button variant="outline" :disabled="loading" @click="loadGroups()"><RefreshCw data-icon="inline-start" />刷新</Button>
                    <Button @click="openGroupCreate"><Plus data-icon="inline-start" />新建分组</Button>
                </div>
            </div>
            <Card>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/30">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground w-20">ID</th>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground">名称</th>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground">备注</th>
                                    <th class="px-4 py-3 text-right font-medium text-muted-foreground w-40">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loading"><td colspan="4" class="px-4 py-12 text-center"><Spinner class="mx-auto" /></td></tr>
                                <tr v-for="g in groupRows" :key="textValue(g.id)" class="border-b last:border-0 hover:bg-muted/20">
                                    <td class="px-4 py-3 text-muted-foreground">{{ textValue(g.id) }}</td>
                                    <td class="px-4 py-3 font-medium">{{ textValue(g.name) }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ textValue(g.des) || '-' }}</td>
                                    <td class="px-4 py-3"><div class="flex justify-end gap-1.5">
                                        <Button variant="outline" size="sm" @click="openGroupEdit(g)"><Pencil data-icon="inline-start" />编辑</Button>
                                        <Button variant="destructive" size="sm" @click="openDelete(g, `分组「${textValue(g.name)}」`)"><Trash2 data-icon="inline-start" />删除</Button>
                                    </div></td>
                                </tr>
                                <tr v-if="!loading && groupRows.length === 0"><td colspan="4" class="px-4 py-16 text-center text-muted-foreground">暂无分组</td></tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
            <div class="flex items-center justify-end gap-2">
                <Button variant="outline" size="sm" :disabled="groupPage <= 1 || loading" @click="loadGroups(groupPage - 1)">上一页</Button>
                <span class="text-sm text-muted-foreground">第 {{ groupPage }} 页</span>
                <Button variant="outline" size="sm" :disabled="groupPage * 20 >= groupTotal || loading" @click="loadGroups(groupPage + 1)">下一页</Button>
            </div>
        </template>

        <!-- ═══ 默认设置 ═══ -->
        <template v-else-if="activeTab === 'defaults'">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <Select v-model="configFilters.type">
                        <SelectTrigger class="w-32"><SelectValue /></SelectTrigger>
                        <SelectContent><SelectGroup>
                            <SelectItem value="all">全部类别</SelectItem>
                            <SelectItem v-for="t in CONFIG_TYPE_OPTIONS" :key="t.value" :value="t.value">{{ t.label }}</SelectItem>
                        </SelectGroup></SelectContent>
                    </Select>
                    <Button :disabled="loading" @click="loadConfigs(1)"><Search data-icon="inline-start" />查询</Button>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" :disabled="loading" @click="loadConfigs()"><RefreshCw data-icon="inline-start" />刷新</Button>
                    <Button @click="openConfigCreate"><Plus data-icon="inline-start" />添加配置</Button>
                </div>
            </div>
            <Card>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/30">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground">配置项</th>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground w-20">类别</th>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground">当前值</th>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground w-28">生效范围</th>
                                    <th class="px-4 py-3 text-center font-medium text-muted-foreground w-20">状态</th>
                                    <th class="px-4 py-3 text-right font-medium text-muted-foreground w-40">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loading"><td colspan="6" class="px-4 py-12 text-center"><Spinner class="mx-auto" /></td></tr>
                                <tr v-for="c in configRows" :key="textValue(c.id)" class="border-b last:border-0 hover:bg-muted/20">
                                    <td class="px-4 py-3"><div class="font-medium">{{ configLabel(textValue(c.name)) }}</div><div class="text-xs text-muted-foreground font-mono">{{ textValue(c.name) }}</div></td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ textValue(c.type) }}</td>
                                    <td class="px-4 py-3"><div class="max-w-xs truncate font-mono text-xs">{{ textValue(c.value) || '-' }}</div></td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ textValue(c.scope_name) === 'group' ? `分组: ${textValue(c.site_group_name) || c.scope_id}` : '全局' }}</td>
                                    <td class="px-4 py-3 text-center"><Badge :variant="c.enable === 1 || c.enable === true ? 'secondary' : 'outline'">{{ c.enable === 1 || c.enable === true ? '启用' : '禁用' }}</Badge></td>
                                    <td class="px-4 py-3"><div class="flex justify-end gap-1.5">
                                        <Button variant="outline" size="sm" @click="openConfigEdit(c)"><Pencil data-icon="inline-start" />编辑</Button>
                                        <Button variant="destructive" size="sm" @click="openDelete(c, `配置「${configLabel(textValue(c.name))}」`)"><Trash2 data-icon="inline-start" />删除</Button>
                                    </div></td>
                                </tr>
                                <tr v-if="!loading && configRows.length === 0"><td colspan="6" class="px-4 py-16 text-center text-muted-foreground">暂无配置</td></tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
            <div class="flex items-center justify-between text-sm text-muted-foreground">
                <span>共 {{ configTotal }} 条</span>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" :disabled="configPage <= 1 || loading" @click="loadConfigs(configPage - 1)">上一页</Button>
                    <span>第 {{ configPage }} 页</span>
                    <Button variant="outline" size="sm" :disabled="configPage * Number(configFilters.per_page) >= configTotal || loading" @click="loadConfigs(configPage + 1)">下一页</Button>
                </div>
            </div>
        </template>

        <!-- ═══ DNS API ═══ -->
        <template v-else-if="activeTab === 'dnsapi'">
            <Card>
                <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div><CardTitle>DNS API 列表</CardTitle><p class="mt-1 text-sm text-muted-foreground">{{ dnsTotal === 0 ? '暂无' : `${dnsTotal} 个 DNS API` }}</p></div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <div class="relative"><Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" /><Input v-model="dnsFilters.search" class="w-full pl-9 sm:w-64" placeholder="搜索名称或服务商" /></div>
                        <Button :disabled="loading" @click="loadDns(1)"><Search data-icon="inline-start" />查询</Button>
                        <Button variant="outline" :disabled="loading" @click="loadDns()"><RefreshCw data-icon="inline-start" />刷新</Button>
                        <Button @click="openDnsCreate"><Plus data-icon="inline-start" />新增</Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto border-y">
                        <table class="w-full min-w-[820px] table-fixed text-sm">
                            <colgroup><col style="width:22%"/><col style="width:16%"/><col style="width:22%"/><col style="width:12%"/><col style="width:14%"/><col style="width:14%"/></colgroup>
                            <thead class="border-b text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium">名称</th>
                                    <th class="px-4 py-3 text-left font-medium">服务商</th>
                                    <th class="px-4 py-3 text-left font-medium">凭据字段</th>
                                    <th class="px-4 py-3 text-center font-medium">状态</th>
                                    <th class="px-4 py-3 text-left font-medium">更新时间</th>
                                    <th class="px-4 py-3 text-right font-medium">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loading"><td class="px-4 py-12 text-center" colspan="6"><Spinner class="mx-auto" /></td></tr>
                                <tr v-for="r in displayedDnsRows" :key="textValue(r.id)" class="border-b">
                                    <td class="px-4 py-3"><div class="font-medium">{{ recordName(r) }}</div><div class="text-xs text-muted-foreground">#{{ textValue(r.id) }}</div></td>
                                    <td class="px-4 py-3">{{ textValue(r.type) || '-' }}</td>
                                    <td class="px-4 py-3 text-muted-foreground truncate">{{ maskedAuth(r) }}</td>
                                    <td class="px-4 py-3 text-center"><Badge variant="outline">{{ r.enable === 0 || r.enable === false ? '禁用' : '可用' }}</Badge></td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ formatDate(r.update_at2 ?? r.created_at) }}</td>
                                    <td class="px-4 py-3"><div class="flex justify-end gap-1.5">
                                        <Button variant="outline" size="sm" @click="openDnsEdit(r)"><Pencil data-icon="inline-start" />编辑</Button>
                                        <Button variant="destructive" size="sm" @click="openDelete(r, `DNS API「${recordName(r)}」`)"><Trash2 data-icon="inline-start" />删除</Button>
                                    </div></td>
                                </tr>
                                <tr v-if="!loading && displayedDnsRows.length === 0"><td class="px-6 py-16 text-center text-muted-foreground" colspan="6">暂无 DNS API</td></tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
            <div class="flex items-center justify-end gap-2">
                <Button variant="outline" size="sm" :disabled="dnsPage <= 1 || loading" @click="loadDns(dnsPage - 1)">上一页</Button>
                <span class="text-sm text-muted-foreground">第 {{ dnsPage }} 页</span>
                <Button variant="outline" size="sm" :disabled="dnsPage * Number(dnsFilters.per_page) >= dnsTotal || loading" @click="loadDns(dnsPage + 1)">下一页</Button>
            </div>
        </template>

        <!-- ═══ 解析检测 ═══ -->
        <template v-else-if="activeTab === 'resolve'">
            <div class="flex flex-wrap items-center gap-2">
                <Button :disabled="resolveChecking || resolveSelected.size === 0" @click="syncResolve">
                    <Spinner v-if="resolveChecking" data-icon="inline-start" /><RefreshCw v-else data-icon="inline-start" />同步解析
                </Button>
                <div class="flex items-center gap-1.5 rounded-md border px-3 h-9">
                    <span class="text-xs text-muted-foreground whitespace-nowrap">域名</span>
                    <input v-model="resolveFilters.domain" class="w-36 bg-transparent text-sm outline-none placeholder:text-muted-foreground/60" placeholder="请输入域名" @keydown.enter="loadResolve(1)" />
                </div>
                <div class="flex items-center gap-1.5 rounded-md border px-3 h-9">
                    <span class="text-xs text-muted-foreground whitespace-nowrap">网站ID</span>
                    <input v-model="resolveFilters.site_id" class="w-28 bg-transparent text-sm outline-none placeholder:text-muted-foreground/60" placeholder="请输入网站ID" @keydown.enter="loadResolve(1)" />
                </div>
                <button type="button" class="text-sm text-muted-foreground hover:text-foreground" @click="resolveFilters.domain = ''; resolveFilters.site_id = ''; void loadResolve(1)">清除</button>
                <Button class="ml-auto" :disabled="loading" @click="loadResolve(1)"><Search data-icon="inline-start" />查询</Button>
            </div>
            <Card>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/30">
                                <tr>
                                    <th class="px-4 py-3 w-10"><input type="checkbox" class="size-4 rounded border-input" :checked="resolveSelectAll" @change="toggleResolveSelectAll" /></th>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground w-16">ID</th>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground w-20">网站ID</th>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground">域名</th>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground">CNAME</th>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground w-24">解析状态</th>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground w-24">DNS API</th>
                                    <th class="px-4 py-3 text-left font-medium text-muted-foreground w-24">任务状态</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loading"><td colspan="8" class="px-4 py-12 text-center"><Spinner class="mx-auto" /></td></tr>
                                <tr v-for="d in resolveRows" :key="textValue(d.id)" class="border-b last:border-0 hover:bg-muted/20 transition-colors">
                                    <td class="px-4 py-3"><input type="checkbox" class="size-4 rounded border-input" :checked="resolveSelected.has(Number(d.id))" @change="toggleResolveSelect(Number(d.id))" /></td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ textValue(d.id) }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ textValue(d.site_id) }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ textValue(d.domain) }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ textValue(d.cname) || '-' }}</td>
                                    <td class="px-4 py-3">
                                        <Badge :variant="d.state === 1 || d.state === '1' ? 'secondary' : 'outline'">{{ resolveStateLabel(d.state) }}</Badge>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ textValue(d.dns_api) || '-' }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ textValue(d.ret) || '-' }}</td>
                                </tr>
                                <tr v-if="!loading && resolveRows.length === 0"><td colspan="8" class="px-4 py-16 text-center text-muted-foreground">暂无数据</td></tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
            <div class="flex items-center justify-between text-sm text-muted-foreground">
                <span>共 {{ resolveTotal }} 条 · 已选 {{ resolveSelected.size }} 条</span>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" :disabled="resolvePage <= 1 || loading" @click="loadResolve(resolvePage - 1)">上一页</Button>
                    <span>第 {{ resolvePage }} 页</span>
                    <Button variant="outline" size="sm" :disabled="resolvePage * Number(resolveFilters.per_page) >= resolveTotal || loading" @click="loadResolve(resolvePage + 1)">下一页</Button>
                </div>
            </div>
        </template>

        <!-- ── 站点弹窗 ── -->
        <Dialog v-model:open="siteDialogOpen">
            <DialogScrollContent class="sm:max-w-3xl">
                <DialogHeader><DialogTitle>{{ editingSite ? '编辑站点' : '创建站点' }}</DialogTitle><DialogDescription>域名、套餐、源站地址等。</DialogDescription></DialogHeader>
                <form class="grid gap-5" @submit.prevent="submitSite">
                    <Alert v-if="formError" variant="destructive"><AlertCircle data-icon="alert" /><AlertDescription>{{ formError }}</AlertDescription></Alert>
                    <div class="grid gap-4">
                        <div class="grid gap-2"><Label for="site-domain">域名</Label><Input id="site-domain" v-model="siteForm.domain" placeholder="www.example.com" required /></div>
                        <div class="grid gap-2"><Label for="site-package">套餐</Label>
                            <Select v-model="siteForm.user_package" @open-change="(open: boolean) => open && userPackages.length === 0 && loadUserPackages()">
                                <SelectTrigger id="site-package"><SelectValue placeholder="选择已购套餐" /></SelectTrigger>
                                <SelectContent><SelectGroup><SelectItem v-for="pkg in userPackages" :key="pkg.id" :value="String(pkg.id)">{{ pkg.name }} (#{{ pkg.id }})</SelectItem></SelectGroup></SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2"><Label for="site-backend">源站地址</Label><Input id="site-backend" v-model="siteForm.backend_addr" placeholder="1.1.1.1" /></div>
                        <div class="grid gap-2"><Label>状态</Label><Select v-model="siteForm.enable"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectGroup><SelectItem value="1">启用</SelectItem><SelectItem value="0">禁用</SelectItem></SelectGroup></SelectContent></Select></div>
                        <div class="border-t pt-3">
                            <button type="button" class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground" @click="showAdvanced = !showAdvanced"><span>{{ showAdvanced ? '▲' : '▼' }}</span>可选配置</button>
                            <div v-if="showAdvanced" class="mt-3 grid gap-4"><div class="grid gap-2"><Label for="site-groups">所属分组</Label><Input id="site-groups" v-model="siteForm.groups" placeholder="分组ID，多个逗号分隔" /></div></div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" type="button" @click="siteDialogOpen = false">取消</Button>
                        <Button :disabled="saving" type="submit"><Spinner v-if="saving" data-icon="inline-start" /><Save v-else data-icon="inline-start" />保存</Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ── 分组弹窗 ── -->
        <Dialog v-model:open="groupDialogOpen">
            <DialogScrollContent class="sm:max-w-lg">
                <DialogHeader><DialogTitle>{{ editingGroup ? '编辑分组' : '新建分组' }}</DialogTitle></DialogHeader>
                <form class="grid gap-5" @submit.prevent="submitGroup">
                    <Alert v-if="formError" variant="destructive"><AlertCircle data-icon="alert" /><AlertDescription>{{ formError }}</AlertDescription></Alert>
                    <div class="grid gap-2"><Label>分组名称</Label><Input v-model="groupForm.name" required /></div>
                    <div class="grid gap-2"><Label>备注</Label><Input v-model="groupForm.des" /></div>
                    <DialogFooter>
                        <Button variant="outline" type="button" @click="groupDialogOpen = false">取消</Button>
                        <Button :disabled="saving" type="submit"><Spinner v-if="saving" data-icon="inline-start" /><Save v-else data-icon="inline-start" />保存</Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ── 默认设置弹窗 ── -->
        <Dialog v-model:open="configDialogOpen">
            <DialogScrollContent class="sm:max-w-2xl">
                <DialogHeader><DialogTitle>{{ editingConfig ? '编辑配置' : '添加配置' }}</DialogTitle><DialogDescription>新建站点时将自动应用这些默认值。</DialogDescription></DialogHeader>
                <form class="grid gap-5" @submit.prevent="submitConfig">
                    <Alert v-if="formError" variant="destructive"><AlertCircle data-icon="alert" /><AlertDescription>{{ formError }}</AlertDescription></Alert>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2"><Label>配置类别</Label>
                            <Select v-model="configForm.type"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectGroup><SelectItem v-for="t in CONFIG_TYPE_OPTIONS" :key="t.value" :value="t.value">{{ t.label }}</SelectItem></SelectGroup></SelectContent></Select>
                        </div>
                        <div class="grid gap-2"><Label>配置项</Label>
                            <Select v-model="configForm.name"><SelectTrigger><SelectValue placeholder="选择配置项" /></SelectTrigger><SelectContent><SelectGroup><SelectItem v-for="c in SITE_CONFIG_NAMES" :key="c.value" :value="c.value">{{ c.label }}</SelectItem></SelectGroup></SelectContent></Select>
                        </div>
                    </div>
                    <!-- 结构化 value 输入 -->
                    <div v-if="configForm.name" class="grid gap-2">
                        <Label>配置值 <span class="text-xs text-muted-foreground font-normal ml-1">{{ configForm.name }}</span></Label>
                        <template v-if="currentConfigMeta?.valueType === 'boolean'">
                            <Select v-model="configForm.value"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectGroup><SelectItem value="1">开启</SelectItem><SelectItem value="0">关闭</SelectItem></SelectGroup></SelectContent></Select>
                        </template>
                        <template v-else-if="currentConfigMeta?.valueType === 'number'">
                            <Input v-model="configForm.value" inputmode="numeric" />
                        </template>
                        <template v-else-if="currentConfigMeta?.valueType === 'select'">
                            <Select v-model="configForm.value"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectGroup><SelectItem v-for="o in currentConfigMeta.options" :key="o" :value="o">{{ o }}</SelectItem></SelectGroup></SelectContent></Select>
                        </template>
                        <template v-else-if="currentConfigMeta?.valueType === 'json'">
                            <textarea v-model="configForm.value" class="min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" spellcheck="false" />
                        </template>
                        <template v-else>
                            <Input v-model="configForm.value" />
                        </template>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="grid gap-2"><Label>生效范围</Label>
                            <Select v-model="configForm.scope_name"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectGroup><SelectItem value="global">全局</SelectItem><SelectItem value="group">分组</SelectItem></SelectGroup></SelectContent></Select>
                        </div>
                        <div v-if="configForm.scope_name === 'group'" class="grid gap-2"><Label>分组</Label>
                            <Select v-model="configForm.scope_id"><SelectTrigger><SelectValue placeholder="选择分组" /></SelectTrigger><SelectContent><SelectGroup><SelectItem v-for="g in siteGroupOptions" :key="g.id" :value="g.id">#{{ g.id }} {{ g.name }}</SelectItem></SelectGroup></SelectContent></Select>
                        </div>
                        <div class="grid gap-2"><Label>状态</Label>
                            <Select v-model="configForm.enable"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectGroup><SelectItem value="1">启用</SelectItem><SelectItem value="0">禁用</SelectItem></SelectGroup></SelectContent></Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" type="button" @click="configDialogOpen = false">取消</Button>
                        <Button :disabled="saving" type="submit"><Spinner v-if="saving" data-icon="inline-start" /><Save v-else data-icon="inline-start" />保存</Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ── DNS API 弹窗 ── -->
        <Dialog v-model:open="dnsDialogOpen">
            <DialogScrollContent class="sm:max-w-2xl">
                <DialogHeader><DialogTitle>{{ editingDns ? '编辑 DNS API' : '新增 DNS API' }}</DialogTitle><DialogDescription>auth 字段以 JSON 格式提交。</DialogDescription></DialogHeader>
                <form class="grid gap-5" @submit.prevent="submitDns">
                    <Alert v-if="formError" variant="destructive"><AlertCircle data-icon="alert" /><AlertDescription>{{ formError }}</AlertDescription></Alert>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2"><Label>名称</Label><Input v-model="dnsForm.name" required /></div>
                        <div class="grid gap-2"><Label>服务商</Label>
                            <Select v-model="dnsForm.type" @update:model-value="applyAuthTemplate"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectGroup><SelectItem v-for="t in DNS_TYPES" :key="t" :value="t">{{ t }}</SelectItem></SelectGroup></SelectContent></Select>
                        </div>
                    </div>
                    <div class="grid gap-2"><Label>auth JSON</Label>
                        <textarea v-model="dnsForm.auth" class="min-h-40 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" spellcheck="false" />
                    </div>
                    <div class="grid gap-2"><Label>备注</Label><Input v-model="dnsForm.des" /></div>
                    <DialogFooter>
                        <Button variant="outline" type="button" @click="dnsDialogOpen = false">取消</Button>
                        <Button :disabled="saving" type="submit"><Spinner v-if="saving" data-icon="inline-start" /><Save v-else data-icon="inline-start" />保存</Button>
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
