<script setup lang="ts">
import {
    AlertCircle,
    ArrowUpCircle,
    BarChart3,
    ChevronDown,
    CreditCard,
    ExternalLink,
    Package,
    ReceiptText,
    RefreshCw,
    Save,
    Search,
    ShoppingCart,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
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
import { formatDate, getErrorMessage, textValue } from '@/lib/cdnRecord';
import {
    extractCdnflyRows,
    extractCdnflyTotal,
    extractCdnflyRecord,
    getUserPackage,
    getUserPackageUpgrades,
    getUserPackageUsage,
    listSalePackageUps,
    listUserPackages,
    purchaseUserPackageUpgrade,
} from '@/lib/cdnUserApi';
import type { CdnflyRecord } from '@/lib/cdnUserApi';
import {
    createBillingOrder,
    listBillingOrders,
    listBillingProducts,
    listBillingServiceInstances,
    provisionBillingOrder,
} from '@/lib/localBillingApi';
import type {
    LocalBillingOrder,
    LocalBillingProduct,
    LocalBillingServiceInstance,
} from '@/lib/localBillingApi';

type BillingView =
    'packages' | 'subscriptions' | 'orders' | 'traffic-packs' | 'usage';

type SubscriptionRow = LocalBillingServiceInstance & {
    source_package?: CdnflyRecord;
};

const props = defineProps<{
    view: BillingView;
}>();

const loading = ref(false);
const saving = ref(false);
const provisioning = ref<string | null>(null);
const errorMessage = ref('');
const formError = ref('');
const purchaseDialogOpen = ref(false);
const selectedProduct = ref<LocalBillingProduct | null>(null);
const renewalService = ref<LocalBillingServiceInstance | null>(null);
const autoOpenedProductId = ref<number | null>(null);
const page = ref(1);
const total = ref(0);
const productCatalog = ref<LocalBillingProduct[]>([]);
const products = ref<LocalBillingProduct[]>([]);
const services = ref<SubscriptionRow[]>([]);
const orders = ref<LocalBillingOrder[]>([]);
const trafficPacks = ref<CdnflyRecord[]>([]);
const userPackages = ref<CdnflyRecord[]>([]);

// upgrade dialog state
const upgradeDialogOpen = ref(false);
const upgradeTarget = ref<LocalBillingServiceInstance | null>(null);
const upgrades = ref<CdnflyRecord[]>([]);
const upgradeLoading = ref(false);
const upgradeSubmitting = ref(false);
const upgradeError = ref('');

// usage dialog state
const usageDialogOpen = ref(false);
const usageTarget = ref<CdnflyRecord | null>(null);
const usageData = ref<CdnflyRecord[]>([]);
const usageDetails = ref<CdnflyRecord | null>(null);
const usageUpgrades = ref<CdnflyRecord[]>([]);
const usageTab = ref<'usage' | 'details'>('usage');
const usageLoading = ref(false);
const usageError = ref('');

const filters = reactive({
    search: '',
    status: 'all',
    per_page: props.view === 'subscriptions' ? '10' : '20',
});

const form = reactive({
    product_id: '',
    billing_cycle: 'monthly',
});

const title = computed(() => {
    const map: Record<BillingView, string> = {
        packages: '套餐购买',
        subscriptions: '我的套餐',
        orders: '消费记录',
        'traffic-packs': '流量包',
        usage: '用量查询',
    };

    return map[props.view] ?? '套餐购买';
});

const description = computed(() => {
    const map: Record<BillingView, string> = {
        packages: '使用本地商品目录创建 EPUSDT 订单，并跳转到支付收银台。',
        subscriptions:
            '展示本地服务实例状态，已支付但未开通的套餐会显示为待同步。',
        orders: '展示本地下单、EPUSDT 支付状态与套餐开通进度。',
        'traffic-packs': '流量包仍保持只读，等待确认真实购买字段后再开放。',
        usage: '查看套餐流量、带宽等资源用量。选择套餐后可查看详细使用数据。',
    };

    return map[props.view] ?? '';
});

const icon = computed(() => {
    const map: Record<BillingView, typeof CreditCard> = {
        packages: CreditCard,
        subscriptions: Package,
        orders: ReceiptText,
        'traffic-packs': CreditCard,
        usage: BarChart3,
    };

    return map[props.view] ?? CreditCard;
});

const activeRows = computed(() => {
    if (props.view === 'packages') {
        return products.value;
    }

    if (props.view === 'subscriptions') {
        return services.value;
    }

    if (props.view === 'orders') {
        return orders.value;
    }

    if (props.view === 'usage') {
        return userPackages.value;
    }

    return trafficPacks.value;
});

const hasPreviousPage = computed(() => page.value > 1);
const hasNextPage = computed(
    () => page.value * Number(filters.per_page) < total.value,
);

onMounted(() => {
    void loadCurrent(1);
});

async function loadCurrent(targetPage = page.value): Promise<void> {
    if (props.view === 'subscriptions') {
        await loadServices(targetPage);

        return;
    }

    if (props.view === 'orders') {
        await loadOrders(targetPage);

        return;
    }

    if (props.view === 'traffic-packs') {
        await loadTrafficPacks(targetPage);

        return;
    }

    if (props.view === 'usage') {
        await loadUserPackages(targetPage);

        return;
    }

    await loadProducts();
}

async function loadProducts(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const result = await ensureProductsLoaded();
        const search = filters.search.trim().toLowerCase();
        const visible =
            search === ''
                ? result
                : result.filter((product) =>
                      `${product.name} ${product.slug ?? ''} ${product.description ?? ''}`
                          .toLowerCase()
                          .includes(search),
                  );

        products.value = visible;
        total.value = visible.length;
        page.value = 1;
        maybeOpenProductFromQuery();
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function ensureProductsLoaded(): Promise<LocalBillingProduct[]> {
    if (productCatalog.value.length > 0) {
        return productCatalog.value;
    }

    const result = await listBillingProducts();
    productCatalog.value = result;

    return result;
}

async function loadServices(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            per_page: Number(filters.per_page),
        };
        const search = filters.search.trim();

        if (search !== '') {
            params.search = search;
        }

        const [localResult, cdnflyResult] = await Promise.allSettled([
            listBillingServiceInstances(params),
            listUserPackages(basePageParams(targetPage)),
        ]);

        if (
            localResult.status === 'rejected' &&
            cdnflyResult.status === 'rejected'
        ) {
            throw cdnflyResult.reason;
        }

        const localServices =
            localResult.status === 'fulfilled' ? localResult.value.items : [];
        const cdnflyRows =
            cdnflyResult.status === 'fulfilled'
                ? extractCdnflyRows(cdnflyResult.value)
                : [];
        const normalizedSearch = search.toLowerCase();
        const cdnflyServices = cdnflyRows
            .map(cdnflyPackageToService)
            .filter(
                (service) =>
                    normalizedSearch === '' ||
                    `${service.service_name ?? ''} ${service.product_name ?? ''} ${service.cdnfly_service_id ?? ''}`
                        .toLowerCase()
                        .includes(normalizedSearch),
            );
        const cdnflyIds = new Set(
            cdnflyServices.map((service) => String(service.cdnfly_service_id)),
        );
        const localOnly = localServices.filter(
            (service) =>
                !service.cdnfly_service_id ||
                !cdnflyIds.has(String(service.cdnfly_service_id)),
        );

        services.value = [...cdnflyServices, ...localOnly];
        total.value =
            normalizedSearch === ''
                ? (cdnflyResult.status === 'fulfilled'
                      ? extractCdnflyTotal(
                            cdnflyResult.value,
                            cdnflyServices.length,
                        )
                      : 0) + localOnly.length
                : services.value.length;
        page.value = targetPage;

        if (cdnflyResult.status === 'rejected') {
            errorMessage.value = getErrorMessage(cdnflyResult.reason);
        } else if (localResult.status === 'rejected') {
            errorMessage.value = getErrorMessage(localResult.reason);
        }
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

function cdnflyPackageToService(record: CdnflyRecord): SubscriptionRow {
    const packageId = textValue(record.id);
    const packageName =
        textValue(record.package_name ?? record.package_title) || null;
    const serviceName =
        textValue(record.name ?? record.user_package_name) ||
        packageName ||
        (packageId ? `CDNfly package-${packageId}` : 'CDNfly package');
    const enabled = !(
        record.enable === 0 ||
        record.enable === '0' ||
        record.enable === false
    );

    return {
        id: Number(record.id) || 0,
        status:
            textValue(record.status ?? record.state) ||
            (enabled ? 'active' : 'disabled'),
        service_name: serviceName,
        product_name: packageName ?? serviceName,
        product_slug: null,
        order_no: null,
        cdnfly_user_id: textValue(record.uid ?? record.user_id) || null,
        cdnfly_service_id: packageId || null,
        opened_at:
            textValue(
                record.start_at2 ??
                    record.start_at ??
                    record.create_at2 ??
                    record.created_at,
            ) || null,
        expired_at:
            textValue(record.end_at2 ?? record.end_at ?? record.expire_at) ||
            null,
        source_package: record,
    };
}

async function loadOrders(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params = basePageParams(targetPage);
        const search = filters.search.trim();

        if (filters.status !== 'all') {
            params.status = filters.status;
        }

        if (search !== '') {
            params.search = search;
        }

        const result = await listBillingOrders(params);

        orders.value = result.items;
        total.value = result.total;
        page.value = result.page;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function loadTrafficPacks(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const result = await listSalePackageUps(basePageParams(targetPage));
        const nextRows = extractCdnflyRows(result);
        const search = filters.search.trim().toLowerCase();
        const visibleRows =
            search === ''
                ? nextRows
                : nextRows.filter((record) =>
                      `${textValue(record.name ?? record.title)} ${textValue(record.id)}`
                          .toLowerCase()
                          .includes(search),
                  );

        trafficPacks.value = visibleRows;
        total.value =
            search === ''
                ? extractCdnflyTotal(result, nextRows.length)
                : visibleRows.length;
        page.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function loadUserPackages(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const result = await listUserPackages(basePageParams(targetPage));
        const rows = extractCdnflyRows(result);
        const search = filters.search.trim().toLowerCase();
        const visible =
            search === ''
                ? rows
                : rows.filter((r) =>
                      `${textValue(r.name ?? r.package_name)} ${textValue(r.id)}`
                          .toLowerCase()
                          .includes(search),
                  );

        userPackages.value = visible;
        total.value =
            search === ''
                ? extractCdnflyTotal(result, rows.length)
                : visible.length;
        page.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function openUpgradeDialog(
    service: LocalBillingServiceInstance,
): Promise<void> {
    const pkgId = Number(service.cdnfly_service_id);

    if (!pkgId) {
        toast.error('该实例没有关联 CDNfly 套餐');

        return;
    }

    upgradeTarget.value = service;
    upgrades.value = [];
    upgradeError.value = '';
    upgradeDialogOpen.value = true;
    upgradeLoading.value = true;

    try {
        const result = await getUserPackageUpgrades(pkgId);
        upgrades.value = extractCdnflyRows(result);
    } catch (error) {
        upgradeError.value = getErrorMessage(error);
    } finally {
        upgradeLoading.value = false;
    }
}

async function submitUpgrade(packageUpId: number): Promise<void> {
    const pkgId = Number(upgradeTarget.value?.cdnfly_service_id);

    if (!pkgId) {
        return;
    }

    upgradeSubmitting.value = true;
    upgradeError.value = '';

    try {
        await purchaseUserPackageUpgrade(pkgId, { package_up_id: packageUpId });
        toast.success('升级购买成功');
        upgradeDialogOpen.value = false;

        if (props.view === 'subscriptions') {
            await loadServices();
        }
    } catch (error) {
        upgradeError.value = getErrorMessage(error);
    } finally {
        upgradeSubmitting.value = false;
    }
}

async function openUsageDialog(record: CdnflyRecord): Promise<void> {
    const pkgId = Number(record.id);

    if (!pkgId) {
        return;
    }

    usageTarget.value = record;
    usageData.value = [];
    usageDetails.value = record;
    usageUpgrades.value = [];
    usageTab.value = 'usage';
    usageError.value = '';
    usageDialogOpen.value = true;
    usageLoading.value = true;

    try {
        const [detailResult, usageResult, upgradesResult] =
            await Promise.allSettled([
                getUserPackage(pkgId),
                getUserPackageUsage(pkgId),
                getUserPackageUpgrades(pkgId, { limit: 0 }),
            ]);

        if (detailResult.status === 'fulfilled') {
            const details = extractCdnflyRecord(detailResult.value);

            if (details) {
                usageDetails.value = { ...record, ...details };
            }
        }

        if (usageResult.status === 'fulfilled') {
            const rows = extractCdnflyRows(usageResult.value);
            const flat = extractCdnflyRecord(usageResult.value);

            usageData.value = rows.length > 0 ? rows : flat ? [flat] : [];
        }

        if (upgradesResult.status === 'fulfilled') {
            usageUpgrades.value = extractCdnflyRows(upgradesResult.value);
        }

        const failed = [detailResult, usageResult, upgradesResult].find(
            (result) => result.status === 'rejected',
        );

        if (failed?.status === 'rejected') {
            usageError.value = getErrorMessage(failed.reason);
        }
    } catch (error) {
        usageError.value = getErrorMessage(error);
    } finally {
        usageLoading.value = false;
    }
}

type UsageMetric = {
    label: string;
    total: string;
    used: string;
    remaining: string;
};

const usageRecord = computed<CdnflyRecord>(() =>
    Object.assign({}, ...usageData.value),
);

const usageMetrics = computed<UsageMetric[]>(() => [
    buildUsageMetric(
        '流量 (GB)',
        ['traffic', 'total_traffic'],
        ['traffic_usage', 'used_traffic'],
        usageDetails.value?.traffic_usage ?? usageTarget.value?.traffic_usage,
        bytesToGigabytes,
    ),
    buildUsageMetric(
        '域名数',
        ['domain', 'total_domain'],
        ['domain_usage', 'used_domain'],
        usageDetails.value?.domain_usage,
    ),
    buildUsageMetric(
        '主域名数',
        ['main_domain', 'total_main_domain'],
        ['main_domain_usage', 'used_main_domain'],
        usageDetails.value?.main_domain_usage,
    ),
    buildUsageMetric(
        'HTTP端口数',
        ['http_port', 'total_http_port'],
        ['http_port_usage', 'used_http_port'],
        usageDetails.value?.http_port_usage,
    ),
    buildUsageMetric(
        '转发端口数',
        ['stream_port', 'total_stream_port'],
        ['stream_port_usage', 'used_stream_port'],
        usageDetails.value?.stream_port_usage,
    ),
]);

const packageDetailItems = computed(() => [
    { label: '名称', value: detailText(['package_name', 'name']) },
    { label: '流量 (GB)', value: quotaText(detailValue(['traffic'])) },
    { label: '带宽', value: quotaText(detailValue(['bandwidth'])) },
    { label: '连接数', value: quotaText(detailValue(['connection'])) },
    { label: '域名数', value: quotaText(detailValue(['domain'])) },
    { label: '主域名数', value: quotaText(detailValue(['main_domain'])) },
    { label: 'HTTP端口数', value: quotaText(detailValue(['http_port'])) },
    { label: '转发端口数', value: quotaText(detailValue(['stream_port'])) },
    {
        label: '自定义CC规则',
        value: enabledText(detailValue(['custom_cc_rule'])),
    },
    { label: 'WebSocket', value: enabledText(detailValue(['websocket'])) },
    { label: 'HTTP3', value: enabledText(detailValue(['http3'])) },
    {
        label: '购买时间',
        value: formatDate(
            detailValue(['start_at2', 'start_at', 'create_at2', 'created_at']),
        ),
    },
    {
        label: '到期时间',
        value: formatDate(detailValue(['end_at2', 'end_at', 'expire_at'])),
    },
    {
        label: '流量重置时间',
        value: formatDate(
            detailValue([
                'traffic_reset_at2',
                'traffic_reset_at',
                'reset_at2',
                'reset_at',
                'end_at2',
                'end_at',
            ]),
        ),
    },
]);

function buildUsageMetric(
    label: string,
    totalKeys: string[],
    usedKeys: string[],
    fallbackUsed?: unknown,
    transformUsed: (value: unknown) => unknown = (value) => value,
): UsageMetric {
    const totalValue = firstRecordValue(usageDetails.value, totalKeys);
    const rawUsedValue =
        firstRecordValue(usageRecord.value, usedKeys) ?? fallbackUsed;
    const usedValue =
        rawUsedValue === undefined || rawUsedValue === null
            ? undefined
            : transformUsed(rawUsedValue);

    return {
        label,
        total: quotaText(totalValue),
        used: numberText(usedValue, '-'),
        remaining: remainingText(totalValue, usedValue),
    };
}

function bytesToGigabytes(value: unknown): unknown {
    const bytes = Number(value);

    return Number.isFinite(bytes) ? bytes / 1024 ** 3 : value;
}

function detailValue(keys: string[]): unknown {
    return firstRecordValue(usageDetails.value, keys);
}

function detailText(keys: string[]): string {
    return textValue(detailValue(keys)) || '-';
}

function firstRecordValue(
    record: CdnflyRecord | null,
    keys: string[],
): unknown {
    if (!record) {
        return undefined;
    }

    for (const key of keys) {
        const value = record[key];

        if (value !== undefined && value !== null && value !== '') {
            return value;
        }
    }

    return undefined;
}

function quotaText(value: unknown): string {
    if (String(value) === '-1') {
        return '不限';
    }

    return numberText(value, '-');
}

function numberText(value: unknown, fallback = '0'): string {
    const numeric = Number(value);

    if (!Number.isFinite(numeric)) {
        return textValue(value) || fallback;
    }

    return Number.isInteger(numeric)
        ? String(numeric)
        : numeric.toFixed(2).replace(/\.00$/, '');
}

function remainingText(total: unknown, used: unknown): string {
    if (String(total) === '-1') {
        return '-';
    }

    const totalNumber = Number(total);
    const usedNumber = Number(used);

    if (!Number.isFinite(totalNumber) || !Number.isFinite(usedNumber)) {
        return '-';
    }

    return numberText(Math.max(totalNumber - usedNumber, 0));
}

function enabledText(value: unknown): string {
    if (value === undefined || value === null || value === '') {
        return '-';
    }

    return ['1', 'true', 'enable', 'enabled', 'allow'].includes(
        String(value).toLowerCase(),
    )
        ? '允许'
        : '不允许';
}

/**
 * A usage page should lead with how much of the allowance is left. `traffic` is
 * the GB allowance (-1 = unlimited); CDNfly returns `traffic_usage` in bytes.
 */
function usageTrafficText(pkg: CdnflyRecord): string {
    const used = Number(bytesToGigabytes(pkg.traffic_usage ?? 0));
    const limit = pkg.traffic;

    if (limit === undefined || limit === null || limit === '') {
        return `${used.toFixed(2)} GB`;
    }

    if (String(limit) === '-1') {
        return `${used.toFixed(2)} GB / 不限`;
    }

    return `${used.toFixed(2)} / ${limit} GB`;
}

/** CDNfly marks an active package with enable=1. */
function usageActive(pkg: CdnflyRecord): boolean {
    return pkg.enable === 1 || pkg.enable === '1';
}

function maybeOpenProductFromQuery(): void {
    if (props.view !== 'packages') {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const rawProductId = params.get('product_id');

    if (!rawProductId) {
        return;
    }

    const productId = Number(rawProductId);

    if (!Number.isFinite(productId) || productId <= 0) {
        return;
    }

    if (autoOpenedProductId.value === productId) {
        return;
    }

    const product = products.value.find((item) => item.id === productId);

    if (product) {
        autoOpenedProductId.value = productId;
        openPurchaseDialog(product);
    } else {
        errorMessage.value = `未找到商品 #${productId}`;
    }
}

function openPurchaseDialog(product?: LocalBillingProduct): void {
    selectedProduct.value = product ?? null;
    renewalService.value = null;
    form.product_id = product ? String(product.id) : '';
    form.billing_cycle = 'monthly';
    formError.value = '';
    purchaseDialogOpen.value = true;
}

async function openRenewDialog(
    service: LocalBillingServiceInstance,
): Promise<void> {
    if (productCatalog.value.length === 0) {
        try {
            await ensureProductsLoaded();
        } catch (error) {
            errorMessage.value = getErrorMessage(error);
        }
    }

    renewalService.value = service;
    selectedProduct.value = service.product_id
        ? (productCatalog.value.find(
              (item) => item.id === service.product_id,
          ) ?? null)
        : null;
    form.product_id = service.product_id ? String(service.product_id) : '';
    form.billing_cycle = 'monthly';
    formError.value = '';
    purchaseDialogOpen.value = true;
}

async function submitPurchase(): Promise<void> {
    const productId = Number(form.product_id);

    if (!Number.isFinite(productId) || productId <= 0) {
        formError.value = '商品 ID 无效';

        return;
    }

    saving.value = true;
    formError.value = '';

    try {
        const checkout = await createBillingOrder({
            product_id: productId,
            order_type: renewalService.value ? 'renew' : 'new',
            billing_cycle: form.billing_cycle,
            quantity: 1,
            service_instance_id: renewalService.value?.id ?? null,
        });

        const paymentUrl = checkout.payment_url?.trim();

        if (!paymentUrl) {
            throw new Error('支付链接未返回');
        }

        toast.success(`订单 ${checkout.order_no} 已创建，正在跳转支付`);
        purchaseDialogOpen.value = false;
        window.location.assign(paymentUrl);
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

async function continueToPayment(order: LocalBillingOrder): Promise<void> {
    const paymentUrl = order.gateway_payment_url?.trim();

    if (!paymentUrl) {
        errorMessage.value = '当前订单没有可继续支付的链接';

        return;
    }

    window.location.assign(paymentUrl);
}

async function retryProvision(order: LocalBillingOrder): Promise<void> {
    provisioning.value = order.order_no;
    errorMessage.value = '';

    try {
        const result = await provisionBillingOrder(order.order_no);
        const nextStatus = textValue(result.status) || 'queued';
        toast.success(
            nextStatus === 'success'
                ? '已提交开通请求'
                : nextStatus === 'queued'
                  ? '已记录待开通状态，系统将稍后自动同步'
                  : '已触发开通重试',
        );
        await loadOrders(page.value);

        if (props.view === 'subscriptions') {
            await loadServices();
        }
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        provisioning.value = null;
    }
}

function submitSearch(): void {
    page.value = 1;
    void loadCurrent(1);
}

function prevPage(): void {
    if (hasPreviousPage.value) {
        void loadCurrent(page.value - 1);
    }
}

function nextPage(): void {
    if (hasNextPage.value) {
        void loadCurrent(page.value + 1);
    }
}

function basePageParams(targetPage: number): Record<string, string | number> {
    return {
        page: targetPage,
        limit: Number(filters.per_page),
    };
}

function productMonthlyPrice(product: LocalBillingProduct): string {
    return formatMoney(product.price_monthly, product.currency);
}

function productRenewalPrice(product: LocalBillingProduct): string {
    const quarter = formatMoney(product.price_quarterly, product.currency);
    const yearly = formatMoney(product.price_yearly, product.currency);

    return `${quarter} / ${yearly}`;
}

function productDescription(product: LocalBillingProduct): string {
    if (product.description && product.description.trim() !== '') {
        return product.description;
    }

    if (Array.isArray(product.features) && product.features.length > 0) {
        return product.features
            .filter(
                (item): item is string =>
                    typeof item === 'string' && item.trim() !== '',
            )
            .slice(0, 3)
            .join(' / ');
    }

    return '-';
}

function formatMoney(
    amount: string | number | null | undefined,
    currency: string | null | undefined,
): string {
    if (amount === null || amount === undefined || amount === '') {
        return '-';
    }

    const numeric = Number(amount);

    if (!Number.isFinite(numeric)) {
        return String(amount);
    }

    return `${numeric.toFixed(2)} ${currency ?? 'USD'}`;
}

function orderStatusText(order: LocalBillingOrder): string {
    if (order.status === 'active') {
        return '已开通';
    }

    if (
        order.status === 'paid' &&
        order.service_instance?.status === 'pending'
    ) {
        return '已支付 / 待开通';
    }

    if (order.status === 'paid') {
        return '已支付';
    }

    if (order.status === 'pending' && order.gateway_status === 'pending') {
        return '待支付';
    }

    if (order.status === 'expired') {
        return '已过期';
    }

    return order.status;
}

function serviceStatusText(service: LocalBillingServiceInstance): string {
    if (service.status === 'pending' && service.queue_reason) {
        return '待同步';
    }

    if (service.status === 'active') {
        return '已开通';
    }

    return service.status;
}

function subscriptionIsActive(service: LocalBillingServiceInstance): boolean {
    return ['1', 'active', 'enabled', 'normal'].includes(
        String(service.status).trim().toLowerCase(),
    );
}

function subscriptionStatusText(service: LocalBillingServiceInstance): string {
    const status = String(service.status).trim().toLowerCase();

    if (subscriptionIsActive(service)) {
        return '正常';
    }

    if (status === 'expired') {
        return '已过期';
    }

    if (status === 'disabled' || status === 'disable' || status === '0') {
        return '已停用';
    }

    return serviceStatusText(service);
}

function subscriptionTrafficText(service: SubscriptionRow): string {
    return service.source_package
        ? usageTrafficText(service.source_package)
        : '-';
}

function openSubscriptionDetails(service: SubscriptionRow): void {
    if (!service.source_package) {
        toast.error('该套餐暂时没有可查询的用量详情');

        return;
    }

    void openUsageDialog(service.source_package);
}

function goToPackagePurchase(): void {
    window.location.href = '/console/billing/packages';
}

function trafficPackName(record: CdnflyRecord): string {
    return textValue(record.name ?? record.title) || '-';
}

function trafficPackPrice(record: CdnflyRecord): string {
    return (
        textValue(record.price ?? record.amount ?? record.month_price) || '-'
    );
}

function trafficPackMetric(record: CdnflyRecord): string {
    return textValue(record.traffic ?? record.flow ?? record.size) || '-';
}
</script>

<template>
    <div class="space-y-6">
        <ConsolePageHeader
            eyebrow="用户端 / 财务"
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
        <Card>
            <CardHeader class="space-y-4">
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <Button
                        v-if="props.view === 'subscriptions'"
                        class="w-fit"
                        @click="goToPackagePurchase"
                    >
                        <ShoppingCart data-icon="inline-start" />
                        购买套餐
                    </Button>
                    <CardTitle v-else class="text-base">{{ title }}</CardTitle>
                    <div class="text-sm text-muted-foreground">
                        {{ total === 0 ? '暂无记录' : `${total} 条记录` }}
                    </div>
                </div>

                <form
                    v-if="
                        props.view === 'packages' ||
                        props.view === 'traffic-packs' ||
                        props.view === 'usage'
                    "
                    class="grid gap-3 xl:grid-cols-[1fr_120px_auto]"
                    @submit.prevent="submitSearch"
                >
                    <Input
                        v-model="filters.search"
                        :placeholder="
                            props.view === 'traffic-packs'
                                ? '搜索流量包名称或 ID'
                                : props.view === 'usage'
                                  ? '搜索套餐名称或 ID'
                                  : '搜索商品名称、slug 或描述'
                        "
                    />
                    <Select v-model="filters.per_page">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="20">20 条</SelectItem>
                                <SelectItem value="50">50 条</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <div class="flex gap-2">
                        <Button type="submit" :disabled="loading">
                            <Spinner v-if="loading" data-icon="inline-start" />
                            <Search v-else data-icon="inline-start" />
                            查询
                        </Button>
                        <Button
                            v-if="props.view === 'packages'"
                            type="button"
                            variant="outline"
                            @click="openPurchaseDialog()"
                        >
                            <ShoppingCart data-icon="inline-start" />
                            手动购买
                        </Button>
                    </div>
                </form>

                <form
                    v-else-if="props.view === 'orders'"
                    class="grid gap-3 xl:grid-cols-[1fr_160px_120px_auto]"
                    @submit.prevent="submitSearch"
                >
                    <Input
                        v-model="filters.search"
                        placeholder="搜索订单号或商品名称"
                    />
                    <Select v-model="filters.status">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">全部状态</SelectItem>
                                <SelectItem value="pending">待支付</SelectItem>
                                <SelectItem value="paid">已支付</SelectItem>
                                <SelectItem value="active">已开通</SelectItem>
                                <SelectItem value="expired">已过期</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.per_page">
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

                <form
                    v-else-if="props.view !== 'subscriptions'"
                    class="grid gap-3 xl:grid-cols-[1fr_120px_auto]"
                    @submit.prevent="submitSearch"
                >
                    <Input
                        v-model="filters.search"
                        placeholder="搜索实例名、商品名或订单号"
                    />
                    <Select v-model="filters.per_page">
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
                    <table class="w-full min-w-[980px] table-fixed text-sm">
                        <colgroup v-if="props.view === 'subscriptions'">
                            <col style="width: 7%" />
                            <col style="width: 20%" />
                            <col style="width: 17%" />
                            <col style="width: 17%" />
                            <col style="width: 16%" />
                            <col style="width: 10%" />
                            <col style="width: 13%" />
                        </colgroup>
                        <colgroup v-else>
                            <col style="width: 22%" />
                            <col style="width: 16%" />
                            <col style="width: 18%" />
                            <col style="width: 18%" />
                            <col style="width: 14%" />
                            <col style="width: 12%" />
                        </colgroup>
                        <thead class="border-b text-muted-foreground">
                            <tr v-if="props.view === 'orders'">
                                <th class="px-4 py-3 text-left font-medium">
                                    订单
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    商品
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    金额
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    状态
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    创建时间
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    操作
                                </th>
                            </tr>
                            <tr v-else-if="props.view === 'subscriptions'">
                                <th class="px-4 py-3 text-left font-medium">
                                    ID
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    套餐名称
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    购买时间
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    到期时间
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    已用 / 总流量
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    状态
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    操作
                                </th>
                            </tr>
                            <tr v-else-if="props.view === 'usage'">
                                <th class="px-4 py-3 text-left font-medium">
                                    套餐
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    流量
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    带宽
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    到期时间
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    状态
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    操作
                                </th>
                            </tr>
                            <tr v-else>
                                <th class="px-4 py-3 text-left font-medium">
                                    {{
                                        props.view === 'traffic-packs'
                                            ? '流量包'
                                            : '商品'
                                    }}
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    {{
                                        props.view === 'traffic-packs'
                                            ? '价格'
                                            : '月付'
                                    }}
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    {{
                                        props.view === 'traffic-packs'
                                            ? '流量'
                                            : '季付 / 年付'
                                    }}
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    {{
                                        props.view === 'traffic-packs'
                                            ? '分组'
                                            : '说明'
                                    }}
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    状态
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    操作
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td
                                    class="px-6 py-16 text-center"
                                    :colspan="
                                        props.view === 'subscriptions' ? 7 : 6
                                    "
                                >
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <template v-else-if="props.view === 'orders'">
                                <tr
                                    v-for="order in orders"
                                    :key="order.order_no"
                                    class="border-b"
                                >
                                    <td class="px-4 py-3">
                                        <div class="truncate font-medium">
                                            {{ order.order_no }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ order.gateway_provider ?? '-' }}
                                            /
                                            {{ order.gateway_trade_id ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="truncate font-medium">
                                            {{ order.product_name ?? '-' }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ order.billing_cycle ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{
                                            formatMoney(
                                                order.fiat_amount,
                                                order.fiat_currency,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1">
                                            <Badge variant="secondary">{{
                                                orderStatusText(order)
                                            }}</Badge>
                                            <span
                                                v-if="
                                                    order.service_instance
                                                        ?.queue_reason
                                                "
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    order.service_instance
                                                        .queue_reason
                                                }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(order.created_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <Button
                                                v-if="
                                                    order.status ===
                                                        'pending' &&
                                                    order.gateway_payment_url
                                                "
                                                variant="outline"
                                                size="sm"
                                                @click="
                                                    continueToPayment(order)
                                                "
                                            >
                                                <ExternalLink
                                                    data-icon="inline-start"
                                                />
                                                继续支付
                                            </Button>
                                            <Button
                                                v-else-if="
                                                    order.status === 'paid' &&
                                                    !order.provisioned_at
                                                "
                                                variant="outline"
                                                size="sm"
                                                :disabled="
                                                    provisioning ===
                                                    order.order_no
                                                "
                                                @click="retryProvision(order)"
                                            >
                                                <Spinner
                                                    v-if="
                                                        provisioning ===
                                                        order.order_no
                                                    "
                                                    data-icon="inline-start"
                                                />
                                                <RefreshCw
                                                    v-else
                                                    data-icon="inline-start"
                                                />
                                                重试开通
                                            </Button>
                                            <Badge v-else variant="outline"
                                                >只读</Badge
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template
                                v-else-if="props.view === 'subscriptions'"
                            >
                                <tr
                                    v-for="service in services"
                                    :key="service.id"
                                    class="border-b"
                                >
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{
                                            service.cdnfly_service_id ??
                                            service.id
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="truncate font-medium">
                                            {{
                                                service.product_name ??
                                                service.service_name ??
                                                '-'
                                            }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(service.opened_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(service.expired_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ subscriptionTrafficText(service) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="size-2 rounded-full"
                                                :class="
                                                    subscriptionIsActive(
                                                        service,
                                                    )
                                                        ? 'bg-emerald-500'
                                                        : 'bg-muted-foreground'
                                                "
                                            />
                                            <span>{{
                                                subscriptionStatusText(service)
                                            }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div
                                            class="flex items-center justify-end gap-2 whitespace-nowrap"
                                        >
                                            <Button
                                                v-if="service.source_package"
                                                variant="ghost"
                                                size="sm"
                                                @click="
                                                    openSubscriptionDetails(
                                                        service,
                                                    )
                                                "
                                            >
                                                详情
                                            </Button>
                                            <Button
                                                v-if="service.product_id"
                                                variant="ghost"
                                                size="sm"
                                                @click="
                                                    openRenewDialog(service)
                                                "
                                            >
                                                续费
                                            </Button>
                                            <Button
                                                v-if="
                                                    service.cdnfly_service_id &&
                                                    subscriptionIsActive(
                                                        service,
                                                    )
                                                "
                                                variant="ghost"
                                                size="sm"
                                                @click="
                                                    openUpgradeDialog(service)
                                                "
                                            >
                                                更多
                                                <ChevronDown
                                                    data-icon="inline-end"
                                                />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template v-else-if="props.view === 'packages'">
                                <tr
                                    v-for="product in products"
                                    :key="product.id"
                                    class="border-b"
                                >
                                    <td class="px-4 py-3">
                                        <div class="truncate font-medium">
                                            {{ product.name }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            #{{ product.id }} /
                                            {{ product.slug ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ productMonthlyPrice(product) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ productRenewalPrice(product) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div
                                            class="line-clamp-2 text-muted-foreground"
                                        >
                                            {{ productDescription(product) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge variant="secondary"
                                            >可购买</Badge
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openPurchaseDialog(product)"
                                        >
                                            <ShoppingCart
                                                data-icon="inline-start"
                                            />
                                            购买
                                        </Button>
                                    </td>
                                </tr>
                            </template>
                            <template v-else-if="props.view === 'usage'">
                                <tr
                                    v-for="pkg in userPackages"
                                    :key="textValue(pkg.id)"
                                    class="border-b"
                                >
                                    <td class="px-4 py-3">
                                        <div class="truncate font-medium">
                                            {{
                                                textValue(pkg.package_name) ||
                                                textValue(pkg.name) ||
                                                '-'
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            #{{ textValue(pkg.id) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ usageTrafficText(pkg) }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ textValue(pkg.bandwidth) || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{
                                            formatDate(
                                                pkg.end_at2 ?? pkg.end_at,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge
                                            :variant="
                                                usageActive(pkg)
                                                    ? 'secondary'
                                                    : 'destructive'
                                            "
                                        >
                                            {{
                                                usageActive(pkg)
                                                    ? '生效中'
                                                    : '已停用'
                                            }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openUsageDialog(pkg)"
                                        >
                                            <BarChart3
                                                data-icon="inline-start"
                                            />
                                            查看用量
                                        </Button>
                                    </td>
                                </tr>
                            </template>
                            <template v-else>
                                <tr
                                    v-for="item in trafficPacks"
                                    :key="
                                        textValue(item.id) ||
                                        trafficPackName(item)
                                    "
                                    class="border-b"
                                >
                                    <td class="px-4 py-3">
                                        <div class="truncate font-medium">
                                            {{ trafficPackName(item) }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            #{{ textValue(item.id) || '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ trafficPackPrice(item) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ trafficPackMetric(item) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{
                                            textValue(
                                                item.package_group ??
                                                    item.group_id,
                                            ) || '-'
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge variant="secondary">
                                            {{
                                                textValue(item.status) ||
                                                textValue(item.enable) ||
                                                '-'
                                            }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Badge variant="outline">只读</Badge>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!loading && activeRows.length === 0">
                                <td
                                    class="px-6 py-16 text-center text-muted-foreground"
                                    :colspan="
                                        props.view === 'subscriptions' ? 7 : 6
                                    "
                                >
                                    暂无记录
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="props.view !== 'packages'"
            class="flex items-center justify-between gap-4"
        >
            <span class="text-sm text-muted-foreground">
                共 {{ total }} 条
            </span>
            <div class="flex items-center gap-2">
                <Select
                    v-if="props.view === 'subscriptions'"
                    v-model="filters.per_page"
                    @update:model-value="loadCurrent(1)"
                >
                    <SelectTrigger class="w-24"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="10">10 条</SelectItem>
                            <SelectItem value="20">20 条</SelectItem>
                            <SelectItem value="50">50 条</SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!hasPreviousPage || loading"
                    @click="prevPage"
                >
                    上一页
                </Button>
                <span class="text-sm text-muted-foreground"
                    >第 {{ page }} 页</span
                >
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

        <Dialog v-model:open="purchaseDialogOpen">
            <DialogScrollContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ renewalService ? '续费套餐' : '购买套餐' }}
                    </DialogTitle>
                    <DialogDescription>
                        创建本地 EPUSDT 订单后会立即跳转到支付收银台。
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-5" @submit.prevent="submitPurchase">
                    <Alert v-if="formError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>提交失败</AlertTitle>
                        <AlertDescription>{{ formError }}</AlertDescription>
                    </Alert>
                    <div
                        v-if="selectedProduct"
                        class="rounded-md border bg-muted/30 p-3 text-sm"
                    >
                        <div class="font-medium">
                            {{ selectedProduct.name }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            {{ productDescription(selectedProduct) }}
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="billing-product">商品 ID</Label>
                            <Input
                                id="billing-product"
                                v-model="form.product_id"
                                inputmode="numeric"
                                required
                                :readonly="
                                    Boolean(selectedProduct || renewalService)
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>周期</Label>
                            <Select v-model="form.billing_cycle">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="monthly"
                                            >monthly</SelectItem
                                        >
                                        <SelectItem value="quarterly"
                                            >quarterly</SelectItem
                                        >
                                        <SelectItem value="yearly"
                                            >yearly</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="purchaseDialogOpen = false"
                        >
                            取消
                        </Button>
                        <Button type="submit" :disabled="saving">
                            <Spinner v-if="saving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            去支付
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- 升级购买对话框 -->
        <Dialog v-model:open="upgradeDialogOpen">
            <DialogScrollContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>套餐升级</DialogTitle>
                    <DialogDescription>
                        为「{{
                            upgradeTarget?.service_name ||
                            upgradeTarget?.product_name ||
                            '-'
                        }}」选择一个升级包。
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4">
                    <Alert v-if="upgradeError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>请求失败</AlertTitle>
                        <AlertDescription>{{ upgradeError }}</AlertDescription>
                    </Alert>

                    <div v-if="upgradeLoading" class="flex justify-center py-8">
                        <Spinner />
                    </div>

                    <div
                        v-else-if="upgrades.length === 0"
                        class="py-8 text-center text-muted-foreground"
                    >
                        暂无可用的升级包
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="up in upgrades"
                            :key="textValue(up.id)"
                            class="flex items-center justify-between rounded-md border p-3"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">
                                    {{
                                        textValue(
                                            up.name ?? up.package_up_name,
                                        ) || `升级包 #${textValue(up.id)}`
                                    }}
                                </div>
                                <div
                                    class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                                >
                                    <span v-if="up.price"
                                        >价格: {{ textValue(up.price) }}</span
                                    >
                                    <span v-if="up.traffic || up.flow"
                                        >流量:
                                        {{
                                            textValue(up.traffic ?? up.flow)
                                        }}</span
                                    >
                                    <span v-if="up.bandwidth || up.bw"
                                        >带宽:
                                        {{
                                            textValue(up.bandwidth ?? up.bw)
                                        }}</span
                                    >
                                    <span v-if="up.connection || up.conn"
                                        >连接数:
                                        {{
                                            textValue(up.connection ?? up.conn)
                                        }}</span
                                    >
                                    <span v-if="up.domain"
                                        >域名数:
                                        {{ textValue(up.domain) }}</span
                                    >
                                </div>
                            </div>
                            <Button
                                size="sm"
                                :disabled="upgradeSubmitting"
                                @click="
                                    submitUpgrade(
                                        Number(up.id ?? up.package_up_id),
                                    )
                                "
                            >
                                <Spinner
                                    v-if="upgradeSubmitting"
                                    data-icon="inline-start"
                                />
                                <ArrowUpCircle
                                    v-else
                                    data-icon="inline-start"
                                />
                                购买
                            </Button>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="upgradeDialogOpen = false"
                        >关闭</Button
                    >
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- 套餐详情对话框 -->
        <Dialog v-model:open="usageDialogOpen">
            <DialogScrollContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>套餐详情</DialogTitle>
                </DialogHeader>

                <div class="flex gap-6 border-b">
                    <button
                        type="button"
                        class="-mb-px border-b-2 px-4 py-2 text-sm transition-colors"
                        :class="
                            usageTab === 'usage'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="usageTab = 'usage'"
                    >
                        使用情况
                    </button>
                    <button
                        type="button"
                        class="-mb-px border-b-2 px-4 py-2 text-sm transition-colors"
                        :class="
                            usageTab === 'details'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="usageTab = 'details'"
                    >
                        套餐详情
                    </button>
                </div>

                <div class="grid min-h-80 gap-4">
                    <Alert v-if="usageError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>请求失败</AlertTitle>
                        <AlertDescription>{{ usageError }}</AlertDescription>
                    </Alert>

                    <div v-if="usageLoading" class="flex justify-center py-8">
                        <Spinner />
                    </div>

                    <div
                        v-else-if="usageTab === 'usage'"
                        class="overflow-x-auto"
                    >
                        <table class="w-full text-sm">
                            <thead class="bg-muted/60 text-muted-foreground">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left font-medium"
                                    ></th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        总额度
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        已使用
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        剩余
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="metric in usageMetrics"
                                    :key="metric.label"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ metric.label }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ metric.total }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ metric.used }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ metric.remaining }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="space-y-6">
                        <dl
                            class="grid gap-x-10 gap-y-4 text-sm sm:grid-cols-2"
                        >
                            <div
                                v-for="item in packageDetailItems"
                                :key="item.label"
                                class="grid grid-cols-[120px_1fr] gap-3"
                            >
                                <dt class="text-right text-muted-foreground">
                                    {{ item.label }}：
                                </dt>
                                <dd class="min-w-0 break-words">
                                    {{ item.value }}
                                </dd>
                            </div>
                        </dl>

                        <div class="space-y-3">
                            <div class="flex items-center gap-4">
                                <div class="h-px flex-1 bg-border"></div>
                                <div class="text-sm font-medium">
                                    已购升级包
                                </div>
                                <div class="h-px flex-1 bg-border"></div>
                            </div>
                            <div class="overflow-x-auto border-y">
                                <table class="w-full text-sm">
                                    <thead
                                        class="bg-muted/60 text-muted-foreground"
                                    >
                                        <tr>
                                            <th
                                                class="px-4 py-3 text-left font-medium"
                                            >
                                                名称
                                            </th>
                                            <th
                                                class="px-4 py-3 text-left font-medium"
                                            >
                                                升级内容
                                            </th>
                                            <th
                                                class="px-4 py-3 text-left font-medium"
                                            >
                                                总数
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="upgrade in usageUpgrades"
                                            :key="
                                                textValue(
                                                    upgrade.id ??
                                                        upgrade.package_up_id,
                                                )
                                            "
                                            class="border-b last:border-b-0"
                                        >
                                            <td class="px-4 py-3">
                                                {{
                                                    textValue(
                                                        upgrade.name ??
                                                            upgrade.package_up_name,
                                                    ) || '-'
                                                }}
                                            </td>
                                            <td class="px-4 py-3">
                                                {{
                                                    textValue(
                                                        upgrade.des ??
                                                            upgrade.description ??
                                                            upgrade.type,
                                                    ) || '-'
                                                }}
                                            </td>
                                            <td class="px-4 py-3">
                                                {{
                                                    textValue(
                                                        upgrade.num ??
                                                            upgrade.count ??
                                                            upgrade.quantity,
                                                    ) || '1'
                                                }}
                                            </td>
                                        </tr>
                                        <tr v-if="usageUpgrades.length === 0">
                                            <td
                                                colspan="3"
                                                class="px-4 py-6 text-center text-muted-foreground"
                                            >
                                                暂无已购升级包
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="usageDialogOpen = false"
                        >关闭</Button
                    >
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
