<script setup lang="ts">
import {
    AlertCircle,
    Copy,
    Eye,
    EyeOff,
    KeyRound,
    RefreshCw,
    Save,
    Search,
    ShieldCheck,
    Trash2,
    UserRound,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    formatDate,
    getErrorMessage,
    jsonText,
    textValue,
} from '@/lib/cdnRecord';
import {
    createUserApiKey,
    deleteUserApiKey,
    extractCdnflyRows,
    extractCdnflyTotal,
    getUserApiKey,
    getUserCertify,
    getUserOverview,
    listUserLoginLogs,
    submitUserCertify,
    updateUserApiKey,
} from '@/lib/cdnUserApi';
import type { CdnflyRecord } from '@/lib/cdnUserApi';

type AccountView = 'profile' | 'certification' | 'api-key' | 'login-logs';

const props = defineProps<{
    view: AccountView;
}>();

const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const overview = ref<CdnflyRecord | null>(null);
const apiKey = ref<CdnflyRecord | null>(null);
const certifyStatus = ref<CdnflyRecord | null>(null);
const loginLogs = ref<CdnflyRecord[]>([]);
const page = ref(1);
const total = ref(0);

const apiForm = reactive({
    ip: '',
});

const logFilters = reactive({
    ip: '',
    success: 'all',
    start: '',
    end: '',
    per_page: '20',
});

const certificationForm = reactive({
    cert_name: '',
    cert_no: '',
});

const title = computed(() => {
    if (props.view === 'certification') {
        return '实名认证';
    }

    if (props.view === 'api-key') {
        return 'API Key';
    }

    if (props.view === 'login-logs') {
        return '登录日志';
    }

    return '账户资料';
});
const description = computed(() => {
    if (props.view === 'certification') {
        return '查询和提交实名认证信息。';
    }

    if (props.view === 'api-key') {
        return '管理 API Key 和 IP 白名单。';
    }

    if (props.view === 'login-logs') {
        return '查询登录日志，支持按 IP、状态和时间范围筛选。';
    }

    return '集中展示账户、套餐和用量概要。';
});
const icon = computed(() => {
    if (props.view === 'certification') {
        return ShieldCheck;
    }

    if (props.view === 'api-key') {
        return KeyRound;
    }

    return UserRound;
});
// profile and api-key now render designed layouts (profileRows / apiKeyPair
// below); objectRows survives only for the certification view.
const certifyRows = computed(() => objectRows(certifyStatus.value));
const hasPreviousPage = computed(() => page.value > 1);
const hasNextPage = computed(
    () => page.value * Number(logFilters.per_page) < total.value,
);

onMounted(() => {
    void loadCurrent(1);
});

async function loadCurrent(targetPage = page.value): Promise<void> {
    if (props.view === 'api-key') {
        await loadApiKey();

        return;
    }

    if (props.view === 'certification') {
        await loadCertification();

        return;
    }

    if (props.view === 'login-logs') {
        await loadLoginLogs(targetPage);

        return;
    }

    await loadOverview();
}

async function loadOverview(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        overview.value = await getUserOverview();
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function loadCertification(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        certifyStatus.value = await getUserCertify();
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function loadApiKey(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        apiKey.value = await getUserApiKey();
        apiForm.ip = textValue(apiKey.value.api_ip ?? apiKey.value.ip);
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function loadLoginLogs(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(logFilters.per_page),
        };

        setOptional(params, 'ip', logFilters.ip);
        setOptional(params, 'start', logFilters.start);
        setOptional(params, 'end', logFilters.end);

        if (logFilters.success !== 'all') {
            params.success = logFilters.success;
        }

        const result = await listUserLoginLogs(params);
        const nextRows = extractCdnflyRows(result);

        loginLogs.value = nextRows;
        total.value = extractCdnflyTotal(result, nextRows.length);
        page.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function submitCertification(): Promise<void> {
    if (certificationForm.cert_name.trim() === '') {
        errorMessage.value = 'cert_name 不能为空';

        return;
    }

    if (certificationForm.cert_no.trim() === '') {
        errorMessage.value = 'cert_no 不能为空';

        return;
    }

    saving.value = true;
    errorMessage.value = '';

    try {
        certifyStatus.value = await submitUserCertify({
            cert_name: certificationForm.cert_name.trim(),
            cert_no: certificationForm.cert_no.trim(),
        });
        toast.success('实名认证请求已提交');
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

async function createKey(): Promise<void> {
    saving.value = true;
    errorMessage.value = '';

    try {
        apiKey.value = await createUserApiKey();
        toast.success('API Key 创建请求已提交');
        apiForm.ip = textValue(apiKey.value.api_ip ?? apiKey.value.ip);
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

async function saveKeyIp(): Promise<void> {
    saving.value = true;
    errorMessage.value = '';

    try {
        apiKey.value = await updateUserApiKey({
            ip: apiForm.ip.trim() === '' ? null : apiForm.ip.trim(),
        });
        toast.success('API Key 白名单更新请求已提交');
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

async function removeKey(): Promise<void> {
    saving.value = true;
    errorMessage.value = '';

    try {
        await deleteUserApiKey();
        apiKey.value = null;
        toast.success('API Key 删除请求已提交');
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

function submitLogs(): void {
    page.value = 1;
    void loadLoginLogs(1);
}

function prevPage(): void {
    if (hasPreviousPage.value) {
        void loadLoginLogs(page.value - 1);
    }
}

function nextPage(): void {
    if (hasNextPage.value) {
        void loadLoginLogs(page.value + 1);
    }
}

function setOptional(
    params: Record<string, string | number>,
    key: string,
    value: string,
): void {
    if (value.trim() !== '') {
        params[key] = value.trim();
    }
}

function objectRows(record: CdnflyRecord | null): Array<{
    key: string;
    value: string;
}> {
    if (!record) {
        return [];
    }

    return Object.entries(record)
        .filter(([, value]) => value !== null && value !== undefined)
        .slice(0, 24)
        .map(([key, value]) => ({
            key,
            value: maskSensitive(key, value),
        }));
}

/**
 * Human labels for the raw CDNfly field names.
 *
 * The overview and api-key views previously rendered Object.entries() straight
 * to a two-column table, so users saw `auth2_enable` and `create_at2` — which
 * reads as a JSON dump rather than an account page.
 */
const FIELD_LABELS: Record<string, string> = {
    name: '用户名',
    email: '邮箱',
    phone: '手机号',
    qq: 'QQ',
    balance: '账户余额',
    id: 'CDNfly 用户 ID',
    create_at2: '注册时间',
    create_at: '注册时间',
    des: '备注',
    cert_name: '实名姓名',
    cert_no: '证件号码',
    company_name: '企业名称',
    company_credit_code: '统一社会信用代码',
    white_ip: 'IP 白名单',
    user_group: '用户组',
};

/** Fields that are internal plumbing, not worth showing on a profile page. */
const HIDDEN_FIELDS = new Set([
    'type',
    'enable',
    'freeze',
    'cert_verified',
    'company_verified',
    'auth2_verified',
    'auth2_enable',
    'auth2_end_at',
    'auth2_expire_action',
    'login_captcha',
    'cert_id',
]);

function fieldLabel(key: string): string {
    return FIELD_LABELS[key] ?? key;
}

/** Detail rows for the profile page, labelled and with the noise removed. */
const profileRows = computed(() => {
    const record = overview.value;

    if (!record) {
        return [] as Array<{ key: string; label: string; value: string }>;
    }

    return Object.entries(record)
        .filter(
            ([key, value]) =>
                !HIDDEN_FIELDS.has(key) &&
                value !== null &&
                value !== undefined &&
                value !== '',
        )
        .map(([key, value]) => ({
            key,
            label: fieldLabel(key),
            value: key.includes('create_at')
                ? formatDate(value)
                : maskSensitive(key, value),
        }));
});

/** Yes/no state badges derived from the flag fields hidden above. */
const profileBadges = computed(() => {
    const record = overview.value;

    if (!record) {
        return [] as Array<{
            label: string;
            ok: boolean;
            okText: string;
            noText: string;
        }>;
    }

    const truthy = (v: unknown) => v === 1 || v === '1' || v === true;

    return [
        {
            label: '账户状态',
            ok: truthy(record.enable),
            okText: '正常',
            noText: '已停用',
        },
        {
            label: '实名认证',
            ok: truthy(record.cert_verified),
            okText: '已认证',
            noText: '未认证',
        },
        {
            label: '企业认证',
            ok: truthy(record.company_verified),
            okText: '已认证',
            noText: '未认证',
        },
        {
            label: '两步验证',
            ok: truthy(record.auth2_verified),
            okText: '已开启',
            noText: '未开启',
        },
    ];
});

const accountBalance = computed(() => {
    const raw = overview.value?.balance;

    return raw === null || raw === undefined ? '-' : String(raw);
});

/**
 * Pick the first field that actually carries a value.
 *
 * CDNfly's field naming is not consistent between endpoints (the IP whitelist
 * arrives as api_ip on one payload and ip on another), so a designed view must
 * probe several names rather than assume one.
 */
function pickField(record: CdnflyRecord | null, keys: string[]): string {
    if (!record) {
        return '';
    }

    for (const key of keys) {
        const value = textValue(record[key]);

        if (value !== '' && value !== '-') {
            return value;
        }
    }

    return '';
}

/** The api-key page needs the credential pair, not a field dump. */
const apiKeyPair = computed(() => {
    const record = apiKey.value;

    return {
        key: pickField(record, ['api_key', 'apiKey', 'key', 'access_key']),
        secret: pickField(record, [
            'api_secret',
            'apiSecret',
            'secret',
            'access_secret',
        ]),
        createdAt: record
            ? formatDate(record.create_at2 ?? record.create_at)
            : '-',
        whiteIp: pickField(record, ['white_ip', 'api_ip', 'ip', 'whiteIp']),
    };
});

/**
 * Anything the designed layout above did not account for.
 *
 * Without this the redesign could show *less* than the raw table it replaced:
 * if CDNfly renames a field, the credential block goes blank and the data
 * silently disappears. These rows guarantee that never happens.
 */
const RENDERED_API_KEY_FIELDS = new Set([
    'api_key',
    'apiKey',
    'key',
    'access_key',
    'api_secret',
    'apiSecret',
    'secret',
    'access_secret',
    'create_at',
    'create_at2',
    'white_ip',
    'api_ip',
    'ip',
    'whiteIp',
]);

const apiKeyExtraRows = computed(() =>
    objectRows(apiKey.value).filter(
        (row) => !RENDERED_API_KEY_FIELDS.has(row.key),
    ),
);

const secretRevealed = ref(false);

function maskValue(value: string): string {
    if (value === '') {
        return '-';
    }

    return value.length <= 8
        ? '******'
        : `${value.slice(0, 4)}${'*'.repeat(12)}${value.slice(-4)}`;
}

async function copyValue(value: string, what: string): Promise<void> {
    if (value === '') {
        return;
    }

    try {
        await navigator.clipboard.writeText(value);
        toast.success(`${what}已复制`);
    } catch {
        toast.error('复制失败，请手动选择文本');
    }
}

function maskSensitive(key: string, value: unknown): string {
    const text = textValue(value) || jsonText(value, '-');
    const normalized = key.toLowerCase();

    if (
        !normalized.includes('secret') &&
        !normalized.includes('token') &&
        !normalized.includes('key')
    ) {
        return text;
    }

    if (text.length <= 8) {
        return text === '-' ? '-' : '******';
    }

    return `${text.slice(0, 4)}******${text.slice(-4)}`;
}

function loginSuccess(record: CdnflyRecord): string {
    const value = record.success ?? record.status;

    if (value === 1 || value === true || value === '1') {
        return '成功';
    }

    if (value === 0 || value === false || value === '0') {
        return '失败';
    }

    return textValue(value) || '-';
}
</script>

<template>
    <div class="space-y-6">
        <ConsolePageHeader
            eyebrow="用户端 / 账户"
            :title="title"
            :description="description"
            :icon="icon"
            :show-api-badge="false"
        />

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>
        <Card v-if="props.view === 'api-key'">
            <CardHeader
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <CardTitle class="text-base">API Key 管理</CardTitle>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        :disabled="loading"
                        @click="loadApiKey"
                    >
                        <RefreshCw data-icon="inline-start" />
                        刷新
                    </Button>
                    <Button :disabled="saving" @click="createKey">
                        <Spinner v-if="saving" data-icon="inline-start" />
                        <KeyRound v-else data-icon="inline-start" />
                        创建 Key
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="space-y-5">
                <form
                    class="grid gap-3 lg:grid-cols-[1fr_auto_auto]"
                    @submit.prevent="saveKeyIp"
                >
                    <div class="grid gap-2">
                        <Label for="api-key-ip">IP 白名单</Label>
                        <Input
                            id="api-key-ip"
                            v-model="apiForm.ip"
                            placeholder="多个 IP 以逗号分隔；留空则由 CDNfly 默认处理"
                        />
                    </div>
                    <div class="flex items-end">
                        <Button type="submit" :disabled="saving">
                            <Spinner v-if="saving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            保存
                        </Button>
                    </div>
                    <div class="flex items-end">
                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="saving"
                            @click="removeKey"
                        >
                            <Trash2 data-icon="inline-start" />
                            删除
                        </Button>
                    </div>
                </form>
                <div v-if="loading" class="py-16">
                    <Spinner class="mx-auto" />
                </div>

                <div v-else-if="apiKey !== null" class="space-y-4">
                    <!-- API Key: safe to display, needed for every API call -->
                    <div
                        v-if="apiKeyPair.key !== ''"
                        class="rounded-lg border p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <Label class="text-xs text-muted-foreground"
                                >API Key</Label
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="copyValue(apiKeyPair.key, 'API Key')"
                            >
                                <Copy data-icon="inline-start" />
                                复制
                            </Button>
                        </div>
                        <code class="mt-1 block font-mono text-sm break-all">
                            {{ apiKeyPair.key }}
                        </code>
                    </div>

                    <!-- API Secret: masked by default, revealed on request -->
                    <div
                        v-if="apiKeyPair.secret !== ''"
                        class="rounded-lg border p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <Label class="text-xs text-muted-foreground">
                                API Secret
                            </Label>
                            <div class="flex gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="secretRevealed = !secretRevealed"
                                >
                                    <component
                                        :is="secretRevealed ? EyeOff : Eye"
                                        data-icon="inline-start"
                                    />
                                    {{ secretRevealed ? '隐藏' : '显示' }}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="
                                        copyValue(
                                            apiKeyPair.secret,
                                            'API Secret',
                                        )
                                    "
                                >
                                    <Copy data-icon="inline-start" />
                                    复制
                                </Button>
                            </div>
                        </div>
                        <code class="mt-1 block font-mono text-sm break-all">
                            {{
                                secretRevealed
                                    ? apiKeyPair.secret
                                    : maskValue(apiKeyPair.secret)
                            }}
                        </code>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Secret 等同于账户密码，请勿分享或提交到代码仓库。
                        </p>
                    </div>

                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                创建时间
                            </dt>
                            <dd class="text-sm">{{ apiKeyPair.createdAt }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                IP 白名单
                            </dt>
                            <dd class="text-sm break-all">
                                {{ apiKeyPair.whiteIp || '未限制' }}
                            </dd>
                        </div>
                        <!-- Any field the layout above did not account for, so a
                             renamed key upstream can never hide data. -->
                        <div v-for="row in apiKeyExtraRows" :key="row.key">
                            <dt class="text-xs text-muted-foreground">
                                {{ row.key }}
                            </dt>
                            <dd class="text-sm break-all">{{ row.value }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-else class="py-16 text-center">
                    <p class="text-sm text-muted-foreground">
                        尚未创建 API Key
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        点击右上角「创建 Key」生成一对凭证。
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card v-else-if="props.view === 'login-logs'">
            <CardHeader class="space-y-4">
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <CardTitle class="text-base">登录日志</CardTitle>
                    <div class="text-sm text-muted-foreground">
                        {{ total === 0 ? '暂无日志' : `${total} 条日志` }}
                    </div>
                </div>
                <form
                    class="grid gap-3 xl:grid-cols-[1fr_140px_1fr_1fr_120px_auto]"
                    @submit.prevent="submitLogs"
                >
                    <Input v-model="logFilters.ip" placeholder="IP" />
                    <Select v-model="logFilters.success">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">全部结果</SelectItem>
                                <SelectItem value="1">成功</SelectItem>
                                <SelectItem value="0">失败</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Input v-model="logFilters.start" placeholder="开始时间" />
                    <Input v-model="logFilters.end" placeholder="结束时间" />
                    <Select v-model="logFilters.per_page">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
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
                </form>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto border-y">
                    <table class="w-full min-w-[820px] table-fixed text-sm">
                        <colgroup>
                            <col style="width: 22%" />
                            <col style="width: 18%" />
                            <col style="width: 16%" />
                            <col style="width: 24%" />
                            <col style="width: 20%" />
                        </colgroup>
                        <thead class="border-b text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">
                                    IP
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    结果
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    账号
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    User-Agent
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    时间
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td class="px-6 py-16 text-center" colspan="5">
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <tr
                                v-for="record in loginLogs"
                                :key="`${textValue(record.ip)}-${textValue(
                                    record.create_at2,
                                )}`"
                                class="border-b"
                            >
                                <td class="px-4 py-3">
                                    {{ textValue(record.ip) || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge variant="secondary">
                                        {{ loginSuccess(record) }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                        textValue(
                                            record.account ?? record.username,
                                        ) || '-'
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="truncate">
                                        {{
                                            textValue(
                                                record.ua ?? record.user_agent,
                                            ) || '-'
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{
                                        formatDate(
                                            record.create_at2 ??
                                                record.created_at,
                                        )
                                    }}
                                </td>
                            </tr>
                            <tr v-if="!loading && loginLogs.length === 0">
                                <td
                                    class="px-6 py-16 text-center text-muted-foreground"
                                    colspan="5"
                                >
                                    暂无登录日志
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Card v-else-if="props.view === 'certification'">
            <CardHeader>
                <CardTitle class="text-base">实名认证</CardTitle>
            </CardHeader>
            <CardContent class="space-y-5">
                <form
                    class="grid gap-4 md:grid-cols-[1fr_1fr_auto]"
                    @submit.prevent="submitCertification"
                >
                    <div class="grid gap-2">
                        <Label for="cert-name">cert_name</Label>
                        <Input
                            id="cert-name"
                            v-model="certificationForm.cert_name"
                            autocomplete="off"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="cert-no">cert_no</Label>
                        <Input
                            id="cert-no"
                            v-model="certificationForm.cert_no"
                            autocomplete="off"
                            required
                        />
                    </div>
                    <div class="flex items-end">
                        <Button type="submit" :disabled="saving">
                            <Spinner v-if="saving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            提交认证
                        </Button>
                    </div>
                </form>
                <Alert>
                    <ShieldCheck data-icon="alert" />
                    <AlertTitle>官方字段</AlertTitle>
                    <AlertDescription>
                        当前支持提交姓名和证件号码进行实名认证。
                    </AlertDescription>
                </Alert>
                <div class="overflow-x-auto border-y">
                    <table class="w-full min-w-[620px] table-fixed text-sm">
                        <colgroup>
                            <col style="width: 34%" />
                            <col style="width: 66%" />
                        </colgroup>
                        <thead class="border-b text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">
                                    字段
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    值
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td class="px-6 py-16 text-center" colspan="2">
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <tr
                                v-for="row in certifyRows"
                                v-else
                                :key="row.key"
                                class="border-b"
                            >
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ row.key }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="truncate">{{ row.value }}</div>
                                </td>
                            </tr>
                            <tr v-if="!loading && certifyRows.length === 0">
                                <td
                                    class="px-6 py-16 text-center text-muted-foreground"
                                    colspan="2"
                                >
                                    暂无认证状态
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Card v-else>
            <CardHeader
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <CardTitle class="text-base">账户概览</CardTitle>
                <Button
                    variant="outline"
                    :disabled="loading"
                    @click="loadOverview"
                >
                    <RefreshCw data-icon="inline-start" />
                    刷新
                </Button>
            </CardHeader>
            <CardContent class="space-y-6">
                <div v-if="loading" class="py-16">
                    <Spinner class="mx-auto" />
                </div>

                <template v-else-if="profileRows.length > 0">
                    <!-- balance + verification state, the things worth seeing first -->
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-lg border p-4">
                            <div class="text-xs text-muted-foreground">
                                账户余额
                            </div>
                            <div
                                class="mt-1 text-2xl font-semibold tabular-nums"
                            >
                                {{ accountBalance }}
                            </div>
                        </div>
                        <div
                            v-for="badge in profileBadges"
                            :key="badge.label"
                            class="rounded-lg border p-4"
                        >
                            <div class="text-xs text-muted-foreground">
                                {{ badge.label }}
                            </div>
                            <Badge
                                class="mt-2"
                                :variant="badge.ok ? 'default' : 'secondary'"
                            >
                                {{ badge.ok ? badge.okText : badge.noText }}
                            </Badge>
                        </div>
                    </div>

                    <!-- labelled detail list, not raw field names -->
                    <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                        <div
                            v-for="row in profileRows"
                            :key="row.key"
                            class="flex flex-col gap-1 border-b pb-3"
                        >
                            <dt class="text-xs text-muted-foreground">
                                {{ row.label }}
                            </dt>
                            <dd class="text-sm break-all">{{ row.value }}</dd>
                        </div>
                    </dl>
                </template>

                <p
                    v-else
                    class="py-16 text-center text-sm text-muted-foreground"
                >
                    暂无账户概览
                </p>
            </CardContent>
        </Card>

        <div
            v-if="props.view === 'login-logs'"
            class="flex items-center justify-end gap-2"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasPreviousPage || loading"
                @click="prevPage"
            >
                上一页
            </Button>
            <span class="text-sm text-muted-foreground">第 {{ page }} 页</span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasNextPage || loading"
                @click="nextPage"
            >
                下一页
            </Button>
        </div>
    </div>
</template>
