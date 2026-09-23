<script setup lang="ts">
import { AlertCircle, Save } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import AdminStreamWorkspace from '@/components/console/AdminStreamWorkspace.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { createAdminStream, updateAdminStream } from '@/lib/adminModulesApi';
import type { AdminStreamPayload, CdnflyRecord } from '@/lib/adminModulesApi';
import { apiRequest } from '@/lib/apiRequest';
import { cdnflyJsonRows, extractCdnflyRows } from '@/lib/cdnflyResponse';

// ─── Helpers ─────────────────────────────────────────
function asNumber(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string') {
        const parsed = Number(value);

        if (Number.isFinite(parsed)) {
            return parsed;
        }
    }

    return null;
}

function textValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}

function getErrorMessage(error: unknown): string {
    return error instanceof Error ? error.message : '请求失败';
}

const workspace = ref<InstanceType<typeof AdminStreamWorkspace> | null>(null);

// ─── Streams – create / edit ────────────────────────
const streamDialogOpen = ref(false);
const streamSaving = ref(false);
const streamFormError = ref('');
const defaultsLoading = ref(false);
let defaultsRequest = 0;
async function applyDefaults() {
    const token = ++defaultsRequest;
    const uid = Number(streamForm.uid);

    if (editingStream.value || !Number.isInteger(uid) || uid < 1) {
        return;
    }

    defaultsLoading.value = true;
    streamFormError.value = '';

    try {
        const data = await apiRequest(
            `/api/admin/stream-defaults?uid=${uid}&scope_name=global&scope_id=0&limit=0`,
        );

        if (
            token !== defaultsRequest ||
            editingStream.value ||
            Number(streamForm.uid) !== uid ||
            !streamDialogOpen.value
        ) {
            return;
        }

        streamForm.listen_protocol = 'tcp';
        streamForm.balance_way = 'rr';
        streamForm.proxy_protocol = false;

        for (const row of extractCdnflyRows(data)) {
            if (
                Number(row.uid) !== uid ||
                row.scope_name !== 'global' ||
                row.enable === 0 ||
                row.enable === '0'
            ) {
                continue;
            }

            const value = String(row.value);

            if (
                row.name === 'listen_protocol' &&
                ['tcp', 'udp'].includes(value)
            ) {
                streamForm.listen_protocol = value;
            }

            if (
                row.name === 'balance_way' &&
                ['rr', 'ip_hash'].includes(value)
            ) {
                streamForm.balance_way = value;
            }

            if (row.name === 'proxy_protocol') {
                streamForm.proxy_protocol = value === '1';
            }
        }
    } catch (error) {
        if (token === defaultsRequest) {
            streamFormError.value = getErrorMessage(error);
        }
    } finally {
        if (token === defaultsRequest) {
            defaultsLoading.value = false;
        }
    }
}
const editingStream = ref<CdnflyRecord | null>(null);

const streamForm = reactive({
    uid: '',
    user_package: '',
    listen_protocol: 'tcp',
    listen_port: '',
    backend_addr: '',
    backend_port: '',
    balance_way: 'rr',
    proxy_protocol: false,
    conn_limit: '',
    enable: true,
});

function resetStreamForm(): void {
    defaultsRequest++;
    defaultsLoading.value = false;
    streamForm.uid = '';
    streamForm.user_package = '';
    streamForm.listen_protocol = 'tcp';
    streamForm.listen_port = '';
    streamForm.backend_addr = '';
    streamForm.backend_port = '';
    streamForm.balance_way = 'rr';
    streamForm.proxy_protocol = false;
    streamForm.conn_limit = '';
    streamForm.enable = true;
}

function openAddStream(): void {
    editingStream.value = null;
    resetStreamForm();
    streamFormError.value = '';
    streamDialogOpen.value = true;
}

function openEditStream(row: CdnflyRecord): void {
    editingStream.value = row;
    streamForm.uid = textValue(row.uid ?? row.user_id);
    streamForm.user_package = textValue(row.user_package);

    // Parse listen JSON
    try {
        const listenArr =
            typeof row.listen === 'string'
                ? JSON.parse(row.listen)
                : row.listen;

        if (Array.isArray(listenArr) && listenArr.length > 0) {
            streamForm.listen_protocol = listenArr[0].protocol || 'tcp';
            streamForm.listen_port = textValue(listenArr[0].port);
        } else {
            streamForm.listen_protocol = 'tcp';
            streamForm.listen_port = '';
        }
    } catch {
        streamForm.listen_protocol = 'tcp';
        streamForm.listen_port = '';
    }

    // Parse backend JSON
    try {
        const backendArr =
            typeof row.backend === 'string'
                ? JSON.parse(row.backend)
                : row.backend;

        if (Array.isArray(backendArr) && backendArr.length > 0) {
            streamForm.backend_addr = textValue(backendArr[0].addr);
        } else {
            streamForm.backend_addr = '';
        }
    } catch {
        streamForm.backend_addr = '';
    }

    streamForm.backend_port = textValue(row.backend_port);
    streamForm.balance_way = textValue(row.balance_way) || 'rr';
    streamForm.proxy_protocol =
        row.proxy_protocol === true ||
        row.proxy_protocol === 1 ||
        row.proxy_protocol === '1';
    streamForm.conn_limit = textValue(row.conn_limit);
    streamForm.enable =
        row.enable === 1 || row.enable === '1' || row.enable === true;
    streamFormError.value = '';
    streamDialogOpen.value = true;
}

async function submitStream(): Promise<void> {
    const userPkg = asNumber(streamForm.user_package);

    if (userPkg === null || !Number.isInteger(userPkg) || userPkg < 1) {
        streamFormError.value = '用户套餐 ID 不能为空';

        return;
    }

    const backendPort = asNumber(streamForm.backend_port);

    if (
        backendPort === null ||
        !Number.isInteger(backendPort) ||
        backendPort < 1 ||
        backendPort > 65535
    ) {
        streamFormError.value = '后端端口不能为空';

        return;
    }

    const port = Number(streamForm.listen_port);
    const uid = Number(streamForm.uid);

    if (
        !Number.isInteger(port) ||
        port < 1 ||
        port > 65535 ||
        !streamForm.backend_addr.trim()
    ) {
        streamFormError.value = '请填写有效的监听端口和源站地址';

        return;
    }

    if (!editingStream.value && (!Number.isInteger(uid) || uid < 1)) {
        streamFormError.value = '请填写套餐所属用户 ID';

        return;
    }

    const previousListen = cdnflyJsonRows(editingStream.value?.listen);
    const previousBackend = cdnflyJsonRows(editingStream.value?.backend);
    const listen = [
        { ...previousListen[0], protocol: streamForm.listen_protocol, port },
        ...previousListen.slice(1).map((entry) => ({
            ...entry,
            protocol: String(entry.protocol),
            port: Number(entry.port),
        })),
    ];
    const backend = [
        {
            ...previousBackend[0],
            addr: streamForm.backend_addr.trim(),
            weight: Number(previousBackend[0]?.weight ?? 1),
            state: String(previousBackend[0]?.state ?? 'up'),
        },
        ...previousBackend.slice(1).map((entry) => ({
            ...entry,
            addr: String(entry.addr),
            weight: Number(entry.weight ?? 1),
            state: String(entry.state ?? 'up'),
        })),
    ];

    const payload: AdminStreamPayload = {
        ...(!editingStream.value ? { uid } : {}),
        user_package: userPkg,
        listen,
        backend_port: backendPort,
        backend,
        balance_way: streamForm.balance_way,
        proxy_protocol: streamForm.proxy_protocol ? 1 : 0,
        enable: streamForm.enable ? 1 : 0,
    };

    if (streamForm.conn_limit.trim() === '') {
        payload.conn_limit = '';
    }

    if (streamForm.conn_limit.trim() !== '') {
        payload.conn_limit = Number(streamForm.conn_limit) || 0;
    }

    streamSaving.value = true;
    streamFormError.value = '';

    try {
        if (editingStream.value) {
            const id = asNumber(editingStream.value.id);

            if (!id) {
                streamFormError.value = '转发 ID 缺失';

                return;
            }

            await updateAdminStream(id, payload);
            toast.success('转发已更新');
        } else {
            await createAdminStream(payload);
            toast.success('转发已创建');
        }

        streamDialogOpen.value = false;
        void workspace.value?.refresh();
    } catch (error) {
        streamFormError.value = getErrorMessage(error);
    } finally {
        streamSaving.value = false;
    }
}
</script>

<template>
    <div class="flex flex-1 flex-col p-3 md:p-5">
        <AdminStreamWorkspace
            ref="workspace"
            @create="openAddStream"
            @manage="openEditStream"
        />
        <Dialog v-model:open="streamDialogOpen">
            <DialogScrollContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        editingStream ? '编辑转发' : '新增转发'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            editingStream
                                ? '修改转发配置，提交后立即生效。'
                                : '创建新的四层转发规则。'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-5" @submit.prevent="submitStream">
                    <Alert v-if="streamFormError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>提交失败</AlertTitle>
                        <AlertDescription>{{
                            streamFormError
                        }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div v-if="!editingStream" class="grid gap-2">
                            <Label for="stream-owner">所属用户 ID</Label>
                            <Input
                                id="stream-owner"
                                v-model="streamForm.uid"
                                type="number"
                                min="1"
                                required
                                @change="applyDefaults"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="stream-user-package">用户套餐 ID</Label>
                            <Input
                                id="stream-user-package"
                                v-model="streamForm.user_package"
                                type="number"
                                min="1"
                                autocomplete="off"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="stream-balance-way">负载均衡</Label>
                            <Select v-model="streamForm.balance_way">
                                <SelectTrigger id="stream-balance-way"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="rr"
                                            >轮询 (rr)</SelectItem
                                        >
                                        <SelectItem value="ip_hash"
                                            >IP 哈希 (ip_hash)</SelectItem
                                        >
                                        <SelectItem value="least_conn"
                                            >最少连接 (least_conn)</SelectItem
                                        >
                                        <SelectItem value="random"
                                            >随机 (random)</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="stream-listen-protocol">监听协议</Label>
                            <Select v-model="streamForm.listen_protocol">
                                <SelectTrigger id="stream-listen-protocol"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="tcp">TCP</SelectItem>
                                        <SelectItem value="udp">UDP</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="stream-listen-port">首个监听端口</Label>
                            <Input
                                id="stream-listen-port"
                                v-model="streamForm.listen_port"
                                placeholder="例如 80"
                                autocomplete="off"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="stream-backend-addr">后端地址</Label>
                            <Input
                                id="stream-backend-addr"
                                v-model="streamForm.backend_addr"
                                placeholder="例如 1.1.1.1"
                                autocomplete="off"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="stream-backend-port">后端端口</Label>
                            <Input
                                id="stream-backend-port"
                                v-model="streamForm.backend_port"
                                type="number"
                                min="1"
                                max="65535"
                                autocomplete="off"
                                required
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="stream-conn-limit">连接限制</Label>
                            <Input
                                id="stream-conn-limit"
                                v-model="streamForm.conn_limit"
                                type="number"
                                min="0"
                                placeholder="留空则不限"
                                autocomplete="off"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="stream-proxy-protocol"
                                :checked="streamForm.proxy_protocol"
                                @update:checked="
                                    streamForm.proxy_protocol = $event === true
                                "
                            />
                            <Label for="stream-proxy-protocol"
                                >Proxy Protocol</Label
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="stream-enable"
                                :checked="streamForm.enable"
                                @update:checked="
                                    streamForm.enable = $event === true
                                "
                            />
                            <Label for="stream-enable">启用</Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            @click="streamDialogOpen = false"
                            >取消</Button
                        >
                        <Button
                            :disabled="streamSaving || defaultsLoading"
                            type="submit"
                        >
                            <Spinner
                                v-if="streamSaving"
                                data-icon="inline-start"
                            />
                            <Save v-else data-icon="inline-start" />
                            {{ editingStream ? '保存' : '创建' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
