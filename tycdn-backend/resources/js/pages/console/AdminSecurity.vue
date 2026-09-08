<script setup lang="ts">
import {
    AlertCircle,
    CheckCircle2,
    KeyRound,
    Pencil,
    Plus,
    Save,
    Search,
    ShieldCheck,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
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
import { listAdminUsers } from '@/lib/adminConsoleApi';
import type { AdminUserRecord, Paginated } from '@/lib/adminConsoleApi';
import {
    createAdminAcl,
    createAdminCcFilter,
    createAdminCcMatcher,
    createAdminCcRule,
    deleteAdminAcl,
    deleteAdminCcFilter,
    deleteAdminCcMatcher,
    deleteAdminCcRule,
    listAdminAllAcls,
    listAdminCcFilters,
    listAdminCcMatchers,
    listAdminCcRules,
    updateAdminAcl,
    updateAdminCcFilter,
    updateAdminCcMatcher,
    updateAdminCcRule,
} from '@/lib/adminModulesApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import { formatDate, getErrorMessage } from '@/lib/formatters';
import {
    textValue,
    recordId,
    numberValue,
    yesNo,
    jsonText,
} from '@/lib/cdnRecord';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnUserApi';

const DEFAULT_DATA_JSON = JSON.stringify(
    [
        {
            acl_action: 'reject',
            acl_matcher: { ip: { operator: '=', value: '' } },
        },
    ],
    null,
    2,
);

const stats = ref<{ admins: number; verified: number; apiKey: number }>({
    admins: 0,
    verified: 0,
    apiKey: 0,
});

onMounted(async () => {
    try {
        const data: Paginated<AdminUserRecord> = await listAdminUsers({
            role: 'admin',
            per_page: 100,
        });
        const rows = data.data ?? [];
        stats.value = {
            admins: data.total ?? rows.length,
            verified: rows.filter((u) => u.email_verified).length,
            apiKey: rows.filter((u) => u.has_api_key).length,
        };
    } catch {
        // stats are non-critical
    }
    void loadCcRows();
});

const adminColumns: ColumnDef[] = [
    { key: 'name', label: '用户' },
    { key: 'role', label: '角色', width: '90px', badge: true },
    {
        key: 'email_verified',
        label: '邮箱验证',
        width: '100px',
        badge: true,
        format: (v) => (v ? '已验证' : '未验证'),
        badgeVariant: (v) => (v ? 'secondary' : 'outline'),
    },
    {
        key: 'cdnfly_user_id',
        label: 'CDNfly ID',
        width: '100px',
        format: (v) => String(v ?? '-'),
    },
    {
        key: 'has_api_key',
        label: 'API Key',
        width: '100px',
        badge: true,
        format: (v) => (v ? '已同步' : '未同步'),
        badgeVariant: (v) => (v ? 'secondary' : 'outline'),
    },
    {
        key: 'updated_at',
        label: '更新时间',
        width: '140px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];

function fetchAdmins(params: Record<string, string | number>) {
    return listAdminUsers({ ...params, role: 'admin' });
}

const aclColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'name', label: '规则名称' },
    {
        key: 'default_action',
        label: '默认动作',
        width: '90px',
        badge: true,
        format: (v) => String(v ?? '-'),
    },
    { key: 'user_id', label: '用户 ID', width: '90px' },
    {
        key: 'enable',
        label: '状态',
        width: '90px',
        badge: true,
        format: (v) => (v === 1 || v === '1' ? '启用' : '禁用'),
        badgeVariant: (v) =>
            v === 1 || v === '1' ? 'secondary' : 'destructive',
    },
];

const aclTableRef = ref<InstanceType<typeof ConsoleDataTable> | null>(null);
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
    default_action: 'reject',
    data: DEFAULT_DATA_JSON,
    des: '',
    enable: '1',
    user_id: '',
});

const dialogTitle = computed(() =>
    editingRecord.value ? '编辑 ACL' : '新增 ACL',
);

async function toggleAclEnabled(
    row: CdnflyRecord,
    checked: boolean,
): Promise<void> {
    const id = Number(row.id);
    if (!id) return;
    togglingId.value = id;
    try {
        await updateAdminAcl(id, {
            enable: checked ? 1 : 0,
            // /v1/waf-rules is user-scope: the server needs the owner to act as
            user_id: Number(row.user_id),
        });
        toast.success(checked ? 'ACL 已启用' : 'ACL 已停用');
        aclTableRef.value?.refresh();
    } catch (error) {
        toast.error(getErrorMessage(error));
    } finally {
        togglingId.value = null;
    }
}

function openCreateDialog(): void {
    editingRecord.value = null;
    form.name = '';
    form.default_action = 'reject';
    form.data = DEFAULT_DATA_JSON;
    form.des = '';
    form.enable = '1';
    form.user_id = '';
    formError.value = '';
    dialogOpen.value = true;
}

function openEditDialog(row: CdnflyRecord): void {
    editingRecord.value = row;
    form.name = String(row.name ?? '');
    form.default_action = String(row.default_action ?? 'reject');
    const d = row.data;
    form.data = typeof d === 'string' ? d : JSON.stringify(d || [], null, 2);
    form.des = String(row.des ?? '');
    form.enable = row.enable === 1 || row.enable === '1' ? '1' : '0';
    form.user_id = String(row.user_id ?? '');
    formError.value = '';
    dialogOpen.value = true;
}

async function submitAcl(): Promise<void> {
    saving.value = true;
    formError.value = '';

    let dataArr: unknown;
    try {
        dataArr = JSON.parse(form.data);
    } catch {
        formError.value = 'data 字段不是有效的 JSON';
        saving.value = false;
        return;
    }

    const payload: Record<string, unknown> = {
        name: form.name.trim(),
        default_action: form.default_action,
        data: dataArr,
        des: form.des.trim() || undefined,
        enable: Number(form.enable),
    };

    // Required on edits as well as creates now: the server mints an SSO token
    // for this user because CDNfly exposes waf-rules only at user scope.
    if (!form.user_id.trim()) {
        formError.value = '用户 ID 不能为空';
        saving.value = false;
        return;
    }
    payload.user_id = Number(form.user_id);

    try {
        if (editingRecord.value) {
            await updateAdminAcl(Number(editingRecord.value.id), payload);
            toast.success('ACL 已更新');
        } else {
            await createAdminAcl(payload);
            toast.success('ACL 已创建');
        }
        dialogOpen.value = false;
        aclTableRef.value?.refresh();
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
    try {
        await deleteAdminAcl(
            Number(deleteTarget.value.id),
            Number(deleteTarget.value.user_id),
        );
        deleteOpen.value = false;
        toast.success('ACL 已删除');
        aclTableRef.value?.refresh();
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        deletingId.value = null;
    }
}

// ─── CC 防护 ─────────────────────────────────────────
type CcKind = 'matcher' | 'filter' | 'rule';
type MatcherCondition = { key: string; operator: string; value: string };
type RuleEntry = {
    action: string;
    matcher: string;
    filter1: string;
    filter2: string;
    state: boolean;
};

const CC_FILTER_TYPES = [
    'req_rate',
    '302_challenge',
    'browser_verify_auto',
    'slide_filter',
    'captcha_filter',
    'click_filter',
    'url_auth',
    'delay_jump_filter',
] as const;

const MATCHER_KEYS = [
    { value: 'ip', label: 'IP 地址' },
    { value: 'host', label: '域名 (Host)' },
    { value: 'uri', label: 'URI 路径' },
    { value: 'req_uri', label: '完整 URI' },
    { value: 'req_method', label: '请求方法' },
    { value: 'user_agent', label: 'User-Agent' },
    { value: 'referer', label: 'Referer' },
    { value: 'country_iso_code', label: '国家代码' },
    { value: 'asnumber', label: 'AS 号码' },
    { value: 'province', label: '省份' },
    { value: 'city', label: '城市' },
    { value: 'isp', label: '运营商' },
] as const;

const OPERATORS = [
    { value: '=', label: '等于' },
    { value: '!=', label: '不等于' },
    { value: 'contain', label: '包含' },
    { value: '!contain', label: '不包含' },
    { value: 'AC', label: '在列表中' },
    { value: '!AC', label: '不在列表中' },
] as const;

const RULE_ACTIONS = [
    { value: 'ipset', label: '加黑名单 (ipset)' },
    { value: 'exit', label: '终止 (exit)' },
    { value: 'log', label: '仅记录 (log)' },
] as const;

const ccKinds: Array<{ key: CcKind; label: string }> = [
    { key: 'matcher', label: '匹配器' },
    { key: 'filter', label: '过滤器' },
    { key: 'rule', label: '规则组' },
];

const activeCcKind = ref<CcKind>('matcher');
const ccDialogOpen = ref(false);
const editingCc = ref<CdnflyRecord | null>(null);
const ccPage = ref(1);
const ccTotal = ref(0);
const ccRows = ref<CdnflyRecord[]>([]);
const ccLoading = ref(false);
const ccSaving = ref(false);
const ccDeletingId = ref<number | null>(null);
const ccError = ref('');
const ccFormError = ref('');

const ccFilters = reactive({ search: '', enable: 'all', per_page: '20' });
const ccForm = reactive({
    name: '',
    type: 'captcha_filter',
    within_second: '60',
    max_req: '5',
    max_req_per_uri: '',
    sort: '100',
    data: '',
    des: '',
    enable: '1',
    is_show: '1',
});
const matcherConditions = ref<MatcherCondition[]>([]);
const ruleEntries = ref<RuleEntry[]>([]);
const matcherOptions = ref<{ id: string; name: string }[]>([]);
const filterOptions = ref<{ id: string; name: string }[]>([]);
const loadingRuleOptions = ref(false);

const extraForm = reactive({
    mode: 'TypeA',
    key: '',
    sign_name: 'sign',
    time_name: 'time',
    time_diff: '300',
    sign_use_times: '1',
});

// User list for uid dropdown
const userOptions = ref<{ id: string; name: string; email: string }[]>([]);
const ccUid = ref('');

const ccDialogTitle = computed(() =>
    editingCc.value
        ? `编辑${ccKindLabel(activeCcKind.value)}`
        : `新增${ccKindLabel(activeCcKind.value)}`,
);

function ccKindLabel(kind: CcKind): string {
    return ccKinds.find((k) => k.key === kind)?.label ?? '资源';
}

function buildMatcherData(): Record<string, unknown> {
    const data: Record<string, unknown> = {};
    for (const c of matcherConditions.value) {
        if (!c.key) continue;
        const isArrayOp = c.operator === 'AC' || c.operator === '!AC';
        data[c.key] = {
            operator: c.operator || '=',
            value: isArrayOp
                ? c.value
                      .split(',')
                      .map((s) => s.trim())
                      .filter(Boolean)
                : c.value,
        };
    }
    return data;
}

function parseMatcherData(data: Record<string, unknown>): MatcherCondition[] {
    if (!data || typeof data !== 'object') return [];
    return Object.entries(data).map(([key, rule]) => ({
        key,
        operator: (rule as any).operator ?? '=',
        value: Array.isArray((rule as any).value)
            ? (rule as any).value.join(', ')
            : String((rule as any).value ?? ''),
    }));
}

function buildExtra(): Record<string, unknown> {
    if (ccForm.type !== 'url_auth') return {};
    const obj: Record<string, unknown> = {
        mode: extraForm.mode,
        key: extraForm.key,
        sign_name: extraForm.sign_name,
        time_diff: Number(extraForm.time_diff),
        sign_use_times: Number(extraForm.sign_use_times),
    };
    if (extraForm.mode === 'TypeA') obj.time_name = extraForm.time_name;
    return obj;
}

function parseExtra(extra: unknown): void {
    let raw = extra;
    if (typeof raw === 'string') {
        try {
            raw = JSON.parse(raw);
        } catch {
            raw = {};
        }
    }
    const e = (
        raw && typeof raw === 'object' && !Array.isArray(raw) ? raw : {}
    ) as Record<string, unknown>;
    extraForm.mode = String(e.mode ?? 'TypeA');
    extraForm.key = String(e.key ?? '');
    extraForm.sign_name = String(e.sign_name ?? 'sign');
    extraForm.time_name = String(e.time_name ?? 'time');
    extraForm.time_diff = String(e.time_diff ?? '300');
    extraForm.sign_use_times = String(e.sign_use_times ?? '1');
}

async function loadRuleFormOptions(): Promise<void> {
    loadingRuleOptions.value = true;
    try {
        const [matchers, filters] = await Promise.all([
            listAdminCcMatchers({ limit: 200 }),
            listAdminCcFilters({ limit: 200 }),
        ]);
        matcherOptions.value = extractCdnflyRows(matchers).map((r) => ({
            id: String(r.id),
            name: textValue(r.name),
        }));
        filterOptions.value = extractCdnflyRows(filters).map((r) => ({
            id: String(r.id),
            name: textValue(r.name),
        }));
    } catch {
        /* non-critical */
    } finally {
        loadingRuleOptions.value = false;
    }
}

async function loadUserOptions(): Promise<void> {
    try {
        const data: Paginated<AdminUserRecord> = await listAdminUsers({
            role: 'admin',
            per_page: 200,
        });
        userOptions.value = (data.data ?? []).map((u) => ({
            id: String(u.id),
            name: u.name ?? u.email ?? '',
            email: u.email ?? '',
        }));
    } catch {
        /* non-critical */
    }
}

async function ccList(kind: CcKind, params: Record<string, string | number>) {
    if (kind === 'filter') return listAdminCcFilters(params);
    if (kind === 'rule') return listAdminCcRules(params);
    return listAdminCcMatchers(params);
}

async function loadCcRows(targetPage = ccPage.value): Promise<void> {
    ccLoading.value = true;
    ccError.value = '';
    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(ccFilters.per_page),
        };
        if (ccFilters.enable !== 'all') params.enable = ccFilters.enable;
        const result = await ccList(activeCcKind.value, params);
        const rows = extractCdnflyRows(result);
        ccRows.value = rows;
        ccTotal.value = extractCdnflyTotal(result, rows.length);
        ccPage.value = targetPage;
    } catch (error) {
        ccError.value = getErrorMessage(error);
    } finally {
        ccLoading.value = false;
    }
}

function selectCcKind(kind: CcKind): void {
    activeCcKind.value = kind;
    ccPage.value = 1;
    void loadCcRows(1);
}

function addCondition(): void {
    matcherConditions.value.push({
        key: 'uri',
        operator: 'contain',
        value: '',
    });
}
function removeCondition(index: number): void {
    matcherConditions.value.splice(index, 1);
}
function addRuleEntry(): void {
    ruleEntries.value.push({
        action: 'ipset',
        matcher: '',
        filter1: '',
        filter2: '',
        state: true,
    });
}
function removeRuleEntry(index: number): void {
    ruleEntries.value.splice(index, 1);
}

function openCcCreateDialog(): void {
    editingCc.value = null;
    ccForm.name = '';
    ccForm.type = 'captcha_filter';
    ccForm.within_second = '60';
    ccForm.max_req = '5';
    ccForm.max_req_per_uri = '';
    ccForm.sort = '100';
    ccForm.data = '';
    ccForm.des = '';
    ccForm.enable = '1';
    ccForm.is_show = '1';
    ccFormError.value = '';
    matcherConditions.value = [];
    ruleEntries.value = [];
    ccUid.value = '';
    parseExtra(null);
    if (activeCcKind.value === 'rule') void loadRuleFormOptions();
    void loadUserOptions();
    ccDialogOpen.value = true;
}

function openCcEditDialog(record: CdnflyRecord): void {
    editingCc.value = record;
    ccForm.name = textValue(record.name);
    ccForm.type = textValue(record.type) || 'captcha_filter';
    ccForm.within_second = textValue(record.within_second) || '60';
    ccForm.max_req = textValue(record.max_req) || '5';
    ccForm.max_req_per_uri = textValue(record.max_req_per_uri);
    ccForm.sort = textValue(record.sort) || '100';
    ccForm.data = '';
    ccForm.des = textValue(record.des ?? record.remark);
    ccForm.enable = record.enable === 0 || record.enable === false ? '0' : '1';
    ccForm.is_show =
        record.is_show === 0 || record.is_show === false ? '0' : '1';
    ccFormError.value = '';

    const rawData = record.data;
    if (activeCcKind.value === 'matcher') {
        let dataObj: Record<string, unknown>;
        if (typeof rawData === 'string') {
            try {
                dataObj = JSON.parse(rawData);
            } catch {
                dataObj = {};
            }
        } else if (
            rawData &&
            typeof rawData === 'object' &&
            !Array.isArray(rawData)
        )
            dataObj = rawData as Record<string, unknown>;
        else dataObj = {};
        matcherConditions.value = parseMatcherData(dataObj);
        ruleEntries.value = [];
    } else if (activeCcKind.value === 'rule') {
        let dataArr: unknown[];
        if (typeof rawData === 'string') {
            try {
                dataArr = JSON.parse(rawData);
            } catch {
                dataArr = [];
            }
        } else dataArr = Array.isArray(rawData) ? rawData : [];
        ruleEntries.value = dataArr.map((entry: any) => ({
            action: String(entry.action ?? 'ipset'),
            matcher: String(entry.matcher ?? ''),
            filter1: String(entry.filter1 ?? ''),
            filter2: String(entry.filter2 ?? ''),
            state: entry.state !== false,
        }));
        matcherConditions.value = [];
        void loadRuleFormOptions();
    } else {
        matcherConditions.value = [];
        ruleEntries.value = [];
    }
    parseExtra(record.extra);
    ccUid.value = String(record.uid ?? '');
    void loadUserOptions();
    ccDialogOpen.value = true;
}

async function submitCc(): Promise<void> {
    let data: Record<string, unknown>;
    const base: Record<string, unknown> = {
        name: ccForm.name.trim(),
        des: ccForm.des.trim() || undefined,
        enable: Number(ccForm.enable),
    };

    if (activeCcKind.value === 'matcher') {
        base.data = buildMatcherData();
    } else if (activeCcKind.value === 'filter') {
        base.type = ccForm.type;
        base.within_second = Number(ccForm.within_second);
        base.max_req = Number(ccForm.max_req);
        base.max_req_per_uri = ccForm.max_req_per_uri
            ? Number(ccForm.max_req_per_uri)
            : undefined;
        base.extra = buildExtra();
    } else {
        base.sort = Number(ccForm.sort) || 100;
        base.is_show = Number(ccForm.is_show);
        base.data = ruleEntries.value.map((e) => ({
            action: e.action,
            matcher: e.matcher,
            filter1: e.filter1,
            filter2: e.filter2 || '',
            state: e.state,
        }));
    }

    if (ccUid.value) base.uid = Number(ccUid.value);
    data = base;

    if (!data.name || String(data.name).trim() === '') {
        ccFormError.value = '名称不能为空';
        return;
    }

    ccSaving.value = true;
    ccFormError.value = '';
    try {
        if (editingCc.value) {
            const id = recordId(editingCc.value);
            if (!id) {
                ccFormError.value = 'ID 缺失';
                return;
            }
            if (activeCcKind.value === 'filter')
                await updateAdminCcFilter(id, data);
            else if (activeCcKind.value === 'rule')
                await updateAdminCcRule(id, data);
            else await updateAdminCcMatcher(id, data);
            toast.success(`${ccKindLabel(activeCcKind.value)}更新成功`);
        } else {
            if (activeCcKind.value === 'filter')
                await createAdminCcFilter(data);
            else if (activeCcKind.value === 'rule')
                await createAdminCcRule(data);
            else await createAdminCcMatcher(data);
            toast.success(`${ccKindLabel(activeCcKind.value)}创建成功`);
        }
        ccDialogOpen.value = false;
        await loadCcRows();
    } catch (error) {
        ccFormError.value = getErrorMessage(error);
    } finally {
        ccSaving.value = false;
    }
}

async function removeCc(record: CdnflyRecord): Promise<void> {
    const id = recordId(record);
    if (!id) {
        ccError.value = 'ID 缺失';
        return;
    }
    ccDeletingId.value = id;
    ccError.value = '';
    try {
        if (activeCcKind.value === 'filter') await deleteAdminCcFilter(id);
        else if (activeCcKind.value === 'rule') await deleteAdminCcRule(id);
        else await deleteAdminCcMatcher(id);
        toast.success(`${ccKindLabel(activeCcKind.value)}已删除`);
        await loadCcRows();
    } catch (error) {
        ccError.value = getErrorMessage(error);
    } finally {
        ccDeletingId.value = null;
    }
}

function dataCount(value: unknown): string {
    try {
        const parsed = typeof value === 'string' ? JSON.parse(value) : value;
        if (Array.isArray(parsed)) return `${parsed.length} 条`;
        if (parsed && typeof parsed === 'object')
            return `${Object.keys(parsed).length} 项`;
    } catch {
        return '已配置';
    }
    return '-';
}

const hasCcPrevPage = computed(() => ccPage.value > 1);
const hasCcNextPage = computed(
    () => ccPage.value * Number(ccFilters.per_page) < ccTotal.value,
);

const displayedCcRows = computed(() => {
    const keyword = ccFilters.search.trim().toLowerCase();
    if (!keyword) return ccRows.value;
    return ccRows.value.filter((r) =>
        [r.name, r.des, r.type]
            .map((v) => textValue(v).toLowerCase())
            .some((v) => v.includes(keyword)),
    );
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="安全与权限"
            :icon="ShieldCheck"
            :show-api-badge="false"
        />

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <div class="grid gap-4 xl:grid-cols-3">
            <Card class="gap-4">
                <CardHeader class="flex flex-row items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-md border bg-card"
                    >
                        <Users class="size-5" />
                    </div>
                    <div>
                        <CardTitle class="text-base">管理员账号</CardTitle>
                        <div class="mt-1 text-3xl font-semibold">
                            {{ stats.admins }}
                        </div>
                    </div>
                </CardHeader>
            </Card>
            <Card class="gap-4">
                <CardHeader class="flex flex-row items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-md border bg-card"
                    >
                        <CheckCircle2 class="size-5" />
                    </div>
                    <div>
                        <CardTitle class="text-base">邮箱已验证</CardTitle>
                        <div class="mt-1 text-3xl font-semibold">
                            {{ stats.verified }}
                        </div>
                    </div>
                </CardHeader>
            </Card>
            <Card class="gap-4">
                <CardHeader class="flex flex-row items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-md border bg-card"
                    >
                        <KeyRound class="size-5" />
                    </div>
                    <div>
                        <CardTitle class="text-base">API Key 已同步</CardTitle>
                        <div class="mt-1 text-3xl font-semibold">
                            {{ stats.apiKey }}
                        </div>
                    </div>
                </CardHeader>
            </Card>
        </div>

        <ConsoleDataTable
            title="管理员列表"
            :icon="Users"
            :columns="adminColumns"
            :fetch-fn="fetchAdmins"
            search-placeholder="搜索管理员"
            :page-size="50"
        >
            <template #cell-name="{ row }">
                <div class="font-medium">
                    {{ row.name || row.email || `#${row.id}` }}
                </div>
                <div class="text-xs text-muted-foreground">{{ row.email }}</div>
            </template>
            <template #actions-col><col style="width: 0" /></template>
            <template #actions-header><th /></template>
        </ConsoleDataTable>

        <ConsoleDataTable
            ref="aclTableRef"
            title="全部 ACL 规则"
            :icon="ShieldCheck"
            :columns="aclColumns"
            :fetch-fn="listAdminAllAcls"
            search-placeholder="搜索 ACL 规则"
        >
            <template #cell-enable="{ row }">
                <Switch
                    :checked="row.enable === 1 || row.enable === '1'"
                    :disabled="togglingId === Number(row.id)"
                    @update:checked="toggleAclEnabled(row, $event)"
                />
            </template>
            <template #toolbar>
                <Button variant="default" size="sm" @click="openCreateDialog">
                    <Plus data-icon="inline-start" class="size-4" />
                    新增
                </Button>
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

        <!-- CC 防护 -->
        <Card>
            <CardHeader class="space-y-4">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <CardTitle>CC 防护资源</CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                ccTotal === 0 ? '暂无资源' : `${ccTotal} 个资源`
                            }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="kind in ccKinds"
                            :key="kind.key"
                            type="button"
                            size="sm"
                            :variant="
                                activeCcKind === kind.key
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="selectCcKind(kind.key)"
                        >
                            {{ kind.label }}
                        </Button>
                    </div>
                </div>
                <form
                    class="grid gap-2 md:grid-cols-[1fr_120px_120px_auto_auto]"
                    @submit.prevent="loadCcRows(1)"
                >
                    <Input v-model="ccFilters.search" placeholder="搜索名称" />
                    <Select v-model="ccFilters.enable">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">全部状态</SelectItem>
                                <SelectItem value="1">启用</SelectItem>
                                <SelectItem value="0">禁用</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Select v-model="ccFilters.per_page">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="10">10 条</SelectItem>
                                <SelectItem value="20">20 条</SelectItem>
                                <SelectItem value="50">50 条</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Button type="submit" :disabled="ccLoading">
                        <Spinner v-if="ccLoading" data-icon="inline-start" />
                        <Search v-else data-icon="inline-start" />
                        查询
                    </Button>
                    <Button type="button" @click="openCcCreateDialog">
                        <Plus data-icon="inline-start" />
                        新增
                    </Button>
                </form>
            </CardHeader>
            <CardContent>
                <Alert v-if="ccError" variant="destructive" class="mb-4">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>请求失败</AlertTitle>
                    <AlertDescription>{{ ccError }}</AlertDescription>
                </Alert>
                <div class="overflow-x-auto border-y">
                    <table class="w-full min-w-[900px] table-fixed text-sm">
                        <colgroup>
                            <col style="width: 18%" />
                            <col style="width: 12%" />
                            <col style="width: 10%" />
                            <col style="width: 18%" />
                            <col style="width: 12%" />
                            <col style="width: 14%" />
                            <col style="width: 16%" />
                        </colgroup>
                        <thead class="border-b text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">
                                    名称
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    类型 / 动作
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    归属
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    规则数据
                                </th>
                                <th class="px-4 py-3 text-center font-medium">
                                    状态
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    更新时间
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    操作
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="ccLoading">
                                <td class="px-4 py-12 text-center" colspan="7">
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <tr
                                v-for="record in displayedCcRows"
                                :key="textValue(record.id)"
                                class="border-b"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{
                                            textValue(record.name) ||
                                            `#${textValue(record.id)}`
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        #{{ textValue(record.id) || '-' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                        activeCcKind === 'filter'
                                            ? textValue(record.type) || '-'
                                            : activeCcKind === 'rule'
                                              ? dataCount(record.data)
                                              : '匹配条件'
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge variant="outline">{{
                                        record.uid
                                            ? `用户 #${record.uid}`
                                            : '系统'
                                    }}</Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="truncate font-mono text-xs">
                                        {{ jsonText(record.data, '-') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Badge variant="secondary">{{
                                        yesNo(record.enable)
                                    }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{
                                        formatDate(
                                            record.update_at2 ??
                                                record.create_at2,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openCcEditDialog(record)"
                                        >
                                            <Pencil data-icon="inline-start" />
                                            编辑
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            :disabled="
                                                ccDeletingId ===
                                                recordId(record)
                                            "
                                            @click="removeCc(record)"
                                        >
                                            <Spinner
                                                v-if="
                                                    ccDeletingId ===
                                                    recordId(record)
                                                "
                                                data-icon="inline-start"
                                            />
                                            <Trash2
                                                v-else
                                                data-icon="inline-start"
                                            />
                                            删除
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-if="
                                    !ccLoading && displayedCcRows.length === 0
                                "
                            >
                                <td
                                    class="px-6 py-16 text-center text-muted-foreground"
                                    colspan="7"
                                >
                                    暂无{{ ccKindLabel(activeCcKind) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex items-center justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!hasCcPrevPage || ccLoading"
                        @click="loadCcRows(ccPage - 1)"
                        >上一页</Button
                    >
                    <span class="text-sm text-muted-foreground"
                        >第 {{ ccPage }} 页</span
                    >
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!hasCcNextPage || ccLoading"
                        @click="loadCcRows(ccPage + 1)"
                        >下一页</Button
                    >
                </div>
            </CardContent>
        </Card>

        <!-- Create/Edit ACL dialog -->
        <Dialog v-model:open="dialogOpen">
            <DialogScrollContent class="max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>
                        管理 ACL 访问控制规则，需指定规则条目和默认动作。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="formError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>提交失败</AlertTitle>
                    <AlertDescription>{{ formError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="acl-name">规则名称</Label>
                            <Input
                                id="acl-name"
                                v-model="form.name"
                                placeholder="ACL 规则名称"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="acl-user-id">用户 ID</Label>
                            <Input
                                id="acl-user-id"
                                v-model="form.user_id"
                                inputmode="numeric"
                                placeholder="绑定用户 ID"
                                :disabled="!!editingRecord"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label>默认动作</Label>
                            <Select v-model="form.default_action">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="reject"
                                            >拒绝</SelectItem
                                        >
                                        <SelectItem value="allow"
                                            >放行</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>状态</Label>
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
                    </div>
                    <div class="grid gap-2">
                        <Label for="acl-data">规则条目 data (JSON)</Label>
                        <textarea
                            id="acl-data"
                            v-model="form.data"
                            class="min-h-40 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            spellcheck="false"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="acl-des">备注</Label>
                        <Input
                            id="acl-des"
                            v-model="form.des"
                            placeholder="可选备注"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="dialogOpen = false"
                        >取消</Button
                    >
                    <Button :disabled="saving" @click="submitAcl">
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
                        确定要删除 ACL 规则「{{ deleteTarget?.name }}」吗？
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

        <!-- CC 新增/编辑 Dialog -->
        <Dialog v-model:open="ccDialogOpen">
            <DialogScrollContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{ ccDialogTitle }}</DialogTitle>
                    <DialogDescription>
                        {{
                            activeCcKind === 'rule'
                                ? '规则组 data 为数组格式'
                                : activeCcKind === 'matcher'
                                  ? '匹配器 data 为对象格式'
                                  : '配置过滤器参数'
                        }}
                    </DialogDescription>
                </DialogHeader>
                <Alert v-if="ccFormError" variant="destructive" class="mb-2">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>提交失败</AlertTitle>
                    <AlertDescription>{{ ccFormError }}</AlertDescription>
                </Alert>
                <form class="grid gap-5" @submit.prevent="submitCc">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="cc-name">名称</Label>
                            <Input
                                id="cc-name"
                                v-model="ccForm.name"
                                required
                            />
                        </div>
                        <div
                            v-if="activeCcKind === 'filter'"
                            class="grid gap-2"
                        >
                            <Label>过滤器类型</Label>
                            <Select v-model="ccForm.type">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem
                                            v-for="type in CC_FILTER_TYPES"
                                            :key="type"
                                            :value="type"
                                            >{{ type }}</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="activeCcKind === 'rule'" class="grid gap-2">
                            <Label for="cc-sort">排序</Label>
                            <Input
                                id="cc-sort"
                                v-model="ccForm.sort"
                                inputmode="numeric"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>状态</Label>
                            <Select v-model="ccForm.enable">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="1">启用</SelectItem>
                                        <SelectItem value="0">禁用</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div
                        v-if="activeCcKind === 'filter'"
                        class="grid gap-4 md:grid-cols-3"
                    >
                        <div class="grid gap-2">
                            <Label for="cc-within">统计秒数</Label>
                            <Input
                                id="cc-within"
                                v-model="ccForm.within_second"
                                inputmode="numeric"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="cc-max">最大请求数</Label>
                            <Input
                                id="cc-max"
                                v-model="ccForm.max_req"
                                inputmode="numeric"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="cc-max-uri">同 URI 次数</Label>
                            <Input
                                id="cc-max-uri"
                                v-model="ccForm.max_req_per_uri"
                                inputmode="numeric"
                            />
                        </div>
                    </div>
                    <div v-if="activeCcKind === 'rule'" class="grid gap-2">
                        <Label>是否显示</Label>
                        <Select v-model="ccForm.is_show">
                            <SelectTrigger class="max-w-40"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="1">显示</SelectItem>
                                    <SelectItem value="0">隐藏</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- 匹配器：动态条件行 -->
                    <div v-if="activeCcKind === 'matcher'" class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label>匹配条件</Label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addCondition"
                            >
                                <Plus
                                    data-icon="inline-start"
                                    class="size-3.5"
                                />
                                添加条件
                            </Button>
                        </div>
                        <p
                            v-if="matcherConditions.length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            不添加任何条件 = 匹配所有请求（data 为 {}）
                        </p>
                        <div
                            v-for="(cond, index) in matcherConditions"
                            :key="index"
                            class="grid grid-cols-[1fr_120px_1fr_auto] gap-2"
                        >
                            <Select v-model="cond.key">
                                <SelectTrigger
                                    ><SelectValue placeholder="选择字段"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem
                                            v-for="mk in MATCHER_KEYS"
                                            :key="mk.value"
                                            :value="mk.value"
                                            >{{ mk.label }}</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <Select v-model="cond.operator">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem
                                            v-for="op in OPERATORS"
                                            :key="op.value"
                                            :value="op.value"
                                            >{{ op.label }}</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <Input
                                v-model="cond.value"
                                :placeholder="
                                    cond.operator === 'AC' ||
                                    cond.operator === '!AC'
                                        ? '逗号分隔多个值'
                                        : '输入值'
                                "
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-9"
                                @click="removeCondition(index)"
                                ><X class="size-4"
                            /></Button>
                        </div>
                    </div>

                    <!-- 规则组：动态规则条目行 -->
                    <div v-if="activeCcKind === 'rule'" class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label>规则条目</Label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="loadingRuleOptions"
                                @click="addRuleEntry"
                            >
                                <Plus
                                    data-icon="inline-start"
                                    class="size-3.5"
                                />
                                添加条目
                            </Button>
                        </div>
                        <div
                            v-if="loadingRuleOptions"
                            class="flex items-center gap-2 py-2 text-sm text-muted-foreground"
                        >
                            <Spinner class="size-4" /> 加载匹配器和过滤器列表...
                        </div>
                        <div
                            v-for="(entry, index) in ruleEntries"
                            :key="index"
                            class="grid grid-cols-[110px_1fr_1fr_1fr_60px_auto] items-end gap-1.5"
                        >
                            <div class="grid gap-1">
                                <Label class="text-[10px]">动作</Label>
                                <Select v-model="entry.action">
                                    <SelectTrigger class="h-9 text-xs"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem
                                                v-for="a in RULE_ACTIONS"
                                                :key="a.value"
                                                :value="a.value"
                                                >{{ a.label }}</SelectItem
                                            >
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-[10px]">匹配器</Label>
                                <Select v-model="entry.matcher">
                                    <SelectTrigger class="h-9 text-xs"
                                        ><SelectValue placeholder="选择匹配器"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem
                                                v-for="m in matcherOptions"
                                                :key="m.id"
                                                :value="m.id"
                                                >#{{ m.id }}
                                                {{ m.name }}</SelectItem
                                            >
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-[10px]">过滤器 1</Label>
                                <Select v-model="entry.filter1">
                                    <SelectTrigger class="h-9 text-xs"
                                        ><SelectValue placeholder="选择过滤器"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value=""
                                                >(无)</SelectItem
                                            >
                                            <SelectItem
                                                v-for="f in filterOptions"
                                                :key="f.id"
                                                :value="f.id"
                                                >#{{ f.id }}
                                                {{ f.name }}</SelectItem
                                            >
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-[10px]">过滤器 2</Label>
                                <Select v-model="entry.filter2">
                                    <SelectTrigger class="h-9 text-xs"
                                        ><SelectValue placeholder="(可选)"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value=""
                                                >(无)</SelectItem
                                            >
                                            <SelectItem
                                                v-for="f in filterOptions"
                                                :key="f.id"
                                                :value="f.id"
                                                >#{{ f.id }}
                                                {{ f.name }}</SelectItem
                                            >
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-center text-[10px]"
                                    >启用</Label
                                >
                                <div
                                    class="flex h-9 items-center justify-center"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="entry.state"
                                        class="size-4 rounded border-input"
                                    />
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-9 self-end"
                                @click="removeRuleEntry(index)"
                                ><X class="size-4"
                            /></Button>
                        </div>
                    </div>

                    <!-- URL 鉴权子表单（仅 filter + url_auth 时显示） -->
                    <div
                        v-if="
                            activeCcKind === 'filter' &&
                            ccForm.type === 'url_auth'
                        "
                        class="space-y-3 rounded-md border p-4"
                    >
                        <Label class="font-medium">URL 鉴权配置</Label>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="extra-mode">模式</Label>
                                <Select v-model="extraForm.mode">
                                    <SelectTrigger id="extra-mode"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="TypeA"
                                                >TypeA</SelectItem
                                            >
                                            <SelectItem value="TypeB"
                                                >TypeB</SelectItem
                                            >
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="extra-key">密钥 (key)</Label>
                                <Input id="extra-key" v-model="extraForm.key" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="extra-sign">sign 参数名</Label>
                                <Input
                                    id="extra-sign"
                                    v-model="extraForm.sign_name"
                                />
                            </div>
                            <div
                                v-if="extraForm.mode === 'TypeA'"
                                class="grid gap-2"
                            >
                                <Label for="extra-time">time 参数名</Label>
                                <Input
                                    id="extra-time"
                                    v-model="extraForm.time_name"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="extra-diff">时间差 (秒)</Label>
                                <Input
                                    id="extra-diff"
                                    v-model="extraForm.time_diff"
                                    inputmode="numeric"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="extra-times">签名可用次数</Label>
                                <Input
                                    id="extra-times"
                                    v-model="extraForm.sign_use_times"
                                    inputmode="numeric"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- uid 用户选择（管理端特有） -->
                    <div class="grid gap-2">
                        <Label for="cc-uid">用户（不选 = 系统规则）</Label>
                        <Select v-model="ccUid" :disabled="!!editingCc">
                            <SelectTrigger id="cc-uid"
                                ><SelectValue placeholder="不选 — 系统规则"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value=""
                                        >不选 — 系统规则</SelectItem
                                    >
                                    <SelectItem
                                        v-for="u in userOptions"
                                        :key="u.id"
                                        :value="u.id"
                                    >
                                        #{{ u.id }} {{ u.name || u.email }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="editingCc"
                            class="text-xs text-muted-foreground"
                        >
                            编辑时不可修改归属
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="cc-des">备注</Label>
                        <textarea
                            id="cc-des"
                            v-model="ccForm.des"
                            class="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="ccDialogOpen = false"
                            >取消</Button
                        >
                        <Button type="submit" :disabled="ccSaving">
                            <Spinner v-if="ccSaving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            保存
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
