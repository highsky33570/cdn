<script setup lang="ts">
import { Plus, RefreshCw, ChevronDown, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/date-picker/DatePicker.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import Switch from '@/components/ui/switch/Switch.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { apiRequest } from '@/lib/apiRequest';
import {
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnflyResponse';
import { getErrorMessage } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import AdminUsers from './AdminUsers.vue';

const endpoint = '/api/admin/master-users',
    groupEndpoint = '/api/admin/master-user-groups';
const tab = ref('users'),
    localOpen = ref(false),
    page = ref(1),
    size = ref(10),
    total = ref(0),
    rows = ref<CdnflyRecord[]>([]),
    selected = ref<(string | number)[]>([]);
const loading = ref(false),
    busy = ref(false),
    error = ref(''),
    groupsError = ref(''),
    groups = ref<CdnflyRecord[]>([]);
const filters = reactive({
    user_id: '',
    name: '',
    cert_name: '',
    des: '',
    email: '',
    qq: '',
    phone: '',
    cert_verified: 'all',
    user_group: 'all',
});
const searchFields = [
    ['user_id', '用户ID'],
    ['name', '用户名'],
    ['cert_name', '姓名'],
    ['des', '备注'],
    ['email', '邮箱'],
    ['qq', 'QQ'],
    ['phone', '手机号'],
] as const;
const userColumns: ColumnDef[] = [
    { key: 'identity', label: '用户信息', width: '270px' },
    { key: 'contact', label: '联系方式', width: '180px' },
    { key: 'des', label: '备注', width: '210px' },
    { key: 'balance', label: '余额', width: '100px', align: 'right' },
    { key: 'cert_verified', label: '实名', width: '120px' },
    { key: 'enable', label: '启用', width: '100px' },
    { key: 'type', label: '类型', width: '120px' },
    { key: 'create_at', label: '添加时间', width: '185px' },
];
const groupColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '85px' },
    { key: 'name', label: '名称', width: '260px' },
    { key: 'des', label: '备注' },
];
const columns = computed(() =>
    tab.value === 'users' ? userColumns : groupColumns,
);
const text = (value: unknown, fallback = '—') =>
    value === null || value === undefined || String(value).trim() === ''
        ? fallback
        : String(value);
const enabled = (value: unknown) => Number(value) === 1;
const certified = (row: CdnflyRecord) =>
    enabled(row.cert_verified) &&
    (!enabled(row.auth2_enable) || enabled(row.auth2_verified));
const balance = (value: unknown) =>
    value !== null &&
    value !== undefined &&
    value !== '' &&
    Number.isFinite(Number(value))
        ? `${Number(value) / 100}元`
        : '—';
let token = 0,
    groupToken = 0,
    timer: ReturnType<typeof setTimeout> | undefined;
async function load() {
    clearTimeout(timer);
    const current = ++token;
    loading.value = true;
    error.value = '';
    rows.value = [];
    selected.value = [];

    try {
        const query = new URLSearchParams({
            page: String(page.value),
            limit: String(size.value),
        });

        if (tab.value === 'users') {
            if (
                filters.user_id.trim() &&
                !/^\d+$/.test(filters.user_id.trim())
            ) {
                throw new Error('用户ID请填写数字');
            }

            for (const [key, value] of Object.entries(filters)) {
                if (value.trim() && value !== 'all') {
                    query.set(key, value.trim());
                }
            }
        }

        const result = await apiRequest(
            `${tab.value === 'users' ? endpoint : groupEndpoint}?${query}`,
        );

        if (current !== token) {
            return;
        }

        rows.value = extractCdnflyRows(result);
        total.value = extractCdnflyTotal(result, rows.value.length);
        const last = Math.max(1, Math.ceil(total.value / size.value));

        if (page.value > last) {
            page.value = last;
        }
    } catch (e) {
        if (current === token) {
            error.value = getErrorMessage(e);
            total.value = 0;
        }
    } finally {
        if (current === token) {
            loading.value = false;
        }
    }
}
async function loadGroups() {
    const current = ++groupToken;
    groupsError.value = '';

    try {
        const result = await apiRequest(`${groupEndpoint}?limit=0`);

        if (current === groupToken) {
            groups.value = extractCdnflyRows(result);
        }
    } catch (e) {
        if (current === groupToken) {
            groupsError.value = getErrorMessage(e);
        }
    }
}
function search() {
    if (page.value === 1) {
        void load();
    } else {
        page.value = 1;
    }
}
watch([tab, size], search);
watch(page, load);
watch(() => [filters.cert_verified, filters.user_group], search);
watch(
    () => searchFields.map(([key]) => filters[key]),
    () => {
        ++token;
        rows.value = [];
        selected.value = [];
        loading.value = true;
        clearTimeout(timer);
        timer = setTimeout(search, 300);
    },
);
onMounted(() => {
    void load();
    void loadGroups();
});

const editorOpen = ref(false),
    editorTab = ref('basic'),
    editId = ref<string | null>(null),
    editorLoading = ref(false),
    formError = ref('');
const defaults = () => ({
    email: '',
    name: '',
    des: '',
    phone: '',
    qq: '',
    password: '',
    user_group: 'none',
    type: '2',
    enable: true,
    cert_name: '',
    cert_no: '',
    company_name: '',
    company_credit_code: '',
    cert_verified: false,
    company_verified: false,
    auth2_enable: false,
    auth2_end_at: '',
    auth2_expire_action: 'none',
    auth2_verified: false,
    login_captcha: 'none',
    white_ip: '',
});
const form = reactive(defaults());
let original: Record<string, unknown> = {},
    editorToken = 0;
const basicFields = [
    ['email', '邮箱', 'email', '请输入邮箱地址'],
    ['name', '用户名', 'text', '请输入用户名'],
    ['des', '备注', 'textarea', '请输入备注'],
    ['phone', '手机号', 'text', '请输入手机号'],
    ['qq', 'QQ', 'text', '请输入QQ'],
    ['password', '密码', 'password', '请输入密码'],
] as const;
const identityFields = [
    ['cert_name', '姓名', '请输入姓名'],
    ['cert_no', '身份证', '请输入身份证'],
    ['company_name', '公司名称', '请输入公司名称'],
    ['company_credit_code', '社会信用代码', '请输入社会信用代码'],
] as const;
function payload() {
    const result: Record<string, unknown> = {
        ...form,
        user_group: form.user_group === 'none' ? null : Number(form.user_group),
        type: Number(form.type),
        login_captcha: form.login_captcha === 'none' ? '' : form.login_captcha,
        auth2_end_at: form.auth2_end_at.replace('T', ' '),
    };

    if (result.auth2_end_at && String(result.auth2_end_at).length === 16) {
        result.auth2_end_at += ':00';
    }

    for (const key of [
        'enable',
        'cert_verified',
        'company_verified',
        'auth2_enable',
        'auth2_verified',
    ] as const) {
        result[key] = form[key] ? 1 : 0;
    }

    if (!form.password) {
        delete result.password;
    }

    if (editId.value) {
        delete result.type;
    } else {
        delete result.company_name;
        delete result.company_credit_code;
        delete result.company_verified;
    }

    return result;
}
async function openEditor(row?: CdnflyRecord) {
    editId.value = row ? String(row.id) : null;
    editorTab.value = 'basic';
    formError.value = '';
    Object.assign(form, defaults());
    editorOpen.value = true;
    const current = ++editorToken;

    if (!row) {
        original = {};
        editorLoading.value = false;

        return;
    }

    editorLoading.value = true;

    try {
        const record = extractCdnflyRecord(
            await apiRequest(`${endpoint}/${row.id}`),
        );

        if (current !== editorToken) {
            return;
        }

        if (!record?.id) {
            throw new Error('未找到用户');
        }

        for (const key of Object.keys(defaults()) as (keyof ReturnType<
            typeof defaults
        >)[]) {
            if (key === 'password') {
                continue;
            }

            if (typeof form[key] === 'boolean') {
                Object.assign(form, { [key]: enabled(record[key]) });
            } else {
                Object.assign(form, { [key]: text(record[key], '') });
            }
        }

        form.user_group = text(record.user_group, 'none');
        form.login_captcha = text(record.login_captcha, 'none');
        form.auth2_expire_action = text(record.auth2_expire_action, 'none');
        form.auth2_end_at = text(record.auth2_end_at, '').replace(' ', 'T');
        original = payload();
    } catch (e) {
        if (current === editorToken) {
            formError.value = getErrorMessage(e);
        }
    } finally {
        if (current === editorToken) {
            editorLoading.value = false;
        }
    }
}
function closeEditor(open: boolean) {
    if (busy.value) {
        return;
    }

    editorOpen.value = open;

    if (!open) {
        ++editorToken;
        editorLoading.value = false;
    }
}
async function saveUser() {
    if (busy.value || editorLoading.value) {
        return;
    }

    formError.value = '';

    if (
        !form.email.trim() ||
        !form.name.trim() ||
        (!editId.value && !form.password)
    ) {
        formError.value = '请填写邮箱、用户名和密码';
        editorTab.value = 'basic';

        return;
    }

    if (form.auth2_enable && !form.cert_verified) {
        formError.value = '开启二次实名前，请先完成个人实名';
        editorTab.value = 'identity';

        return;
    }

    let data = payload();

    if (editId.value) {
        data = Object.fromEntries(
            Object.entries(data).filter(
                ([key, value]) => value !== original[key],
            ),
        );
    }

    if (!Object.keys(data).length) {
        closeEditor(false);

        return;
    }

    busy.value = true;

    try {
        await apiRequest(endpoint + (editId.value ? `/${editId.value}` : ''), {
            method: editId.value ? 'PUT' : 'POST',
            body: JSON.stringify(data),
        });
        editorOpen.value = false;
        toast.success('用户已保存');
        await load();
    } catch (e) {
        formError.value = getErrorMessage(e);
    } finally {
        busy.value = false;
    }
}
const groupOpen = ref(false),
    groupId = ref<string | null>(null),
    groupForm = reactive({ name: '', des: '' }),
    groupError = ref('');
function openGroup(row?: CdnflyRecord) {
    groupId.value = row ? String(row.id) : null;
    groupForm.name = text(row?.name, '');
    groupForm.des = text(row?.des, '');
    groupError.value = '';
    groupOpen.value = true;
}
async function saveGroup() {
    if (busy.value) {
        return;
    }

    groupError.value = '';

    if (!groupForm.name.trim()) {
        groupError.value = '请输入用户组名称';

        return;
    }

    busy.value = true;

    try {
        await apiRequest(
            groupEndpoint + (groupId.value ? `/${groupId.value}` : ''),
            {
                method: groupId.value ? 'PUT' : 'POST',
                body: JSON.stringify(groupForm),
            },
        );
        groupOpen.value = false;
        toast.success('用户组已保存');
        await Promise.all([load(), loadGroups()]);
    } catch (e) {
        groupError.value = getErrorMessage(e);
    } finally {
        busy.value = false;
    }
}
const actionOpen = ref(false),
    actionIds = ref<(string | number)[]>([]),
    action = ref('delete'),
    actionGroup = ref(false),
    actionError = ref('');
const actionLabel = computed(() =>
    action.value === 'delete'
        ? '删除'
        : action.value === 'enable'
          ? '启用'
          : '禁用',
);
function askAction(kind: string, ids = selected.value) {
    action.value = kind;
    actionIds.value = [...ids];
    actionGroup.value = tab.value === 'groups';
    actionError.value = '';
    actionOpen.value = true;
}
async function runAction() {
    if (busy.value) {
        return;
    }

    busy.value = true;
    actionError.value = '';
    const failed: (string | number)[] = [];

    for (const id of actionIds.value) {
        try {
            await apiRequest(
                `${actionGroup.value ? groupEndpoint : endpoint}/${id}`,
                {
                    method: action.value === 'delete' ? 'DELETE' : 'PUT',
                    ...(action.value === 'delete'
                        ? {}
                        : {
                              body: JSON.stringify({
                                  enable: action.value === 'enable' ? 1 : 0,
                              }),
                          }),
                },
            );
        } catch (e) {
            failed.push(id);
            actionError.value = getErrorMessage(e);
        }
    }

    await load();

    if (actionGroup.value) {
        await loadGroups();
    }

    actionIds.value = failed;
    selected.value = failed;
    actionOpen.value = failed.length > 0;

    if (!failed.length) {
        toast.success('操作成功');
    }

    busy.value = false;
}
const switching = ref<string | null>(null);
async function switchUser(row: CdnflyRecord) {
    if (switching.value) {
        return;
    }

    const target = window.open('about:blank', '_blank');

    if (!target) {
        error.value = '请允许弹出窗口后重试';

        return;
    }

    target.opener = null;
    switching.value = String(row.id);

    try {
        const result = await apiRequest<{ url: string }>(
            `${endpoint}/${row.id}/switch`,
            { method: 'POST' },
        );
        const url = new URL(result.url);

        if (!['https:', 'http:'].includes(url.protocol)) {
            throw new Error('用户控制台地址无效');
        }

        target.location.href = url.href;
    } catch (e) {
        target.close();
        error.value = getErrorMessage(e);
    } finally {
        switching.value = null;
    }
}
onUnmounted(() => {
    ++token;
    ++editorToken;
    ++groupToken;
    clearTimeout(timer);
});
</script>

<template>
    <div class="master-users-workspace min-w-0 p-4 md:p-6">
        <section
            class="console-panel min-w-0 rounded-xl border bg-card p-4 text-card-foreground md:p-5"
        >
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <ConsoleTabs
                    v-model="tab"
                    :tabs="[
                        { key: 'users', label: '用户列表' },
                        { key: 'groups', label: '用户分组' },
                    ]"
                /><Button variant="ghost" size="sm" @click="localOpen = true"
                    >本地账号管理</Button
                >
            </div>
            <div v-if="tab === 'users'" class="space-y-3">
                <div class="flex flex-wrap justify-between gap-3">
                    <div class="flex gap-2">
                        <Button :disabled="busy" @click="openEditor()"
                            ><Plus class="mr-1 size-4" />新增用户</Button
                        ><DropdownMenu
                            ><DropdownMenuTrigger as-child
                                ><Button
                                    variant="outline"
                                    :disabled="busy || loading"
                                    >更多操作<ChevronDown
                                        class="ml-2 size-4" /></Button></DropdownMenuTrigger
                            ><DropdownMenuContent
                                ><DropdownMenuItem
                                    :disabled="!selected.length"
                                    @select="askAction('enable')"
                                    >启用</DropdownMenuItem
                                ><DropdownMenuItem
                                    :disabled="!selected.length"
                                    @select="askAction('disable')"
                                    >禁用</DropdownMenuItem
                                ><DropdownMenuItem
                                    :disabled="!selected.length"
                                    class="text-destructive"
                                    @select="askAction('delete')"
                                    >删除</DropdownMenuItem
                                ></DropdownMenuContent
                            ></DropdownMenu
                        >
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Select v-model="filters.cert_verified" :disabled="busy"
                            ><SelectTrigger class="w-44" aria-label="实名状态"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem value="all"
                                    >所有实名状态</SelectItem
                                ><SelectItem value="1">已实名</SelectItem
                                ><SelectItem value="0"
                                    >未实名</SelectItem
                                ></SelectContent
                            ></Select
                        ><Select v-model="filters.user_group" :disabled="busy"
                            ><SelectTrigger class="w-44" aria-label="用户组筛选"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem value="all">所有用户组</SelectItem
                                ><SelectItem
                                    v-for="group in groups"
                                    :key="String(group.id)"
                                    :value="String(group.id)"
                                    >{{ group.name }}</SelectItem
                                ></SelectContent
                            ></Select
                        ><Button
                            variant="outline"
                            :disabled="loading || busy"
                            @click="
                                load();
                                loadGroups();
                            "
                            ><RefreshCw class="mr-1 size-4" />刷新</Button
                        >
                    </div>
                </div>
                <form
                    class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
                    @submit.prevent="search"
                >
                    <div
                        v-for="[key, label] in searchFields"
                        :key="key"
                        class="flex min-w-0"
                    >
                        <Label
                            :for="`user-filter-${key}`"
                            class="shrink-0 rounded-l-md border border-r-0 bg-muted/40 px-2"
                            >{{ label }}</Label
                        ><Input
                            :id="`user-filter-${key}`"
                            v-model="filters[key]"
                            :disabled="busy"
                            :placeholder="`请输入${label}`"
                            class="min-w-0 rounded-l-none"
                        />
                    </div>
                </form>
            </div>
            <div
                v-else
                class="flex flex-wrap items-center justify-between gap-3"
            >
                <div class="flex gap-2">
                    <Button :disabled="busy" @click="openGroup()"
                        ><Plus class="mr-1 size-4" />新增用户组</Button
                    ><Button
                        variant="outline"
                        :disabled="loading || busy"
                        @click="
                            load();
                            loadGroups();
                        "
                        ><RefreshCw class="mr-1 size-4" />刷新</Button
                    ><Button
                        variant="outline"
                        :disabled="!selected.length || busy"
                        @click="askAction('delete')"
                        ><Trash2 class="mr-1 size-4" />删除</Button
                    >
                </div>
                <Badge variant="outline">共 {{ total }} 个用户组</Badge>
            </div>
            <Alert
                v-if="error || groupsError"
                variant="destructive"
                class="mt-4"
                ><AlertDescription
                    >{{ error || groupsError
                    }}<Button
                        variant="outline"
                        size="sm"
                        class="ml-2"
                        @click="
                            load();
                            loadGroups();
                        "
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <div
                :class="tab === 'users' ? 'users-table' : 'groups-table'"
                class="mt-4 min-w-0"
            >
                <ConsoleDataTable
                    v-model:selected="selected"
                    embedded
                    selectable
                    :selection-disabled="busy || loading"
                    :title="tab === 'users' ? '用户列表' : '用户分组'"
                    :columns="columns"
                    :data="{ rows, total, page, pageSize: size, loading }"
                    :empty-text="
                        error
                            ? '加载失败'
                            : tab === 'groups'
                              ? '暂无用户组，可点击新增用户组'
                              : '暂无用户'
                    "
                >
                    <template #actions-col
                        ><col style="width: 130px"
                    /></template>
                    <template #cell-identity="{ row }"
                        ><p
                            data-typography="body"
                            class="max-w-60 truncate font-semibold"
                            :title="text(row.email)"
                        >
                            {{
                                text(
                                    row.email,
                                    text(row.name, '未填写用户信息'),
                                )
                            }}
                        </p>
                        <p
                            data-typography="helper"
                            class="mt-1 text-muted-foreground"
                        >
                            ID {{ row.id }}
                            <span class="ml-2">{{
                                text(row.name, '未填写用户名')
                            }}</span>
                        </p></template
                    >
                    <template #cell-contact="{ row }"
                        ><p
                            data-typography="body"
                            :class="!row.phone ? 'text-muted-foreground' : ''"
                        >
                            {{ text(row.phone, '未填写手机号') }}
                        </p>
                        <p
                            data-typography="helper"
                            class="mt-1 text-muted-foreground"
                        >
                            {{ text(row.qq, '未填写QQ') }}
                        </p></template
                    >
                    <template #cell-des="{ row }"
                        ><span
                            class="block max-w-64 truncate text-muted-foreground"
                            :title="text(row.des, '未填写备注')"
                            >{{ text(row.des, '未填写备注') }}</span
                        ></template
                    >
                    <template #cell-balance="{ row }"
                        ><span class="font-semibold">{{
                            balance(row.balance)
                        }}</span></template
                    >
                    <template #cell-cert_verified="{ row }"
                        ><Badge
                            :variant="certified(row) ? 'secondary' : 'outline'"
                            class="rounded-full"
                            >{{ certified(row) ? '已实名' : '未实名' }}</Badge
                        ></template
                    >
                    <template #cell-enable="{ row }"
                        ><Badge
                            :variant="
                                enabled(row.enable)
                                    ? 'secondary'
                                    : 'destructive'
                            "
                            class="rounded-full"
                            >{{ enabled(row.enable) ? '启用' : '禁用' }}</Badge
                        ></template
                    >
                    <template #cell-type="{ row }"
                        ><Badge variant="secondary" class="rounded-full">{{
                            Number(row.type) === 1 ? '管理员' : '普通用户'
                        }}</Badge></template
                    >
                    <template #cell-name="{ row }"
                        ><Button
                            variant="link"
                            class="px-0"
                            :disabled="busy"
                            @click="openGroup(row)"
                            >{{ row.name }}</Button
                        ></template
                    >
                    <template #row-actions="{ row }"
                        ><template v-if="tab === 'users'"
                            ><Button
                                size="sm"
                                variant="link"
                                :disabled="
                                    busy ||
                                    !!switching ||
                                    !enabled(row.enable) ||
                                    Number(row.type) === 1
                                "
                                @click="switchUser(row)"
                                >{{
                                    switching === String(row.id)
                                        ? '切换中'
                                        : '切换'
                                }}</Button
                            ><Button
                                size="sm"
                                variant="link"
                                :disabled="busy"
                                @click="openEditor(row)"
                                >编辑</Button
                            ></template
                        ><template v-else
                            ><Button
                                size="sm"
                                variant="link"
                                :disabled="busy"
                                @click="openGroup(row)"
                                >编辑</Button
                            ><Button
                                size="sm"
                                variant="link"
                                :disabled="busy"
                                @click="askAction('delete', [String(row.id)])"
                                >删除</Button
                            ></template
                        ></template
                    >
                </ConsoleDataTable>
            </div>
            <div class="flex flex-wrap items-center justify-between">
                <span
                    v-if="tab === 'groups'"
                    class="text-sm text-muted-foreground"
                    >当前页 {{ rows.length }} 条</span
                ><PackagePagination
                    v-model:page="page"
                    v-model:page-size="size"
                    class="ml-auto"
                    :total="total"
                    :disabled="busy || loading || !!error"
                    numbered
                    edge-links
                />
            </div>
        </section>
        <Dialog :open="editorOpen" @update:open="closeEditor"
            ><DialogScrollContent
                class="max-h-[95dvh] max-w-[940px] grid-rows-[auto_minmax(0,1fr)_auto] gap-0 overflow-hidden p-0"
                ><DialogHeader class="border-b px-5 py-4"
                    ><DialogTitle>{{
                        editId ? '编辑用户' : '新增用户'
                    }}</DialogTitle
                    ><DialogDescription class="sr-only"
                        >用户基础资料、实名信息和登录安全设置</DialogDescription
                    ></DialogHeader
                >
                <div class="min-h-0 overflow-y-auto p-5">
                    <ConsoleTabs
                        v-model="editorTab"
                        :tabs="[
                            { key: 'basic', label: '基础信息' },
                            { key: 'identity', label: '实名信息' },
                            { key: 'security', label: '登录安全' },
                        ]"
                    />
                    <Alert v-if="formError" variant="destructive" class="mt-4"
                        ><AlertDescription
                            >{{ formError
                            }}<Button
                                v-if="editId && !form.email"
                                variant="outline"
                                size="sm"
                                @click="openEditor({ id: editId })"
                                >重试</Button
                            ></AlertDescription
                        ></Alert
                    >
                    <div v-if="editorLoading" class="flex justify-center p-12">
                        <Spinner />
                    </div>
                    <form
                        v-else
                        id="master-user-form"
                        class="user-form mt-5 space-y-5"
                        @submit.prevent="saveUser"
                    >
                        <fieldset :disabled="busy" class="space-y-5">
                            <template v-if="editorTab === 'basic'"
                                ><div
                                    v-for="[
                                        key,
                                        label,
                                        kind,
                                        placeholder,
                                    ] in basicFields"
                                    :key="key"
                                    class="user-field"
                                >
                                    <Label :for="`user-${key}`"
                                        >{{ label }}：</Label
                                    ><Textarea
                                        v-if="kind === 'textarea'"
                                        :id="`user-${key}`"
                                        v-model="form[key]"
                                        :placeholder="placeholder"
                                    /><Input
                                        v-else
                                        :id="`user-${key}`"
                                        v-model="form[key]"
                                        :type="kind"
                                        :placeholder="
                                            key === 'password' && editId
                                                ? '留空则不修改密码'
                                                : placeholder
                                        "
                                        :autocomplete="
                                            key === 'password'
                                                ? 'new-password'
                                                : 'off'
                                        "
                                    />
                                </div>
                                <div class="user-field">
                                    <Label>用户分组：</Label
                                    ><Select v-model="form.user_group"
                                        ><SelectTrigger
                                            class="w-full"
                                            aria-label="用户分组"
                                            ><SelectValue /></SelectTrigger
                                        ><SelectContent
                                            ><SelectItem value="none"
                                                >不分组</SelectItem
                                            ><SelectItem
                                                v-for="group in groups"
                                                :key="String(group.id)"
                                                :value="String(group.id)"
                                                >{{ group.name }}</SelectItem
                                            ><SelectItem
                                                v-if="
                                                    form.user_group !==
                                                        'none' &&
                                                    !groups.some(
                                                        (g) =>
                                                            String(g.id) ===
                                                            form.user_group,
                                                    )
                                                "
                                                :value="form.user_group"
                                                >分组 #{{
                                                    form.user_group
                                                }}</SelectItem
                                            ></SelectContent
                                        ></Select
                                    >
                                </div>
                                <div v-if="!editId" class="user-field">
                                    <Label>用户类型：</Label>
                                    <div
                                        class="flex gap-2"
                                        role="group"
                                        aria-label="用户类型"
                                    >
                                        <Button
                                            v-for="[value, label] in [
                                                ['2', '普通用户'],
                                                ['1', '管理员'],
                                            ]"
                                            :key="value"
                                            type="button"
                                            :variant="
                                                form.type === value
                                                    ? 'secondary'
                                                    : 'outline'
                                            "
                                            :aria-pressed="form.type === value"
                                            @click="form.type = value"
                                            >{{ label }}</Button
                                        >
                                    </div>
                                </div>
                                <div class="user-field">
                                    <Label for="user-enable">启用：</Label
                                    ><Switch
                                        id="user-enable"
                                        v-model:checked="form.enable"
                                    />
                                </div>
                            </template>
                            <template v-else-if="editorTab === 'identity'"
                                ><div
                                    v-for="[
                                        key,
                                        label,
                                        placeholder,
                                    ] in identityFields"
                                    :key="key"
                                    class="user-field"
                                >
                                    <Label :for="`user-${key}`"
                                        >{{ label }}：</Label
                                    ><Input
                                        :id="`user-${key}`"
                                        v-model="form[key]"
                                        :placeholder="placeholder"
                                        :disabled="
                                            !editId &&
                                            key.startsWith('company_')
                                        "
                                    />
                                </div>
                                <div class="user-field">
                                    <Label for="user-cert">个人实名：</Label
                                    ><Switch
                                        id="user-cert"
                                        v-model:checked="form.cert_verified"
                                    />
                                </div>
                                <div class="user-field">
                                    <Label for="user-company">企业实名：</Label
                                    ><Switch
                                        id="user-company"
                                        v-model:checked="form.company_verified"
                                        :disabled="!editId"
                                    />
                                </div>
                                <p
                                    data-typography="body"
                                    v-if="!editId"
                                    class="text-muted-foreground"
                                >
                                    企业信息请在创建用户后编辑保存。
                                </p>
                                <div class="border-t pt-4 font-medium">
                                    二次实名
                                </div>
                                <div class="user-field">
                                    <Label for="user-auth2">开关：</Label>
                                    <div>
                                        <Switch
                                            id="user-auth2"
                                            v-model:checked="form.auth2_enable"
                                        />
                                        <p
                                            data-typography="body"
                                            class="mt-2 text-muted-foreground"
                                        >
                                            开启后，用户必须使用首次实名认证的身份证进行二次实名认证；系统将发送邮件和短信通知。
                                        </p>
                                    </div>
                                </div>
                                <div class="user-field">
                                    <Label for="user-auth2-end"
                                        >截止时间：</Label
                                    ><DatePicker
                                        id="user-auth2-end"
                                        v-model="form.auth2_end_at"
                                        type="datetime-local"
                                        step="1"
                                    />
                                </div>
                                <div class="user-field">
                                    <Label>认证过期：</Label>
                                    <div
                                        class="flex gap-2"
                                        role="group"
                                        aria-label="认证过期"
                                    >
                                        <Button
                                            v-for="[value, label] in [
                                                ['none', '不处理'],
                                                ['lock', '锁定网站'],
                                            ]"
                                            :key="value"
                                            type="button"
                                            :variant="
                                                form.auth2_expire_action ===
                                                value
                                                    ? 'secondary'
                                                    : 'outline'
                                            "
                                            :aria-pressed="
                                                form.auth2_expire_action ===
                                                value
                                            "
                                            @click="
                                                form.auth2_expire_action = value
                                            "
                                            >{{ label }}</Button
                                        >
                                    </div>
                                </div>
                                <div class="user-field">
                                    <Label for="user-auth2-verified"
                                        >认证状态：</Label
                                    ><Switch
                                        id="user-auth2-verified"
                                        v-model:checked="form.auth2_verified"
                                    />
                                </div>
                            </template>
                            <template v-else
                                ><div class="user-field">
                                    <Label>登录验证码：</Label>
                                    <div
                                        class="flex gap-2"
                                        role="group"
                                        aria-label="登录验证码"
                                    >
                                        <Button
                                            v-for="[value, label] in [
                                                ['none', '无'],
                                                ['sms', '短信'],
                                                ['email', '邮件'],
                                            ]"
                                            :key="value"
                                            type="button"
                                            :variant="
                                                form.login_captcha === value
                                                    ? 'secondary'
                                                    : 'outline'
                                            "
                                            :aria-pressed="
                                                form.login_captcha === value
                                            "
                                            @click="form.login_captcha = value"
                                            >{{ label }}</Button
                                        >
                                    </div>
                                </div>
                                <div class="user-field">
                                    <Label for="user-white-ip"
                                        >登录白名单：</Label
                                    ><Textarea
                                        id="user-white-ip"
                                        v-model="form.white_ip"
                                        placeholder="多个IP空格分隔"
                                    /></div
                            ></template>
                        </fieldset>
                    </form>
                </div>
                <DialogFooter class="border-t px-5 py-4"
                    ><Button
                        type="submit"
                        form="master-user-form"
                        :disabled="
                            busy || editorLoading || (!!editId && !form.email)
                        "
                        ><Spinner v-if="busy" class="mr-1" />确定</Button
                    ><Button
                        variant="outline"
                        :disabled="busy"
                        @click="closeEditor(false)"
                        >关闭</Button
                    ></DialogFooter
                >
            </DialogScrollContent></Dialog
        >
        <Dialog
            :open="groupOpen"
            @update:open="
                (v) => {
                    if (!busy) groupOpen = v;
                }
            "
            ><DialogScrollContent class="max-w-xl"
                ><DialogHeader
                    ><DialogTitle>{{
                        groupId ? '编辑用户组' : '新增用户组'
                    }}</DialogTitle
                    ><DialogDescription class="sr-only"
                        >设置用户组名称和备注</DialogDescription
                    ></DialogHeader
                ><Alert v-if="groupError" variant="destructive"
                    ><AlertDescription>{{
                        groupError
                    }}</AlertDescription></Alert
                >
                <form
                    id="user-group-form"
                    class="space-y-5"
                    @submit.prevent="saveGroup"
                >
                    <div class="user-field">
                        <Label for="user-group-name">名称：</Label
                        ><Input
                            id="user-group-name"
                            v-model="groupForm.name"
                            :disabled="busy"
                            placeholder="请输入用户组名称"
                        />
                    </div>
                    <div class="user-field">
                        <Label for="user-group-des">备注：</Label
                        ><Textarea
                            id="user-group-des"
                            v-model="groupForm.des"
                            :disabled="busy"
                            placeholder="请输入备注"
                        />
                    </div>
                </form>
                <DialogFooter
                    ><Button
                        type="submit"
                        form="user-group-form"
                        :disabled="busy"
                        ><Spinner v-if="busy" class="mr-1" />确定</Button
                    ><Button
                        variant="outline"
                        :disabled="busy"
                        @click="groupOpen = false"
                        >取消</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <Dialog
            :open="actionOpen"
            @update:open="
                (v) => {
                    if (!busy) actionOpen = v;
                }
            "
            ><DialogScrollContent
                ><DialogHeader
                    ><DialogTitle>{{ actionLabel }}确认</DialogTitle
                    ><DialogDescription
                        >是否{{ actionLabel }}选中的 {{ actionIds.length }} 个{{
                            actionGroup ? '用户组' : '用户'
                        }}？</DialogDescription
                    ></DialogHeader
                ><Alert v-if="actionError" variant="destructive"
                    ><AlertDescription>{{
                        actionError
                    }}</AlertDescription></Alert
                ><DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="busy"
                        @click="actionOpen = false"
                        >取消</Button
                    ><Button
                        :variant="
                            action === 'delete' ? 'destructive' : 'default'
                        "
                        :disabled="busy"
                        @click="runAction"
                        ><Spinner v-if="busy" class="mr-1" />确认{{
                            actionLabel
                        }}</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <Dialog v-model:open="localOpen"
            ><DialogScrollContent
                class="max-h-[95dvh] max-w-[1400px] overflow-auto"
                ><DialogHeader
                    ><DialogTitle>本地账号管理</DialogTitle
                    ><DialogDescription
                        >管理门户登录账号及其 CDN 用户关联。</DialogDescription
                    ></DialogHeader
                ><AdminUsers /></DialogScrollContent
        ></Dialog>
    </div>
</template>
<style scoped>
.users-table :deep(table) {
    min-width: 1480px;
}
.groups-table :deep(table) {
    min-width: 740px;
}
.user-form {
    max-width: 690px;
}
.user-field {
    display: grid;
    grid-template-columns: 120px minmax(0, 1fr);
    align-items: center;
    gap: 16px;
}
.user-field > label {
    justify-content: flex-end;
    text-align: right;
}
@media (max-width: 600px) {
    .user-field {
        grid-template-columns: minmax(0, 1fr);
        gap: 8px;
    }
    .user-field > label {
        justify-content: flex-start;
        text-align: left;
    }
}
:deep([data-slot='textarea']) {
    width: 100%;
    min-height: 72px;
    border: 1px solid var(--input);
    border-radius: var(--radius);
    padding: 8px 12px;
    background: transparent;
    font-size: var(--console-text-body);
    resize: vertical;
}
:deep([data-slot='textarea']):focus-visible {
    outline: 2px solid var(--ring);
}
:deep([data-slot='textarea'])::placeholder {
    color: var(--muted-foreground);
}
</style>
