<script setup lang="ts">
import { ChevronDown, Copy, Download, Filter } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import AdminSiteWorkspace from '@/components/console/AdminSiteWorkspace.vue';
import CertificateUserPicker from '@/components/console/CertificateUserPicker.vue';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import { Button } from '@/components/ui/button';
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
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import {
    certificateTypes,
    certificateDays,
    certificateExpiryHint,
    certificateStatus,
    certificateSummary,
} from '@/lib/adminCertificates';
import {
    createAdminCert,
    deleteAdminCert,
    listAdminAllCerts,
    updateAdminCert,
} from '@/lib/adminModulesApi';
import type { AdminCertPayload } from '@/lib/adminModulesApi';
import { csvCell, siteResource } from '@/lib/adminSiteWorkspace';
import { apiRequest } from '@/lib/apiRequest';
import {
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnflyResponse';
import type { CdnflyRecord } from '@/lib/sharedTypes';

type Tab = 'list' | 'defaults' | 'dnsapi';
const tab = ref<Tab>('list'),
    rows = ref<CdnflyRecord[]>([]),
    selected = ref<number[]>([]),
    page = ref(1),
    size = ref(10),
    total = ref(0),
    loading = ref(false),
    busy = ref(false),
    error = ref(''),
    now = ref(Date.now());
const tabs: { key: Tab; label: string }[] = [
    { key: 'list', label: '证书列表' },
    { key: 'defaults', label: '默认设置' },
    { key: 'dnsapi', label: 'DNS API' },
];
const searchType = ref('domain'),
    search = ref(''),
    filterOpen = ref(false),
    summaryFilter = ref('all');
const filters = reactive({
    uid: '',
    type: '',
    enable: '',
    expire: '',
    issue_state: '',
    sync_state: '',
    valid: '',
    dnsapi: '',
});
const applied = ref<Record<string, string>>({});
const summary = computed(() => certificateSummary(rows.value, now.value));
const summaryItems = [
    { key: 'all', label: '全部' },
    { key: 'normal', label: '正常' },
    { key: 'expiring', label: '即将到期' },
    { key: 'expired', label: '已过期' },
    { key: 'renew', label: '自动续签' },
    { key: 'noRenew', label: '未开启续签' },
] as const;
const visibleRows = computed(() =>
    rows.value.filter((row) =>
        summaryFilter.value === 'renew'
            ? Number(row.auto_renew) === 1
            : summaryFilter.value === 'noRenew'
              ? Number(row.auto_renew) !== 1
              : true,
    ),
);
const allSelected = computed(
    () =>
        visibleRows.value.length > 0 &&
        visibleRows.value.every((row) =>
            selected.value.includes(Number(row.id)),
        ),
);
const pages = computed(() => Math.max(1, Math.ceil(total.value / size.value)));
let listRequest = 0,
    defaultRequest = 0,
    editorRequest = 0;
onUnmounted(() => {
    listRequest++;
    defaultRequest++;
    editorRequest++;
});
onMounted(() => void load());
const message = (e: unknown) =>
    e instanceof Error ? e.message : '操作失败，请重试';
function selectTab(value: Tab) {
    tab.value = value;
    error.value = '';

    if (value === 'list') {
        void load();
    }
}
async function load(target = page.value) {
    const request = ++listRequest;
    page.value = target;
    loading.value = true;
    error.value = '';
    selected.value = [];

    try {
        const result = await listAdminAllCerts({
            ...applied.value,
            page: page.value,
            limit: size.value,
        });

        if (request !== listRequest) {
            return;
        }

        rows.value = extractCdnflyRows(result);
        total.value = extractCdnflyTotal(result, rows.value.length);
        now.value = Date.now();

        if (page.value > 1 && !rows.value.length) {
            void load(Math.max(1, Math.ceil(total.value / size.value)));

            return;
        }
    } catch (e) {
        if (request === listRequest) {
            error.value = message(e);
            rows.value = [];
            total.value = 0;
        }
    } finally {
        if (request === listRequest) {
            loading.value = false;
        }
    }
}
function toggle(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((value) => value !== id)
        : [...selected.value, id];
}
function toggleAll() {
    selected.value = allSelected.value
        ? []
        : visibleRows.value.map((row) => Number(row.id));
}
function searchCerts() {
    summaryFilter.value = 'all';
    applied.value = Object.fromEntries(
        Object.entries({
            ...filters,
            [searchType.value]: search.value.trim(),
        }).filter(([, value]) => value !== ''),
    );
    void load(1);
}
function clearFilters() {
    Object.keys(filters).forEach(
        (key) => (filters[key as keyof typeof filters] = ''),
    );
    search.value = '';
    searchCerts();
}
function selectSummary(key: string) {
    summaryFilter.value = key;
    selected.value = [];

    if (key === 'renew' || key === 'noRenew') {
        return;
    }

    const query = { ...applied.value };

    for (const name of [
        'expire',
        'valid',
        'enable',
        'issue_state',
        'sync_state',
    ]) {
        delete query[name];
    }

    if (key === 'normal') {
        query.valid = '1';
        query.sync_state = 'done';
    }

    if (key === 'expiring') {
        query.expire = '30';
    }

    if (key === 'expired') {
        query.expire = '0';
    }

    applied.value = query;
    void load(1);
}
async function copy(value: unknown) {
    try {
        await navigator.clipboard.writeText(String(value ?? ''));
        toast.success('已复制');
    } catch {
        toast.error('复制失败');
    }
}
async function exportRows() {
    busy.value = true;
    error.value = '';

    try {
        const data = extractCdnflyRows(
            await listAdminAllCerts({ ...applied.value, limit: 0 }),
        );
        const csv = [
            [
                'ID',
                '用户',
                '用户ID',
                '证书名称',
                '域名',
                '类型',
                '创建时间',
                '到期时间',
                '自动续签',
                '状态',
            ],
            ...data.map((row) => [
                row.id,
                row.username ?? row.user_name,
                row.uid,
                row.name,
                row.domain,
                certificateTypes[String(row.type)] ?? row.type,
                row.create_at2 ?? row.create_at,
                row.expire_time2 ?? row.expire_time,
                Number(row.auto_renew) === 1 ? '已开启' : '未开启',
                certificateStatus(row).text,
            ]),
        ]
            .map((row) => row.map(csvCell).join(','))
            .join('\r\n');
        const url = URL.createObjectURL(
                new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' }),
            ),
            a = document.createElement('a');
        a.href = url;
        a.download = 'certificates.csv';
        a.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
        error.value = message(e);
    } finally {
        busy.value = false;
    }
}
async function batch(
    action: 'reissue' | 'enable' | 'disable' | 'renew' | 'noRenew',
    ids = [...selected.value],
) {
    busy.value = true;
    error.value = '';
    const failures: number[] = [];
    let lastError = '';

    for (const id of ids) {
        try {
            const payload: Partial<AdminCertPayload> =
                action === 'reissue'
                    ? { reissue: 1 }
                    : action === 'enable' || action === 'disable'
                      ? { enable: action === 'enable' ? 1 : 0 }
                      : { auto_renew: action === 'renew' ? 1 : 0 };
            await updateAdminCert(id, payload);
        } catch (e) {
            failures.push(id);
            lastError = message(e);
        }
    }

    await load();
    selected.value = failures;

    if (failures.length) {
        error.value = `${failures.length} 项失败：${lastError}`;
    } else {
        toast.success(action === 'reissue' ? '已提交重签申请' : '操作成功');
    }

    busy.value = false;
}
const deleteOpen = ref(false),
    deleteIds = ref<number[]>([]),
    deleteError = ref('');
function askDelete(ids = [...selected.value]) {
    deleteIds.value = [...ids];
    deleteError.value = '';
    deleteOpen.value = true;
}
async function remove() {
    busy.value = true;
    deleteError.value = '';
    const failures: number[] = [];

    for (const id of deleteIds.value) {
        try {
            await deleteAdminCert(id);
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

const defaultUid = ref(''),
    defaultUser = ref(''),
    defaultLoading = ref(false),
    defaultSaving = ref(false),
    defaultError = ref(''),
    defaultType = ref('system'),
    defaultDns = ref(''),
    defaultDnsOptions = ref<CdnflyRecord[]>([]),
    defaultReady = ref(false);
async function chooseDefaultUser(user: CdnflyRecord | null) {
    defaultUid.value = user ? String(user.id) : '';
    defaultUser.value = user
        ? `${user.name ?? user.username} (ID: ${user.id})`
        : '';
    await loadDefaults();
}
async function loadDefaults() {
    const request = ++defaultRequest;
    defaultReady.value = false;
    defaultError.value = '';
    defaultType.value = 'system';
    defaultDns.value = '';
    defaultDnsOptions.value = [];
    defaultLoading.value = false;

    if (!defaultUid.value) {
        return;
    }

    defaultLoading.value = true;

    try {
        const data = await apiRequest<{
            configs: CdnflyRecord[];
            dnsapis: CdnflyRecord[];
        }>(`/api/admin/certificate-defaults/${defaultUid.value}`);

        if (request !== defaultRequest) {
            return;
        }

        defaultType.value = String(
            data.configs.find((row) => row.name === 'cert_default_type')
                ?.value ?? 'system',
        );
        defaultDns.value = String(
            data.configs.find((row) => row.name === 'dnsapi')?.value ?? '',
        );
        defaultDnsOptions.value = data.dnsapis;
        defaultReady.value = true;
    } catch (e) {
        if (request === defaultRequest) {
            defaultError.value = message(e);
        }
    } finally {
        if (request === defaultRequest) {
            defaultLoading.value = false;
        }
    }
}
async function saveDefault(name: 'cert_default_type' | 'dnsapi', event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    defaultSaving.value = true;
    defaultError.value = '';

    try {
        await apiRequest(
            `/api/admin/certificate-defaults/${defaultUid.value}`,
            { method: 'PUT', body: JSON.stringify({ name, value }) },
        );

        if (name === 'cert_default_type') {
            defaultType.value = value;
        } else {
            defaultDns.value = value;
        }

        toast.success('设置已保存');
    } catch (e) {
        defaultError.value = message(e);
        (event.target as HTMLSelectElement).value =
            name === 'cert_default_type' ? defaultType.value : defaultDns.value;
    } finally {
        defaultSaving.value = false;
    }
}

const editorOpen = ref(false),
    editing = ref<number | null>(null),
    editorLoading = ref(false),
    editorReady = ref(false),
    editorError = ref(''),
    saving = ref(false),
    replacePem = ref(false),
    editorDns = ref<CdnflyRecord[]>([]),
    editorDnsError = ref(''),
    editorDnsLoading = ref(false),
    ownerLabel = ref('');
const form = reactive({
    uid: '',
    name: '',
    des: '',
    type: 'custom',
    domain: '',
    dnsapi: '',
    key: '',
    cert: '',
    auto_renew: '1',
    enable: '1',
});
let dnsRequest = 0;
onUnmounted(() => dnsRequest++);
async function loadEditorDns() {
    const request = ++dnsRequest;
    editorDns.value = [];
    editorDnsError.value = '';
    editorDnsLoading.value = false;

    if (!form.uid) {
        return;
    }

    editorDnsLoading.value = true;

    try {
        const data = await siteResource('dnsapis', 'GET', {
            uid: form.uid,
            limit: 0,
        });

        if (request === dnsRequest) {
            editorDns.value = extractCdnflyRows(data);
        }
    } catch (e) {
        if (request === dnsRequest) {
            editorDnsError.value = message(e);
        }
    } finally {
        if (request === dnsRequest) {
            editorDnsLoading.value = false;
        }
    }
}
function chooseOwner(user: CdnflyRecord | null) {
    form.uid = user ? String(user.id) : '';
    ownerLabel.value = user
        ? `${user.name ?? user.username} (ID: ${user.id})`
        : '';
    form.dnsapi = '';
    void loadEditorDns();
}
async function openEditor(row: CdnflyRecord | null = null) {
    const request = ++editorRequest;
    dnsRequest++;
    editorDnsLoading.value = false;
    editing.value = row ? Number(row.id) : null;
    editorOpen.value = true;
    editorError.value = '';
    editorReady.value = !row;
    editorLoading.value = !!row;
    replacePem.value = false;
    editorDns.value = [];
    editorDnsError.value = '';
    ownerLabel.value = '';
    Object.assign(form, {
        uid: '',
        name: '',
        des: '',
        type: 'custom',
        domain: '',
        dnsapi: '',
        key: '',
        cert: '',
        auto_renew: '1',
        enable: '1',
    });

    if (!row) {
        return;
    }

    try {
        const data = extractCdnflyRecord(
            await apiRequest(`/api/admin/all-certs/${row.id}`),
        );

        if (request !== editorRequest) {
            return;
        }

        if (!data) {
            throw new Error('证书详情不可用');
        }

        for (const key of [
            'uid',
            'name',
            'des',
            'type',
            'domain',
            'dnsapi',
            'auto_renew',
            'enable',
        ] as const) {
            form[key] = String(
                data[key] ??
                    (key === 'auto_renew' || key === 'enable' ? '1' : ''),
            );
        }

        ownerLabel.value = `${data.username ?? row.username ?? ''} (ID: ${form.uid})`;
        editorReady.value = true;
        void loadEditorDns();
    } catch (e) {
        if (request === editorRequest) {
            editorError.value = message(e);
        }
    } finally {
        if (request === editorRequest) {
            editorLoading.value = false;
        }
    }
}
function closeEditor() {
    if (saving.value) {
        return;
    }

    editorOpen.value = false;
    editorRequest++;
    dnsRequest++;
}
async function saveEditor() {
    editorError.value = '';

    if (!editorReady.value || !form.name.trim() || !(Number(form.uid) > 0)) {
        editorError.value = '请选择用户并填写证书名称';

        return;
    }

    const payload: AdminCertPayload = {
        name: form.name.trim(),
        type: form.type as AdminCertPayload['type'],
        des: form.des,
        auto_renew: Number(form.auto_renew),
        enable: Number(form.enable),
        ...(!editing.value ? { uid: Number(form.uid) } : {}),
    };

    if (form.type === 'custom') {
        if (!editing.value || replacePem.value) {
            if (!form.key.trim() || !form.cert.trim()) {
                editorError.value = '请填写证书和私钥';

                return;
            }

            payload.key = form.key.trim();
            payload.cert = form.cert.trim();
        }
    } else {
        if (!form.domain.trim()) {
            editorError.value = '请输入证书域名';

            return;
        }

        payload.domain = form.domain.trim();
        payload.dnsapi = form.dnsapi ? Number(form.dnsapi) : null;
    }

    saving.value = true;

    try {
        if (editing.value) {
            await updateAdminCert(editing.value, payload);
        } else {
            await createAdminCert(payload);
        }

        editorOpen.value = false;
        toast.success('保存成功');
        await load();
    } catch (e) {
        editorError.value = message(e);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="p-3 md:p-5">
        <section class="cert-workspace rounded-xl border bg-card p-4 shadow-sm">
            <nav role="tablist" aria-label="证书管理" class="cert-tabs">
                <Button
                    variant="ghost"
                    type="button"
                    data-slot="console-tab"
                    v-for="item in tabs"
                    :key="item.key"
                    role="tab"
                    :aria-selected="tab === item.key"
                    :disabled="busy || defaultSaving"
                    @click="selectTab(item.key)"
                >
                    {{ item.label }}
                </Button>
            </nav>
            <div v-if="tab === 'list'" role="tabpanel" :aria-busy="loading">
                <p
                    data-typography="body"
                    v-if="error"
                    role="alert"
                    class="mb-3 rounded border border-destructive/30 p-3 text-destructive"
                >
                    {{ error }}
                    <Button
                        variant="link"
                        size="inline"
                        type="button"
                        data-slot="console-link"
                        class="link"
                        @click="load()"
                    >
                        重试
                    </Button>
                </p>
                <div class="toolbar">
                    <Button
                        variant="default"
                        type="button"
                        data-slot="console-action"
                        class="primary"
                        @click="openEditor()"
                    >
                        添加证书</Button
                    ><Button
                        variant="outline"
                        type="button"
                        data-slot="console-action"
                        :disabled="!selected.length || busy"
                        @click="batch('reissue')"
                    >
                        重新申请</Button
                    ><DropdownMenu
                        ><DropdownMenuTrigger as-child
                            ><Button
                                variant="outline"
                                type="button"
                                data-slot="console-action"
                            >
                                更多操作
                                <ChevronDown /></Button></DropdownMenuTrigger
                        ><DropdownMenuContent align="start"
                            ><DropdownMenuItem
                                :disabled="!selected.length || busy"
                                @select="batch('enable')"
                                >启用证书</DropdownMenuItem
                            ><DropdownMenuItem
                                :disabled="!selected.length || busy"
                                @select="batch('disable')"
                                >禁用证书</DropdownMenuItem
                            ><DropdownMenuItem
                                :disabled="!selected.length || busy"
                                @select="batch('renew')"
                                >开启自动续签</DropdownMenuItem
                            ><DropdownMenuItem
                                :disabled="!selected.length || busy"
                                @select="batch('noRenew')"
                                >关闭自动续签</DropdownMenuItem
                            ><DropdownMenuItem
                                :disabled="!selected.length || busy"
                                @select="askDelete()"
                                >删除证书</DropdownMenuItem
                            ><DropdownMenuItem @select="load()"
                                >刷新</DropdownMenuItem
                            ></DropdownMenuContent
                        ></DropdownMenu
                    >
                    <form class="search" @submit.prevent="searchCerts">
                        <SelectField v-model="searchType" aria-label="搜索类型">
                            <SelectOption value="domain">域名</SelectOption>
                            <SelectOption value="name">名称</SelectOption>
                            <SelectOption value="id">证书ID</SelectOption>
                            <SelectOption value="uid">用户ID</SelectOption>
                            <SelectOption value="des"
                                >备注</SelectOption
                            ></SelectField
                        ><Input
                            v-model="search"
                            aria-label="搜索证书"
                            placeholder="输入域名,模糊搜索"
                        /><Button
                            variant="default"
                            type="submit"
                            data-slot="console-action"
                            class="primary"
                        >
                            查询
                        </Button>
                    </form>
                    <Button
                        variant="outline"
                        type="button"
                        data-slot="console-action"
                        :aria-expanded="filterOpen"
                        @click="filterOpen = !filterOpen"
                    >
                        <Filter />筛选</Button
                    ><Button
                        variant="outline"
                        type="button"
                        data-slot="console-action"
                        :disabled="busy"
                        @click="exportRows"
                    >
                        <Download />导出
                    </Button>
                </div>
                <form
                    v-if="filterOpen"
                    class="filters"
                    @submit.prevent="searchCerts"
                >
                    <label>用户ID<Input v-model="filters.uid" /></label
                    ><label
                        >类型<SelectField v-model="filters.type">
                            <SelectOption value="">全部</SelectOption>
                            <SelectOption
                                v-for="(label, value) in certificateTypes"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </SelectOption>
                        </SelectField></label
                    ><label
                        >到期时间<SelectField v-model="filters.expire">
                            <SelectOption value="">全部</SelectOption>
                            <SelectOption value="30">一个月内</SelectOption>
                            <SelectOption value="0">已过期</SelectOption>
                        </SelectField></label
                    ><label
                        >启用<SelectField v-model="filters.enable">
                            <SelectOption value="">全部</SelectOption>
                            <SelectOption value="1">启用</SelectOption>
                            <SelectOption value="0">禁用</SelectOption>
                        </SelectField></label
                    ><label
                        >签发状态<SelectField v-model="filters.issue_state">
                            <SelectOption value="">全部</SelectOption>
                            <SelectOption value="done">已签发</SelectOption>
                            <SelectOption value="pending">待签发</SelectOption>
                            <SelectOption value="process">签发中</SelectOption>
                            <SelectOption value="failed">签发失败</SelectOption>
                        </SelectField></label
                    ><label
                        >同步状态<SelectField v-model="filters.sync_state">
                            <SelectOption value="">全部</SelectOption>
                            <SelectOption value="done">已同步</SelectOption>
                            <SelectOption value="pending">待同步</SelectOption>
                            <SelectOption value="process">同步中</SelectOption>
                            <SelectOption value="failed">同步失败</SelectOption>
                        </SelectField></label
                    ><label>DNS API ID<Input v-model="filters.dnsapi" /></label
                    ><Button
                        variant="default"
                        type="submit"
                        data-slot="console-action"
                        class="primary"
                    >
                        查询</Button
                    ><Button
                        variant="outline"
                        data-slot="console-action"
                        type="button"
                        @click="clearFilters"
                    >
                        清除
                    </Button>
                </form>
                <div class="summary">
                    <Button
                        variant="outline"
                        type="button"
                        data-slot="console-action"
                        v-for="item in summaryItems"
                        :key="item.key"
                        :class="{ active: summaryFilter === item.key }"
                        @click="selectSummary(item.key)"
                    >
                        {{ item.label }}
                        <b
                            :class="
                                item.key === 'expired'
                                    ? 'text-destructive'
                                    : item.key === 'expiring'
                                      ? 'text-orange-500'
                                      : 'text-emerald-500'
                            "
                            >{{ summary[item.key] }}</b
                        ></Button
                    ><span class="muted ml-auto">统计当前页</span>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th class="selection">
                                    <CheckboxField
                                        aria-label="选择本页全部"
                                        :checked="allSelected"
                                        :disabled="
                                            loading ||
                                            busy ||
                                            !visibleRows.length
                                        "
                                        @change="toggleAll"
                                    />
                                </th>
                                <th>ID</th>
                                <th>用户</th>
                                <th>证书信息</th>
                                <th>类型</th>
                                <th>创建时间</th>
                                <th>到期时间</th>
                                <th>自动续签</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading || !visibleRows.length">
                                <td colspan="10" class="empty">
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
                                        :checked="
                                            selected.includes(Number(row.id))
                                        "
                                        :disabled="busy"
                                        @change="toggle(Number(row.id))"
                                    />
                                </td>
                                <td>{{ row.id }}</td>
                                <td>
                                    <strong>{{
                                        row.username ?? row.user_name ?? '—'
                                    }}</strong>
                                    <div class="subline">
                                        ID: {{ row.uid ?? '—' }}
                                    </div>
                                </td>
                                <td class="cert-info">
                                    <div class="copy-line">
                                        <Button
                                            variant="link"
                                            size="inline"
                                            type="button"
                                            data-slot="console-link"
                                            class="link cert-name"
                                            :title="String(row.name ?? '')"
                                            @click="openEditor(row)"
                                        >
                                            {{ row.name || '—' }}</Button
                                        ><Button
                                            variant="ghost"
                                            size="icon-sm"
                                            type="button"
                                            class="copy"
                                            :aria-label="`复制证书名称 ${row.id}`"
                                            @click="copy(row.name)"
                                        >
                                            <Copy />
                                        </Button>
                                    </div>
                                    <div class="copy-line subline">
                                        <span
                                            class="truncate"
                                            :title="String(row.domain ?? '')"
                                            >域名: {{ row.domain || '—' }}</span
                                        ><Button
                                            variant="ghost"
                                            size="icon-sm"
                                            type="button"
                                            class="copy"
                                            :aria-label="`复制域名 ${row.id}`"
                                            @click="copy(row.domain)"
                                        >
                                            <Copy />
                                        </Button>
                                    </div>
                                </td>
                                <td>
                                    {{
                                        certificateTypes[String(row.type)] ??
                                        row.type
                                    }}
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
                                    {{
                                        row.expire_time2 ??
                                        row.expire_time ??
                                        '—'
                                    }}
                                    <div
                                        class="subline"
                                        :class="
                                            certificateDays(row, now) !==
                                                null &&
                                            Number(certificateDays(row, now)) <=
                                                30
                                                ? 'text-orange-500'
                                                : ''
                                        "
                                    >
                                        {{ certificateExpiryHint(row, now) }}
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="pill"
                                        :class="
                                            Number(row.auto_renew) === 1
                                                ? 'success'
                                                : 'muted'
                                        "
                                        >{{
                                            Number(row.auto_renew) === 1
                                                ? '● 已开启'
                                                : '未开启'
                                        }}</span
                                    >
                                </td>
                                <td>
                                    <span
                                        class="pill"
                                        :class="certificateStatus(row).tone"
                                        :title="certificateStatus(row).tip"
                                        >●
                                        {{ certificateStatus(row).text }}</span
                                    >
                                </td>
                                <td>
                                    <div class="actions">
                                        <Button
                                            variant="link"
                                            size="inline"
                                            type="button"
                                            data-slot="console-link"
                                            class="link"
                                            @click="openEditor(row)"
                                        >
                                            管理</Button
                                        ><Button
                                            variant="link"
                                            size="inline"
                                            type="button"
                                            data-slot="console-link"
                                            v-if="row.type !== 'custom'"
                                            class="link"
                                            :disabled="busy"
                                            @click="
                                                batch('reissue', [
                                                    Number(row.id),
                                                ])
                                            "
                                        >
                                            重新申请</Button
                                        ><DropdownMenu
                                            ><DropdownMenuTrigger as-child
                                                ><Button
                                                    variant="link"
                                                    size="inline"
                                                    type="button"
                                                    data-slot="console-link"
                                                    class="link"
                                                    :aria-label="`更多操作 ${row.id}`"
                                                >
                                                    更多
                                                    <ChevronDown /></Button></DropdownMenuTrigger
                                            ><DropdownMenuContent align="end"
                                                ><DropdownMenuItem
                                                    :disabled="busy"
                                                    @select="
                                                        batch(
                                                            Number(
                                                                row.auto_renew,
                                                            ) === 1
                                                                ? 'noRenew'
                                                                : 'renew',
                                                            [Number(row.id)],
                                                        )
                                                    "
                                                    >{{
                                                        Number(
                                                            row.auto_renew,
                                                        ) === 1
                                                            ? '关闭自动续签'
                                                            : '开启自动续签'
                                                    }}</DropdownMenuItem
                                                ><DropdownMenuItem
                                                    :disabled="busy"
                                                    @select="
                                                        batch(
                                                            Number(
                                                                row.enable,
                                                            ) === 1
                                                                ? 'disable'
                                                                : 'enable',
                                                            [Number(row.id)],
                                                        )
                                                    "
                                                    >{{
                                                        Number(row.enable) === 1
                                                            ? '禁用'
                                                            : '启用'
                                                    }}</DropdownMenuItem
                                                ><DropdownMenuItem
                                                    :disabled="busy"
                                                    @select="
                                                        askDelete([
                                                            Number(row.id),
                                                        ])
                                                    "
                                                    >删除</DropdownMenuItem
                                                ></DropdownMenuContent
                                            ></DropdownMenu
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ConsolePagination
                    :total="total"
                    :page="page"
                    :previous-disabled="loading || page <= 1"
                    :next-disabled="loading || page >= pages"
                    @previous="load(page - 1)"
                    @next="load(page + 1)"
                />
            </div>
            <div
                v-else-if="tab === 'defaults'"
                role="tabpanel"
                class="default-settings"
                :aria-busy="defaultLoading || defaultSaving"
            >
                <div class="setting-row">
                    <span>用户</span
                    ><CertificateUserPicker
                        v-model="defaultUid"
                        :display="defaultUser"
                        :disabled="defaultSaving"
                        @select="chooseDefaultUser"
                    />
                </div>
                <p
                    data-typography="body"
                    v-if="defaultError"
                    role="alert"
                    class="text-destructive"
                >
                    {{ defaultError }}
                    <Button
                        variant="link"
                        size="inline"
                        type="button"
                        data-slot="console-link"
                        v-if="!defaultReady"
                        class="link"
                        @click="loadDefaults"
                    >
                        重试
                    </Button>
                </p>
                <div class="setting-row">
                    <span>证书类型</span
                    ><span v-if="!defaultUid" class="placeholder-pill"
                        >请先选择用户。</span
                    ><span v-else-if="defaultLoading" class="placeholder-pill"
                        >加载中…</span
                    ><SelectField
                        v-else
                        :value="defaultType"
                        aria-label="默认证书类型"
                        :disabled="!defaultReady || defaultSaving"
                        @change="saveDefault('cert_default_type', $event)"
                    >
                        <SelectOption value="system">跟随系统</SelectOption>
                        <SelectOption value="lets">Let's Encrypt</SelectOption>
                        <SelectOption value="zerossl">ZeroSSL</SelectOption>
                    </SelectField>
                </div>
                <div class="setting-row">
                    <span>DNS API</span
                    ><span v-if="!defaultUid" class="placeholder-pill"
                        >选择用户后加载可用 DNS API。</span
                    ><span v-else-if="defaultLoading" class="placeholder-pill"
                        >加载中…</span
                    ><SelectField
                        v-else
                        :value="defaultDns"
                        aria-label="默认DNS API"
                        :disabled="!defaultReady || defaultSaving"
                        @change="saveDefault('dnsapi', $event)"
                    >
                        <SelectOption value="">不设置</SelectOption>
                        <SelectOption
                            v-if="
                                defaultDns &&
                                !defaultDnsOptions.some(
                                    (row) => String(row.id) === defaultDns,
                                )
                            "
                            :value="defaultDns"
                        >
                            当前配置 (ID: {{ defaultDns }})
                        </SelectOption>
                        <SelectOption
                            v-for="row in defaultDnsOptions"
                            :key="String(row.id)"
                            :value="String(row.id)"
                        >
                            {{ row.name }}
                        </SelectOption>
                    </SelectField>
                </div>
            </div>
            <AdminSiteWorkspace v-else initial-tab="dnsapi" embedded />
            <Dialog
                :open="editorOpen"
                @update:open="
                    (value) => {
                        if (!value) closeEditor();
                    }
                "
                ><DialogScrollContent
                    class="certificate-editor w-[calc(100%_-_2rem)] bg-card sm:max-w-[620px]"
                    ><DialogHeader
                        ><DialogTitle>{{
                            editing ? '管理证书' : '添加证书'
                        }}</DialogTitle
                        ><DialogDescription class="sr-only"
                            >证书配置</DialogDescription
                        ></DialogHeader
                    >
                    <p data-typography="body" v-if="editorLoading">加载中…</p>
                    <form
                        v-else
                        id="certificate-form"
                        class="certificate-form"
                        @submit.prevent="saveEditor"
                    >
                        <p
                            data-typography="body"
                            v-if="editorError"
                            role="alert"
                            class="text-destructive"
                        >
                            {{ editorError }}
                            <Button
                                variant="link"
                                size="inline"
                                data-slot="console-link"
                                v-if="!editorReady && editing"
                                type="button"
                                @click="openEditor({ id: editing })"
                            >
                                重试
                            </Button>
                        </p>
                        <template v-if="editorReady"
                            ><label
                                >用户<span v-if="editing">{{ ownerLabel }}</span
                                ><CertificateUserPicker
                                    v-else
                                    v-model="form.uid"
                                    :display="ownerLabel"
                                    :disabled="saving"
                                    @select="chooseOwner" /></label
                            ><label
                                >证书名称<Input
                                    v-model="form.name"
                                    required /></label
                            ><label>备注<Input v-model="form.des" /></label
                            ><label
                                >证书类型<SelectField
                                    v-model="form.type"
                                    aria-label="证书类型"
                                    :disabled="!!editing"
                                    @change="
                                        form.type !== 'custom' &&
                                        loadEditorDns()
                                    "
                                >
                                    <SelectOption
                                        v-for="(
                                            label, value
                                        ) in certificateTypes"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </SelectOption>
                                </SelectField></label
                            ><template v-if="form.type === 'custom'"
                                ><label v-if="editing" class="replace-pem"
                                    ><CheckboxField
                                        v-model="replacePem"
                                    />替换证书和私钥</label
                                ><template v-if="!editing || replacePem"
                                    ><label
                                        >证书<Textarea
                                            v-model="form.cert"
                                            rows="5"
                                            placeholder="-----BEGIN CERTIFICATE-----"
                                            required
                                            spellcheck="false" /></label
                                    ><label
                                        >私钥<Textarea
                                            v-model="form.key"
                                            rows="5"
                                            placeholder="-----BEGIN PRIVATE KEY-----"
                                            required
                                            spellcheck="false" /></label></template></template
                            ><template v-else
                                ><label
                                    >域名<Textarea
                                        v-model="form.domain"
                                        rows="3"
                                        placeholder="多个域名以空格分隔"
                                        required
                                    />
                                </label>
                                <p
                                    data-typography="body"
                                    v-if="editorDnsError"
                                    role="alert"
                                    class="text-destructive"
                                >
                                    {{ editorDnsError }}
                                    <Button
                                        variant="link"
                                        size="inline"
                                        data-slot="console-link"
                                        type="button"
                                        @click="loadEditorDns"
                                    >
                                        重试
                                    </Button>
                                </p>
                                <label
                                    >DNS API<SelectField
                                        v-model="form.dnsapi"
                                        aria-label="证书DNS API"
                                        :disabled="
                                            editorDnsLoading || !!editorDnsError
                                        "
                                    >
                                        <SelectOption value=""
                                            >不设置</SelectOption
                                        >
                                        <SelectOption
                                            v-if="
                                                form.dnsapi &&
                                                !editorDns.some(
                                                    (row) =>
                                                        String(row.id) ===
                                                        form.dnsapi,
                                                )
                                            "
                                            :value="form.dnsapi"
                                        >
                                            当前配置 (ID: {{ form.dnsapi }})
                                        </SelectOption>
                                        <SelectOption
                                            v-for="row in editorDns"
                                            :key="String(row.id)"
                                            :value="String(row.id)"
                                        >
                                            {{ row.name }}
                                        </SelectOption>
                                    </SelectField></label
                                ></template
                            ><label
                                >自动续签<SelectField
                                    v-model="form.auto_renew"
                                    aria-label="自动续签"
                                >
                                    <SelectOption value="1">开启</SelectOption>
                                    <SelectOption value="0">关闭</SelectOption>
                                </SelectField></label
                            ><label
                                >状态<SelectField
                                    v-model="form.enable"
                                    aria-label="证书状态"
                                >
                                    <SelectOption value="1">启用</SelectOption>
                                    <SelectOption value="0">禁用</SelectOption>
                                </SelectField></label
                            ></template
                        >
                    </form>
                    <DialogFooter
                        ><Button
                            variant="default"
                            data-slot="console-action"
                            class="primary"
                            type="submit"
                            form="certificate-form"
                            :disabled="saving || !editorReady || editorLoading"
                        >
                            确定</Button
                        ><Button
                            variant="outline"
                            type="button"
                            data-slot="console-action"
                            :disabled="saving"
                            @click="closeEditor"
                        >
                            取消
                        </Button></DialogFooter
                    ></DialogScrollContent
                ></Dialog
            >
            <ConfirmDeleteDialog
                :open="deleteOpen"
                title="删除确认"
                :description="`是否删除选中的 ${deleteIds.length} 张证书？`"
                :loading="busy"
                :error="deleteError"
                @confirm="remove"
                @cancel="!busy && (deleteOpen = false)"
            />
        </section>
    </div>
</template>

<style scoped>
.cert-workspace {
    font-size: var(--console-text-body);
}
.cert-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}
.cert-tabs button {
    border: 0;
    background: transparent;
    padding: 6px 13px;
}
.cert-tabs button[aria-selected='true'] {
    color: #2d8cf0;
    background: #2d8cf01a;
    border-radius: 5px;
}
button,
input:not([type='checkbox']),
:deep([data-slot='select-trigger']),
:deep([data-slot='textarea']) {
    font-size: var(--console-text-body);
    border: 1px solid var(--border);
    background: var(--card);
    border-radius: 3px;
}
button {
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
button svg {
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
:deep([data-slot='textarea']):focus-visible {
    outline: 2px solid #2d8cf0;
    outline-offset: 2px;
}
.primary {
    background: #2d8cf0;
    color: white;
    border-color: #2d8cf0;
}
.link {
    border: 0;
    background: transparent;
    color: #2d8cf0;
    padding: 0;
    min-height: 0;
}
.copy {
    border: 0;
    background: transparent;
    color: var(--muted-foreground);
    padding: 1px;
    min-height: 0;
}
.copy svg {
    width: 10px;
    height: 10px;
}
.toolbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}
.search {
    display: flex;
    align-items: center;
    margin-left: auto;
}
.search > * {
    border-radius: 0;
}
.search input {
    width: 330px;
    max-width: 40vw;
}
.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    padding: 12px;
    border: 1px solid var(--border);
    margin-bottom: 10px;
}
.filters label {
    display: flex;
    align-items: center;
    gap: 6px;
}
.filters input {
    width: 100px;
}
.summary {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 7px;
    margin-bottom: 10px;
}
.summary button {
    border: 1px solid transparent;
    background: color-mix(in srgb, var(--muted) 60%, var(--card));
    border-radius: 20px;
    font-size: var(--console-text-body);
    padding: 2px 10px;
    min-height: 24px;
    gap: 2px;
}
.summary button.active {
    color: #2d8cf0;
    border-color: #2d8cf040;
    background: #2d8cf005;
}
.summary b {
    font-weight: 500;
}
.muted {
    color: var(--muted-foreground);
}
.summary > span {
    font-size: var(--console-text-helper);
}
.table-scroll {
    overflow-x: auto;
}
table {
    border-collapse: collapse;
    white-space: nowrap;
    width: 100%;
    text-align: left;
}
th {
    height: 34px;
    background: color-mix(in srgb, var(--muted) 25%, var(--card));
    font-weight: 500;
    font-size: var(--console-text-body);
    padding: 6px 10px;
    border-bottom: 1px solid var(--border);
}
td {
    height: 42px;
    padding: 3px 10px;
    border-bottom: 1px solid var(--border);
    font-size: var(--console-text-body);
    vertical-align: top;
}
td.selection {
    padding-top: 5px;
}
.selection {
    width: 44px;
    text-align: center;
}
tbody tr:hover {
    background: color-mix(in srgb, var(--primary) 3%, var(--card));
}
.cert-info {
    min-width: 215px;
    max-width: 310px;
}
.copy-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.cert-name {
    font-weight: 500;
    max-width: 280px;
    text-overflow: ellipsis;
    overflow: hidden;
    white-space: nowrap;
}
.subline {
    font-size: var(--console-text-helper);
    line-height: 18px;
    color: var(--muted-foreground);
}
.actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.pill {
    display: inline-block;
    border-radius: 12px;
    padding: 1px 8px;
    font-size: var(--console-text-helper);
    border: 1px solid transparent;
}
.success {
    color: #13b96a;
    background: #19be6b12;
    border-color: #19be6b25;
}
.danger {
    color: #f44b35;
    background: #f44b3510;
    border-color: #f44b3525;
}
.warning {
    color: #ed960d;
    background: #ff990010;
    border-color: #ff990025;
}
.pill.muted {
    background: var(--muted);
}
.empty {
    text-align: center;
    height: 44px;
    vertical-align: middle;
    color: var(--muted-foreground);
}
.default-settings {
    padding: 4px 20px 2px;
    display: grid;
    gap: 18px;
}
.setting-row {
    display: flex;
    align-items: center;
    gap: 14px;
    min-height: 28px;
}
.setting-row > span:first-child {
    width: 64px;
    text-align: right;
    flex-shrink: 0;
}
.placeholder-pill {
    padding: 6px 10px;
    border-radius: 20px;
    color: var(--muted-foreground);
    background: color-mix(in srgb, var(--muted) 60%, var(--card));
}
.setting-row :deep([data-slot='select-trigger']) {
    min-width: 220px;
}
.certificate-form {
    display: grid;
    gap: 16px;
}
.certificate-form label {
    display: grid;
    grid-template-columns: 80px minmax(0, 1fr);
    align-items: center;
    gap: 12px;
}
.certificate-form :deep([data-slot='textarea']) {
    padding: 6px;
    min-width: 0;
    resize: vertical;
}
.certificate-form .replace-pem {
    display: flex;
    justify-content: flex-end;
}
.certificate-form input:not([type='checkbox']) {
    width: 100%;
}
:global(.certificate-editor [data-slot='dialog-footer'] button) {
    min-height: 28px;
    border: 1px solid var(--border);
    border-radius: 3px;
    padding: 4px 12px;
    font-size: var(--console-text-body);
}
:global(.certificate-editor [data-slot='dialog-footer'] button.primary) {
    background: #2d8cf0;
    color: white;
    border-color: #2d8cf0;
}
@media (max-width: 640px) {
    .search {
        margin-left: 0;
        max-width: 100%;
    }
    .search input {
        width: 155px;
    }
    .default-settings {
        padding: 0;
    }
    .setting-row {
        gap: 8px;
        flex-wrap: wrap;
    }
    .setting-row > span:first-child {
        width: 55px;
    }
    .setting-row :deep(.user-picker) {
        width: 100%;
    }
    .certificate-form label {
        grid-template-columns: 1fr;
        gap: 6px;
    }
}
</style>
