<script setup lang="ts">
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
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
import StreamBatchCreate from '@/components/console/StreamBatchCreate.vue';
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
const pages = computed(() => Math.max(1, Math.ceil(total.value / size.value)));
const numbers = computed(() =>
    Array.from(
        { length: Math.min(5, pages.value) },
        (_, i) => Math.max(1, Math.min(page.value - 2, pages.value - 4)) + i,
    ),
);
const allSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selected.value.includes(Number(row.id))),
);
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
function toggle(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((value) => value !== id)
        : [...selected.value, id];
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
    <section class="stream-workspace">
        <nav role="tablist" aria-label="四层转发" class="stream-tabs">
            <button
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
            </button>
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
                    <h2>四层转发默认配置</h2>
                    <p>
                        配置新增四层转发时自动带入的监听协议、负载方式与 Proxy
                        Protocol。
                    </p>
                </div>
                <div class="actions">
                    <button
                        class="primary"
                        :disabled="busy"
                        @click="editResource()"
                    >
                        <Plus />新增设置</button
                    ><button
                        :disabled="!selected.length || busy || loading"
                        @click="confirmDelete()"
                    >
                        <Trash2 />删除
                    </button>
                </div>
            </header>
            <div v-else class="toolbar">
                <div v-if="tab === 'streams'" class="actions">
                    <button
                        class="primary"
                        :disabled="busy"
                        @click="emit('create')"
                    >
                        <Plus />添加转发</button
                    ><button
                        :disabled="!selected.length || busy || loading"
                        @click="openBatch"
                    >
                        <Pencil />批量修改</button
                    ><DropdownMenu
                        ><DropdownMenuTrigger as-child
                            ><button :disabled="busy">
                                <MoreHorizontal />更多操作<ChevronDown /></button></DropdownMenuTrigger
                        ><DropdownMenuContent align="start"
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
                    <button
                        class="primary"
                        :disabled="busy"
                        @click="editResource()"
                    >
                        <Plus />新增分组</button
                    ><button :disabled="busy || loading" @click="load()">
                        <RefreshCw />刷新</button
                    ><button
                        class="danger-button"
                        :disabled="!selected.length || busy || loading"
                        @click="confirmDelete()"
                    >
                        <Trash2 />删除
                    </button>
                </div>
                <form
                    v-if="tab === 'streams'"
                    class="actions search"
                    @submit.prevent="load(1)"
                >
                    <div class="search-box">
                        <select v-model="searchType" aria-label="搜索类型">
                            <option
                                v-for="(label, key) in searchOptions"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option></select
                        ><input
                            v-model="search"
                            aria-label="搜索转发"
                            :placeholder="`输入${searchOptions[searchType as keyof typeof searchOptions]}`"
                            :disabled="busy"
                        /><button class="primary" :disabled="busy">查询</button>
                    </div>
                    <button
                        type="button"
                        :aria-expanded="advanced"
                        :disabled="busy"
                        @click="advanced = !advanced"
                    >
                        <Filter />筛选</button
                    ><button
                        type="button"
                        :disabled="busy || loading"
                        @click="load()"
                    >
                        <RefreshCw :class="{ spin: loading }" />刷新
                    </button>
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
                    >监听协议<select
                        v-model="filters.listen_protocol"
                        aria-label="监听协议"
                    >
                        <option value="">全部</option>
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                    </select></label
                ><label
                    >启用状态<select
                        v-model="filters.enable"
                        aria-label="启用状态"
                    >
                        <option value="">全部</option>
                        <option value="1">启用</option>
                        <option value="0">停用</option>
                    </select></label
                ><label
                    >同步状态<select
                        v-model="filters.sync_state"
                        aria-label="同步状态"
                    >
                        <option value="">全部</option>
                        <option value="done">正常</option>
                        <option value="pending">待同步</option>
                        <option value="failed">失败</option>
                    </select></label
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
                    }}<input
                        v-model="filters[key]"
                        inputmode="numeric" /></label
                ><button class="primary" :disabled="busy">查询</button
                ><button type="button" :disabled="busy" @click="clear">
                    清除
                </button>
            </form>
            <div v-if="error" role="alert" class="error">
                {{ error }}
                <button :disabled="loading || busy" @click="load()">
                    重试
                </button>
            </div>
            <div class="table-scroll">
                <table :class="{ 'forwarding-table': tab === 'streams' }">
                    <thead>
                        <tr>
                            <th class="check">
                                <input
                                    type="checkbox"
                                    aria-label="选择全部"
                                    :checked="allSelected"
                                    :disabled="loading || busy || !rows.length"
                                    @change="
                                        selected = allSelected
                                            ? []
                                            : rows.map((row) => Number(row.id))
                                    "
                                />
                            </th>
                            <th>ID</th>
                            <template v-if="tab === 'streams'"
                                ><th>监听端口</th>
                                <th>CNAME</th>
                                <th>源站</th>
                                <th>套餐/分组</th>
                                <th>线路</th>
                                <th>状态</th></template
                            ><template v-else-if="tab === 'groups'"
                                ><th>用户</th>
                                <th>名称</th>
                                <th>备注</th></template
                            ><template v-else
                                ><th>用户</th>
                                <th>设置项</th>
                                <th>设置值</th>
                                <th>生效范围</th></template
                            >
                            <th class="operation">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="String(row.id)">
                            <td class="check">
                                <input
                                    type="checkbox"
                                    :aria-label="`选择 ${row.id}`"
                                    :checked="selected.includes(Number(row.id))"
                                    :disabled="loading || busy"
                                    @change="toggle(Number(row.id))"
                                />
                            </td>
                            <td>{{ row.id }}</td>
                            <template v-if="tab === 'streams'"
                                ><td>
                                    <button
                                        class="link"
                                        :disabled="busy || loading"
                                        @click="manage(row)"
                                    >
                                        {{ streamListenText(row.listen) }}
                                    </button>
                                    <div class="muted">
                                        用户
                                        {{ row.name || row.username || '-' }}
                                        ({{ row.uid || row.user_id || '-' }})
                                    </div>
                                </td>
                                <td>
                                    <span
                                        :class="{
                                            muted:
                                                row.cname_state &&
                                                row.cname_state !== 'done',
                                        }"
                                        >{{
                                            row.cname_state === 'failed'
                                                ? '生成失败'
                                                : row.cname_state &&
                                                    row.cname_state !== 'done'
                                                  ? '生成中'
                                                  : cname(row)
                                        }}</span
                                    >
                                </td>
                                <td>{{ streamBackendText(row) }}</td>
                                <td>
                                    <div>
                                        套餐
                                        {{
                                            row.package_name ||
                                            row.user_package ||
                                            '-'
                                        }}
                                    </div>
                                    <div class="muted">
                                        分组 {{ row.group_name || '-' }}
                                    </div>
                                </td>
                                <td>
                                    {{
                                        [
                                            row.region_name,
                                            row.node_group_name,
                                            row.backup_node_group_name
                                                ? `备: ${row.backup_node_group_name}`
                                                : '',
                                        ]
                                            .filter(Boolean)
                                            .join(' / ') || '-'
                                    }}
                                </td>
                                <td>
                                    <span
                                        class="pill"
                                        :class="{
                                            success: status(row) === '正常',
                                            danger: status(row) === '异常',
                                        }"
                                        >{{ status(row) }}</span
                                    >
                                </td></template
                            >
                            <template v-else-if="tab === 'groups'"
                                ><td>
                                    {{
                                        row.username ||
                                        row.user_name ||
                                        row.uid ||
                                        '-'
                                    }}
                                </td>
                                <td>
                                    <button
                                        class="link"
                                        :disabled="busy"
                                        @click="editResource(row)"
                                    >
                                        {{ row.name }}
                                    </button>
                                </td>
                                <td>{{ row.des || '-' }}</td></template
                            >
                            <template v-else
                                ><td>
                                    {{
                                        row.username ||
                                        row.user_name ||
                                        row.uid ||
                                        '-'
                                    }}
                                </td>
                                <td>
                                    {{ names[String(row.name)] || row.name }}
                                </td>
                                <td>{{ configValue(row) }}</td>
                                <td>
                                    {{
                                        row.scope_name === 'group'
                                            ? row.group_name ||
                                              `分组 ${row.scope_id}`
                                            : '全局'
                                    }}
                                </td></template
                            >
                            <td class="operation">
                                <div class="actions">
                                    <button
                                        class="link"
                                        :disabled="busy || loading"
                                        @click="
                                            tab === 'streams'
                                                ? manage(row)
                                                : editResource(row)
                                        "
                                    >
                                        {{
                                            tab === 'streams' ? '管理' : '编辑'
                                        }}</button
                                    ><DropdownMenu v-if="tab === 'streams'"
                                        ><DropdownMenuTrigger as-child
                                            ><button
                                                class="link"
                                                :aria-label="`更多操作 ${row.id}`"
                                                :disabled="busy || loading"
                                            >
                                                <MoreHorizontal /></button></DropdownMenuTrigger
                                        ><DropdownMenuContent align="end"
                                            ><DropdownMenuItem
                                                @select="
                                                    mutate(
                                                        [Number(row.id)],
                                                        'PUT',
                                                        {
                                                            enable: enabled(
                                                                row.enable,
                                                            )
                                                                ? 0
                                                                : 1,
                                                        },
                                                    )
                                                "
                                                >{{
                                                    enabled(row.enable)
                                                        ? '停用'
                                                        : '启用'
                                                }}</DropdownMenuItem
                                            ><DropdownMenuItem
                                                @select="
                                                    confirmDelete([
                                                        Number(row.id),
                                                    ])
                                                "
                                                >删除</DropdownMenuItem
                                            ></DropdownMenuContent
                                        ></DropdownMenu
                                    ><button
                                        v-else
                                        class="link"
                                        :disabled="busy || loading"
                                        @click="confirmDelete([Number(row.id)])"
                                    >
                                        删除
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td
                                :colspan="
                                    tab === 'streams'
                                        ? 9
                                        : tab === 'groups'
                                          ? 6
                                          : 7
                                "
                                class="empty"
                            >
                                {{
                                    loading
                                        ? '加载中…'
                                        : error
                                          ? '加载失败，请重试'
                                          : tab === 'streams'
                                            ? '暂无转发数据'
                                            : '暂无数据'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <footer :class="{ right: tab !== 'streams' }">
                <span v-if="tab === 'groups'" class="group-note muted">{{
                    total ? `已选 ${selected.length} 个分组` : '暂无分组'
                }}</span>
                <nav aria-label="分页" class="pager">
                    <span>共 {{ total }} 条</span
                    ><button
                        aria-label="上一页"
                        :disabled="page <= 1 || loading || busy"
                        @click="load(page - 1)"
                    >
                        <ChevronLeft /></button
                    ><button
                        v-for="number in numbers"
                        :key="number"
                        :class="{ current: page === number }"
                        :aria-current="page === number ? 'page' : undefined"
                        :disabled="loading || busy"
                        @click="load(number)"
                    >
                        {{ number }}</button
                    ><button
                        aria-label="下一页"
                        :disabled="page >= pages || loading || busy"
                        @click="load(page + 1)"
                    >
                        <ChevronRight /></button
                    ><select
                        v-model.number="size"
                        aria-label="每页条数"
                        :disabled="loading || busy"
                        @change="load(1)"
                    >
                        <option :value="10">10 条/页</option>
                        <option :value="30">30 条/页</option>
                        <option :value="100">100 条/页</option>
                    </select>
                </nav>
            </footer>
        </div>
        <StreamBatchCreate
            ref="batchCreate"
            scope="admin"
            hide-trigger
            @updated="load()"
        />
        <Dialog v-model:open="editorOpen"
            ><DialogScrollContent class="stream-resource-dialog sm:max-w-lg"
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
                    <p v-if="editorError" role="alert" class="error">
                        {{ editorError }}
                    </p>
                    <label
                        >用户 ID<input
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
                            >名称<input
                                v-model="form.name"
                                required
                                maxlength="255" /></label
                        ><label
                            >备注<input
                                v-model="form.des"
                                maxlength="1000" /></label></template
                    ><template v-else
                        ><label
                            >设置项<select
                                aria-label="设置项"
                                v-model="form.name"
                                @change="
                                    form.value = values[form.name][0].value
                                "
                            >
                                <option
                                    v-for="(label, key) in names"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select></label
                        ><label
                            >设置值<select
                                v-model="form.value"
                                aria-label="设置值"
                            >
                                <option
                                    v-for="option in values[form.name]"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select></label
                        ><label
                            >生效范围<select
                                v-model="form.scope_name"
                                aria-label="生效范围"
                            >
                                <option value="global">全局</option>
                                <option value="group">转发分组</option>
                            </select></label
                        ><label v-if="form.scope_name === 'group'"
                            >转发分组<select
                                aria-label="转发分组"
                                v-model="form.scope_id"
                                :disabled="groupsLoading"
                                required
                            >
                                <option value="">
                                    {{
                                        groupsLoading
                                            ? '加载中…'
                                            : '请选择转发分组'
                                    }}
                                </option>
                                <option
                                    v-for="group in groups"
                                    :key="String(group.id)"
                                    :value="String(group.id)"
                                >
                                    {{ group.name }}
                                </option>
                            </select></label
                        ></template
                    >
                    <div class="modal-actions">
                        <button
                            type="button"
                            :disabled="busy"
                            @click="editorOpen = false"
                        >
                            取消</button
                        ><button
                            class="primary"
                            :disabled="busy || groupsLoading"
                        >
                            {{ busy ? '保存中…' : '保存' }}
                        </button>
                    </div>
                </form></DialogScrollContent
            ></Dialog
        >
        <Dialog v-model:open="batchOpen"
            ><DialogScrollContent class="stream-resource-dialog sm:max-w-lg"
                ><DialogHeader
                    ><DialogTitle>批量修改</DialogTitle
                    ><DialogDescription
                        >勾选需要修改的设置，应用到所选
                        {{ selected.length }} 条转发。</DialogDescription
                    ></DialogHeader
                >
                <form class="resource-form" @submit.prevent="applyBatch">
                    <p v-if="batchError" role="alert" class="error">
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
                            ><input
                                v-model="patchFields[key]"
                                type="checkbox"
                            />{{ label }}</label
                        ><input
                            v-if="key === 'conn_limit'"
                            v-model="batchForm.conn_limit"
                            type="number"
                            min="0"
                            placeholder="留空则不限"
                            :disabled="!patchFields[key]"
                        /><select
                            v-else
                            v-model="batchForm[key]"
                            :disabled="!patchFields[key]"
                        >
                            <option
                                v-for="option in values[key]"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button
                            type="button"
                            :disabled="busy"
                            @click="batchOpen = false"
                        >
                            取消</button
                        ><button
                            class="primary"
                            :disabled="busy || !selected.length"
                        >
                            保存
                        </button>
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

<style scoped>
.stream-workspace {
    --line: #e8eef7;
    --ink: #344766;
    --muted: #8994a8;
    min-width: 0;
    background: #fff;
    color: var(--ink);
    font-size: 12px;
    border-radius: 8px;
    box-shadow: 0 3px 8px #20385b10;
}
.stream-tabs {
    display: flex;
    gap: 4px;
    padding: 8px 15px;
    border-bottom: 1px solid var(--line);
}
.stream-tabs button {
    border: 0;
    padding: 0 14px;
}
.stream-tabs .active {
    color: #2d8cf0;
    background: #e6f2ff;
    font-weight: 600;
}
.stream-panel {
    padding: 15px;
}
.toolbar,
.actions,
.search-box,
.default-heading,
footer,
.pager {
    display: flex;
    align-items: center;
    gap: 8px;
}
.toolbar {
    justify-content: space-between;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.actions {
    flex-wrap: wrap;
}
button,
input,
select {
    font: inherit;
}
button,
input:not([type='checkbox']),
select {
    height: 28px;
    border: 1px solid #d7dce5;
    border-radius: 3px;
    background: transparent;
}
button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 0 12px;
    white-space: nowrap;
    cursor: pointer;
}
button svg {
    width: 12px;
    height: 12px;
}
button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
button:hover:not(:disabled) {
    color: #2d8cf0;
    border-color: #2d8cf0;
}
.primary {
    background: #2d8cf0;
    border-color: #2d8cf0;
    color: white;
}
.primary:hover:not(:disabled) {
    background: #57a3f3;
    color: white;
}
.danger-button {
    color: #ed4014;
    border-color: #ffd4ca;
    background: #fff7f5;
}
input:not([type='checkbox']),
select {
    padding: 0 7px;
    min-width: 0;
}
input::placeholder {
    color: #b7bfcc;
}
input[type='checkbox'] {
    width: 14px;
    height: 14px;
    accent-color: #2d8cf0;
    vertical-align: middle;
}
.search-box {
    gap: 0;
}
.search-box select {
    width: 78px;
    border-radius: 3px 0 0 3px;
}
.search-box input {
    width: 158px;
    border-radius: 0;
    border-left: 0;
    border-right: 0;
}
.search-box button {
    border-radius: 0 3px 3px 0;
}
.group-count {
    border: 1px solid var(--line);
    border-radius: 3px;
    padding: 5px 8px;
    color: var(--muted);
}
.group-count i {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 100%;
    background: #19be6b;
    margin-right: 5px;
}
.advanced {
    display: flex;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px;
    background: #fafcff;
    border: 1px solid var(--line);
    border-radius: 4px;
    margin-bottom: 12px;
}
.advanced label {
    display: grid;
    gap: 5px;
}
.advanced input,
.advanced select {
    width: 120px;
}
.table-scroll {
    overflow-x: auto;
}
table {
    width: 100%;
    min-width: 720px;
    border-collapse: collapse;
    text-align: left;
}
th,
td {
    padding: 9px 8px;
    border-bottom: 1px solid var(--line);
    font-weight: 400;
}
th {
    background: #fafcff;
    white-space: nowrap;
    height: 34px;
}
td {
    overflow-wrap: anywhere;
}
.forwarding-table {
    min-width: 1100px;
}
th:nth-child(2),
td:nth-child(2) {
    min-width: 60px;
    white-space: nowrap;
}
.check {
    width: 40px;
    text-align: center;
}
.operation {
    width: 96px;
    text-align: right;
}
.operation .actions {
    justify-content: flex-end;
    flex-wrap: nowrap;
}
.link {
    border: 0;
    color: #2d8cf0;
    padding: 0;
    height: auto;
}
.muted {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.8;
}
.pill {
    padding: 3px 8px;
    border-radius: 12px;
    background: #f3f5f9;
    font-size: 11px;
    white-space: nowrap;
}
.pill.success {
    color: #00b578;
    background: #eaf8f0;
}
.pill.danger {
    color: #ed4014;
    background: #fff1ee;
}
.empty {
    text-align: center;
    color: var(--muted);
    height: 48px;
}
footer {
    margin-top: 15px;
}
.right {
    justify-content: flex-end;
}
.group-note {
    margin-right: auto;
}
.pager {
    gap: 4px;
}
.pager > span {
    margin-right: 5px;
}
.pager button {
    padding: 0;
    min-width: 28px;
}
.pager select {
    margin-left: 8px;
}
.pager .current {
    border-color: #2d8cf0;
    color: #2d8cf0;
}
.default-panel {
    max-width: 967px;
    border: 1px solid var(--line);
    border-radius: 6px;
    margin: 15px;
    padding: 15px;
}
.default-heading {
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid var(--line);
    padding-bottom: 14px;
    margin-bottom: 14px;
}
.default-heading h2 {
    font-weight: 600;
    color: #173452;
}
.default-heading h2:before {
    content: '';
    display: inline-block;
    width: 3px;
    height: 14px;
    border-radius: 2px;
    background: #2d8cf0;
    vertical-align: middle;
    margin-right: 7px;
}
.default-heading p {
    font-size: 11px;
    color: var(--muted);
    margin-top: 6px;
}
.error {
    padding: 10px;
    margin-bottom: 10px;
    color: #d9363e;
    background: #fff1f0;
    border-radius: 4px;
}
.spin {
    animation: stream-spin 1s linear infinite;
}
@keyframes stream-spin {
    to {
        transform: rotate(360deg);
    }
}
:global(.stream-resource-dialog .resource-form) {
    display: grid;
    gap: 18px;
    font-size: 13px;
}
:global(.stream-resource-dialog .resource-form label) {
    display: grid;
    gap: 7px;
}
:global(.stream-resource-dialog input:not([type='checkbox'])),
:global(.stream-resource-dialog select) {
    height: 32px;
    border: 1px solid #d7dce5;
    border-radius: 4px;
    padding: 0 8px;
    width: 100%;
    background: transparent;
}
:global(.stream-resource-dialog button) {
    border: 1px solid #d7dce5;
    border-radius: 4px;
    padding: 6px 14px;
}
:global(.stream-resource-dialog button.primary) {
    background: #2d8cf0;
    border-color: #2d8cf0;
    color: white;
}
:global(.stream-resource-dialog button:disabled) {
    opacity: 0.5;
}
:global(.stream-resource-dialog .modal-actions) {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
:global(.stream-resource-dialog .error) {
    color: #d9363e;
}
:global(.stream-resource-dialog .batch-field) {
    display: grid;
    grid-template-columns: 140px 1fr;
    align-items: center;
    gap: 12px;
}
:global(.stream-resource-dialog .batch-field label) {
    display: flex;
    align-items: center;
    gap: 6px;
}
:global(.dark .stream-workspace) {
    --line: #303a4a;
    --ink: #d8e1ef;
    --muted: #94a3b8;
    background: #151e2b;
}
:global(.dark .stream-workspace .stream-tabs .active) {
    background: #173756;
    color: #7db9ff;
}
:global(.dark .stream-workspace th),
:global(.dark .stream-workspace .advanced) {
    background: #1c2738;
}
:global(.dark .stream-workspace select) {
    background: #151e2b;
}
:global(.dark .stream-workspace .default-heading h2) {
    color: #e6eef9;
}
:global(.dark .stream-workspace .pill) {
    background: #263449;
}
:global(.dark .stream-workspace .pill.success) {
    background: #123d33;
    color: #45d7a5;
}
:global(.dark .stream-workspace .error),
:global(.dark .stream-workspace .pill.danger) {
    background: #45262c;
    color: #fda4af;
}
@media (max-width: 640px) {
    .toolbar,
    .search {
        width: 100%;
        gap: 10px;
    }
    .search-box {
        flex: 1;
        min-width: 250px;
    }
    .search-box input {
        flex: 1;
        width: 100px;
    }
    .default-heading {
        flex-wrap: wrap;
    }
    .default-panel {
        margin: 12px;
    }
    .pager {
        flex-wrap: wrap;
    }
    footer {
        flex-wrap: wrap;
    }
}
</style>
