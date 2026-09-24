<script setup lang="ts">
import { RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
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
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { apiRequest } from '@/lib/apiRequest';
import {
    extractCdnflyRows,
    extractCdnflyTotal,
    inclusiveUsageEnd,
    isCdnflyRecord,
} from '@/lib/cdnflyResponse';
import { getErrorMessage } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';

type Tab = 'login' | 'op' | 'backup' | 'msg-send';
const tabs = [
    { key: 'login', label: '登录日志' },
    { key: 'op', label: '操作日志' },
    { key: 'backup', label: '备份日志' },
    { key: 'msg-send', label: '发信日志' },
];
const tab = ref('login'),
    page = ref(1),
    size = ref(10),
    rows = ref<CdnflyRecord[]>([]),
    total = ref(0),
    loading = ref(false),
    error = ref('');
const filters = reactive<Record<string, Record<string, string>>>({
    login: { success: 'all', start: '', end: '', uid: '', ip: '' },
    op: {
        action: 'all',
        start: '',
        end: '',
        uid: '',
        ip: '',
        type: '',
        content: '',
        diff: '',
    },
    backup: {},
    'msg-send': {
        msg_type: 'all',
        media: 'all',
        state: 'all',
        uid: '',
        msg_id: '',
    },
});
const active = computed(() => filters[tab.value]);
const currentTitle = computed(
    () => tabs.find((t) => t.key === tab.value)?.label ?? '日志',
);
const fields: Record<string, string[][]> = {
    login: [
        ['uid', '用户ID'],
        ['ip', 'IP地址'],
    ],
    op: [
        ['uid', '用户ID'],
        ['ip', 'IP地址'],
        ['type', '类别'],
        ['content', '对象'],
        ['diff', '变更'],
    ],
    backup: [],
    'msg-send': [
        ['uid', '用户ID'],
        ['msg_id', '消息ID'],
    ],
};
const messageTypes = [
    ['package-expiring', '套餐即将到期'],
    ['package-expire', '套餐已到期'],
    ['traffic-exceeding', '流量即将用尽'],
    ['traffic-exceed', '流量已用尽'],
    ['cc-switch', 'CC规则组自动切换'],
    ['bandwidth-exceed', '套餐带宽超限'],
    ['connection-exceed', '套餐连接数超限'],
    ['cert-expire', '证书已过期'],
    ['cert-expiring', '证书即将过期'],
    ['account-auth2', '二次实名'],
    ['notice', '系统通知'],
];
type FilterSelect = { key: string; label: string; options: string[][] };
const selects: Record<string, FilterSelect[]> = {
    login: [
        {
            key: 'success',
            label: '所有状态',
            options: [
                ['1', '成功'],
                ['0', '失败'],
            ],
        },
    ],
    op: [
        {
            key: 'action',
            label: '所有动作',
            options: [
                ['新增', '新增'],
                ['更新', '更新'],
                ['删除', '删除'],
            ],
        },
    ],
    backup: [],
    'msg-send': [
        { key: 'msg_type', label: '所有类型', options: messageTypes },
        {
            key: 'media',
            label: '所有媒介',
            options: [
                ['email', '电子邮件'],
                ['phone', '手机短信'],
            ],
        },
        {
            key: 'state',
            label: '所有状态',
            options: [
                ['done', '成功'],
                ['failed', '失败'],
                ['pending', '待发送'],
                ['process', '发送中'],
                ['success', '发送成功'],
            ],
        },
    ],
};
const columnsByTab: Record<Tab, ColumnDef[]> = {
    login: [
        { key: 'uid', label: '用户ID', width: '100px' },
        { key: 'ip', label: 'IP地址', width: '185px' },
        { key: 'ip_location', label: '地理位置', width: '210px' },
        { key: 'ua', label: '浏览器UA', width: '230px' },
        { key: 'time', label: '登录时间', width: '185px' },
        { key: 'success', label: '登录状态', width: '110px' },
    ],
    op: [
        { key: 'uid', label: '用户ID', width: '100px' },
        { key: 'object', label: '类别/对象', width: '210px' },
        { key: 'action', label: '动作', width: '100px' },
        { key: 'diff', label: '变更内容', width: '380px' },
        { key: 'source', label: 'IP/位置', width: '205px' },
        { key: 'time', label: '操作时间', width: '185px' },
    ],
    backup: [
        { key: 'time', label: '备份时间', width: '240px' },
        { key: 'end_at', label: '完成时间', width: '240px' },
        { key: 'state', label: '状态', width: '150px' },
        { key: 'ret', label: '结果' },
    ],
    'msg-send': [
        { key: 'recipient', label: '收件人', width: '150px' },
        { key: 'msg_id', label: '消息ID', width: '110px' },
        { key: 'message', label: '标题/内容', width: '280px' },
        { key: 'media', label: '媒介', width: '115px' },
        { key: 'failed_times', label: '失败次数', width: '100px' },
        { key: 'state', label: '状态', width: '110px' },
        { key: 'ret', label: '原因', width: '240px' },
        { key: 'time', label: '发送时间', width: '185px' },
    ],
};
const columns = computed(() => columnsByTab[tab.value as Tab]);
const absent = (v: unknown) =>
    v === null || v === undefined || String(v).trim() === '';
const text = (v: unknown, fallback = '—') => (absent(v) ? fallback : String(v));
function raw(value: unknown): string {
    if (absent(value)) {
        return '—';
    }

    return typeof value === 'string' ? value : JSON.stringify(value, null, 2);
}
function pretty(value: unknown) {
    if (typeof value === 'string') {
        try {
            return JSON.stringify(JSON.parse(value), null, 2);
        } catch {
            return value;
        }
    }

    return raw(value);
}
const first = (row: CdnflyRecord, keys: string[], fallback = '—') =>
    text(
        keys.map((k) => row[k]).find((v) => !absent(v)),
        fallback,
    );
const time = (row: CdnflyRecord) =>
    first(row, ['create_at2', 'create_at', 'created_at']);
const success = (v: unknown) => v === true || v === 1 || v === '1';
const status = (row: CdnflyRecord) =>
    ({
        done: tab.value === 'backup' ? '已完成' : '成功',
        success: '成功',
        failed: '失败',
        pending: tab.value === 'backup' ? '待执行' : '待发送',
        process: tab.value === 'backup' ? '执行中' : '发送中',
    })[text(row.state)] ?? text(row.state, '未知状态');
const fieldNames: Record<string, string> = {
    value: '值',
    enable: '启用',
    name: '名称',
    des: '备注',
    domain: '域名',
    port: '端口',
    user_group: '用户分组',
    backend: '源站',
    balance: '余额',
};
function changes(
    value: unknown,
): { field: string; old: string; next: string }[] {
    let parsed = value;

    if (typeof parsed === 'string') {
        try {
            parsed = JSON.parse(parsed);
        } catch {
            return [];
        }
    }

    const entries = Array.isArray(parsed)
        ? parsed
        : isCdnflyRecord(parsed)
          ? 'field' in parsed || 'old' in parsed || 'new' in parsed
              ? [parsed]
              : Object.values(parsed)
          : [];

    return entries
        .filter(isCdnflyRecord)
        .filter((v) => 'old' in v || 'new' in v)
        .map((v) => ({
            field: fieldNames[text(v.field)] ?? text(v.field, '字段'),
            old: pretty(v.old),
            next: pretty(v.new),
        }));
}
function hasDiff(row: CdnflyRecord) {
    return (
        !absent(row.diff) &&
        !['[]', '{}', 'null'].includes(raw(row.diff).trim())
    );
}
let token = 0,
    timer: ReturnType<typeof setTimeout> | undefined;
async function load() {
    clearTimeout(timer);
    const current = ++token;
    loading.value = true;
    error.value = '';
    rows.value = [];

    try {
        const query = new URLSearchParams({
            page: String(page.value),
            limit: String(size.value),
        });

        for (const [key, value] of Object.entries(active.value)) {
            if (!value.trim() || value === 'all') {
                continue;
            }

            if (
                ['uid', 'msg_id'].includes(key) &&
                !/^\d+$/.test(value.trim())
            ) {
                throw new Error(
                    `${key === 'uid' ? '用户ID' : '消息ID'}请填写数字`,
                );
            }

            query.set(
                key,
                key === 'end'
                    ? inclusiveUsageEnd(value)
                    : key === 'start'
                      ? value.slice(0, 10)
                      : value.trim(),
            );
        }

        const result = await apiRequest(
            `/api/admin/logs/${tab.value}?${query}`,
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
function search() {
    clearTimeout(timer);

    if (page.value === 1) {
        void load();
    } else {
        page.value = 1;
    }
}
watch([tab, size], search);
watch(page, load);
watch(
    () => [tab.value, ...fields[tab.value].map(([key]) => active.value[key])],
    (next, previous) => {
        if (next[0] !== previous[0]) {
            return;
        }

        ++token;
        rows.value = [];
        loading.value = true;
        clearTimeout(timer);
        timer = setTimeout(search, 300);
    },
);
watch(
    () => [
        tab.value,
        active.value.start,
        active.value.end,
        ...selects[tab.value].map((s) => active.value[s.key]),
    ],
    (next, previous) => {
        if (next[0] === previous[0]) {
            search();
        }
    },
);
onMounted(load);
const detailOpen = ref(false),
    detailTitle = ref('变更详情'),
    detailRow = ref<CdnflyRecord>({}),
    detailValue = ref<unknown>('');
const detailChanges = computed(() =>
    detailTitle.value === '变更详情' ? changes(detailValue.value) : [],
);
function showDiff(row: CdnflyRecord) {
    detailTitle.value = '变更详情';
    detailRow.value = row;
    detailValue.value = row.diff;
    detailOpen.value = true;
}
function showResult(row: CdnflyRecord) {
    detailTitle.value = tab.value === 'backup' ? '备份结果' : '发送结果';
    detailRow.value = row;
    detailValue.value = row.ret;
    detailOpen.value = true;
}
onUnmounted(() => {
    ++token;
    clearTimeout(timer);
});
</script>

<template>
    <div class="monitoring-workspace min-w-0 p-4 md:p-6">
        <section
            class="console-panel min-w-0 rounded-xl border bg-card p-4 text-card-foreground md:p-5"
        >
            <ConsoleTabs v-model="tab" :tabs="tabs" class="mb-5" />
            <div
                v-if="tab === 'backup' || tab === 'msg-send'"
                class="mb-4 flex items-center justify-between gap-3"
            >
                <div>
                    <h2 class="font-semibold">{{ currentTitle }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        共 {{ total }} 条记录<span v-if="tab === 'backup'"
                            >，当前页 {{ rows.length }} 条</span
                        >
                    </p>
                </div>
                <Button
                    v-if="tab === 'backup'"
                    variant="outline"
                    :disabled="loading"
                    @click="load"
                    ><RefreshCw class="mr-1 size-4" />刷新</Button
                >
            </div>
            <form
                v-if="tab !== 'backup'"
                class="mb-4 flex flex-wrap items-center gap-2"
                :class="
                    tab === 'op'
                        ? 'rounded-lg border bg-muted/10 p-3'
                        : tab === 'msg-send'
                          ? 'justify-end'
                          : ''
                "
                @submit.prevent="search"
            >
                <Select
                    v-for="select in selects[tab]"
                    :key="select.key"
                    v-model="active[select.key]"
                    ><SelectTrigger
                        class="w-full sm:w-44"
                        :aria-label="select.label"
                        ><SelectValue /></SelectTrigger
                    ><SelectContent
                        ><SelectItem value="all">{{ select.label }}</SelectItem
                        ><SelectItem
                            v-for="[value, label] in select.options"
                            :key="value"
                            :value="value"
                            >{{ label }}</SelectItem
                        ></SelectContent
                    ></Select
                >
                <DateRangePicker
                    v-if="tab === 'login' || tab === 'op'"
                    :key="tab"
                    v-model:start="active.start"
                    v-model:end="active.end"
                    placeholder="请选择时间范围"
                    trigger-class="w-full sm:w-64"
                />
                <div
                    v-for="[key, label] in fields[tab]"
                    :key="key"
                    class="flex w-full min-w-0 sm:w-52"
                >
                    <Label
                        :for="`log-${key}`"
                        class="shrink-0 rounded-l-md border border-r-0 bg-muted/40 px-2"
                        >{{ label }}</Label
                    ><Input
                        :id="`log-${key}`"
                        v-model="active[key]"
                        :placeholder="`请输入${label === '变更' ? '变更内容' : label}`"
                        class="min-w-0 rounded-l-none"
                    />
                </div>
                <Button
                    v-if="tab === 'msg-send'"
                    variant="outline"
                    :disabled="loading"
                    @click="load"
                    ><RefreshCw class="mr-1 size-4" />刷新</Button
                >
            </form>
            <Alert v-if="error" variant="destructive" class="mb-4"
                ><AlertDescription
                    >{{ error
                    }}<Button
                        size="sm"
                        variant="outline"
                        class="ml-2"
                        @click="load"
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <div class="logs-table min-w-0" :class="`logs-${tab}`">
                <ConsoleDataTable
                    embedded
                    :show-actions="false"
                    :title="currentTitle"
                    :columns="columns"
                    :data="{ rows, total, page, pageSize: size, loading }"
                    :empty-text="error ? '日志加载失败' : '暂无数据'"
                >
                    <template #cell-uid="{ row }"
                        ><span class="font-semibold">{{
                            text(row.uid)
                        }}</span></template
                    >
                    <template #cell-ip="{ row }"
                        ><span class="font-mono text-xs">{{
                            text(row.ip)
                        }}</span></template
                    >
                    <template #cell-ip_location="{ row }"
                        ><span
                            class="block max-w-52 truncate"
                            :title="text(row.ip_location, '未记录')"
                            >{{ text(row.ip_location, '未记录') }}</span
                        ></template
                    >
                    <template #cell-ua="{ row }"
                        ><span
                            class="block max-w-56 truncate text-muted-foreground"
                            :title="
                                first(
                                    row,
                                    ['ua', 'user_agent', 'userAgent'],
                                    '未记录',
                                )
                            "
                            >{{
                                first(
                                    row,
                                    ['ua', 'user_agent', 'userAgent'],
                                    '未记录',
                                )
                            }}</span
                        ></template
                    >
                    <template #cell-time="{ row }"
                        ><template v-if="tab === 'login'"
                            ><p class="font-semibold">
                                {{ time(row).split(/[T ]/)[0] }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ time(row).split(/[T ]/).slice(1).join(' ') }}
                            </p></template
                        ><span v-else class="whitespace-nowrap">{{
                            time(row)
                        }}</span></template
                    >
                    <template #cell-success="{ row }"
                        ><Badge
                            :variant="
                                absent(row.success)
                                    ? 'outline'
                                    : success(row.success)
                                      ? 'secondary'
                                      : 'destructive'
                            "
                            class="gap-1 rounded-full"
                            ><span class="size-1.5 rounded-full bg-current" />{{
                                absent(row.success)
                                    ? '未知状态'
                                    : success(row.success)
                                      ? '成功'
                                      : '失败'
                            }}</Badge
                        ></template
                    >
                    <template #cell-object="{ row }"
                        ><p class="font-semibold">{{ text(row.type) }}</p>
                        <p
                            class="mt-1 max-w-48 truncate text-xs text-muted-foreground"
                            :title="text(row.content)"
                        >
                            {{ text(row.content) }}
                        </p></template
                    >
                    <template #cell-action="{ row }"
                        ><Badge
                            :variant="
                                row.action === '删除'
                                    ? 'destructive'
                                    : 'secondary'
                            "
                            class="rounded-full"
                            >{{ text(row.action, '未知动作') }}</Badge
                        ></template
                    >
                    <template #cell-diff="{ row }"
                        ><div v-if="hasDiff(row)" class="max-w-80">
                            <template v-if="changes(row.diff).length"
                                ><p
                                    v-for="(change, index) in changes(
                                        row.diff,
                                    ).slice(0, 2)"
                                    :key="index"
                                    class="truncate"
                                    :title="`${change.field} ${change.old} → ${change.next}`"
                                >
                                    <strong class="mr-2">{{
                                        change.field
                                    }}</strong
                                    >{{ change.old }} → {{ change.next }}
                                </p>
                                <p
                                    v-if="changes(row.diff).length > 2"
                                    class="text-xs text-muted-foreground"
                                >
                                    另有
                                    {{ changes(row.diff).length - 2 }} 项变更
                                </p></template
                            >
                            <p v-else class="truncate" :title="raw(row.diff)">
                                {{ raw(row.diff) }}
                            </p>
                            <Button
                                variant="link"
                                size="sm"
                                class="h-auto px-0 py-1"
                                @click="showDiff(row)"
                                >查看详情</Button
                            >
                        </div>
                        <span v-else class="text-muted-foreground"
                            >无变更内容</span
                        ></template
                    >
                    <template #cell-source="{ row }"
                        ><p class="font-semibold">{{ text(row.ip) }}</p>
                        <p
                            class="mt-1 max-w-48 truncate text-xs text-muted-foreground"
                            :title="text(row.ip_location, '未记录')"
                        >
                            {{ text(row.ip_location, '未记录') }}
                        </p></template
                    >
                    <template #cell-end_at="{ row }">{{
                        first(
                            row,
                            ['end_at', 'end_at2'],
                            ['pending', 'process'].includes(text(row.state))
                                ? '未完成'
                                : '未记录',
                        )
                    }}</template>
                    <template #cell-state="{ row }"
                        ><Badge
                            :variant="
                                row.state === 'failed'
                                    ? 'destructive'
                                    : ['done', 'success'].includes(
                                            text(row.state),
                                        )
                                      ? 'secondary'
                                      : 'outline'
                            "
                            class="gap-1 rounded-full"
                            ><span class="size-1.5 rounded-full bg-current" />{{
                                status(row)
                            }}</Badge
                        ></template
                    >
                    <template #cell-ret="{ row }"
                        ><Button
                            v-if="!absent(row.ret)"
                            variant="link"
                            class="block h-auto max-w-64 truncate px-0 text-left"
                            :title="raw(row.ret)"
                            @click="showResult(row)"
                            >{{ raw(row.ret) }}</Button
                        ><span v-else class="text-muted-foreground">{{
                            tab === 'backup' ? '暂无结果' : '—'
                        }}</span></template
                    >
                    <template #cell-recipient="{ row }"
                        ><p class="font-semibold">{{ text(row.uid) }}</p>
                        <p
                            class="mt-1 max-w-36 truncate text-xs text-muted-foreground"
                            :title="
                                first(
                                    row,
                                    [
                                        'recipient',
                                        'receiver',
                                        'receive',
                                        'email',
                                        'phone',
                                        'to',
                                    ],
                                    '',
                                )
                            "
                        >
                            {{
                                first(
                                    row,
                                    [
                                        'recipient',
                                        'receiver',
                                        'receive',
                                        'email',
                                        'phone',
                                        'to',
                                    ],
                                    '',
                                )
                            }}
                        </p></template
                    >
                    <template #cell-message="{ row }"
                        ><p
                            class="max-w-64 truncate font-medium"
                            :title="text(row.title, '无标题')"
                        >
                            {{ text(row.title, '无标题') }}
                        </p>
                        <p
                            class="mt-1 max-w-64 truncate text-xs text-muted-foreground"
                            :title="
                                first(
                                    row,
                                    [
                                        'content',
                                        'phone_content',
                                        'templ_content',
                                        'message',
                                    ],
                                    '',
                                )
                            "
                        >
                            {{
                                first(
                                    row,
                                    [
                                        'content',
                                        'phone_content',
                                        'templ_content',
                                        'message',
                                    ],
                                    '',
                                )
                            }}
                        </p></template
                    >
                    <template #cell-media="{ row }">{{
                        row.media === 'email'
                            ? '电子邮件'
                            : ['phone', 'sms'].includes(text(row.media))
                              ? '手机短信'
                              : text(row.media, '未知媒介')
                    }}</template>
                    <template #cell-failed_times="{ row }"
                        ><span
                            :class="
                                Number(row.failed_times) > 0
                                    ? 'text-destructive'
                                    : ''
                            "
                            >{{ text(row.failed_times) }}</span
                        ></template
                    >
                </ConsoleDataTable>
            </div>
            <PackagePagination
                v-model:page="page"
                v-model:page-size="size"
                :total="total"
                :disabled="loading || !!error"
                numbered
                edge-links
            />
        </section>
        <Dialog v-model:open="detailOpen"
            ><DialogScrollContent
                class="max-h-[90dvh] max-w-4xl grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden"
                ><DialogHeader
                    ><DialogTitle>{{ detailTitle }}</DialogTitle
                    ><DialogDescription
                        >用户 {{ text(detailRow.uid) }} ·
                        {{ time(detailRow) }}</DialogDescription
                    ></DialogHeader
                >
                <div class="min-h-0 overflow-auto">
                    <table
                        v-if="detailChanges.length"
                        class="w-full min-w-[540px] text-sm"
                    >
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="p-3 text-left">字段</th>
                                <th class="p-3 text-left">原值</th>
                                <th class="p-3 text-left">新值</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(change, index) in detailChanges"
                                :key="index"
                                class="border-b align-top"
                            >
                                <td class="p-3">{{ change.field }}</td>
                                <td class="p-3">
                                    <pre
                                        class="max-w-72 break-words whitespace-pre-wrap"
                                        >{{ change.old }}</pre
                                    >
                                </td>
                                <td class="p-3">
                                    <pre
                                        class="max-w-72 break-words whitespace-pre-wrap"
                                        >{{ change.next }}</pre
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <pre
                        v-else
                        class="text-sm break-words whitespace-pre-wrap"
                        >{{ pretty(detailValue) }}</pre
                    >
                </div>
                <DialogFooter
                    ><Button variant="outline" @click="detailOpen = false"
                        >关闭</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
    </div>
</template>
<style scoped>
.logs-login :deep(table) {
    min-width: 1040px;
}
.logs-op :deep(table) {
    min-width: 1180px;
}
.logs-backup :deep(table) {
    min-width: 820px;
}
.logs-msg-send :deep(table) {
    min-width: 1310px;
}
</style>
