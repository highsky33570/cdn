<script setup lang="ts">
import {
    ChevronDown,
    Filter,
    MoreHorizontal,
    Pencil,
    Plus,
    RefreshCw,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import StreamBatchCreate from '@/components/console/StreamBatchCreate.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import CheckboxField from '@/components/ui/checkbox/CheckboxField.vue';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { apiRequest } from '@/lib/apiRequest';
import {
    extractCdnflyRows,
    extractCdnflyTotal,
    streamListenText,
    streamBackendText,
} from '@/lib/cdnflyResponse';
import type { CdnflyRecord } from '@/lib/sharedTypes';

type Tab = 'streams' | 'groups' | 'defaults';
const batchCreate = ref<InstanceType<typeof StreamBatchCreate> | null>(null);
const emit = defineEmits<{ create: []; manage: [row: CdnflyRecord] }>();
const tabs = [
    { key: 'streams', label: '转发列表' },
    { key: 'groups', label: '分组管理' },
    { key: 'defaults', label: '默认设置' },
] as const;
const endpoints = {
    streams: '/api/admin/streams',
    groups: '/api/admin/stream-groups',
    defaults: '/api/admin/stream-defaults',
};
const tab = ref<Tab>('streams'),
    rows = ref<CdnflyRecord[]>([]),
    total = ref(0),
    page = ref(1),
    size = ref(10),
    loading = ref(false),
    busy = ref(false),
    error = ref(''),
    selected = ref<number[]>([]),
    advanced = ref(false);
const searchType = ref('listen_port'),
    search = ref('');
const filters = reactive({
    listen_protocol: '',
    enable: '',
    state: '',
    sync_state: '',
    uid: '',
    group: '',
    user_package: '',
    region: '',
    node_group: '',
});
const searchOptions = {
    listen_port: '监听端口',
    backend_ip: '源IP',
    id: '转发ID',
    uid: '用户ID',
    cname_hostname: 'CNAME主机名',
};
const names: Record<string, string> = {
    listen_protocol: '监听协议',
    balance_way: '负载方式',
    proxy_protocol: 'Proxy Protocol',
};
const values: Record<string, { value: string; label: string }[]> = {
    listen_protocol: [
        { value: 'tcp', label: 'TCP' },
        { value: 'udp', label: 'UDP' },
    ],
    balance_way: [
        { value: 'rr', label: '轮询' },
        { value: 'ip_hash', label: '定源' },
    ],
    proxy_protocol: [
        { value: '1', label: '开启' },
        { value: '0', label: '关闭' },
    ],
};
const enabled = (value: unknown) =>
    value === true || value === 1 || value === '1';
const message = (e: unknown) =>
    e instanceof Error ? e.message : '操作失败，请重试';
const columns = computed<ColumnDef[]>(() => [
    { key: 'id', label: 'ID', width: '60px' },
    ...(tab.value === 'streams'
        ? [
              { key: 'listen', label: '监听端口' },
              { key: 'cname', label: 'CNAME' },
              { key: 'backend', label: '源站' },
              { key: 'package', label: '套餐/分组' },
              { key: 'line', label: '线路' },
              { key: 'status', label: '状态' },
          ]
        : tab.value === 'groups'
          ? [
                { key: 'user', label: '用户' },
                { key: 'name', label: '名称' },
                { key: 'des', label: '备注' },
            ]
          : [
                { key: 'user', label: '用户' },
                { key: 'name', label: '设置项' },
                { key: 'value', label: '设置值' },
                { key: 'scope', label: '生效范围' },
            ]),
]);
const pages = computed(() => Math.max(1, Math.ceil(total.value / size.value)));

function configValue(row: CdnflyRecord) {
    return (
        values[String(row.name)]?.find(
            (option) => option.value === String(row.value),
        )?.label ||
        row.value ||
        '-'
    );
}
function cname(row: CdnflyRecord) {
    return (
        row.cname ||
        (row.cname_mode === 'site'
            ? [row.cname_hostname, row.cname_domain]
            : [
                  row.up_cname_hostname || row.cname_hostname,
                  row.up_cname_domain || row.cname_domain,
              ]
        )
            .filter(Boolean)
            .join('.') ||
        '-'
    );
}
function status(row: CdnflyRecord) {
    if (!enabled(row.enable)) {
        return '已停用';
    }

    if (row.state === 'failed' || row.sync_state === 'failed') {
        return '异常';
    }

    if (row.sync_state && row.sync_state !== 'done') {
        return '同步中';
    }

    return '正常';
}
let request = 0;
onMounted(() => void load());
onUnmounted(() => {
    request++;
    groupRequest++;
});
defineExpose({ refresh: () => load() });
async function load(target = page.value) {
    const token = ++request;
    loading.value = true;
    error.value = '';
    selected.value = [];
    page.value = target;
    const query = new URLSearchParams({
        page: String(target),
        limit: String(size.value),
    });

    if (tab.value === 'streams') {
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== '') {
                query.set(key, value.trim());
            }
        });

        if (search.value.trim()) {
            query.set(searchType.value, search.value.trim());
        }
    }

    try {
        const data = await apiRequest(`${endpoints[tab.value]}?${query}`);

        if (token !== request) {
            return;
        }

        rows.value = extractCdnflyRows(data);
        total.value = extractCdnflyTotal(data, rows.value.length);

        if (page.value > pages.value) {
            void load(pages.value);
        }
    } catch (e) {
        if (token === request) {
            error.value = message(e);
            rows.value = [];
            total.value = 0;
        }
    } finally {
        if (token === request) {
            loading.value = false;
        }
    }
}
function selectTab(value: Tab) {
    if (busy.value || value === tab.value) {
        return;
    }

    tab.value = value;
    size.value = 10;
    void load(1);
}
function clear() {
    search.value = '';
    Object.keys(filters).forEach((key) => {
        filters[key as keyof typeof filters] = '';
    });
    void load(1);
}
async function manage(row: CdnflyRecord) {
    busy.value = true;

    try {
        const response = await apiRequest<CdnflyRecord>(
            `/api/admin/streams/${row.id}`,
        );
        const detail =
            response.data &&
            typeof response.data === 'object' &&
            !Array.isArray(response.data)
                ? (response.data as CdnflyRecord)
                : response;

        if (!detail.id || !detail.listen || !detail.backend) {
            throw new Error('无法读取完整转发配置，请重试');
        }

        emit('manage', detail);
    } catch (e) {
        toast.error(message(e));
    } finally {
        busy.value = false;
    }
}
const editorOpen = ref(false),
    editorError = ref(''),
    editing = ref<number | null>(null),
    editorTab = ref<'groups' | 'defaults'>('groups');
const form = reactive({
    uid: '',
    name: '',
    des: '',
    value: 'tcp',
    scope_name: 'global',
    scope_id: '',
});
const groups = ref<CdnflyRecord[]>([]),
    groupsLoading = ref(false);
let groupRequest = 0;
async function loadGroups() {
    const token = ++groupRequest;
    groups.value = [];
    const uid = Number(form.uid);

    if (!Number.isInteger(uid) || uid < 1) {
        groupsLoading.value = false;

        return;
    }

    groupsLoading.value = true;

    try {
        const data = await apiRequest(
            `/api/admin/stream-groups?limit=0&uid=${uid}`,
        );

        if (token === groupRequest) {
            groups.value = extractCdnflyRows(data).filter(
                (row) => Number(row.uid) === uid,
            );
        }
    } catch (e) {
        if (token === groupRequest) {
            editorError.value = message(e);
        }
    } finally {
        if (token === groupRequest) {
            groupsLoading.value = false;
        }
    }
}
function editResource(row?: CdnflyRecord) {
    editorTab.value = tab.value === 'defaults' ? 'defaults' : 'groups';
    editing.value = row ? Number(row.id) : null;
    editorError.value = '';
    Object.assign(form, {
        uid: String(row?.uid || ''),
        name: String(
            row?.name ||
                (editorTab.value === 'defaults' ? 'listen_protocol' : ''),
        ),
        des: String(row?.des || ''),
        value: String(row?.value ?? 'tcp'),
        scope_name: String(row?.scope_name || 'global'),
        scope_id: String(row?.scope_id || ''),
    });
    editorOpen.value = true;

    if (editorTab.value === 'defaults') {
        void loadGroups();
    }
}
async function saveResource() {
    const uid = Number(form.uid);

    if (!Number.isInteger(uid) || uid < 1 || !form.name.trim()) {
        editorError.value = '请填写有效的用户 ID 和名称';

        return;
    }

    const payload: CdnflyRecord = { uid, name: form.name.trim() };

    if (editorTab.value === 'groups') {
        payload.des = form.des.trim();
    } else {
        if (!values[form.name]?.some((option) => option.value === form.value)) {
            editorError.value = '请选择有效的设置值';

            return;
        }

        if (
            form.scope_name === 'group' &&
            !groups.value.some(
                (row) => Number(row.id) === Number(form.scope_id),
            )
        ) {
            editorError.value = '请选择该用户的转发分组';

            return;
        }

        Object.assign(payload, {
            type: 'stream',
            value: form.value,
            scope_name: form.scope_name,
            scope_id: form.scope_name === 'global' ? 0 : Number(form.scope_id),
        });
    }

    busy.value = true;
    editorError.value = '';

    try {
        await apiRequest(
            endpoints[editorTab.value] +
                (editing.value ? `/${editing.value}` : ''),
            {
                method: editing.value ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            },
        );
        editorOpen.value = false;
        toast.success('保存成功');
        await load();
    } catch (e) {
        editorError.value = message(e);
    } finally {
        busy.value = false;
    }
}
const batchOpen = ref(false),
    batchError = ref('');
const batchForm = reactive({
    balance_way: '',
    proxy_protocol: '',
    conn_limit: '',
});
const patchFields = reactive({
    balance_way: false,
    proxy_protocol: false,
    conn_limit: false,
});
function openBatch() {
    Object.assign(patchFields, {
        balance_way: false,
        proxy_protocol: false,
        conn_limit: false,
    });
    Object.assign(batchForm, {
        balance_way: 'rr',
        proxy_protocol: '0',
        conn_limit: '',
    });
    batchError.value = '';
    batchOpen.value = true;
}
async function applyBatch() {
    const patch: CdnflyRecord = {};

    if (patchFields.balance_way) {
        patch.balance_way = batchForm.balance_way;
    }

    if (patchFields.proxy_protocol) {
        patch.proxy_protocol = Number(batchForm.proxy_protocol);
    }

    if (patchFields.conn_limit) {
        if (
            batchForm.conn_limit !== '' &&
            (!Number.isInteger(Number(batchForm.conn_limit)) ||
                Number(batchForm.conn_limit) < 0)
        ) {
            batchError.value = '连接限制必须为非负整数';

            return;
        }

        patch.conn_limit =
            batchForm.conn_limit === '' ? '' : Number(batchForm.conn_limit);
    }

    if (!Object.keys(patch).length) {
        batchError.value = '请选择需要修改的设置';

        return;
    }

    const success = await mutate([...selected.value], 'PUT', patch);

    if (success) {
        batchOpen.value = false;
    } else {
        batchError.value = error.value;
    }
}
const deleteOpen = ref(false),
    deleteIds = ref<number[]>([]),
    deleteError = ref('');
function confirmDelete(ids = [...selected.value]) {
    deleteIds.value = ids;
    deleteError.value = '';
    deleteOpen.value = true;
}
async function remove() {
    const success = await mutate([...deleteIds.value], 'DELETE');

    if (success) {
        deleteOpen.value = false;
    } else {
        deleteError.value = error.value;
        deleteIds.value = [...selected.value];
    }
}
async function mutate(ids: number[], method: string, payload?: CdnflyRecord) {
    if (!ids.length || busy.value) {
        return false;
    }

    busy.value = true;
    const failed: number[] = [];
    let detail = '';
    const endpoint = endpoints[tab.value];

    for (const id of ids) {
        try {
            await apiRequest(`${endpoint}/${id}`, {
                method,
                ...(payload ? { body: JSON.stringify(payload) } : {}),
            });
        } catch (e) {
            failed.push(id);
            detail = message(e);
        }
    }

    await load();
    selected.value = failed;
    busy.value = false;

    if (failed.length) {
        error.value = `${failed.length} 项操作失败：${detail}`;

        return false;
    }

    toast.success('操作成功');

    return true;
}
</script>

<template>
    <section class="console-admin-stream-workspace stream-workspace">
        <nav role="tablist" aria-label="四层转发" class="stream-tabs">
            <Button
                size="sm"
                :variant="tab === item.key ? 'secondary' : 'ghost'"
                v-for="item in tabs"
                :id="`stream-tab-${item.key}`"
                :key="item.key"
                role="tab"
                :aria-selected="tab === item.key"
                :aria-controls="`stream-panel-${item.key}`"
                :class="{ active: tab === item.key }"
                :disabled="busy"
                @click="selectTab(item.key)"
            >
                {{ item.label }}
            </Button>
        </nav>
        <div
            :id="`stream-panel-${tab}`"
            role="tabpanel"
            :aria-labelledby="`stream-tab-${tab}`"
            :aria-busy="loading || busy"
            class="stream-panel"
            :class="{ 'default-panel': tab === 'defaults' }"
        >
            <header v-if="tab === 'defaults'" class="default-heading">
                <div>
                    <h2 data-typography="section-title">四层转发默认配置</h2>
                    <p data-typography="description">
                        配置新增四层转发时自动带入的监听协议、负载方式与 Proxy
                        Protocol。
                    </p>
                </div>
                <div class="actions">
                    <Button
                        size="sm"
                        variant="default"
                        class="primary"
                        :disabled="busy"
                        @click="editResource()"
                    >
                        <Plus />新增设置</Button
                    ><Button
                        size="sm"
                        variant="outline"
                        :disabled="!selected.length || busy || loading"
                        @click="confirmDelete()"
                    >
                        <Trash2 />删除
                    </Button>
                </div>
            </header>
            <div v-else class="toolbar">
                <div v-if="tab === 'streams'" class="actions">
                    <Button
                        size="sm"
                        variant="default"
                        class="primary"
                        :disabled="busy"
                        @click="emit('create')"
                    >
                        <Plus />添加转发</Button
                    ><Button
                        size="sm"
                        variant="outline"
                        :disabled="!selected.length || busy || loading"
                        @click="openBatch"
                    >
                        <Pencil />批量修改</Button
                    ><DropdownMenu
                        ><DropdownMenuTrigger as-child
                            ><Button
                                size="sm"
                                variant="outline"
                                :disabled="busy"
                            >
                                <MoreHorizontal />更多操作<ChevronDown /></Button></DropdownMenuTrigger
                        ><DropdownMenuContent
                            class="console-admin-stream-workspace"
                            align="start"
                            ><DropdownMenuItem
                                :disabled="!selected.length || loading"
                                @select="
                                    mutate([...selected], 'PUT', { enable: 1 })
                                "
                                >启用</DropdownMenuItem
                            ><DropdownMenuItem
                                :disabled="!selected.length || loading"
                                @select="
                                    mutate([...selected], 'PUT', { enable: 0 })
                                "
                                >停用</DropdownMenuItem
                            ><DropdownMenuItem
                                :disabled="!selected.length || loading"
                                @select="confirmDelete()"
                                >删除</DropdownMenuItem
                            ><DropdownMenuItem @select="batchCreate?.show()"
                                >批量新增</DropdownMenuItem
                            ></DropdownMenuContent
                        ></DropdownMenu
                    >
                </div>
                <div v-else class="actions">
                    <Button
                        size="sm"
                        variant="default"
                        class="primary"
                        :disabled="busy"
                        @click="editResource()"
                    >
                        <Plus />新增分组</Button
                    ><Button
                        size="sm"
                        variant="outline"
                        :disabled="busy || loading"
                        @click="load()"
                    >
                        <RefreshCw />刷新</Button
                    ><Button
                        size="sm"
                        variant="destructive"
                        class="danger-button"
                        :disabled="!selected.length || busy || loading"
                        @click="confirmDelete()"
                    >
                        <Trash2 />删除
                    </Button>
                </div>
                <form
                    v-if="tab === 'streams'"
                    class="actions search"
                    @submit.prevent="load(1)"
                >
                    <div data-slot="console-input-group" class="search-box">
                        <SelectField v-model="searchType" aria-label="搜索类型">
                            <SelectOption
                                v-for="(label, key) in searchOptions"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectOption></SelectField
                        ><Input
                            v-model="search"
                            aria-label="搜索转发"
                            :placeholder="`输入${searchOptions[searchType as keyof typeof searchOptions]}`"
                            :disabled="busy"
                        /><Button
                            size="sm"
                            variant="default"
                            class="primary"
                            :disabled="busy"
                            >查询</Button
                        >
                    </div>
                    <Button
                        size="sm"
                        variant="outline"
                        type="button"
                        :aria-expanded="advanced"
                        :disabled="busy"
                        @click="advanced = !advanced"
                    >
                        <Filter />筛选</Button
                    ><Button
                        size="sm"
                        variant="outline"
                        type="button"
                        :disabled="busy || loading"
                        @click="load()"
                    >
                        <RefreshCw :class="{ spin: loading }" />刷新
                    </Button>
                </form>
                <span v-else class="group-count"
                    ><i />共 {{ total }} 个分组</span
                >
            </div>
            <form
                v-if="tab === 'streams' && advanced"
                class="advanced"
                @submit.prevent="load(1)"
            >
                <label
                    >监听协议<SelectField
                        v-model="filters.listen_protocol"
                        aria-label="监听协议"
                    >
                        <SelectOption value="">全部</SelectOption>
                        <SelectOption value="tcp">TCP</SelectOption>
                        <SelectOption value="udp">UDP</SelectOption>
                    </SelectField></label
                ><label
                    >启用状态<SelectField
                        v-model="filters.enable"
                        aria-label="启用状态"
                    >
                        <SelectOption value="">全部</SelectOption>
                        <SelectOption value="1">启用</SelectOption>
                        <SelectOption value="0">停用</SelectOption>
                    </SelectField></label
                ><label
                    >同步状态<SelectField
                        v-model="filters.sync_state"
                        aria-label="同步状态"
                    >
                        <SelectOption value="">全部</SelectOption>
                        <SelectOption value="done">正常</SelectOption>
                        <SelectOption value="pending">待同步</SelectOption>
                        <SelectOption value="failed">失败</SelectOption>
                    </SelectField></label
                >
                <label
                    v-for="(label, key) in {
                        uid: '用户ID',
                        group: '分组ID',
                        user_package: '转发套餐ID',
                        region: '区域ID',
                        node_group: '线路分组ID',
                    }"
                    :key="key"
                    >{{ label
                    }}<Input
                        v-model="filters[key]"
                        inputmode="numeric" /></label
                ><Button
                    size="sm"
                    variant="default"
                    class="primary"
                    :disabled="busy"
                    >查询</Button
                ><Button
                    size="sm"
                    variant="outline"
                    type="button"
                    :disabled="busy"
                    @click="clear"
                >
                    清除
                </Button>
            </form>
            <div v-if="error" role="alert" class="error">
                {{ error }}
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="loading || busy"
                    @click="load()"
                >
                    重试
                </Button>
            </div>
            <ConsoleDataTable
                embedded
                selectable
                title="四层转发"
                :columns="columns"
                :data="{ rows, total, page, pageSize: size, loading }"
                :selected="selected"
                :selection-disabled="busy"
                :empty-text="
                    error
                        ? '加载失败，请重试'
                        : tab === 'streams'
                          ? '暂无转发数据'
                          : '暂无数据'
                "
                @update:selected="selected = $event.map(Number)"
            >
                <template #cell-listen="{ row }">
                    <Button
                        variant="link"
                        size="sm"
                        class="h-auto p-0"
                        :disabled="busy || loading"
                        @click="manage(row)"
                        >{{ streamListenText(row.listen) }}</Button
                    >
                    <div class="muted">
                        用户 {{ row.name || row.username || '-' }} ({{
                            row.uid || row.user_id || '-'
                        }})
                    </div>
                </template>
                <template #cell-cname="{ row }"
                    ><span
                        :class="{
                            muted:
                                row.cname_state && row.cname_state !== 'done',
                        }"
                        >{{
                            row.cname_state === 'failed'
                                ? '生成失败'
                                : row.cname_state && row.cname_state !== 'done'
                                  ? '生成中'
                                  : cname(row)
                        }}</span
                    ></template
                >
                <template #cell-backend="{ row }">{{
                    streamBackendText(row)
                }}</template>
                <template #cell-package="{ row }"
                    ><div>
                        套餐 {{ row.package_name || row.user_package || '-' }}
                    </div>
                    <div class="muted">
                        分组 {{ row.group_name || '-' }}
                    </div></template
                >
                <template #cell-line="{ row }">{{
                    [
                        row.region_name,
                        row.node_group_name,
                        row.backup_node_group_name
                            ? `备: ${row.backup_node_group_name}`
                            : '',
                    ]
                        .filter(Boolean)
                        .join(' / ') || '-'
                }}</template>
                <template #cell-status="{ row }"
                    ><Badge
                        :variant="
                            status(row) === '异常' ? 'destructive' : 'secondary'
                        "
                        >{{ status(row) }}</Badge
                    ></template
                >
                <template #cell-user="{ row }">{{
                    row.username || row.user_name || row.uid || '-'
                }}</template>
                <template #cell-name="{ row }"
                    ><Button
                        v-if="tab === 'groups'"
                        variant="link"
                        size="sm"
                        class="h-auto p-0"
                        :disabled="busy"
                        @click="editResource(row)"
                        >{{ row.name }}</Button
                    ><template v-else>{{
                        names[String(row.name)] || row.name
                    }}</template></template
                >
                <template #cell-value="{ row }">{{
                    configValue(row)
                }}</template>
                <template #cell-scope="{ row }">{{
                    row.scope_name === 'group'
                        ? row.group_name || `分组 ${row.scope_id}`
                        : '全局'
                }}</template>
                <template #row-actions="{ row }">
                    <Button
                        variant="ghost"
                        size="sm"
                        :disabled="busy || loading"
                        @click="
                            tab === 'streams' ? manage(row) : editResource(row)
                        "
                        >{{ tab === 'streams' ? '管理' : '编辑' }}</Button
                    >
                    <DropdownMenu v-if="tab === 'streams'"
                        ><DropdownMenuTrigger as-child
                            ><Button
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="`更多操作 ${row.id}`"
                                :disabled="busy || loading"
                                ><MoreHorizontal /></Button></DropdownMenuTrigger
                        ><DropdownMenuContent
                            class="console-admin-stream-workspace"
                            align="end"
                            ><DropdownMenuItem
                                @select="
                                    mutate([Number(row.id)], 'PUT', {
                                        enable: enabled(row.enable) ? 0 : 1,
                                    })
                                "
                                >{{
                                    enabled(row.enable) ? '停用' : '启用'
                                }}</DropdownMenuItem
                            ><DropdownMenuItem
                                @select="confirmDelete([Number(row.id)])"
                                >删除</DropdownMenuItem
                            ></DropdownMenuContent
                        ></DropdownMenu
                    >
                    <Button
                        v-else
                        variant="ghost"
                        size="sm"
                        :disabled="busy || loading"
                        @click="confirmDelete([Number(row.id)])"
                        >删除</Button
                    >
                </template>
            </ConsoleDataTable>
            <footer :class="{ right: tab !== 'streams' }">
                <span v-if="tab === 'groups'" class="group-note muted">{{
                    total ? `已选 ${selected.length} 个分组` : '暂无分组'
                }}</span>
                <ConsolePagination
                    aria-label="分页"
                    :total="total"
                    :page="page"
                    :previous-disabled="page <= 1 || loading || busy"
                    :next-disabled="page >= pages || loading || busy"
                    @previous="load(page - 1)"
                    @next="load(page + 1)"
                />
            </footer>
        </div>
        <StreamBatchCreate
            ref="batchCreate"
            scope="admin"
            hide-trigger
            @updated="load()"
        />
        <Dialog v-model:open="editorOpen"
            ><DialogScrollContent
                class="console-admin-stream-workspace stream-resource-dialog sm:max-w-lg"
                ><DialogHeader
                    ><DialogTitle
                        >{{ editing ? '编辑' : '新增'
                        }}{{
                            editorTab === 'groups' ? '分组' : '设置'
                        }}</DialogTitle
                    ><DialogDescription>{{
                        editorTab === 'groups'
                            ? '为用户管理四层转发分组。'
                            : '设置该用户新增四层转发时使用的默认配置。'
                    }}</DialogDescription></DialogHeader
                >
                <form class="resource-form" @submit.prevent="saveResource">
                    <p
                        data-typography="body"
                        v-if="editorError"
                        role="alert"
                        class="error"
                    >
                        {{ editorError }}
                    </p>
                    <label
                        >用户 ID<Input
                            v-model="form.uid"
                            type="number"
                            min="1"
                            required
                            :disabled="!!editing || busy"
                            @change="
                                form.scope_id = '';
                                loadGroups();
                            " /></label
                    ><template v-if="editorTab === 'groups'"
                        ><label
                            >名称<Input
                                v-model="form.name"
                                required
                                maxlength="255" /></label
                        ><label
                            >备注<Input
                                v-model="form.des"
                                maxlength="1000" /></label></template
                    ><template v-else
                        ><label
                            >设置项<SelectField
                                aria-label="设置项"
                                v-model="form.name"
                                @change="
                                    form.value = values[form.name][0].value
                                "
                            >
                                <SelectOption
                                    v-for="(label, key) in names"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </SelectOption>
                            </SelectField></label
                        ><label
                            >设置值<SelectField
                                v-model="form.value"
                                aria-label="设置值"
                            >
                                <SelectOption
                                    v-for="option in values[form.name]"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectOption>
                            </SelectField></label
                        ><label
                            >生效范围<SelectField
                                v-model="form.scope_name"
                                aria-label="生效范围"
                            >
                                <SelectOption value="global">全局</SelectOption>
                                <SelectOption value="group"
                                    >转发分组</SelectOption
                                >
                            </SelectField></label
                        ><label v-if="form.scope_name === 'group'"
                            >转发分组<SelectField
                                aria-label="转发分组"
                                v-model="form.scope_id"
                                :disabled="groupsLoading"
                                required
                            >
                                <SelectOption value="">
                                    {{
                                        groupsLoading
                                            ? '加载中…'
                                            : '请选择转发分组'
                                    }}
                                </SelectOption>
                                <SelectOption
                                    v-for="group in groups"
                                    :key="String(group.id)"
                                    :value="String(group.id)"
                                >
                                    {{ group.name }}
                                </SelectOption>
                            </SelectField></label
                        ></template
                    >
                    <div class="modal-actions">
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            :disabled="busy"
                            @click="editorOpen = false"
                        >
                            取消</Button
                        ><Button
                            size="sm"
                            variant="default"
                            class="primary"
                            :disabled="busy || groupsLoading"
                        >
                            {{ busy ? '保存中…' : '保存' }}
                        </Button>
                    </div>
                </form></DialogScrollContent
            ></Dialog
        >
        <Dialog v-model:open="batchOpen"
            ><DialogScrollContent
                class="console-admin-stream-workspace stream-resource-dialog sm:max-w-lg"
                ><DialogHeader
                    ><DialogTitle>批量修改</DialogTitle
                    ><DialogDescription
                        >勾选需要修改的设置，应用到所选
                        {{ selected.length }} 条转发。</DialogDescription
                    ></DialogHeader
                >
                <form class="resource-form" @submit.prevent="applyBatch">
                    <p
                        data-typography="body"
                        v-if="batchError"
                        role="alert"
                        class="error"
                    >
                        {{ batchError }}
                    </p>
                    <div
                        v-for="(label, key) in {
                            balance_way: '负载方式',
                            proxy_protocol: 'Proxy Protocol',
                            conn_limit: '连接限制',
                        }"
                        :key="key"
                        class="batch-field"
                    >
                        <label
                            ><CheckboxField v-model="patchFields[key]" />{{
                                label
                            }}</label
                        ><Input
                            v-if="key === 'conn_limit'"
                            v-model="batchForm.conn_limit"
                            type="number"
                            min="0"
                            placeholder="留空则不限"
                            :disabled="!patchFields[key]"
                        /><SelectField
                            v-else
                            v-model="batchForm[key]"
                            :disabled="!patchFields[key]"
                        >
                            <SelectOption
                                v-for="option in values[key]"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectOption>
                        </SelectField>
                    </div>
                    <div class="modal-actions">
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            :disabled="busy"
                            @click="batchOpen = false"
                        >
                            取消</Button
                        ><Button
                            size="sm"
                            variant="default"
                            class="primary"
                            :disabled="busy || !selected.length"
                        >
                            保存
                        </Button>
                    </div>
                </form></DialogScrollContent
            ></Dialog
        >
        <ConfirmDeleteDialog
            :open="deleteOpen"
            :description="`确认删除所选 ${deleteIds.length} 项？`"
            :loading="busy"
            :error="deleteError"
            @cancel="!busy && (deleteOpen = false)"
            @confirm="remove"
        />
    </section>
</template>
