<script setup lang="ts">
import {
    AlertCircle,
    CheckCircle2,
    XCircle,
    MoreHorizontal,
    ChevronDown,
    PackagePlus,
    Plus,
    RefreshCw,
    Save,
    Trash2,
} from 'lucide-vue-next';
import { Zap } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import type { ConsoleTab } from '@/components/console/ConsoleTabs.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import DatePicker from '@/components/ui/date-picker/DatePicker.vue';
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
    DropdownMenuCheckboxItem,
    DropdownMenuSeparator,
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
import { Spinner } from '@/components/ui/spinner';
import Switch from '@/components/ui/switch/Switch.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { listAdminUsers } from '@/lib/adminConsoleApi';
import type { AdminUserRecord } from '@/lib/adminConsoleApi';
import {
    createAdminUserPackage,
    listAdminPackageGroups,
    createAdminPackageGroup,
    updateAdminPackageGroup,
    deleteAdminPackageGroup,
} from '@/lib/adminModulesApi';
import {
    batchUpdateAdminPackages,
    createAdminPackage,
    deleteAdminPackage,
    getAdminPackage,
    listAdminPackageOptions,
    listAdminPackageProducts,
    listAdminPackages,
    saveAdminPackageProduct,
    updateAdminPackage,
} from '@/lib/adminPackagesApi';
import type { AdminPackageProduct } from '@/lib/adminPackagesApi';
import type {
    AdminPackageBatchItem,
    AdminPackageOption,
    AdminPackageOptions,
    AdminPackagePayload,
} from '@/lib/adminPackagesApi';
import { extractCdnflyRows } from '@/lib/cdnflyResponse';
import {
    formatDate,
    formatMoney,
    getErrorMessage as fmtError,
} from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import AdminPackageUpgrades from './AdminPackageUpgrades.vue';

type PackageRecord = Record<string, unknown>;

type PackageForm = {
    name: string;
    des: string;
    region_id: string;
    node_group_id: string;
    backup_node_group: string;
    groups: string;
    month_price: string;
    quarter_price: string;
    year_price: string;
    traffic: string;
    bandwidth: string;
    connection: string;
    domain: string;
    main_domain: string;
    http_port: string;
    stream_port: string;
    custom_cc_rule: string;
    cc_protect: string;
    ddos_protect: string;
    websocket: string;
    http3: string;
    waf_protect: string;
    l2_state: string;
    id_verify: string;
    cname_domain: string;
    cname_hostname2: string;
    cname_mode: string;
    buy_num_limit: string;
    expire: string;
    before_exp_days_renew: string;
    owner: string;
    enable: string;
    sort: string;
    sync_item: string;
    backend_ip_limit: string;
    extra_json: string;
};

type DialogMode = 'create' | 'edit';
type LimitFieldKey =
    | 'traffic'
    | 'bandwidth'
    | 'connection'
    | 'domain'
    | 'main_domain'
    | 'http_port'
    | 'stream_port';

const SELECT_KEEP_VALUE = 'keep';
const CNAME_DEFAULT_VALUE = '__default__';
// 备用线路组 is optional. Select components can't hold an empty-string value,
// so "no backup line" is carried by this sentinel and dropped on submit.
const BACKUP_NONE_VALUE = '__none__';

/**
 * Tier presets for a single-node Tokyo deployment.
 *
 * The numbers match what ProductSeeder already advertises on the landing page,
 * so the console, the catalogue and the marketing copy cannot drift apart.
 *
 * Units come from the master: `traffic` is GB, `bandwidth` is free text
 * (100Mbps / 1Gbps), and -1 means unlimited. `main_domain` is the site count a
 * customer sees as "N 个网站"; `domain` is the larger total including
 * subdomains.
 *
 * DDoS protection is left as 不支持 deliberately: a plain VPS has no scrubbing
 * in front of it, and selling protection that does not exist is worse than
 * selling none.
 *
 * `features` holds only claims the limits cannot express. The numbers on a
 * plan card are rendered from the CDNfly package itself, so repeating them
 * here would reintroduce the drift this is meant to prevent.
 */
const TIER_PRESETS = [
    {
        key: 'mini',
        label: '入门',
        name: 'JPN-Mini',
        slug: 'jpn-mini',
        price: '5',
        limits: {
            traffic: '50',
            bandwidth: '100Mbps',
            connection: '2000',
            domain: '5',
            main_domain: '1',
            http_port: '0',
            stream_port: '0',
        },
        // Custom CC rules are the upsell; basic CC protection is included
        // everywhere because the agent does it anyway.
        custom_cc_rule: '0',
        features: ['东京 BGP 线路', '被攻击不额外收费', '5 分钟内开通'],
        description: '日本东京节点入门套餐，适合个人站点与小流量业务。',
    },
    {
        key: 'standard',
        label: '标准',
        name: 'JPN-Standard',
        slug: 'jpn-standard',
        price: '10',
        limits: {
            traffic: '100',
            bandwidth: '300Mbps',
            connection: '5000',
            domain: '15',
            main_domain: '5',
            http_port: '2',
            stream_port: '0',
        },
        custom_cc_rule: '0',
        features: ['东京 BGP 线路', '被攻击不额外收费', '免费 SSL 证书'],
        description: '日本东京节点标准套餐，适合中小企业站点与多域名业务。',
    },
    {
        key: 'plus',
        label: '进阶',
        name: 'JPN-Plus',
        slug: 'jpn-plus',
        price: '20',
        limits: {
            traffic: '200',
            bandwidth: '1Gbps',
            connection: '10000',
            domain: '30',
            main_domain: '10',
            http_port: '5',
            stream_port: '5',
        },
        custom_cc_rule: '1',
        features: [
            '东京 BGP 线路',
            '被攻击不额外收费',
            '免费 SSL 证书',
            '工单优先响应',
        ],
        description: '日本东京节点进阶套餐，适合流量增长期的业务。',
    },
    {
        key: 'pro',
        label: '高阶',
        name: 'JPN-Pro',
        slug: 'jpn-pro',
        price: '30',
        limits: {
            traffic: '300',
            bandwidth: '1Gbps',
            connection: '20000',
            domain: '60',
            main_domain: '20',
            http_port: '10',
            stream_port: '10',
        },
        custom_cc_rule: '1',
        features: [
            '东京 BGP 线路',
            '被攻击不额外收费',
            '免费 SSL 证书',
            '专属技术支持',
        ],
        description: '日本东京节点高阶套餐，适合高并发与多站点业务。',
    },
];

type TierPreset = (typeof TIER_PRESETS)[number];

/** The portal charges in USD (products.currency defaults to USD). */
// Stored as the ISO code USD (the product column is validated size:3), but the
// customer pays in the USD-pegged stablecoin USDT — so that is what the UI
// shows. 1:1 with the number, symbol only.
const PORTAL_CURRENCY = 'USD';
const PORTAL_CURRENCY_LABEL = 'USDT';
const cnameModeOptions = [
    { value: 'site', label: '按网站生成（推荐）' },
    { value: 'package', label: '按套餐生成' },
] as const;
const limitFieldConfigs: Array<{
    key: LimitFieldKey;
    label: string;
    placeholder: string;
    inputmode?: 'decimal' | 'numeric';
}> = [
    {
        key: 'traffic',
        label: '月流量（G）',
        placeholder: '填写月流量限制',
        inputmode: 'decimal',
    },
    {
        key: 'bandwidth',
        label: '带宽',
        placeholder: '如 100Mbps / 1Gbps',
    },
    {
        key: 'connection',
        label: '连接数',
        placeholder: '填写连接数限制',
        inputmode: 'numeric',
    },
    {
        key: 'domain',
        label: '域名数',
        placeholder: '填写域名数量',
        inputmode: 'numeric',
    },
    {
        key: 'main_domain',
        label: '主域名数',
        placeholder: '填写主域名数量',
        inputmode: 'numeric',
    },
    {
        key: 'http_port',
        label: '网站非标端口数',
        placeholder: '填写网站非标端口数',
        inputmode: 'numeric',
    },
    {
        key: 'stream_port',
        label: '四层端口数',
        placeholder: '填写四层端口数',
        inputmode: 'numeric',
    },
];
const emptyOptions: AdminPackageOptions = {
    regions: [],
    node_groups: [],
    package_groups: [],
    cname_domains: [],
};

const packages = ref<PackageRecord[]>([]);
const selectedIds = ref<number[]>([]);
const loading = ref(false);
const saving = ref(false);
const loadingDetailId = ref<number | null>(null);
const deleteOpen = ref(false);
const deleting = ref(false);
const deleteError = ref('');
const deleteTargetId = ref<number | null>(null);
const deleteTargetName = ref('');
const errorMessage = ref('');
const formError = ref('');
const batchError = ref('');
const optionsError = ref('');
const packageDialogOpen = ref(false);
const grantDialogOpen = ref(false);
const grantSaving = ref(false);
const grantUsersLoading = ref(false);
const grantError = ref('');
const grantPackage = ref<PackageRecord | null>(null);
const grantUsers = ref<AdminUserRecord[]>([]);
const grantForm = reactive({
    userId: '',
    duration: 'month',
    name: '',
});
const PACKAGE_DURATION_OPTIONS = [
    { value: 'month', label: '月付' },
    { value: 'quarter', label: '季付' },
    { value: 'year', label: '年付' },
];
type PackageTab = 'packages' | 'groups' | 'upgrades';

const props = withDefaults(defineProps<{ initialTab?: PackageTab }>(), {
    initialTab: 'packages',
});
const activeTab = ref<PackageTab>(props.initialTab);

const packageTabs = computed<ConsoleTab[]>(() => [
    { key: 'packages', label: '基础套餐' },
    { key: 'groups', label: '套餐分组' },
    ...(props.initialTab === 'upgrades' || activeTab.value === 'upgrades'
        ? [{ key: 'upgrades', label: '升级包', icon: Zap }]
        : []),
]);

const detailDialogOpen = ref(false);
const detailJsonOpen = ref(false);
const batchDialogOpen = ref(false);
const dialogMode = ref<DialogMode>('create');

/**
 * The portal-side product — the price customers actually pay.
 *
 * Kept next to the package form because the two are useless apart: a package
 * with no product cannot be bought, and a product with no package cannot be
 * provisioned. Creating them separately is what used to require editing .env
 * and re-running a seeder.
 */

const portalProducts = ref<Record<string, AdminPackageProduct>>({});
const portalLoading = ref(false);
const portalError = ref('');

const portalForm = reactive({
    sell: true,
    name: '',
    slug: '',
    description: '',
    features: '',
    sort_order: '',
});

/**
 * Fill the limits, capability flags and portal pricing from a tier.
 *
 * Region, 线路组 and 套餐组 are left alone on purpose — those are choices about
 * this deployment, not about the tier, and the admin has already made them.
 */
function applyTierPreset(preset: TierPreset): void {
    form.name = preset.name;

    if (form.cname_domain === CNAME_DEFAULT_VALUE || form.cname_domain === '') {
        form.cname_domain = defaultCnameDomainId();
    }

    Object.assign(form, preset.limits);

    form.custom_cc_rule = preset.custom_cc_rule;
    form.cc_protect = '支持';
    form.websocket = '1';
    form.http3 = '1';
    // WAF needs the module/rules running on the node; off until it actually is.
    form.waf_protect = '0';
    // One node means no L2 tier to fall back to.
    form.l2_state = '0';
    // Real-name verification blocks checkout unless you actually police it.
    form.id_verify = '0';
    // A plain VPS has no scrubbing in front of it.
    form.ddos_protect = '不支持';

    form.month_price = preset.price;
    form.quarter_price = '';
    form.year_price = '';

    portalForm.sell = true;
    portalForm.name = preset.name;
    portalForm.slug = preset.slug;
    portalForm.description = preset.description;
    portalForm.features = preset.features.join('\n');
    portalForm.sort_order = String(
        (TIER_PRESETS.findIndex((tier) => tier.key === preset.key) + 1) * 10,
    );
}

function resetPortalForm(
    packageName = '',
    existing?: AdminPackageProduct,
): void {
    portalForm.sell = existing?.is_active ?? true;
    portalForm.name = existing?.name ?? packageName;
    // Pre-filled from the linked product so a price-only edit re-sends the slug
    // it already has and cannot move the tier.
    portalForm.slug = existing?.slug ?? '';
    portalForm.description = existing?.description ?? '';
    portalForm.features = (existing?.features ?? []).join('\n');
    portalForm.sort_order = existing ? String(existing.sort_order) : '';
}

/** Preview of what a blank quarterly/yearly price will become. */
function portalPayload(): Record<string, unknown> {
    return {
        name: portalForm.name.trim() || form.name.trim(),
        slug: portalForm.slug.trim(),
        price_monthly: Number(form.month_price),
        price_quarterly: Number(form.quarter_price),
        price_yearly: Number(form.year_price),
        description: portalForm.description.trim() || null,
        features: portalForm.features
            .split('\n')
            .map((line) => line.trim())
            .filter((line) => line !== ''),
        sort_order:
            portalForm.sort_order === '' ? 0 : Number(portalForm.sort_order),
        is_active: portalForm.sell,
        currency: PORTAL_CURRENCY,
    };
}

async function loadPortalProducts(): Promise<void> {
    portalLoading.value = true;
    portalError.value = '';

    try {
        portalProducts.value = await listAdminPackageProducts();
    } catch (error) {
        portalError.value = getErrorMessage(error);
    } finally {
        portalLoading.value = false;
    }
}

const editingId = ref<number | null>(null);
const detailRecord = ref<PackageRecord | null>(null);

const batchAdvancedOpen = ref(false);
const packageOptions = ref<AdminPackageOptions>({ ...emptyOptions });
const resourceOptionLabel = (kind: 'regions' | 'node_groups', id: unknown) =>
    packageOptions.value[kind].find((o) => String(o.id) === String(id))?.name ??
    String(id ?? '—');
const loadingOptions = ref(false);

const form = reactive<PackageForm>(emptyPackageForm());
const packageLineOptions = computed(() =>
    packageOptions.value.node_groups.filter(
        (option) =>
            option.region_id === undefined ||
            !form.region_id ||
            String(option.region_id) === form.region_id,
    ),
);
function changePackageRegion(): void {
    if (
        !packageLineOptions.value.some(
            (option) => String(option.id) === form.node_group_id,
        )
    ) {
        form.node_group_id =
            packageLineOptions.value.length === 1
                ? String(packageLineOptions.value[0].id)
                : '';
    }

    if (
        !packageLineOptions.value.some(
            (option) => String(option.id) === form.backup_node_group,
        )
    ) {
        form.backup_node_group = BACKUP_NONE_VALUE;
    }
}
const batchForm = reactive<PackageForm>(emptyPackageForm(true));
const packageExpiry = computed({
    get: () => form.expire.replace(' ', 'T'),
    set: (value: string) => {
        form.expire = value
            ? value.replace('T', ' ') + (value.length === 16 ? ':00' : '')
            : '';
    },
});
const bandwidthUnit = computed(
    () => form.bandwidth.match(/(Mbps|Gbps)$/i)?.[1] ?? 'Mbps',
);
const bandwidthAmount = computed(() =>
    form.bandwidth.replace(/(Mbps|Gbps)$/i, ''),
);
function updateBandwidth(value: string, unit = bandwidthUnit.value): void {
    form.bandwidth = value === '' ? '' : `${value}${unit}`;
}
const rememberedLimits = new WeakMap<
    PackageForm,
    Partial<Record<LimitFieldKey, string>>
>();

const packageIds = computed(() =>
    packages.value
        .map((record) => getPackageId(record))
        .filter((id): id is number => id !== null),
);

const selectedCount = computed(() => selectedIds.value.length);
const detailJson = computed(() =>
    JSON.stringify(detailRecord.value ?? {}, null, 2),
);

/**
 * The detail view used to be a raw JSON dump, which meant reading -1 as
 * "unlimited" and 0/1 as feature flags in your head, and gave no sign of what
 * the package is actually sold for.
 *
 * Names are resolved through the option lists so 区域 1 reads as its name.
 */
function detailValue(key: string): unknown {
    return detailRecord.value?.[key];
}

/** -1 is CDNfly's unlimited; 0 means genuinely none. */
function limitText(key: string, unit = ''): string {
    const raw = detailValue(key);

    if (raw === null || raw === undefined || raw === '') {
        return '—';
    }

    if (String(raw) === '-1') {
        return '不限';
    }

    return `${raw}${unit}`;
}

function flagText(key: string): string {
    const raw = detailValue(key);

    if (raw === null || raw === undefined || raw === '') {
        return '—';
    }

    return String(raw) === '1' ? '支持' : '不支持';
}

function optionName(
    list: AdminPackageOption[],
    value: unknown,
    fallback = '—',
): string {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }

    const match = list.find((option) => String(option.id) === String(value));

    return match ? `${match.name}（${value}）` : String(value);
}

function plainText(key: string): string {
    const raw = detailValue(key);

    return raw === null || raw === undefined || raw === '' ? '—' : String(raw);
}

const detailSections = computed(() => {
    if (!detailRecord.value) {
        return [];
    }

    const options = packageOptions.value;

    return [
        {
            title: '基本信息',
            rows: [
                { label: '套餐 ID', value: plainText('id') },
                { label: '名称', value: plainText('name') },
                {
                    label: '区域',
                    value: optionName(
                        options.regions,
                        detailValue('region_id'),
                    ),
                },
                {
                    label: '线路组',
                    value: optionName(
                        options.node_groups,
                        detailValue('node_group_id'),
                    ),
                },
                { label: '所属套餐组', value: plainText('groups') },
                {
                    label: '状态',
                    value:
                        String(detailValue('enable')) === '1' ? '启用' : '停用',
                },
                { label: '备注', value: plainText('des') },
                {
                    label: '创建时间',
                    value: formatDate(detailValue('create_at') as string),
                },
            ],
        },
        {
            title: '规格限制',
            rows: [
                { label: '月流量', value: limitText('traffic', ' GB') },
                { label: '带宽', value: limitText('bandwidth') },
                { label: '连接数', value: limitText('connection') },
                { label: '主域名数（网站）', value: limitText('main_domain') },
                { label: '域名数', value: limitText('domain') },
                { label: '网站非标端口数', value: limitText('http_port') },
                { label: '四层端口数', value: limitText('stream_port') },
            ],
        },
        {
            title: '功能',
            rows: [
                { label: '自定义 CC 规则', value: flagText('custom_cc_rule') },
                { label: 'CC 防护', value: plainText('cc_protect') },
                { label: 'DDoS 防护', value: plainText('ddos_protect') },
                { label: 'WebSocket', value: flagText('websocket') },
                { label: 'HTTP3', value: flagText('http3') },
                { label: 'WAF 防护', value: flagText('waf_protect') },
                {
                    label: 'L2 节点回源',
                    value:
                        String(detailValue('l2_state')) === '1'
                            ? '启用'
                            : '禁用',
                },
                {
                    label: '实名认证',
                    value:
                        String(detailValue('id_verify')) === '1'
                            ? '需要'
                            : '不需要',
                },
            ],
        },
        {
            title: 'CNAME 设置',
            rows: [
                {
                    label: 'CNAME 域名',
                    value: optionName(
                        options.cname_domains,
                        detailValue('cname_domain'),
                    ),
                },
                { label: 'CNAME 主机名', value: plainText('cname_hostname2') },
                {
                    label: 'CNAME 模式',
                    value:
                        String(detailValue('cname_mode')) === 'package'
                            ? '按套餐生成'
                            : '按网站生成',
                },
            ],
        },
        {
            title: '购买与计费',
            rows: [
                { label: '单用户购买数量', value: limitText('buy_num_limit') },
                {
                    label: '提前续费天数',
                    value: limitText('before_exp_days_renew', ' 天'),
                },
                {
                    label: '可购买截止时间',
                    value: formatDate(detailValue('expire')),
                },
                // The real prepaid prices charged against the customer's CDNfly
                // balance. Synced from 门户售价 on save, so these should match it
                // 1:1 using the same numeric USDT accounting units.
                {
                    label: 'CDNfly 月付',
                    value: formatMoney(detailValue('month_price')),
                },
                {
                    label: 'CDNfly 季付',
                    value: formatMoney(detailValue('quarter_price')),
                },
                {
                    label: 'CDNfly 年付',
                    value: formatMoney(detailValue('year_price')),
                },
            ],
        },
    ];
});

/**
 * The customer-facing monthly price, or '' when the package has no product.
 *
 * CDNfly's own month_price is 0 on every portal-sold tier, so showing it in the
 * list made every package look free.
 */
/**
 * The six capability flags CDNfly shows on a package, as list badges.
 * WS/H3/WAF/L2 are stored 1/0; CC and DDoS are free text (支持 / 不支持 /
 * a scrubbing size like "500G"), so `on` is derived per field rather than a
 * plain 1-check.
 */
function capabilityBadges(
    record: PackageRecord,
): Array<{ label: string; on: boolean }> {
    const flag = (keys: string[]) =>
        String(getDisplayValue(record, keys, '0')) === '1';
    const cc = getDisplayValue(record, ['cc_protect'], '');
    const ddos = getDisplayValue(record, ['ddos_protect'], '');

    return [
        { label: 'WS', on: flag(['websocket']) },
        { label: 'H3', on: flag(['http3']) },
        { label: 'WAF', on: flag(['waf_protect']) },
        { label: 'L2', on: flag(['l2_state']) },
        { label: 'CC', on: cc === '支持' || cc === '1' },
        { label: 'DDoS', on: ddos !== '' && ddos !== '不支持' && ddos !== '0' },
    ];
}

/** The portal product this package is sold as, if any. */
const detailProduct = computed(() => {
    const id = detailValue('id');

    return id === null || id === undefined
        ? undefined
        : portalProducts.value[String(id)];
});
const cnameDomainOptions = computed(() => [
    { id: CNAME_DEFAULT_VALUE, name: '不指定 CNAME 域名' },
    ...packageOptions.value.cname_domains,
]);

onMounted(() => {
    void loadPackages();
    void loadPackageOptions();
    void loadPortalProducts();
});

async function loadPackages(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const payload = await listAdminPackages({ limit: 0 });
        packages.value = extractPackageRecords(payload);
        selectedIds.value = selectedIds.value.filter((id) =>
            packageIds.value.includes(id),
        );
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function loadPackageOptions(): Promise<void> {
    loadingOptions.value = true;
    optionsError.value = '';

    try {
        packageOptions.value = await listAdminPackageOptions();
    } catch (error) {
        packageOptions.value = { ...emptyOptions };
        optionsError.value = getErrorMessage(error);
    } finally {
        loadingOptions.value = false;
    }
}

/**
 * The first real CNAME domain, or '' when the options have not loaded.
 *
 * 不指定 is a valid choice when editing, but on create the master requires a
 * domain, so leaving the placeholder selected guarantees a failed save.
 */
function defaultCnameDomainId(): string {
    const first = packageOptions.value.cname_domains.find(
        (option) => String(option.id) !== CNAME_DEFAULT_VALUE,
    );

    return first ? String(first.id) : '';
}

function openCreateDialog(): void {
    rememberedLimits.delete(form);
    Object.assign(form, emptyPackageForm());
    form.cname_domain = defaultCnameDomainId();

    if (packageOptions.value.regions.length === 1) {
        form.region_id = String(packageOptions.value.regions[0].id);
    }

    changePackageRegion();

    form.groups = String(packageOptions.value.package_groups[0]?.id ?? '');
    resetPortalForm();
    formError.value = '';
    editingId.value = null;
    dialogMode.value = 'create';
    closePackageAdvancedSections();
    packageDialogOpen.value = true;
}

async function openEditDialog(record: PackageRecord): Promise<void> {
    if (portalLoading.value || portalError.value) {
        errorMessage.value = '请等待门户商品加载完成，或重试加载后再编辑套餐';

        return;
    }

    const id = getPackageId(record);

    if (id === null) {
        errorMessage.value = '套餐缺少 ID，无法获取详情';

        return;
    }

    loadingDetailId.value = id;
    formError.value = '';

    try {
        const payload = await getAdminPackage(id);
        const detail = extractPackageDetail(payload) ?? record;
        Object.assign(form, formFromRecord(detail));
        rememberedLimits.delete(form);
        resetPortalForm(
            String(detail.name ?? ''),
            portalProducts.value[String(id)],
        );

        if (!portalProducts.value[String(id)]) {
            portalForm.sell = false;
        }

        editingId.value = id;
        dialogMode.value = 'edit';
        closePackageAdvancedSections();
        packageDialogOpen.value = true;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loadingDetailId.value = null;
    }
}

async function openDetailDialog(record: PackageRecord): Promise<void> {
    const id = getPackageId(record);

    if (id === null) {
        errorMessage.value = '套餐缺少 ID，无法获取详情';

        return;
    }

    loadingDetailId.value = id;
    errorMessage.value = '';

    try {
        const payload = await getAdminPackage(id);
        detailRecord.value = extractPackageDetail(payload) ?? record;
        detailDialogOpen.value = true;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loadingDetailId.value = null;
    }
}

/**
 * CDNfly is the real, prepaid price: buying or renewing deducts the customer's
 * balance at the package's month_price/quarter_price/year_price. So the single
 * price the admin types (门户售价) drives those CDNfly fields directly — the
 * accounting is 1:1 (5 USDT = 5 CDNfly balance units). The portal product
 * stores the same numbers for the storefront, so the two never diverge.
 *
 * A blank quarter/year mirrors the product's rule: ×3 and ×12, no assumed
 * discount.
 */
function resolvePackagePrices(): void {
    const monthly = Number(form.month_price);

    if (
        form.month_price.trim() === '' ||
        !Number.isFinite(monthly) ||
        monthly < 0
    ) {
        throw new Error('请填写有效的月付价格');
    }

    const quarterly =
        form.quarter_price.trim() === ''
            ? monthly * 3
            : Number(form.quarter_price);
    const yearly =
        form.year_price.trim() === '' ? monthly * 12 : Number(form.year_price);

    if (
        !Number.isFinite(quarterly) ||
        quarterly < 0 ||
        !Number.isFinite(yearly) ||
        yearly < 0
    ) {
        throw new Error('请填写有效的季度付和年付价格');
    }

    form.month_price = String(monthly);
    form.quarter_price = String(quarterly);
    form.year_price = String(yearly);
}

async function submitPackage(): Promise<void> {
    saving.value = true;
    formError.value = '';
    let masterSaved = false;

    try {
        // The price the admin entered must reach CDNfly, not just the portal
        // product — otherwise the master keeps charging its old (or zero) price.
        resolvePackagePrices();

        const payload = buildPackagePayload(form, dialogMode.value);

        if (dialogMode.value === 'create') {
            const result = (await createAdminPackage(
                portalForm.sell
                    ? { ...payload, portal: portalPayload() }
                    : payload,
            )) as { portal_warning?: string } | undefined;

            // The package is created either way; a portal problem must be
            // visible without implying the whole operation failed.
            if (result?.portal_warning) {
                toast.warning(result.portal_warning);
            }
        } else if (editingId.value !== null) {
            await updateAdminPackage(editingId.value, payload);
            masterSaved = true;

            if (
                portalForm.sell ||
                portalProducts.value[String(editingId.value)]
            ) {
                await saveAdminPackageProduct(editingId.value, portalPayload());
            }
        }

        packageDialogOpen.value = false;
        await Promise.all([loadPackages(), loadPortalProducts()]);
    } catch (error) {
        formError.value = masterSaved
            ? `主控套餐已保存，门户商品同步失败：${getErrorMessage(error)}。可重试保存。`
            : getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

async function submitBatchUpdate(): Promise<void> {
    saving.value = true;
    batchError.value = '';

    try {
        const payload = buildPackagePayload(batchForm, 'batch');
        const items: AdminPackageBatchItem[] = selectedIds.value.map((id) => ({
            id,
            ...payload,
        }));
        const result = await batchUpdateAdminPackages(items);

        if (result.failed_count > 0) {
            batchError.value = `${result.failed_count} 个套餐更新失败，请刷新后核对`;
        } else {
            batchDialogOpen.value = false;
        }

        await loadPackages();
    } catch (error) {
        batchError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

function openDeletePackage(record: PackageRecord) {
    const id = getPackageId(record);

    if (id === null) {
        errorMessage.value = '套餐缺少 ID，无法删除';

        return;
    }

    deleteTargetId.value = id;
    deleteTargetName.value = getDisplayValue(
        record,
        ['name', 'title'],
        `#${id}`,
    );
    deleteError.value = '';
    deleteOpen.value = true;
}

async function confirmDeletePackage(): Promise<void> {
    const id = deleteTargetId.value;

    if (id === null) {
        return;
    }

    deleting.value = true;

    try {
        await deleteAdminPackage(id);
        deleteOpen.value = false;
        selectedIds.value = selectedIds.value.filter((item) => item !== id);
        toast.success('套餐已删除');
        await loadPackages();
    } catch (error) {
        deleteError.value = getErrorMessage(error);
    } finally {
        deleting.value = false;
    }
}

function openBatchDialog(): void {
    Object.assign(batchForm, emptyPackageForm(true));
    batchError.value = '';
    batchAdvancedOpen.value = false;
    batchDialogOpen.value = true;
}

function closePackageAdvancedSections(): void {
    advancedOpen.value = false;
    salesOpen.value = false;
}

function isLimitEnabled(source: PackageForm, key: LimitFieldKey): boolean {
    return source[key] !== '-1';
}

function toggleLimit(source: PackageForm, key: LimitFieldKey): void {
    if (isLimitEnabled(source, key)) {
        rememberedLimits.set(source, {
            ...rememberedLimits.get(source),
            [key]: source[key],
        });
        source[key] = '-1';

        return;
    }

    source[key] = rememberedLimits.get(source)?.[key] ?? '';
}

function emptyPackageForm(batch = false): PackageForm {
    return {
        name: '',
        des: '',
        region_id: '',
        node_group_id: '',
        backup_node_group: batch ? SELECT_KEEP_VALUE : BACKUP_NONE_VALUE,
        groups: '',
        month_price: '',
        quarter_price: '',
        year_price: '',
        traffic: batch ? '' : '-1',
        bandwidth: batch ? '' : '-1',
        connection: batch ? '' : '-1',
        domain: batch ? '' : '-1',
        main_domain: batch ? '' : '-1',
        http_port: batch ? '' : '-1',
        stream_port: batch ? '' : '-1',
        custom_cc_rule: batch ? SELECT_KEEP_VALUE : '1',
        cc_protect: batch ? SELECT_KEEP_VALUE : '支持',
        ddos_protect: batch ? SELECT_KEEP_VALUE : '不支持',
        websocket: batch ? SELECT_KEEP_VALUE : '1',
        http3: batch ? SELECT_KEEP_VALUE : '1',
        waf_protect: batch ? SELECT_KEEP_VALUE : '0',
        l2_state: batch ? SELECT_KEEP_VALUE : '0',
        id_verify: batch ? SELECT_KEEP_VALUE : '1',
        cname_domain: batch ? SELECT_KEEP_VALUE : CNAME_DEFAULT_VALUE,
        cname_hostname2: '',
        cname_mode: batch ? SELECT_KEEP_VALUE : 'site',
        buy_num_limit: '',
        expire: '',
        before_exp_days_renew: '',
        owner: '',
        enable: batch ? SELECT_KEEP_VALUE : '1',
        sort: '',
        sync_item: '',
        backend_ip_limit: '',
        extra_json: '{}',
    };
}

function formFromRecord(record: PackageRecord): PackageForm {
    return {
        name: getDisplayValue(record, ['name', 'title'], ''),
        des: getDisplayValue(
            record,
            ['des', 'remark', 'description', 'desc'],
            '',
        ),
        region_id: getDisplayValue(record, ['region_id'], ''),
        node_group_id: getDisplayValue(record, ['node_group_id'], ''),
        backup_node_group: normalizeBackupNodeGroup(
            getDisplayValue(record, ['backup_node_group'], ''),
        ),
        groups: Array.isArray(record.groups)
            ? record.groups.map(String).join(',')
            : getDisplayValue(record, ['groups', 'group'], ''),
        month_price: getDisplayValue(record, ['month_price'], ''),
        quarter_price: getDisplayValue(record, ['quarter_price'], ''),
        year_price: getDisplayValue(record, ['year_price'], ''),
        traffic: getDisplayValue(
            record,
            ['traffic', 'traffic_limit', 'flow'],
            '-1',
        ),
        bandwidth: getDisplayValue(
            record,
            ['bandwidth', 'bandwidth_limit'],
            '-1',
        ),
        connection: getDisplayValue(
            record,
            ['connection', 'connections'],
            '-1',
        ),
        domain: getDisplayValue(
            record,
            ['domain', 'site_limit', 'site_num', 'sites', 'domain_limit'],
            '-1',
        ),
        main_domain: getDisplayValue(record, ['main_domain'], '-1'),
        http_port: getDisplayValue(record, ['http_port'], '-1'),
        stream_port: getDisplayValue(record, ['stream_port'], '-1'),
        custom_cc_rule: getDisplayValue(record, ['custom_cc_rule'], '0'),
        cc_protect: getDisplayValue(record, ['cc_protect'], '支持'),
        ddos_protect: getDisplayValue(record, ['ddos_protect'], ''),
        websocket: getDisplayValue(record, ['websocket'], '0'),
        http3: getDisplayValue(record, ['http3'], '0'),
        waf_protect: getDisplayValue(record, ['waf_protect'], '0'),
        l2_state: getDisplayValue(record, ['l2_state'], '0'),
        id_verify: getDisplayValue(record, ['id_verify'], '0'),
        cname_domain:
            getDisplayValue(record, ['cname_domain'], '') ||
            CNAME_DEFAULT_VALUE,
        cname_hostname2: getDisplayValue(record, ['cname_hostname2'], ''),
        cname_mode: getDisplayValue(record, ['cname_mode'], ''),
        buy_num_limit: getDisplayValue(record, ['buy_num_limit'], '-1'),
        expire: getDisplayValue(record, ['expire2', 'expire'], ''),
        before_exp_days_renew: getDisplayValue(
            record,
            ['before_exp_days_renew'],
            '',
        ),
        owner: getDisplayValue(record, ['owner'], ''),
        enable: getDisplayValue(record, ['enable', 'status', 'state'], '1'),
        sort: getDisplayValue(record, ['sort', 'order'], ''),
        sync_item: '',
        backend_ip_limit: getDisplayValue(record, ['backend_ip_limit'], ''),
        extra_json: '{}',
    };
}

function buildPackagePayload(
    source: PackageForm,
    mode: DialogMode | 'batch',
): AdminPackagePayload {
    const requireName = mode === 'create';
    const payload: AdminPackagePayload = {};
    const name = source.name.trim();

    if (mode !== 'batch' && name === '') {
        throw new Error('请输入套餐名称');
    }

    if (mode !== 'batch') {
        if (
            source.backup_node_group === source.node_group_id &&
            source.node_group_id !== ''
        ) {
            throw new Error('备用线路不能与主线路相同');
        }

        for (const field of limitFieldConfigs) {
            if (source[field.key].trim() === '') {
                throw new Error(`请填写${field.label}，或关闭对应限制`);
            }
        }
    }

    appendText(payload, 'name', name);
    appendText(payload, 'des', source.des.trim());
    appendNumber(payload, 'region_id', source.region_id, '区域 ID');
    appendNumber(payload, 'node_group_id', source.node_group_id, '线路组 ID');
    const backupNodeGroup = source.backup_node_group.trim();

    if (
        backupNodeGroup !== '' &&
        backupNodeGroup !== BACKUP_NONE_VALUE &&
        backupNodeGroup !== SELECT_KEEP_VALUE
    ) {
        appendNumber(
            payload,
            'backup_node_group',
            backupNodeGroup,
            '备用线路组 ID',
        );
    }

    appendText(payload, 'groups', source.groups.trim());
    appendNumber(payload, 'month_price', source.month_price, '月付价格');
    appendNumber(payload, 'quarter_price', source.quarter_price, '季付价格');
    appendNumber(payload, 'year_price', source.year_price, '年付价格');
    appendNumber(payload, 'traffic', source.traffic, '月流量');
    appendText(payload, 'bandwidth', source.bandwidth.trim());
    appendNumber(payload, 'connection', source.connection, '连接数');
    appendNumber(payload, 'domain', source.domain, '域名数');
    appendNumber(payload, 'main_domain', source.main_domain, '主域名数');
    appendNumber(payload, 'http_port', source.http_port, 'HTTP 非标端口数');
    appendNumber(payload, 'stream_port', source.stream_port, '四层端口数');
    appendSelectBoolean(payload, 'custom_cc_rule', source.custom_cc_rule);
    appendTextOrKeep(payload, 'cc_protect', source.cc_protect.trim());
    appendTextOrKeep(payload, 'ddos_protect', source.ddos_protect.trim());
    appendSelectBoolean(payload, 'websocket', source.websocket);
    appendSelectBoolean(payload, 'http3', source.http3);
    appendSelectBoolean(payload, 'waf_protect', source.waf_protect);
    appendSelectBoolean(payload, 'l2_state', source.l2_state);
    appendSelectBoolean(payload, 'id_verify', source.id_verify);
    appendCnameDomain(payload, source.cname_domain);
    appendText(payload, 'cname_hostname2', source.cname_hostname2.trim());

    if (source.cname_mode !== SELECT_KEEP_VALUE) {
        appendText(payload, 'cname_mode', source.cname_mode.trim());
    }

    appendNumber(
        payload,
        'buy_num_limit',
        source.buy_num_limit,
        '单用户购买数',
    );
    appendText(payload, 'expire', source.expire.trim());
    appendNumber(
        payload,
        'before_exp_days_renew',
        source.before_exp_days_renew,
        '提前续费天数',
    );
    appendText(payload, 'owner', source.owner.trim());
    appendSelectBoolean(payload, 'enable', source.enable);
    appendNumber(payload, 'sort', source.sort, '排序');
    appendText(payload, 'sync-item', source.sync_item.trim());

    if (requireName) {
        requirePayloadKeys(payload, [
            ['region_id', '请选择或填写区域 ID'],
            ['node_group_id', '请选择或填写线路组 ID'],
            ['month_price', '请填写月付价格'],
            ['quarter_price', '请填写季付价格'],
            ['year_price', '请填写年付价格'],
            ['groups', '请填写所属套餐组 ID'],
            // Omitting it gets rejected by the master with 无法找到此cname域名,
            // which does not hint that a field is simply missing.
            ['cname_domain', '请选择 CNAME 域名'],
        ]);
    }

    if (requireName || source.backend_ip_limit.trim() !== '') {
        payload.backend_ip_limit = source.backend_ip_limit.trim();
    }

    if (mode !== 'batch') {
        for (const key of [
            'des',
            'cname_hostname2',
            'expire',
            'owner',
            'backend_ip_limit',
            'cc_protect',
            'ddos_protect',
        ] as const) {
            payload[key] = source[key].trim();
        }
    }

    if (source.backup_node_group === BACKUP_NONE_VALUE) {
        payload.backup_node_group = '';
    }

    const extra = parseExtraJson(source.extra_json);
    const merged = {
        ...payload,
        ...extra,
    };

    if (Object.keys(merged).length === 0) {
        throw new Error('请至少填写一个要提交的字段');
    }

    return merged;
}

function appendText(
    payload: AdminPackagePayload,
    key: string,
    value: string,
): void {
    if (value !== '') {
        payload[key] = value;
    }
}

function appendTextOrKeep(
    payload: AdminPackagePayload,
    key: string,
    value: string,
): void {
    if (value === SELECT_KEEP_VALUE) {
        return;
    }

    appendText(payload, key, value);
}

function appendNumber(
    payload: AdminPackagePayload,
    key: string,
    value: string,
    label: string,
): void {
    const trimmed = value.trim();

    if (trimmed === '') {
        return;
    }

    const numberValue = Number(trimmed);

    if (!Number.isFinite(numberValue)) {
        throw new Error(`${label}必须是数字`);
    }

    payload[key] = numberValue;
}

function appendSelectBoolean(
    payload: AdminPackagePayload,
    key: string,
    value: string,
): void {
    if (value === SELECT_KEEP_VALUE || value === '') {
        return;
    }

    if (!['0', '1', 'false', 'true'].includes(value)) {
        throw new Error(`${key} 只能选择启用或禁用`);
    }

    payload[key] = ['1', 'true'].includes(value) ? 1 : 0;
}

function appendCnameDomain(payload: AdminPackagePayload, value: string): void {
    if (value === SELECT_KEEP_VALUE || value === CNAME_DEFAULT_VALUE) {
        return;
    }

    const trimmed = value.trim();

    if (trimmed === '') {
        return;
    }

    // The master keys this on the CNAME domain's id and sends it as a number
    // (packageNumber, chunk-7d3669d6). Sending the domain text instead gets
    // rejected with 无法找到此cname域名.
    const id = Number(trimmed);

    payload.cname_domain = Number.isInteger(id) ? id : trimmed;
}

function requirePayloadKeys(
    payload: AdminPackagePayload,
    keys: Array<[string, string]>,
): void {
    for (const [key, message] of keys) {
        if (!(key in payload)) {
            throw new Error(message);
        }
    }
}

/** CDNfly stores "no backup line" as an empty value or 0; the Select needs a
 * real sentinel to show it as 「无」. */
function normalizeBackupNodeGroup(value: string): string {
    const trimmed = value.trim();

    return trimmed === '' || trimmed === '0' ? BACKUP_NONE_VALUE : trimmed;
}

function optionValue(option: AdminPackageOption): string {
    return String(option.id);
}

function optionLabel(option: AdminPackageOption): string {
    if (String(option.id) === CNAME_DEFAULT_VALUE) {
        return option.name;
    }

    return `${option.name} (${option.id})`;
}

function parseExtraJson(value: string): AdminPackagePayload {
    const trimmed = value.trim();

    if (trimmed === '' || trimmed === '{}') {
        return {};
    }

    const parsed = JSON.parse(trimmed) as unknown;

    if (!isRecord(parsed) || Array.isArray(parsed)) {
        throw new Error('扩展字段必须是 JSON 对象');
    }

    return parsed;
}

function extractPackageRecords(payload: unknown): PackageRecord[] {
    return findRecordList(payload);
}

function extractPackageDetail(payload: unknown): PackageRecord | null {
    const list = findRecordList(payload);

    if (list[0]) {
        return list[0];
    }

    if (!isRecord(payload)) {
        return null;
    }

    const data = payload.data;

    if (isRecord(data)) {
        return data;
    }

    return payload;
}

function findRecordList(value: unknown, depth = 0): PackageRecord[] {
    if (depth > 4) {
        return [];
    }

    if (Array.isArray(value)) {
        return value.filter(isRecord);
    }

    if (!isRecord(value)) {
        return [];
    }

    for (const key of ['data', 'items', 'list', 'rows', 'records']) {
        const found = findRecordList(value[key], depth + 1);

        if (found.length > 0) {
            return found;
        }
    }

    return [];
}

function getPackageId(record: PackageRecord): number | null {
    for (const key of ['id', 'package_id', 'pid']) {
        const value = record[key];
        const numeric = Number(value);

        if (Number.isInteger(numeric) && numeric > 0) {
            return numeric;
        }
    }

    return null;
}

async function openGrantDialog(record: PackageRecord): Promise<void> {
    const packageId = getPackageId(record);

    if (packageId === null) {
        toast.error('套餐 ID 无效，无法开通');

        return;
    }

    grantPackage.value = record;
    grantForm.userId = '';
    grantForm.duration = 'month';
    grantForm.name = '';
    grantError.value = '';
    grantUsers.value = [];
    grantDialogOpen.value = true;
    grantUsersLoading.value = true;

    try {
        const result = await listAdminUsers({
            page: 1,
            per_page: 100,
            role: 'user',
        });
        grantUsers.value = result.data.filter(
            (user) => Number(user.cdnfly_user_id) > 0,
        );

        if (grantUsers.value.length === 0) {
            grantError.value = '暂无已同步 CDNfly 的普通用户';
        }
    } catch (error) {
        grantError.value = getErrorMessage(error);
    } finally {
        grantUsersLoading.value = false;
    }
}

async function submitGrantPackage(): Promise<void> {
    const packageId = grantPackage.value
        ? getPackageId(grantPackage.value)
        : null;
    const selectedUser = grantUsers.value.find(
        (user) => String(user.id) === grantForm.userId,
    );

    if (packageId === null || !selectedUser?.cdnfly_user_id) {
        grantError.value = '请选择要开通套餐的用户';

        return;
    }

    grantSaving.value = true;
    grantError.value = '';

    try {
        const packageName = getDisplayValue(
            grantPackage.value ?? {},
            ['name', 'title', 'package_name'],
            `套餐 #${packageId}`,
        );

        await createAdminUserPackage({
            uid: selectedUser.cdnfly_user_id,
            package: packageId,
            duration: grantForm.duration,
            name: grantForm.name.trim() || packageName,
        });
        grantDialogOpen.value = false;
        toast.success(`已为 ${selectedUser.name} 开通 ${packageName}`);
    } catch (error) {
        grantError.value = getErrorMessage(error);
    } finally {
        grantSaving.value = false;
    }
}

function grantUserLabel(user: AdminUserRecord): string {
    return `${user.name}（${user.email}，CDNfly ID: ${user.cdnfly_user_id}）`;
}

function getDisplayValue(
    record: PackageRecord,
    keys: string[],
    fallback = '-',
): string {
    for (const key of keys) {
        const value = record[key];

        if (isScalar(value) && String(value) !== '') {
            return String(value);
        }
    }

    return fallback;
}

function normalizeStatus(record: PackageRecord): string {
    return getDisplayValue(
        record,
        ['enable', 'status', 'state'],
        '',
    ).toLowerCase();
}

function statusText(record: PackageRecord): string {
    const status = normalizeStatus(record);

    if (['1', 'true', 'active', 'enable', 'enabled'].includes(status)) {
        return '上架';
    }

    if (['0', 'false', 'inactive', 'disable', 'disabled'].includes(status)) {
        return '下架';
    }

    return getDisplayValue(record, ['enable', 'status', 'state'], '未知');
}

function isRecord(value: unknown): value is PackageRecord {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isScalar(value: unknown): value is string | number | boolean {
    return ['string', 'number', 'boolean'].includes(typeof value);
}

function getErrorMessage(error: unknown): string {
    return error instanceof Error ? error.message : '请求失败';
}

// ─── Package Groups CRUD ─────────────────────────────────
const pgColumns: ColumnDef[] = [
    { key: 'id', label: 'ID' },
    { key: 'name', label: '名称' },
    { key: 'des', label: '备注' },
    { key: 'sort', label: '排序', format: (v) => String(v ?? 100) },
    { key: 'enable', label: '启用' },
];
const pgDialogOpen = ref(false);
const pgSaving = ref(false);
const pgError = ref('');
const pgEditing = ref<CdnflyRecord | null>(null);
const pgForm = reactive({ name: '', des: '', sort: '100', enable: '1' });
const pgDeleteOpen = ref(false);
const pgDeleteTarget = ref<CdnflyRecord | null>(null);
const pgDeleting = ref(false);
const pgDeleteError = ref('');

function openPgCreate(): void {
    pgEditing.value = null;
    pgForm.name = '';
    pgForm.des = '';
    pgForm.sort = '100';
    pgForm.enable = '1';
    pgError.value = '';
    pgDialogOpen.value = true;
}

function openPgEdit(row: CdnflyRecord): void {
    pgEditing.value = row;
    pgForm.name = String(row.name ?? '');
    pgForm.des = String(row.des ?? '');
    pgForm.sort = String(row.sort ?? 100);
    pgForm.enable = String(row.enable ?? 1);
    pgError.value = '';
    pgDialogOpen.value = true;
}

async function submitPg(): Promise<void> {
    if (!pgForm.name.trim() || !Number.isInteger(Number(pgForm.sort))) {
        pgError.value = '请输入名称和有效排序';

        return;
    }

    pgSaving.value = true;
    pgError.value = '';

    try {
        const payload: Record<string, unknown> = {
            name: pgForm.name.trim(),
            des: pgForm.des.trim(),
            sort: Number(pgForm.sort),
            enable: Number(pgForm.enable),
        };

        if (pgEditing.value) {
            await updateAdminPackageGroup(Number(pgEditing.value.id), payload);
            toast.success('套餐组已更新');
        } else {
            await createAdminPackageGroup(payload);
            toast.success('套餐组已创建');
        }

        pgDialogOpen.value = false;
        void loadGroups();
        void loadPackageOptions();
    } catch (error) {
        pgError.value = fmtError(error);
    } finally {
        pgSaving.value = false;
    }
}

function openPgDelete(row: CdnflyRecord): void {
    pgDeleteTarget.value = row;
    pgDeleteError.value = '';
    pgDeleteOpen.value = true;
}

async function confirmPgDelete(): Promise<void> {
    if (!pgDeleteTarget.value) {
        return;
    }

    pgDeleting.value = true;
    pgDeleteError.value = '';

    try {
        await deleteAdminPackageGroup(Number(pgDeleteTarget.value.id));
        pgDeleteOpen.value = false;
        toast.success('套餐组已删除');
        void loadGroups();
        void loadPackageOptions();
    } catch (error) {
        pgDeleteError.value = fmtError(error);
    } finally {
        pgDeleting.value = false;
    }
}

const listPage = ref(1),
    listSize = ref(10),
    groupFilter = ref('all');
const splitGroups = (value: unknown): string[] =>
    Array.isArray(value)
        ? value.map(String)
        : String(value ?? '')
              .split(',')
              .map((v) => v.trim())
              .filter(Boolean);
const filteredPackages = computed(() =>
    packages.value.filter(
        (row) =>
            groupFilter.value === 'all' ||
            splitGroups(row.groups ?? row.group).includes(groupFilter.value),
    ),
);
const visiblePackages = computed(() =>
    filteredPackages.value.slice(
        (listPage.value - 1) * listSize.value,
        listPage.value * listSize.value,
    ),
);
watch([groupFilter, listSize], () => {
    listPage.value = 1;
    selectedIds.value = [];
});
watch(listPage, () => {
    selectedIds.value = [];
});
watch(
    () => filteredPackages.value.length,
    (n) => {
        listPage.value = Math.min(
            listPage.value,
            Math.max(1, Math.ceil(n / listSize.value)),
        );
    },
);
const packageColumns: ColumnDef[] = [
    { key: 'package', label: '套餐' },
    { key: 'line', label: '区域 / 线路' },
    { key: 'limits', label: '资源限制' },
    { key: 'capabilities', label: '能力' },
    { key: 'prices', label: '月价 / 季价 / 年价' },
    { key: 'enable', label: '启用' },
];
const rowLimitText = (value: unknown, unit = '') =>
    value === undefined || value === null || value === ''
        ? '—'
        : String(value) === '-1'
          ? '不限'
          : `${value}${unit}`;
function priceText(row: PackageRecord) {
    const product = portalProducts.value[String(getPackageId(row))];

    return `${formatMoney(row.month_price ?? product?.price_monthly)} / ${formatMoney(row.quarter_price ?? product?.price_quarterly)} / ${formatMoney(row.year_price ?? product?.price_yearly)}`;
}
function setPackageGroup(id: string, checked: boolean | 'indeterminate') {
    const values = splitGroups(form.groups).filter((value) => value !== id);

    if (checked === true) {
        values.push(id);
    }

    form.groups = values.join(',');
}
const formGroupLabels = computed(() =>
    splitGroups(form.groups)
        .map(
            (id) =>
                packageOptions.value.package_groups.find(
                    (row) => String(row.id) === id,
                )?.name ?? id,
        )
        .join('、'),
);
const capabilityFields = [
    { key: 'custom_cc_rule', label: '自定义CC规则' },
    { key: 'websocket', label: 'WebSocket' },
    { key: 'http3', label: 'HTTP3' },
    { key: 'waf_protect', label: 'WAF防护' },
    { key: 'l2_state', label: 'L2节点回源' },
] as const;
const advancedOpen = ref(false),
    salesOpen = ref(false);
const orderedLimits = computed(() =>
    [
        'traffic',
        'bandwidth',
        'connection',
        'stream_port',
        'domain',
        'main_domain',
        'http_port',
    ].map((key) => limitFieldConfigs.find((field) => field.key === key)!),
);
const actionBusy = ref(false),
    syncOpen = ref(false),
    syncError = ref(''),
    syncFields = ref<string[]>([]);
const syncOptions = [
    { key: 'traffic', label: '月流量' },
    { key: 'bandwidth', label: '带宽' },
    { key: 'connection', label: '连接数' },
    { key: 'domain', label: '域名数' },
    { key: 'main_domain', label: '主域名数' },
    { key: 'http_port', label: '网站非标端口数' },
    { key: 'stream_port', label: '四层端口数' },
    { key: 'node_group_id', label: '线路分组' },
    { key: 'backup_node_group', label: '备用分组' },
];
async function runPackageAction(
    patch: Record<string, unknown>,
    syncing = false,
) {
    if (!selectedIds.value.length || actionBusy.value) {
        return;
    }

    actionBusy.value = true;
    errorMessage.value = '';
    syncError.value = '';

    try {
        const result = await batchUpdateAdminPackages(
            selectedIds.value.map((id) => ({ id, ...patch })),
        );

        if (result.failed_count) {
            selectedIds.value = result.failed.map((row) => row.id);

            throw new Error(
                result.failed
                    .map((row) => `#${row.id}: ${row.message}`)
                    .join('；'),
            );
        }

        if (syncing) {
            syncOpen.value = false;
        }

        toast.success(syncing ? '同步成功' : '更新成功');
        await loadPackages();
    } catch (error) {
        if (syncing) {
            syncError.value = getErrorMessage(error);
        } else {
            errorMessage.value = getErrorMessage(error);
        }
    } finally {
        actionBusy.value = false;
    }
}
const bulkDeleteOpen = ref(false),
    bulkDeleteBusy = ref(false),
    bulkDeleteError = ref('');
const bulkDeleteKind = ref<'packages' | 'groups'>('packages'),
    bulkDeleteIds = ref<number[]>([]);
function askBulkDelete(kind: 'packages' | 'groups') {
    bulkDeleteKind.value = kind;
    bulkDeleteIds.value = [
        ...(kind === 'packages' ? selectedIds.value : pgSelected.value),
    ];
    bulkDeleteError.value = '';
    bulkDeleteOpen.value = true;
}
async function deleteSelected() {
    bulkDeleteBusy.value = true;
    bulkDeleteError.value = '';
    const failed: number[] = [];

    for (const id of bulkDeleteIds.value) {
        try {
            if (bulkDeleteKind.value === 'packages') {
                await deleteAdminPackage(id);
            } else {
                await deleteAdminPackageGroup(id);
            }
        } catch (error) {
            failed.push(id);
            bulkDeleteError.value = getErrorMessage(error);
        }
    }

    if (bulkDeleteKind.value === 'packages') {
        await loadPackages();
        selectedIds.value = failed;
    } else {
        await loadGroups();
        pgSelected.value = failed;
        await loadPackageOptions();
    }

    bulkDeleteIds.value = failed;
    bulkDeleteBusy.value = false;

    if (!failed.length) {
        bulkDeleteOpen.value = false;
        toast.success('删除成功');
    }
}
const pgRows = ref<CdnflyRecord[]>([]),
    pgLoading = ref(false),
    pgListError = ref(''),
    pgPage = ref(1),
    pgSize = ref(10),
    pgSelected = ref<number[]>([]);
const pgVisible = computed(() =>
    pgRows.value.slice(
        (pgPage.value - 1) * pgSize.value,
        pgPage.value * pgSize.value,
    ),
);
watch(pgSize, () => {
    pgPage.value = 1;
    pgSelected.value = [];
});
watch(pgPage, () => {
    pgSelected.value = [];
});
async function loadGroups() {
    pgLoading.value = true;
    pgListError.value = '';
    pgSelected.value = [];

    try {
        pgRows.value = extractCdnflyRows(
            await listAdminPackageGroups({ limit: 0 }),
        );
        pgPage.value = Math.min(
            pgPage.value,
            Math.max(1, Math.ceil(pgRows.value.length / pgSize.value)),
        );
    } catch (error) {
        pgListError.value = getErrorMessage(error);
    } finally {
        pgLoading.value = false;
    }
}
watch(activeTab, (value) => {
    if (value === 'groups') {
        void loadGroups();
    }
});
onMounted(() => {
    if (activeTab.value === 'groups') {
        void loadGroups();
    }
});
</script>

<template>
    <div class="packages-workspace flex flex-1 flex-col gap-4 p-4 md:p-6">
        <section
            class="console-panel rounded-xl border bg-card p-4 text-card-foreground md:p-5"
        >
            <ConsoleTabs v-model="activeTab" :tabs="packageTabs" />
            <Alert v-if="portalError" variant="destructive" class="mt-4"
                ><AlertTitle>门户商品加载失败</AlertTitle
                ><AlertDescription
                    >{{ portalError
                    }}<Button variant="link" @click="loadPortalProducts"
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <Alert v-if="errorMessage" variant="destructive" class="mt-4"
                ><AlertTitle>操作失败</AlertTitle
                ><AlertDescription>{{ errorMessage }}</AlertDescription></Alert
            >
            <Alert v-if="optionsError" variant="destructive" class="mt-4"
                ><AlertTitle>套餐选项加载失败</AlertTitle
                ><AlertDescription
                    >{{ optionsError
                    }}<Button variant="link" @click="loadPackageOptions"
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <div
                v-if="activeTab === 'packages'"
                class="mt-4"
                role="tabpanel"
                aria-label="基础套餐"
            >
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <Button
                        size="sm"
                        :disabled="
                            loadingOptions ||
                            portalLoading ||
                            !!optionsError ||
                            !!portalError
                        "
                        @click="openCreateDialog"
                        ><Plus />添加套餐</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="!selectedCount || actionBusy"
                        @click="
                            syncFields = [];
                            syncError = '';
                            syncOpen = true;
                        "
                        ><RefreshCw />同步数据</Button
                    >
                    <DropdownMenu
                        ><DropdownMenuTrigger as-child
                            ><Button size="sm" variant="outline"
                                >更多操作<ChevronDown /></Button></DropdownMenuTrigger
                        ><DropdownMenuContent>
                            <DropdownMenuItem
                                :disabled="!selectedCount || actionBusy"
                                @select="runPackageAction({ enable: 1 })"
                                >启用</DropdownMenuItem
                            >
                            <DropdownMenuItem
                                :disabled="!selectedCount || actionBusy"
                                @select="runPackageAction({ enable: 0 })"
                                >禁用</DropdownMenuItem
                            >
                            <DropdownMenuItem
                                :disabled="!selectedCount"
                                @select="openBatchDialog"
                                >批量修改</DropdownMenuItem
                            >
                            <DropdownMenuItem
                                :disabled="!selectedCount || actionBusy"
                                class="text-destructive"
                                @select="askBulkDelete('packages')"
                                >删除</DropdownMenuItem
                            >
                            <DropdownMenuSeparator /><DropdownMenuItem
                                @select="activeTab = 'upgrades'"
                                >升级包管理</DropdownMenuItem
                            >
                        </DropdownMenuContent></DropdownMenu
                    >
                    <Select v-model="groupFilter"
                        ><SelectTrigger class="w-44" aria-label="套餐分组筛选"
                            ><SelectValue
                                placeholder="所有套餐分组" /></SelectTrigger
                        ><SelectContent
                            ><SelectItem value="all">所有套餐分组</SelectItem
                            ><SelectItem
                                v-for="group in packageOptions.package_groups"
                                :key="String(group.id)"
                                :value="String(group.id)"
                                >{{ group.name }}</SelectItem
                            ></SelectContent
                        ></Select
                    >
                    <Button
                        size="sm"
                        variant="ghost"
                        class="ml-auto"
                        :disabled="loading"
                        @click="
                            loadPackages();
                            loadPortalProducts();
                        "
                        ><RefreshCw />刷新</Button
                    >
                </div>
                <ConsoleDataTable
                    embedded
                    selectable
                    title="基础套餐"
                    :columns="packageColumns"
                    :data="{
                        rows: visiblePackages,
                        total: filteredPackages.length,
                        page: listPage,
                        pageSize: listSize,
                        loading,
                    }"
                    :get-row-key="
                        (row) => getPackageId(row) ?? String(row.name)
                    "
                    :selected="selectedIds"
                    :selection-disabled="actionBusy || bulkDeleteBusy"
                    empty-text="暂无套餐数据"
                    @update:selected="selectedIds = $event.map(Number)"
                >
                    <template #cell-package="{ row }"
                        ><Button
                            variant="link"
                            class="h-auto p-0 font-medium"
                            :disabled="loadingDetailId === getPackageId(row)"
                            @click="openEditDialog(row)"
                            >{{
                                getDisplayValue(row, ['name', 'title'])
                            }}</Button
                        >
                        <p class="mt-1 text-xs text-muted-foreground">
                            ID: {{ getPackageId(row) }} / 排序:
                            {{ row.sort ?? 100 }}
                        </p></template
                    >
                    <template #cell-line="{ row }"
                        ><p>
                            {{ resourceOptionLabel('regions', row.region_id) }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                resourceOptionLabel(
                                    'node_groups',
                                    row.node_group_id,
                                )
                            }}<template v-if="Number(row.backup_node_group)"
                                >(主) /
                                {{
                                    resourceOptionLabel(
                                        'node_groups',
                                        row.backup_node_group,
                                    )
                                }}(备)</template
                            >
                        </p></template
                    >
                    <template #cell-limits="{ row }"
                        ><div class="space-y-1 text-xs text-muted-foreground">
                            <p>
                                流量/带宽/连接:
                                {{ rowLimitText(row.traffic, 'GB') }} /
                                {{ rowLimitText(row.bandwidth) }} /
                                {{ rowLimitText(row.connection) }}
                            </p>
                            <p>
                                域名/主域名: {{ rowLimitText(row.domain) }} /
                                {{ rowLimitText(row.main_domain) }}
                            </p>
                            <p>
                                HTTP/转发端口:
                                {{ rowLimitText(row.http_port) }} /
                                {{ rowLimitText(row.stream_port) }}
                            </p>
                        </div></template
                    >
                    <template #cell-capabilities="{ row }"
                        ><div class="flex flex-wrap gap-2">
                            <span
                                v-for="cap in capabilityBadges(row).slice(0, 4)"
                                :key="cap.label"
                                class="inline-flex items-center gap-1 text-xs"
                                :title="`${cap.label}：${cap.on ? '支持' : '不支持'}`"
                                ><component
                                    :is="cap.on ? CheckCircle2 : XCircle"
                                    class="size-3.5"
                                    :class="
                                        cap.on
                                            ? 'text-primary'
                                            : 'text-muted-foreground'
                                    "
                                />{{ cap.label }}</span
                            >
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            CC: {{ row.cc_protect || '不支持' }} / DDOS:
                            {{ row.ddos_protect || '不支持' }}
                        </p></template
                    >
                    <template #cell-prices="{ row }"
                        ><span class="text-xs whitespace-nowrap">{{
                            priceText(row)
                        }}</span></template
                    >
                    <template #cell-enable="{ row }"
                        ><Badge
                            :variant="
                                statusText(row) === '上架'
                                    ? 'secondary'
                                    : 'outline'
                            "
                            >{{
                                statusText(row) === '上架'
                                    ? '启用'
                                    : statusText(row) === '下架'
                                      ? '禁用'
                                      : statusText(row)
                            }}</Badge
                        ></template
                    >
                    <template #row-actions="{ row }"
                        ><Button
                            size="sm"
                            variant="ghost"
                            :disabled="loadingDetailId === getPackageId(row)"
                            @click="openEditDialog(row)"
                            >管理</Button
                        ><Button
                            size="sm"
                            variant="ghost"
                            @click="openGrantDialog(row)"
                            >分配</Button
                        ><DropdownMenu
                            ><DropdownMenuTrigger as-child
                                ><Button
                                    size="icon-sm"
                                    variant="ghost"
                                    :aria-label="`更多操作 ${getPackageId(row)}`"
                                    ><MoreHorizontal /></Button></DropdownMenuTrigger
                            ><DropdownMenuContent
                                ><DropdownMenuItem
                                    @select="openDetailDialog(row)"
                                    >查看详情</DropdownMenuItem
                                ><DropdownMenuItem
                                    class="text-destructive"
                                    @select="openDeletePackage(row)"
                                    >删除</DropdownMenuItem
                                ></DropdownMenuContent
                            ></DropdownMenu
                        ></template
                    >
                </ConsoleDataTable>
                <PackagePagination
                    v-model:page="listPage"
                    v-model:page-size="listSize"
                    :total="filteredPackages.length"
                    :disabled="loading || actionBusy"
                />
            </div>
            <div
                v-if="activeTab === 'groups'"
                class="mt-4"
                role="tabpanel"
                aria-label="套餐分组"
            >
                <div class="mb-4 flex gap-2">
                    <Button size="sm" @click="openPgCreate"
                        ><Plus />新增分组</Button
                    ><Button
                        size="sm"
                        variant="destructive"
                        :disabled="
                            !pgSelected.length || pgLoading || bulkDeleteBusy
                        "
                        @click="askBulkDelete('groups')"
                        ><Trash2 />删除</Button
                    >
                </div>
                <Alert v-if="pgListError" variant="destructive" class="mb-3"
                    ><AlertDescription
                        >{{ pgListError
                        }}<Button variant="link" @click="loadGroups"
                            >重试</Button
                        ></AlertDescription
                    ></Alert
                >
                <ConsoleDataTable
                    embedded
                    selectable
                    title="套餐分组"
                    :columns="pgColumns"
                    :data="{
                        rows: pgVisible,
                        total: pgRows.length,
                        page: pgPage,
                        pageSize: pgSize,
                        loading: pgLoading,
                    }"
                    :selected="pgSelected"
                    :get-row-key="(row) => Number(row.id)"
                    :selection-disabled="bulkDeleteBusy"
                    empty-text="暂无数据"
                    @update:selected="pgSelected = $event.map(Number)"
                >
                    <template #cell-name="{ row }"
                        ><Button
                            variant="link"
                            class="h-auto p-0"
                            @click="openPgEdit(row)"
                            >{{ row.name }}</Button
                        ></template
                    >
                    <template #cell-enable="{ row }"
                        ><Badge
                            :variant="
                                String(row.enable ?? 1) === '1'
                                    ? 'secondary'
                                    : 'outline'
                            "
                            >{{
                                String(row.enable ?? 1) === '1'
                                    ? '启用'
                                    : '禁用'
                            }}</Badge
                        ></template
                    >
                    <template #row-actions="{ row }"
                        ><Button
                            size="sm"
                            variant="ghost"
                            @click="openPgEdit(row)"
                            >编辑</Button
                        ><Button
                            size="sm"
                            variant="ghost"
                            class="text-destructive"
                            @click="openPgDelete(row)"
                            >删除</Button
                        ></template
                    >
                </ConsoleDataTable>
                <PackagePagination
                    v-model:page="pgPage"
                    v-model:page-size="pgSize"
                    :total="pgRows.length"
                    :disabled="pgLoading"
                />
            </div>
        </section>
        <Dialog v-model:open="syncOpen"
            ><DialogScrollContent
                ><DialogHeader
                    ><DialogTitle>同步数据到已售套餐</DialogTitle
                    ><DialogDescription
                        >将选中的
                        {{ selectedCount }}
                        个基础套餐的指定配置同步到已售套餐。</DialogDescription
                    ></DialogHeader
                ><Alert v-if="syncError" variant="destructive"
                    ><AlertDescription>{{ syncError }}</AlertDescription></Alert
                >
                <div class="grid grid-cols-2 gap-3">
                    <label
                        v-for="option in syncOptions"
                        :key="option.key"
                        class="flex items-center gap-2 text-sm"
                        ><Checkbox
                            :model-value="syncFields.includes(option.key)"
                            @update:model-value="
                                syncFields =
                                    $event === true
                                        ? [...syncFields, option.key]
                                        : syncFields.filter(
                                              (key) => key !== option.key,
                                          )
                            "
                        />{{ option.label }}</label
                    >
                </div>
                <DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="actionBusy"
                        @click="syncOpen = false"
                        >取消</Button
                    ><Button
                        :disabled="
                            actionBusy || !syncFields.length || !selectedCount
                        "
                        @click="
                            runPackageAction(
                                { 'sync-item': syncFields.join(',') },
                                true,
                            )
                        "
                        >同步</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <ConfirmDeleteDialog
            :open="bulkDeleteOpen"
            :description="`确认删除选中的 ${bulkDeleteIds.length} 个${bulkDeleteKind === 'packages' ? '套餐' : '分组'}？`"
            :loading="bulkDeleteBusy"
            :error="bulkDeleteError"
            @confirm="deleteSelected"
            @cancel="!bulkDeleteBusy && (bulkDeleteOpen = false)"
        />

        <Dialog v-model:open="grantDialogOpen">
            <DialogScrollContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>为用户开通套餐</DialogTitle>
                    <DialogDescription>
                        直接为用户分配当前套餐，无需用户下单或支付。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="grantError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>开通失败</AlertTitle>
                    <AlertDescription>{{ grantError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label>当前套餐</Label>
                        <div
                            class="rounded-md border bg-muted/30 px-3 py-2 text-sm"
                        >
                            <div class="font-medium">
                                {{
                                    grantPackage
                                        ? getDisplayValue(grantPackage, [
                                              'name',
                                              'title',
                                              'package_name',
                                          ])
                                        : '-'
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                ID:
                                {{
                                    grantPackage
                                        ? getPackageId(grantPackage)
                                        : '-'
                                }}
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>用户</Label>
                        <Select
                            v-model="grantForm.userId"
                            :disabled="grantUsersLoading"
                        >
                            <SelectTrigger>
                                <SelectValue
                                    :placeholder="
                                        grantUsersLoading
                                            ? '正在加载用户…'
                                            : '请选择用户'
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="user in grantUsers"
                                        :key="user.id"
                                        :value="String(user.id)"
                                    >
                                        {{ grantUserLabel(user) }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            仅显示已关联 CDNfly 账号的普通用户。
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label>开通时长</Label>
                        <Select v-model="grantForm.duration">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="duration in PACKAGE_DURATION_OPTIONS"
                                        :key="duration.value"
                                        :value="duration.value"
                                    >
                                        {{ duration.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="grant-package-name">套餐名称（可选）</Label>
                        <Input
                            id="grant-package-name"
                            v-model="grantForm.name"
                            placeholder="留空使用当前套餐名称"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="grantDialogOpen = false">
                        取消
                    </Button>
                    <Button
                        :disabled="
                            grantSaving ||
                            grantUsersLoading ||
                            !grantForm.userId
                        "
                        @click="submitGrantPackage"
                    >
                        <Spinner v-if="grantSaving" data-icon="inline-start" />
                        <PackagePlus v-else data-icon="inline-start" />
                        确认开通
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <Dialog
            :open="packageDialogOpen"
            @update:open="!saving && (packageDialogOpen = $event)"
            ><DialogScrollContent
                class="package-editor my-3 flex max-h-[calc(100dvh-24px)] w-[calc(100%-24px)] max-w-6xl flex-col gap-0 overflow-hidden p-0"
                ><DialogHeader class="shrink-0 border-b px-5 py-4"
                    ><DialogTitle>{{
                        dialogMode === 'create' ? '添加套餐' : '管理套餐'
                    }}</DialogTitle
                    ><DialogDescription class="sr-only"
                        >设置套餐资源、能力、价格和购买限制。</DialogDescription
                    ></DialogHeader
                >
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                    <Alert v-if="formError" variant="destructive"
                        ><AlertTitle>保存失败</AlertTitle
                        ><AlertDescription>{{
                            formError
                        }}</AlertDescription></Alert
                    >
                    <fieldset :disabled="saving" class="min-w-0 space-y-4">
                        <section
                            class="package-section rounded-lg border bg-card p-4"
                        >
                            <h3
                                class="mb-5 border-l-3 border-primary pl-2 font-semibold"
                            >
                                基础信息
                            </h3>
                            <div class="package-grid">
                                <div class="package-field">
                                    <Label for="package-name">名称</Label>
                                    <div class="min-w-0">
                                        <Input
                                            id="package-name"
                                            v-model="form.name"
                                            placeholder="请输入套餐名称"
                                        />
                                    </div>
                                </div>
                                <div class="package-field">
                                    <Label for="package-des">描述</Label>
                                    <div class="min-w-0">
                                        <Input
                                            id="package-des"
                                            v-model="form.des"
                                            placeholder="套餐备注"
                                        />
                                    </div>
                                </div>
                                <div class="package-field">
                                    <Label>套餐分组</Label>
                                    <div class="min-w-0">
                                        <DropdownMenu
                                            ><DropdownMenuTrigger as-child
                                                ><Button
                                                    variant="outline"
                                                    class="w-full justify-between"
                                                    aria-label="套餐分组"
                                                    >{{
                                                        formGroupLabels ||
                                                        '请选择套餐分组'
                                                    }}<ChevronDown /></Button></DropdownMenuTrigger
                                            ><DropdownMenuContent
                                                ><DropdownMenuCheckboxItem
                                                    v-for="option in packageOptions.package_groups"
                                                    :key="String(option.id)"
                                                    :model-value="
                                                        splitGroups(
                                                            form.groups,
                                                        ).includes(
                                                            String(option.id),
                                                        )
                                                    "
                                                    @update:model-value="
                                                        setPackageGroup(
                                                            String(option.id),
                                                            $event,
                                                        )
                                                    "
                                                    @select.prevent
                                                    >{{
                                                        option.name
                                                    }}</DropdownMenuCheckboxItem
                                                ></DropdownMenuContent
                                            ></DropdownMenu
                                        >
                                    </div>
                                </div>
                                <div class="package-field">
                                    <Label>区域</Label>
                                    <div class="min-w-0">
                                        <Select
                                            v-model="form.region_id"
                                            @update:model-value="
                                                changePackageRegion
                                            "
                                            :disabled="loadingOptions"
                                            ><SelectTrigger
                                                class="w-full"
                                                aria-label="区域"
                                                ><SelectValue
                                                    placeholder="请选择" /></SelectTrigger
                                            ><SelectContent
                                                ><SelectItem
                                                    v-for="option in packageOptions.regions"
                                                    :key="optionValue(option)"
                                                    :value="optionValue(option)"
                                                    >{{
                                                        optionLabel(option)
                                                    }}</SelectItem
                                                ></SelectContent
                                            ></Select
                                        >
                                    </div>
                                </div>
                                <div class="package-field">
                                    <Label>线路分组</Label>
                                    <div class="min-w-0">
                                        <Select
                                            v-model="form.node_group_id"
                                            :disabled="loadingOptions"
                                            ><SelectTrigger
                                                class="w-full"
                                                aria-label="线路分组"
                                                ><SelectValue
                                                    placeholder="请选择" /></SelectTrigger
                                            ><SelectContent
                                                ><SelectItem
                                                    v-for="option in packageLineOptions"
                                                    :key="optionValue(option)"
                                                    :value="optionValue(option)"
                                                    >{{
                                                        optionLabel(option)
                                                    }}</SelectItem
                                                ></SelectContent
                                            ></Select
                                        >
                                    </div>
                                </div>
                                <div class="package-field">
                                    <Label>备用分组</Label>
                                    <div class="min-w-0">
                                        <Select
                                            v-model="form.backup_node_group"
                                            :disabled="loadingOptions"
                                            ><SelectTrigger
                                                class="w-full"
                                                aria-label="备用分组"
                                                ><SelectValue
                                                    placeholder="请选择" /></SelectTrigger
                                            ><SelectContent
                                                ><SelectItem
                                                    :value="BACKUP_NONE_VALUE"
                                                    >无（不使用备用）</SelectItem
                                                ><SelectItem
                                                    v-for="option in packageLineOptions.filter(
                                                        (item) =>
                                                            String(item.id) !==
                                                            form.node_group_id,
                                                    )"
                                                    :key="optionValue(option)"
                                                    :value="optionValue(option)"
                                                    >{{
                                                        optionLabel(option)
                                                    }}</SelectItem
                                                ></SelectContent
                                            ></Select
                                        >
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section
                            class="package-section rounded-lg border bg-card p-4"
                        >
                            <h3
                                class="mb-5 border-l-3 border-primary pl-2 font-semibold"
                            >
                                资源限制
                            </h3>
                            <div class="package-grid">
                                <div
                                    v-for="field in orderedLimits"
                                    :key="field.key"
                                    class="package-field"
                                >
                                    <Label :for="`package-${field.key}`">{{
                                        field.label
                                    }}</Label>
                                    <div
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <Switch
                                            :aria-label="`${field.label}限制`"
                                            :checked="
                                                isLimitEnabled(form, field.key)
                                            "
                                            @update:checked="
                                                toggleLimit(form, field.key)
                                            "
                                        /><span
                                            v-if="
                                                !isLimitEnabled(form, field.key)
                                            "
                                            class="text-xs text-muted-foreground"
                                            >不限</span
                                        >
                                        <div
                                            v-else-if="
                                                field.key === 'bandwidth'
                                            "
                                            class="flex min-w-0 flex-1"
                                        >
                                            <Input
                                                id="package-bandwidth"
                                                :model-value="bandwidthAmount"
                                                @update:model-value="
                                                    updateBandwidth(
                                                        String($event),
                                                    )
                                                "
                                                inputmode="decimal"
                                                placeholder="带宽"
                                                class="min-w-0 rounded-r-none"
                                            />
                                            <Select
                                                :model-value="bandwidthUnit"
                                                @update:model-value="
                                                    updateBandwidth(
                                                        bandwidthAmount,
                                                        String($event),
                                                    )
                                                "
                                                ><SelectTrigger
                                                    aria-label="带宽单位"
                                                    class="w-24 shrink-0 rounded-l-none"
                                                    ><SelectValue /></SelectTrigger
                                                ><SelectContent
                                                    ><SelectItem value="Mbps"
                                                        >Mbps</SelectItem
                                                    ><SelectItem value="Gbps"
                                                        >Gbps</SelectItem
                                                    ></SelectContent
                                                ></Select
                                            >
                                        </div>
                                        <Input
                                            v-else
                                            :id="`package-${field.key}`"
                                            v-model="form[field.key]"
                                            :inputmode="field.inputmode"
                                            :placeholder="field.placeholder"
                                        />
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section
                            class="package-section rounded-lg border bg-card p-4"
                        >
                            <h3
                                class="mb-5 border-l-3 border-primary pl-2 font-semibold"
                            >
                                功能能力
                            </h3>
                            <div class="package-grid">
                                <div
                                    v-for="field in capabilityFields"
                                    :key="field.key"
                                    class="package-field"
                                >
                                    <Label :for="`package-${field.key}`">{{
                                        field.label
                                    }}</Label>
                                    <div class="flex items-center gap-2">
                                        <Switch
                                            :id="`package-${field.key}`"
                                            :checked="form[field.key] === '1'"
                                            @update:checked="
                                                form[field.key] = $event
                                                    ? '1'
                                                    : '0'
                                            "
                                        /><span
                                            class="text-xs text-muted-foreground"
                                            >{{
                                                form[field.key] === '1'
                                                    ? '允许'
                                                    : '禁止'
                                            }}</span
                                        >
                                    </div>
                                </div>
                                <div class="package-field">
                                    <Label for="package-cc_protect"
                                        >CC防护</Label
                                    >
                                    <div class="min-w-0">
                                        <Input
                                            id="package-cc_protect"
                                            v-model="form.cc_protect"
                                            placeholder="如填写支持"
                                        />
                                    </div>
                                </div>
                                <div class="package-field">
                                    <Label for="package-ddos_protect"
                                        >DDOS防护</Label
                                    >
                                    <div class="min-w-0">
                                        <Input
                                            id="package-ddos_protect"
                                            v-model="form.ddos_protect"
                                            placeholder="如填写100G"
                                        />
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section
                            class="package-section rounded-lg border bg-card p-4"
                        >
                            <h3
                                class="mb-5 border-l-3 border-primary pl-2 font-semibold"
                            >
                                定价
                            </h3>
                            <div class="package-grid">
                                <div
                                    v-for="field in [
                                        {
                                            key: 'month_price',
                                            portal: 'price_monthly',
                                            label: '月付',
                                        },
                                        {
                                            key: 'quarter_price',
                                            portal: 'price_quarterly',
                                            label: '季度付',
                                        },
                                        {
                                            key: 'year_price',
                                            portal: 'price_yearly',
                                            label: '年付',
                                        },
                                    ] as const"
                                    :key="field.key"
                                    class="package-field"
                                >
                                    <Label :for="`package-${field.key}`">{{
                                        field.label
                                    }}</Label>
                                    <div
                                        data-slot="console-input-group"
                                        class="flex"
                                    >
                                        <Input
                                            :id="`package-${field.key}`"
                                            v-model="form[field.key]"
                                            class="rounded-r-none"
                                            inputmode="decimal"
                                            :placeholder="
                                                field.key === 'month_price'
                                                    ? ''
                                                    : field.key ===
                                                        'quarter_price'
                                                      ? '留空 = 月付 × 3'
                                                      : '留空 = 月付 × 12'
                                            "
                                        /><span
                                            class="flex items-center rounded-r-md border border-l-0 border-input bg-muted px-2 text-xs text-muted-foreground"
                                            >{{ PORTAL_CURRENCY_LABEL }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </section>
                        <Collapsible
                            v-model:open="advancedOpen"
                            class="package-section rounded-lg border bg-card p-4"
                            ><CollapsibleTrigger
                                class="flex w-full items-center justify-between text-left font-semibold"
                                ><span class="border-l-3 border-primary pl-2"
                                    >高级配置</span
                                ><ChevronDown
                                    class="size-4"
                                    :class="{
                                        'rotate-180': advancedOpen,
                                    }" /></CollapsibleTrigger
                            ><CollapsibleContent class="mt-5 space-y-6"
                                ><div>
                                    <h4 class="mb-4 font-medium">CNAME设置</h4>
                                    <div class="package-grid">
                                        <div class="package-field">
                                            <Label for="package-cname_hostname2"
                                                >主机名</Label
                                            >
                                            <div class="min-w-0">
                                                <Input
                                                    id="package-cname_hostname2"
                                                    v-model="
                                                        form.cname_hostname2
                                                    "
                                                    placeholder="留空则使用一级域名"
                                                />
                                            </div>
                                        </div>
                                        <div class="package-field">
                                            <Label>CNAME域名</Label>
                                            <div class="min-w-0">
                                                <Select
                                                    v-model="form.cname_domain"
                                                    :disabled="loadingOptions"
                                                    ><SelectTrigger
                                                        class="w-full"
                                                        aria-label="CNAME域名"
                                                        ><SelectValue
                                                            placeholder="请选择" /></SelectTrigger
                                                    ><SelectContent
                                                        ><SelectItem
                                                            v-for="option in cnameDomainOptions"
                                                            :key="
                                                                optionValue(
                                                                    option,
                                                                )
                                                            "
                                                            :value="
                                                                optionValue(
                                                                    option,
                                                                )
                                                            "
                                                            >{{
                                                                optionLabel(
                                                                    option,
                                                                )
                                                            }}</SelectItem
                                                        ></SelectContent
                                                    ></Select
                                                >
                                            </div>
                                        </div>
                                        <div class="package-field">
                                            <Label>CNAME模式</Label>
                                            <div class="min-w-0">
                                                <Select
                                                    v-model="form.cname_mode"
                                                    ><SelectTrigger
                                                        class="w-full"
                                                        aria-label="CNAME模式"
                                                        ><SelectValue /></SelectTrigger
                                                    ><SelectContent
                                                        ><SelectItem
                                                            v-for="option in cnameModeOptions"
                                                            :key="option.value"
                                                            :value="
                                                                option.value
                                                            "
                                                            >{{
                                                                option.label
                                                            }}</SelectItem
                                                        ></SelectContent
                                                    ></Select
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="mb-4 font-medium">购买限制</h4>
                                    <div class="package-grid package-purchase">
                                        <div class="package-field">
                                            <Label for="package-buy_num_limit"
                                                >单用户购买数量</Label
                                            >
                                            <div class="min-w-0">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <Switch
                                                        aria-label="单用户购买数量限制"
                                                        :checked="
                                                            ![
                                                                '',
                                                                '-1',
                                                            ].includes(
                                                                form.buy_num_limit,
                                                            )
                                                        "
                                                        @update:checked="
                                                            form.buy_num_limit =
                                                                $event
                                                                    ? '1'
                                                                    : '-1'
                                                        "
                                                    /><span
                                                        v-if="
                                                            ['', '-1'].includes(
                                                                form.buy_num_limit,
                                                            )
                                                        "
                                                        class="text-xs text-muted-foreground"
                                                        >不限</span
                                                    ><Input
                                                        v-else
                                                        id="package-buy_num_limit"
                                                        v-model="
                                                            form.buy_num_limit
                                                        "
                                                        inputmode="numeric"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="package-field">
                                            <Label
                                                for="package-before_exp_days_renew"
                                                >套餐剩余少于N天才能续费</Label
                                            >
                                            <div class="min-w-0">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <Switch
                                                        aria-label="套餐剩余少于N天才能续费限制"
                                                        :checked="
                                                            ![
                                                                '',
                                                                '-1',
                                                            ].includes(
                                                                form.before_exp_days_renew,
                                                            )
                                                        "
                                                        @update:checked="
                                                            form.before_exp_days_renew =
                                                                $event
                                                                    ? '1'
                                                                    : '-1'
                                                        "
                                                    /><span
                                                        v-if="
                                                            ['', '-1'].includes(
                                                                form.before_exp_days_renew,
                                                            )
                                                        "
                                                        class="text-xs text-muted-foreground"
                                                        >不限</span
                                                    ><Input
                                                        v-else
                                                        id="package-before_exp_days_renew"
                                                        v-model="
                                                            form.before_exp_days_renew
                                                        "
                                                        inputmode="numeric"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="package-field">
                                            <Label for="package-expire"
                                                >有效期至</Label
                                            >
                                            <div class="min-w-0">
                                                <DatePicker
                                                    id="package-expire"
                                                    v-model="packageExpiry"
                                                    type="datetime-local"
                                                    step="1"
                                                    placeholder="留空则不限制"
                                                />
                                            </div>
                                        </div>
                                        <div class="package-field">
                                            <Label for="package-id_verify"
                                                >实名认证</Label
                                            >
                                            <div class="min-w-0">
                                                <Switch
                                                    id="package-id_verify"
                                                    :checked="
                                                        form.id_verify === '1'
                                                    "
                                                    @update:checked="
                                                        form.id_verify = $event
                                                            ? '1'
                                                            : '0'
                                                    "
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="mb-4 font-medium">其它设置</h4>
                                    <div class="package-grid">
                                        <div class="package-field">
                                            <Label for="package-owner"
                                                >分配给用户</Label
                                            >
                                            <div class="min-w-0">
                                                <Input
                                                    id="package-owner"
                                                    v-model="form.owner"
                                                    placeholder="输入用户ID，多个逗号分隔"
                                                />
                                            </div>
                                        </div>
                                        <div class="package-field">
                                            <Label for="package-sort"
                                                >排序</Label
                                            >
                                            <div class="min-w-0">
                                                <Input
                                                    id="package-sort"
                                                    v-model="form.sort"
                                                    placeholder="默认100，数字小的靠前"
                                                    inputmode="numeric"
                                                />
                                            </div>
                                        </div>
                                        <div class="package-field">
                                            <Label for="package-enable"
                                                >状态</Label
                                            >
                                            <div class="min-w-0">
                                                <Switch
                                                    id="package-enable"
                                                    :checked="
                                                        form.enable === '1'
                                                    "
                                                    @update:checked="
                                                        form.enable = $event
                                                            ? '1'
                                                            : '0'
                                                    "
                                                />
                                            </div>
                                        </div>
                                        <div class="package-field">
                                            <Label
                                                for="package-backend_ip_limit"
                                                >源IP限制</Label
                                            >
                                            <div class="min-w-0">
                                                <Textarea
                                                    id="package-backend_ip_limit"
                                                    v-model="
                                                        form.backend_ip_limit
                                                    "
                                                    rows="3"
                                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                    placeholder="一行一个IP或IP段"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <Collapsible class="rounded-md border p-3"
                                    ><CollapsibleTrigger
                                        class="text-sm text-muted-foreground"
                                        >更多字段</CollapsibleTrigger
                                    ><CollapsibleContent class="mt-3 grid gap-3"
                                        ><Label for="package-sync-item"
                                            >同步到已售套餐字段</Label
                                        ><Input
                                            id="package-sync-item"
                                            v-model="form.sync_item"
                                            placeholder="仅修改时使用，字段名用逗号分隔" /><Label
                                            for="package-extra"
                                            >扩展字段 JSON</Label
                                        ><Textarea
                                            id="package-extra"
                                            v-model="form.extra_json"
                                            class="min-h-24 rounded-md border border-input bg-background p-3 font-mono text-xs"
                                            spellcheck="false" /></CollapsibleContent></Collapsible></CollapsibleContent></Collapsible
                        ><Collapsible
                            v-model:open="salesOpen"
                            class="rounded-lg border bg-card p-4"
                            ><CollapsibleTrigger
                                class="flex w-full items-center justify-between text-left font-semibold"
                                >门户销售设置<ChevronDown
                                    class="size-4"
                                    :class="{
                                        'rotate-180': salesOpen,
                                    }" /></CollapsibleTrigger
                            ><CollapsibleContent class="mt-5 space-y-4"
                                ><label class="flex items-center gap-2 text-sm"
                                    ><Checkbox
                                        v-model="portalForm.sell"
                                    />在门户上架销售</label
                                >
                                <div
                                    v-if="portalForm.sell"
                                    class="package-grid"
                                >
                                    <div class="package-field">
                                        <Label for="portal-name"
                                            >商品名称</Label
                                        >
                                        <div class="min-w-0">
                                            <Input
                                                id="portal-name"
                                                v-model="portalForm.name"
                                                :placeholder="
                                                    form.name ||
                                                    '默认使用套餐名称'
                                                "
                                            />
                                        </div>
                                    </div>
                                    <div class="package-field">
                                        <Label for="portal-slug"
                                            >商品标识</Label
                                        >
                                        <div class="min-w-0">
                                            <Input
                                                id="portal-slug"
                                                v-model="portalForm.slug"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    上架时使用上方定价，季度付和年付留空分别按月付的3倍和12倍计算。
                                </p>
                                <Label for="portal-features"
                                    >营销卖点（每行一条）</Label
                                ><Textarea
                                    id="portal-features"
                                    v-model="portalForm.features"
                                    rows="3"
                                    class="w-full rounded-md border border-input bg-background p-3 text-sm"
                                />
                                <div
                                    v-if="dialogMode === 'create'"
                                    class="flex flex-wrap gap-2"
                                >
                                    <span class="text-sm">套餐预设</span
                                    ><Button
                                        v-for="preset in TIER_PRESETS"
                                        :key="preset.key"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="applyTierPreset(preset)"
                                        >{{ preset.label }}</Button
                                    >
                                </div></CollapsibleContent
                            ></Collapsible
                        >
                    </fieldset>
                </div>
                <DialogFooter class="shrink-0 border-t bg-background px-5 py-4"
                    ><Button
                        :disabled="saving || portalLoading || !!portalError"
                        @click="submitPackage"
                        ><Spinner v-if="saving" />确定</Button
                    ><Button
                        variant="outline"
                        :disabled="saving"
                        @click="packageDialogOpen = false"
                        >取消</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <Dialog v-model:open="batchDialogOpen">
            <DialogScrollContent class="max-w-4xl">
                <DialogHeader>
                    <DialogTitle>批量修改基础套餐</DialogTitle>
                    <DialogDescription>
                        当前选择
                        {{ selectedCount }} 个套餐，只提交填写过的字段。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="batchError" variant="destructive">
                    <AlertCircle />
                    <AlertTitle>批量修改失败</AlertTitle>
                    <AlertDescription>{{ batchError }}</AlertDescription>
                </Alert>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="flex flex-col gap-2">
                        <Label>套餐启用状态</Label>
                        <Select v-model="batchForm.enable">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="不修改" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="SELECT_KEEP_VALUE">
                                        不修改
                                    </SelectItem>
                                    <SelectItem value="1">启用</SelectItem>
                                    <SelectItem value="0">禁用</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-month-price">月付价格（USDT）</Label>
                        <Input
                            id="batch-month-price"
                            v-model="batchForm.month_price"
                            inputmode="decimal"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-quarter-price"
                            >季付价格（USDT）</Label
                        >
                        <Input
                            id="batch-quarter-price"
                            v-model="batchForm.quarter_price"
                            inputmode="decimal"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-year-price">年付价格（USDT）</Label>
                        <Input
                            id="batch-year-price"
                            v-model="batchForm.year_price"
                            inputmode="decimal"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-traffic">月流量（G）</Label>
                        <Input
                            id="batch-traffic"
                            v-model="batchForm.traffic"
                            inputmode="decimal"
                            placeholder="-1 表示不限制"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-bandwidth">带宽</Label>
                        <Input
                            id="batch-bandwidth"
                            v-model="batchForm.bandwidth"
                            placeholder="如 100Mbps / 1Gbps，-1 不限制"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-connection">连接数</Label>
                        <Input
                            id="batch-connection"
                            v-model="batchForm.connection"
                            inputmode="numeric"
                            placeholder="-1 表示不限制"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-domain">域名数</Label>
                        <Input
                            id="batch-domain"
                            v-model="batchForm.domain"
                            inputmode="numeric"
                            placeholder="-1 表示不限制"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-main-domain">主域名数</Label>
                        <Input
                            id="batch-main-domain"
                            v-model="batchForm.main_domain"
                            inputmode="numeric"
                            placeholder="-1 表示不限制"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-http-port">网站非标端口数</Label>
                        <Input
                            id="batch-http-port"
                            v-model="batchForm.http_port"
                            inputmode="numeric"
                            placeholder="-1 表示不限制"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-stream-port">四层端口数</Label>
                        <Input
                            id="batch-stream-port"
                            v-model="batchForm.stream_port"
                            inputmode="numeric"
                            placeholder="-1 表示不限制"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>自定义CC规则</Label>
                        <Select v-model="batchForm.custom_cc_rule">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="不修改" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="SELECT_KEEP_VALUE">
                                        不修改
                                    </SelectItem>
                                    <SelectItem value="1">支持</SelectItem>
                                    <SelectItem value="0">不支持</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>CC 防护</Label>
                        <Select v-model="batchForm.cc_protect">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="不修改" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="SELECT_KEEP_VALUE">
                                        不修改
                                    </SelectItem>
                                    <SelectItem value="支持">支持</SelectItem>
                                    <SelectItem value="不支持"
                                        >不支持</SelectItem
                                    >
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>DDoS 防护</Label>
                        <Select v-model="batchForm.ddos_protect">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="不修改" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="SELECT_KEEP_VALUE">
                                        不修改
                                    </SelectItem>
                                    <SelectItem value="支持">支持</SelectItem>
                                    <SelectItem value="不支持"
                                        >不支持</SelectItem
                                    >
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>WebSocket</Label>
                        <Select v-model="batchForm.websocket">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="不修改" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="SELECT_KEEP_VALUE">
                                        不修改
                                    </SelectItem>
                                    <SelectItem value="1">支持</SelectItem>
                                    <SelectItem value="0">不支持</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>HTTP3</Label>
                        <Select v-model="batchForm.http3">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="不修改" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="SELECT_KEEP_VALUE">
                                        不修改
                                    </SelectItem>
                                    <SelectItem value="1">支持</SelectItem>
                                    <SelectItem value="0">不支持</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>L2 节点回源</Label>
                        <Select v-model="batchForm.l2_state">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="不修改" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="SELECT_KEEP_VALUE">
                                        不修改
                                    </SelectItem>
                                    <SelectItem value="1">启用</SelectItem>
                                    <SelectItem value="0">禁用</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-sort">排序</Label>
                        <Input
                            id="batch-sort"
                            v-model="batchForm.sort"
                            inputmode="numeric"
                        />
                    </div>
                    <Collapsible
                        v-model:open="batchAdvancedOpen"
                        class="rounded-lg border md:col-span-3"
                    >
                        <CollapsibleTrigger
                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                        >
                            <div class="font-medium">更多批量字段</div>
                            <ChevronDown
                                class="size-4 shrink-0 transition-transform"
                                :class="{ 'rotate-180': batchAdvancedOpen }"
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent class="border-t p-4">
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="flex flex-col gap-2">
                                    <Label>CNAME 域名</Label>
                                    <Select
                                        v-model="batchForm.cname_domain"
                                        :disabled="loadingOptions"
                                    >
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="不修改" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem
                                                    :value="SELECT_KEEP_VALUE"
                                                >
                                                    不修改
                                                </SelectItem>
                                                <SelectItem
                                                    v-for="option in cnameDomainOptions"
                                                    :key="optionValue(option)"
                                                    :value="optionValue(option)"
                                                >
                                                    {{ optionLabel(option) }}
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="batch-cname-hostname">
                                        CNAME 主机名
                                    </Label>
                                    <Input
                                        id="batch-cname-hostname"
                                        v-model="batchForm.cname_hostname2"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label>CNAME 模式</Label>
                                    <Select v-model="batchForm.cname_mode">
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="不修改" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem
                                                    :value="SELECT_KEEP_VALUE"
                                                >
                                                    不修改
                                                </SelectItem>
                                                <SelectItem
                                                    v-for="option in cnameModeOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="batch-buy-num-limit">
                                        单用户购买数量
                                    </Label>
                                    <Input
                                        id="batch-buy-num-limit"
                                        v-model="batchForm.buy_num_limit"
                                        inputmode="numeric"
                                        placeholder="-1 表示不限制"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="batch-expire">有效期至</Label>
                                    <Input
                                        id="batch-expire"
                                        v-model="batchForm.expire"
                                        placeholder="2026-12-31 23:59:59，留空不限"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="batch-owner">分配给用户</Label>
                                    <Input
                                        id="batch-owner"
                                        v-model="batchForm.owner"
                                        placeholder="用户 ID，多个用逗号分隔"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label>实名认证</Label>
                                    <Select v-model="batchForm.id_verify">
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="不修改" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem
                                                    :value="SELECT_KEEP_VALUE"
                                                >
                                                    不修改
                                                </SelectItem>
                                                <SelectItem value="1">
                                                    需要
                                                </SelectItem>
                                                <SelectItem value="0">
                                                    不需要
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label>备用线路组</Label>
                                    <Select
                                        v-model="batchForm.backup_node_group"
                                        :disabled="loadingOptions"
                                    >
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="不修改" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem
                                                    :value="SELECT_KEEP_VALUE"
                                                >
                                                    不修改
                                                </SelectItem>
                                                <SelectItem
                                                    :value="BACKUP_NONE_VALUE"
                                                >
                                                    无（不使用备用）
                                                </SelectItem>
                                                <SelectItem
                                                    v-for="option in packageOptions.node_groups"
                                                    :key="optionValue(option)"
                                                    :value="optionValue(option)"
                                                >
                                                    {{ optionLabel(option) }}
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="batch-before-renew">
                                        提前续费天数
                                    </Label>
                                    <Input
                                        id="batch-before-renew"
                                        v-model="
                                            batchForm.before_exp_days_renew
                                        "
                                        inputmode="numeric"
                                    />
                                </div>
                                <div class="flex flex-col gap-2 md:col-span-3">
                                    <Label for="batch-sync-item">
                                        同步到已售套餐字段
                                    </Label>
                                    <Input
                                        id="batch-sync-item"
                                        v-model="batchForm.sync_item"
                                        placeholder="字段名用逗号分隔"
                                    />
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                    <div class="flex flex-col gap-2 md:col-span-3">
                        <Label for="batch-extra">扩展字段 JSON</Label>
                        <Textarea
                            id="batch-extra"
                            v-model="batchForm.extra_json"
                            class="min-h-24 rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            spellcheck="false"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="batchDialogOpen = false">
                        取消
                    </Button>
                    <Button
                        :disabled="saving || selectedCount === 0"
                        @click="submitBatchUpdate"
                    >
                        <Spinner v-if="saving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        批量保存
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <Dialog v-model:open="detailDialogOpen">
            <DialogScrollContent class="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>基础套餐详情</DialogTitle>
                    <DialogDescription>
                        套餐的限制、功能与对外售价。
                    </DialogDescription>
                </DialogHeader>

                <div class="grid max-h-[560px] gap-4 overflow-auto pr-1">
                    <!--
                        What the package is sold for is the first thing an
                        operator wants and the one thing the CDNfly payload
                        cannot answer, so it leads.
                    -->
                    <div
                        v-if="detailProduct"
                        class="rounded-lg border border-primary/30 bg-primary/5 p-4"
                    >
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <div class="text-sm font-medium">门户售价</div>
                            <Badge
                                :variant="
                                    detailProduct.is_active
                                        ? 'secondary'
                                        : 'outline'
                                "
                            >
                                {{
                                    detailProduct.is_active
                                        ? '已上架'
                                        : '已下架'
                                }}
                            </Badge>
                        </div>
                        <div
                            class="mt-2 flex flex-wrap items-baseline gap-x-6 gap-y-1"
                        >
                            <span class="text-2xl font-semibold">
                                {{ detailProduct.price_monthly }}
                                {{ PORTAL_CURRENCY_LABEL }}
                                <span
                                    class="text-sm font-normal text-muted-foreground"
                                >
                                    / 月
                                </span>
                            </span>
                            <span class="text-sm text-muted-foreground">
                                季付 {{ detailProduct.price_quarterly }} · 年付
                                {{ detailProduct.price_yearly }}
                            </span>
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            商品：{{ detailProduct.name }}（{{
                                detailProduct.slug
                            }}）
                        </div>
                    </div>

                    <Alert v-else>
                        <AlertCircle />
                        <AlertTitle>尚未在门户上架</AlertTitle>
                        <AlertDescription>
                            该套餐没有对应的门户商品，客户无法下单购买。
                            点击「修改」并填写门户售价即可上架。
                        </AlertDescription>
                    </Alert>

                    <div
                        v-for="section in detailSections"
                        :key="section.title"
                        class="rounded-lg border"
                    >
                        <div class="border-b px-4 py-2 text-sm font-medium">
                            {{ section.title }}
                        </div>
                        <dl class="grid gap-x-6 gap-y-2 p-4 sm:grid-cols-2">
                            <div
                                v-for="row in section.rows"
                                :key="row.label"
                                class="flex items-baseline justify-between gap-3 border-b border-dashed border-border/60 pb-1 last:border-0"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    {{ row.label }}
                                </dt>
                                <dd class="text-sm">{{ row.value }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!--
                        Kept, collapsed: when CDNfly returns a field this view
                        does not model, the raw payload is the only way to see
                        it.
                    -->
                    <Collapsible
                        v-model:open="detailJsonOpen"
                        class="rounded-lg border"
                    >
                        <CollapsibleTrigger
                            class="flex w-full items-center justify-between gap-3 px-4 py-2 text-left text-sm font-medium"
                        >
                            原始数据
                            <ChevronDown
                                class="size-4 shrink-0 transition-transform"
                                :class="detailJsonOpen ? 'rotate-180' : ''"
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <pre
                                class="max-h-80 overflow-auto border-t bg-muted p-4 text-xs text-muted-foreground"
                                >{{ detailJson }}</pre
                            >
                        </CollapsibleContent>
                    </Collapsible>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="detailDialogOpen = false">
                        关闭
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <ConfirmDeleteDialog
            :open="deleteOpen"
            :description="`确认删除基础套餐「${deleteTargetName}」？删除后不可恢复。`"
            :loading="deleting"
            :error="deleteError"
            @confirm="confirmDeletePackage"
            @cancel="deleteOpen = false"
        />

        <!-- Package Group create/edit dialog -->
        <Dialog v-model:open="pgDialogOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        pgEditing ? '编辑分组' : '新增分组'
                    }}</DialogTitle>
                    <DialogDescription
                        >设置分组名称、备注、排序和启用状态。</DialogDescription
                    >
                </DialogHeader>
                <Alert v-if="pgError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>提交失败</AlertTitle>
                    <AlertDescription>{{ pgError }}</AlertDescription>
                </Alert>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="pg-name">名称</Label>
                        <Input
                            id="pg-name"
                            v-model="pgForm.name"
                            placeholder="套餐组名称"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="pg-des">备注</Label>
                        <Input
                            id="pg-des"
                            v-model="pgForm.des"
                            placeholder="可选备注"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="pg-sort">排序</Label
                        ><Input
                            id="pg-sort"
                            v-model="pgForm.sort"
                            type="number"
                        />
                    </div>
                    <div class="flex items-center gap-3">
                        <Label for="pg-enable">启用</Label
                        ><Switch
                            id="pg-enable"
                            :checked="pgForm.enable === '1'"
                            @update:checked="pgForm.enable = $event ? '1' : '0'"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="pgDialogOpen = false"
                        >取消</Button
                    >
                    <Button :disabled="pgSaving" @click="submitPg">
                        <Spinner v-if="pgSaving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        保存
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- Package Group delete confirm -->
        <Dialog v-model:open="pgDeleteOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>确认删除</DialogTitle>
                    <DialogDescription>
                        确定要删除套餐组「{{ pgDeleteTarget?.name }}」吗？
                    </DialogDescription>
                </DialogHeader>
                <Alert v-if="pgDeleteError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>删除失败</AlertTitle>
                    <AlertDescription>{{ pgDeleteError }}</AlertDescription>
                </Alert>
                <DialogFooter>
                    <Button variant="outline" @click="pgDeleteOpen = false"
                        >取消</Button
                    >
                    <Button
                        variant="destructive"
                        :disabled="pgDeleting"
                        @click="confirmPgDelete"
                    >
                        <Spinner v-if="pgDeleting" data-icon="inline-start" />
                        <Trash2 v-else data-icon="inline-start" />
                        确认删除
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <AdminPackageUpgrades v-if="activeTab === 'upgrades'" embedded />
    </div>
</template>

<style scoped>
.package-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px 28px;
}
.package-purchase > :nth-child(1) {
    order: 1;
}
.package-purchase > :nth-child(2) {
    order: 4;
}
.package-purchase > :nth-child(3) {
    order: 2;
}
.package-purchase > :nth-child(4) {
    order: 3;
}
.package-field {
    display: grid;
    grid-template-columns: 130px minmax(0, 1fr);
    align-items: center;
    gap: 12px;
}
.package-field > label {
    justify-content: flex-end;
    text-align: right;
    line-height: 1.5;
}
@media (max-width: 760px) {
    .package-grid {
        grid-template-columns: 1fr;
    }
    .package-field {
        grid-template-columns: 110px minmax(0, 1fr);
    }
}
@media (max-width: 430px) {
    .package-field {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .package-field > label {
        justify-content: flex-start;
        text-align: left;
    }
}
</style>
