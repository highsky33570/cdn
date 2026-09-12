<script setup lang="ts">
import {
    AlertCircle,
    ChevronDown,
    Eye,
    Layers3,
    Pencil,
    Plus,
    RefreshCw,
    Save,
    Trash2,
} from 'lucide-vue-next';
import { FolderTree, Package, Zap } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import type { ConsoleTab } from '@/components/console/ConsoleTabs.vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import {
    listAdminPackageGroups,
    createAdminPackageGroup,
    updateAdminPackageGroup,
    deleteAdminPackageGroup,
    listAdminPackageUps,
    createAdminPackageUp,
    updateAdminPackageUp,
    deleteAdminPackageUp,
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
import { formatDate, getErrorMessage as fmtError } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';

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
const PORTAL_CURRENCY = 'USD';
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
type PackageTab = 'packages' | 'groups' | 'upgrades';

const activeTab = ref<PackageTab>('packages');

const packageTabs: ConsoleTab[] = [
    { key: 'packages', label: '基础套餐', icon: Package },
    { key: 'groups', label: '套餐组', icon: FolderTree },
    { key: 'upgrades', label: '升级包', icon: Zap },
];

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
/** CDNfly internal prices stay collapsed: 0 is the right answer. */
const cdnflyPriceOpen = ref(false);

const portalProducts = ref<Record<string, AdminPackageProduct>>({});

const portalForm = reactive({
    sell: true,
    name: '',
    slug: '',
    price_monthly: '',
    price_quarterly: '',
    price_yearly: '',
    description: '',
    features: '',
    sort_order: '',
    is_active: true,
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
    // One node means no L2 tier to fall back to.
    form.l2_state = '0';
    // Real-name verification blocks checkout unless you actually police it.
    form.id_verify = '0';
    // A plain VPS has no scrubbing in front of it.
    form.ddos_protect = '不支持';

    // CDNfly bills these against the customer's CDNfly balance, which a portal
    // order never credits.
    form.month_price = '0';
    form.quarter_price = '0';
    form.year_price = '0';

    portalForm.sell = true;
    portalForm.name = preset.name;
    portalForm.slug = preset.slug;
    portalForm.price_monthly = preset.price;
    portalForm.price_quarterly = '';
    portalForm.price_yearly = '';
    portalForm.description = preset.description;
    portalForm.features = preset.features.join('\n');
    portalForm.is_active = true;
    portalForm.sort_order = String(
        (TIER_PRESETS.findIndex((tier) => tier.key === preset.key) + 1) * 10,
    );
}

function resetPortalForm(
    packageName = '',
    existing?: AdminPackageProduct,
): void {
    portalForm.sell = true;
    portalForm.name = existing?.name ?? packageName;
    // Pre-filled from the linked product so a price-only edit re-sends the slug
    // it already has and cannot move the tier.
    portalForm.slug = existing?.slug ?? '';
    portalForm.price_monthly = existing ? String(existing.price_monthly) : '';
    portalForm.price_quarterly = existing
        ? String(existing.price_quarterly)
        : '';
    portalForm.price_yearly = existing ? String(existing.price_yearly) : '';
    portalForm.description = existing?.description ?? '';
    portalForm.features = (existing?.features ?? []).join('\n');
    portalForm.sort_order = existing ? String(existing.sort_order) : '';
    portalForm.is_active = existing?.is_active ?? true;
}

/** Preview of what a blank quarterly/yearly price will become. */
const portalDerived = computed(() => {
    const monthly = Number(portalForm.price_monthly);

    if (!Number.isFinite(monthly) || monthly <= 0) {
        return null;
    }

    return {
        quarterly: (monthly * 3).toFixed(2),
        yearly: (monthly * 12).toFixed(2),
    };
});

function portalPayload(): Record<string, unknown> {
    return {
        name: portalForm.name.trim(),
        slug: portalForm.slug.trim(),
        price_monthly: Number(portalForm.price_monthly) || 0,
        price_quarterly:
            portalForm.price_quarterly === ''
                ? null
                : Number(portalForm.price_quarterly),
        price_yearly:
            portalForm.price_yearly === ''
                ? null
                : Number(portalForm.price_yearly),
        description: portalForm.description.trim() || null,
        features: portalForm.features
            .split('\n')
            .map((line) => line.trim())
            .filter((line) => line !== ''),
        sort_order:
            portalForm.sort_order === '' ? 0 : Number(portalForm.sort_order),
        is_active: portalForm.is_active,
        currency: PORTAL_CURRENCY,
    };
}

async function loadPortalProducts(): Promise<void> {
    try {
        portalProducts.value = await listAdminPackageProducts();
    } catch {
        // A pricing lookup must never block the package list; the column just
        // shows 未上架 until it succeeds.
        portalProducts.value = {};
    }
}

const editingId = ref<number | null>(null);
const detailRecord = ref<PackageRecord | null>(null);
const packageCnameOpen = ref(false);
const packagePurchaseLimitOpen = ref(false);
const packageOtherOpen = ref(false);
const batchAdvancedOpen = ref(false);
const packageOptions = ref<AdminPackageOptions>({ ...emptyOptions });
const loadingOptions = ref(false);

const form = reactive<PackageForm>(emptyPackageForm());
const batchForm = reactive<PackageForm>(emptyPackageForm(true));

const packageIds = computed(() =>
    packages.value
        .map((record) => getPackageId(record))
        .filter((id): id is number => id !== null),
);

const allVisibleSelected = computed(
    () =>
        packageIds.value.length > 0 &&
        packageIds.value.every((id) => selectedIds.value.includes(id)),
);

const enabledCount = computed(
    () =>
        packages.value.filter((record) =>
            ['1', 'true', 'active', 'enable', 'enabled', '上架'].includes(
                normalizeStatus(record),
            ),
        ).length,
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
                { label: '可购买截止时间', value: plainText('expire') },
                // These are CDNfly's internal prices, billed against the
                // customer's CDNfly balance. 0 is correct for portal selling.
                { label: 'CDNfly 月付', value: plainText('month_price') },
                { label: 'CDNfly 季付', value: plainText('quarter_price') },
                { label: 'CDNfly 年付', value: plainText('year_price') },
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
function portalPriceOf(record: PackageRecord): string {
    const id = getPackageId(record);

    if (id === null) {
        return '';
    }

    const product = portalProducts.value[String(id)];

    if (!product) {
        return '';
    }

    return `${product.currency} ${product.price_monthly}`;
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
        const payload = await listAdminPackages();
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
    Object.assign(form, emptyPackageForm());
    form.cname_domain = defaultCnameDomainId();
    resetPortalForm();
    formError.value = '';
    editingId.value = null;
    dialogMode.value = 'create';
    closePackageAdvancedSections();
    packageDialogOpen.value = true;
}

async function openEditDialog(record: PackageRecord): Promise<void> {
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
        resetPortalForm(
            String(detail.name ?? ''),
            portalProducts.value[String(id)],
        );
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

async function submitPackage(): Promise<void> {
    saving.value = true;
    formError.value = '';

    try {
        const payload = buildPackagePayload(
            form,
            dialogMode.value === 'create',
        );

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

            if (portalForm.sell) {
                await saveAdminPackageProduct(editingId.value, portalPayload());
            }
        }

        packageDialogOpen.value = false;
        await Promise.all([loadPackages(), loadPortalProducts()]);
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

async function submitBatchUpdate(): Promise<void> {
    saving.value = true;
    batchError.value = '';

    try {
        const payload = buildPackagePayload(batchForm, false);
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

function toggleAll(checked: boolean | 'indeterminate'): void {
    selectedIds.value = checked === true ? [...packageIds.value] : [];
}

function toggleOne(id: number, checked: boolean | 'indeterminate'): void {
    if (checked === true) {
        selectedIds.value = Array.from(new Set([...selectedIds.value, id]));

        return;
    }

    selectedIds.value = selectedIds.value.filter((item) => item !== id);
}

function recordSelected(record: PackageRecord): boolean {
    const id = getPackageId(record);

    return id !== null && selectedIds.value.includes(id);
}

function toggleRecord(
    record: PackageRecord,
    checked: boolean | 'indeterminate',
): void {
    const id = getPackageId(record);

    if (id !== null) {
        toggleOne(id, checked);
    }
}

function openBatchDialog(): void {
    Object.assign(batchForm, emptyPackageForm(true));
    batchError.value = '';
    batchAdvancedOpen.value = false;
    batchDialogOpen.value = true;
}

function closePackageAdvancedSections(): void {
    cdnflyPriceOpen.value = false;
    packageCnameOpen.value = false;
    packagePurchaseLimitOpen.value = false;
    packageOtherOpen.value = false;
}

function isLimitEnabled(source: PackageForm, key: LimitFieldKey): boolean {
    return source[key] !== '-1';
}

function toggleLimit(source: PackageForm, key: LimitFieldKey): void {
    if (isLimitEnabled(source, key)) {
        source[key] = '-1';

        return;
    }

    source[key] = '';
}

function emptyPackageForm(batch = false): PackageForm {
    return {
        name: '',
        des: '',
        region_id: '',
        node_group_id: '',
        backup_node_group: '',
        groups: '',
        month_price: batch ? '' : '0',
        quarter_price: batch ? '' : '0',
        year_price: batch ? '' : '0',
        traffic: batch ? '' : '-1',
        bandwidth: batch ? '' : '-1',
        connection: batch ? '' : '-1',
        domain: batch ? '' : '-1',
        main_domain: batch ? '' : '-1',
        http_port: batch ? '' : '-1',
        stream_port: batch ? '' : '-1',
        custom_cc_rule: batch ? SELECT_KEEP_VALUE : '1',
        cc_protect: batch ? SELECT_KEEP_VALUE : '支持',
        ddos_protect: '',
        websocket: batch ? SELECT_KEEP_VALUE : '1',
        http3: batch ? SELECT_KEEP_VALUE : '1',
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
        backup_node_group: getDisplayValue(record, ['backup_node_group'], ''),
        groups: getDisplayValue(record, ['groups', 'group'], ''),
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
        l2_state: getDisplayValue(record, ['l2_state'], '0'),
        id_verify: getDisplayValue(record, ['id_verify'], '0'),
        cname_domain:
            getDisplayValue(record, ['cname_domain'], '') ||
            CNAME_DEFAULT_VALUE,
        cname_hostname2: getDisplayValue(record, ['cname_hostname2'], ''),
        cname_mode: getDisplayValue(record, ['cname_mode'], ''),
        buy_num_limit: getDisplayValue(record, ['buy_num_limit'], '-1'),
        expire: getDisplayValue(record, ['expire', 'expire2'], ''),
        before_exp_days_renew: getDisplayValue(
            record,
            ['before_exp_days_renew'],
            '',
        ),
        owner: getDisplayValue(record, ['owner'], ''),
        enable: getDisplayValue(record, ['enable', 'status', 'state'], '1'),
        sort: getDisplayValue(record, ['sort', 'order'], ''),
        sync_item: '',
        extra_json: '{}',
    };
}

function buildPackagePayload(
    source: PackageForm,
    requireName: boolean,
): AdminPackagePayload {
    const payload: AdminPackagePayload = {};
    const name = source.name.trim();

    if (requireName && name === '') {
        throw new Error('请输入套餐名称');
    }

    appendText(payload, 'name', name);
    appendText(payload, 'des', source.des.trim());
    appendNumber(payload, 'region_id', source.region_id, '区域 ID');
    appendNumber(payload, 'node_group_id', source.node_group_id, '线路组 ID');
    appendText(payload, 'backup_node_group', source.backup_node_group.trim());
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
    appendText(payload, 'ddos_protect', source.ddos_protect.trim());
    appendSelectBoolean(payload, 'websocket', source.websocket);
    appendSelectBoolean(payload, 'http3', source.http3);
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

    if (Number.isNaN(numberValue)) {
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
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'name', label: '名称' },
    { key: 'des', label: '备注', format: (v) => String(v ?? '-') },
    {
        key: 'created_at',
        label: '创建时间',
        width: '160px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];

const pgTableRef = ref<InstanceType<typeof ConsoleDataTable> | null>(null);
const pgDialogOpen = ref(false);
const pgSaving = ref(false);
const pgError = ref('');
const pgEditing = ref<CdnflyRecord | null>(null);
const pgForm = reactive({ name: '', des: '' });
const pgDeleteOpen = ref(false);
const pgDeleteTarget = ref<CdnflyRecord | null>(null);
const pgDeleting = ref(false);
const pgDeleteError = ref('');

function openPgCreate(): void {
    pgEditing.value = null;
    pgForm.name = '';
    pgForm.des = '';
    pgError.value = '';
    pgDialogOpen.value = true;
}

function openPgEdit(row: CdnflyRecord): void {
    pgEditing.value = row;
    pgForm.name = String(row.name ?? '');
    pgForm.des = String(row.des ?? '');
    pgError.value = '';
    pgDialogOpen.value = true;
}

async function submitPg(): Promise<void> {
    pgSaving.value = true;
    pgError.value = '';

    try {
        const payload: Record<string, unknown> = {
            name: pgForm.name.trim(),
            des: pgForm.des.trim() || undefined,
        };

        if (pgEditing.value) {
            await updateAdminPackageGroup(Number(pgEditing.value.id), payload);
            toast.success('套餐组已更新');
        } else {
            await createAdminPackageGroup(payload);
            toast.success('套餐组已创建');
        }

        pgDialogOpen.value = false;
        pgTableRef.value?.refresh();
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
        pgTableRef.value?.refresh();
        void loadPackageOptions();
    } catch (error) {
        pgDeleteError.value = fmtError(error);
    } finally {
        pgDeleting.value = false;
    }
}

// ─── Package Ups CRUD ────────────────────────────────────
const puColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'name', label: '名称' },
    {
        key: 'groups',
        label: '套餐组',
        width: '100px',
        format: (v) => String(v ?? '-'),
    },
    {
        key: 'month_price',
        label: '月价格',
        width: '100px',
        format: (v) => String(v ?? '-'),
    },
    {
        key: 'created_at',
        label: '创建时间',
        width: '160px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];

const puTableRef = ref<InstanceType<typeof ConsoleDataTable> | null>(null);
const puDialogOpen = ref(false);
const puSaving = ref(false);
const puError = ref('');
const puEditing = ref<CdnflyRecord | null>(null);
const puForm = reactive({
    name: '',
    des: '',
    groups: '',
    month_price: '',
    quarter_price: '',
    year_price: '',
    traffic: '',
    bandwidth: '',
    connection: '',
    domain: '',
    extra_json: '{}',
});
const puDeleteOpen = ref(false);
const puDeleteTarget = ref<CdnflyRecord | null>(null);
const puDeleting = ref(false);
const puDeleteError = ref('');

function openPuCreate(): void {
    puEditing.value = null;
    Object.assign(puForm, {
        name: '',
        des: '',
        groups: '',
        month_price: '',
        quarter_price: '',
        year_price: '',
        traffic: '',
        bandwidth: '',
        connection: '',
        domain: '',
        extra_json: '{}',
    });
    puError.value = '';
    puDialogOpen.value = true;
}

function openPuEdit(row: CdnflyRecord): void {
    puEditing.value = row;
    puForm.name = String(row.name ?? '');
    puForm.des = String(row.des ?? '');
    puForm.groups = String(row.groups ?? '');
    puForm.month_price = String(row.month_price ?? '');
    puForm.quarter_price = String(row.quarter_price ?? '');
    puForm.year_price = String(row.year_price ?? '');
    puForm.traffic = String(row.traffic ?? '');
    puForm.bandwidth = String(row.bandwidth ?? '');
    puForm.connection = String(row.connection ?? '');
    puForm.domain = String(row.domain ?? '');
    puForm.extra_json = '{}';
    puError.value = '';
    puDialogOpen.value = true;
}

function buildPuPayload(): Record<string, unknown> {
    const p: Record<string, unknown> = { name: puForm.name.trim() };

    if (puForm.des.trim()) {
        p.des = puForm.des.trim();
    }

    if (puForm.groups.trim()) {
        p.groups = puForm.groups.trim();
    }

    for (const k of [
        'month_price',
        'quarter_price',
        'year_price',
        'traffic',
        'connection',
        'domain',
    ] as const) {
        const v = puForm[k].trim();

        if (v !== '') {
            p[k] = Number(v);
        }
    }

    if (puForm.bandwidth.trim()) {
        p.bandwidth = puForm.bandwidth.trim();
    }

    const extra = puForm.extra_json.trim();

    if (extra && extra !== '{}') {
        Object.assign(p, JSON.parse(extra));
    }

    return p;
}

async function submitPu(): Promise<void> {
    puSaving.value = true;
    puError.value = '';

    try {
        const payload = buildPuPayload();

        if (puEditing.value) {
            await updateAdminPackageUp(Number(puEditing.value.id), payload);
            toast.success('升级包已更新');
        } else {
            await createAdminPackageUp(payload);
            toast.success('升级包已创建');
        }

        puDialogOpen.value = false;
        puTableRef.value?.refresh();
    } catch (error) {
        puError.value = fmtError(error);
    } finally {
        puSaving.value = false;
    }
}

function openPuDelete(row: CdnflyRecord): void {
    puDeleteTarget.value = row;
    puDeleteError.value = '';
    puDeleteOpen.value = true;
}

async function confirmPuDelete(): Promise<void> {
    if (!puDeleteTarget.value) {
        return;
    }

    puDeleting.value = true;
    puDeleteError.value = '';

    try {
        await deleteAdminPackageUp(Number(puDeleteTarget.value.id));
        puDeleteOpen.value = false;
        toast.success('升级包已删除');
        puTableRef.value?.refresh();
    } catch (error) {
        puDeleteError.value = fmtError(error);
    } finally {
        puDeleting.value = false;
    }
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="套餐管理"
            description="基础套餐直接对接 CDNfly 套餐接口。"
            :show-api-badge="false"
        >
            <template #default />
        </ConsolePageHeader>

        <div class="flex flex-wrap gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="loading"
                @click="loadPackages"
            >
                <RefreshCw data-icon="inline-start" />
                刷新
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="selectedCount === 0"
                @click="openBatchDialog"
            >
                <Layers3 data-icon="inline-start" />
                批量修改
            </Button>
            <Button size="sm" @click="openCreateDialog">
                <Plus data-icon="inline-start" />
                新增基础套餐
            </Button>
        </div>

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle />
            <AlertTitle>套餐接口请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <Alert v-if="optionsError" variant="destructive">
            <AlertCircle />
            <AlertTitle>套餐选项加载失败</AlertTitle>
            <AlertDescription>{{ optionsError }}</AlertDescription>
        </Alert>

        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardDescription>基础套餐</CardDescription>
                    <CardTitle class="text-2xl">{{
                        packages.length
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>上架中</CardDescription>
                    <CardTitle class="text-2xl">{{ enabledCount }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>已选择</CardDescription>
                    <CardTitle class="text-2xl">{{ selectedCount }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <ConsoleTabs v-model="activeTab" :tabs="packageTabs" />

        <Card v-if="activeTab === 'packages'" class="gap-0 overflow-hidden">
            <CardHeader class="gap-2">
                <div
                    class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <CardTitle>基础套餐列表</CardTitle>
                        <CardDescription>
                            获取、查看、创建、修改、批量修改和删除基础套餐。
                        </CardDescription>
                    </div>
                    <Badge variant="outline">基础套餐</Badge>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead
                            class="border-y bg-muted/50 text-muted-foreground"
                        >
                            <tr>
                                <th class="px-6 py-3 text-left font-medium">
                                    <Checkbox
                                        :checked="allVisibleSelected"
                                        aria-label="选择全部套餐"
                                        @update:checked="toggleAll"
                                    />
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    名称
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    门户月付
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    月流量
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    带宽
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    域名数
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    状态
                                </th>
                                <th class="px-6 py-3 text-right font-medium">
                                    操作
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="8" class="px-6 py-10 text-center">
                                    <div
                                        class="inline-flex items-center gap-2 text-muted-foreground"
                                    >
                                        <Spinner />
                                        正在获取套餐列表
                                    </div>
                                </td>
                            </tr>
                            <template v-else>
                                <tr
                                    v-for="record in packages"
                                    :key="
                                        getPackageId(record) ??
                                        getDisplayValue(record, [
                                            'name',
                                            'title',
                                        ])
                                    "
                                    class="border-b last:border-b-0"
                                >
                                    <td class="px-6 py-4">
                                        <Checkbox
                                            v-if="getPackageId(record) !== null"
                                            :checked="recordSelected(record)"
                                            aria-label="选择套餐"
                                            @update:checked="
                                                toggleRecord(record, $event)
                                            "
                                        />
                                    </td>
                                    <td class="px-4 py-4 font-medium">
                                        {{
                                            getDisplayValue(record, [
                                                'name',
                                                'title',
                                            ])
                                        }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <span
                                            v-if="portalPriceOf(record)"
                                            class="font-medium"
                                        >
                                            {{ portalPriceOf(record) }}
                                        </span>
                                        <Badge v-else variant="outline">
                                            未上架
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-4 text-muted-foreground">
                                        {{
                                            getDisplayValue(record, [
                                                'traffic',
                                                'traffic_limit',
                                                'flow',
                                            ])
                                        }}
                                    </td>
                                    <td class="px-4 py-4 text-muted-foreground">
                                        {{
                                            getDisplayValue(record, [
                                                'bandwidth',
                                                'bandwidth_limit',
                                            ])
                                        }}
                                    </td>
                                    <td class="px-4 py-4 text-muted-foreground">
                                        {{
                                            getDisplayValue(record, [
                                                'domain',
                                                'site_limit',
                                                'site_num',
                                                'sites',
                                                'domain_limit',
                                            ])
                                        }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <Badge variant="secondary">
                                            {{ statusText(record) }}
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                :disabled="
                                                    loadingDetailId ===
                                                    getPackageId(record)
                                                "
                                                @click="
                                                    openDetailDialog(record)
                                                "
                                            >
                                                <Eye data-icon="inline-start" />
                                                查看
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                :disabled="
                                                    loadingDetailId ===
                                                    getPackageId(record)
                                                "
                                                @click="openEditDialog(record)"
                                            >
                                                <Pencil
                                                    data-icon="inline-start"
                                                />
                                                修改
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="
                                                    openDeletePackage(record)
                                                "
                                            >
                                                <Trash2
                                                    data-icon="inline-start"
                                                />
                                                删除
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!loading && packages.length === 0">
                                <td
                                    colspan="8"
                                    class="px-6 py-10 text-center text-muted-foreground"
                                >
                                    暂无套餐数据
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="packageDialogOpen">
            <DialogScrollContent class="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            dialogMode === 'create'
                                ? '新增基础套餐'
                                : '修改基础套餐'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        保存后会调用 CDNfly 套餐接口。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="formError" variant="destructive">
                    <AlertCircle />
                    <AlertTitle>保存失败</AlertTitle>
                    <AlertDescription>{{ formError }}</AlertDescription>
                </Alert>

                <!--
                    Forty fields is the real barrier to creating four tiers.
                    These fill the limits, capability flags and portal price in
                    one click, leaving 区域 / 线路组 / 套餐组 — the choices that
                    belong to this deployment rather than to the tier.
                -->
                <div
                    v-if="dialogMode === 'create'"
                    class="rounded-lg border bg-muted/30 p-3"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium">套餐预设</span>
                        <Button
                            v-for="preset in TIER_PRESETS"
                            :key="preset.key"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="applyTierPreset(preset)"
                        >
                            {{ preset.label }}
                            <span class="ml-1 text-xs text-muted-foreground">
                                {{ preset.limits.traffic }}G ·
                                {{ preset.limits.bandwidth }} · ${{
                                    preset.price
                                }}
                            </span>
                        </Button>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        点击后会覆盖下方的限制与售价，区域、线路组、套餐组保持不变。
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="flex flex-col gap-2">
                        <Label for="package-name">套餐名称</Label>
                        <Input id="package-name" v-model="form.name" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>区域</Label>
                        <Select
                            v-model="form.region_id"
                            :disabled="loadingOptions"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="选择区域" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="option in packageOptions.regions"
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
                        <Label>线路组</Label>
                        <Select
                            v-model="form.node_group_id"
                            :disabled="loadingOptions"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="选择线路组" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
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
                        <Label>所属套餐组</Label>
                        <Select
                            v-model="form.groups"
                            :disabled="loadingOptions"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="选择套餐组" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="option in packageOptions.package_groups"
                                        :key="optionValue(option)"
                                        :value="optionValue(option)"
                                    >
                                        {{ optionLabel(option) }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <!--
                        The price that matters. CDNfly's own prices bill against
                        the customer's CDNfly balance, which a portal order never
                        credits — so they stay at 0, out of the way, and this is
                        what customers actually pay.
                    -->
                    <div
                        class="rounded-lg border border-primary/30 bg-primary/5 p-4 md:col-span-3"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <div class="font-medium">门户售价</div>
                                <div class="text-xs text-muted-foreground">
                                    客户在本站看到并支付的价格（{{
                                        PORTAL_CURRENCY
                                    }}）。保存后会自动创建对应商品，无需再改
                                    .env。
                                </div>
                            </div>
                            <label
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <Checkbox v-model="portalForm.sell" />
                                在门户上架销售
                            </label>
                        </div>

                        <div v-if="portalForm.sell" class="mt-4 grid gap-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex flex-col gap-2">
                                    <Label for="portal-name">商品名称</Label>
                                    <Input
                                        id="portal-name"
                                        v-model="portalForm.name"
                                        placeholder="客户看到的名称"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="portal-slug">商品标识</Label>
                                    <Input
                                        id="portal-slug"
                                        v-model="portalForm.slug"
                                        placeholder="例如 jpn-mini"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        落地页按此标识匹配套餐。填写已存在的标识会直接接管该商品，
                                        而不是新建一个重复的。
                                    </p>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="portal-monthly">
                                        月付价格（{{ PORTAL_CURRENCY }}）
                                    </Label>
                                    <Input
                                        id="portal-monthly"
                                        v-model="portalForm.price_monthly"
                                        inputmode="decimal"
                                        placeholder="例如 5"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex flex-col gap-2">
                                    <Label for="portal-quarterly">
                                        季付价格（可留空）
                                    </Label>
                                    <Input
                                        id="portal-quarterly"
                                        v-model="portalForm.price_quarterly"
                                        inputmode="decimal"
                                        :placeholder="
                                            portalDerived
                                                ? `留空按 ${portalDerived.quarterly}`
                                                : '留空 = 月付 × 3'
                                        "
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="portal-yearly">
                                        年付价格（可留空）
                                    </Label>
                                    <Input
                                        id="portal-yearly"
                                        v-model="portalForm.price_yearly"
                                        inputmode="decimal"
                                        :placeholder="
                                            portalDerived
                                                ? `留空按 ${portalDerived.yearly}`
                                                : '留空 = 月付 × 12'
                                        "
                                    />
                                </div>
                            </div>

                            <div class="flex flex-col gap-2">
                                <Label for="portal-features">
                                    营销卖点（每行一条，可留空）
                                </Label>
                                <textarea
                                    id="portal-features"
                                    v-model="portalForm.features"
                                    rows="3"
                                    class="min-h-20 rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    placeholder="东京 BGP 线路&#10;被攻击不额外收费&#10;免费 SSL 证书"
                                />
                                <p class="text-xs text-muted-foreground">
                                    只写限制之外的卖点。流量、带宽、网站数等数字会自动取自本套餐的限制设置，
                                    无需也不要在这里重复填写。
                                </p>
                            </div>
                        </div>
                    </div>

                    <!--
                        CDNfly's internal prices. Collapsed because the correct
                        value is 0 for a portal-driven sale and changing it
                        breaks provisioning on an empty CDNfly balance.
                    -->
                    <Collapsible
                        v-model:open="cdnflyPriceOpen"
                        class="rounded-lg border md:col-span-3"
                    >
                        <CollapsibleTrigger
                            class="flex w-full items-center justify-between gap-3 p-4 text-left"
                        >
                            <div>
                                <div class="font-medium">
                                    CDNfly 内部价格（通常保持 0）
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    仅在让客户用 CDNfly
                                    余额自助购买时才需要填写。
                                </div>
                            </div>
                            <ChevronDown
                                class="size-4 shrink-0 transition-transform"
                                :class="cdnflyPriceOpen ? 'rotate-180' : ''"
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent class="border-t p-4">
                            <Alert class="mb-4">
                                <AlertCircle />
                                <AlertTitle>为什么保持 0</AlertTitle>
                                <AlertDescription>
                                    门户下单时以客户自己的 CDNfly 账号调用
                                    <code>POST /v1/user-packages</code>，CDNfly
                                    会按这里的价格扣该账号的 CDNfly
                                    余额。门户订单并不会为该余额充值，所以非 0
                                    的价格会让开通因余额不足而失败。
                                </AlertDescription>
                            </Alert>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="flex flex-col gap-2">
                                    <Label for="package-month-price">
                                        月付价格
                                    </Label>
                                    <Input
                                        id="package-month-price"
                                        v-model="form.month_price"
                                        inputmode="decimal"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="package-quarter-price">
                                        季付价格
                                    </Label>
                                    <Input
                                        id="package-quarter-price"
                                        v-model="form.quarter_price"
                                        inputmode="decimal"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="package-year-price">
                                        年付价格
                                    </Label>
                                    <Input
                                        id="package-year-price"
                                        v-model="form.year_price"
                                        inputmode="decimal"
                                    />
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                    <div class="grid gap-4 md:col-span-3 md:grid-cols-3">
                        <div
                            v-for="field in limitFieldConfigs"
                            :key="field.key"
                            class="rounded-lg border p-3"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <Label :for="`package-${field.key}`">
                                    {{ field.label }}
                                </Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="toggleLimit(form, field.key)"
                                >
                                    {{
                                        isLimitEnabled(form, field.key)
                                            ? '改为不限制'
                                            : '设置限制'
                                    }}
                                </Button>
                            </div>
                            <Input
                                v-if="isLimitEnabled(form, field.key)"
                                :id="`package-${field.key}`"
                                v-model="form[field.key]"
                                class="mt-3"
                                :inputmode="field.inputmode"
                                :placeholder="field.placeholder"
                            />
                            <button
                                v-else
                                type="button"
                                class="mt-3 flex h-10 w-full items-center rounded-md border bg-muted/40 px-3 text-left text-sm text-muted-foreground transition-colors hover:bg-muted"
                                @click="toggleLimit(form, field.key)"
                            >
                                当前不限制，点击设置
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>自定义CC规则</Label>
                        <Select v-model="form.custom_cc_rule">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="选择支持状态" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="1">支持</SelectItem>
                                    <SelectItem value="0">不支持</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>CC 防护</Label>
                        <Select v-model="form.cc_protect">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="选择支持状态" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="支持">支持</SelectItem>
                                    <SelectItem value="不支持"
                                        >不支持</SelectItem
                                    >
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="package-ddos-protect">DDoS 防护</Label>
                        <Input
                            id="package-ddos-protect"
                            v-model="form.ddos_protect"
                            placeholder="如 500G / 不支持"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>WebSocket</Label>
                        <Select v-model="form.websocket">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="选择支持状态" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="1">支持</SelectItem>
                                    <SelectItem value="0">不支持</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>HTTP3</Label>
                        <Select v-model="form.http3">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="选择支持状态" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="1">支持</SelectItem>
                                    <SelectItem value="0">不支持</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>L2 节点回源</Label>
                        <Select v-model="form.l2_state">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="选择状态" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="1">启用</SelectItem>
                                    <SelectItem value="0">禁用</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label>实名认证</Label>
                        <Select v-model="form.id_verify">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="选择要求" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="1">需要</SelectItem>
                                    <SelectItem value="0">不需要</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <Collapsible
                        v-model:open="packageCnameOpen"
                        class="rounded-lg border md:col-span-3"
                    >
                        <CollapsibleTrigger
                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                        >
                            <div class="min-w-0">
                                <div class="font-medium">CNAME 设置</div>
                                <div class="text-sm text-muted-foreground">
                                    CNAME 域名、主机名和分配模式。
                                </div>
                            </div>
                            <ChevronDown
                                class="size-4 shrink-0 transition-transform"
                                :class="{ 'rotate-180': packageCnameOpen }"
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent class="border-t p-4">
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="flex flex-col gap-2">
                                    <Label>CNAME 域名</Label>
                                    <Select
                                        v-model="form.cname_domain"
                                        :disabled="loadingOptions"
                                    >
                                        <SelectTrigger class="w-full">
                                            <SelectValue
                                                placeholder="选择 CNAME 域名"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
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
                                    <Label for="package-cname-hostname">
                                        CNAME 主机名
                                    </Label>
                                    <Input
                                        id="package-cname-hostname"
                                        v-model="form.cname_hostname2"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label>CNAME 模式</Label>
                                    <Select v-model="form.cname_mode">
                                        <SelectTrigger class="w-full">
                                            <SelectValue
                                                placeholder="选择 CNAME 模式"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
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
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <Collapsible
                        v-model:open="packagePurchaseLimitOpen"
                        class="rounded-lg border md:col-span-3"
                    >
                        <CollapsibleTrigger
                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                        >
                            <div class="min-w-0">
                                <div class="font-medium">购买限制</div>
                                <div class="text-sm text-muted-foreground">
                                    单用户购买数量和套餐可购买截止时间。
                                </div>
                            </div>
                            <ChevronDown
                                class="size-4 shrink-0 transition-transform"
                                :class="{
                                    'rotate-180': packagePurchaseLimitOpen,
                                }"
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent class="border-t p-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex flex-col gap-2">
                                    <Label for="package-buy-num-limit">
                                        单用户购买数量
                                    </Label>
                                    <Input
                                        id="package-buy-num-limit"
                                        v-model="form.buy_num_limit"
                                        inputmode="numeric"
                                        placeholder="-1 表示不限制"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="package-expire">
                                        有效期至
                                    </Label>
                                    <Input
                                        id="package-expire"
                                        v-model="form.expire"
                                        placeholder="2026-12-31 23:59:59，留空不限"
                                    />
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <Collapsible
                        v-model:open="packageOtherOpen"
                        class="rounded-lg border md:col-span-3"
                    >
                        <CollapsibleTrigger
                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                        >
                            <div class="min-w-0">
                                <div class="font-medium">其他</div>
                                <div class="text-sm text-muted-foreground">
                                    指定用户、启用状态和备注。
                                </div>
                            </div>
                            <ChevronDown
                                class="size-4 shrink-0 transition-transform"
                                :class="{ 'rotate-180': packageOtherOpen }"
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent class="border-t p-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex flex-col gap-2">
                                    <Label for="package-owner">
                                        分配给用户
                                    </Label>
                                    <Input
                                        id="package-owner"
                                        v-model="form.owner"
                                        placeholder="用户 ID，多个用逗号分隔"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label>套餐启用状态</Label>
                                    <Select v-model="form.enable">
                                        <SelectTrigger class="w-full">
                                            <SelectValue
                                                placeholder="选择启用状态"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="1">
                                                    启用
                                                </SelectItem>
                                                <SelectItem value="0">
                                                    禁用
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="package-backup-node-group">
                                        备用线路组
                                    </Label>
                                    <Input
                                        id="package-backup-node-group"
                                        v-model="form.backup_node_group"
                                        placeholder="按 CDNfly 要求填写"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="package-before-renew">
                                        提前续费天数
                                    </Label>
                                    <Input
                                        id="package-before-renew"
                                        v-model="form.before_exp_days_renew"
                                        inputmode="numeric"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="package-sort">排序</Label>
                                    <Input
                                        id="package-sort"
                                        v-model="form.sort"
                                        inputmode="numeric"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Label for="package-des">备注</Label>
                                    <Input
                                        id="package-des"
                                        v-model="form.des"
                                    />
                                </div>
                                <div class="flex flex-col gap-2 md:col-span-2">
                                    <Label for="package-sync-item">
                                        同步到已售套餐字段
                                    </Label>
                                    <Input
                                        id="package-sync-item"
                                        v-model="form.sync_item"
                                        placeholder="仅修改时使用，字段名用逗号分隔"
                                    />
                                </div>
                                <div class="flex flex-col gap-2 md:col-span-2">
                                    <Label for="package-extra">
                                        扩展字段 JSON
                                    </Label>
                                    <textarea
                                        id="package-extra"
                                        v-model="form.extra_json"
                                        class="min-h-28 rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        spellcheck="false"
                                    />
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="packageDialogOpen = false"
                    >
                        取消
                    </Button>
                    <Button :disabled="saving" @click="submitPackage">
                        <Spinner v-if="saving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        保存
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

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
                        <Label for="batch-month-price">月付价格（元）</Label>
                        <Input
                            id="batch-month-price"
                            v-model="batchForm.month_price"
                            inputmode="decimal"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-quarter-price">季付价格（元）</Label>
                        <Input
                            id="batch-quarter-price"
                            v-model="batchForm.quarter_price"
                            inputmode="decimal"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="batch-year-price">年付价格（元）</Label>
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
                        <Label for="batch-ddos-protect">DDoS 防护</Label>
                        <Input
                            id="batch-ddos-protect"
                            v-model="batchForm.ddos_protect"
                            placeholder="如 500G / 不支持"
                        />
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
                                    <Label for="batch-backup-node-group">
                                        备用线路组
                                    </Label>
                                    <Input
                                        id="batch-backup-node-group"
                                        v-model="batchForm.backup_node_group"
                                        placeholder="按 CDNfly 要求填写"
                                    />
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
                        <textarea
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
                                {{ detailProduct.currency }}
                                {{ detailProduct.price_monthly }}
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

        <!-- ─── Package Groups ────────────────────────────────── -->
        <ConsoleDataTable
            v-if="activeTab === 'groups'"
            ref="pgTableRef"
            title="套餐组"
            :icon="FolderTree"
            :columns="pgColumns"
            :fetch-fn="listAdminPackageGroups"
            search-placeholder="搜索套餐组"
        >
            <template #toolbar>
                <Button variant="default" size="sm" @click="openPgCreate">
                    <Plus data-icon="inline-start" class="size-4" />
                    新增
                </Button>
            </template>
            <template #row-actions="{ row }">
                <Button variant="ghost" size="sm" @click="openPgEdit(row)">
                    <Pencil class="size-4" />
                </Button>
                <Button variant="ghost" size="sm" @click="openPgDelete(row)">
                    <Trash2 class="size-4 text-destructive" />
                </Button>
            </template>
        </ConsoleDataTable>

        <!-- Package Group create/edit dialog -->
        <Dialog v-model:open="pgDialogOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        pgEditing ? '编辑套餐组' : '新增套餐组'
                    }}</DialogTitle>
                    <DialogDescription>管理 CDNfly 套餐组。</DialogDescription>
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

        <!-- ─── Package Ups (升级包) ──────────────────────────── -->
        <ConsoleDataTable
            v-if="activeTab === 'upgrades'"
            ref="puTableRef"
            title="升级包"
            :icon="Zap"
            :columns="puColumns"
            :fetch-fn="listAdminPackageUps"
            search-placeholder="搜索升级包"
        >
            <template #toolbar>
                <Button variant="default" size="sm" @click="openPuCreate">
                    <Plus data-icon="inline-start" class="size-4" />
                    新增
                </Button>
            </template>
            <template #row-actions="{ row }">
                <Button variant="ghost" size="sm" @click="openPuEdit(row)">
                    <Pencil class="size-4" />
                </Button>
                <Button variant="ghost" size="sm" @click="openPuDelete(row)">
                    <Trash2 class="size-4 text-destructive" />
                </Button>
            </template>
        </ConsoleDataTable>

        <!-- Package Up create/edit dialog -->
        <Dialog v-model:open="puDialogOpen">
            <DialogScrollContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        puEditing ? '编辑升级包' : '新增升级包'
                    }}</DialogTitle>
                    <DialogDescription>管理 CDNfly 升级包。</DialogDescription>
                </DialogHeader>
                <Alert v-if="puError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>提交失败</AlertTitle>
                    <AlertDescription>{{ puError }}</AlertDescription>
                </Alert>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="pu-name">名称</Label>
                        <Input
                            id="pu-name"
                            v-model="puForm.name"
                            placeholder="升级包名称"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="pu-groups">所属套餐组 ID</Label>
                        <Input
                            id="pu-groups"
                            v-model="puForm.groups"
                            placeholder="套餐组 ID"
                        />
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="grid gap-2">
                            <Label for="pu-month">月价格</Label>
                            <Input
                                id="pu-month"
                                v-model="puForm.month_price"
                                inputmode="decimal"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="pu-quarter">季价格</Label>
                            <Input
                                id="pu-quarter"
                                v-model="puForm.quarter_price"
                                inputmode="decimal"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="pu-year">年价格</Label>
                            <Input
                                id="pu-year"
                                v-model="puForm.year_price"
                                inputmode="decimal"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="pu-traffic">月流量（G）</Label>
                            <Input
                                id="pu-traffic"
                                v-model="puForm.traffic"
                                inputmode="decimal"
                                placeholder="-1 不限"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="pu-bandwidth">带宽</Label>
                            <Input
                                id="pu-bandwidth"
                                v-model="puForm.bandwidth"
                                placeholder="如 100Mbps"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="pu-connection">连接数</Label>
                            <Input
                                id="pu-connection"
                                v-model="puForm.connection"
                                inputmode="numeric"
                                placeholder="-1 不限"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="pu-domain">域名数</Label>
                            <Input
                                id="pu-domain"
                                v-model="puForm.domain"
                                inputmode="numeric"
                                placeholder="-1 不限"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="pu-des">备注</Label>
                        <Input
                            id="pu-des"
                            v-model="puForm.des"
                            placeholder="可选备注"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="pu-extra">扩展字段 JSON</Label>
                        <textarea
                            id="pu-extra"
                            v-model="puForm.extra_json"
                            class="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            spellcheck="false"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="puDialogOpen = false"
                        >取消</Button
                    >
                    <Button :disabled="puSaving" @click="submitPu">
                        <Spinner v-if="puSaving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        保存
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- Package Up delete confirm -->
        <Dialog v-model:open="puDeleteOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>确认删除</DialogTitle>
                    <DialogDescription>
                        确定要删除升级包「{{ puDeleteTarget?.name }}」吗？
                    </DialogDescription>
                </DialogHeader>
                <Alert v-if="puDeleteError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>删除失败</AlertTitle>
                    <AlertDescription>{{ puDeleteError }}</AlertDescription>
                </Alert>
                <DialogFooter>
                    <Button variant="outline" @click="puDeleteOpen = false"
                        >取消</Button
                    >
                    <Button
                        variant="destructive"
                        :disabled="puDeleting"
                        @click="confirmPuDelete"
                    >
                        <Spinner v-if="puDeleting" data-icon="inline-start" />
                        <Trash2 v-else data-icon="inline-start" />
                        确认删除
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
