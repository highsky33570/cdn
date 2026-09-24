<script setup lang="ts">
import {
    AlertCircle,
    Plus,
    Save,
    Search,
    ShieldCheck,
    UnlockKeyhole,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import UserAclWorkspace from '@/components/console/UserAclWorkspace.vue';
import UserCcWorkspace from '@/components/console/UserCcWorkspace.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
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
import { cdnflyJsonObject } from '@/lib/cdnflyResponse';
import type { MatcherCondition } from '@/lib/cdnflySecurity';
import { buildCcMatcher, parseCcMatcher } from '@/lib/cdnflySecurity';
import {
    formatDate,
    getErrorMessage,
    jsonText,
    numberValue,
    recordId,
    textValue,
} from '@/lib/cdnRecord';
import {
    createUserAcl,
    createUserCcFilter,
    createUserCcMatcher,
    createUserCcRule,
    extractCdnflyRows,
    extractCdnflyTotal,
    getUserBlackIpCount,
    listUserBlackIps,
    listUserCcFilters,
    listUserCcMatchers,
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

type RuleEntry = {
    raw?: Record<string, unknown>;
    mode?: string;
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
    { value: 'ip_range', label: '在 IP 段' },
    { value: '!ip_range', label: '不在 IP 段' },
    { value: 'prefix', label: '前缀匹配' },
    { value: 'suffix', label: '后缀匹配' },
    { value: 'regex', label: '正则匹配' },
    { value: '!regex', label: '正则不匹配' },
    { value: '>', label: '大于' },
    { value: 'exists', label: '存在' },
    { value: '!exists', label: '不存在' },
] as const;

const RULE_ACTIONS = [
    { value: 'block', label: '拦截并加黑名单' },
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
    // 规则组 first: it is the resource CDNfly seeds with system protection
    // templates (关闭/宽松/JS验证/5秒盾/验证码…), so it is where users actually
    // find data. 匹配器/过滤器 are the building blocks and start empty.
    { key: 'rule', label: '规则组' },
    { key: 'matcher', label: '匹配器' },
    { key: 'filter', label: '过滤器' },
];

const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const formError = ref('');

const aclWorkspace = ref<InstanceType<typeof UserAclWorkspace> | null>(null);
const aclDialogOpen = ref(false);
const editingAcl = ref<CdnflyRecord | null>(null);
const aclForm = reactive({
    name: '',
    data: '[]',
    des: '',
    enable: '1',
});

const activeCcKind = ref<CcKind>('rule');
const ccWorkspace = ref<InstanceType<typeof UserCcWorkspace> | null>(null);
const ccDialogOpen = ref(false);
const editingCc = ref<CdnflyRecord | null>(null);
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
        return 'CC 规则';
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

    return '维护访问控制规则。';
});
const ccDialogTitle = computed(() =>
    editingCc.value
        ? `编辑${ccKindLabel(activeCcKind.value)}`
        : `新增${ccKindLabel(activeCcKind.value)}`,
);
const hasBlackIpPreviousPage = computed(() => blackIpPage.value > 1);
const hasBlackIpNextPage = computed(
    () =>
        blackIpPage.value * Number(blackIpFilters.per_page) <
        blackIpTotal.value,
);
onMounted(() => {
    void reloadCurrentView();
});

async function reloadCurrentView(): Promise<void> {
    if (props.view === 'cc' || props.view === 'acls') {
        return;
    }

    if (props.view === 'blackip') {
        await loadHistoryBlackIps();

        return;
    }
}

async function loadAclRows(): Promise<void> {
    await aclWorkspace.value?.refresh();
}

function openAclCreateDialog(): void {
    editingAcl.value = null;
    aclForm.name = '';
    aclForm.data = '[]';
    aclForm.des = '';
    aclForm.enable = '1';
    formError.value = '';
    aclDialogOpen.value = true;
}

function openAclEditDialog(record: CdnflyRecord): void {
    editingAcl.value = record;
    aclForm.name = textValue(record.name);
    aclForm.data = jsonText(record.data, '[]');
    aclForm.des = textValue(record.des ?? record.remark);
    aclForm.enable = record.enable === 0 || record.enable === false ? '0' : '1';
    formError.value = '';
    aclDialogOpen.value = true;
}

async function submitAcl(): Promise<void> {
    const name = aclForm.name.trim();

    if (name === '') {
        formError.value = 'ACL 名称不能为空';

        return;
    }

    let ruleData: unknown[];

    try {
        const parsed = JSON.parse(aclForm.data);

        if (!Array.isArray(parsed)) {
            throw new Error('规则必须是 JSON 数组');
        }

        ruleData = parsed;
    } catch (error) {
        formError.value = getErrorMessage(error);

        return;
    }

    const payload: CdnAclPayload = {
        name,
        data: ruleData,
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
            await createUserAcl(payload);
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

async function loadCcRows(): Promise<void> {
    await ccWorkspace.value?.refresh();
}

function createCc(kind: CcKind): void {
    activeCcKind.value = kind;
    openCcCreateDialog();
}

function manageCc(kind: CcKind, record: CdnflyRecord): void {
    activeCcKind.value = kind;
    openCcEditDialog(record);
}

function buildMatcherData(): Record<string, unknown>[] {
    return buildCcMatcher(matcherConditions.value);
}

function buildExtra(): Record<string, unknown> {
    if (ccForm.type !== 'url_auth') {
        return cdnflyJsonObject(editingCc.value?.extra);
    }

    const obj: Record<string, unknown> = {
        ...cdnflyJsonObject(editingCc.value?.extra),
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
            listUserCcMatchers({ limit: 200, internal_self: 1 }),
            listUserCcFilters({ limit: 200, internal_self: 1 }),
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
        // non-critical
    } finally {
        loadingRuleOptions.value = false;
    }
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
        action: 'block',
        matcher: '',
        filter1: '',
        filter2: '__none__',
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
        matcherConditions.value = parseCcMatcher(rawData);
        ruleEntries.value = [];
    } else if (activeCcKind.value === 'rule') {
        let dataArr: unknown[];

        if (typeof rawData === 'string') {
            try {
                dataArr = JSON.parse(rawData);
            } catch {
                dataArr = [];
            }
        } else {
            dataArr = Array.isArray(rawData) ? rawData : [];
        }

        ruleEntries.value = dataArr.map((entry: any) => ({
            raw: entry,
            mode: String(entry.mode ?? 'continue'),
            action: String(entry.action ?? 'block'),
            matcher: String(entry.matcher ?? ''),
            filter1: String(entry.filter1 ?? ''),
            filter2: String(entry.filter2 ?? '__none__'),
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

async function loadHistoryBlackIps(
    targetPage = historyBlackIpPage.value,
): Promise<void> {
    historyBlackIpLoading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(historyBlackIpFilters.per_page),
        };

        if (historyBlackIpFilters.ip.trim()) {
            params.ip = historyBlackIpFilters.ip.trim();
        }

        if (historyBlackIpFilters.site_id.trim()) {
            params.site_id = historyBlackIpFilters.site_id.trim();
        }

        if (historyBlackIpFilters.start.trim()) {
            params.start = historyBlackIpFilters.start.trim();
        }

        if (historyBlackIpFilters.end.trim()) {
            params.end = historyBlackIpFilters.end.trim();
        }

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

async function switchBlackIpTab(
    tab: 'current' | 'stats' | 'history',
): Promise<void> {
    blackIpTab.value = tab;

    if (tab === 'current' && blackIpRows.value.length === 0) {
        void loadBlackIps();
    }

    if (tab === 'stats' && blackIpCountRows.value.length === 0) {
        void loadBlackIpCount();
    }

    if (tab === 'history' && historyBlackIpRows.value.length === 0) {
        void loadHistoryBlackIps();
    }
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

    const data: Record<string, string | number> = {
        site_id: Number(siteId),
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
        toast.success(
            ip.trim()
                ? `已提交 ${ip.trim()} 解锁任务`
                : `已提交站点 ${siteId.trim()} 全部黑名单解锁任务`,
        );
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

        const missingMatcher = ruleEntries.value.findIndex(
            (e) => !e.matcher || !e.filter1,
        );

        if (missingMatcher !== -1) {
            throw new Error(
                `第 ${missingMatcher + 1} 条规则未选择匹配器或第一过滤器`,
            );
        }

        return {
            name: ccForm.name.trim(),
            sort: optionalNumber(ccForm.sort) ?? 100,
            data: ruleEntries.value.map((e) => ({
                ...e.raw,
                mode: e.mode ?? 'continue',
                action: e.action,
                matcher: Number(e.matcher),
                filter1: Number(e.filter1),
                filter2:
                    e.filter2 && e.filter2 !== '__none__'
                        ? Number(e.filter2)
                        : null,
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

function ccKindLabel(kind: CcKind): string {
    return ccKinds.find((item) => item.key === kind)?.label ?? '资源';
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
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            v-if="props.view === 'blackip'"
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

        <UserAclWorkspace
            v-if="props.view === 'acls'"
            ref="aclWorkspace"
            @create="openAclCreateDialog"
            @manage="openAclEditDialog"
        />

        <UserCcWorkspace
            v-else-if="props.view === 'cc'"
            ref="ccWorkspace"
            @create="createCc"
            @manage="manageCc"
        />

        <div v-else class="space-y-4">
            <!-- 三个 Tab -->
            <div class="flex gap-1 border-b">
                <button
                    v-for="tab in [
                        { key: 'current', label: '当前拉黑' },
                        { key: 'stats', label: '拉黑统计' },
                        { key: 'history', label: '历史拉黑' },
                    ]"
                    :key="tab.key"
                    type="button"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                    :class="
                        blackIpTab === tab.key
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="
                        switchBlackIpTab(
                            tab.key as 'current' | 'stats' | 'history',
                        )
                    "
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- ── 历史拉黑 ── -->
            <template v-if="blackIpTab === 'history'">
                <!-- 筛选栏 -->
                <div class="flex flex-wrap items-center gap-2">
                    <div
                        class="flex h-9 items-center gap-1.5 rounded-md border px-3"
                    >
                        <span
                            class="text-xs whitespace-nowrap text-muted-foreground"
                            >IP地址</span
                        >
                        <input
                            v-model="historyBlackIpFilters.ip"
                            class="w-36 bg-transparent text-sm outline-none placeholder:text-muted-foreground/60"
                            placeholder="请输入IP地址"
                            @keydown.enter="loadHistoryBlackIps(1)"
                        />
                    </div>
                    <div
                        class="flex h-9 items-center gap-1.5 rounded-md border px-3"
                    >
                        <span
                            class="text-xs whitespace-nowrap text-muted-foreground"
                            >网站ID</span
                        >
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
                        @click="
                            () => {
                                historyBlackIpFilters.ip = '';
                                historyBlackIpFilters.site_id = '';
                                historyBlackIpFilters.start = '';
                                historyBlackIpFilters.end = '';
                                void loadHistoryBlackIps(1);
                            }
                        "
                    >
                        清除
                    </button>
                    <Button
                        class="ml-auto h-9"
                        :disabled="historyBlackIpLoading"
                        @click="loadHistoryBlackIps(1)"
                    >
                        <Spinner
                            v-if="historyBlackIpLoading"
                            data-icon="inline-start"
                        />
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
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            网站ID
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            域名
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            IP
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            位置
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            过滤器
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            拉黑时间
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            手动解锁?
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="historyBlackIpLoading">
                                        <td
                                            colspan="7"
                                            class="px-4 py-12 text-center"
                                        >
                                            <Spinner class="mx-auto" />
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(
                                            row, index
                                        ) in historyBlackIpRows"
                                        :key="index"
                                        class="border-b transition-colors last:border-0 hover:bg-muted/20"
                                    >
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{ textValue(row.site_id) || '-' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{
                                                textValue(row.domain) ||
                                                textValue(row.host) ||
                                                '-'
                                            }}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs">
                                            {{ textValue(row.ip) || '-' }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{
                                                textValue(row.position) ||
                                                textValue(row.country) ||
                                                '-'
                                            }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium"
                                            >
                                                {{
                                                    textValue(row.name) ||
                                                    textValue(row.filter) ||
                                                    '-'
                                                }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-4 py-3 text-xs text-muted-foreground tabular-nums"
                                        >
                                            {{
                                                formatDate(
                                                    row.create_time ??
                                                        row.created_at ??
                                                        row.time,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{
                                                row.manual_unlock === true ||
                                                row.manual_unlock === 1
                                                    ? '是'
                                                    : '否'
                                            }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            !historyBlackIpLoading &&
                                            historyBlackIpRows.length === 0
                                        "
                                    >
                                        <td
                                            colspan="7"
                                            class="px-4 py-16 text-center text-muted-foreground"
                                        >
                                            暂无历史拉黑记录
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- 分页 -->
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>共 {{ historyBlackIpTotal }} 条</span>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="
                                historyBlackIpPage <= 1 || historyBlackIpLoading
                            "
                            @click="loadHistoryBlackIps(historyBlackIpPage - 1)"
                            >上一页</Button
                        >
                        <span>第 {{ historyBlackIpPage }} 页</span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="
                                historyBlackIpPage *
                                    Number(historyBlackIpFilters.per_page) >=
                                    historyBlackIpTotal || historyBlackIpLoading
                            "
                            @click="loadHistoryBlackIps(historyBlackIpPage + 1)"
                            >下一页</Button
                        >
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
                                        <th
                                            class="w-16 px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            排行
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            网站ID
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            黑名单数量
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="blackIpCountLoading">
                                        <td
                                            colspan="3"
                                            class="px-4 py-12 text-center"
                                        >
                                            <Spinner class="mx-auto" />
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(row, index) in blackIpCountRows"
                                        :key="index"
                                        class="border-b last:border-0 hover:bg-muted/20"
                                    >
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{ index + 1 }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ textValue(row.site_id) || '-' }}
                                        </td>
                                        <td class="px-4 py-3 tabular-nums">
                                            {{
                                                textValue(row.count) ||
                                                textValue(row.total) ||
                                                '-'
                                            }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            !blackIpCountLoading &&
                                            blackIpCountRows.length === 0
                                        "
                                    >
                                        <td
                                            colspan="3"
                                            class="px-4 py-16 text-center text-muted-foreground"
                                        >
                                            暂无数据
                                        </td>
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
                    <Button
                        size="sm"
                        variant="outline"
                        @click="
                            unlockBlackIp(
                                unlockForm.site_id,
                                unlockForm.ip,
                                'batch',
                            )
                        "
                    >
                        <UnlockKeyhole data-icon="inline-start" />解锁IP
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        @click="unlockBlackIp(unlockForm.site_id, '', 'site')"
                    >
                        解锁网站
                    </Button>
                </div>
                <!-- 筛选栏 -->
                <div class="flex flex-wrap items-center gap-2">
                    <div
                        class="flex h-9 items-center gap-1.5 rounded-md border px-3"
                    >
                        <span class="text-xs text-muted-foreground"
                            >IP地址</span
                        >
                        <input
                            v-model="blackIpFilters.ip"
                            class="w-36 bg-transparent text-sm outline-none placeholder:text-muted-foreground/60"
                            placeholder="请输入IP地址"
                            @keydown.enter="loadBlackIps(1)"
                        />
                    </div>
                    <div
                        class="flex h-9 items-center gap-1.5 rounded-md border px-3"
                    >
                        <span class="text-xs text-muted-foreground"
                            >网站ID</span
                        >
                        <input
                            v-model="blackIpFilters.site_id"
                            class="w-28 bg-transparent text-sm outline-none placeholder:text-muted-foreground/60"
                            placeholder="请输入网站ID"
                            @keydown.enter="loadBlackIps(1)"
                        />
                    </div>
                    <button
                        type="button"
                        class="text-sm text-muted-foreground hover:text-foreground"
                        @click="
                            () => {
                                blackIpFilters.ip = '';
                                blackIpFilters.site_id = '';
                                void loadBlackIps(1);
                            }
                        "
                    >
                        清除
                    </button>
                    <Button
                        class="ml-auto h-9"
                        :disabled="loading"
                        @click="loadBlackIps(1)"
                    >
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
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            网站ID
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            域名
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            IP
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            位置
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            过滤器
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            拉黑时间
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                                        >
                                            解锁时间
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right font-medium text-muted-foreground"
                                        >
                                            操作
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="loading">
                                        <td
                                            colspan="8"
                                            class="px-4 py-12 text-center"
                                        >
                                            <Spinner class="mx-auto" />
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="row in blackIpRows"
                                        :key="`${textValue(row.site_id)}-${textValue(row.ip)}`"
                                        class="border-b transition-colors last:border-0 hover:bg-muted/20"
                                    >
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{ textValue(row.site_id) || '-' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ textValue(row.domain) || '-' }}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs">
                                            {{ textValue(row.ip) || '-' }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{ textValue(row.position) || '-' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium"
                                            >
                                                {{ textValue(row.name) || '-' }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-4 py-3 text-xs text-muted-foreground tabular-nums"
                                        >
                                            {{
                                                formatDate(
                                                    row.create_time ?? row.time,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-xs text-muted-foreground tabular-nums"
                                        >
                                            {{ textValue(row.exp) || '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                :disabled="
                                                    unlocking &&
                                                    unlockingId ===
                                                        `${textValue(row.site_id)}-${textValue(row.ip)}`
                                                "
                                                @click="
                                                    unlockBlackIp(
                                                        textValue(row.site_id),
                                                        textValue(row.ip),
                                                        `${textValue(row.site_id)}-${textValue(row.ip)}`,
                                                    )
                                                "
                                            >
                                                <Spinner
                                                    v-if="
                                                        unlocking &&
                                                        unlockingId ===
                                                            `${textValue(row.site_id)}-${textValue(row.ip)}`
                                                    "
                                                    data-icon="inline-start"
                                                />
                                                <UnlockKeyhole
                                                    v-else
                                                    data-icon="inline-start"
                                                />
                                                解锁
                                            </Button>
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            !loading && blackIpRows.length === 0
                                        "
                                    >
                                        <td
                                            colspan="8"
                                            class="px-4 py-16 text-center text-muted-foreground"
                                        >
                                            暂无黑名单
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>共 {{ blackIpTotal }} 条</span>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!hasBlackIpPreviousPage || loading"
                            @click="prevBlackIpPage"
                            >上一页</Button
                        >
                        <span>第 {{ blackIpPage }} 页</span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!hasBlackIpNextPage || loading"
                            @click="nextBlackIpPage"
                            >下一页</Button
                        >
                    </div>
                </div>
            </template>
        </div>

        <Dialog v-model:open="aclDialogOpen">
            <DialogScrollContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{
                        editingAcl ? '编辑 ACL 规则库' : '新增 ACL 规则库'
                    }}</DialogTitle>
                    <DialogDescription
                        >规则数组支持 action、action_config 和
                        matcher_groups，保存时保留完整规则配置。</DialogDescription
                    >
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitAcl">
                    <Alert v-if="formError" variant="destructive"
                        ><AlertDescription>{{
                            formError
                        }}</AlertDescription></Alert
                    >
                    <div class="grid gap-2">
                        <Label for="acl-name">名称</Label
                        ><Input id="acl-name" v-model="aclForm.name" required />
                    </div>
                    <div class="grid gap-2">
                        <Label>状态</Label
                        ><Select v-model="aclForm.enable"
                            ><SelectTrigger><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem value="1">启用</SelectItem
                                ><SelectItem value="0"
                                    >禁用</SelectItem
                                ></SelectContent
                            ></Select
                        >
                    </div>
                    <div class="grid gap-2">
                        <Label for="waf-data">规则 (JSON 数组)</Label
                        ><textarea
                            id="waf-data"
                            v-model="aclForm.data"
                            rows="16"
                            class="w-full rounded-md border bg-background p-3 font-mono text-xs"
                            spellcheck="false"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="acl-des">备注</Label
                        ><Input id="acl-des" v-model="aclForm.des" />
                    </div>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            @click="aclDialogOpen = false"
                            >取消</Button
                        ><Button type="submit" :disabled="saving"
                            ><Spinner v-if="saving" />保存</Button
                        ></DialogFooter
                    >
                </form>
            </DialogScrollContent>
        </Dialog>

        <Dialog v-model:open="ccDialogOpen">
            <DialogScrollContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{ ccDialogTitle }}</DialogTitle>
                    <DialogDescription>
                        规则组和匹配器使用有序规则数组。
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
                                        >
                                            {{ mk.label }}
                                        </SelectItem>
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
                                        >
                                            {{ op.label }}
                                        </SelectItem>
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
                            >
                                <X class="size-4" />
                            </Button>
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
                                            >
                                                {{ a.label }}
                                            </SelectItem>
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
                                            >
                                                #{{ m.id }} {{ m.name }}
                                            </SelectItem>
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
                                            <SelectItem
                                                v-for="f in filterOptions"
                                                :key="f.id"
                                                :value="f.id"
                                            >
                                                #{{ f.id }} {{ f.name }}
                                            </SelectItem>
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
                                            <SelectItem value="__none__"
                                                >(无)</SelectItem
                                            >
                                            <SelectItem
                                                v-for="f in filterOptions"
                                                :key="f.id"
                                                :value="f.id"
                                            >
                                                #{{ f.id }} {{ f.name }}
                                            </SelectItem>
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
                                    <Switch
                                        :checked="entry.state"
                                        @update:checked="entry.state = $event"
                                    />
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-9 self-end"
                                @click="removeRuleEntry(index)"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                    </div>
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
    </div>
</template>
