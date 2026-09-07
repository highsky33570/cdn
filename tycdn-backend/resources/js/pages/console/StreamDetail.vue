<script setup lang="ts">
import {
    AlertCircle,
    Check,
    ChevronLeft,
    Copy,
    Network,
    Plus,
    RefreshCw,
    Save,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
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
    numberValue,
    parseJsonArray,
    parseJsonObject,
    textValue,
} from '@/lib/cdnRecord';
import {
    getUserStream,
    updateUserStream,
} from '@/lib/cdnUserApi';
import type { CdnflyRecord } from '@/lib/cdnUserApi';

const props = defineProps<{ streamId: string }>();

type ListenRow = { protocol: 'tcp' | 'udp'; port: string };
type BackendRow = { addr: string; weight: number; state: 'up' | 'down' | 'backup' };
type AclRule = { ip: string; action: 'allow' | 'deny' };

// 页面状态
const loading = ref(false);
const errorMessage = ref('');
const stream = ref<CdnflyRecord | null>(null);
const copied = ref(false);

// 各区块保存状态
const savingListen = ref(false);
const savingBackend = ref(false);
const savingAdvanced = ref(false);
const savingAcl = ref(false);

const errorListen = ref('');
const errorBackend = ref('');
const errorAdvanced = ref('');
const errorAcl = ref('');

const successListen = ref('');
const successBackend = ref('');
const successAdvanced = ref('');
const successAcl = ref('');

// 表单数据
const listenRows = ref<ListenRow[]>([]);
const backendRows = ref<BackendRow[]>([]);

const backendForm = reactive({
    backend_port: '',
    balance_way: 'ip_hash',
});

const advancedForm = reactive({
    proxy_protocol: '0',
    conn_limit: '',
    enable: '1',
});

const aclForm = reactive({
    default_action: 'allow' as 'allow' | 'deny',
    rules: [] as AclRule[],
});

// 计算属性
const streamId = computed(() => Number(props.streamId));

const cname = computed(() => {
    if (!stream.value) return '';
    const hostname = textValue(stream.value.cname_hostname);
    const domain = textValue(stream.value.cname_domain);
    return hostname && domain ? `${hostname}.${domain}` : '';
});

const streamState = computed(() => {
    if (!stream.value) return '-';
    return textValue(stream.value.stream_state ?? stream.value.state) || '-';
});

const stateVariant = computed(() => {
    return streamState.value === '200' ? 'default' : 'secondary';
});

const stateLabel = computed(() => {
    const map: Record<string, string> = {
        '200': '正常',
        '512': '套餐过期',
        '513': '流量超限',
        '514': '已锁定',
    };
    return map[streamState.value] ?? streamState.value;
});

onMounted(() => {
    void loadStream();
});

async function loadStream(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const data = await getUserStream(streamId.value);
        stream.value = data;
        populateForms(data);
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

function populateForms(data: CdnflyRecord): void {
    // 监听
    const parsedListen = parseJsonArray(data.listen).map((item) => {
        const r = item as Record<string, unknown>;
        return {
            protocol: (r.protocol === 'udp' ? 'udp' : 'tcp') as 'tcp' | 'udp',
            port: String(r.port ?? ''),
        };
    });
    listenRows.value = parsedListen.length > 0 ? parsedListen : [{ protocol: 'tcp', port: '' }];

    // 回源
    const parsedBackend = parseJsonArray(data.backend).map((item) => {
        const r = item as Record<string, unknown>;
        const stateVal = r.state as string;
        return {
            addr: String(r.addr ?? ''),
            weight: Number(r.weight ?? 1),
            state: (['up', 'down', 'backup'].includes(stateVal) ? stateVal : 'up') as BackendRow['state'],
        };
    });
    backendRows.value = parsedBackend.length > 0 ? parsedBackend : [{ addr: '', weight: 1, state: 'up' }];
    backendForm.backend_port = textValue(data.backend_port);
    backendForm.balance_way = textValue(data.balance_way) || 'ip_hash';

    // 高级
    advancedForm.proxy_protocol =
        data.proxy_protocol === 1 || data.proxy_protocol === true ? '1' : '0';
    advancedForm.conn_limit = textValue(data.conn_limit);
    advancedForm.enable = data.enable === 0 || data.enable === false ? '0' : '1';

    // ACL
    const acl = parseJsonObject(data.acl);
    aclForm.default_action =
        textValue(acl?.default_action) === 'deny' ? 'deny' : 'allow';
    const rules = Array.isArray(acl?.rule) ? (acl.rule as Array<Record<string, unknown>>) : [];
    aclForm.rules = rules.map((r) => ({
        ip: String(r.ip ?? ''),
        action: (r.action === 'deny' ? 'deny' : 'allow') as 'allow' | 'deny',
    }));
}

// 监听行操作
function addListenRow(): void {
    listenRows.value.push({ protocol: 'tcp', port: '' });
}
function removeListenRow(index: number): void {
    listenRows.value.splice(index, 1);
}

// 回源行操作
function addBackendRow(): void {
    backendRows.value.push({ addr: '', weight: 1, state: 'up' });
}
function removeBackendRow(index: number): void {
    backendRows.value.splice(index, 1);
}

// ACL 规则操作
function addAclRule(): void {
    aclForm.rules.push({ ip: '', action: 'deny' });
}
function removeAclRule(index: number): void {
    aclForm.rules.splice(index, 1);
}

// 保存各区块
async function saveListen(): Promise<void> {
    errorListen.value = '';
    successListen.value = '';

    for (const row of listenRows.value) {
        if (!row.port.trim()) {
            errorListen.value = '监听端口不能为空';
            return;
        }
    }

    savingListen.value = true;
    try {
        await updateUserStream(streamId.value, {
            listen: listenRows.value.map((r) => ({ protocol: r.protocol, port: r.port.trim() })),
        });
        successListen.value = '已保存';
        void loadStream();
    } catch (error) {
        errorListen.value = getErrorMessage(error);
    } finally {
        savingListen.value = false;
    }
}

async function saveBackend(): Promise<void> {
    errorBackend.value = '';
    successBackend.value = '';

    for (const row of backendRows.value) {
        if (!row.addr.trim()) {
            errorBackend.value = '回源地址不能为空';
            return;
        }
    }

    const backendPort = numberValue(backendForm.backend_port);
    if (backendPort === null) {
        errorBackend.value = '回源端口必须是数字';
        return;
    }

    savingBackend.value = true;
    try {
        await updateUserStream(streamId.value, {
            backend: backendRows.value.map((r) => ({ addr: r.addr.trim(), weight: r.weight, state: r.state })),
            backend_port: backendPort,
            balance_way: backendForm.balance_way,
        });
        successBackend.value = '已保存';
        void loadStream();
    } catch (error) {
        errorBackend.value = getErrorMessage(error);
    } finally {
        savingBackend.value = false;
    }
}

async function saveAdvanced(): Promise<void> {
    errorAdvanced.value = '';
    successAdvanced.value = '';
    savingAdvanced.value = true;

    try {
        const connLimit = numberValue(advancedForm.conn_limit);
        await updateUserStream(streamId.value, {
            proxy_protocol: advancedForm.proxy_protocol === '1',
            conn_limit: connLimit,
            enable: advancedForm.enable === '1',
        });
        successAdvanced.value = '已保存';
        void loadStream();
    } catch (error) {
        errorAdvanced.value = getErrorMessage(error);
    } finally {
        savingAdvanced.value = false;
    }
}

async function saveAcl(): Promise<void> {
    errorAcl.value = '';
    successAcl.value = '';

    for (const rule of aclForm.rules) {
        if (!rule.ip.trim()) {
            errorAcl.value = 'IP 不能为空';
            return;
        }
    }

    savingAcl.value = true;
    try {
        const aclPayload = {
            default_action: aclForm.default_action,
            rule: aclForm.rules.map((r) => ({ ip: r.ip.trim(), action: r.action })),
        };
        await updateUserStream(streamId.value, { acl: aclPayload });
        successAcl.value = '已保存';
        void loadStream();
    } catch (error) {
        errorAcl.value = getErrorMessage(error);
    } finally {
        savingAcl.value = false;
    }
}

async function copyCname(): Promise<void> {
    if (!cname.value) return;
    await navigator.clipboard.writeText(cname.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function goBack(): void {
    router.visit('/console/streams');
}
</script>

<template>
    <div class="space-y-6">
        <ConsolePageHeader
            eyebrow="用户端 / 四层转发"
            :title="`转发 #${streamId}`"
            description="查看和修改四层转发配置，各区块独立保存。"
            :icon="Network"
            :show-api-badge="false"
        />

        <!-- 返回 -->
        <div>
            <Button variant="ghost" size="sm" @click="goBack">
                <ChevronLeft data-icon="inline-start" />
                返回列表
            </Button>
        </div>

        <!-- 加载/错误 -->
        <div v-if="loading" class="flex justify-center py-16">
            <Spinner class="h-8 w-8" />
        </div>

        <Alert v-else-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>加载失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <template v-else-if="stream">
            <!-- 顶部状态栏 -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="pt-6">
                        <p class="text-xs text-muted-foreground">状态</p>
                        <div class="mt-1">
                            <Badge :variant="stateVariant">{{ stateLabel }}</Badge>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <p class="text-xs text-muted-foreground">套餐</p>
                        <p class="mt-1 font-medium">
                            {{ textValue(stream.package_name) || textValue(stream.user_package) || '-' }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <p class="text-xs text-muted-foreground">启用</p>
                        <p class="mt-1 font-medium">
                            {{ stream.enable === 1 || stream.enable === true ? '已启用' : '已禁用' }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <p class="text-xs text-muted-foreground">创建时间</p>
                        <p class="mt-1 text-sm">
                            {{ formatDate(stream.create_at2) || '-' }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- CNAME -->
            <Card>
                <CardHeader>
                    <CardTitle>CNAME 解析</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="cname" class="flex items-center gap-3">
                        <code class="flex-1 rounded-md bg-muted px-4 py-3 font-mono text-sm">
                            {{ cname }}
                        </code>
                        <Button variant="outline" size="sm" @click="copyCname">
                            <Check v-if="copied" data-icon="inline-start" class="text-green-600" />
                            <Copy v-else data-icon="inline-start" />
                            {{ copied ? '已复制' : '复制' }}
                        </Button>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        CNAME 尚未生成，请稍后刷新。
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        请将你的域名 CNAME 解析到此地址，CDNfly 节点将自动接管流量。
                    </p>
                </CardContent>
            </Card>

            <!-- 监听配置 -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>监听配置</CardTitle>
                    <div class="flex items-center gap-2">
                        <span v-if="successListen" class="text-sm text-green-600">{{ successListen }}</span>
                        <Button variant="outline" size="sm" @click="addListenRow">
                            <Plus data-icon="inline-start" />
                            添加
                        </Button>
                        <Button size="sm" :disabled="savingListen" @click="saveListen">
                            <Spinner v-if="savingListen" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            保存
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <Alert v-if="errorListen" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertDescription>{{ errorListen }}</AlertDescription>
                    </Alert>
                    <div class="rounded-md border">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium w-32">协议</th>
                                    <th class="px-3 py-2 text-left font-medium">端口</th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, index) in listenRows"
                                    :key="index"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-3 py-2">
                                        <Select v-model="row.protocol">
                                            <SelectTrigger class="h-8">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="tcp">TCP</SelectItem>
                                                    <SelectItem value="udp">UDP</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <Input
                                            v-model="row.port"
                                            class="h-8"
                                            inputmode="numeric"
                                            placeholder="如 80 或 80-90"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button
                                            type="button"
                                            class="text-muted-foreground hover:text-destructive"
                                            :disabled="listenRows.length <= 1"
                                            @click="removeListenRow(index)"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- 回源配置 -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>回源配置</CardTitle>
                    <div class="flex items-center gap-2">
                        <span v-if="successBackend" class="text-sm text-green-600">{{ successBackend }}</span>
                        <Button variant="outline" size="sm" @click="addBackendRow">
                            <Plus data-icon="inline-start" />
                            添加
                        </Button>
                        <Button size="sm" :disabled="savingBackend" @click="saveBackend">
                            <Spinner v-if="savingBackend" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            保存
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <Alert v-if="errorBackend" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertDescription>{{ errorBackend }}</AlertDescription>
                    </Alert>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="detail-backend-port">回源端口</Label>
                            <Input
                                id="detail-backend-port"
                                v-model="backendForm.backend_port"
                                inputmode="numeric"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>负载均衡</Label>
                            <Select v-model="backendForm.balance_way">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="ip_hash">IP Hash</SelectItem>
                                        <SelectItem value="rr">轮询 (RR)</SelectItem>
                                        <SelectItem value="least_conn">最小连接</SelectItem>
                                        <SelectItem value="random">随机</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="rounded-md border">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">地址</th>
                                    <th class="px-3 py-2 text-left font-medium w-24">权重</th>
                                    <th class="px-3 py-2 text-left font-medium w-32">状态</th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, index) in backendRows"
                                    :key="index"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-3 py-2">
                                        <Input v-model="row.addr" class="h-8" placeholder="如 1.2.3.4" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <Input
                                            v-model.number="row.weight"
                                            class="h-8"
                                            type="number"
                                            min="1"
                                            max="100"
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        <Select v-model="row.state">
                                            <SelectTrigger class="h-8">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="up">up（正常）</SelectItem>
                                                    <SelectItem value="down">down（停用）</SelectItem>
                                                    <SelectItem value="backup">backup（备用）</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button
                                            type="button"
                                            class="text-muted-foreground hover:text-destructive"
                                            :disabled="backendRows.length <= 1"
                                            @click="removeBackendRow(index)"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- 高级设置 -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>高级设置</CardTitle>
                    <div class="flex items-center gap-2">
                        <span v-if="successAdvanced" class="text-sm text-green-600">{{ successAdvanced }}</span>
                        <Button size="sm" :disabled="savingAdvanced" @click="saveAdvanced">
                            <Spinner v-if="savingAdvanced" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            保存
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <Alert v-if="errorAdvanced" variant="destructive" class="mb-4">
                        <AlertCircle data-icon="alert" />
                        <AlertDescription>{{ errorAdvanced }}</AlertDescription>
                    </Alert>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label>启用状态</Label>
                            <Select v-model="advancedForm.enable">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="1">启用</SelectItem>
                                        <SelectItem value="0">禁用</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Proxy Protocol</Label>
                            <Select v-model="advancedForm.proxy_protocol">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="0">关闭</SelectItem>
                                        <SelectItem value="1">开启</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="detail-conn-limit">连接数限制</Label>
                            <Input
                                id="detail-conn-limit"
                                v-model="advancedForm.conn_limit"
                                inputmode="numeric"
                                placeholder="不限制"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ACL -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>访问控制（ACL）</CardTitle>
                    <div class="flex items-center gap-2">
                        <span v-if="successAcl" class="text-sm text-green-600">{{ successAcl }}</span>
                        <Button variant="outline" size="sm" @click="addAclRule">
                            <Plus data-icon="inline-start" />
                            添加规则
                        </Button>
                        <Button size="sm" :disabled="savingAcl" @click="saveAcl">
                            <Spinner v-if="savingAcl" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            保存
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <Alert v-if="errorAcl" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertDescription>{{ errorAcl }}</AlertDescription>
                    </Alert>
                    <div class="grid gap-2">
                        <Label>默认动作</Label>
                        <Select v-model="aclForm.default_action">
                            <SelectTrigger class="w-40"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="allow">允许（allow）</SelectItem>
                                    <SelectItem value="deny">拒绝（deny）</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            未匹配规则的 IP 将执行此默认动作。
                        </p>
                    </div>
                    <div v-if="aclForm.rules.length > 0" class="rounded-md border">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">IP 地址</th>
                                    <th class="px-3 py-2 text-left font-medium w-36">动作</th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(rule, index) in aclForm.rules"
                                    :key="index"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-3 py-2">
                                        <Input
                                            v-model="rule.ip"
                                            class="h-8 font-mono"
                                            placeholder="如 1.2.3.4 或 1.2.3.0/24"
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        <Select v-model="rule.action">
                                            <SelectTrigger class="h-8">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="allow">允许</SelectItem>
                                                    <SelectItem value="deny">拒绝</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button
                                            type="button"
                                            class="text-muted-foreground hover:text-destructive"
                                            @click="removeAclRule(index)"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        暂无规则，所有 IP 将执行默认动作。
                    </p>
                </CardContent>
            </Card>

            <!-- 刷新 -->
            <div class="flex justify-end">
                <Button variant="outline" :disabled="loading" @click="loadStream">
                    <RefreshCw data-icon="inline-start" :class="{ 'animate-spin': loading }" />
                    重新拉取
                </Button>
            </div>
        </template>
    </div>
</template>
