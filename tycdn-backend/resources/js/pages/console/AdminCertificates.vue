<script setup lang="ts">
import { Check, ChevronDown, Search } from 'lucide-vue-next';
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
    certificateStatus,
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
    error = ref('');
const tabs: { key: Tab; label: string }[] = [
    { key: 'list', label: '证书列表' },
    { key: 'defaults', label: '默认设置' },
    { key: 'dnsapi', label: 'DNS API' },
];
const searchType = ref('domain'),
    search = ref(''),
    filterOpen = ref(false);
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
const allSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selected.value.includes(Number(row.id))),
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
        : rows.value.map((row) => Number(row.id));
}
function searchCerts() {
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
function changePageSize(value: string | number) {
    size.value = Math.max(1, Number(value) || 10);
    void load(1);
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
    <div class="console-admin-certificates p-3 md:p-5">
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
                        ><DropdownMenuContent
                            class="console-admin-certificates"
                            align="start"
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
                            ><DropdownMenuItem
                                :disabled="busy"
                                @select="exportRows"
                                >导出</DropdownMenuItem
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
                            variant="ghost"
                            type="submit"
                            data-slot="console-action"
                            class="search-submit"
                            aria-label="查询"
                        >
                            <Search />
                        </Button>
                    </form>
                    <Button
                        variant="link"
                        size="inline"
                        type="button"
                        data-slot="console-link"
                        class="link advanced-toggle"
                        :aria-expanded="filterOpen"
                        @click="filterOpen = !filterOpen"
                    >
                        高级搜索
                    </Button>
                </div>
                <form
                    v-if="filterOpen"
                    class="filters"
                    aria-label="高级搜索"
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
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th class="selection">
                                    <div data-slot="table-cell-content">
                                        <CheckboxField
                                            aria-label="选择本页全部"
                                            :checked="allSelected"
                                            :disabled="
                                                loading ||
                                                busy ||
                                                !rows.length
                                            "
                                            @change="toggleAll"
                                        />
                                    </div>
                                </th>
                                <th>
                                    <div data-slot="table-cell-content">ID</div>
                                </th>
                                <th>
                                    <div data-slot="table-cell-content">
                                        名称
                                    </div>
                                </th>
                                <th>
                                    <div data-slot="table-cell-content">
                                        类型
                                    </div>
                                </th>
                                <th>
                                    <div data-slot="table-cell-content">
                                        域名
                                    </div>
                                </th>
                                <th>
                                    <div data-slot="table-cell-content">
                                        创建时间
                                    </div>
                                </th>
                                <th>
                                    <div data-slot="table-cell-content">
                                        到期时间
                                    </div>
                                </th>
                                <th>
                                    <div data-slot="table-cell-content">
                                        自动续签
                                    </div>
                                </th>
                                <th>
                                    <div data-slot="table-cell-content">
                                        状态
                                    </div>
                                </th>
                                <th>
                                    <div data-slot="table-cell-content">
                                        操作
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading || !rows.length">
                                <td colspan="10" class="empty">
                                    <div data-slot="table-cell-content">
                                        {{ loading ? '加载中…' : '暂无数据' }}
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="row in loading ? [] : rows"
                                :key="Number(row.id)"
                            >
                                <td class="selection">
                                    <div data-slot="table-cell-content">
                                        <CheckboxField
                                            :aria-label="`选择 ${row.id}`"
                                            :checked="
                                                selected.includes(
                                                    Number(row.id),
                                                )
                                            "
                                            :disabled="busy"
                                            @change="toggle(Number(row.id))"
                                        />
                                    </div>
                                </td>
                                <td>
                                    <div data-slot="table-cell-content">
                                        {{ row.id }}
                                    </div>
                                </td>
                                <td>
                                    <div data-slot="table-cell-content">
                                        <Button
                                            variant="link"
                                            size="inline"
                                            type="button"
                                            data-slot="console-link"
                                            class="link cert-name"
                                            :title="String(row.name ?? '')"
                                            @click="openEditor(row)"
                                        >
                                            {{ row.name || '—' }}
                                        </Button>
                                    </div>
                                </td>
                                <td>
                                    <div data-slot="table-cell-content">
                                        {{
                                            certificateTypes[
                                                String(row.type)
                                            ] ?? row.type
                                        }}
                                    </div>
                                </td>
                                <td>
                                    <div
                                        data-slot="table-cell-content"
                                        class="domain-cell"
                                        :title="String(row.domain ?? '')"
                                    >
                                        {{ row.domain || '—' }}
                                    </div>
                                </td>
                                <td>
                                    <div data-slot="table-cell-content">
                                        {{
                                            row.create_at2 ??
                                            String(row.create_at ?? '')
                                                .replace('T', ' ')
                                                .slice(0, 19)
                                        }}
                                    </div>
                                </td>
                                <td>
                                    <div data-slot="table-cell-content">
                                        {{
                                            row.expire_time2 ??
                                            row.expire_time ??
                                            '—'
                                        }}
                                    </div>
                                </td>
                                <td>
                                    <div data-slot="table-cell-content">
                                        <span
                                            v-if="Number(row.auto_renew) === 1"
                                            class="renew-on"
                                            title="已开启"
                                            aria-label="已开启"
                                        >
                                            <Check />
                                        </span>
                                        <span v-else class="muted">—</span>
                                    </div>
                                </td>
                                <td>
                                    <div data-slot="table-cell-content">
                                        <span
                                            class="status"
                                            :class="certificateStatus(row).tone"
                                            :title="certificateStatus(row).tip"
                                            ><i class="status-dot" aria-hidden="true" />{{
                                                certificateStatus(row).text
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td>
                                    <div data-slot="table-cell-content">
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
                                                ><DropdownMenuContent
                                                    class="console-admin-certificates"
                                                    align="end"
                                                    ><DropdownMenuItem
                                                        v-if="
                                                            row.type !==
                                                            'custom'
                                                        "
                                                        :disabled="busy"
                                                        @select="
                                                            batch('reissue', [
                                                                Number(row.id),
                                                            ])
                                                        "
                                                        >重新申请</DropdownMenuItem
                                                    ><DropdownMenuItem
                                                        :disabled="busy"
                                                        @select="
                                                            batch(
                                                                Number(
                                                                    row.auto_renew,
                                                                ) === 1
                                                                    ? 'noRenew'
                                                                    : 'renew',
                                                                [
                                                                    Number(
                                                                        row.id,
                                                                    ),
                                                                ],
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
                                                                [
                                                                    Number(
                                                                        row.id,
                                                                    ),
                                                                ],
                                                            )
                                                        "
                                                        >{{
                                                            Number(
                                                                row.enable,
                                                            ) === 1
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
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="list-footer">
                    <ConsolePagination
                        :total="total"
                        :page="page"
                        :previous-disabled="loading || page <= 1"
                        :next-disabled="loading || page >= pages"
                        @previous="load(page - 1)"
                        @next="load(page + 1)"
                    />
                    <SelectField
                        class="page-size"
                        :model-value="String(size)"
                        aria-label="每页条数"
                        @update:model-value="changePageSize"
                    >
                        <SelectOption value="10">10 / 页</SelectOption>
                        <SelectOption value="20">20 / 页</SelectOption>
                        <SelectOption value="50">50 / 页</SelectOption>
                    </SelectField>
                </div>
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
                    class="console-admin-certificates certificate-editor w-[calc(100%_-_2rem)] bg-card sm:max-w-[620px]"
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
