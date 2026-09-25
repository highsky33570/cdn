<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
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
} from '@/lib/adminCc';
import type { CcKind } from '@/lib/adminCc';
import { getErrorMessage, textValue } from '@/lib/cdnRecord';
import {
    listUserCcRules,
    listUserCcMatchers,
    listUserCcFilters,
    updateUserCcRule,
    updateUserCcMatcher,
    updateUserCcFilter,
    deleteUserCcRule,
    deleteUserCcMatcher,
    deleteUserCcFilter,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnUserApi';
import type { CdnflyRecord } from '@/lib/cdnUserApi';

const emit = defineEmits<{
    create: [kind: CcKind];
    manage: [kind: CcKind, row: CdnflyRecord];
}>();
const kind = ref<CcKind>('rule'),
    rows = ref<CdnflyRecord[]>([]),
    selected = ref<number[]>([]);
const loading = ref(false),
    busy = ref(false),
    error = ref('');
const page = ref(1),
    pageSize = ref(10),
    total = ref(0);
const emptyFilters = () => ({ name: '', id: '', enable: '', is_show: '' });
const filterState = reactive({
    rule: emptyFilters(),
    matcher: emptyFilters(),
    filter: emptyFilters(),
});
const filters = computed(() => filterState[kind.value]);
const label = computed(
    () => ccKinds.find((item) => item.key === kind.value)!.label,
);
const selectableRows = computed(() =>
    rows.value.filter((row) => !ccSystem(row)),
);
const allSelected = computed(
    () =>
        selectableRows.value.length > 0 &&
        selectableRows.value.every((row) =>
            selected.value.includes(Number(row.id)),
        ),
);
const api = {
    rule: {
        list: listUserCcRules,
        update: updateUserCcRule,
        remove: deleteUserCcRule,
    },
    matcher: {
        list: listUserCcMatchers,
        update: updateUserCcMatcher,
        remove: deleteUserCcMatcher,
    },
    filter: {
        list: listUserCcFilters,
        update: updateUserCcFilter,
        remove: deleteUserCcFilter,
    },
};
let request = 0,
    searchTimer: ReturnType<typeof setTimeout> | undefined;
onMounted(() => void load());
onUnmounted(() => {
    request++;
    clearTimeout(searchTimer);
});
defineExpose({ refresh: () => load() });
async function load(target = page.value) {
    clearTimeout(searchTimer);
    const id = ++request;
    loading.value = true;
    error.value = '';
    selected.value = [];
    page.value = target;
    const query: Record<string, string | number> = {
        page: target,
        limit: pageSize.value,
        internal_self: 1,
    };

    for (const [key, value] of Object.entries(filters.value)) {
        if (value.trim() && (key !== 'is_show' || kind.value === 'rule')) {
            query[key] = value.trim();
        }
    }

    try {
        const data = await api[kind.value].list(query);

        if (id !== request) {
            return;
        }

        rows.value = extractCdnflyRows(data);
        total.value = extractCdnflyTotal(data, rows.value.length);
        const lastPage = Math.max(1, Math.ceil(total.value / pageSize.value));

        if (target > lastPage) {
            void load(lastPage);
        }
    } catch (e) {
        if (id === request) {
            rows.value = [];
            total.value = 0;
            error.value = getErrorMessage(e);
        }
    } finally {
        if (id === request) {
            loading.value = false;
        }
    }
}
function search() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => void load(1), 300);
}
function selectKind(value: CcKind) {
    if (busy.value || kind.value === value) {
        return;
    }

    kind.value = value;
    void load(1);
}
function tabKey(event: KeyboardEvent) {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
        return;
    }

    event.preventDefault();
    const current = ccKinds.findIndex((item) => item.key === kind.value);
    const index =
        event.key === 'Home'
            ? 0
            : event.key === 'End'
              ? 2
              : (current + (event.key === 'ArrowLeft' ? 2 : 1)) % 3;
    selectKind(ccKinds[index].key);
    document.getElementById(`user-cc-tab-${kind.value}`)?.focus();
}
function clearFilters() {
    Object.assign(filters.value, emptyFilters());
    void load(1);
}
const deleteOpen = ref(false),
    deleteIds = ref<number[]>([]);
function askDelete(ids = [...selected.value]) {
    if (!ids.length) {
        toast.warning('请选择要删除的项目');

        return;
    }

    deleteIds.value = ids;
    deleteOpen.value = true;
}
async function batch(
    action: 'enable' | 'disable' | 'delete',
    ids = [...selected.value],
) {
    if (busy.value || loading.value) {
        return;
    }

    if (!ids.length) {
        toast.warning('请选择要操作的项目');

        return;
    }

    clearTimeout(searchTimer);
    busy.value = true;
    error.value = '';
    const failed: number[] = [];
    let reason = '';

    for (const id of ids) {
        try {
            if (
                rows.value.some((row) => Number(row.id) === id && ccSystem(row))
            ) {
                throw new Error('系统规则不可修改');
            }

            if (action === 'delete') {
                await api[kind.value].remove(id);
            } else {
                await api[kind.value].update(id, {
                    enable: action === 'enable' ? 1 : 0,
                });
            }
        } catch (e) {
            failed.push(id);
            reason = getErrorMessage(e);
        }
    }

    deleteOpen.value = false;
    await load();
    selected.value = failed;

    if (failed.length) {
        error.value = `${failed.length} 项操作失败：${reason}`;
    } else {
        toast.success('操作成功');
    }

    busy.value = false;
}
function createdAt(row: CdnflyRecord) {
    return (
        textValue(row.create_at2 || row.created_at || row.create_at)
            .replace('T', ' ')
            .slice(0, 19) || '-'
    );
}
</script>

<template>
    <section class="user-cc-workspace">
        <nav
            class="cc-tabs"
            role="tablist"
            aria-label="CC 规则"
            @keydown="tabKey"
        >
            <Button
                variant="ghost"
                data-slot="console-tab"
                v-for="item in ccKinds"
                :id="`user-cc-tab-${item.key}`"
                :key="item.key"
                type="button"
                role="tab"
                :aria-selected="kind === item.key"
                aria-controls="user-cc-panel"
                :tabindex="kind === item.key ? 0 : -1"
                :disabled="busy"
                @click="selectKind(item.key)"
            >
                {{ item.label }}
            </Button>
        </nav>
        <div
            id="user-cc-panel"
            role="tabpanel"
            :aria-labelledby="`user-cc-tab-${kind}`"
            :aria-busy="loading || busy"
        >
            <div class="cc-actions">
                <Button
                    variant="default"
                    data-slot="console-action"
                    type="button"
                    class="cc-button primary"
                    :disabled="busy || loading"
                    @click="emit('create', kind)"
                >
                    添加{{ label }}
                </Button>
                <DropdownMenu
                    ><DropdownMenuTrigger as-child
                        ><Button
                            variant="outline"
                            data-slot="console-action"
                            type="button"
                            class="cc-button"
                            :disabled="busy || loading"
                        >
                            更多操作
                            <ChevronDown
                                :size="16" /></Button></DropdownMenuTrigger
                    ><DropdownMenuContent align="start"
                        ><DropdownMenuItem @select="batch('enable')"
                            >启用</DropdownMenuItem
                        ><DropdownMenuItem @select="batch('disable')"
                            >禁用</DropdownMenuItem
                        ><DropdownMenuItem @select="askDelete()"
                            >删除</DropdownMenuItem
                        ></DropdownMenuContent
                    ></DropdownMenu
                >
            </div>
            <form class="cc-filters" @submit.prevent="load(1)">
                <SelectField
                    v-if="kind === 'rule'"
                    v-model="filters.is_show"
                    aria-label="显示状态"
                    :disabled="busy"
                    @change="load(1)"
                >
                    <SelectOption value="">所有显示</SelectOption>
                    <SelectOption value="1">显示</SelectOption>
                    <SelectOption value="0">隐藏</SelectOption>
                </SelectField>
                <SelectField
                    v-model="filters.enable"
                    aria-label="状态"
                    :disabled="busy"
                    @change="load(1)"
                >
                    <SelectOption value="">所有状态</SelectOption>
                    <SelectOption value="1">启用</SelectOption>
                    <SelectOption value="0">禁用</SelectOption>
                </SelectField>
                <label data-slot="console-input-group" class="cc-input"
                    ><span>{{ label }}名称</span
                    ><Input
                        v-model="filters.name"
                        :placeholder="`请输入${label}名称,模糊搜索`"
                        :disabled="busy"
                        @input="search"
                /></label>
                <label data-slot="console-input-group" class="cc-input"
                    ><span>{{ label }}ID</span
                    ><Input
                        v-model="filters.id"
                        :placeholder="`请输入${label}ID`"
                        inputmode="numeric"
                        :disabled="busy"
                        @input="search"
                /></label>
                <Button
                    variant="link"
                    size="inline"
                    data-slot="console-link"
                    type="button"
                    class="text-action"
                    :disabled="busy"
                    @click="clearFilters"
                >
                    清除
                </Button>
                <Button
                    variant="ghost"
                    size="inline"
                    data-slot="console-submit-helper"
                    type="submit"
                    class="sr-only"
                    tabindex="-1"
                    :disabled="busy"
                >
                    查询
                </Button>
            </form>
            <p
                data-typography="body"
                v-if="error"
                class="cc-error"
                role="alert"
            >
                {{ error }}
                <Button
                    variant="link"
                    size="inline"
                    data-slot="console-link"
                    type="button"
                    class="text-action"
                    :disabled="busy || loading"
                    @click="load()"
                >
                    重试
                </Button>
            </p>
            <div class="cc-table-scroll">
                <table class="cc-table">
                    <colgroup>
                        <col style="width: 7%" />
                        <col style="width: 12%" />
                        <col style="width: 18%" />
                        <col style="width: 12%" />
                        <col style="width: 12%" />
                        <col v-if="kind === 'rule'" style="width: 9%" />
                        <col style="width: 19%" />
                        <col style="width: 18%" />
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
                                    :disabled="
                                        busy ||
                                        loading ||
                                        !selectableRows.length
                                    "
                                    @change="
                                        selected = allSelected
                                            ? []
                                            : selectableRows.map((row) =>
                                                  Number(row.id),
                                              )
                                    "
                                />
                            </th>
                            <th>ID</th>
                            <th>名称</th>
                            <th>
                                {{
                                    kind === 'rule'
                                        ? '显示'
                                        : kind === 'matcher'
                                          ? '系统规则'
                                          : '类型'
                                }}
                            </th>
                            <th>状态</th>
                            <th v-if="kind === 'rule'">排序</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td
                                :colspan="kind === 'rule' ? 8 : 7"
                                class="empty"
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
                                        :disabled="busy || ccSystem(row)"
                                        :title="
                                            ccSystem(row)
                                                ? '系统规则只读'
                                                : undefined
                                        "
                                    />
                                </td>
                                <td>{{ row.id }}</td>
                                <td
                                    class="name-cell"
                                    :title="textValue(row.name)"
                                >
                                    {{ row.name || '-' }}
                                </td>
                                <td v-if="kind === 'rule'">
                                    {{
                                        ccEnabled(row.is_show) ? '显示' : '隐藏'
                                    }}
                                </td>
                                <td v-else-if="kind === 'matcher'">
                                    {{ ccSystem(row) ? '是' : '否' }}
                                </td>
                                <td v-else>
                                    {{
                                        ccFilterLabels[textValue(row.type)] ||
                                        row.type ||
                                        '-'
                                    }}
                                </td>
                                <td>
                                    <span
                                        class="cc-status"
                                        :data-tone="ccStatus(row).tone"
                                        ><i />{{ ccStatus(row).label }}</span
                                    >
                                </td>
                                <td v-if="kind === 'rule'">
                                    {{ row.sort ?? '-' }}
                                </td>
                                <td>{{ createdAt(row) }}</td>
                                <td>
                                    <div class="row-actions">
                                        <Button
                                            variant="link"
                                            size="inline"
                                            data-slot="console-link"
                                            type="button"
                                            class="text-action"
                                            :disabled="busy || ccSystem(row)"
                                            @click="emit('manage', kind, row)"
                                        >
                                            管理</Button
                                        ><DropdownMenu
                                            ><DropdownMenuTrigger as-child
                                                ><Button
                                                    variant="link"
                                                    size="inline"
                                                    data-slot="console-link"
                                                    type="button"
                                                    class="text-action row-more"
                                                    :disabled="
                                                        busy || ccSystem(row)
                                                    "
                                                    :aria-label="`更多操作 ${row.id}`"
                                                >
                                                    更多
                                                    <ChevronDown
                                                        :size="
                                                            14
                                                        " /></Button></DropdownMenuTrigger
                                            ><DropdownMenuContent align="end"
                                                ><DropdownMenuItem
                                                    @select="
                                                        batch('enable', [
                                                            Number(row.id),
                                                        ])
                                                    "
                                                    >启用</DropdownMenuItem
                                                ><DropdownMenuItem
                                                    @select="
                                                        batch('disable', [
                                                            Number(row.id),
                                                        ])
                                                    "
                                                    >禁用</DropdownMenuItem
                                                ><DropdownMenuItem
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
                            <tr v-if="!rows.length">
                                <td
                                    :colspan="kind === 'rule' ? 8 : 7"
                                    class="empty"
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
                v-model:page-size="pageSize"
                class="cc-pagination"
                :total="total"
                :disabled="busy || loading"
                numbered
                edge-links
                @update:page="load($event)"
                @update:page-size="load(1)"
            />
        </div>
        <ConfirmDeleteDialog
            :open="deleteOpen"
            :description="`确认删除选中的 ${deleteIds.length} 个${label}？删除后不可恢复。`"
            :loading="busy"
            @confirm="batch('delete', deleteIds)"
            @cancel="!busy && (deleteOpen = false)"
        />
    </section>
</template>

<style scoped>
.user-cc-workspace {
    min-width: 0;
    background: var(--card);
    padding: 12px 14px 24px;
    font-size: var(--console-text-body);
    color: var(--muted-foreground);
}
.cc-tabs {
    display: flex;
    gap: 5px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 20px;
}
.cc-tabs button {
    padding: 8px 20px;
    height: 40px;
    border: 1px solid var(--border);
    border-bottom: 0;
    border-radius: 4px 4px 0 0;
    background: var(--muted);
    margin-bottom: -1px;
}
.cc-tabs button[aria-selected='true'] {
    background: var(--card);
    border-color: var(--primary);
    color: var(--primary);
}
.cc-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
}
.cc-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 40px;
    padding: 0 19px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--card);
    white-space: nowrap;
}
.cc-button.primary {
    background: var(--primary);
    border-color: var(--primary);
    color: var(--primary-foreground);
}
.cc-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
}
.cc-filters :deep([data-slot='select-trigger']) {
    width: 188px;
    height: 40px;
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 0 10px;
    background: var(--card);
}
.cc-input {
    display: flex;
    width: 363px;
    max-width: 100%;
    height: 40px;
    border: 1px solid var(--border);
    border-radius: 4px;
    overflow: hidden;
}
.cc-input span {
    display: flex;
    align-items: center;
    white-space: nowrap;
    padding: 0 10px;
    background: var(--muted);
    border-right: 1px solid var(--border);
}
.cc-input input {
    width: 0;
    flex: 1;
    min-width: 0;
    background: var(--card);
    padding: 0 10px;
    outline: none;
}
.cc-input:focus-within {
    border-color: var(--primary);
}
.cc-input input::placeholder {
    color: var(--muted-foreground);
    opacity: 0.55;
}
.text-action {
    color: var(--primary);
}
button:not(:disabled) {
    cursor: pointer;
}
button:disabled,
input:disabled,
:deep([data-slot='select-trigger']):disabled {
    cursor: not-allowed;
    opacity: 0.5;
}
button:focus-visible,
input:focus-visible,
:deep([data-slot='select-trigger']):focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}
.cc-table-scroll {
    overflow-x: auto;
}
.cc-table {
    width: 100%;
    min-width: 1000px;
    table-layout: fixed;
    font-size: var(--console-text-body);
}
.cc-table th {
    height: 48px;
    padding: 10px 20px;
    text-align: left;
    font-weight: 600;
    background: var(--muted);
    border-bottom: 1px solid var(--border);
}
.cc-table td {
    height: 60px;
    padding: 10px 20px;
    border-bottom: 1px solid var(--border);
    overflow-wrap: anywhere;
}
.cc-table th:first-child,
.cc-table td:first-child {
    text-align: center;
}
.cc-table input {
    width: 19px;
    height: 19px;
    vertical-align: middle;
    accent-color: var(--primary);
}
.cc-table tbody tr:hover {
    background: color-mix(in srgb, var(--primary) 4%, var(--card));
}
.name-cell {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.empty {
    text-align: center;
}
.cc-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.cc-status i {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
    background: #19be6b;
}
.cc-status[data-tone='warning'] i {
    background: #f59e0b;
}
.cc-status[data-tone='danger'] i {
    background: var(--destructive);
}
.row-actions,
.row-more {
    display: flex;
    align-items: center;
    gap: 14px;
    white-space: nowrap;
}
.row-more {
    gap: 4px;
}
.cc-error {
    color: var(--destructive);
    margin: 12px 0;
    font-size: var(--console-text-body);
}
@media (max-width: 640px) {
    .user-cc-workspace {
        padding: 10px;
    }
    .cc-tabs button {
        padding: 8px 16px;
    }
    .cc-filters :deep([data-slot='select-trigger']) {
        flex: 1;
        min-width: 125px;
    }
    .cc-input {
        width: 100%;
    }
}
</style>
