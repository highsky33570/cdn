<script setup lang="ts">
import { RefreshCw } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import { formatDate, getErrorMessage } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const initial = new URLSearchParams(
    typeof window === 'undefined' ? '' : window.location.search,
);
const order = ref(
    initial.get('order_by') === 'connection' ? 'connection' : 'bandwidth',
);
const uid = ref(initial.get('uid') ?? ''),
    upids = ref(initial.get('upids') ?? ''),
    nodes = ref(initial.get('node_ids') ?? '');
const page = ref(1),
    size = ref(10),
    total = ref(0),
    rows = ref<CdnflyRecord[]>([]),
    loading = ref(false),
    error = ref('');
const columns = [
    { key: 'id', label: '套餐' },
    { key: 'uid', label: '用户ID' },
    { key: 'bandwidth', label: '当前带宽 / 总限制' },
    { key: 'connection', label: '当前连接数 / 总限制' },
];
const nodeColumns = [
    { key: 'node_id', label: '节点ID' },
    { key: 'bandwidth', label: '带宽' },
    { key: 'connection', label: '连接数' },
    { key: 'time', label: '上报时间' },
];
function numeric(value: unknown): number | null {
    if (value === null || value === undefined || String(value).trim() === '') {
        return null;
    }

    const number = Number(value);

    return Number.isFinite(number) && number >= 0 ? number : null;
}
function bandwidth(value: unknown) {
    const number = numeric(value);

    if (number === null) {
        return '—';
    }

    return number >= 125_000_000
        ? `${((number * 8) / 1_000_000_000).toFixed(2)}Gbps`
        : `${((number * 8) / 1_000_000).toFixed(2)}Mbps`;
}
function connection(value: unknown) {
    const number = numeric(value);

    return number === null ? '—' : String(number);
}
function limit(value: unknown) {
    return String(value) === '-1'
        ? '不限'
        : value === null || value === undefined || value === ''
          ? '—'
          : String(value);
}
function reportTime(value: unknown) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const raw = String(value);

    if (/^\d+(\.\d+)?$/.test(raw)) {
        const date = new Date(Number(raw) * 1000);

        return Number.isNaN(date.getTime())
            ? '—'
            : formatDate(date.toISOString());
    }

    return formatDate(raw);
}
function idList(value: string, label: string) {
    if (!value.trim()) {
        return '';
    }

    const ids = value.split(',').map((item) => item.trim());

    if (ids.some((id) => !/^\d+$/.test(id))) {
        throw new Error(`${label}请填写数字，多个ID用英文逗号分隔`);
    }

    return [...new Set(ids)].join(',');
}
let listToken = 0,
    timer: ReturnType<typeof setTimeout> | undefined;
async function loadRows() {
    clearTimeout(timer);
    const token = ++listToken;
    loading.value = true;
    error.value = '';
    rows.value = [];

    try {
        const user = String(uid.value).trim(),
            packages = idList(String(upids.value), '用户套餐ID'),
            nodeIds = idList(String(nodes.value), '节点ID');

        if (user && !/^\d+$/.test(user)) {
            throw new Error('用户ID请填写数字');
        }

        if (user && packages) {
            throw new Error('用户ID与用户套餐ID不能同时筛选，请清空其中一项');
        }

        const query = new URLSearchParams({
            page: String(page.value),
            limit: String(size.value),
            order_by: order.value,
        });

        if (user) {
            query.set('uid', user);
        }

        if (packages) {
            query.set('upids', packages);
        }

        if (nodeIds) {
            query.set('node_ids', nodeIds);
        }

        const response = await apiRequest(
            `/api/admin/workspace/package-monitor?${query}`,
        );

        if (token !== listToken) {
            return;
        }

        rows.value = extractCdnflyRows(response);
        total.value = extractCdnflyTotal(response, rows.value.length);
        page.value = Math.min(
            page.value,
            Math.max(1, Math.ceil(total.value / size.value)),
        );
    } catch (e) {
        if (token === listToken) {
            error.value = getErrorMessage(e);
            total.value = 0;
        }
    } finally {
        if (token === listToken) {
            loading.value = false;
        }
    }
}
function search() {
    clearTimeout(timer);

    if (page.value === 1) {
        void loadRows();
    } else {
        page.value = 1;
    }
}
watch(page, loadRows);
watch([order, size], search);
watch([uid, upids, nodes], () => {
    clearTimeout(timer);
    timer = setTimeout(search, 300);
});
onMounted(loadRows);
const detailOpen = ref(false),
    packageId = ref(''),
    nodeRows = ref<CdnflyRecord[]>([]),
    nodeLoading = ref(false),
    nodeError = ref('');
let nodeToken = 0;
async function loadNodes() {
    const token = ++nodeToken;
    nodeLoading.value = true;
    nodeError.value = '';
    nodeRows.value = [];

    try {
        const response = await apiRequest(
            `/api/admin/workspace/package-nodes?${new URLSearchParams({ upid: packageId.value })}`,
        );

        if (token === nodeToken) {
            nodeRows.value = extractCdnflyRows(response);
        }
    } catch (e) {
        if (token === nodeToken) {
            nodeError.value = getErrorMessage(e);
        }
    } finally {
        if (token === nodeToken) {
            nodeLoading.value = false;
        }
    }
}
function showDetails(row: CdnflyRecord) {
    packageId.value = String(row.id);
    detailOpen.value = true;
    void loadNodes();
}
watch(detailOpen, (open) => {
    if (!open) {
        ++nodeToken;
        nodeLoading.value = false;
    }
});
onUnmounted(() => {
    clearTimeout(timer);
    ++listToken;
    ++nodeToken;
});
</script>

<template>
    <div class="package-monitor-workspace min-w-0 p-4 md:p-6">
        <div
            class="console-panel rounded-xl border bg-card p-4 text-card-foreground md:p-5"
        >
            <form
                class="mb-4 flex flex-wrap items-center gap-2"
                @submit.prevent="search"
            >
                <Select v-model="order"
                    ><SelectTrigger aria-label="排序方式" class="w-full sm:w-44"
                        ><SelectValue /></SelectTrigger
                    ><SelectContent
                        ><SelectItem value="bandwidth">按带宽排序</SelectItem
                        ><SelectItem value="connection"
                            >按连接数排序</SelectItem
                        ></SelectContent
                    ></Select
                >
                <div class="monitor-filter">
                    <Label for="monitor-uid">用户ID</Label
                    ><Input
                        id="monitor-uid"
                        v-model="uid"
                        placeholder="用户ID"
                    />
                </div>
                <div class="monitor-filter">
                    <Label for="monitor-upids">用户套餐ID</Label
                    ><Input
                        id="monitor-upids"
                        v-model="upids"
                        placeholder="用户套餐ID,多个逗号分隔"
                    />
                </div>
                <div class="monitor-filter">
                    <Label for="monitor-nodes">节点ID</Label
                    ><Input
                        id="monitor-nodes"
                        v-model="nodes"
                        placeholder="节点ID,多个逗号分隔"
                    />
                </div>
                <Button
                    type="submit"
                    size="sm"
                    variant="ghost"
                    class="ml-auto"
                    :disabled="loading"
                    ><RefreshCw />刷新</Button
                >
            </form>
            <Alert v-if="error" variant="destructive" class="mb-4"
                ><AlertDescription
                    >{{ error
                    }}<Button variant="link" @click="loadRows"
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <ConsoleDataTable
                embedded
                title="套餐监控"
                :columns="columns"
                :data="{ rows, total, page, pageSize: size, loading }"
                empty-text="暂无数据"
            >
                <template #cell-bandwidth="{ row }"
                    >{{ bandwidth(row.bandwidth_usage) }} /
                    {{ limit(row.bandwidth_limit) }}</template
                >
                <template #cell-connection="{ row }"
                    >{{ connection(row.connection_usage) }} /
                    {{ limit(row.connection_limit) }}</template
                >
                <template #row-actions="{ row }"
                    ><Button size="sm" variant="ghost" @click="showDetails(row)"
                        >详情</Button
                    ></template
                >
            </ConsoleDataTable>
            <PackagePagination
                v-model:page="page"
                v-model:page-size="size"
                numbered
                :total="total"
                :disabled="loading"
            />
        </div>
        <Dialog v-model:open="detailOpen"
            ><DialogScrollContent class="sm:max-w-3xl">
                <DialogHeader
                    ><DialogTitle
                        >用户套餐({{ packageId }})资源用量节点分布</DialogTitle
                    ><DialogDescription class="sr-only"
                        >查看各节点的带宽、连接数和上报时间</DialogDescription
                    ></DialogHeader
                >
                <div>
                    <Button size="sm" :disabled="nodeLoading" @click="loadNodes"
                        ><RefreshCw />刷新</Button
                    >
                </div>
                <Alert v-if="nodeError" variant="destructive"
                    ><AlertDescription
                        >{{ nodeError
                        }}<Button variant="link" @click="loadNodes"
                            >重试</Button
                        ></AlertDescription
                    ></Alert
                >
                <ConsoleDataTable
                    embedded
                    class="node-monitor-table"
                    title="节点资源用量"
                    :show-actions="false"
                    :columns="nodeColumns"
                    :get-row-key="(row) => String(row.node_id)"
                    :data="{
                        rows: nodeRows,
                        total: nodeRows.length,
                        page: 1,
                        pageSize: nodeRows.length,
                        loading: nodeLoading,
                    }"
                    empty-text="暂无节点上报数据"
                >
                    <template #cell-bandwidth="{ row }">{{
                        bandwidth(row.bandwidth)
                    }}</template>
                    <template #cell-connection="{ row }">{{
                        connection(row.connection)
                    }}</template>
                    <template #cell-time="{ row }">{{
                        reportTime(row.time)
                    }}</template>
                </ConsoleDataTable>
                <DialogFooter
                    ><Button variant="outline" @click="detailOpen = false"
                        >关闭</Button
                    ></DialogFooter
                >
            </DialogScrollContent></Dialog
        >
    </div>
</template>

<style scoped>
.monitor-filter {
    display: flex;
    width: 18rem;
    max-width: 100%;
}
.monitor-filter label {
    display: flex;
    align-items: center;
    white-space: nowrap;
    padding: 0 0.75rem;
    border: 1px solid var(--border);
    border-right: 0;
    border-radius: var(--radius) 0 0 var(--radius);
    background: var(--muted);
    color: var(--muted-foreground);
}
.monitor-filter input {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}
.node-monitor-table :deep(table) {
    min-width: 560px;
}
@media (max-width: 640px) {
    .monitor-filter {
        width: 100%;
    }
}
</style>
