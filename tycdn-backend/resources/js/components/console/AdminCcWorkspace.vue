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
import { Button } from '@/components/ui/button';
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
    <section
        class="console-admin-cc-workspace cc-workspace rounded-xl border bg-card p-3 shadow-sm"
    >
        <nav role="tablist" aria-label="CC规则" class="cc-tabs">
            <Button
                variant="ghost"
                type="button"
                data-slot="console-tab"
                v-for="item in ccKinds"
                :key="item.key"
                role="tab"
                :aria-selected="kind === item.key"
                :disabled="busy"
                @click="selectKind(item.key)"
            >
                {{ item.label }}
            </Button>
        </nav>
        <div role="tabpanel" :aria-busy="loading">
            <div class="toolbar" :class="{ 'rule-toolbar': kind === 'rule' }">
                <Button
                    variant="default"
                    type="button"
                    data-slot="console-action"
                    class="primary"
                    :disabled="busy"
                    @click="emit('create', kind)"
                >
                    <Plus />添加{{ label }}</Button
                ><Button
                    variant="outline"
                    type="button"
                    data-slot="console-action"
                    v-if="kind === 'rule'"
                    :disabled="loading || busy"
                    @click="load()"
                >
                    <RefreshCw />刷新</Button
                ><DropdownMenu
                    ><DropdownMenuTrigger as-child
                        ><Button
                            variant="outline"
                            type="button"
                            data-slot="console-action"
                            :class="{ 'ml-auto': kind === 'rule' }"
                            :disabled="busy"
                        >
                            {{ kind === 'rule' ? '批量操作' : '更多操作' }}
                            <ChevronDown /></Button></DropdownMenuTrigger
                    ><DropdownMenuContent
                        class="console-admin-cc-workspace"
                        align="end"
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
                    ><Button
                        variant="outline"
                        data-slot="console-action"
                        type="button"
                        :aria-expanded="advanced"
                        @click="advanced = !advanced"
                    >
                        <Filter />更多筛选</Button
                    ><span class="muted"
                        >已筛选 <b>{{ activeFilters }}</b> 项</span
                    ></template
                ><Button
                    variant="link"
                    size="inline"
                    data-slot="console-link"
                    v-if="kind === 'rule'"
                    type="button"
                    class="link ml-auto"
                    :disabled="busy"
                    @click="clearFilters"
                >
                    清除
                </Button>
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
                ><Button
                    variant="default"
                    type="submit"
                    data-slot="console-action"
                    class="primary"
                    :disabled="busy"
                >
                    查询</Button
                ><Button
                    variant="link"
                    size="inline"
                    data-slot="console-link"
                    type="button"
                    class="link"
                    :disabled="busy"
                    @click="clearFilters"
                >
                    清除
                </Button>
            </form>
            <p
                data-typography="body"
                v-if="error"
                role="alert"
                class="error-message"
            >
                {{ error }}
                <Button
                    variant="link"
                    size="inline"
                    type="button"
                    data-slot="console-link"
                    class="link"
                    :disabled="busy"
                    @click="load()"
                >
                    重试
                </Button>
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
                                    <Button
                                        variant="link"
                                        size="inline"
                                        type="button"
                                        data-slot="console-link"
                                        class="link"
                                        :disabled="busy"
                                        @click="emit('manage', kind, row)"
                                    >
                                        {{ row.name }}
                                    </Button>
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
                                    <Button
                                        variant="link"
                                        size="inline"
                                        type="button"
                                        data-slot="console-link"
                                        class="name-button"
                                        :disabled="busy"
                                        @click="emit('manage', kind, row)"
                                    >
                                        {{ row.name }}
                                    </Button>
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
                                    <Button
                                        variant="link"
                                        size="inline"
                                        type="button"
                                        data-slot="console-link"
                                        class="link"
                                        :disabled="busy"
                                        @click="emit('manage', kind, row)"
                                    >
                                        管理</Button
                                    ><DropdownMenu
                                        ><DropdownMenuTrigger as-child
                                            ><Button
                                                variant="link"
                                                size="inline"
                                                type="button"
                                                data-slot="console-link"
                                                class="link row-more"
                                                :aria-label="`更多操作 ${row.id}`"
                                                :disabled="busy"
                                            >
                                                <template v-if="kind === 'rule'"
                                                    >更多
                                                    <ChevronDown /></template
                                                ><MoreHorizontal
                                                    v-else /></Button></DropdownMenuTrigger
                                        ><DropdownMenuContent
                                            class="console-admin-cc-workspace"
                                            align="end"
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
