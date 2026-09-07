<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    Globe2,
    Loader2,
    RefreshCw,
    ShieldCheck,
    Wifi,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { getErrorMessage, parseJsonArray, parseJsonObject, textValue } from '@/lib/cdnRecord';
import { getUserSite } from '@/lib/cdnUserApi';
import type { CdnflyRecord } from '@/lib/cdnUserApi';
import { cn } from '@/lib/utils';

const props = defineProps<{
    siteId?: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Console', href: '/console' },
            { title: '网站管理', href: '/console/sites' },
        ],
    },
});

// ── 状态 ──────────────────────────────────────────────
const loading = ref(false);
const refreshing = ref(false);
const errorMessage = ref('');
const site = ref<CdnflyRecord | null>(null);

const tabs = [
    { key: 'overview', label: '概览' },
    { key: 'origin',   label: '源站' },
    { key: 'http',     label: 'HTTP/HTTPS' },
    { key: 'cache',    label: '缓存' },
    { key: 'security', label: '安全' },
    { key: 'advanced', label: '高级' },
] as const;
type TabKey = (typeof tabs)[number]['key'];
const activeTab = ref<TabKey>('overview');

// ── 计算属性（全部基于真实 API 字段）──────────────────
const domain        = computed(() => textValue(site.value?.domain) || '-');
const siteName      = computed(() => textValue(site.value?.name) || domain.value);
const enable        = computed(() => site.value?.enable === 1 || site.value?.enable === true);
const siteState     = computed(() => textValue(site.value?.site_state ?? site.value?.state) || '-');
const syncState     = computed(() => textValue(site.value?.sync_state) || '-');
const packageName   = computed(() => textValue(site.value?.package_name) || '-');
const endAt         = computed(() => textValue(site.value?.end_at2 ?? site.value?.end_at) || '-');
const nodeGroupName = computed(() => textValue(site.value?.node_group_name) || '-');
const cnameHostname = computed(() => textValue(site.value?.cname_hostname) || '');
const cnameDomain   = computed(() => textValue(site.value?.cname_domain) || '');
const cname         = computed(() =>
    cnameHostname.value && cnameDomain.value
        ? `${cnameHostname.value}.${cnameDomain.value}`
        : '-',
);
const cnameState  = computed(() => textValue(site.value?.cname_state) || '-');
const regionName  = computed(() => textValue(site.value?.region_name) || '-');
const enableIPv6  = computed(() => site.value?.enable_ipv6 === 1);

// HTTP/HTTPS 监听（均为 JSON 字符串）
const httpListen   = computed(() => parseJsonObject(textValue(site.value?.http_listen) ?? '') as CdnflyRecord | null);
const httpsListen  = computed(() => parseJsonObject(textValue(site.value?.https_listen) ?? '') as CdnflyRecord | null);
const httpPort     = computed(() => textValue(httpListen.value?.port) || '80');
const httpsPort    = computed(() => textValue(httpsListen.value?.port) || '443');
const forceSSL     = computed(() => httpsListen.value?.force_ssl_enable === 1);
const http2        = computed(() => httpsListen.value?.http2 === 1);
const http3        = computed(() => httpsListen.value?.http3 === 1);
const hsts         = computed(() => httpsListen.value?.hsts === 1);
const ocspStapling = computed(() => httpsListen.value?.ocsp_stapling === 1);
const sslProtocols = computed(() => textValue(httpsListen.value?.ssl_protocols) || '-');
const certId       = computed(() => textValue(httpsListen.value?.cert) || '-');

// 源站
const backendProtocol  = computed(() => textValue(site.value?.backend_protocol) || '-');
const backendHost      = computed(() => textValue(site.value?.backend_host) || '-');
const backendHttpPort  = computed(() => textValue(site.value?.backend_http_port) || '80');
const backendHttpsPort = computed(() => textValue(site.value?.backend_https_port) || '443');
const backendList      = computed(() => {
    const raw = textValue(site.value?.backend);
    if (!raw) return [] as CdnflyRecord[];
    return parseJsonArray(raw) as CdnflyRecord[];
});
const balanceWay       = computed(() => textValue(site.value?.balance_way) || '-');
const proxyTimeout     = computed(() => textValue(site.value?.proxy_timeout) || '-');
const proxyConnTimeout = computed(() => textValue(site.value?.proxy_connect_timeout) || '-');

// 功能开关
const gzipEnable     = computed(() => site.value?.gzip_enable === 1);
const websocketEnable = computed(() => site.value?.websocket_enable === 1);
const rangeEnable    = computed(() => site.value?.range === 1);

function stateVariant(state: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (state === '200' || state === 'done' || state === 'running') return 'default';
    if (state === 'pending' || state === 'syncing') return 'secondary';
    return 'destructive';
}

// ── 数据加载 ──────────────────────────────────────────
async function loadSite(isRefresh = false): Promise<void> {
    const idNum = Number(props.siteId);
    if (!idNum) {
        errorMessage.value = '无效的站点 ID';
        return;
    }
    if (isRefresh) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }
    errorMessage.value = '';
    try {
        site.value = await getUserSite(idNum);
    } catch (err) {
        errorMessage.value = getErrorMessage(err);
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
}

onMounted(() => { void loadSite(); });
</script>

<template>
    <Head :title="`站点详情 - ${siteName}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- 返回 -->
        <div>
            <Button variant="ghost" size="sm" as-child>
                <Link href="/console/sites">
                    <ArrowLeft data-icon="inline-start" />
                    返回网站列表
                </Link>
            </Button>
        </div>

        <!-- 页头 -->
        <ConsolePageHeader
            eyebrow="网站详情"
            :title="loading ? (props.siteId ?? '-') : siteName"
            :description="loading ? '加载中…' : domain"
            :show-api-badge="false"
        />

        <!-- 错误 -->
        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>加载失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <!-- 全屏 loading -->
        <div v-if="loading" class="flex items-center justify-center py-24">
            <Spinner />
        </div>

        <template v-else-if="site">
            <!-- ── 指标卡片 ── -->
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <!-- 站点状态 -->
                <Card>
                    <CardContent class="flex items-start gap-3 pt-5">
                        <div class="mt-0.5 rounded-md bg-muted p-2">
                            <Wifi class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm text-muted-foreground">站点状态</p>
                            <div class="mt-1 flex items-center gap-2">
                                <CheckCircle2 v-if="siteState === '200'" class="h-4 w-4 text-green-500" />
                                <XCircle v-else class="h-4 w-4 text-destructive" />
                                <span class="font-semibold">{{ siteState === '200' ? '正常' : siteState }}</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ enable ? '已启用' : '已停用' }}
                                · 同步：{{ syncState === 'done' ? '完成' : syncState }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- CNAME -->
                <Card>
                    <CardContent class="flex items-start gap-3 pt-5">
                        <div class="mt-0.5 rounded-md bg-muted p-2">
                            <Globe2 class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm text-muted-foreground">CNAME</p>
                            <p class="mt-1 truncate font-mono text-sm font-semibold">{{ cname }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                解析：{{ cnameState === 'done' ? '已完成' : cnameState }}
                                · 区域：{{ regionName }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- 套餐 -->
                <Card>
                    <CardContent class="flex items-start gap-3 pt-5">
                        <div class="mt-0.5 rounded-md bg-muted p-2">
                            <ShieldCheck class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm text-muted-foreground">当前套餐</p>
                            <p class="mt-1 font-semibold">{{ packageName }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">节点组：{{ nodeGroupName }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- 到期 -->
                <Card>
                    <CardContent class="flex items-start gap-3 pt-5">
                        <div class="mt-0.5 rounded-md bg-muted p-2">
                            <Clock class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm text-muted-foreground">套餐到期</p>
                            <p class="mt-1 font-semibold">{{ endAt !== '-' ? endAt.slice(0, 10) : '-' }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ endAt }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Tab 卡片 ── -->
            <Card class="gap-0 overflow-hidden">
                <!-- Tab 导航 -->
                <div class="flex gap-1 overflow-x-auto border-b bg-muted/40 px-4 pt-3">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        :class="cn(
                            'shrink-0 rounded-t-md px-4 py-2 text-sm font-medium transition-colors',
                            activeTab === tab.key
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                        )"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <CardContent class="p-6">
                    <!-- 刷新按钮 -->
                    <div class="mb-5 flex justify-end">
                        <Button variant="outline" size="sm" :disabled="refreshing" @click="loadSite(true)">
                            <Loader2 v-if="refreshing" data-icon="inline-start" class="animate-spin" />
                            <RefreshCw v-else data-icon="inline-start" />
                            重新拉取
                        </Button>
                    </div>

                    <!-- ── 概览 ── -->
                    <div v-if="activeTab === 'overview'" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-md border p-4 space-y-1">
                            <p class="text-xs text-muted-foreground">域名</p>
                            <p class="font-mono text-sm font-medium break-all">{{ domain }}</p>
                        </div>
                        <div class="rounded-md border p-4 space-y-1">
                            <p class="text-xs text-muted-foreground">站点 ID</p>
                            <p class="font-mono text-sm font-medium">{{ textValue(site.id) || '-' }}</p>
                        </div>
                        <div class="rounded-md border p-4 space-y-1">
                            <p class="text-xs text-muted-foreground">启用状态</p>
                            <Badge :variant="enable ? 'default' : 'secondary'">{{ enable ? '已启用' : '已停用' }}</Badge>
                        </div>
                        <div class="rounded-md border p-4 space-y-1">
                            <p class="text-xs text-muted-foreground">节点组</p>
                            <p class="text-sm font-medium">{{ nodeGroupName }}</p>
                        </div>
                        <div class="rounded-md border p-4 space-y-1">
                            <p class="text-xs text-muted-foreground">同步状态</p>
                            <Badge :variant="stateVariant(syncState)">{{ syncState }}</Badge>
                        </div>
                        <div class="rounded-md border p-4 space-y-1">
                            <p class="text-xs text-muted-foreground">IPv6</p>
                            <Badge :variant="enableIPv6 ? 'default' : 'outline'">{{ enableIPv6 ? '已启用' : '未启用' }}</Badge>
                        </div>
                        <div class="rounded-md border p-4 space-y-1 sm:col-span-2 xl:col-span-3">
                            <p class="text-xs text-muted-foreground">CNAME 记录（将你的域名 CNAME 指向此地址）</p>
                            <p class="font-mono text-sm font-medium break-all">{{ cname }}</p>
                        </div>
                    </div>

                    <!-- ── 源站 ── -->
                    <div v-else-if="activeTab === 'origin'" class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">回源协议</p>
                                <p class="text-sm font-medium uppercase">{{ backendProtocol }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">回源 Host</p>
                                <p class="font-mono text-sm font-medium break-all">{{ backendHost }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">负载均衡</p>
                                <p class="text-sm font-medium">{{ balanceWay }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">HTTP 回源端口</p>
                                <p class="font-mono text-sm font-medium">{{ backendHttpPort }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">HTTPS 回源端口</p>
                                <p class="font-mono text-sm font-medium">{{ backendHttpsPort }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">回源超时 / 连接超时</p>
                                <p class="text-sm font-medium">{{ proxyTimeout }}s / {{ proxyConnTimeout }}s</p>
                            </div>
                        </div>

                        <!-- 源站节点列表 -->
                        <div v-if="backendList.length > 0">
                            <p class="mb-2 text-sm font-medium">源站节点</p>
                            <div class="overflow-x-auto rounded-md border">
                                <table class="w-full text-sm">
                                    <thead class="border-b bg-muted/40 text-muted-foreground">
                                        <tr>
                                            <th class="px-4 py-2 text-left font-medium">地址</th>
                                            <th class="px-4 py-2 text-left font-medium">权重</th>
                                            <th class="px-4 py-2 text-left font-medium">状态</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(node, i) in backendList"
                                            :key="i"
                                            class="border-b last:border-0"
                                        >
                                            <td class="px-4 py-2 font-mono">{{ textValue(node.addr) || '-' }}</td>
                                            <td class="px-4 py-2">{{ textValue(node.weight) ?? 1 }}</td>
                                            <td class="px-4 py-2">
                                                <Badge :variant="textValue(node.state) === 'up' ? 'default' : 'destructive'">
                                                    {{ textValue(node.state) || '-' }}
                                                </Badge>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">暂无源站节点数据</p>
                    </div>

                    <!-- ── HTTP/HTTPS ── -->
                    <div v-else-if="activeTab === 'http'" class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">HTTP 监听端口</p>
                                <p class="font-mono text-sm font-medium">{{ httpPort }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">HTTPS 监听端口</p>
                                <p class="font-mono text-sm font-medium">{{ httpsPort }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">绑定证书 ID</p>
                                <p class="font-mono text-sm font-medium">{{ certId }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">强制 HTTPS</p>
                                <Badge :variant="forceSSL ? 'default' : 'outline'">{{ forceSSL ? '已开启' : '未开启' }}</Badge>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">HTTP/2</p>
                                <Badge :variant="http2 ? 'default' : 'outline'">{{ http2 ? '已开启' : '未开启' }}</Badge>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">HTTP/3 (QUIC)</p>
                                <Badge :variant="http3 ? 'default' : 'outline'">{{ http3 ? '已开启' : '未开启' }}</Badge>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">HSTS</p>
                                <Badge :variant="hsts ? 'default' : 'outline'">{{ hsts ? '已开启' : '未开启' }}</Badge>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">OCSP Stapling</p>
                                <Badge :variant="ocspStapling ? 'default' : 'outline'">{{ ocspStapling ? '已开启' : '未开启' }}</Badge>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">Gzip 压缩</p>
                                <Badge :variant="gzipEnable ? 'default' : 'outline'">{{ gzipEnable ? '已开启' : '未开启' }}</Badge>
                            </div>
                        </div>
                        <div class="rounded-md border p-4 space-y-1">
                            <p class="text-xs text-muted-foreground">TLS 协议版本</p>
                            <p class="font-mono text-xs text-muted-foreground break-all">{{ sslProtocols }}</p>
                        </div>
                    </div>

                    <!-- ── 缓存 ── -->
                    <div v-else-if="activeTab === 'cache'" class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">Gzip</p>
                                <Badge :variant="gzipEnable ? 'default' : 'outline'">{{ gzipEnable ? '已开启' : '未开启' }}</Badge>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">Range 回源</p>
                                <Badge :variant="rangeEnable ? 'default' : 'outline'">{{ rangeEnable ? '已开启' : '未开启' }}</Badge>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">WebSocket</p>
                                <Badge :variant="websocketEnable ? 'default' : 'outline'">{{ websocketEnable ? '已开启' : '未开启' }}</Badge>
                            </div>
                        </div>
                        <div class="rounded-md border p-4 space-y-1">
                            <p class="text-xs text-muted-foreground mb-1">Gzip 压缩类型</p>
                            <p class="font-mono text-xs text-muted-foreground break-all">
                                {{ textValue(site.gzip_types) || '-' }}
                            </p>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            缓存规则配置功能即将上线。
                        </p>
                    </div>

                    <!-- ── 安全 ── -->
                    <div v-else-if="activeTab === 'security'" class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">CC 默认规则 ID</p>
                                <p class="font-mono text-sm font-medium">{{ textValue(site.cc_default_rule) || '-' }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">蜘蛛策略</p>
                                <p class="text-sm font-medium">{{ textValue(site.spider_allow) || '-' }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">ACL 规则组</p>
                                <p class="text-sm font-medium">{{ textValue(site.acl) || '未绑定' }}</p>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">IP 黑名单</p>
                                <p class="font-mono text-xs text-muted-foreground break-all">{{ textValue(site.black_ip) || '（空）' }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">IP 白名单</p>
                                <p class="font-mono text-xs text-muted-foreground break-all">{{ textValue(site.white_ip) || '（空）' }}</p>
                            </div>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            ACL / CC 规则绑定编辑，后续在此 Tab 补充。
                        </p>
                    </div>

                    <!-- ── 高级 ── -->
                    <div v-else-if="activeTab === 'advanced'" class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">回源 HTTP 版本</p>
                                <p class="font-mono text-sm font-medium">{{ textValue(site.proxy_http_version) || '-' }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">回源 SSL 协议</p>
                                <p class="font-mono text-sm font-medium">{{ textValue(site.proxy_ssl_protocols) || '-' }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">Keepalive 连接数</p>
                                <p class="font-mono text-sm font-medium">{{ textValue(site.ups_keepalive_conn) || '-' }}</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">Keepalive 超时</p>
                                <p class="font-mono text-sm font-medium">{{ textValue(site.ups_keepalive_timeout) ?? '-' }}s</p>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">记录请求头</p>
                                <Badge :variant="site.log_req_header === 1 ? 'default' : 'outline'">
                                    {{ site.log_req_header === 1 ? '是' : '否' }}
                                </Badge>
                            </div>
                            <div class="rounded-md border p-4 space-y-1">
                                <p class="text-xs text-muted-foreground">版本号</p>
                                <p class="font-mono text-sm font-medium">v{{ textValue(site.version) || '-' }}</p>
                            </div>
                        </div>
                        <div class="rounded-md border p-4 space-y-1">
                            <p class="text-xs text-muted-foreground mb-1">Record ID</p>
                            <p class="font-mono text-xs text-muted-foreground break-all">{{ textValue(site.record_id) || '-' }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- 空态 -->
        <div v-else-if="!loading && !errorMessage" class="py-16 text-center text-sm text-muted-foreground">
            未找到站点数据
        </div>
    </div>
</template>
