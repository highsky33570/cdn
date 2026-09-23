<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Plus,
    RefreshCw,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import NodeIpLogsDialog from '@/components/console/NodeIpLogsDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    deleteAdminNode,
    deleteAdminPendingNode,
    deleteAdminRegion,
    listAdminNodes,
    listAdminPendingNodes,
    listAdminRegions,
    setAdminNodeEnabled,
    updateAdminNode,
} from '@/lib/adminModulesApi';
import type { CdnflyRecord } from '@/lib/adminModulesApi';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import {
    nodeBandwidth,
    nodeListQuery,
    nodeStatus,
    nodeTree,
} from '@/lib/nodeManagement';

const emit = defineEmits<{
    install: [];
    edit: [node: CdnflyRecord];
    initialize: [node: CdnflyRecord];
    subIps: [node: CdnflyRecord];
    addRegion: [];
    editRegion: [region: CdnflyRecord];
    regionsChanged: [];
}>();
type Tab = 'nodes' | 'pending' | 'disabled' | 'regions';
const tabs: { key: Tab; label: string }[] = [
    { key: 'nodes', label: '节点列表' },
    { key: 'pending', label: '待初始化' },
    { key: 'disabled', label: '同步失败已禁用' },
    { key: 'regions', label: '区域管理' },
];
const active = ref<Tab>('nodes');
const logIp = ref<string | null>(null);
const logNodeId = ref<number | null>(null);
const filters = reactive({
    search: '',
    region: 'all',
    status: 'all',
    type: 'all',
});
const regions = ref<CdnflyRecord[]>([]);
const referenceError = ref('');
const rows = ref<CdnflyRecord[]>([]);
const page = ref(1),
    limit = ref(10),
    total = ref(0);
const loading = ref(false),
    busy = ref(false),
    error = ref('');
const selected = ref<number[]>([]),
    expanded = ref<number[]>([]);
let requestId = 0;
const tree = computed(() => nodeTree(rows.value));
const visibleRows = computed(() =>
    active.value === 'nodes'
        ? tree.value.flatMap(({ node, children }) =>
              expanded.value.includes(Number(node.id))
                  ? [node, ...children]
                  : [node],
          )
        : rows.value,
);
const selectable = computed(() =>
    active.value === 'nodes'
        ? tree.value.map((item) => Number(item.node.id))
        : rows.value.map((row) => Number(row.id)),
);
const allSelected = computed(
    () =>
        selectable.value.length > 0 &&
        selectable.value.every((id) => selected.value.includes(id)),
);
const pages = computed(() => Math.max(1, Math.ceil(total.value / limit.value)));
const pageNumbers = computed(() => {
    const start = Math.max(1, Math.min(page.value - 2, pages.value - 4));

    return Array.from(
        { length: Math.min(5, pages.value) },
        (_, i) => start + i,
    );
});
const columns = computed(() =>
    active.value === 'nodes'
        ? 12
        : active.value === 'regions'
          ? 7
          : active.value === 'disabled'
            ? 6
            : 5,
);
const message = (e: unknown) => (e instanceof Error ? e.message : '请求失败');
const text = (v: unknown) => (v === undefined || v === null ? '' : String(v));

async function loadRegions() {
    referenceError.value = '';

    try {
        regions.value = extractCdnflyRows(await listAdminRegions({ limit: 0 }));
    } catch (e) {
        referenceError.value = message(e);
    }
}
async function refresh(
    targetPage = page.value,
    preserveSelection = false,
): Promise<void> {
    const ticket = ++requestId,
        tab = active.value;
    loading.value = true;
    error.value = '';
    rows.value = [];

    if (!preserveSelection) {
        selected.value = [];
    }

    try {
        const params = { page: targetPage, limit: limit.value };
        const result =
            tab === 'nodes'
                ? await listAdminNodes(
                      nodeListQuery(filters, targetPage, limit.value),
                  )
                : tab === 'pending'
                  ? await listAdminPendingNodes(params)
                  : tab === 'disabled'
                    ? await listAdminNodes({
                          ...params,
                          enable: 0,
                          disable_by: 'sync_error',
                      })
                    : await listAdminRegions(params);

        if (ticket !== requestId) {
            return;
        }

        const nextRows = extractCdnflyRows(result);
        total.value = extractCdnflyTotal(
            result,
            tab === 'nodes' ? nodeTree(nextRows).length : nextRows.length,
        );

        if (targetPage > 1 && targetPage > pages.value) {
            await refresh(pages.value, preserveSelection);

            return;
        }

        rows.value = nextRows;
        page.value = targetPage;
    } catch (e) {
        if (ticket === requestId) {
            error.value = message(e);
            total.value = 0;
        }
    } finally {
        if (ticket === requestId) {
            loading.value = false;
        }
    }
}
async function reload(): Promise<void> {
    await Promise.all([refresh(), loadRegions()]);
}
defineExpose({ refresh, reload });
onMounted(() => {
    void reload();
});
function changeTab(tab: Tab) {
    if (busy.value || tab === active.value) {
        return;
    }

    active.value = tab;
    page.value = 1;
    limit.value = 10;
    total.value = 0;
    expanded.value = [];
    void refresh(1);
}
function clearFilters() {
    Object.assign(filters, {
        search: '',
        region: 'all',
        status: 'all',
        type: 'all',
    });
    void refresh(1);
}
function toggle(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((n) => n !== id)
        : [...selected.value, id];
}
function toggleExpanded(id: number) {
    expanded.value = expanded.value.includes(id)
        ? expanded.value.filter((n) => n !== id)
        : [...expanded.value, id];
}
function hasChildren(id: unknown) {
    return tree.value.some(
        (item) =>
            Number(item.node.id) === Number(id) && item.children.length > 0,
    );
}
function regionName(row: CdnflyRecord) {
    return (
        row.region_name ??
        regions.value.find(
            (region) => Number(region.id) === Number(row.region_id),
        )?.name ??
        '—'
    );
}

/** Sequential writes keep bulk actions within upstream rate limits and retain failed selections. */
async function applyTo(
    ids: number[],
    action: (id: number) => Promise<unknown>,
): Promise<boolean> {
    if (busy.value || !ids.length) {
        return false;
    }

    busy.value = true;
    error.value = '';
    const failures: string[] = [],
        failedIds: number[] = [];

    try {
        for (const id of ids) {
            try {
                await action(id);
            } catch (e) {
                failedIds.push(id);
                failures.push(`#${id}：${message(e)}`);
            }
        }

        selected.value = failedIds;
        await refresh(page.value, true);

        if (failures.length) {
            error.value = `${ids.length - failures.length} 项成功，${failures.length} 项失败。${failures.join('；')}`;
        } else {
            toast.success('操作成功');
        }

        return failures.length === 0;
    } finally {
        busy.value = false;
    }
}
async function enableNodes(enable: boolean, node?: CdnflyRecord) {
    const ids = node ? [Number(node.id)] : [...selected.value];
    await applyTo(ids, (id) =>
        node && Number(node.pid) > 0
            ? updateAdminNode(id, {
                  enable: enable ? 1 : 0,
                  ...(!enable ? { disable_by: 'admin' as const } : {}),
              })
            : setAdminNodeEnabled(id, enable),
    );
}
const deleteOpen = ref(false),
    deleteIds = ref<number[]>([]),
    deleteTab = ref<Tab>('nodes'),
    deleteError = ref('');
function askDelete(row?: CdnflyRecord) {
    deleteIds.value = row ? [Number(row.id)] : [...selected.value];

    if (!deleteIds.value.length) {
        return;
    }

    deleteTab.value = active.value;
    deleteError.value = '';
    deleteOpen.value = true;
}
async function confirmDelete() {
    const action =
        deleteTab.value === 'pending'
            ? deleteAdminPendingNode
            : deleteTab.value === 'regions'
              ? deleteAdminRegion
              : deleteAdminNode;
    const ok = await applyTo([...deleteIds.value], action);

    if (deleteTab.value === 'regions') {
        await loadRegions();
        emit('regionsChanged');
    }

    if (ok) {
        deleteOpen.value = false;
    } else {
        deleteIds.value = [...selected.value];
        deleteError.value = error.value;
    }
}
</script>

<template>
    <section
        class="node-panel rounded-xl border bg-card p-5 text-card-foreground shadow-sm"
        :aria-busy="loading || busy"
    >
        <h1 class="mb-3 text-base font-semibold">节点管理</h1>
        <div
            class="mb-4 flex flex-wrap gap-1"
            role="tablist"
            aria-label="节点管理"
        >
            <button
                v-for="tab in tabs"
                :id="`node-tab-${tab.key}`"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="active === tab.key"
                aria-controls="node-tab-content"
                :disabled="busy"
                class="rounded-md px-4 py-2 text-sm transition-colors disabled:opacity-50"
                :class="
                    active === tab.key
                        ? 'bg-primary/10 font-medium text-primary'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="changeTab(tab.key)"
            >
                {{ tab.label }}
            </button>
        </div>
        <div
            id="node-tab-content"
            role="tabpanel"
            :aria-labelledby="`node-tab-${active}`"
        >
            <div
                v-if="active === 'nodes'"
                class="mb-3 flex flex-wrap items-center justify-between gap-3"
            >
                <div class="flex flex-wrap gap-2">
                    <Button size="sm" @click="emit('install')"
                        ><Plus />安装节点</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="busy || loading || !selected.length"
                        @click="enableNodes(false)"
                        >禁用节点</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="busy || loading || !selected.length"
                        @click="enableNodes(true)"
                        >启用节点</Button
                    >
                    <DropdownMenu
                        ><DropdownMenuTrigger as-child
                            ><Button
                                size="sm"
                                variant="outline"
                                :disabled="busy"
                                >更多操作<ChevronDown /></Button></DropdownMenuTrigger
                        ><DropdownMenuContent align="start"
                            ><DropdownMenuItem @select="refresh()"
                                >刷新</DropdownMenuItem
                            ><DropdownMenuItem
                                :disabled="loading || !selected.length"
                                class="text-destructive"
                                @select="askDelete()"
                                >删除所选节点</DropdownMenuItem
                            ></DropdownMenuContent
                        ></DropdownMenu
                    >
                </div>
                <form
                    class="flex flex-wrap items-center gap-2"
                    @submit.prevent="refresh(1)"
                >
                    <select
                        v-model="filters.region"
                        aria-label="区域筛选"
                        :disabled="busy"
                        @change="refresh(1)"
                    >
                        <option value="all">所有区域</option>
                        <option
                            v-for="region in regions"
                            :key="text(region.id)"
                            :value="text(region.id)"
                        >
                            {{ region.name }}
                        </option>
                    </select>
                    <select
                        v-model="filters.status"
                        aria-label="状态筛选"
                        :disabled="busy"
                        @change="refresh(1)"
                    >
                        <option value="all">所有状态</option>
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                    <select
                        v-model="filters.type"
                        aria-label="类型筛选"
                        :disabled="busy"
                        @change="refresh(1)"
                    >
                        <option value="all">所有类型</option>
                        <option value="L1">L1节点</option>
                        <option value="L2">L2节点</option>
                    </select>
                    <div class="relative">
                        <Input
                            v-model="filters.search"
                            aria-label="搜索节点"
                            placeholder="填ID、IP或名称搜索"
                            class="h-8 w-52 pr-8"
                            :disabled="busy"
                        /><button
                            type="submit"
                            aria-label="搜索"
                            class="absolute inset-y-0 right-2 text-muted-foreground"
                            :disabled="busy"
                        >
                            <Search class="size-4" />
                        </button>
                    </div>
                    <button
                        type="button"
                        class="text-sm text-primary"
                        :disabled="busy"
                        @click="clearFilters"
                    >
                        清除
                    </button>
                </form>
            </div>
            <div
                v-else
                class="mb-3 flex flex-wrap gap-3 rounded-md border bg-muted/20 p-3"
            >
                <template v-if="active === 'pending'"
                    ><Button
                        size="sm"
                        :disabled="loading || busy"
                        @click="refresh()"
                        ><RefreshCw />刷新</Button
                    ><Button
                        size="sm"
                        variant="destructive"
                        :disabled="loading || busy || !selected.length"
                        @click="askDelete()"
                        ><Trash2 />删除</Button
                    ></template
                >
                <template v-else-if="active === 'disabled'"
                    ><Button
                        size="sm"
                        :disabled="loading || busy || !selected.length"
                        @click="enableNodes(true)"
                        >启用节点</Button
                    >
                    <ol
                        class="list-inside list-decimal text-xs leading-6 text-muted-foreground"
                    >
                        <li>
                            这里的节点由于同步网站失败被禁用，你可以手动启用这些节点来同步网站，并查看同步失败的原因来解决。
                        </li>
                        <li>
                            节点最多禁用半小时，之后会自动重新启用来尝试同步。
                        </li>
                        <li>需要到系统管理 › 系统设置 › 其它配置里开启。</li>
                    </ol></template
                >
                <template v-else
                    ><Button size="sm" @click="emit('addRegion')"
                        ><Plus />新增区域</Button
                    ><Button
                        size="sm"
                        variant="destructive"
                        :disabled="loading || busy || !selected.length"
                        @click="askDelete()"
                        ><Trash2 />删除</Button
                    ></template
                >
            </div>
            <div
                v-if="referenceError"
                role="alert"
                class="mb-3 rounded-md border border-destructive/30 p-3 text-sm text-destructive"
            >
                区域选项加载失败：{{ referenceError }}
                <button class="underline" @click="loadRegions">重试</button>
            </div>
            <div
                v-if="error"
                role="alert"
                class="mb-3 rounded-md border border-destructive/30 p-3 text-sm text-destructive"
            >
                {{ error }}
                <button :disabled="busy" class="underline" @click="refresh()">
                    重试
                </button>
            </div>
            <div class="overflow-x-auto">
                <table
                    class="w-full text-left text-xs"
                    :aria-label="tabs.find((tab) => tab.key === active)?.label"
                >
                    <thead class="bg-muted/25">
                        <tr>
                            <th class="w-12">
                                <input
                                    type="checkbox"
                                    aria-label="选择本页全部"
                                    :checked="allSelected"
                                    :indeterminate="
                                        selected.length > 0 && !allSelected
                                    "
                                    :disabled="
                                        busy || loading || !selectable.length
                                    "
                                    @change="
                                        selected = allSelected
                                            ? []
                                            : [...selectable]
                                    "
                                />
                            </th>
                            <th>ID</th>
                            <template v-if="active === 'nodes'"
                                ><th>名称</th>
                                <th>区域</th>
                                <th>节点IP</th>
                                <th>监控</th>
                                <th>带宽</th>
                                <th>
                                    月流量
                                    <span
                                        class="text-primary"
                                        title="月流量统计周期及限额由节点流量限制设置决定"
                                        >?</span
                                    >
                                </th>
                                <th>状态</th>
                                <th>备注</th>
                                <th>排序</th>
                                <th>操作</th></template
                            >
                            <template v-else-if="active === 'pending'"
                                ><th>节点IP</th>
                                <th>添加时间</th>
                                <th>操作</th></template
                            >
                            <template v-else-if="active === 'disabled'"
                                ><th>名称</th>
                                <th>备注</th>
                                <th>禁用时间</th>
                                <th>操作</th></template
                            >
                            <template v-else
                                ><th>名称</th>
                                <th>备注</th>
                                <th>排序</th>
                                <th>添加时间</th>
                                <th>操作</th></template
                            >
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in visibleRows"
                            :key="text(row.id)"
                            :class="Number(row.pid) > 0 ? 'bg-muted/15' : ''"
                        >
                            <td>
                                <input
                                    v-if="!Number(row.pid)"
                                    type="checkbox"
                                    :aria-label="`选择 ${row.id}`"
                                    :checked="selected.includes(Number(row.id))"
                                    :disabled="busy || loading"
                                    @change="toggle(Number(row.id))"
                                />
                            </td>
                            <td>{{ row.id }}</td>
                            <template v-if="active === 'nodes'">
                                <td>
                                    <button
                                        v-if="!Number(row.pid)"
                                        class="text-primary"
                                        @click="emit('edit', row)"
                                    >
                                        {{ row.name }}
                                    </button>
                                </td>
                                <td>
                                    <template v-if="!Number(row.pid)"
                                        >{{ regionName(row) }}
                                        <Link
                                            :href="`/console/admin/line-groups?node_id=${row.id}`"
                                            class="text-primary"
                                            >线路组({{
                                                row.node_group_count ?? '—'
                                            }}个)</Link
                                        ></template
                                    >
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="hasChildren(row.id)"
                                            :aria-label="`${expanded.includes(Number(row.id)) ? '收起' : '展开'} ${row.ip}`"
                                            :aria-expanded="
                                                expanded.includes(
                                                    Number(row.id),
                                                )
                                            "
                                            class="rounded-sm border p-0.5 text-muted-foreground"
                                            @click="
                                                toggleExpanded(Number(row.id))
                                            "
                                        >
                                            <ChevronDown
                                                v-if="
                                                    expanded.includes(
                                                        Number(row.id),
                                                    )
                                                "
                                                class="size-3"
                                            /><Plus
                                                v-else
                                                class="size-3"
                                            /></button
                                        ><span
                                            :class="
                                                Number(row.pid) > 0
                                                    ? 'pl-6'
                                                    : ''
                                            "
                                            >{{ row.ip }}</span
                                        >
                                    </div>
                                </td>
                                <td>
                                    <span>{{
                                        Number(row.check_on) === 1
                                            ? row.check_protocol
                                            : '未开启'
                                    }}</span>
                                    <button
                                        class="ml-1 text-primary"
                                        @click="
                                            logNodeId = Number(row.id);
                                            logIp = text(row.ip);
                                        "
                                    >
                                        [日志]
                                    </button>
                                </td>
                                <td>
                                    <Link
                                        v-if="!Number(row.pid)"
                                        :href="`/console/admin/node-monitoring?node_id=${row.id}`"
                                        class="whitespace-nowrap"
                                        >{{ nodeBandwidth(row.outbound)
                                        }}<span class="text-primary">↑</span>
                                        <span class="ml-1">{{
                                            nodeBandwidth(row.inbound)
                                        }}</span
                                        ><span class="text-amber-500"
                                            >↓</span
                                        ></Link
                                    >
                                </td>
                                <td>
                                    {{
                                        row.month_traffic == null
                                            ? '—'
                                            : `${row.month_traffic} GB`
                                    }}
                                </td>
                                <td>
                                    <span
                                        class="inline-flex items-center gap-1.5 whitespace-nowrap"
                                        ><span
                                            class="size-2 rounded-full"
                                            :class="{
                                                'bg-emerald-500':
                                                    nodeStatus(row).tone ===
                                                    'success',
                                                'bg-amber-500':
                                                    nodeStatus(row).tone ===
                                                    'warning',
                                                'bg-red-500':
                                                    nodeStatus(row).tone ===
                                                    'danger',
                                            }"
                                        />{{ nodeStatus(row).label }}</span
                                    >
                                </td>
                                <td
                                    class="max-w-48 truncate"
                                    :title="text(row.des)"
                                >
                                    {{ row.des }}
                                </td>
                                <td>{{ row.sort }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="!Number(row.pid)"
                                            class="text-primary"
                                            @click="emit('edit', row)"
                                        >
                                            管理</button
                                        ><DropdownMenu
                                            ><DropdownMenuTrigger as-child
                                                ><button
                                                    class="inline-flex items-center gap-0.5 text-primary"
                                                    :disabled="busy"
                                                >
                                                    更多<ChevronDown
                                                        class="size-3"
                                                    /></button></DropdownMenuTrigger
                                            ><DropdownMenuContent align="end"
                                                ><DropdownMenuItem
                                                    @select="
                                                        enableNodes(false, row)
                                                    "
                                                    >禁用</DropdownMenuItem
                                                ><DropdownMenuItem
                                                    @select="
                                                        enableNodes(true, row)
                                                    "
                                                    >启用</DropdownMenuItem
                                                ><DropdownMenuItem
                                                    v-if="!Number(row.pid)"
                                                    @select="
                                                        emit('subIps', row)
                                                    "
                                                    >管理子 IP</DropdownMenuItem
                                                ><DropdownMenuItem
                                                    class="text-destructive"
                                                    @select="askDelete(row)"
                                                    >删除</DropdownMenuItem
                                                ></DropdownMenuContent
                                            ></DropdownMenu
                                        >
                                    </div>
                                </td>
                            </template>
                            <template v-else-if="active === 'pending'"
                                ><td>{{ row.ip }}</td>
                                <td>{{ row.create_at ?? row.created_at }}</td>
                                <td>
                                    <button
                                        class="mr-3 text-primary"
                                        @click="emit('initialize', row)"
                                    >
                                        初始化</button
                                    ><button
                                        class="text-primary"
                                        :disabled="busy"
                                        @click="askDelete(row)"
                                    >
                                        删除
                                    </button>
                                </td></template
                            >
                            <template v-else-if="active === 'disabled'"
                                ><td>{{ row.name }}</td>
                                <td>{{ row.des }}</td>
                                <td>{{ row.disable_at }}</td>
                                <td>
                                    <button
                                        class="text-primary"
                                        :disabled="busy"
                                        @click="enableNodes(true, row)"
                                    >
                                        启用
                                    </button>
                                </td></template
                            >
                            <template v-else
                                ><td>
                                    <button
                                        class="text-primary"
                                        @click="emit('editRegion', row)"
                                    >
                                        {{ row.name }}
                                    </button>
                                </td>
                                <td>{{ row.des }}</td>
                                <td>{{ row.sort }}</td>
                                <td>{{ row.create_at ?? row.created_at }}</td>
                                <td>
                                    <button
                                        class="mr-3 text-primary"
                                        @click="emit('editRegion', row)"
                                    >
                                        编辑</button
                                    ><button
                                        class="text-primary"
                                        :disabled="busy"
                                        @click="askDelete(row)"
                                    >
                                        删除
                                    </button>
                                </td></template
                            >
                        </tr>
                        <tr v-if="!visibleRows.length">
                            <td
                                :colspan="columns"
                                class="h-12 text-center text-muted-foreground"
                            >
                                {{
                                    loading
                                        ? '加载中…'
                                        : error
                                          ? '数据加载失败'
                                          : '暂无数据'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav
                class="mt-4 flex flex-wrap items-center gap-1 text-xs text-muted-foreground"
                aria-label="节点分页"
            >
                <span class="mr-2">共 {{ total }} 条</span>
                <Button
                    size="icon-sm"
                    variant="outline"
                    aria-label="上一页"
                    :disabled="page <= 1 || loading || busy"
                    @click="refresh(page - 1)"
                    ><ChevronLeft
                /></Button>
                <Button
                    v-for="number in pageNumbers"
                    :key="number"
                    size="icon-sm"
                    variant="outline"
                    :aria-label="`第 ${number} 页`"
                    :aria-current="page === number ? 'page' : undefined"
                    :class="
                        page === number ? 'border-primary text-primary' : ''
                    "
                    :disabled="loading || busy"
                    @click="refresh(number)"
                    >{{ number }}</Button
                >
                <Button
                    size="icon-sm"
                    variant="outline"
                    aria-label="下一页"
                    :disabled="page >= pages || loading || busy"
                    @click="refresh(page + 1)"
                    ><ChevronRight
                /></Button>
                <select
                    v-model="limit"
                    class="ml-2"
                    aria-label="每页条数"
                    :disabled="loading || busy"
                    @change="refresh(1)"
                >
                    <option
                        v-for="size in [10, 30, 100, 300]"
                        :key="size"
                        :value="size"
                    >
                        {{ size }} 条/页
                    </option>
                </select>
            </nav>
        </div>
        <NodeIpLogsDialog
            :ip="logIp"
            :node-id="logNodeId"
            @close="logIp = null"
        />
        <ConfirmDeleteDialog
            :open="deleteOpen"
            title="确认删除"
            :description="`确认删除所选 ${deleteIds.length} 项（ID：${deleteIds.join('、')}）？删除后不可恢复。`"
            :loading="busy"
            :error="deleteError"
            @confirm="confirmDelete"
            @cancel="!busy && (deleteOpen = false)"
        />
    </section>
</template>

<style scoped>
@reference '../../../css/app.css';
.node-panel th {
    @apply border-b px-3 py-2.5 font-medium whitespace-nowrap;
}
.node-panel td {
    @apply h-11 border-b px-3 py-2 whitespace-nowrap text-muted-foreground;
}
.node-panel tbody tr:hover {
    @apply bg-muted/25;
}
.node-panel select {
    @apply h-8 min-w-28 rounded border border-input bg-background px-2 text-xs text-foreground focus:border-primary focus:outline-none disabled:opacity-50;
}
.node-panel input[type='checkbox'] {
    @apply size-3.5 accent-primary;
}
</style>
