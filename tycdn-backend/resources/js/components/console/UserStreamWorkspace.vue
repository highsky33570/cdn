<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import StreamBatchCreate from '@/components/console/StreamBatchCreate.vue';
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

const props = defineProps<{ packages: CdnflyRecord[] }>();
type Tab = 'streams' | 'groups' | 'defaults';
const batchCreate = ref<InstanceType<typeof StreamBatchCreate> | null>(null);
const emit = defineEmits<{ create: []; manage: [row: CdnflyRecord] }>();
const tabs = [
    { key: 'streams', label: '转发列表' },
    { key: 'groups', label: '分组管理' },
    { key: 'defaults', label: '默认设置' },
] as const;
const endpoints = {
    streams: '/api/cdn/proxy/v1/streams',
    groups: '/api/cdn/proxy/v1/stream-groups',
    defaults: '/api/cdn/proxy/v1/user-configs',
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
    group: '',
    user_package: '',
});
const searchOptions = {
    listen_port: '监听端口',
    backend_ip: '源IP',
    id: '转发ID',
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
onMounted(() => {
    void load();
    void loadGroups();
});
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

    if (tab.value === 'defaults') {
        query.set('type', 'stream');
    }

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
function manage(row: CdnflyRecord) {
    emit('manage', row);
}
const editorOpen = ref(false),
    editorError = ref(''),
    editing = ref<number | null>(null),
    editorTab = ref<'groups' | 'defaults'>('groups');
const form = reactive({
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
    groupsLoading.value = true;

    try {
        const data = await apiRequest(
            '/api/cdn/proxy/v1/stream-groups?limit=0',
        );

        if (token === groupRequest) {
            groups.value = extractCdnflyRows(data);
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
    if (!form.name.trim()) {
        editorError.value = '请填写名称';

        return;
    }

    const payload: CdnflyRecord = { name: form.name.trim() };

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
            editorError.value = '请选择转发分组';

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

        if (editorTab.value === 'groups') {
            void loadGroups();
        }

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
    if (!selected.value.length) {
        toast.warning('请选择需要修改的转发');

        return;
    }

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
    if (!ids.length) {
        toast.warning('请选择要删除的项目');

        return;
    }

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
    if (!ids.length || busy.value || loading.value) {
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

    if (tab.value === 'groups') {
        void loadGroups();
    }

    selected.value = failed;
    busy.value = false;

    if (failed.length) {
        error.value = `${failed.length} 项操作失败：${detail}`;

        return false;
    }

    toast.success('操作成功');

    return true;
}
const allSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selected.value.includes(Number(row.id))),
);
function packageText(row: CdnflyRecord) {
    const pack = props.packages.find(
        (item) => Number(item.id) === Number(row.user_package),
    );

    return String(
        row.package_name ||
            pack?.name ||
            pack?.package_name ||
            row.user_package ||
            '-',
    );
}
function groupText(row: CdnflyRecord) {
    if (row.group_name || row.group_names) {
        return String(row.group_name || row.group_names);
    }

    let ids = row.groups;

    if (typeof ids === 'string') {
        const text = ids;

        try {
            ids = JSON.parse(text);
        } catch {
            ids = text.split(',');
        }
    }

    return (
        (Array.isArray(ids) ? ids : ids ? [ids] : [])
            .map((id) =>
                String(
                    groups.value.find(
                        (group) => Number(group.id) === Number(id),
                    )?.name || id,
                ),
            )
            .join(', ') || '-'
    );
}
function createdAt(row: CdnflyRecord) {
    return String(row.create_at2 || row.created_at || row.create_at || '-')
        .replace('T', ' ')
        .slice(0, 19);
}
function tabKey(event: KeyboardEvent) {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
        return;
    }

    event.preventDefault();
    const index = tabs.findIndex((item) => item.key === tab.value);
    selectTab(
        tabs[
            event.key === 'Home'
                ? 0
                : event.key === 'End'
                  ? 2
                  : (index + (event.key === 'ArrowLeft' ? 2 : 1)) % 3
        ].key,
    );
    document.getElementById(`user-stream-tab-${tab.value}`)?.focus();
}
</script>

<template>
    <section class="console-user-stream-workspace user-stream-workspace">
        <nav
            class="stream-tabs"
            role="tablist"
            aria-label="四层转发"
            @keydown="tabKey"
        >
            <Button
                variant="ghost"
                data-slot="console-tab"
                v-for="item in tabs"
                :id="`user-stream-tab-${item.key}`"
                :key="item.key"
                type="button"
                role="tab"
                :aria-selected="tab === item.key"
                aria-controls="user-stream-panel"
                :tabindex="tab === item.key ? 0 : -1"
                :disabled="busy"
                @click="selectTab(item.key)"
            >
                {{ item.label }}
            </Button>
        </nav>
        <div
            id="user-stream-panel"
            role="tabpanel"
            :aria-labelledby="`user-stream-tab-${tab}`"
            :aria-busy="loading || busy"
        >
            <div v-if="tab === 'streams'" class="stream-toolbar">
                <Button
                    variant="default"
                    data-slot="console-action"
                    class="stream-button primary"
                    type="button"
                    :disabled="busy || loading"
                    @click="emit('create')"
                >
                    添加转发
                </Button>
                <Button
                    variant="outline"
                    data-slot="console-action"
                    class="stream-button"
                    type="button"
                    :disabled="busy || loading"
                    @click="openBatch"
                >
                    批量修改
                </Button>
                <DropdownMenu
                    ><DropdownMenuTrigger as-child
                        ><Button
                            variant="outline"
                            data-slot="console-action"
                            class="stream-button"
                            type="button"
                            :disabled="busy || loading"
                        >
                            更多操作
                            <ChevronDown
                                :size="16" /></Button></DropdownMenuTrigger
                    ><DropdownMenuContent
                        class="console-user-stream-workspace"
                        align="start"
                        ><DropdownMenuItem
                            :disabled="!selected.length"
                            @select="
                                mutate([...selected], 'PUT', { enable: 1 })
                            "
                            >启用</DropdownMenuItem
                        ><DropdownMenuItem
                            :disabled="!selected.length"
                            @select="
                                mutate([...selected], 'PUT', { enable: 0 })
                            "
                            >禁用</DropdownMenuItem
                        ><DropdownMenuItem @select="confirmDelete()"
                            >删除</DropdownMenuItem
                        ><DropdownMenuItem @select="batchCreate?.show()"
                            >批量添加</DropdownMenuItem
                        ></DropdownMenuContent
                    ></DropdownMenu
                >
                <form
                    data-slot="console-input-group"
                    class="stream-search"
                    @submit.prevent="load(1)"
                >
                    <SelectField
                        v-model="searchType"
                        aria-label="搜索类型"
                        :disabled="busy"
                    >
                        <SelectOption
                            v-for="(label, key) in searchOptions"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </SelectOption></SelectField
                    ><Input
                        v-model="search"
                        aria-label="转发搜索"
                        :placeholder="`输入${searchOptions[searchType as keyof typeof searchOptions]}`"
                        :disabled="busy"
                    /><Button
                        variant="default"
                        data-slot="console-action"
                        class="stream-button primary"
                        type="submit"
                        :disabled="busy || loading"
                    >
                        查询
                    </Button>
                </form>
                <Button
                    variant="link"
                    size="inline"
                    data-slot="console-link"
                    type="button"
                    class="text-action"
                    :aria-expanded="advanced"
                    aria-controls="stream-advanced"
                    @click="advanced = !advanced"
                >
                    高级搜索
                </Button>
            </div>
            <div v-else class="stream-toolbar">
                <Button
                    variant="default"
                    data-slot="console-action"
                    type="button"
                    class="stream-button primary"
                    :disabled="busy || loading"
                    @click="editResource()"
                >
                    {{ tab === 'groups' ? '新增分组' : '新增设置' }}</Button
                ><Button
                    variant="outline"
                    data-slot="console-action"
                    type="button"
                    class="stream-button"
                    :disabled="busy || loading"
                    @click="confirmDelete()"
                >
                    删除
                </Button>
            </div>
            <form
                v-if="tab === 'streams' && advanced"
                id="stream-advanced"
                class="advanced-filters"
                @submit.prevent="load(1)"
            >
                <label
                    >监听协议<SelectField
                        v-model="filters.listen_protocol"
                        aria-label="监听协议"
                        :disabled="busy"
                    >
                        <SelectOption value="">所有协议</SelectOption>
                        <SelectOption value="tcp">TCP</SelectOption>
                        <SelectOption value="udp">UDP</SelectOption>
                    </SelectField></label
                >
                <label
                    >状态<SelectField
                        v-model="filters.enable"
                        aria-label="状态"
                        :disabled="busy"
                    >
                        <SelectOption value="">所有状态</SelectOption>
                        <SelectOption value="1">启用</SelectOption>
                        <SelectOption value="0">禁用</SelectOption>
                    </SelectField></label
                >
                <label
                    >任务状态<SelectField
                        v-model="filters.state"
                        aria-label="任务状态"
                        :disabled="busy"
                    >
                        <SelectOption value="">所有状态</SelectOption>
                        <SelectOption value="done">已完成</SelectOption>
                        <SelectOption value="pending">待执行</SelectOption>
                        <SelectOption value="process">执行中</SelectOption>
                        <SelectOption value="failed">失败</SelectOption>
                    </SelectField></label
                >
                <label
                    >同步状态<SelectField
                        v-model="filters.sync_state"
                        aria-label="同步状态"
                        :disabled="busy"
                    >
                        <SelectOption value="">所有状态</SelectOption>
                        <SelectOption value="done">已同步</SelectOption>
                        <SelectOption value="pending">待同步</SelectOption>
                        <SelectOption value="process">同步中</SelectOption>
                        <SelectOption value="failed">同步失败</SelectOption>
                    </SelectField></label
                >
                <label
                    >分组<SelectField
                        v-model="filters.group"
                        aria-label="分组"
                        :disabled="busy"
                    >
                        <SelectOption value="">所有分组</SelectOption>
                        <SelectOption
                            v-for="group in groups"
                            :key="String(group.id)"
                            :value="String(group.id)"
                        >
                            {{ group.name }}
                        </SelectOption>
                    </SelectField></label
                >
                <label
                    >套餐<SelectField
                        v-model="filters.user_package"
                        aria-label="套餐"
                        :disabled="busy"
                    >
                        <SelectOption value="">所有套餐</SelectOption>
                        <SelectOption
                            v-for="pack in packages"
                            :key="String(pack.id)"
                            :value="String(pack.id)"
                        >
                            {{ pack.name || pack.package_name || pack.id }}
                        </SelectOption>
                    </SelectField></label
                >
                <Button
                    variant="default"
                    data-slot="console-action"
                    type="submit"
                    class="stream-button primary"
                    :disabled="busy || loading"
                >
                    查询</Button
                ><Button
                    variant="link"
                    size="inline"
                    data-slot="console-link"
                    type="button"
                    class="text-action"
                    :disabled="busy"
                    @click="clear"
                >
                    清除
                </Button>
            </form>
            <p data-typography="body" v-if="error" class="error" role="alert">
                {{ error }}
                <Button
                    variant="link"
                    size="inline"
                    data-slot="console-link"
                    class="text-action"
                    type="button"
                    :disabled="busy || loading"
                    @click="load()"
                >
                    重试
                </Button>
            </p>
            <div class="stream-table-scroll">
                <table
                    class="stream-table"
                    :class="{ 'forwarding-table': tab === 'streams' }"
                >
                    <colgroup v-if="tab === 'streams'">
                        <col style="width: 60px" />
                        <col style="width: 90px" />
                        <col style="width: 140px" />
                        <col style="width: 170px" />
                        <col style="width: 210px" />
                        <col style="width: 170px" />
                        <col style="width: 120px" />
                        <col style="width: 150px" />
                        <col style="width: 190px" />
                        <col style="width: 210px" />
                        <col style="width: 170px" />
                    </colgroup>
                    <colgroup v-else>
                        <col style="width: 7%" />
                        <col style="width: 12%" />
                        <col style="width: 25%" />
                        <col style="width: 25%" />
                        <col v-if="tab === 'defaults'" style="width: 18%" />
                        <col style="width: 15%" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th>
                                <CheckboxField
                                    aria-label="全选当前页"
                                    :checked="allSelected"
                                    :indeterminate="
                                        selected.length > 0 && !allSelected
                                    "
                                    :disabled="busy || loading || !rows.length"
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
                                <th>源站</th>
                                <th>CNAME</th>
                                <th>套餐</th>
                                <th>分组</th>
                                <th>状态</th>
                                <th>备注</th>
                                <th>添加时间</th></template
                            ><template v-else-if="tab === 'groups'"
                                ><th>名称</th>
                                <th>备注</th></template
                            ><template v-else
                                ><th>设置项</th>
                                <th>设置值</th>
                                <th>生效范围</th></template
                            >
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td
                                class="empty"
                                :colspan="
                                    tab === 'streams'
                                        ? 11
                                        : tab === 'groups'
                                          ? 5
                                          : 6
                                "
                            >
                                加载中…
                            </td>
                        </tr>
                        <template v-else>
                            <tr v-for="row in rows" :key="Number(row.id)">
                                <td>
                                    <CheckboxField
                                        v-model="selected"
                                        :value="Number(row.id)"
                                        :aria-label="`选择 ${row.id}`"
                                        :disabled="busy"
                                    />
                                </td>
                                <td>{{ row.id }}</td>
                                <template v-if="tab === 'streams'"
                                    ><td>{{ streamListenText(row.listen) }}</td>
                                    <td
                                        :title="streamBackendText(row)"
                                        class="truncate-cell"
                                    >
                                        {{ streamBackendText(row) }}
                                    </td>
                                    <td
                                        class="truncate-cell"
                                        :title="String(cname(row))"
                                    >
                                        {{ cname(row) }}
                                    </td>
                                    <td>{{ packageText(row) }}</td>
                                    <td>{{ groupText(row) }}</td>
                                    <td>
                                        <span
                                            class="stream-status"
                                            :data-state="status(row)"
                                            ><i />{{ status(row) }}</span
                                        >
                                    </td>
                                    <td
                                        class="truncate-cell"
                                        :title="String(row.des || '')"
                                    >
                                        {{ row.des || '-' }}
                                    </td>
                                    <td>{{ createdAt(row) }}</td></template
                                >
                                <template v-else-if="tab === 'groups'"
                                    ><td>{{ row.name || '-' }}</td>
                                    <td>{{ row.des || '-' }}</td></template
                                >
                                <template v-else
                                    ><td>
                                        {{
                                            names[String(row.name)] || row.name
                                        }}
                                    </td>
                                    <td>{{ configValue(row) }}</td>
                                    <td>
                                        {{
                                            row.scope_name === 'global'
                                                ? '全局'
                                                : `转发分组 #${row.scope_id}`
                                        }}
                                    </td></template
                                >
                                <td>
                                    <div class="row-actions">
                                        <Button
                                            variant="link"
                                            size="inline"
                                            data-slot="console-link"
                                            type="button"
                                            class="text-action"
                                            :disabled="busy"
                                            @click="
                                                tab === 'streams'
                                                    ? manage(row)
                                                    : editResource(row)
                                            "
                                        >
                                            {{
                                                tab === 'streams'
                                                    ? '管理'
                                                    : '编辑'
                                            }}</Button
                                        ><DropdownMenu v-if="tab === 'streams'"
                                            ><DropdownMenuTrigger as-child
                                                ><Button
                                                    variant="link"
                                                    size="inline"
                                                    data-slot="console-link"
                                                    type="button"
                                                    class="text-action row-more"
                                                    :disabled="busy"
                                                    :aria-label="`更多操作 ${row.id}`"
                                                >
                                                    更多
                                                    <ChevronDown
                                                        :size="
                                                            14
                                                        " /></Button></DropdownMenuTrigger
                                            ><DropdownMenuContent
                                                class="console-user-stream-workspace"
                                                align="end"
                                                ><DropdownMenuItem
                                                    @select="
                                                        mutate(
                                                            [Number(row.id)],
                                                            'PUT',
                                                            { enable: 1 },
                                                        )
                                                    "
                                                    >启用</DropdownMenuItem
                                                ><DropdownMenuItem
                                                    @select="
                                                        mutate(
                                                            [Number(row.id)],
                                                            'PUT',
                                                            { enable: 0 },
                                                        )
                                                    "
                                                    >禁用</DropdownMenuItem
                                                ><DropdownMenuItem
                                                    @select="
                                                        confirmDelete([
                                                            Number(row.id),
                                                        ])
                                                    "
                                                    >删除</DropdownMenuItem
                                                ></DropdownMenuContent
                                            ></DropdownMenu
                                        ><Button
                                            variant="link"
                                            size="inline"
                                            data-slot="console-link"
                                            v-else
                                            type="button"
                                            class="text-action"
                                            :disabled="busy"
                                            @click="
                                                confirmDelete([Number(row.id)])
                                            "
                                        >
                                            删除
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td
                                    class="empty"
                                    :colspan="
                                        tab === 'streams'
                                            ? 11
                                            : tab === 'groups'
                                              ? 5
                                              : 6
                                    "
                                >
                                    {{
                                        error ? '加载失败，请重试' : '暂无数据'
                                    }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <PackagePagination
                v-model:page="page"
                v-model:page-size="size"
                class="stream-pagination"
                :total="total"
                :disabled="busy || loading"
                numbered
                edge-links
                @update:page="load($event)"
                @update:page-size="load(1)"
            />
        </div>

        <StreamBatchCreate
            ref="batchCreate"
            scope="user"
            hide-trigger
            @updated="load()"
        />
        <Dialog v-model:open="editorOpen"
            ><DialogScrollContent
                class="console-user-stream-workspace stream-resource-dialog sm:max-w-lg"
                ><DialogHeader
                    ><DialogTitle
                        >{{ editing ? '编辑' : '新增'
                        }}{{
                            editorTab === 'groups' ? '分组' : '设置'
                        }}</DialogTitle
                    ><DialogDescription>{{
                        editorTab === 'groups'
                            ? '管理四层转发分组。'
                            : '设置新增四层转发时使用的默认配置。'
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
                    <template v-if="editorTab === 'groups'"
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
                class="console-user-stream-workspace stream-resource-dialog sm:max-w-lg"
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
