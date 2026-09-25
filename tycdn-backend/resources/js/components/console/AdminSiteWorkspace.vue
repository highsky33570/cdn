<script setup lang="ts">
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Copy,
    Download,
    Filter,
    MoreHorizontal,
    Plus,
    RefreshCw,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import CheckboxField from '@/components/ui/checkbox/CheckboxField.vue';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import {
    applyAdminSiteCertificate,
    deleteAdminSite,
    listAdminSites,
    setAdminSiteEnabled,
    updateAdminSite,
} from '@/lib/adminModulesApi';
import {
    AUTH_TEMPLATES,
    DNS_TYPES,
    SITE_CONFIG_NAMES,
} from '@/lib/adminSiteOptions';
import {
    csvCell,
    siteCname,
    siteObject,
    siteOrigins,
    sitePorts,
    siteResource,
    siteStatus,
    siteWorkspaceTabs,
} from '@/lib/adminSiteWorkspace';
import type { SiteWorkspaceTab } from '@/lib/adminSiteWorkspace';
import {
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnflyResponse';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import ConfirmDeleteDialog from './ConfirmDeleteDialog.vue';

const emit = defineEmits<{
    create: [];
    manage: [row: CdnflyRecord];
    details: [row: CdnflyRecord];
    edit: [row: CdnflyRecord];
    certificate: [row: CdnflyRecord];
    toggle: [row: CdnflyRecord];
    delete: [row: CdnflyRecord];
}>();
const props = withDefaults(
    defineProps<{ initialTab?: SiteWorkspaceTab; embedded?: boolean }>(),
    { initialTab: 'sites', embedded: false },
);
const tab = ref<SiteWorkspaceTab>(props.initialTab),
    rows = ref<CdnflyRecord[]>([]),
    page = ref(1),
    size = ref(10),
    total = ref(0),
    loading = ref(false),
    busy = ref(false),
    error = ref('');
const selected = ref<number[]>([]),
    showFilters = ref(false),
    searchKey = ref('domain'),
    search = ref('');
const filters = reactive({
    uid: '',
    enable: '',
    https_enable: '',
    group: '',
    region: '',
    sync_state: '',
});
const applied = ref<Record<string, string>>({});
const resolveFilters = reactive({
    dnsapi_state: '',
    task_state: '',
    domain: '',
    site_id: '',
});
const summary = ref('all'),
    checks = ref<Record<string, string>>({});
const resource = computed(
    () =>
        ({
            sites: '',
            groups: 'site-groups',
            defaults: 'user-configs',
            dnsapi: 'dnsapis',
            resolve: 'domains',
        })[tab.value],
);
const pages = computed(() => Math.max(1, Math.ceil(total.value / size.value)));
const taskText = (row: CdnflyRecord) =>
    ({
        done: '已完成',
        pending: '待同步',
        process: '同步中',
        failed: '同步失败',
    })[String(row.state)] ?? (row.state ? String(row.state) : '无任务');
const checkText = (row: CdnflyRecord) =>
    ({
        resolved: '正常',
        unresolved: '解析失败',
        error: '检测失败',
        checking: '检测中',
    })[checks.value[String(row.id)]] ?? '检测中';
const visibleRows = computed(() =>
    tab.value !== 'resolve'
        ? rows.value
        : rows.value.filter(
              (row) =>
                  summary.value === 'all' ||
                  (summary.value === 'failed' &&
                      checks.value[String(row.id)] === 'unresolved') ||
                  (summary.value === 'missing' && !row.dns_api) ||
                  (summary.value === 'checking' &&
                      checks.value[String(row.id)] === 'checking'),
          ),
);
const allSelected = computed(
    () =>
        visibleRows.value.length > 0 &&
        visibleRows.value.every((row) =>
            selected.value.includes(Number(row.id)),
        ),
);
const counts = computed(() => ({
    all: rows.value.length,
    failed: rows.value.filter(
        (r) => checks.value[String(r.id)] === 'unresolved',
    ).length,
    missing: rows.value.filter((r) => !r.dns_api).length,
    checking: rows.value.filter(
        (r) => checks.value[String(r.id)] === 'checking',
    ).length,
}));
let version = 0;
onUnmounted(() => version++);
onMounted(() => void load());
function chooseTab(next: SiteWorkspaceTab) {
    tab.value = next;
    page.value = 1;
    selected.value = [];
    rows.value = [];
    error.value = '';
    void load();
}
function toggle(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((n) => n !== id)
        : [...selected.value, id];
}
function toggleAll() {
    selected.value = allSelected.value
        ? []
        : visibleRows.value.map((r) => Number(r.id));
}
function query() {
    const result: Record<string, string | number> = {
        page: page.value,
        limit: size.value,
    };

    if (tab.value === 'sites') {
        Object.assign(result, applied.value);
    }

    if (tab.value === 'defaults') {
        result.type = 'site';
    }

    if (tab.value === 'resolve') {
        for (const [key, value] of Object.entries(resolveFilters)) {
            if (value.trim()) {
                result[key] = value.trim();
            }
        }
    }

    return result;
}
async function load(target = page.value) {
    const request = ++version,
        active = tab.value;
    page.value = target;
    loading.value = true;
    error.value = '';
    selected.value = [];

    try {
        const data =
            active === 'sites'
                ? await listAdminSites(query())
                : await siteResource(resource.value, 'GET', query());

        if (request !== version) {
            return;
        }

        rows.value = extractCdnflyRows(data);
        total.value = extractCdnflyTotal(data, rows.value.length);

        if (page.value > 1 && !rows.value.length) {
            void load(Math.max(1, Math.ceil(total.value / size.value)));

            return;
        }

        if (active === 'resolve') {
            checks.value = Object.fromEntries(
                rows.value.map((r) => [String(r.id), 'checking']),
            );
            void checkDomains(rows.value, request);
        }
    } catch (e) {
        if (request === version) {
            rows.value = [];
            total.value = 0;
            error.value = message(e);
        }
    } finally {
        if (request === version) {
            loading.value = false;
        }
    }
}
function message(e: unknown) {
    return e instanceof Error ? e.message : '操作失败，请重试';
}
function searchSites() {
    applied.value = Object.fromEntries(
        Object.entries({
            ...filters,
            [searchKey.value]: search.value.trim(),
        }).filter(([, value]) => value !== ''),
    );
    void load(1);
}
function clearFilters() {
    Object.assign(filters, {
        uid: '',
        enable: '',
        https_enable: '',
        group: '',
        region: '',
        sync_state: '',
    });
    search.value = '';
    searchSites();
}
function clearResolve() {
    Object.assign(resolveFilters, {
        dnsapi_state: '',
        task_state: '',
        domain: '',
        site_id: '',
    });
    summary.value = 'all';
    void load(1);
}
async function checkDomains(data: CdnflyRecord[], request: number) {
    const payload = Object.fromEntries(
        data
            .filter((r) => r.domain && r.cname)
            .map((r) => [
                `domain_${r.id}`,
                { domain: r.domain, cname: r.cname },
            ]),
    );

    for (const row of data) {
        if (!row.domain || !row.cname) {
            checks.value[String(row.id)] = 'error';
        }
    }

    if (!Object.keys(payload).length) {
        return;
    }

    try {
        const result =
            extractCdnflyRecord(
                await siteResource('cname-check', 'POST', payload),
            ) ?? {};

        if (request !== version) {
            return;
        }

        for (const row of data) {
            const value = result[`domain_${row.id}`];
            checks.value[String(row.id)] =
                value === undefined
                    ? 'error'
                    : [true, 1, '1'].includes(
                            value as string | number | boolean,
                        )
                      ? 'resolved'
                      : 'unresolved';
        }
    } catch (e) {
        if (request === version) {
            for (const row of data) {
                checks.value[String(row.id)] = 'error';
            }

            error.value = `解析检测失败：${message(e)}`;
        }
    }
}
async function syncDomains() {
    busy.value = true;
    error.value = '';

    try {
        await siteResource(
            'domains',
            'POST',
            selected.value.map((id) => ({ id })),
        );
        toast.success('同步任务已提交');
        await load();
    } catch (e) {
        error.value = message(e);
    } finally {
        busy.value = false;
    }
}
async function copy(value: unknown) {
    try {
        await navigator.clipboard.writeText(String(value ?? ''));
        toast.success('已复制');
    } catch {
        toast.error('复制失败');
    }
}
const username = (row: CdnflyRecord) =>
    `${row.username ?? row.user_name ?? (tab.value === 'sites' ? row.name : '') ?? ''}${row.uid ? ` (${row.uid})` : ''}`.trim() ||
    '—';
const configLabel = (row: CdnflyRecord) =>
    SITE_CONFIG_NAMES.find((item) => item.value === row.name)?.label ??
    String(row.name ?? '—');
const configValue = (row: CdnflyRecord) =>
    SITE_CONFIG_NAMES.find((item) => item.value === row.name)?.valueType ===
    'boolean'
        ? String(row.value) === '1'
            ? '是'
            : '否'
        : String(row.value ?? '');
const originRegion = (row: CdnflyRecord) =>
    `${row.region_name ?? '—'}${row.node_group_name ? ` (${[row.node_group_name, row.backup_node_group_name].filter(Boolean).join(' / ')})` : ''}`;
async function exportSites() {
    busy.value = true;
    error.value = '';

    try {
        const data = extractCdnflyRows(
            await listAdminSites({ ...applied.value, limit: 0 }),
        );
        const csv = [
            [
                'ID',
                '域名',
                '用户',
                'CNAME',
                'HTTPS',
                '源站',
                '监听',
                '套餐',
                '分组',
                '区域',
                '状态',
                '添加时间',
            ],
            ...data.map((r) => [
                r.id,
                r.domain,
                username(r),
                siteCname(r),
                siteObject(r.https_listen).port ? '已开启' : '未开启',
                siteOrigins(r),
                sitePorts(r).join(' '),
                r.package_name,
                r.group_name,
                originRegion(r),
                siteStatus(r).text,
                r.create_at2 ?? r.create_at,
            ]),
        ]
            .map((r) => r.map(csvCell).join(','))
            .join('\r\n');
        const url = URL.createObjectURL(
            new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' }),
        );
        const a = document.createElement('a');
        a.href = url;
        a.download = 'sites.csv';
        a.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
        error.value = message(e);
    } finally {
        busy.value = false;
    }
}

const dialog = ref(false),
    editing = ref<CdnflyRecord | null>(null),
    formError = ref(''),
    saving = ref(false),
    replaceAuth = ref(false);
const form = reactive({
    uid: '',
    name: '',
    des: '',
    type: 'CloudFlare',
    value: '',
    scope_name: 'global',
    scope_id: '0',
    enable: '1',
});
const auth = ref<Record<string, string>>({});
const editorTab = ref<SiteWorkspaceTab>('groups');
const editorTitle = computed(
    () =>
        `${editing.value ? '编辑' : '新增'}${editorTab.value === 'groups' ? '分组' : editorTab.value === 'defaults' ? '设置' : ' DNS API'}`,
);
const configMeta = computed(() =>
    SITE_CONFIG_NAMES.find((item) => item.value === form.name),
);
function authTemplate() {
    auth.value = { ...AUTH_TEMPLATES[form.type] };
}
function openEditor(row: CdnflyRecord | null = null) {
    editing.value = row;
    editorTab.value = tab.value;
    formError.value = '';
    replaceAuth.value = false;
    Object.assign(form, {
        uid: String(row?.uid ?? ''),
        name: String(row?.name ?? ''),
        des: String(row?.des ?? ''),
        type: String(row?.type ?? 'CloudFlare'),
        value: String(row?.value ?? ''),
        scope_name: String(row?.scope_name ?? 'global'),
        scope_id: String(row?.scope_id ?? 0),
        enable: String(row?.enable ?? 1),
    });
    authTemplate();
    dialog.value = true;
}
async function saveEditor() {
    formError.value = '';

    if (!form.name.trim() || (!editing.value && !(Number(form.uid) > 0))) {
        formError.value = '请填写用户 ID 和名称/设置项';

        return;
    }

    const payload: CdnflyRecord = {
        name: form.name.trim(),
        ...(!editing.value ? { uid: Number(form.uid) } : {}),
    };

    if (editorTab.value === 'defaults') {
        if (form.scope_name === 'group' && !(Number(form.scope_id) > 0)) {
            formError.value = '请输入网站分组 ID';

            return;
        }

        Object.assign(payload, {
            type: 'site',
            value: form.value,
            scope_name: form.scope_name,
            scope_id: form.scope_name === 'global' ? 0 : Number(form.scope_id),
            enable: Number(form.enable),
        });
    } else {
        payload.des = form.des;
    }

    if (editorTab.value === 'dnsapi') {
        payload.type = form.type;

        if (!editing.value || replaceAuth.value) {
            if (
                !Object.keys(auth.value).length ||
                Object.values(auth.value).some((value) => !value.trim())
            ) {
                formError.value = '请填写 DNS API 凭据';

                return;
            }

            payload.auth = { ...auth.value };
        }
    }

    saving.value = true;

    try {
        await siteResource(
            resource.value,
            editing.value ? 'PUT' : 'POST',
            payload,
            editing.value ? Number(editing.value.id) : undefined,
        );
        dialog.value = false;
        toast.success('保存成功');
        await load();
    } catch (e) {
        formError.value = message(e);
    } finally {
        saving.value = false;
    }
}
const deleteOpen = ref(false),
    deleteIds = ref<number[]>([]),
    deleteError = ref('');
function confirmDelete(ids = selected.value) {
    deleteIds.value = [...ids];
    deleteError.value = '';
    deleteOpen.value = true;
}
async function removeSelected() {
    busy.value = true;
    deleteError.value = '';
    const failures: number[] = [];

    for (const id of deleteIds.value) {
        try {
            if (tab.value === 'sites') {
                await deleteAdminSite(id);
            } else {
                await siteResource(resource.value, 'DELETE', {}, id);
            }
        } catch (e) {
            failures.push(id);
            deleteError.value = message(e);
        }
    }

    await load();
    selected.value = failures;
    deleteIds.value = failures;
    deleteOpen.value = failures.length > 0;
    busy.value = false;
}
async function bulk(
    action: 'enable' | 'disable' | 'certificate',
    ids = [...selected.value],
) {
    busy.value = true;
    error.value = '';
    const failures: number[] = [];

    for (const id of ids) {
        try {
            if (tab.value === 'defaults') {
                await siteResource(
                    'user-configs',
                    'PUT',
                    { type: 'site', enable: action === 'enable' ? 1 : 0 },
                    id,
                );
            } else if (action === 'certificate') {
                await applyAdminSiteCertificate(id);
            } else {
                await setAdminSiteEnabled(id, action === 'enable');
            }
        } catch (e) {
            failures.push(id);
            error.value = message(e);
        }
    }

    const failedMessage = error.value;
    await load();
    selected.value = failures;

    if (failures.length) {
        error.value = `${failures.length} 项失败：${failedMessage}`;
    } else {
        toast.success('操作成功');
    }

    busy.value = false;
}
const batchOpen = ref(false),
    batchField = ref('groups'),
    batchValue = ref(''),
    batchError = ref('');
function openBatch() {
    batchField.value = 'groups';
    batchValue.value = '';
    batchError.value = '';
    batchOpen.value = true;
}
async function saveBatch() {
    saving.value = true;
    batchError.value = '';
    const failures: number[] = [];

    for (const id of selected.value) {
        try {
            await updateAdminSite(id, {
                [batchField.value]: [
                    'gzip_enable',
                    'websocket_enable',
                ].includes(batchField.value)
                    ? Number(batchValue.value)
                    : batchValue.value,
            });
        } catch (e) {
            failures.push(id);
            batchError.value = message(e);
        }
    }

    await load();
    selected.value = failures;

    if (!failures.length) {
        batchOpen.value = false;
    }

    saving.value = false;
}
defineExpose({ refresh: load });
</script>

<template>
    <section
        class="site-workspace"
        :class="props.embedded ? '' : 'rounded-xl border bg-card p-4 shadow-sm'"
    >
        <nav
            v-if="!props.embedded"
            role="tablist"
            aria-label="网站管理"
            class="site-tabs"
        >
            <button
                v-for="item in siteWorkspaceTabs"
                :key="item.key"
                role="tab"
                :aria-selected="tab === item.key"
                :disabled="busy"
                @click="chooseTab(item.key)"
            >
                {{ item.label }}
            </button>
        </nav>
        <div
            v-if="error"
            role="alert"
            class="mb-3 rounded border border-destructive/30 p-3 text-destructive"
        >
            {{ error }} <button class="link" @click="load()">重试</button>
        </div>
        <div
            :class="{ 'defaults-panel': tab === 'defaults' }"
            role="tabpanel"
            :aria-busy="loading"
        >
            <header v-if="tab === 'defaults'" class="default-heading">
                <h3>站点默认设置</h3>
                <p>
                    按用户、全局或网站分组维护站点默认配置，新增和批量操作会影响后续匹配范围内的站点设置。
                </p>
            </header>
            <div class="site-toolbar">
                <template v-if="tab === 'sites'">
                    <button class="primary" @click="emit('create')">
                        添加网站
                    </button>
                    <button
                        :disabled="!selected.length || busy"
                        @click="openBatch"
                    >
                        批量修改
                    </button>
                    <button
                        :disabled="!selected.length || busy"
                        @click="bulk('certificate')"
                    >
                        申请证书
                    </button>
                    <details class="menu">
                        <summary>更多操作 <ChevronDown /></summary>
                        <div class="menu-content">
                            <button
                                :disabled="!selected.length || busy"
                                @click="bulk('enable')"
                            >
                                启用网站</button
                            ><button
                                :disabled="!selected.length || busy"
                                @click="bulk('disable')"
                            >
                                禁用网站</button
                            ><button
                                :disabled="!selected.length || busy"
                                @click="confirmDelete()"
                            >
                                删除网站</button
                            ><button @click="load()">刷新</button>
                        </div>
                    </details>
                    <form class="search-box" @submit.prevent="searchSites">
                        <SelectField v-model="searchKey" aria-label="搜索类型">
                            <SelectOption value="domain">域名</SelectOption>
                            <SelectOption value="id">ID</SelectOption>
                            <SelectOption value="uid">用户ID</SelectOption>
                            <SelectOption value="backend_ip"
                                >源站IP</SelectOption
                            >
                            <SelectOption value="cname_hostname">
                                CNAME
                            </SelectOption></SelectField
                        ><Input
                            v-model="search"
                            aria-label="搜索网站"
                            placeholder="输入域名,模糊搜索"
                        /><button class="primary">查询</button>
                    </form>
                    <button
                        :aria-expanded="showFilters"
                        @click="showFilters = !showFilters"
                    >
                        <Filter />筛选</button
                    ><button :disabled="busy" @click="exportSites">
                        <Download />导出
                    </button>
                </template>
                <template v-else-if="tab === 'groups'"
                    ><button class="primary" @click="openEditor()">
                        <Plus />新增分组</button
                    ><button @click="load()"><RefreshCw />刷新</button
                    ><button
                        class="danger-outline"
                        :disabled="!selected.length || busy"
                        @click="confirmDelete()"
                    >
                        <Trash2 />删除</button
                    ><span class="muted ml-auto"
                        >● 共 {{ total }} 个分组</span
                    ></template
                >
                <template v-else-if="tab === 'defaults'"
                    ><button class="primary" @click="openEditor()">
                        <Plus />新增设置</button
                    ><button
                        class="danger-outline"
                        :disabled="!selected.length || busy"
                        @click="confirmDelete()"
                    >
                        <Trash2 />删除</button
                    ><button
                        :disabled="!selected.length || busy"
                        @click="bulk('enable')"
                    >
                        ◉ 启用</button
                    ><button
                        :disabled="!selected.length || busy"
                        @click="bulk('disable')"
                    >
                        ⊗ 禁用
                    </button></template
                >
                <template v-else-if="tab === 'dnsapi'"
                    ><button class="primary" @click="openEditor()">
                        新增 DNS API</button
                    ><button
                        :disabled="!selected.length || busy"
                        @click="confirmDelete()"
                    >
                        删除选中</button
                    ><span class="muted ml-auto"
                        >共 {{ total }} 条</span
                    ></template
                >
                <template v-else>
                    <button
                        class="primary"
                        :disabled="!selected.length || busy"
                        @click="syncDomains"
                    >
                        同步解析</button
                    ><span class="muted">{{
                        selected.length
                            ? `已选 ${selected.length} 个域名`
                            : '请选择要同步的域名'
                    }}</span>
                    <form class="resolve-filters" @submit.prevent="load(1)">
                        <SelectField
                            v-model="resolveFilters.dnsapi_state"
                            aria-label="DNS API状态"
                            @change="load(1)"
                        >
                            <SelectOption value="">全部</SelectOption>
                            <SelectOption value="set"
                                >已配置 DNS API</SelectOption
                            >
                            <SelectOption value="not_set">
                                未配置 DNS API
                            </SelectOption></SelectField
                        ><SelectField
                            v-model="resolveFilters.task_state"
                            aria-label="任务状态"
                            @change="load(1)"
                        >
                            <SelectOption value="">全部</SelectOption>
                            <SelectOption value="done">已完成</SelectOption>
                            <SelectOption value="pending">待同步</SelectOption>
                            <SelectOption value="process">同步中</SelectOption>
                            <SelectOption value="failed"
                                >同步失败</SelectOption
                            ></SelectField
                        ><label
                            >域名
                            <Input
                                v-model="resolveFilters.domain"
                                placeholder="域名关键字"
                                @change="load(1)" /></label
                        ><label
                            >网站ID
                            <Input
                                v-model="resolveFilters.site_id"
                                placeholder="网站ID"
                                @change="load(1)" /></label
                        ><button
                            type="button"
                            class="link"
                            @click="clearResolve"
                        >
                            清除
                        </button>
                    </form>
                </template>
            </div>
            <form
                v-if="tab === 'sites' && showFilters"
                class="filter-panel"
                @submit.prevent="searchSites"
            >
                <label>用户ID<Input v-model="filters.uid" /></label
                ><label>分组ID<Input v-model="filters.group" /></label
                ><label>区域ID<Input v-model="filters.region" /></label>
                <label
                    >状态<SelectField v-model="filters.enable">
                        <SelectOption value="">全部状态</SelectOption>
                        <SelectOption value="1">正常</SelectOption>
                        <SelectOption value="0">已禁用</SelectOption>
                    </SelectField></label
                ><label
                    >HTTPS<SelectField v-model="filters.https_enable">
                        <SelectOption value="">全部</SelectOption>
                        <SelectOption value="1">已开启</SelectOption>
                        <SelectOption value="0">未开启</SelectOption>
                    </SelectField></label
                ><label
                    >同步<SelectField v-model="filters.sync_state">
                        <SelectOption value="">全部</SelectOption>
                        <SelectOption value="done">已同步</SelectOption>
                        <SelectOption value="process">同步中</SelectOption>
                        <SelectOption value="failed">同步失败</SelectOption>
                    </SelectField></label
                ><button class="primary">查询</button
                ><button type="button" @click="clearFilters">清除</button>
            </form>
            <div v-if="tab === 'resolve'" class="resolve-summary">
                <button
                    :class="{ active: summary === 'all' }"
                    @click="summary = 'all'"
                >
                    全部 {{ counts.all }}</button
                ><button
                    :class="{ active: summary === 'failed' }"
                    @click="summary = 'failed'"
                >
                    解析失败
                    <b class="text-destructive">{{ counts.failed }}</b></button
                ><button
                    :class="{ active: summary === 'missing' }"
                    @click="summary = 'missing'"
                >
                    未配置 DNS API {{ counts.missing }}</button
                ><button class="link" @click="chooseTab('dnsapi')">
                    去配置</button
                ><button
                    :class="{ active: summary === 'checking' }"
                    @click="summary = 'checking'"
                >
                    检测中 {{ counts.checking }}</button
                ><span class="muted ml-auto">统计当前页</span>
            </div>
            <div class="table-scroll">
                <table :class="{ 'sites-table': tab === 'sites' }">
                    <thead>
                        <tr>
                            <th class="selection">
                                <CheckboxField
                                    aria-label="选择本页全部"
                                    :checked="allSelected"
                                    :disabled="
                                        loading || busy || !visibleRows.length
                                    "
                                    @change="toggleAll"
                                />
                            </th>
                            <th>ID</th>
                            <template v-if="tab === 'sites'"
                                ><th>域名</th>
                                <th>CNAME</th>
                                <th>HTTPS</th>
                                <th>源站 / 监听</th>
                                <th>套餐/分组</th>
                                <th>区域</th>
                                <th>状态</th>
                                <th>添加时间</th>
                                <th>操作</th></template
                            >
                            <template v-else-if="tab === 'groups'"
                                ><th>用户</th>
                                <th>名称</th>
                                <th>备注</th>
                                <th>操作</th></template
                            >
                            <template v-else-if="tab === 'defaults'"
                                ><th>用户</th>
                                <th>设置项</th>
                                <th>设置值</th>
                                <th>生效范围</th>
                                <th>操作</th></template
                            >
                            <template v-else-if="tab === 'dnsapi'"
                                ><th>用户</th>
                                <th>名称</th>
                                <th>类型</th>
                                <th>备注</th>
                                <th>操作</th></template
                            >
                            <template v-else
                                ><th>网站ID</th>
                                <th>域名</th>
                                <th>CNAME</th>
                                <th>解析状态</th>
                                <th>DNS API</th>
                                <th>任务状态</th></template
                            >
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading || !visibleRows.length">
                            <td
                                :colspan="
                                    tab === 'sites'
                                        ? 11
                                        : tab === 'resolve'
                                          ? 8
                                          : tab === 'groups'
                                            ? 6
                                            : 7
                                "
                                class="empty"
                            >
                                {{ loading ? '加载中…' : '暂无数据' }}
                            </td>
                        </tr>
                        <tr
                            v-for="row in loading ? [] : visibleRows"
                            :key="Number(row.id)"
                        >
                            <td class="selection">
                                <CheckboxField
                                    :aria-label="`选择 ${row.id}`"
                                    :checked="selected.includes(Number(row.id))"
                                    :disabled="busy"
                                    @change="toggle(Number(row.id))"
                                />
                            </td>
                            <td>{{ row.id }}</td>
                            <template v-if="tab === 'sites'">
                                <td>
                                    <div class="copy-cell">
                                        <button
                                            class="link max-w-60 truncate"
                                            :title="String(row.domain)"
                                            @click="emit('manage', row)"
                                        >
                                            {{ row.domain }}</button
                                        ><button
                                            class="copy"
                                            :aria-label="`复制域名 ${row.id}`"
                                            @click="copy(row.domain)"
                                        >
                                            <Copy />
                                        </button>
                                    </div>
                                    <div class="subline">
                                        用户
                                        <span class="link">{{
                                            username(row)
                                        }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="copy-cell">
                                        <span>{{ siteCname(row) || '—' }}</span
                                        ><button
                                            class="copy"
                                            :aria-label="`复制CNAME ${row.id}`"
                                            @click="copy(siteCname(row))"
                                        >
                                            <Copy />
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="pill"
                                        :class="
                                            siteObject(row.https_listen).port
                                                ? 'success'
                                                : 'muted'
                                        "
                                        >{{
                                            siteObject(row.https_listen).port
                                                ? '已开启'
                                                : '未开启'
                                        }}</span
                                    >
                                </td>
                                <td>
                                    <div class="subline">
                                        源站
                                        <span
                                            class="foreground"
                                            :title="siteOrigins(row)"
                                            >{{ siteOrigins(row) }}</span
                                        >
                                    </div>
                                    <div class="subline">
                                        监听
                                        <span
                                            v-for="port in sitePorts(row)"
                                            :key="port"
                                            class="port"
                                            >{{ port }}</span
                                        >
                                    </div>
                                </td>
                                <td>
                                    <div class="subline">
                                        套餐
                                        <span class="foreground"
                                            >{{
                                                row.package_name ??
                                                row.user_package ??
                                                '—'
                                            }}
                                            (id:
                                            {{ row.user_package ?? '—' }})</span
                                        >
                                    </div>
                                    <div class="subline">
                                        分组 {{ row.group_name || '—' }}
                                    </div>
                                </td>
                                <td>{{ originRegion(row) }}</td>
                                <td>
                                    <span
                                        class="pill"
                                        :class="siteStatus(row).tone"
                                        >● {{ siteStatus(row).text }}</span
                                    >
                                </td>
                                <td>
                                    {{
                                        row.create_at2 ??
                                        String(row.create_at ?? '')
                                            .replace('T', ' ')
                                            .slice(0, 19)
                                    }}
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button
                                            class="link"
                                            @click="emit('manage', row)"
                                        >
                                            管理
                                        </button>
                                        <DropdownMenu
                                            ><DropdownMenuTrigger as-child
                                                ><button
                                                    class="link"
                                                    :aria-label="`更多操作 ${row.id}`"
                                                >
                                                    <MoreHorizontal /></button></DropdownMenuTrigger
                                            ><DropdownMenuContent
                                                align="end"
                                                class="min-w-28"
                                                ><DropdownMenuItem
                                                    @select="
                                                        emit('details', row)
                                                    "
                                                    >查看详情</DropdownMenuItem
                                                ><DropdownMenuItem
                                                    @select="emit('edit', row)"
                                                    >编辑</DropdownMenuItem
                                                ><DropdownMenuItem
                                                    @select="
                                                        emit('certificate', row)
                                                    "
                                                    >申请证书</DropdownMenuItem
                                                ><DropdownMenuItem
                                                    @select="
                                                        emit('toggle', row)
                                                    "
                                                    >{{
                                                        String(row.enable) ===
                                                        '0'
                                                            ? '启用'
                                                            : '禁用'
                                                    }}</DropdownMenuItem
                                                ><DropdownMenuItem
                                                    @select="
                                                        emit('delete', row)
                                                    "
                                                    >删除</DropdownMenuItem
                                                ></DropdownMenuContent
                                            ></DropdownMenu
                                        >
                                    </div>
                                </td>
                            </template>
                            <template v-else-if="tab === 'resolve'"
                                ><td>{{ row.site_id }}</td>
                                <td>
                                    <div class="copy-cell">
                                        <span class="link">{{
                                            row.domain
                                        }}</span
                                        ><button
                                            class="copy"
                                            aria-label="复制域名"
                                            @click="copy(row.domain)"
                                        >
                                            <Copy />
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <div class="copy-cell">
                                        {{ row.cname
                                        }}<button
                                            class="copy"
                                            aria-label="复制CNAME"
                                            @click="copy(row.cname)"
                                        >
                                            <Copy />
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="pill outlined"
                                        :class="
                                            checks[String(row.id)] ===
                                            'resolved'
                                                ? 'success'
                                                : checks[String(row.id)] ===
                                                    'checking'
                                                  ? 'muted'
                                                  : 'danger'
                                        "
                                        >{{ checkText(row) }}</span
                                    >
                                </td>
                                <td>
                                    <span
                                        class="pill outlined"
                                        :class="
                                            row.dns_api ? 'success' : 'warning'
                                        "
                                        >{{
                                            row.dns_api ? '已配置' : '未配置'
                                        }}</span
                                    >
                                </td>
                                <td>
                                    <span
                                        class="pill outlined"
                                        :class="
                                            row.state === 'failed'
                                                ? 'danger'
                                                : row.state === 'done'
                                                  ? 'success'
                                                  : 'muted'
                                        "
                                        :title="String(row.ret ?? '')"
                                        >{{ taskText(row) }}</span
                                    >
                                </td></template
                            >
                            <template v-else
                                ><td>{{ username(row) }}</td>
                                <td>
                                    {{
                                        tab === 'defaults'
                                            ? configLabel(row)
                                            : row.name
                                    }}
                                </td>
                                <td v-if="tab === 'dnsapi'">{{ row.type }}</td>
                                <td
                                    v-if="tab === 'defaults'"
                                    class="max-w-72 truncate"
                                    :title="String(row.value ?? '')"
                                >
                                    {{ configValue(row)
                                    }}<span
                                        v-if="String(row.enable) === '0'"
                                        class="pill muted ml-2"
                                        >已禁用</span
                                    >
                                </td>
                                <td>
                                    {{
                                        tab === 'defaults'
                                            ? row.scope_name === 'group'
                                                ? `网站分组 (${row.scope_id})`
                                                : '全局'
                                            : row.des || '—'
                                    }}
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button
                                            class="link"
                                            @click="openEditor(row)"
                                        >
                                            编辑</button
                                        ><button
                                            class="link"
                                            :disabled="busy"
                                            @click="
                                                confirmDelete([Number(row.id)])
                                            "
                                        >
                                            删除
                                        </button>
                                    </div>
                                </td></template
                            >
                        </tr>
                    </tbody>
                </table>
            </div>
            <footer
                class="pagination"
                :class="{ 'justify-end': tab === 'groups' || tab === 'dnsapi' }"
            >
                <span v-if="tab === 'groups'" class="muted mr-auto">{{
                    total ? '' : '暂无分组'
                }}</span
                ><span>共 {{ total }} 条</span
                ><button
                    :disabled="loading || page <= 1"
                    aria-label="上一页"
                    @click="load(page - 1)"
                >
                    <ChevronLeft /></button
                ><button class="current" aria-current="page">{{ page }}</button
                ><button
                    :disabled="loading || page >= pages"
                    aria-label="下一页"
                    @click="load(page + 1)"
                >
                    <ChevronRight /></button
                ><SelectField
                    v-model="size"
                    aria-label="每页条数"
                    @change="load(1)"
                >
                    <SelectOption :value="10">10 条/页</SelectOption>
                    <SelectOption :value="20">20 条/页</SelectOption>
                    <SelectOption :value="50">50 条/页</SelectOption>
                    <SelectOption :value="100">100 条/页</SelectOption>
                </SelectField>
            </footer>
        </div>
        <Dialog
            :open="dialog"
            @update:open="
                (value) => {
                    if (!saving) dialog = value;
                }
            "
            ><DialogScrollContent
                class="site-resource-editor w-[calc(100%_-_2rem)] bg-card sm:max-w-[520px]"
                ><DialogHeader
                    ><DialogTitle>{{ editorTitle }}</DialogTitle
                    ><DialogDescription class="sr-only"
                        >网站管理配置</DialogDescription
                    ></DialogHeader
                >
                <form
                    id="site-resource-form"
                    class="resource-form"
                    @submit.prevent="saveEditor"
                >
                    <p v-if="formError" role="alert" class="text-destructive">
                        {{ formError }}
                    </p>
                    <label
                        >用户 ID
                        <Input
                            v-model="form.uid"
                            type="number"
                            min="1"
                            :disabled="!!editing"
                            required
                    /></label>
                    <template v-if="editorTab === 'defaults'"
                        ><label
                            >设置项<SelectField
                                aria-label="设置项"
                                v-model="form.name"
                                required
                                @change="
                                    form.value =
                                        configMeta?.valueType === 'boolean'
                                            ? '0'
                                            : ''
                                "
                            >
                                <SelectOption value="" disabled
                                    >请选择设置项</SelectOption
                                >
                                <SelectOption
                                    v-if="form.name && !configMeta"
                                    :value="form.name"
                                >
                                    {{ form.name }}
                                </SelectOption>
                                <SelectOption
                                    v-for="item in SITE_CONFIG_NAMES"
                                    :key="item.value"
                                    :value="item.value"
                                >
                                    {{ item.label }}
                                </SelectOption>
                            </SelectField></label
                        ><label
                            >设置值<SelectField
                                v-if="configMeta?.valueType === 'boolean'"
                                aria-label="设置值"
                                v-model="form.value"
                            >
                                <SelectOption value="1">是</SelectOption>
                                <SelectOption value="0"
                                    >否</SelectOption
                                ></SelectField
                            ><SelectField
                                v-else-if="configMeta?.options"
                                aria-label="设置值"
                                v-model="form.value"
                            >
                                <SelectOption
                                    v-for="value in configMeta.options"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ value }}
                                </SelectOption></SelectField
                            ><Textarea
                                v-else
                                v-model="form.value"
                                rows="3" /></label
                        ><label
                            >生效范围<SelectField
                                aria-label="生效范围"
                                v-model="form.scope_name"
                            >
                                <SelectOption value="global">全局</SelectOption>
                                <SelectOption value="group"
                                    >网站分组</SelectOption
                                >
                            </SelectField></label
                        ><label v-if="form.scope_name === 'group'"
                            >网站分组 ID<Input
                                v-model="form.scope_id"
                                type="number"
                                min="1"
                                required /></label
                        ><label
                            >状态<SelectField
                                aria-label="状态"
                                v-model="form.enable"
                            >
                                <SelectOption value="1">启用</SelectOption>
                                <SelectOption value="0">禁用</SelectOption>
                            </SelectField></label
                        ></template
                    >
                    <template v-else
                        ><label
                            >名称<Input
                                v-model="form.name"
                                placeholder="请输入名称"
                                required /></label
                        ><label
                            >备注<Input
                                v-model="form.des"
                                placeholder="请输入备注" /></label
                    ></template>
                    <template v-if="editorTab === 'dnsapi'"
                        ><label
                            >类型<SelectField
                                aria-label="类型"
                                v-model="form.type"
                                :disabled="!!editing && !replaceAuth"
                                @change="authTemplate"
                            >
                                <SelectOption
                                    v-for="type in DNS_TYPES"
                                    :key="type"
                                    :value="type"
                                >
                                    {{ type }}
                                </SelectOption>
                            </SelectField></label
                        ><label v-if="editing" class="auth-toggle"
                            ><CheckboxField v-model="replaceAuth" />更新 API
                            凭据</label
                        ><template v-if="!editing || replaceAuth"
                            ><label v-for="(_, key) in auth" :key="key"
                                >{{ key
                                }}<Input
                                    v-model="auth[key]"
                                    type="password"
                                    autocomplete="new-password"
                                    required /></label></template
                    ></template>
                </form>
                <DialogFooter
                    ><button
                        class="primary"
                        type="submit"
                        form="site-resource-form"
                        :disabled="saving"
                    >
                        确定</button
                    ><button :disabled="saving" @click="dialog = false">
                        取消
                    </button></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <Dialog
            :open="batchOpen"
            @update:open="
                (value) => {
                    if (!saving) batchOpen = value;
                }
            "
            ><DialogScrollContent
                class="site-resource-editor w-[calc(100%_-_2rem)] bg-card sm:max-w-[520px]"
                ><DialogHeader
                    ><DialogTitle>批量修改网站</DialogTitle
                    ><DialogDescription
                        >修改选中的
                        {{ selected.length }} 个网站。</DialogDescription
                    ></DialogHeader
                >
                <form
                    id="site-batch-form"
                    class="resource-form"
                    @submit.prevent="saveBatch"
                >
                    <p v-if="batchError" role="alert" class="text-destructive">
                        {{ batchError }}
                    </p>
                    <label
                        >设置项<SelectField
                            aria-label="设置项"
                            v-model="batchField"
                            @change="batchValue = ''"
                        >
                            <SelectOption value="groups">网站分组</SelectOption>
                            <SelectOption value="backend_http_port">
                                回源 HTTP 端口
                            </SelectOption>
                            <SelectOption value="proxy_timeout"
                                >回源超时</SelectOption
                            >
                            <SelectOption value="gzip_enable"
                                >Gzip</SelectOption
                            >
                            <SelectOption value="websocket_enable"
                                >WebSocket</SelectOption
                            >
                        </SelectField></label
                    ><label
                        >设置值<SelectField
                            v-if="
                                ['gzip_enable', 'websocket_enable'].includes(
                                    batchField,
                                )
                            "
                            aria-label="设置值"
                            v-model="batchValue"
                            required
                        >
                            <SelectOption value="" disabled
                                >请选择</SelectOption
                            >
                            <SelectOption value="1">启用</SelectOption>
                            <SelectOption value="0"
                                >禁用</SelectOption
                            ></SelectField
                        ><Input
                            v-else
                            v-model="batchValue"
                            :required="batchField !== 'groups'"
                            :placeholder="
                                batchField === 'groups'
                                    ? '分组ID，逗号分隔；留空清除'
                                    : ''
                            "
                    /></label>
                </form>
                <DialogFooter
                    ><button
                        class="primary"
                        form="site-batch-form"
                        :disabled="saving"
                    >
                        确定</button
                    ><button :disabled="saving" @click="batchOpen = false">
                        取消
                    </button></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <ConfirmDeleteDialog
            :open="deleteOpen"
            title="删除确认"
            :description="`是否删除选中的 ${deleteIds.length} 项？`"
            :loading="busy"
            :error="deleteError"
            @confirm="removeSelected"
            @cancel="!busy && (deleteOpen = false)"
        />
    </section>
</template>

<style scoped>
.site-workspace {
    font-size: 12px;
    color: var(--foreground);
}
.site-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 12px;
}
.site-tabs button {
    border: 0;
    padding: 6px 12px;
    height: 28px;
    background: transparent;
}
.site-tabs button[aria-selected='true'] {
    background: #2d8cf01a;
    color: #2d8cf0;
    border-radius: 5px;
}
button,
:deep([data-slot='select-trigger']),
input:not([type='checkbox']),
:deep([data-slot='textarea']),
summary {
    border: 1px solid var(--border);
    border-radius: 3px;
    background: var(--card);
    font-size: 12px;
}
button,
summary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 4px 12px;
    min-height: 28px;
    cursor: pointer;
}
button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
button svg,
summary svg {
    width: 13px;
    height: 13px;
}
input:not([type='checkbox']),
:deep([data-slot='select-trigger']) {
    height: 29px;
    padding: 4px 8px;
    min-width: 0;
}
:deep([data-slot='checkbox']) {
    accent-color: #2d8cf0;
    width: 14px;
    height: 14px;
    vertical-align: middle;
}
button:focus-visible,
input:focus-visible,
:deep([data-slot='select-trigger']):focus-visible,
summary:focus-visible {
    outline: 2px solid #2d8cf0;
    outline-offset: 2px;
}
.primary {
    background: #2d8cf0;
    color: white;
    border-color: #2d8cf0;
}
.danger-outline {
    color: #f44b35;
    border-color: #f44b3550;
}
.site-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}
.search-box {
    display: flex;
    margin-left: 20px;
}
.search-box > * {
    border-radius: 0;
}
.search-box input {
    width: 180px;
}
.filter-panel {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px;
    border: 1px solid var(--border);
    background: var(--muted);
    margin-bottom: 12px;
}
.filter-panel label {
    display: flex;
    align-items: center;
    gap: 6px;
}
.filter-panel input {
    width: 95px;
}
.table-scroll {
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    white-space: nowrap;
}
th {
    height: 34px;
    font-weight: 500;
    background: color-mix(in srgb, var(--muted) 25%, var(--card));
    padding: 8px 10px;
    border-bottom: 1px solid var(--border);
}
td {
    height: 46px;
    padding: 4px 10px;
    border-bottom: 1px solid var(--border);
    font-size: 11px;
}
tbody tr:hover {
    background: color-mix(in srgb, var(--primary) 3%, var(--card));
}
.selection {
    width: 44px;
    text-align: center;
}
.empty {
    text-align: center;
    color: var(--muted-foreground);
    height: 42px;
}
.link {
    background: transparent;
    border: 0;
    color: #2d8cf0;
    padding: 0;
    min-height: 0;
}
.copy {
    background: transparent;
    border: 0;
    padding: 2px;
    min-height: 0;
    color: var(--muted-foreground);
}
.copy svg {
    width: 10px;
    height: 10px;
}
.copy-cell,
.row-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.row-actions {
    gap: 16px;
}
.subline {
    color: var(--muted-foreground);
    font-size: 10px;
    line-height: 19px;
}
.foreground {
    color: var(--foreground);
}
.port {
    border: 1px solid var(--border);
    padding: 1px 5px;
    margin-right: 4px;
    border-radius: 2px;
    color: var(--foreground);
}
.pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 10px;
    line-height: 16px;
}
.success {
    background: #19be6b12;
    color: #13b96a;
}
.warning {
    background: #ff990010;
    color: #ed960d;
}
.danger {
    background: #f44b3510;
    color: #f44b35;
}
.muted {
    color: var(--muted-foreground);
}
.pill.muted {
    background: var(--muted);
}
.outlined {
    border: 1px solid currentColor;
}
.pagination {
    display: flex;
    align-items: center;
    gap: 5px;
    padding-top: 16px;
    flex-wrap: wrap;
}
.pagination button {
    padding: 4px 7px;
}
.pagination :deep([data-slot='select-trigger']) {
    margin-left: 8px;
}
.pagination .current {
    color: #2d8cf0;
    border-color: #2d8cf0;
}
.menu {
    position: relative;
}
.menu summary {
    list-style: none;
}
.menu summary::-webkit-details-marker {
    display: none;
}
.row-actions summary {
    border: 0;
    color: #2d8cf0;
    padding: 0;
}
.menu-content {
    position: absolute;
    right: 0;
    top: 100%;
    z-index: 20;
    min-width: 110px;
    display: grid;
    padding: 5px;
    border: 1px solid var(--border);
    background: var(--card);
    box-shadow: 0 4px 12px #0002;
}
.menu-content button {
    border: 0;
    justify-content: flex-start;
    white-space: nowrap;
}
.menu-content button:hover {
    background: var(--muted);
}
.defaults-panel {
    max-width: 966px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 6px;
}
.default-heading {
    border-bottom: 1px solid var(--border);
    padding-bottom: 14px;
    margin-bottom: 14px;
}
.default-heading h3 {
    font-weight: 600;
    border-left: 3px solid #2d8cf0;
    padding-left: 8px;
}
.default-heading p {
    font-size: 11px;
    color: var(--muted-foreground);
    margin-top: 8px;
}
.resolve-filters {
    margin-left: auto;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}
.resolve-filters :deep([data-slot='select-trigger']) {
    width: 125px;
}
.resolve-filters label {
    display: flex;
    align-items: center;
    border: 1px solid var(--border);
    border-radius: 3px;
    padding-left: 6px;
}
.resolve-filters input {
    width: 160px;
    border: 0;
    border-left: 1px solid var(--border);
    border-radius: 0;
    margin-left: 6px;
}
.resolve-filters label:last-of-type input {
    width: 110px;
}
.resolve-summary {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
    border: 1px solid var(--border);
    background: color-mix(in srgb, var(--muted) 20%, var(--card));
    border-radius: 5px;
    padding: 5px 8px;
    margin-bottom: 12px;
    font-size: 10px;
}
.resolve-summary button {
    border: 0;
    border-radius: 14px;
    padding: 3px 8px;
    font-size: 10px;
    min-height: 22px;
}
.resolve-summary .active {
    color: #2d8cf0;
    border: 1px solid #2d8cf033;
}
.resource-form {
    display: grid;
    gap: 20px;
}
.resource-form label {
    display: grid;
    grid-template-columns: 90px minmax(0, 1fr);
    align-items: center;
    gap: 12px;
    font-size: 12px;
}
.resource-form :deep([data-slot='textarea']) {
    padding: 6px;
}
.resource-form .auth-toggle {
    display: flex;
    justify-content: flex-end;
}
:global(.site-resource-editor button) {
    font-size: 12px;
    min-height: 28px;
    border: 1px solid var(--border);
    border-radius: 3px;
    padding: 4px 12px;
}
:global(.site-resource-editor button.primary) {
    background: #2d8cf0;
    color: white;
    border-color: #2d8cf0;
}
@media (max-width: 640px) {
    .search-box {
        margin-left: 0;
        max-width: 100%;
    }
    .search-box input {
        width: 130px;
    }
    .resolve-filters {
        margin-left: 0;
    }
    .resource-form label {
        grid-template-columns: 1fr;
        gap: 6px;
    }
    .site-workspace {
        padding: 12px;
    }
}
</style>
