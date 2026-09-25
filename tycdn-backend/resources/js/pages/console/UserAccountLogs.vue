<script setup lang="ts">
import { Check, X, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
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
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { Spinner } from '@/components/ui/spinner';
import { inclusiveUsageEnd } from '@/lib/cdnflyResponse';
import { formatDate, getErrorMessage } from '@/lib/cdnRecord';
import {
    extractCdnflyRows,
    extractCdnflyTotal,
    listUserLoginLogs,
    listUserOperationLogs,
} from '@/lib/cdnUserApi';
import type { CdnflyRecord } from '@/lib/cdnUserApi';

type Tab = 'login' | 'op';
const tabs = [
    { key: 'login' as const, label: '登录日志' },
    { key: 'op' as const, label: '操作日志' },
];
const tab = ref<Tab>('login'),
    page = ref(1),
    size = ref(10),
    total = ref(0);
const rows = ref<CdnflyRecord[]>([]),
    loading = ref(false),
    error = ref(''),
    dateKey = ref(0);
const filters = reactive({
    login: { success: 'all', start: '', end: '', ip: '' },
    op: {
        action: 'all',
        start: '',
        end: '',
        ip: '',
        type: '',
        content: '',
        diff: '',
    },
});
const active = computed(() => filters[tab.value]);
const hasFilters = computed(() =>
    Object.values(active.value).some((v) => v !== '' && v !== 'all'),
);
const detailOpen = ref(false),
    detail = ref('');
let token = 0,
    timer: ReturnType<typeof setTimeout> | undefined;
async function load() {
    clearTimeout(timer);
    const id = ++token;
    loading.value = true;
    error.value = '';
    rows.value = [];

    try {
        const params: Record<string, string | number> = {
            page: page.value,
            limit: size.value,
        };

        for (const [key, value] of Object.entries(active.value)) {
            if (
                ['start', 'end'].includes(key) ||
                !value.trim() ||
                value === 'all'
            ) {
                continue;
            }

            params[key] = value.trim();
        }

        if (active.value.start && active.value.end) {
            params.start = active.value.start.slice(0, 10);
            params.end = inclusiveUsageEnd(active.value.end);
        }

        const result = await (tab.value === 'login'
            ? listUserLoginLogs(params)
            : listUserOperationLogs(params));

        if (id !== token) {
            return;
        }

        rows.value = extractCdnflyRows(result);
        total.value = extractCdnflyTotal(result, rows.value.length);
        const last = Math.max(1, Math.ceil(total.value / size.value));

        if (page.value > last) {
            page.value = last;
        }
    } catch (cause) {
        if (id === token) {
            error.value = getErrorMessage(cause);
            total.value = 0;
        }
    } finally {
        if (id === token) {
            loading.value = false;
        }
    }
}
function search() {
    clearTimeout(timer);

    if (page.value !== 1) {
        page.value = 1;
    } else {
        void load();
    }
}
watch(
    () => ({ tab: tab.value, size: size.value, ...active.value }),
    (next, previous) => {
        ++token;
        clearTimeout(timer);
        rows.value = [];
        loading.value = true;
        const immediate =
            next.tab !== previous.tab ||
            next.size !== previous.size ||
            next.start !== previous.start ||
            next.end !== previous.end ||
            ('success' in next &&
                'success' in previous &&
                next.success !== previous.success) ||
            ('action' in next &&
                'action' in previous &&
                next.action !== previous.action);

        if (immediate) {
            search();
        } else {
            timer = setTimeout(search, 300);
        }
    },
);
watch(page, load);
onMounted(load);
onUnmounted(() => {
    token++;
    clearTimeout(timer);
});
function clear() {
    for (const key of Object.keys(active.value)) {
        (active.value as Record<string, string>)[key] = [
            'action',
            'success',
        ].includes(key)
            ? 'all'
            : '';
    }

    dateKey.value++;
}
function switchTab(event: KeyboardEvent, index: number) {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
        return;
    }

    event.preventDefault();
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? 1 : 1 - index;
    tab.value = tabs[next].key;
    document.getElementById(`account-log-tab-${tab.value}`)?.focus();
}
function text(value: unknown): string {
    return value == null || value === ''
        ? '—'
        : typeof value === 'object'
          ? JSON.stringify(value)
          : String(value);
}
function time(row: CdnflyRecord) {
    return formatDate(row.create_at2 ?? row.create_at ?? row.created_at);
}
function success(value: unknown) {
    return value === true || value === 1 || value === '1';
}
function failed(value: unknown) {
    return value === false || value === 0 || value === '0';
}
function pretty(value: unknown) {
    if (typeof value === 'string') {
        try {
            return JSON.stringify(JSON.parse(value), null, 2);
        } catch {
            return value;
        }
    }

    return value == null ? '' : JSON.stringify(value, null, 2);
}
function hasDiff(row: CdnflyRecord) {
    return (
        row.diff != null &&
        !['', '[]', '{}', 'null'].includes(text(row.diff).trim())
    );
}
function showDiff(row: CdnflyRecord) {
    detail.value = pretty(row.diff);
    detailOpen.value = true;
}
</script>

<template>
    <section
        class="console-user-account-logs account-logs"
        aria-label="账户日志"
    >
        <div class="log-tabs" role="tablist" aria-label="日志类型">
            <Button
                variant="ghost"
                type="button"
                data-slot="console-tab"
                v-for="(item, index) in tabs"
                :id="`account-log-tab-${item.key}`"
                :key="item.key"
                role="tab"
                :aria-selected="tab === item.key"
                aria-controls="account-log-panel"
                :tabindex="tab === item.key ? 0 : -1"
                @click="tab = item.key"
                @keydown="switchTab($event, index)"
            >
                {{ item.label }}
            </Button>
        </div>
        <div
            id="account-log-panel"
            role="tabpanel"
            :aria-labelledby="`account-log-tab-${tab}`"
        >
            <form class="log-filters" @submit.prevent="search">
                <SelectField
                    v-if="tab === 'login'"
                    v-model="filters.login.success"
                    aria-label="登录状态"
                >
                    <SelectOption value="all">所有状态</SelectOption>
                    <SelectOption value="1">成功</SelectOption>
                    <SelectOption value="0">失败</SelectOption>
                </SelectField>
                <SelectField
                    v-else
                    v-model="filters.op.action"
                    aria-label="操作动作"
                >
                    <SelectOption value="all">所有动作</SelectOption>
                    <SelectOption value="新增">新增</SelectOption>
                    <SelectOption value="更新">更新</SelectOption>
                    <SelectOption value="删除">删除</SelectOption>
                </SelectField>
                <DateRangePicker
                    :key="`${tab}-${dateKey}`"
                    v-model:start="active.start"
                    v-model:end="active.end"
                    placeholder="请选择时间范围"
                    trigger-class="log-date"
                />
                <label data-slot="console-input-group" class="log-input ip"
                    ><span>IP地址</span
                    ><Input
                        v-model="active.ip"
                        aria-label="IP地址"
                        placeholder="请输入IP地址"
                /></label>
                <template v-if="tab === 'op'">
                    <label
                        data-slot="console-input-group"
                        class="log-input small"
                        ><span>类别</span
                        ><Input
                            v-model="filters.op.type"
                            aria-label="类别"
                            placeholder="请输入类别"
                    /></label>
                    <label
                        data-slot="console-input-group"
                        class="log-input small"
                        ><span>对象</span
                        ><Input
                            v-model="filters.op.content"
                            aria-label="对象"
                            placeholder="请输入对象"
                    /></label>
                    <label
                        data-slot="console-input-group"
                        class="log-input diff"
                        ><span>变更</span
                        ><Input
                            v-model="filters.op.diff"
                            aria-label="变更"
                            placeholder="请输入变更内容"
                    /></label>
                </template>
                <Button
                    variant="link"
                    size="inline"
                    data-slot="console-link"
                    v-if="hasFilters"
                    type="button"
                    class="text-link"
                    @click="clear"
                >
                    清除
                </Button>
            </form>
            <div v-if="error" class="log-error" role="alert">
                {{ error
                }}<Button variant="outline" @click="load"
                    ><RefreshCw class="size-4" />重试</Button
                >
            </div>
            <div
                class="log-scroll"
                tabindex="0"
                aria-label="日志表格，可横向滚动"
                :aria-busy="loading"
            >
                <table :class="{ 'operation-table': tab === 'op' }">
                    <colgroup v-if="tab === 'login'">
                        <col style="width: 25.5%" />
                        <col style="width: 25.5%" />
                        <col style="width: 28.5%" />
                        <col style="width: 20.5%" />
                    </colgroup>
                    <colgroup v-else>
                        <col style="width: 125px" />
                        <col style="width: 125px" />
                        <col style="width: 125px" />
                        <col style="width: 310px" />
                        <col style="width: 225px" />
                        <col style="width: 190px" />
                        <col style="width: 200px" />
                    </colgroup>
                    <thead>
                        <tr v-if="tab === 'login'">
                            <th>IP地址</th>
                            <th>地理位置</th>
                            <th>登录时间</th>
                            <th>登录状态</th>
                        </tr>
                        <tr v-else>
                            <th>类别</th>
                            <th>对象</th>
                            <th>动作</th>
                            <th>变更内容</th>
                            <th>IP地址</th>
                            <th>地理位置</th>
                            <th>操作时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td
                                :colspan="tab === 'login' ? 4 : 7"
                                class="empty"
                            >
                                <span
                                    ><Spinner class="inline size-4" />
                                    加载中…</span
                                >
                            </td>
                        </tr>
                        <tr v-else-if="!rows.length">
                            <td
                                :colspan="tab === 'login' ? 4 : 7"
                                class="empty"
                            >
                                <span>{{
                                    error ? '加载失败，请重试' : '暂无数据'
                                }}</span>
                            </td>
                        </tr>
                        <template v-else
                            ><tr
                                v-for="(row, index) in rows"
                                :key="String(row.id ?? index)"
                            >
                                <template v-if="tab === 'login'"
                                    ><td>{{ text(row.ip) }}</td>
                                    <td class="location">
                                        {{ text(row.ip_location) }}
                                    </td>
                                    <td>{{ time(row) }}</td>
                                    <td>
                                        <span
                                            v-if="
                                                success(
                                                    row.success ?? row.status,
                                                )
                                            "
                                            class="status-icon success"
                                            role="img"
                                            aria-label="登录成功"
                                            title="成功"
                                            ><Check :size="11" /></span
                                        ><span
                                            v-else-if="
                                                failed(
                                                    row.success ?? row.status,
                                                )
                                            "
                                            class="status-icon failed"
                                            role="img"
                                            aria-label="登录失败"
                                            title="失败"
                                            ><X :size="11" /></span
                                        ><span v-else title="未记录登录状态"
                                            >—</span
                                        >
                                    </td></template
                                >
                                <template v-else
                                    ><td>
                                        <span
                                            class="truncate-cell"
                                            :title="text(row.type)"
                                            >{{ text(row.type) }}</span
                                        >
                                    </td>
                                    <td>
                                        <span
                                            class="truncate-cell"
                                            :title="text(row.content)"
                                            >{{ text(row.content) }}</span
                                        >
                                    </td>
                                    <td>{{ text(row.action) }}</td>
                                    <td>
                                        <Button
                                            variant="link"
                                            size="inline"
                                            type="button"
                                            data-slot="console-link"
                                            v-if="hasDiff(row)"
                                            class="text-link truncate-cell"
                                            :title="text(row.diff)"
                                            :aria-label="`查看第 ${index + 1} 条变更详情`"
                                            @click="showDiff(row)"
                                        >
                                            {{ text(row.diff) }}</Button
                                        ><span v-else>—</span>
                                    </td>
                                    <td>{{ text(row.ip) }}</td>
                                    <td class="location">
                                        {{ text(row.ip_location) }}
                                    </td>
                                    <td>{{ time(row) }}</td></template
                                >
                            </tr></template
                        >
                    </tbody>
                </table>
            </div>
            <PackagePagination
                v-model:page="page"
                v-model:page-size="size"
                :total="total"
                :disabled="loading"
                numbered
                edge-links
                class="log-pagination"
            />
        </div>
        <Dialog v-model:open="detailOpen"
            ><DialogScrollContent class="console-user-account-logs sm:max-w-2xl"
                ><DialogHeader
                    ><DialogTitle>变更详情</DialogTitle
                    ><DialogDescription
                        >查看此操作记录的完整变更内容。</DialogDescription
                    ></DialogHeader
                >
                <pre
                    class="max-h-[60vh] overflow-auto rounded border bg-muted/30 p-4 text-sm break-all whitespace-pre-wrap"
                    >{{ detail }}</pre
                >
                <DialogFooter
                    ><Button variant="outline" @click="detailOpen = false"
                        >关闭</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
    </section>
</template>
