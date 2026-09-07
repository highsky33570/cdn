<script setup lang="ts">
import {
    AlertCircle,
    Pencil,
    Plus,
    Save,
    Search,
    ShieldCheck,
    Trash2,
    UnlockKeyhole,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner'
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
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
import { DateRangePicker } from '@/components/ui/date-range-picker';
import {
    formatDate,
    getErrorMessage,
    jsonText,
    numberValue,
    recordId,
    textValue,
    yesNo,
} from '@/lib/cdnRecord';
import {
    createUserAcl,
    createUserCcFilter,
    createUserCcMatcher,
    createUserCcRule,
    deleteUserAcl,
    deleteUserCcFilter,
    deleteUserCcMatcher,
    deleteUserCcRule,
    extractCdnflyRows,
    extractCdnflyTotal,
    listUserAcls,
    getUserBlackIpCount,
    listUserBlackIps,
    listUserCcFilters,
    listUserCcMatchers,
    listUserCcRules,
    listUserHistoryBlackIps,
    unlockUserBlackIps,
    updateUserAcl,
    updateUserCcFilter,
    updateUserCcMatcher,
    updateUserCcRule,
} from '@/lib/cdnUserApi';
import type {
    CdnAclPayload,
    CdnCcFilterPayload,
    CdnCcMatcherPayload,
    CdnCcRulePayload,
    CdnJobPayload,
    CdnflyRecord,
} from '@/lib/cdnUserApi';

type SecurityView = 'acls' | 'cc' | 'blackip';
type CcKind = 'matcher' | 'filter' | 'rule';

type MatcherCondition = {
    key: string;
    operator: string;
    value: string;
};

type AclEntry = {
    action: string;
    conditions: MatcherCondition[];
};

type RuleEntry = {
    action: string;
    matcher: string;
    filter1: string;
    filter2: string;
    state: boolean;
};

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

const props = defineProps<{
    view: SecurityView;
}>();

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

const ccKinds: Array<{ key: CcKind; label: string }> = [
    { key: 'matcher', label: '匹配器' },
    { key: 'filter', label: '过滤器' },
    { key: 'rule', label: '规则组' },
];

const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const formError = ref('');

const deleteOpen = ref(false);
const deleting = ref(false);
const deleteError = ref('');
const deleteTarget = ref<CdnflyRecord | null>(null);
const deleteKind = ref<'acl' | CcKind>('acl');

const aclDialogOpen = ref(false);
const editingAcl = ref<CdnflyRecord | null>(null);
const aclPage = ref(1);
const aclTotal = ref(0);
const aclRows = ref<CdnflyRecord[]>([]);
const aclFilters = reactive({
    search: '',
    enable: 'all',
    per_page: '20',
});
const aclForm = reactive({
    name: '',
    default_action: 'reject',
    des: '',
    enable: '1',
});
const aclEntries = ref<AclEntry[]>([]);

const activeCcKind = ref<CcKind>('matcher');
const ccDialogOpen = ref(false);
const editingCc = ref<CdnflyRecord | null>(null);
const ccPage = ref(1);
const ccTotal = ref(0);
const ccRows = ref<CdnflyRecord[]>([]);
const ccFilters = reactive({
    search: '',
    enable: 'all',
    per_page: '20',
});
const ccForm = reactive({
    name: '',
    type: 'captcha_filter',
    within_second: '60',
    max_req: '5',
    max_req_per_uri: '',
    sort: '100',
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

// 拉黑日志 Tab
const blackIpTab = ref<'current' | 'stats' | 'history'>('history');

// 当前拉黑
const blackIpPage = ref(1);
const blackIpTotal = ref(0);
const blackIpRows = ref<CdnflyRecord[]>([]);
const unlocking = ref(false);
const unlockingId = ref<string | null>(null);
const blackIpFilters = reactive({
    site_id: '',
    ip: '',
    per_page: '20',
});
const unlockForm = reactive({
    site_id: '',
    ip: '',
});

// 拉黑统计
const blackIpCountRows = ref<CdnflyRecord[]>([]);
const blackIpCountLoading = ref(false);

// 历史拉黑
const historyBlackIpPage = ref(1);
const historyBlackIpTotal = ref(0);
const historyBlackIpRows = ref<CdnflyRecord[]>([]);
const historyBlackIpLoading = ref(false);
const historyBlackIpFilters = reactive({
    ip: '',
    site_id: '',
    start: '',
    end: '',
    per_page: '10',
});

const pageTitle = computed(() => {
    if (props.view === 'cc') {
        return 'CC 防护';
    }

    if (props.view === 'blackip') {
        return '拉黑日志';
    }

    return 'ACL 规则';
});
const pageDescription = computed(() => {
    if (props.view === 'cc') {
        return '维护 CC 防护规则组、匹配条件和过滤器。';
    }

    if (props.view === 'blackip') {
        return '查询拉黑 IP 记录，并提交解锁任务。';
    }

    return '维护访问控制规则组。';
});
const aclDialogTitle = computed(() =>
    editingAcl.value ? '编辑 ACL' : '新增 ACL',
);
const ccDialogTitle = computed(() =>
    editingCc.value
        ? `编辑${ccKindLabel(activeCcKind.value)}`
        : `新增${ccKindLabel(activeCcKind.value)}`,
);
const hasAclPreviousPage = computed(() => aclPage.value > 1);
const hasAclNextPage = computed(
    () => aclPage.value * Number(aclFilters.per_page) < aclTotal.value,
);
const hasCcPreviousPage = computed(() => ccPage.value > 1);
const hasCcNextPage = computed(
    () => ccPage.value * Number(ccFilters.per_page) < ccTotal.value,
);
const hasBlackIpPreviousPage = computed(() => blackIpPage.value > 1);
const hasBlackIpNextPage = computed(
    () =>
        blackIpPage.value * Number(blackIpFilters.per_page) <
        blackIpTotal.value,
);
const displayedAclRows = computed(() => {
    const keyword = aclFilters.search.trim().toLowerCase();

    if (keyword === '') {
        return aclRows.value;
    }

    return aclRows.value.filter((row) =>
        [row.name, row.des, row.default_action]
            .map((value) => textValue(value).toLowerCase())
            .some((value) => value.includes(keyword)),
    );
});
const displayedCcRows = computed(() => {
    const keyword = ccFilters.search.trim().toLowerCase();

    if (keyword === '') {
        return ccRows.value;
    }

    return ccRows.value.filter((row) =>
        [row.name, row.des, row.type]
            .map((value) => textValue(value).toLowerCase())
            .some((value) => value.includes(keyword)),
    );
});

onMounted(() => {
    void reloadCurrentView();
});

async function reloadCurrentView(): Promise<void> {
    if (props.view === 'cc') {
        await loadCcRows();

        return;
    }

    if (props.view === 'blackip') {
        await loadHistoryBlackIps();
        return;
    }

    await loadAclRows();
}

async function loadAclRows(targetPage = aclPage.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(aclFilters.per_page),
        };

        if (aclFilters.enable !== 'all') {
            params.enable = aclFilters.enable;
        }

        const result = await listUserAcls(params);
        const rows = extractCdnflyRows(result);

        aclRows.value = rows;
        aclTotal.value = extractCdnflyTotal(result, rows.length);
        aclPage.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

function openAclCreateDialog(): void {
    editingAcl.value = null;
    aclForm.name = '';
    aclForm.default_action = 'reject';
    aclForm.des = '';
    aclForm.enable = '1';
    aclEntries.value = [{ action: 'allow', conditions: [{ key: 'ip', operator: '=', value: '' }] }];
    formError.value = '';
    aclDialogOpen.value = true;
}

function openAclEditDialog(record: CdnflyRecord): void {
    editingAcl.value = record;
    aclForm.name = textValue(record.name);
    aclForm.default_action = textValue(record.default_action) || 'reject';
    aclForm.des = textValue(record.des ?? record.remark);
    aclForm.enable = record.enable === 0 || record.enable === false ? '0' : '1';
    aclEntries.value = parseAclData(record.data);
    formError.value = '';
    aclDialogOpen.value = true;
}

async function submitAcl(): Promise<void> {
    const name = aclForm.name.trim();
    if (name === '') {
        formError.value = 'ACL 名称不能为空';
        return;
    }

    const payload: CdnAclPayload = {
        name,
        default_action: aclForm.default_action,
        data: buildAclData(),
        des: nullableText(aclForm.des),
        enable: aclForm.enable === '1' ? 1 : 0,
    };

    saving.value = true;
    formError.value = '';

    try {
        if (editingAcl.value) {
            const id = recordId(editingAcl.value);

            if (!id) {
                formError.value = 'ACL ID 缺失';

                return;
            }

            await updateUserAcl(id, payload);
            toast.success('ACL 更新请求已提交');
        } else {
            await createUserAcl(omitEnable(payload));
            toast.success('ACL 创建请求已提交');
        }

        aclDialogOpen.value = false;
        await loadAclRows();
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

function openDeleteAcl(record: CdnflyRecord): void {
    deleteTarget.value = record;
    deleteKind.value = 'acl';
    deleteError.value = '';
    deleteOpen.value = true;
}

async function confirmDelete(): Promise<void> {
    const id = recordId(deleteTarget.value!);
    if (!id) return;

    deleting.value = true;
    try {
        if (deleteKind.value === 'acl') {
            await deleteUserAcl(id);
            toast.success('ACL 删除请求已提交');
        } else {
            await ccDelete(deleteKind.value as CcKind, id);
            toast.success(`${ccKindLabel(deleteKind.value as CcKind)}删除请求已提交`);
        }
        deleteOpen.value = false;
        if (deleteKind.value === 'acl') {
            await loadAclRows();
        } else {
            await loadCcRows();
        }
    } catch (error) {
        deleteError.value = getErrorMessage(error);
    } finally {
        deleting.value = false;
    }
}

async function loadCcRows(targetPage = ccPage.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(ccFilters.per_page),
            internal_self: 1,
        };

        if (ccFilters.enable !== 'all') {
            params.enable = ccFilters.enable;
        }

        const result = await ccList(activeCcKind.value, params);
        const rows = extractCdnflyRows(result);

        ccRows.value = rows;
        ccTotal.value = extractCdnflyTotal(result, rows.length);
        ccPage.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

function selectCcKind(kind: CcKind): void {
    activeCcKind.value = kind;
    ccPage.value = 1;
    void loadCcRows(1);
}

function buildMatcherData(): Record<string, unknown> {
    return buildMatcherObject(matcherConditions.value);
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

function buildMatcherObject(conditions: MatcherCondition[]): Record<string, unknown> {
    const data: Record<string, unknown> = {};
    for (const c of conditions) {
        if (!c.key) continue;
        const isArrayOp = c.operator === 'AC' || c.operator === '!AC';
        data[c.key] = {
            operator: c.operator || '=',
            value: isArrayOp
                ? c.value.split(',').map(s => s.trim()).filter(Boolean)
                : c.value,
        };
    }
    return data;
}

function buildAclData(): unknown[] {
    return aclEntries.value.map(entry => ({
        acl_action: entry.action,
        acl_matcher: buildMatcherObject(entry.conditions),
    }));
}

function parseAclData(raw: unknown): AclEntry[] {
    let arr: unknown[];
    if (typeof raw === 'string') {
        try { arr = JSON.parse(raw); } catch { arr = []; }
    } else {
        arr = Array.isArray(raw) ? raw : [];
    }
    return arr.map((item: any) => ({
        action: String(item.acl_action ?? 'reject'),
        conditions: parseMatcherData(item.acl_matcher ?? {}),
    }));
}

function addAclEntry(): void {
    aclEntries.value.push({ action: 'allow', conditions: [{ key: 'ip', operator: '=', value: '' }] });
}

function removeAclEntry(index: number): void {
    aclEntries.value.splice(index, 1);
}

function addAclCondition(entryIndex: number): void {
    aclEntries.value[entryIndex].conditions.push({ key: 'uri', operator: 'contain', value: '' });
}

function removeAclCondition(entryIndex: number, condIndex: number): void {
    aclEntries.value[entryIndex].conditions.splice(condIndex, 1);
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
    if (extraForm.mode === 'TypeA') {
        obj.time_name = extraForm.time_name;
    }
    return obj;
}

function parseExtra(extra: unknown): void {
    let raw = extra;
    if (typeof raw === 'string') {
        try { raw = JSON.parse(raw); } catch { raw = {}; }
    }
    const e = (raw && typeof raw === 'object' && !Array.isArray(raw) ? raw : {}) as Record<string, unknown>;
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
            ccList('matcher', { limit: 200, internal_self: 1 }),
            ccList('filter', { limit: 200, internal_self: 1 }),
        ]);
        matcherOptions.value = extractCdnflyRows(matchers).map(r => ({
            id: String(r.id),
            name: textValue(r.name),
        }));
        filterOptions.value = extractCdnflyRows(filters).map(r => ({
            id: String(r.id),
            name: textValue(r.name),
        }));
    } catch {
        // non-critical
    } finally {
        loadingRuleOptions.value = false;
    }
}

function addCondition(): void {
    matcherConditions.value.push({ key: 'uri', operator: 'contain', value: '' });
}

function removeCondition(index: number): void {
    matcherConditions.value.splice(index, 1);
}

function addRuleEntry(): void {
    ruleEntries.value.push({ action: 'ipset', matcher: '', filter1: '', filter2: '', state: true });
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
    ccForm.des = '';
    ccForm.enable = '1';
    ccForm.is_show = '1';
    formError.value = '';
    matcherConditions.value = [];
    ruleEntries.value = [];
    parseExtra(null);
    if (activeCcKind.value === 'rule') {
        void loadRuleFormOptions();
    }
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
    ccForm.des = textValue(record.des ?? record.remark);
    ccForm.enable = record.enable === 0 || record.enable === false ? '0' : '1';
    ccForm.is_show =
        record.is_show === 0 || record.is_show === false ? '0' : '1';
    formError.value = '';

    const rawData = record.data;
    if (activeCcKind.value === 'matcher') {
        let dataObj: Record<string, unknown>;
        if (typeof rawData === 'string') {
            try { dataObj = JSON.parse(rawData); } catch { dataObj = {}; }
        } else if (rawData && typeof rawData === 'object' && !Array.isArray(rawData)) {
            dataObj = rawData as Record<string, unknown>;
        } else {
            dataObj = {};
        }
        matcherConditions.value = parseMatcherData(dataObj);
        ruleEntries.value = [];
    } else if (activeCcKind.value === 'rule') {
        let dataArr: unknown[];
        if (typeof rawData === 'string') {
            try { dataArr = JSON.parse(rawData); } catch { dataArr = []; }
        } else {
            dataArr = Array.isArray(rawData) ? rawData : [];
        }
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
    ccDialogOpen.value = true;
}

async function submitCc(): Promise<void> {
    let payload: CdnCcMatcherPayload | CdnCcFilterPayload | CdnCcRulePayload;

    try {
        payload = buildCcPayload();
    } catch (error) {
        formError.value = getErrorMessage(error);

        return;
    }

    if (payload.name.trim() === '') {
        formError.value = '名称不能为空';

        return;
    }

    saving.value = true;
    formError.value = '';

    try {
        if (editingCc.value) {
            const id = recordId(editingCc.value);

            if (!id) {
                formError.value = `${ccKindLabel(activeCcKind.value)} ID 缺失`;

                return;
            }

            await ccUpdate(activeCcKind.value, id, payload);
            toast.success(`${ccKindLabel(activeCcKind.value)}更新请求已提交`);
        } else {
            await ccCreate(activeCcKind.value, payload);
            toast.success(`${ccKindLabel(activeCcKind.value)}创建请求已提交`);
        }

        ccDialogOpen.value = false;
        await loadCcRows();
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

function openDeleteCc(record: CdnflyRecord): void {
    deleteTarget.value = record;
    deleteKind.value = activeCcKind.value;
    deleteError.value = '';
    deleteOpen.value = true;
}

async function loadBlackIps(targetPage = blackIpPage.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(blackIpFilters.per_page),
        };

        if (blackIpFilters.site_id.trim() !== '') {
            params.site_id = blackIpFilters.site_id.trim();
        }

        if (blackIpFilters.ip.trim() !== '') {
            params.ip = blackIpFilters.ip.trim();
        }

        const result = await listUserBlackIps(params);
        const rows = extractCdnflyRows(result);

        blackIpRows.value = rows;
        blackIpTotal.value = extractCdnflyTotal(result, rows.length);
        blackIpPage.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function loadHistoryBlackIps(targetPage = historyBlackIpPage.value): Promise<void> {
    historyBlackIpLoading.value = true;
    errorMessage.value = '';
    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(historyBlackIpFilters.per_page),
        };
        if (historyBlackIpFilters.ip.trim()) params.ip = historyBlackIpFilters.ip.trim();
        if (historyBlackIpFilters.site_id.trim()) params.site_id = historyBlackIpFilters.site_id.trim();
        if (historyBlackIpFilters.start.trim()) params.start = historyBlackIpFilters.start.trim();
        if (historyBlackIpFilters.end.trim()) params.end = historyBlackIpFilters.end.trim();
        const result = await listUserHistoryBlackIps(params);
        const rows = extractCdnflyRows(result);
        historyBlackIpRows.value = rows;
        historyBlackIpTotal.value = extractCdnflyTotal(result, rows.length);
        historyBlackIpPage.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        historyBlackIpLoading.value = false;
    }
}

async function loadBlackIpCount(): Promise<void> {
    blackIpCountLoading.value = true;
    errorMessage.value = '';
    try {
        const result = await getUserBlackIpCount();
        blackIpCountRows.value = extractCdnflyRows(result);
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        blackIpCountLoading.value = false;
    }
}

async function switchBlackIpTab(tab: 'current' | 'stats' | 'history'): Promise<void> {
    blackIpTab.value = tab;
    if (tab === 'current' && blackIpRows.value.length === 0) void loadBlackIps();
    if (tab === 'stats' && blackIpCountRows.value.length === 0) void loadBlackIpCount();
    if (tab === 'history' && historyBlackIpRows.value.length === 0) void loadHistoryBlackIps();
}

async function unlockBlackIp(
    siteId: string,
    ip: string,
    lockKey = 'manual',
): Promise<void> {
    if (siteId.trim() === '') {
        formError.value = 'site_id 不能为空';

        return;
    }

    const data: Record<string, string> = {
        site_id: siteId.trim(),
        key1: 'site_id',
    };

    if (ip.trim() !== '') {
        data.ip = ip.trim();
    }

    const payload: CdnJobPayload[] = [
        {
            type: 'unlock_ip',
            data,
        },
    ];

    unlocking.value = true;
    unlockingId.value = lockKey;
    formError.value = '';

    try {
        await unlockUserBlackIps(payload);
        toast.success(ip.trim()
            ? `已提交 ${ip.trim()} 解锁任务`
            : `已提交站点 ${siteId.trim()} 全部黑名单解锁任务`);
        unlockForm.ip = '';
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        unlocking.value = false;
        unlockingId.value = null;
    }
}

function buildCcPayload():
    | CdnCcMatcherPayload
    | CdnCcFilterPayload
    | CdnCcRulePayload {
    if (activeCcKind.value === 'filter') {
        return {
            name: ccForm.name.trim(),
            type: ccForm.type,
            within_second: requiredNumber(ccForm.within_second, '统计秒数'),
            max_req: requiredNumber(ccForm.max_req, '最大请求数'),
            max_req_per_uri: optionalNumber(ccForm.max_req_per_uri),
            extra: buildExtra(),
            des: nullableText(ccForm.des),
            enable: ccForm.enable === '1' ? 1 : 0,
        };
    }

    if (activeCcKind.value === 'rule') {
        if (ruleEntries.value.length === 0) {
            throw new Error('至少需要添加一条规则条目');
        }
        const missingMatcher = ruleEntries.value.findIndex(e => !e.matcher);
        if (missingMatcher !== -1) {
            throw new Error(`第 ${missingMatcher + 1} 条规则未选择匹配器`);
        }
        return {
            name: ccForm.name.trim(),
            sort: optionalNumber(ccForm.sort) ?? 100,
            data: ruleEntries.value.map(e => ({
                action: e.action,
                matcher: e.matcher,
                filter1: e.filter1,
                filter2: e.filter2 || '',
                state: e.state,
            })),
            des: nullableText(ccForm.des),
            enable: ccForm.enable === '1' ? 1 : 0,
            is_show: ccForm.is_show === '1' ? 1 : 0,
        };
    }

    return {
        name: ccForm.name.trim(),
        data: buildMatcherData(),
        des: nullableText(ccForm.des),
        enable: ccForm.enable === '1' ? 1 : 0,
    };
}

async function ccList(kind: CcKind, params: Record<string, string | number>) {
    if (kind === 'filter') {
        return listUserCcFilters(params);
    }

    if (kind === 'rule') {
        return listUserCcRules(params);
    }

    return listUserCcMatchers(params);
}

function ccCreate(
    kind: CcKind,
    payload: CdnCcMatcherPayload | CdnCcFilterPayload | CdnCcRulePayload,
) {
    if (kind === 'filter') {
        return createUserCcFilter(omitEnable(payload as CdnCcFilterPayload));
    }

    if (kind === 'rule') {
        return createUserCcRule(payload as CdnCcRulePayload);
    }

    return createUserCcMatcher(omitEnable(payload as CdnCcMatcherPayload));
}

function ccUpdate(
    kind: CcKind,
    id: number,
    payload: CdnCcMatcherPayload | CdnCcFilterPayload | CdnCcRulePayload,
) {
    if (kind === 'filter') {
        return updateUserCcFilter(id, payload as Partial<CdnCcFilterPayload>);
    }

    if (kind === 'rule') {
        return updateUserCcRule(id, payload as Partial<CdnCcRulePayload>);
    }

    return updateUserCcMatcher(id, payload as Partial<CdnCcMatcherPayload>);
}

function ccDelete(kind: CcKind, id: number) {
    if (kind === 'filter') {
        return deleteUserCcFilter(id);
    }

    if (kind === 'rule') {
        return deleteUserCcRule(id);
    }

    return deleteUserCcMatcher(id);
}

function ccKindLabel(kind: CcKind): string {
    return ccKinds.find((item) => item.key === kind)?.label ?? '资源';
}

function prevAclPage(): void {
    if (hasAclPreviousPage.value) {
        void loadAclRows(aclPage.value - 1);
    }
}

function nextAclPage(): void {
    if (hasAclNextPage.value) {
        void loadAclRows(aclPage.value + 1);
    }
}

function prevCcPage(): void {
    if (hasCcPreviousPage.value) {
        void loadCcRows(ccPage.value - 1);
    }
}

function nextCcPage(): void {
    if (hasCcNextPage.value) {
        void loadCcRows(ccPage.value + 1);
    }
}

function prevBlackIpPage(): void {
    if (hasBlackIpPreviousPage.value) {
        void loadBlackIps(blackIpPage.value - 1);
    }
}

function nextBlackIpPage(): void {
    if (hasBlackIpNextPage.value) {
        void loadBlackIps(blackIpPage.value + 1);
    }
}

function recordName(record: CdnflyRecord): string {
    return textValue(record.name) || `#${textValue(record.id)}`;
}

function dataCount(value: unknown): string {
    try {
        const parsed = typeof value === 'string' ? JSON.parse(value) : value;

        if (Array.isArray(parsed)) {
            return `${parsed.length} 条`;
        }

        if (parsed && typeof parsed === 'object') {
            return `${Object.keys(parsed).length} 项`;
        }
    } catch {
        return '已配置';
    }

    return '-';
}

function requiredNumber(value: string, label: string): number {
    const parsed = optionalNumber(value);

    if (parsed === null) {
        throw new Error(`${label} 必须是数字`);
    }

    return parsed;
}

function optionalNumber(value: string): number | null {
    return numberValue(value);
}

function nullableText(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function omitEnable<TPayload extends { enable?: unknown }>(
    payload: TPayload,
): Omit<TPayload, 'enable'> {
    const clone = { ...payload };

    delete clone.enable;

    return clone;
}
</script>

<template>
    <div class="space-y-6">
        <ConsolePageHeader
            eyebrow="用户端 / 安全防护"
            :title="pageTitle"
            :description="pageDescription"
            :icon="ShieldCheck"
            :show-api-badge="false"
        />

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>
        <Alert v-if="formError" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>提交失败</AlertTitle>
            <AlertDescription>{{ formError }}</AlertDescription>
        </Alert>

        <Card v-if="props.view === 'acls'">
            <CardHeader
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <CardTitle>ACL 规则组</CardTitle>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ aclTotal === 0 ? '暂无 ACL' : `${aclTotal} 个 ACL` }}
                    </p>
                </div>
                <form
                    class="grid gap-2 md:grid-cols-[1fr_120px_120px_auto_auto]"
                    @submit.prevent="loadAclRows(1)"
                >
                    <Input v-model="aclFilters.search" placeholder="搜索名称" />
                    <Select v-model="aclFilters.enable">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">全部状态</SelectItem>
                                <SelectItem value="1">启用</SelectItem>
                                <SelectItem value="0">禁用</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Select v-model="aclFilters.per_page">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="10">10 条</SelectItem>
                                <SelectItem value="20">20 条</SelectItem>
                                <SelectItem value="50">50 条</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Button type="submit" :disabled="loading">
                        <Spinner v-if="loading" data-icon="inline-start" />
                        <Search v-else data-icon="inline-start" />
                        查询
                    </Button>
                    <Button type="button" @click="openAclCreateDialog">
                        <Plus data-icon="inline-start" />
                        新增
                    </Button>
                </form>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto border-y">
                    <table class="w-full min-w-[880px] table-fixed text-sm">
                        <colgroup>
                            <col style="width: 22%" />
                            <col style="width: 14%" />
                            <col style="width: 14%" />
                            <col style="width: 14%" />
                            <col style="width: 18%" />
                            <col style="width: 18%" />
                        </colgroup>
                        <thead class="border-b text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">
                                    名称
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    默认动作
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    规则
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
                            <tr v-if="loading" class="border-b">
                                <td class="px-4 py-12 text-center" colspan="6">
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <tr
                                v-for="acl in displayedAclRows"
                                :key="textValue(acl.id)"
                                class="border-b"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ recordName(acl) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        #{{ textValue(acl.id) || '-' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge variant="outline">
                                        {{
                                            textValue(acl.default_action) ===
                                            'allow'
                                                ? '允许'
                                                : '拒绝'
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    {{ dataCount(acl.data) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Badge variant="secondary">
                                        {{ yesNo(acl.enable) }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{
                                        formatDate(
                                            acl.update_at2 ?? acl.create_at2,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openAclEditDialog(acl)"
                                        >
                                            <Pencil data-icon="inline-start" />
                                            编辑
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            @click="openDeleteAcl(acl)"
                                        >
                                            <Trash2 data-icon="inline-start" />
                                            删除
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-if="!loading && displayedAclRows.length === 0"
                            >
                                <td
                                    class="px-6 py-16 text-center text-muted-foreground"
                                    colspan="6"
                                >
                                    暂无 ACL
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Card v-else-if="props.view === 'cc'">
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
                    <Button type="submit" :disabled="loading">
                        <Spinner v-if="loading" data-icon="inline-start" />
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
                <div class="overflow-x-auto border-y">
                    <table class="w-full min-w-[900px] table-fixed text-sm">
                        <colgroup>
                            <col style="width: 22%" />
                            <col style="width: 16%" />
                            <col style="width: 20%" />
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
                            <tr v-if="loading" class="border-b">
                                <td class="px-4 py-12 text-center" colspan="6">
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
                                        {{ recordName(record) }}
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
                                    <div class="truncate font-mono text-xs">
                                        {{ jsonText(record.data, '-') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Badge variant="secondary">
                                        {{ yesNo(record.enable) }}
                                    </Badge>
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
                                            @click="openDeleteCc(record)"
                                        >
                                            <Trash2 data-icon="inline-start" />
                                            删除
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && displayedCcRows.length === 0">
                                <td
                                    class="px-6 py-16 text-center text-muted-foreground"
                                    colspan="6"
                                >
                                    暂无{{ ccKindLabel(activeCcKind) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div v-else class="space-y-4">
            <!-- 三个 Tab -->
            <div class="flex gap-1 border-b">
                <button
                    v-for="tab in [{ key: 'current', label: '当前拉黑' }, { key: 'stats', label: '拉黑统计' }, { key: 'history', label: '历史拉黑' }]"
                    :key="tab.key"
                    type="button"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
                    :class="blackIpTab === tab.key
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="switchBlackIpTab(tab.key as 'current' | 'stats' | 'history')"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- ── 历史拉黑 ── -->
            <template v-if="blackIpTab === 'history'">
                <!-- 筛选栏 -->
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1.5 rounded-md border px-3 h-9">
                        <span class="text-xs text-muted-foreground whitespace-nowrap">IP地址</span>
                        <input
                            v-model="historyBlackIpFilters.ip"
                            class="w-36 bg-transparent text-sm outline-none placeholder:text-muted-foreground/60"
                            placeholder="请输入IP地址"
                            @keydown.enter="loadHistoryBlackIps(1)"
                        />
                    </div>
                    <div class="flex items-center gap-1.5 rounded-md border px-3 h-9">
                        <span class="text-xs text-muted-foreground whitespace-nowrap">网站ID</span>
                        <input
                            v-model="historyBlackIpFilters.site_id"
                            class="w-28 bg-transparent text-sm outline-none placeholder:text-muted-foreground/60"
                            placeholder="请输入网站ID"
                            @keydown.enter="loadHistoryBlackIps(1)"
                        />
                    </div>
                    <DateRangePicker
                        :start="historyBlackIpFilters.start"
                        :end="historyBlackIpFilters.end"
                        @update:start="historyBlackIpFilters.start = $event"
                        @update:end="historyBlackIpFilters.end = $event"
                    />
                    <button
                        type="button"
                        class="text-sm text-muted-foreground hover:text-foreground"
                        @click="() => { historyBlackIpFilters.ip = ''; historyBlackIpFilters.site_id = ''; historyBlackIpFilters.start = ''; historyBlackIpFilters.end = ''; void loadHistoryBlackIps(1); }"
                    >清除</button>
                    <Button class="h-9 ml-auto" :disabled="historyBlackIpLoading" @click="loadHistoryBlackIps(1)">
                        <Spinner v-if="historyBlackIpLoading" data-icon="inline-start" />
                        <Search v-else data-icon="inline-start" />
                        查询
                    </Button>
                </div>

                <!-- 表格 -->
                <Card>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/30">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">网站ID</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">域名</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">IP</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">位置</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">过滤器</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">拉黑时间</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">手动解锁?</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="historyBlackIpLoading">
                                        <td colspan="7" class="px-4 py-12 text-center"><Spinner class="mx-auto" /></td>
                                    </tr>
                                    <tr
                                        v-for="(row, index) in historyBlackIpRows"
                                        :key="index"
                                        class="border-b last:border-0 hover:bg-muted/20 transition-colors"
                                    >
                                        <td class="px-4 py-3 text-muted-foreground">{{ textValue(row.site_id) || '-' }}</td>
                                        <td class="px-4 py-3">{{ textValue(row.domain) || textValue(row.host) || '-' }}</td>
                                        <td class="px-4 py-3 font-mono text-xs">{{ textValue(row.ip) || '-' }}</td>
                                        <td class="px-4 py-3 text-muted-foreground">{{ textValue(row.position) || textValue(row.country) || '-' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium">
                                                {{ textValue(row.name) || textValue(row.filter) || '-' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground text-xs tabular-nums">
                                            {{ formatDate(row.create_time ?? row.created_at ?? row.time) }}
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground">
                                            {{ (row.manual_unlock === true || row.manual_unlock === 1) ? '是' : '否' }}
                                        </td>
                                    </tr>
                                    <tr v-if="!historyBlackIpLoading && historyBlackIpRows.length === 0">
                                        <td colspan="7" class="px-4 py-16 text-center text-muted-foreground">暂无历史拉黑记录</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- 分页 -->
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <span>共 {{ historyBlackIpTotal }} 条</span>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" :disabled="historyBlackIpPage <= 1 || historyBlackIpLoading" @click="loadHistoryBlackIps(historyBlackIpPage - 1)">上一页</Button>
                        <span>第 {{ historyBlackIpPage }} 页</span>
                        <Button variant="outline" size="sm" :disabled="historyBlackIpPage * Number(historyBlackIpFilters.per_page) >= historyBlackIpTotal || historyBlackIpLoading" @click="loadHistoryBlackIps(historyBlackIpPage + 1)">下一页</Button>
                    </div>
                </div>
            </template>

            <!-- ── 拉黑统计 ── -->
            <template v-else-if="blackIpTab === 'stats'">
                <Card>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/30">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground w-16">排行</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">网站ID</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">黑名单数量</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="blackIpCountLoading">
                                        <td colspan="3" class="px-4 py-12 text-center"><Spinner class="mx-auto" /></td>
                                    </tr>
                                    <tr
                                        v-for="(row, index) in blackIpCountRows"
                                        :key="index"
                                        class="border-b last:border-0 hover:bg-muted/20"
                                    >
                                        <td class="px-4 py-3 text-muted-foreground">{{ index + 1 }}</td>
                                        <td class="px-4 py-3">{{ textValue(row.site_id) || '-' }}</td>
                                        <td class="px-4 py-3 tabular-nums">{{ textValue(row.count) || textValue(row.total) || '-' }}</td>
                                    </tr>
                                    <tr v-if="!blackIpCountLoading && blackIpCountRows.length === 0">
                                        <td colspan="3" class="px-4 py-16 text-center text-muted-foreground">暂无数据</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <!-- ── 当前拉黑 ── -->
            <template v-else>
                <!-- 解锁操作栏 -->
                <div class="flex gap-2">
                    <Button size="sm" variant="outline" @click="unlockBlackIp(unlockForm.site_id, unlockForm.ip, 'batch')">
                        <UnlockKeyhole data-icon="inline-start" />解锁IP
                    </Button>
                    <Button size="sm" variant="outline" @click="unlockBlackIp(unlockForm.site_id, '', 'site')">
                        解锁网站
                    </Button>
                </div>
                <!-- 筛选栏 -->
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1.5 rounded-md border px-3 h-9">
                        <span class="text-xs text-muted-foreground">IP地址</span>
                        <input v-model="blackIpFilters.ip" class="w-36 bg-transparent text-sm outline-none placeholder:text-muted-foreground/60" placeholder="请输入IP地址" @keydown.enter="loadBlackIps(1)" />
                    </div>
                    <div class="flex items-center gap-1.5 rounded-md border px-3 h-9">
                        <span class="text-xs text-muted-foreground">网站ID</span>
                        <input v-model="blackIpFilters.site_id" class="w-28 bg-transparent text-sm outline-none placeholder:text-muted-foreground/60" placeholder="请输入网站ID" @keydown.enter="loadBlackIps(1)" />
                    </div>
                    <button type="button" class="text-sm text-muted-foreground hover:text-foreground" @click="() => { blackIpFilters.ip = ''; blackIpFilters.site_id = ''; void loadBlackIps(1); }">清除</button>
                    <Button class="h-9 ml-auto" :disabled="loading" @click="loadBlackIps(1)">
                        <Spinner v-if="loading" data-icon="inline-start" />
                        <Search v-else data-icon="inline-start" />
                        查询
                    </Button>
                </div>
                <Card>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/30">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">网站ID</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">域名</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">IP</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">位置</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">过滤器</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">拉黑时间</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">解锁时间</th>
                                        <th class="px-4 py-3 text-right font-medium text-muted-foreground">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="loading"><td colspan="8" class="px-4 py-12 text-center"><Spinner class="mx-auto" /></td></tr>
                                    <tr v-for="row in blackIpRows" :key="`${textValue(row.site_id)}-${textValue(row.ip)}`" class="border-b last:border-0 hover:bg-muted/20 transition-colors">
                                        <td class="px-4 py-3 text-muted-foreground">{{ textValue(row.site_id) || '-' }}</td>
                                        <td class="px-4 py-3">{{ textValue(row.domain) || '-' }}</td>
                                        <td class="px-4 py-3 font-mono text-xs">{{ textValue(row.ip) || '-' }}</td>
                                        <td class="px-4 py-3 text-muted-foreground">{{ textValue(row.position) || '-' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium">
                                                {{ textValue(row.name) || '-' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground text-xs tabular-nums">{{ formatDate(row.create_time ?? row.time) }}</td>
                                        <td class="px-4 py-3 text-muted-foreground text-xs tabular-nums">{{ textValue(row.exp) || '-' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <Button variant="outline" size="sm"
                                                :disabled="unlocking && unlockingId === `${textValue(row.site_id)}-${textValue(row.ip)}`"
                                                @click="unlockBlackIp(textValue(row.site_id), textValue(row.ip), `${textValue(row.site_id)}-${textValue(row.ip)}`)"
                                            >
                                                <Spinner v-if="unlocking && unlockingId === `${textValue(row.site_id)}-${textValue(row.ip)}`" data-icon="inline-start" />
                                                <UnlockKeyhole v-else data-icon="inline-start" />
                                                解锁
                                            </Button>
                                        </td>
                                    </tr>
                                    <tr v-if="!loading && blackIpRows.length === 0"><td colspan="8" class="px-4 py-16 text-center text-muted-foreground">暂无黑名单</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <span>共 {{ blackIpTotal }} 条</span>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" :disabled="!hasBlackIpPreviousPage || loading" @click="prevBlackIpPage">上一页</Button>
                        <span>第 {{ blackIpPage }} 页</span>
                        <Button variant="outline" size="sm" :disabled="!hasBlackIpNextPage || loading" @click="nextBlackIpPage">下一页</Button>
                    </div>
                </div>
            </template>
        </div>


        <div
            v-if="props.view === 'acls'"
            class="flex items-center justify-end gap-2"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasAclPreviousPage || loading"
                @click="prevAclPage"
            >
                上一页
            </Button>
            <span class="text-sm text-muted-foreground">
                第 {{ aclPage }} 页
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasAclNextPage || loading"
                @click="nextAclPage"
            >
                下一页
            </Button>
        </div>
        <div
            v-else-if="props.view === 'cc'"
            class="flex items-center justify-end gap-2"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasCcPreviousPage || loading"
                @click="prevCcPage"
            >
                上一页
            </Button>
            <span class="text-sm text-muted-foreground">
                第 {{ ccPage }} 页
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasCcNextPage || loading"
                @click="nextCcPage"
            >
                下一页
            </Button>
        </div>

        <Dialog v-model:open="aclDialogOpen">
            <DialogScrollContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{ aclDialogTitle }}</DialogTitle>
                    <DialogDescription>
                        每条规则由动作（允许/拒绝）和匹配条件组成，不匹配任何规则时执行默认动作。
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-5" @submit.prevent="submitAcl">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="acl-name">名称</Label>
                            <Input
                                id="acl-name"
                                v-model="aclForm.name"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>默认动作</Label>
                            <Select v-model="aclForm.default_action">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="reject"
                                            >拒绝</SelectItem
                                        >
                                        <SelectItem value="allow"
                                            >允许</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>状态</Label>
                            <Select v-model="aclForm.enable">
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
                        <div class="flex items-center justify-between">
                            <Label>规则条目</Label>
                            <Button type="button" variant="outline" size="sm" @click="addAclEntry">
                                <Plus data-icon="inline-start" class="size-3.5" /> 添加规则
                            </Button>
                        </div>
                        <p v-if="aclEntries.length === 0" class="text-xs text-muted-foreground">
                            未添加任何规则条目
                        </p>
                        <div v-for="(entry, ei) in aclEntries" :key="ei" class="border rounded-md p-3 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <Label class="text-xs whitespace-nowrap">动作</Label>
                                    <Select v-model="entry.action">
                                        <SelectTrigger class="h-8 w-24 text-xs"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="allow">允许</SelectItem>
                                                <SelectItem value="reject">拒绝</SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button type="button" variant="ghost" size="sm" class="h-7 text-xs" @click="addAclCondition(ei)">
                                        <Plus class="size-3" /> 条件
                                    </Button>
                                    <Button type="button" variant="ghost" size="icon" class="size-7" @click="removeAclEntry(ei)">
                                        <X class="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                            <div v-for="(cond, ci) in entry.conditions" :key="ci" class="grid grid-cols-[1fr_120px_1fr_auto] gap-2">
                                <Select v-model="cond.key">
                                    <SelectTrigger><SelectValue placeholder="选择字段" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem v-for="mk in MATCHER_KEYS" :key="mk.value" :value="mk.value">
                                                {{ mk.label }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <Select v-model="cond.operator">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem v-for="op in OPERATORS" :key="op.value" :value="op.value">
                                                {{ op.label }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <Input v-model="cond.value" :placeholder="cond.operator === 'AC' || cond.operator === '!AC' ? '逗号分隔多个值' : '输入值'" />
                                <Button type="button" variant="ghost" size="icon" class="size-9" @click="removeAclCondition(ei, ci)">
                                    <X class="size-4" />
                                </Button>
                            </div>
                            <p v-if="entry.conditions.length === 0" class="text-xs text-muted-foreground pl-1">
                                无匹配条件 = 匹配所有请求
                            </p>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="acl-des">备注</Label>
                        <textarea
                            id="acl-des"
                            v-model="aclForm.des"
                            class="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </div>
                    <Alert v-if="formError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertDescription>{{ formError }}</AlertDescription>
                    </Alert>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="aclDialogOpen = false"
                        >
                            取消
                        </Button>
                        <Button type="submit" :disabled="saving">
                            <Spinner v-if="saving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            保存
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <Dialog v-model:open="ccDialogOpen">
            <DialogScrollContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{ ccDialogTitle }}</DialogTitle>
                    <DialogDescription>
                        规则组 data 为数组格式，匹配器 data 为对象格式。
                    </DialogDescription>
                </DialogHeader>
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
                                        >
                                            {{ type }}
                                        </SelectItem>
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
                            <SelectTrigger class="max-w-40">
                                <SelectValue />
                            </SelectTrigger>
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
                            <Button type="button" variant="outline" size="sm" @click="addCondition">
                                <Plus data-icon="inline-start" class="size-3.5" /> 添加条件
                            </Button>
                        </div>
                        <p v-if="matcherConditions.length === 0" class="text-xs text-muted-foreground">
                            不添加任何条件 = 匹配所有请求（data 为 {}）
                        </p>
                        <div v-for="(cond, index) in matcherConditions" :key="index" class="grid grid-cols-[1fr_120px_1fr_auto] gap-2">
                            <Select v-model="cond.key">
                                <SelectTrigger><SelectValue placeholder="选择字段" /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem v-for="mk in MATCHER_KEYS" :key="mk.value" :value="mk.value">
                                            {{ mk.label }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <Select v-model="cond.operator">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem v-for="op in OPERATORS" :key="op.value" :value="op.value">
                                            {{ op.label }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <Input v-model="cond.value" :placeholder="cond.operator === 'AC' || cond.operator === '!AC' ? '逗号分隔多个值' : '输入值'" />
                            <Button type="button" variant="ghost" size="icon" class="size-9" @click="removeCondition(index)">
                                <X class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- 规则组：动态规则条目行 -->
                    <div v-if="activeCcKind === 'rule'" class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label>规则条目</Label>
                            <Button type="button" variant="outline" size="sm" :disabled="loadingRuleOptions" @click="addRuleEntry">
                                <Plus data-icon="inline-start" class="size-3.5" /> 添加条目
                            </Button>
                        </div>
                        <div v-if="loadingRuleOptions" class="flex items-center gap-2 text-sm text-muted-foreground py-2">
                            <Spinner class="size-4" /> 加载匹配器和过滤器列表...
                        </div>
                        <div v-for="(entry, index) in ruleEntries" :key="index" class="grid grid-cols-[110px_1fr_1fr_1fr_60px_auto] gap-1.5 items-end">
                            <div class="grid gap-1">
                                <Label class="text-[10px]">动作</Label>
                                <Select v-model="entry.action">
                                    <SelectTrigger class="h-9 text-xs"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem v-for="a in RULE_ACTIONS" :key="a.value" :value="a.value">
                                                {{ a.label }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-[10px]">匹配器</Label>
                                <Select v-model="entry.matcher">
                                    <SelectTrigger class="h-9 text-xs"><SelectValue placeholder="选择匹配器" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem v-for="m in matcherOptions" :key="m.id" :value="m.id">
                                                #{{ m.id }} {{ m.name }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-[10px]">过滤器 1</Label>
                                <Select v-model="entry.filter1">
                                    <SelectTrigger class="h-9 text-xs"><SelectValue placeholder="选择过滤器" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="">(无)</SelectItem>
                                            <SelectItem v-for="f in filterOptions" :key="f.id" :value="f.id">
                                                #{{ f.id }} {{ f.name }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-[10px]">过滤器 2</Label>
                                <Select v-model="entry.filter2">
                                    <SelectTrigger class="h-9 text-xs"><SelectValue placeholder="(可选)" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="">(无)</SelectItem>
                                            <SelectItem v-for="f in filterOptions" :key="f.id" :value="f.id">
                                                #{{ f.id }} {{ f.name }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-[10px] text-center">启用</Label>
                                <div class="flex items-center justify-center h-9">
                                    <Switch :checked="entry.state" @update:checked="entry.state = $event" />
                                </div>
                            </div>
                            <Button type="button" variant="ghost" size="icon" class="size-9 self-end" @click="removeRuleEntry(index)">
                                <X class="size-4" />
                            </Button>
                        </div>
                    </div>
                    <div v-if="activeCcKind === 'filter' && ccForm.type === 'url_auth'" class="border rounded-md p-4 space-y-3">
                        <Label class="font-medium">URL 鉴权配置</Label>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="extra-mode">模式</Label>
                                <Select v-model="extraForm.mode">
                                    <SelectTrigger id="extra-mode"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="TypeA">TypeA</SelectItem>
                                            <SelectItem value="TypeB">TypeB</SelectItem>
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
                                <Input id="extra-sign" v-model="extraForm.sign_name" />
                            </div>
                            <div v-if="extraForm.mode === 'TypeA'" class="grid gap-2">
                                <Label for="extra-time">time 参数名</Label>
                                <Input id="extra-time" v-model="extraForm.time_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="extra-diff">时间差 (秒)</Label>
                                <Input id="extra-diff" v-model="extraForm.time_diff" inputmode="numeric" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="extra-times">签名可用次数</Label>
                                <Input id="extra-times" v-model="extraForm.sign_use_times" inputmode="numeric" />
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="cc-des">备注</Label>
                        <textarea
                            id="cc-des"
                            v-model="ccForm.des"
                            class="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </div>
                    <Alert v-if="formError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertDescription>{{ formError }}</AlertDescription>
                    </Alert>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="ccDialogOpen = false"
                        >
                            取消
                        </Button>
                        <Button type="submit" :disabled="saving">
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
            :description="`确认删除${deleteKind === 'acl' ? 'ACL' : ccKindLabel(deleteKind as CcKind)}「${deleteTarget ? recordName(deleteTarget) : ''}」？删除后不可恢复。`"
            :loading="deleting"
            :error="deleteError"
            @confirm="confirmDelete"
            @cancel="deleteOpen = false"
        />
    </div>
</template>
