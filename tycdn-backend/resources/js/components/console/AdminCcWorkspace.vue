<script setup lang="ts">
import {
    ChevronDown,
    Filter,
    MoreHorizontal,
    Plus,
    RefreshCw,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import CheckboxField from '@/components/ui/checkbox/CheckboxField.vue';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import {
    ccKinds,
    ccFilterLabels,
    ccSystem,
    ccEnabled,
    ccStatus,
    ccQuery,
    ccSummary,
    emptyCcFilters,
} from '@/lib/adminCc';
import type { CcKind } from '@/lib/adminCc';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import type { CdnflyRecord } from '@/lib/sharedTypes';
const emit = defineEmits<{
    create: [kind: CcKind];
    manage: [kind: CcKind, row: CdnflyRecord];
}>();
const kind = ref<CcKind>('rule'),
    rows = ref<CdnflyRecord[]>([]),
    selected = ref<number[]>([]),
    loading = ref(false),
    busy = ref(false),
    error = ref(''),
    page = ref(1),
    pageSize = ref(10),
    total = ref(0),
    advanced = ref(false);
const filterState = reactive({
    rule: emptyCcFilters(),
    matcher: emptyCcFilters(),
    filter: emptyCcFilters(),
});
const filters = computed(() => filterState[kind.value]);
const label = computed(
    () => ccKinds.find((item) => item.key === kind.value)!.label,
);
const activeFilters = computed(
    () =>
        Object.values(filters.value).filter((value) => value.trim() !== '')
            .length,
);
const summary = computed(() => ccSummary(rows.value, total.value));
const summaryItems = [
    { key: 'all', label: '全部' },
    { key: 'system', label: '系统' },
    { key: 'custom', label: '自定义' },
    { key: 'shown', label: '显示' },
    { key: 'disabled', label: '禁用' },
] as const;
const allSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selected.value.includes(Number(row.id))),
);
const pages = computed(() =>
    Math.max(1, Math.ceil(total.value / pageSize.value)),
);

const selectedRows = computed(() =>
    rows.value.filter((row) => selected.value.includes(Number(row.id))),
);
const canDelete = computed(
    () =>
        selected.value.length > 0 &&
        !(kind.value === 'rule' && selectedRows.value.some(ccSystem)),
);
let request = 0;
onMounted(() => void load());
onUnmounted(() => request++);
defineExpose({ refresh: () => load() });
const message = (e: unknown) =>
    e instanceof Error ? e.message : '操作失败，请重试';
async function load(target = page.value) {
    const id = ++request;
    loading.value = true;
    error.value = '';
    selected.value = [];
    page.value = target;
    const query = ccQuery(
        kind.value,
        filters.value,
        page.value,
        pageSize.value,
    );

    try {
        const data = await apiRequest(
            `/api/admin/cc/${kind.value}?${new URLSearchParams(Object.entries(query).map(([key, value]) => [key, String(value)]))}`,
        );

        if (id !== request) {
            return;
        }

        rows.value = extractCdnflyRows(data);
        total.value = extractCdnflyTotal(data, rows.value.length);

        if (page.value > pages.value) {
            void load(pages.value);

            return;
        }
    } catch (e) {
        if (id === request) {
            error.value = message(e);
            rows.value = [];
            total.value = 0;
        }
    } finally {
        if (id === request) {
            loading.value = false;
        }
    }
}
function selectKind(value: CcKind) {
    if (kind.value === value) {
        return;
    }

    kind.value = value;
    advanced.value = false;
    void load(1);
}
function clearFilters() {
    Object.assign(filters.value, emptyCcFilters());
    void load(1);
}
function toggle(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((value) => value !== id)
        : [...selected.value, id];
}
async function update(enable: number, ids = [...selected.value]) {
    if (!ids.length || busy.value) {
        return;
    }

    busy.value = true;
    error.value = '';
    const failed: number[] = [];
    let detail = '';

    for (const id of ids) {
        try {
            await apiRequest(`/api/admin/cc/${kind.value}/${id}`, {
                method: 'PUT',
                body: JSON.stringify({ enable }),
            });
        } catch (e) {
            failed.push(id);
            detail = message(e);
        }
    }

    await load();
    selected.value = failed;

    if (failed.length) {
        error.value = `${failed.length} 项失败：${detail}`;
    } else {
        toast.success(enable ? '已启用' : '已禁用');
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
    const failed: number[] = [];

    for (const id of deleteIds.value) {
        try {
            await apiRequest(`/api/admin/cc/${kind.value}/${id}`, {
                method: 'DELETE',
            });
        } catch (e) {
            failed.push(id);
            deleteError.value = message(e);
        }
    }

    await load();
    selected.value = failed;
    deleteIds.value = failed;
    deleteOpen.value = failed.length > 0;

    if (!failed.length) {
        toast.success('删除成功');
    }

    busy.value = false;
}
</script>
<template>
    <section class="cc-workspace rounded-xl border bg-card p-3 shadow-sm">
        <nav role="tablist" aria-label="CC规则" class="cc-tabs">
            <button
                v-for="item in ccKinds"
                :key="item.key"
                role="tab"
                :aria-selected="kind === item.key"
                :disabled="busy"
                @click="selectKind(item.key)"
            >
                {{ item.label }}
            </button>
        </nav>
        <div role="tabpanel" :aria-busy="loading">
            <div class="toolbar" :class="{ 'rule-toolbar': kind === 'rule' }">
                <button
                    data-slot="console-action"
                    class="primary"
                    :disabled="busy"
                    @click="emit('create', kind)"
                >
                    <Plus />添加{{ label }}</button
                ><button
                    data-slot="console-action"
                    v-if="kind === 'rule'"
                    :disabled="loading || busy"
                    @click="load()"
                >
                    <RefreshCw />刷新</button
                ><DropdownMenu
                    ><DropdownMenuTrigger as-child
                        ><button
                            data-slot="console-action"
                            :class="{ 'ml-auto': kind === 'rule' }"
                            :disabled="busy"
                        >
                            {{ kind === 'rule' ? '批量操作' : '更多操作' }}
                            <ChevronDown /></button></DropdownMenuTrigger
                    ><DropdownMenuContent align="end"
                        ><DropdownMenuItem
                            :disabled="!selected.length || busy"
                            @select="update(1)"
                            >启用</DropdownMenuItem
                        ><DropdownMenuItem
                            :disabled="!selected.length || busy"
                            @select="update(0)"
                            >禁用</DropdownMenuItem
                        ><DropdownMenuItem
                            :disabled="!canDelete || busy"
                            @select="askDelete()"
                            >删除</DropdownMenuItem
                        ><DropdownMenuItem
                            v-if="kind !== 'rule'"
                            :disabled="loading || busy"
                            @select="load()"
                            >刷新</DropdownMenuItem
                        ></DropdownMenuContent
                    ></DropdownMenu
                >
            </div>
            <form class="filter-bar" @submit.prevent="load(1)">
                <SelectField
                    v-model="filters.internal"
                    aria-label="规则类型"
                    :disabled="busy"
                    @change="load(1)"
                >
                    <SelectOption value="">所有类型</SelectOption>
                    <SelectOption value="1">系统规则</SelectOption>
                    <SelectOption value="0"
                        >自定义规则</SelectOption
                    ></SelectField
                ><SelectField
                    v-if="kind === 'rule'"
                    v-model="filters.is_show"
                    aria-label="显示状态"
                    :disabled="busy"
                    @change="load(1)"
                >
                    <SelectOption value="">所有显示</SelectOption>
                    <SelectOption value="1">显示</SelectOption>
                    <SelectOption value="0">隐藏</SelectOption></SelectField
                ><SelectField
                    v-model="filters.enable"
                    aria-label="启用状态"
                    :disabled="busy"
                    @change="load(1)"
                >
                    <SelectOption value="">所有状态</SelectOption>
                    <SelectOption value="1">正常</SelectOption>
                    <SelectOption value="0">禁用</SelectOption></SelectField
                ><label data-slot="console-input-group" class="input-group"
                    ><span>{{ label }}名称</span
                    ><Input
                        v-model="filters.name"
                        :aria-label="`${label}名称`"
                        :placeholder="
                            kind === 'rule'
                                ? '请输入规则组名称,模糊搜索'
                                : `请输入${label}名称`
                        "
                        :disabled="busy"
                        @change="load(1)" /></label
                ><label
                    data-slot="console-input-group"
                    class="input-group id-input"
                    ><span>{{ label }}ID</span
                    ><Input
                        v-model="filters.id"
                        :aria-label="`${label}ID`"
                        :placeholder="`请输入${label}ID`"
                        :disabled="busy"
                        inputmode="numeric"
                        @change="load(1)" /></label
                ><label
                    data-slot="console-input-group"
                    v-if="kind === 'rule'"
                    class="input-group id-input"
                    ><span>用户ID</span
                    ><Input
                        v-model="filters.uid"
                        aria-label="用户ID"
                        placeholder="请输入用户ID"
                        :disabled="busy"
                        inputmode="numeric"
                        @change="load(1)" /></label
                ><template v-else
                    ><button
                        data-slot="console-action"
                        type="button"
                        :aria-expanded="advanced"
                        @click="advanced = !advanced"
                    >
                        <Filter />更多筛选</button
                    ><span class="muted"
                        >已筛选 <b>{{ activeFilters }}</b> 项</span
                    ></template
                ><button
                    data-slot="console-link"
                    v-if="kind === 'rule'"
                    type="button"
                    class="link ml-auto"
                    :disabled="busy"
                    @click="clearFilters"
                >
                    清除
                </button>
            </form>
            <form
                v-if="advanced && kind !== 'rule'"
                class="advanced"
                @submit.prevent="load(1)"
            >
                <label data-slot="console-input-group" class="input-group"
                    ><span>用户ID</span
                    ><Input
                        v-model="filters.uid"
                        aria-label="用户ID"
                        placeholder="请输入用户ID"
                        :disabled="busy"
                        inputmode="numeric" /></label
                ><button
                    data-slot="console-action"
                    class="primary"
                    :disabled="busy"
                >
                    查询</button
                ><button
                    data-slot="console-link"
                    type="button"
                    class="link"
                    :disabled="busy"
                    @click="clearFilters"
                >
                    清除
                </button>
            </form>
            <p v-if="error" role="alert" class="error-message">
                {{ error }}
                <button
                    data-slot="console-link"
                    class="link"
                    :disabled="busy"
                    @click="load()"
                >
                    重试
                </button>
            </p>
            <div v-if="kind === 'rule'" class="summary">
                <span
                    v-for="item in summaryItems"
                    :key="item.key"
                    class="pill"
                    :class="item.key"
                    :title="
                        item.key === 'all' ? '匹配的全部规则组' : '统计当前页'
                    "
                    >{{ item.label }} <b>{{ summary[item.key] }}</b></span
                >
            </div>
            <div
                class="table-scroll"
                :class="{
                    'matcher-table': kind === 'matcher',
                    'rule-table': kind === 'rule',
                }"
            >
                <table>
                    <thead>
                        <tr>
                            <th class="selection">
                                <CheckboxField
                                    aria-label="选择本页全部"
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
                            <th>{{ kind === 'rule' ? '名称' : '用户' }}</th>
                            <th>{{ kind === 'rule' ? '用户' : '名称' }}</th>
                            <th>{{ kind === 'rule' ? '类型' : '来源' }}</th>
                            <th v-if="kind !== 'matcher'">
                                {{ kind === 'rule' ? '显示' : '类型' }}
                            </th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading || !rows.length">
                            <td
                                :colspan="kind === 'matcher' ? 8 : 9"
                                class="empty"
                            >
                                {{ loading ? '加载中…' : '暂无数据' }}
                            </td>
                        </tr>
                        <tr
                            v-for="row in loading ? [] : rows"
                            :key="Number(row.id)"
                            :class="{ 'system-row': ccSystem(row) }"
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
                            <template v-if="kind === 'rule'"
                                ><td class="rule-name">
                                    <button
                                        data-slot="console-link"
                                        class="link"
                                        :disabled="busy"
                                        @click="emit('manage', kind, row)"
                                    >
                                        {{ row.name }}
                                    </button>
                                    <div class="subline">
                                        {{
                                            ccSystem(row)
                                                ? '系统规则'
                                                : '自定义规则'
                                        }}
                                        · 排序 {{ row.sort ?? '—' }}
                                    </div>
                                </td>
                                <td>
                                    <span class="pill owner">{{
                                        Number(row.uid) > 0
                                            ? row.username || `用户 #${row.uid}`
                                            : '系统'
                                    }}</span>
                                </td>
                                <td>
                                    <span class="pill source">{{
                                        ccSystem(row) ? '⊙ 系统' : '自定义'
                                    }}</span>
                                </td>
                                <td>
                                    <span
                                        class="pill"
                                        :class="
                                            ccEnabled(row.is_show)
                                                ? 'success'
                                                : 'muted'
                                        "
                                        >{{
                                            ccEnabled(row.is_show)
                                                ? '◉ 显示'
                                                : '隐藏'
                                        }}</span
                                    >
                                </td></template
                            ><template v-else
                                ><td>
                                    {{
                                        Number(row.uid) > 0
                                            ? row.username || `用户 #${row.uid}`
                                            : '系统'
                                    }}
                                </td>
                                <td class="resource-name">
                                    <button
                                        class="name-button"
                                        :disabled="busy"
                                        @click="emit('manage', kind, row)"
                                    >
                                        {{ row.name }}
                                    </button>
                                </td>
                                <td>
                                    <span class="pill source">{{
                                        ccSystem(row)
                                            ? '系统规则'
                                            : '自定义规则'
                                    }}</span>
                                </td>
                                <td v-if="kind === 'filter'">
                                    {{
                                        ccFilterLabels[String(row.type)] ??
                                        row.type
                                    }}
                                </td></template
                            >
                            <td>
                                <span class="pill" :class="ccStatus(row).tone"
                                    >{{ kind === 'rule' ? '● ' : ''
                                    }}{{ ccStatus(row).label }}</span
                                >
                            </td>
                            <td>
                                {{ row.create_at2 ?? row.create_at ?? '—' }}
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button
                                        data-slot="console-link"
                                        class="link"
                                        :disabled="busy"
                                        @click="emit('manage', kind, row)"
                                    >
                                        管理</button
                                    ><DropdownMenu
                                        ><DropdownMenuTrigger as-child
                                            ><button
                                                data-slot="console-link"
                                                class="link row-more"
                                                :aria-label="`更多操作 ${row.id}`"
                                                :disabled="busy"
                                            >
                                                <template v-if="kind === 'rule'"
                                                    >更多
                                                    <ChevronDown /></template
                                                ><MoreHorizontal
                                                    v-else
                                                /></button></DropdownMenuTrigger
                                        ><DropdownMenuContent align="end"
                                            ><DropdownMenuItem
                                                v-if="
                                                    kind !== 'rule' ||
                                                    ccEnabled(row.enable)
                                                "
                                                :disabled="busy"
                                                @select="
                                                    update(0, [Number(row.id)])
                                                "
                                                >禁用</DropdownMenuItem
                                            ><DropdownMenuItem
                                                v-if="
                                                    kind !== 'rule' ||
                                                    !ccEnabled(row.enable)
                                                "
                                                :disabled="busy"
                                                @select="
                                                    update(1, [Number(row.id)])
                                                "
                                                >启用</DropdownMenuItem
                                            ><DropdownMenuItem
                                                v-if="
                                                    kind !== 'rule' ||
                                                    !ccSystem(row)
                                                "
                                                :disabled="busy"
                                                @select="
                                                    askDelete([Number(row.id)])
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
                :previous-disabled="loading || busy || page <= 1"
                :next-disabled="loading || busy || page >= pages"
                @previous="load(page - 1)"
                @next="load(page + 1)"
            />
        </div>
        <ConfirmDeleteDialog
            :open="deleteOpen"
            title="删除确认"
            :description="`是否删除所选 ${deleteIds.length} 个${label}？`"
            :loading="busy"
            :error="deleteError"
            @confirm="remove"
            @cancel="!busy && (deleteOpen = false)"
        />
    </section>
</template>
<style scoped>
.cc-workspace {
    font-size: 12px;
    min-width: 0;
}
.cc-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 12px;
}
.cc-tabs button {
    border: 0;
    background: transparent;
    padding: 5px 12px;
}
.cc-tabs button[aria-selected='true'] {
    background: #2d8cf015;
    color: #2d8cf0;
    font-weight: 600;
}
button,
input,
:deep([data-slot='select-trigger']) {
    font-size: 12px;
    border: 1px solid var(--border);
    background: var(--card);
    border-radius: 3px;
}
button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 4px 12px;
    min-height: 28px;
    cursor: pointer;
}
button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
button svg {
    width: 12px;
    height: 12px;
}
input,
:deep([data-slot='select-trigger']) {
    height: 27px;
    min-width: 0;
    padding: 4px 7px;
}
:deep([data-slot='checkbox']) {
    height: 14px;
    width: 14px;
    accent-color: #2d8cf0;
    vertical-align: middle;
}
button:focus-visible,
input:focus-visible,
:deep([data-slot='select-trigger']):focus-visible {
    outline: 2px solid #2d8cf0;
    outline-offset: 1px;
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
    min-height: 0;
    padding: 0;
}
.toolbar {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 10px;
}
.rule-toolbar {
    border-bottom: 1px solid var(--border);
    padding-bottom: 10px;
}
.filter-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 7px;
    margin-bottom: 10px;
}
.filter-bar :deep([data-slot='select-trigger']) {
    width: 132px;
}
.input-group {
    display: flex;
    align-items: center;
    border: 1px solid var(--border);
    border-radius: 3px;
    overflow: hidden;
}
.input-group span {
    padding: 4px 6px;
    background: color-mix(in srgb, var(--muted) 60%, var(--card));
    white-space: nowrap;
    font-size: 11px;
}
.input-group input {
    border: 0;
    border-radius: 0;
    width: 151px;
    height: 25px;
}
.input-group.id-input input {
    width: 104px;
}
.filter-bar button {
    min-height: 27px;
    font-size: 11px;
    padding: 4px 9px;
}
.filter-bar .link {
    padding: 0;
}
.filter-bar .muted {
    font-size: 11px;
}
.muted,
.subline {
    color: var(--muted-foreground);
}
b {
    font-weight: 500;
    color: #2d8cf0;
}
.advanced {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: color-mix(in srgb, var(--muted) 20%, var(--card));
    margin-bottom: 10px;
}
.summary {
    display: flex;
    gap: 6px;
    margin: 4px 0 10px;
    flex-wrap: wrap;
}
.pill {
    display: inline-block;
    border-radius: 14px;
    padding: 2px 8px;
    font-size: 10px;
    line-height: 16px;
    background: color-mix(in srgb, var(--muted) 65%, var(--card));
    white-space: nowrap;
}
.summary .pill {
    font-size: 11px;
    padding: 3px 11px;
}
.summary .all b,
.summary .system b {
    color: inherit;
    font-weight: 600;
}
.summary .shown b {
    color: #19be6b;
}
.summary .disabled b {
    color: #f90;
}
.summary .custom {
    color: #2d8cf0;
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
    font-size: 12px;
    font-weight: 500;
    height: 33px;
    background: color-mix(in srgb, var(--muted) 20%, var(--card));
    padding: 6px 10px;
    border-bottom: 1px solid var(--border);
}
td {
    height: 40px;
    padding: 2px 10px;
    font-size: 11px;
    border-bottom: 1px solid var(--border);
}
.selection {
    width: 65px;
    text-align: center;
}
th:nth-child(2) {
    width: 9%;
}
.rule-name {
    min-width: 210px;
}
.rule-table th:nth-child(3) {
    width: 17%;
}
.rule-table th:nth-child(4),
.rule-table th:nth-child(5),
.rule-table th:nth-child(6),
.rule-table th:nth-child(7) {
    width: 11%;
}
.resource-name {
    min-width: 180px;
}
.subline {
    font-size: 10px;
    line-height: 17px;
}
.rule-name .link {
    font-weight: 500;
}
.name-button {
    border: 0;
    padding: 0;
    min-height: 0;
    background: transparent;
    font-size: 11px;
}
.source {
    color: color-mix(in srgb, #2d8cf0 35%, var(--foreground));
    background: #2d8cf00a;
}
.owner {
    color: var(--foreground);
}
.success {
    color: #19be6b;
    background: #19be6b0d;
}
.warning {
    color: #ed960d;
    background: #ff990010;
}
.danger {
    color: #ed4014;
    background: #ed401410;
}
.system-row {
    background: color-mix(in srgb, var(--primary) 1.5%, var(--card));
}
.system-row:nth-child(even) {
    background: var(--card);
}
.row-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 85px;
}
.row-more {
    gap: 3px;
}
.row-more[aria-expanded='true'] {
    box-shadow: 0 0 0 1px #2d8cf030;
}
.empty {
    text-align: center;
    color: var(--muted-foreground);
    height: 42px;
}
.matcher-table {
    border: 1px solid var(--border);
    border-radius: 5px;
}
.matcher-table tr:last-child td {
    border-bottom: 0;
}
.error-message {
    color: var(--destructive);
    margin: 8px 0;
    padding: 8px;
    border: 1px solid color-mix(in srgb, var(--destructive) 30%, var(--border));
    border-radius: 4px;
}
@media (max-width: 640px) {
    .filter-bar :deep([data-slot='select-trigger']) {
        flex: 1;
        min-width: 110px;
    }
    .input-group {
        max-width: 100%;
    }
    .filter-bar .input-group {
        flex: 1;
        min-width: 200px;
    }
    .filter-bar .input-group input {
        width: 100%;
    }
    .toolbar {
        flex-wrap: wrap;
    }
    .advanced {
        flex-wrap: wrap;
    }
}
</style>
