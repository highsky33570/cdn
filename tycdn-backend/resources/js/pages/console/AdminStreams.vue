<script setup lang="ts">
import { AlertCircle, Network, Pencil, Plus, RefreshCw, Save, Trash2 } from 'lucide-vue-next';
import Switch from '@/components/ui/switch/Switch.vue';
import { computed, onMounted, reactive, ref } from 'vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import { toast } from 'vue-sonner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    createAdminStream,
    createAdminStreamGroup,
    deleteAdminStream,
    deleteAdminStreamGroup,
    listAdminStreamGroups,
    listAdminStreams,
    setAdminStreamEnabled,
    updateAdminStream,
    updateAdminStreamGroup,
} from '@/lib/adminModulesApi';
import type {
    AdminStreamGroupPayload,
    AdminStreamPayload,
    CdnflyListData,
    CdnflyRecord,
} from '@/lib/adminModulesApi';

// ─── Helpers ─────────────────────────────────────────
function isRecord(value: unknown): value is CdnflyRecord {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function extractRows(result: unknown): CdnflyRecord[] {
    if (Array.isArray(result)) return result.filter(isRecord);
    if (!isRecord(result)) return [];
    for (const key of ['data', 'items', 'list', 'rows', 'records']) {
        const value = result[key];
        if (Array.isArray(value)) return value.filter(isRecord);
        if (isRecord(value)) {
            const nested = extractRows(value);
            if (nested.length > 0) return nested;
        }
    }
    return [];
}

function extractTotal(result: CdnflyListData, fallback: number): number | null {
    if (typeof result.total === 'number') return result.total;
    if (typeof result.count === 'number') return result.count;
    if (isRecord(result.meta) && typeof result.meta.total === 'number') return result.meta.total;
    if (isRecord(result.data) && typeof result.data.total === 'number') return result.data.total;
    return null;
}

function asNumber(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) return value;
    if (typeof value === 'string') {
        const parsed = Number(value);
        if (Number.isFinite(parsed)) return parsed;
    }
    return null;
}

function textValue(value: unknown): string {
    if (value === null || value === undefined) return '';
    return String(value);
}

function getErrorMessage(error: unknown): string {
    return error instanceof Error ? error.message : '请求失败';
}

// ─── Stream columns (unchanged) ─────────────────────
const streamColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'name', label: '名称' },
    { key: 'src_port', label: '源端口', width: '100px' },
    { key: 'dst_addr', label: '目标地址' },
    { key: 'protocol', label: '协议', width: '80px' },
    { key: 'user_id', label: '用户 ID', width: '90px' },
    {
        key: 'enable',
        label: '状态',
        width: '90px',
        badge: true,
        format: (v) =>
            v === 1 || v === '1'
                ? '运行中'
                : v === 0 || v === '0'
                  ? '已停用'
                  : String(v ?? '-'),
        badgeVariant: (v) =>
            v === 1 || v === '1' ? 'secondary' : 'destructive',
    },
];

// ─── Streams – existing toggle / delete ─────────────
const streamsTableRef = ref<InstanceType<typeof ConsoleDataTable> | null>(null);
const togglingId = ref<number | null>(null);
const deletingId = ref<number | null>(null);
const deleteOpen = ref(false);
const deleteTarget = ref<CdnflyRecord | null>(null);

function isRunning(row: CdnflyRecord): boolean {
    return row.enable === 1 || row.enable === '1';
}

async function toggleEnabled(row: CdnflyRecord, checked: boolean): Promise<void> {
    const id = Number(row.id);
    if (!id) return;
    togglingId.value = id;
    try {
        await setAdminStreamEnabled(id, checked ? 1 : 0);
        streamsTableRef.value?.refresh();
    } finally {
        togglingId.value = null;
    }
}

function openDeleteConfirm(row: CdnflyRecord): void {
    deleteTarget.value = row;
    deleteOpen.value = true;
}

async function confirmDelete(): Promise<void> {
    if (!deleteTarget.value) return;
    deletingId.value = Number(deleteTarget.value.id);
    try {
        await deleteAdminStream(Number(deleteTarget.value.id));
        deleteOpen.value = false;
        streamsTableRef.value?.refresh();
    } finally {
        deletingId.value = null;
    }
}

// ─── Streams – create / edit ────────────────────────
const streamDialogOpen = ref(false);
const streamSaving = ref(false);
const streamFormError = ref('');
const editingStream = ref<CdnflyRecord | null>(null);

const streamForm = reactive({
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
    streamForm.user_package = textValue(row.user_package);
    // Parse listen JSON
    try {
        const listenArr = typeof row.listen === 'string' ? JSON.parse(row.listen) : row.listen;
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
        const backendArr = typeof row.backend === 'string' ? JSON.parse(row.backend) : row.backend;
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
    streamForm.proxy_protocol = row.proxy_protocol === true || row.proxy_protocol === 1 || row.proxy_protocol === '1';
    streamForm.conn_limit = textValue(row.conn_limit);
    streamForm.enable = row.enable === 1 || row.enable === '1' || row.enable === true;
    streamFormError.value = '';
    streamDialogOpen.value = true;
}

async function submitStream(): Promise<void> {
    const userPkg = asNumber(streamForm.user_package);
    if (userPkg === null) {
        streamFormError.value = '用户套餐 ID 不能为空';
        return;
    }
    const backendPort = asNumber(streamForm.backend_port);
    if (backendPort === null) {
        streamFormError.value = '后端端口不能为空';
        return;
    }

    const listen = JSON.stringify([{ protocol: streamForm.listen_protocol, port: streamForm.listen_port }]);
    const backend = JSON.stringify([{ addr: streamForm.backend_addr, weight: 1, state: 'up' }]);

    const payload: AdminStreamPayload = {
        user_package: userPkg,
        listen,
        backend_port: backendPort,
        backend,
        balance_way: streamForm.balance_way,
        proxy_protocol: streamForm.proxy_protocol ? 1 : 0,
        enable: streamForm.enable ? 1 : 0,
    };

    if (streamForm.conn_limit.trim() !== '') {
        payload.conn_limit = Number(streamForm.conn_limit) || 0;
    }

    streamSaving.value = true;
    streamFormError.value = '';

    try {
        if (editingStream.value) {
            const id = asNumber(editingStream.value.id);
            if (!id) { streamFormError.value = '转发 ID 缺失'; return; }
            await updateAdminStream(id, payload);
            toast.success('转发已更新');
        } else {
            await createAdminStream(payload);
            toast.success('转发已创建');
        }
        streamDialogOpen.value = false;
        streamsTableRef.value?.refresh();
    } catch (error) {
        streamFormError.value = getErrorMessage(error);
    } finally {
        streamSaving.value = false;
    }
}

// ─── Stream Groups – full CRUD ──────────────────────
const sgLoading = ref(false);
const sgError = ref('');
const sgDialogOpen = ref(false);
const sgSaving = ref(false);
const sgFormError = ref('');
const editingStreamGroup = ref<CdnflyRecord | null>(null);
const sgPage = ref(1);
const sgTotal = ref<number | null>(null);
const sgRows = ref<CdnflyRecord[]>([]);

const sgForm = reactive({
    name: '',
    des: '',
});

// Delete confirm dialog (shared)
const deleteConfirmOpen = ref(false);
const deleteConfirmTitle = ref('');
const deleteConfirmDesc = ref('');
const deleteConfirmAction = ref<(() => Promise<void>) | null>(null);
const deleteConfirmError = ref('');
const deleteConfirmLoading = ref(false);

const hasSgPrevPage = computed(() => sgPage.value > 1);
const hasSgNextPage = computed(() => {
    if (typeof sgTotal.value === 'number') {
        return sgPage.value * 20 < sgTotal.value;
    }
    return sgRows.value.length >= 20;
});

onMounted(() => {
    void loadStreamGroups();
});

async function loadStreamGroups(targetPage = sgPage.value): Promise<void> {
    sgLoading.value = true;
    sgError.value = '';
    try {
        const result = await listAdminStreamGroups({ page: targetPage, limit: 20 });
        sgRows.value = extractRows(result);
        sgTotal.value = extractTotal(result, sgRows.value.length);
        sgPage.value = targetPage;
    } catch (error) {
        sgError.value = getErrorMessage(error);
    } finally {
        sgLoading.value = false;
    }
}

function openAddStreamGroup(): void {
    editingStreamGroup.value = null;
    sgForm.name = '';
    sgForm.des = '';
    sgFormError.value = '';
    sgDialogOpen.value = true;
}

function openEditStreamGroup(record: CdnflyRecord): void {
    editingStreamGroup.value = record;
    sgForm.name = textValue(record.name);
    sgForm.des = textValue(record.des);
    sgFormError.value = '';
    sgDialogOpen.value = true;
}

async function submitStreamGroup(): Promise<void> {
    if (sgForm.name.trim() === '') {
        sgFormError.value = '分组名称不能为空';
        return;
    }

    const payload: AdminStreamGroupPayload = {
        name: sgForm.name.trim(),
        des: sgForm.des.trim(),
    };

    sgSaving.value = true;
    sgFormError.value = '';

    try {
        if (editingStreamGroup.value) {
            const id = asNumber(editingStreamGroup.value.id);
            if (!id) { sgFormError.value = '分组 ID 缺失'; return; }
            await updateAdminStreamGroup(id, payload);
            toast.success('转发分组已更新');
        } else {
            await createAdminStreamGroup(payload);
            toast.success('转发分组已创建');
        }
        sgDialogOpen.value = false;
        await loadStreamGroups();
    } catch (error) {
        sgFormError.value = getErrorMessage(error);
    } finally {
        sgSaving.value = false;
    }
}

function openDeleteStreamGroup(record: CdnflyRecord): void {
    const id = asNumber(record.id);
    if (!id) { sgError.value = '分组 ID 缺失'; return; }
    deleteConfirmTitle.value = '确认删除分组';
    deleteConfirmDesc.value = `确认删除转发分组「${textValue(record.name) || '#' + id}」？删除后不可恢复。`;
    deleteConfirmError.value = '';
    deleteConfirmAction.value = async () => {
        deleteConfirmLoading.value = true;
        try {
            await deleteAdminStreamGroup(id);
            deleteConfirmOpen.value = false;
            toast.success('转发分组已删除');
            await loadStreamGroups();
        } catch (error) {
            deleteConfirmError.value = getErrorMessage(error);
        } finally {
            deleteConfirmLoading.value = false;
        }
    };
    deleteConfirmOpen.value = true;
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader title="四层转发管理" :icon="Network" :show-api-badge="false" />

        <ConsoleDataTable
            ref="streamsTableRef"
            title="转发列表"
            :icon="Network"
            :columns="streamColumns"
            :fetch-fn="listAdminStreams"
            search-placeholder="搜索转发"
        >
            <template #toolbar-end="{ loading: tLoading, refresh: tRefresh }">
                <div class="flex items-center gap-2">
                    <Button size="sm" @click="openAddStream">
                        <Plus data-icon="inline-start" />
                        新增转发
                    </Button>
                    <Button variant="outline" size="sm" :disabled="tLoading" @click="tRefresh">
                        <Spinner v-if="tLoading" data-icon="inline-start" />
                        <RefreshCw v-else data-icon="inline-start" />
                        刷新
                    </Button>
                </div>
            </template>
            <template #cell-enable="{ row }">
                <Switch
                    :checked="row.enable === 1 || row.enable === '1'"
                    :disabled="togglingId === Number(row.id)"
                    @update:checked="toggleEnabled(row, $event)"
                />
            </template>
            <template #row-actions="{ row }">
                <Button
                    variant="ghost"
                    size="sm"
                    @click="openEditStream(row)"
                >
                    <Pencil class="size-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="deletingId === Number(row.id)"
                    @click="openDeleteConfirm(row)"
                >
                    <Spinner v-if="deletingId === Number(row.id)" class="size-4" />
                    <Trash2 v-else class="size-4 text-destructive" />
                </Button>
            </template>
        </ConsoleDataTable>

        <!-- ─── Stream Groups (CRUD) ─── -->
        <Card>
            <CardHeader class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <CardTitle class="text-base">转发分组</CardTitle>
                <div class="flex items-center gap-2">
                    <Button size="sm" @click="openAddStreamGroup">
                        <Plus data-icon="inline-start" />
                        新增转发组
                    </Button>
                    <Button variant="outline" size="sm" :disabled="sgLoading" @click="loadStreamGroups(1)">
                        <RefreshCw data-icon="inline-start" />
                        刷新
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <Alert v-if="sgError" variant="destructive" class="mb-4">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>分组请求失败</AlertTitle>
                    <AlertDescription>{{ sgError }}</AlertDescription>
                </Alert>
                <div class="overflow-x-auto rounded-md border">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="border-b bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="w-20 px-4 py-3 text-left font-medium">ID</th>
                                <th class="px-4 py-3 text-left font-medium">名称</th>
                                <th class="px-4 py-3 text-left font-medium">备注</th>
                                <th class="w-44 px-4 py-3 text-right font-medium">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="sgLoading && sgRows.length === 0">
                                <td class="px-4 py-12 text-center" colspan="4"><Spinner /></td>
                            </tr>
                            <tr v-for="r in sgRows" :key="textValue(r.id)" class="border-b last:border-b-0">
                                <td class="px-4 py-3 text-muted-foreground">{{ textValue(r.id) }}</td>
                                <td class="px-4 py-3 font-medium">{{ textValue(r.name) || '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ textValue(r.des) || '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <Button variant="outline" size="sm" @click="openEditStreamGroup(r)">
                                            <Pencil data-icon="inline-start" />
                                            编辑
                                        </Button>
                                        <Button variant="destructive" size="sm" @click="openDeleteStreamGroup(r)">
                                            <Trash2 data-icon="inline-start" />
                                            删除
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!sgLoading && sgRows.length === 0">
                                <td class="px-4 py-12 text-center text-muted-foreground" colspan="4">暂无转发分组</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-center justify-end gap-2">
                    <Button variant="outline" size="sm" :disabled="!hasSgPrevPage || sgLoading" @click="loadStreamGroups(sgPage - 1)">上一页</Button>
                    <span class="text-sm text-muted-foreground">第 {{ sgPage }} 页</span>
                    <Button variant="outline" size="sm" :disabled="!hasSgNextPage || sgLoading" @click="loadStreamGroups(sgPage + 1)">下一页</Button>
                </div>
            </CardContent>
        </Card>

        <!-- ─── Stream Delete Confirm ─── -->
        <Dialog v-model:open="deleteOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>确认删除</DialogTitle>
                    <DialogDescription>
                        确定要删除转发「{{ deleteTarget?.name }}」吗？删除前会自动停用。
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteOpen = false">取消</Button>
                    <Button variant="destructive" :disabled="deletingId !== null" @click="confirmDelete">
                        <Spinner v-if="deletingId !== null" data-icon="inline-start" />
                        <Trash2 v-else data-icon="inline-start" />
                        确认删除
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- ─── Stream Create/Edit Dialog ─── -->
        <Dialog v-model:open="streamDialogOpen">
            <DialogScrollContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingStream ? '编辑转发' : '新增转发' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingStream ? '修改转发配置，提交后立即生效。' : '创建新的四层转发规则。' }}
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-5" @submit.prevent="submitStream">
                    <Alert v-if="streamFormError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>提交失败</AlertTitle>
                        <AlertDescription>{{ streamFormError }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="stream-user-package">用户套餐 ID</Label>
                            <Input id="stream-user-package" v-model="streamForm.user_package" type="number" min="1" autocomplete="off" required />
                        </div>
                        <div class="grid gap-2">
                            <Label for="stream-balance-way">负载均衡</Label>
                            <Select v-model="streamForm.balance_way">
                                <SelectTrigger id="stream-balance-way"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="rr">轮询 (rr)</SelectItem>
                                        <SelectItem value="ip_hash">IP 哈希 (ip_hash)</SelectItem>
                                        <SelectItem value="least_conn">最少连接 (least_conn)</SelectItem>
                                        <SelectItem value="random">随机 (random)</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="stream-listen-protocol">监听协议</Label>
                            <Select v-model="streamForm.listen_protocol">
                                <SelectTrigger id="stream-listen-protocol"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="tcp">TCP</SelectItem>
                                        <SelectItem value="udp">UDP</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="stream-listen-port">监听端口</Label>
                            <Input id="stream-listen-port" v-model="streamForm.listen_port" placeholder="例如 80" autocomplete="off" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="stream-backend-addr">后端地址</Label>
                            <Input id="stream-backend-addr" v-model="streamForm.backend_addr" placeholder="例如 1.1.1.1" autocomplete="off" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="stream-backend-port">后端端口</Label>
                            <Input id="stream-backend-port" v-model="streamForm.backend_port" type="number" min="1" max="65535" autocomplete="off" required />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="stream-conn-limit">连接限制</Label>
                            <Input id="stream-conn-limit" v-model="streamForm.conn_limit" type="number" min="0" placeholder="留空则不限" autocomplete="off" />
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="stream-proxy-protocol"
                                :checked="streamForm.proxy_protocol"
                                @update:checked="streamForm.proxy_protocol = $event === true"
                            />
                            <Label for="stream-proxy-protocol">Proxy Protocol</Label>
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="stream-enable"
                                :checked="streamForm.enable"
                                @update:checked="streamForm.enable = $event === true"
                            />
                            <Label for="stream-enable">启用</Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" type="button" @click="streamDialogOpen = false">取消</Button>
                        <Button :disabled="streamSaving" type="submit">
                            <Spinner v-if="streamSaving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            {{ editingStream ? '保存' : '创建' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ─── Stream Group Create/Edit Dialog ─── -->
        <Dialog v-model:open="sgDialogOpen">
            <DialogScrollContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingStreamGroup ? '编辑转发分组' : '新增转发分组' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingStreamGroup ? '修改分组信息，提交后立即生效。' : '创建新的转发分组。' }}
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-5" @submit.prevent="submitStreamGroup">
                    <Alert v-if="sgFormError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>提交失败</AlertTitle>
                        <AlertDescription>{{ sgFormError }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-2">
                        <Label for="sg-name">分组名称</Label>
                        <Input id="sg-name" v-model="sgForm.name" autocomplete="off" required />
                    </div>

                    <div class="grid gap-2">
                        <Label for="sg-des">备注</Label>
                        <textarea
                            id="sg-des"
                            v-model="sgForm.des"
                            class="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>

                    <DialogFooter>
                        <Button variant="outline" type="button" @click="sgDialogOpen = false">取消</Button>
                        <Button :disabled="sgSaving" type="submit">
                            <Spinner v-if="sgSaving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            {{ editingStreamGroup ? '保存' : '创建' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ─── Shared Delete Confirm Dialog ─── -->
        <ConfirmDeleteDialog
            :open="deleteConfirmOpen"
            :title="deleteConfirmTitle"
            :description="deleteConfirmDesc"
            :loading="deleteConfirmLoading"
            :error="deleteConfirmError"
            @confirm="deleteConfirmAction?.()"
            @cancel="deleteConfirmOpen = false"
        />
    </div>
</template>
