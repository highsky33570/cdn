<script setup lang="ts">
import {
    AlertCircle,
    ArrowUpCircle,
    BarChart3,
    CreditCard,
    ExternalLink,
    Package,
    ReceiptText,
    RefreshCw,
    Save,
    Search,
    ShoppingCart,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner'
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

type BillingView = 'packages' | 'subscriptions' | 'orders' | 'traffic-packs' | 'usage';

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
const services = ref<LocalBillingServiceInstance[]>([]);
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
const usageLoading = ref(false);
const usageError = ref('');

const filters = reactive({
    search: '',
    status: 'all',
    per_page: '20',
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
        subscriptions: '展示本地服务实例状态，已支付但未开通的套餐会显示为待同步。',
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
    if (props.view === 'packages') return products.value;
    if (props.view === 'subscriptions') return services.value;
    if (props.view === 'orders') return orders.value;
    if (props.view === 'usage') return userPackages.value;
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
        const params = basePageParams(targetPage);
        const search = filters.search.trim();

        if (search !== '') {
            params.search = search;
        }

        const result = await listBillingServiceInstances(params);

        services.value = result.items;
        total.value = result.total;
        page.value = result.page;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
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

async function openUpgradeDialog(service: LocalBillingServiceInstance): Promise<void> {
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

    if (!pkgId) return;

    upgradeSubmitting.value = true;
    upgradeError.value = '';

    try {
        await purchaseUserPackageUpgrade(pkgId, { package_up_id: packageUpId });
        toast.success('升级购买成功');
        upgradeDialogOpen.value = false;
        if (props.view === 'subscriptions') await loadServices();
    } catch (error) {
        upgradeError.value = getErrorMessage(error);
    } finally {
        upgradeSubmitting.value = false;
    }
}

async function openUsageDialog(record: CdnflyRecord): Promise<void> {
    const pkgId = Number(record.id);

    if (!pkgId) return;

    usageTarget.value = record;
    usageData.value = [];
    usageError.value = '';
    usageDialogOpen.value = true;
    usageLoading.value = true;

    try {
        const result = await getUserPackageUsage(pkgId);
        usageData.value = extractCdnflyRows(result);

        if (usageData.value.length === 0 && result && typeof result === 'object') {
            const flat = { ...result } as CdnflyRecord;
            delete flat.code;
            delete flat.msg;
            delete flat.message;
            if (Object.keys(flat).length > 0) {
                usageData.value = [flat];
            }
        }
    } catch (error) {
        usageError.value = getErrorMessage(error);
    } finally {
        usageLoading.value = false;
    }
}

function usageLabel(key: string): string {
    const labels: Record<string, string> = {
        traffic: '流量', flow: '流量', bandwidth: '带宽', bw: '带宽',
        connection: '连接数', conn: '连接数', domain: '域名数',
        site: '站点数', sites: '站点数', request: '请求数',
        used_traffic: '已用流量', total_traffic: '总流量',
        used_bandwidth: '已用带宽', total_bandwidth: '总带宽',
        used_connection: '已用连接数', total_connection: '总连接数',
        used_domain: '已用域名数', total_domain: '总域名数',
        expire_time: '到期时间', start_time: '开始时间',
    };
    return labels[key] ?? key;
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
        toast.success(`订单已创建并开通成功`);
            nextStatus === 'success'
                ? '已提交开通请求'
                : nextStatus === 'queued'
                  ? '已记录待开通状态，系统将稍后自动同步'
                  : '已触发开通重试';
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
                    <CardTitle class="text-base">{{ title }}</CardTitle>
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
                    v-else
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
                        <colgroup>
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
                                <th class="px-4 py-3 text-left font-medium">实例</th>
                                <th class="px-4 py-3 text-left font-medium">商品</th>
                                <th class="px-4 py-3 text-left font-medium">订单</th>
                                <th class="px-4 py-3 text-left font-medium">状态</th>
                                <th class="px-4 py-3 text-left font-medium">最近更新时间</th>
                                <th class="px-4 py-3 text-right font-medium">操作</th>
                            </tr>
                            <tr v-else-if="props.view === 'usage'">
                                <th class="px-4 py-3 text-left font-medium">套餐</th>
                                <th class="px-4 py-3 text-left font-medium">分组</th>
                                <th class="px-4 py-3 text-left font-medium">周期</th>
                                <th class="px-4 py-3 text-left font-medium">到期时间</th>
                                <th class="px-4 py-3 text-left font-medium">状态</th>
                                <th class="px-4 py-3 text-right font-medium">操作</th>
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
                                <td class="px-6 py-16 text-center" colspan="6">
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
                                    <td class="px-4 py-3">
                                        <div class="truncate font-medium">
                                            {{
                                                service.service_name ||
                                                `service-${service.id}`
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            #{{ service.id }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="truncate font-medium">
                                            {{ service.product_name ?? '-' }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ service.product_slug ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ service.order_no ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1">
                                            <Badge variant="secondary">{{
                                                serviceStatusText(service)
                                            }}</Badge>
                                            <span
                                                v-if="service.queue_reason"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ service.queue_reason }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{
                                            formatDate(
                                                service.opened_at ??
                                                    service.last_attempt_at,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <Button
                                                v-if="service.cdnfly_service_id && service.status === 'active'"
                                                variant="outline"
                                                size="sm"
                                                @click="openUpgradeDialog(service)"
                                            >
                                                <Zap data-icon="inline-start" />
                                                升级
                                            </Button>
                                            <Button
                                                v-if="service.product_id"
                                                variant="outline"
                                                size="sm"
                                                @click="openRenewDialog(service)"
                                            >
                                                <ShoppingCart data-icon="inline-start" />
                                                续费
                                            </Button>
                                            <Badge v-if="!service.product_id && !service.cdnfly_service_id" variant="outline">只读</Badge>
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
                                            {{ textValue(pkg.name ?? pkg.package_name) || '-' }}
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            #{{ textValue(pkg.id) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ textValue(pkg.package_group_name ?? pkg.group_name) || '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ textValue(pkg.duration) || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(pkg.expire_time ?? pkg.expired_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge variant="secondary">
                                            {{ textValue(pkg.status ?? pkg.enable) || '-' }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openUsageDialog(pkg)"
                                        >
                                            <BarChart3 data-icon="inline-start" />
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
                                    colspan="6"
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
                        为「{{ upgradeTarget?.service_name || upgradeTarget?.product_name || '-' }}」选择一个升级包。
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

                    <div v-else-if="upgrades.length === 0" class="py-8 text-center text-muted-foreground">
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
                                    {{ textValue(up.name ?? up.package_up_name) || `升级包 #${textValue(up.id)}` }}
                                </div>
                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    <span v-if="up.price">价格: {{ textValue(up.price) }}</span>
                                    <span v-if="up.traffic || up.flow">流量: {{ textValue(up.traffic ?? up.flow) }}</span>
                                    <span v-if="up.bandwidth || up.bw">带宽: {{ textValue(up.bandwidth ?? up.bw) }}</span>
                                    <span v-if="up.connection || up.conn">连接数: {{ textValue(up.connection ?? up.conn) }}</span>
                                    <span v-if="up.domain">域名数: {{ textValue(up.domain) }}</span>
                                </div>
                            </div>
                            <Button
                                size="sm"
                                :disabled="upgradeSubmitting"
                                @click="submitUpgrade(Number(up.id ?? up.package_up_id))"
                            >
                                <Spinner v-if="upgradeSubmitting" data-icon="inline-start" />
                                <ArrowUpCircle v-else data-icon="inline-start" />
                                购买
                            </Button>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="upgradeDialogOpen = false">关闭</Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- 用量详情对话框 -->
        <Dialog v-model:open="usageDialogOpen">
            <DialogScrollContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>用量详情</DialogTitle>
                    <DialogDescription>
                        「{{ textValue(usageTarget?.name ?? usageTarget?.package_name) || '-' }}」的资源使用情况
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4">
                    <Alert v-if="usageError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>请求失败</AlertTitle>
                        <AlertDescription>{{ usageError }}</AlertDescription>
                    </Alert>

                    <div v-if="usageLoading" class="flex justify-center py-8">
                        <Spinner />
                    </div>

                    <div v-else-if="usageData.length === 0" class="py-8 text-center text-muted-foreground">
                        暂无用量数据
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="(record, idx) in usageData"
                            :key="idx"
                            class="overflow-x-auto rounded-md border"
                        >
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr
                                        v-for="(val, key) in record"
                                        :key="String(key)"
                                        class="border-b last:border-b-0"
                                    >
                                        <td class="whitespace-nowrap px-4 py-2 font-medium text-muted-foreground" style="width: 40%">
                                            {{ usageLabel(String(key)) }}
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ textValue(val) || '-' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="usageDialogOpen = false">关闭</Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
