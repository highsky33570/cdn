<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
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
import { getErrorMessage, textValue } from '@/lib/cdnRecord';
import {
    listUserAcls,
    updateUserAcl,
    deleteUserAcl,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnUserApi';
import type { CdnflyRecord } from '@/lib/cdnUserApi';

const emit = defineEmits<{ create: []; manage: [row: CdnflyRecord] }>();
const rows = ref<CdnflyRecord[]>([]),
    selected = ref<number[]>([]);
const loading = ref(false),
    busy = ref(false),
    error = ref('');
const page = ref(1),
    pageSize = ref(10),
    total = ref(0);
const filters = reactive({ name: '', id: '', enable: '' });
const readOnly = (row: CdnflyRecord) => row.scope === 'global';
const selectableRows = computed(() =>
    rows.value.filter((row) => !readOnly(row)),
);
const allSelected = computed(
    () =>
        selectableRows.value.length > 0 &&
        selectableRows.value.every((row) =>
            selected.value.includes(Number(row.id)),
        ),
);
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
    };

    for (const [key, value] of Object.entries(filters)) {
        if (value.trim()) {
            query[key] = value.trim();
        }
    }

    try {
        const data = await listUserAcls(query);

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
function clearFilters() {
    Object.assign(filters, { name: '', id: '', enable: '' });
    void load(1);
}
const deleteOpen = ref(false),
    deleteIds = ref<number[]>([]);
function askDelete(ids = [...selected.value]) {
    if (!ids.length) {
        toast.warning('请选择要删除的 ACL');

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
        toast.warning('请选择要操作的 ACL');

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
                rows.value.some((row) => Number(row.id) === id && readOnly(row))
            ) {
                throw new Error('全局 ACL 不可修改');
            }

            if (action === 'delete') {
                await deleteUserAcl(id);
            } else {
                await updateUserAcl(id, {
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
</script>

<template>
    <section
        class="user-acl-workspace"
        :aria-busy="loading || busy"
        aria-label="ACL 规则"
    >
        <div class="acl-actions">
            <button
                data-slot="console-action"
                type="button"
                class="acl-button primary"
                :disabled="busy || loading"
                @click="emit('create')"
            >
                添加ACL
            </button>
            <DropdownMenu
                ><DropdownMenuTrigger as-child
                    ><button
                        data-slot="console-action"
                        type="button"
                        class="acl-button"
                        :disabled="busy || loading"
                    >
                        更多操作
                        <ChevronDown :size="16" /></button></DropdownMenuTrigger
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
        <form class="acl-filters" @submit.prevent="load(1)">
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
            <label data-slot="console-input-group" class="acl-input"
                ><span>ACL名称</span
                ><Input
                    v-model="filters.name"
                    placeholder="请输入ACL名称,模糊搜索"
                    :disabled="busy"
                    @input="search"
            /></label>
            <label data-slot="console-input-group" class="acl-input"
                ><span>ACL ID</span
                ><Input
                    v-model="filters.id"
                    placeholder="请输入ACL ID"
                    inputmode="numeric"
                    :disabled="busy"
                    @input="search"
            /></label>
            <button
                data-slot="console-link"
                type="button"
                class="text-action"
                :disabled="busy"
                @click="clearFilters"
            >
                清除
            </button>
            <button
                type="submit"
                class="sr-only"
                tabindex="-1"
                :disabled="busy"
            >
                查询
            </button>
        </form>
        <p v-if="error" class="acl-error" role="alert">
            {{ error }}
            <button
                data-slot="console-link"
                type="button"
                class="text-action"
                :disabled="busy || loading"
                @click="load()"
            >
                重试
            </button>
        </p>
        <div class="acl-table-scroll">
            <table class="acl-table">
                <colgroup>
                    <col style="width: 13%" />
                    <col style="width: 14%" />
                    <col style="width: 21%" />
                    <col style="width: 14%" />
                    <col style="width: 17%" />
                    <col style="width: 21%" />
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
                                    busy || loading || !selectableRows.length
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
                        <th>备注</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="6" class="empty"><span>加载中…</span></td>
                    </tr>
                    <template v-else>
                        <tr v-for="row in rows" :key="Number(row.id)">
                            <td>
                                <CheckboxField
                                    v-model="selected"
                                    :value="Number(row.id)"
                                    :aria-label="`选择 ${row.id}`"
                                    :disabled="busy || readOnly(row)"
                                    :title="
                                        readOnly(row)
                                            ? '全局 ACL 只读'
                                            : undefined
                                    "
                                />
                            </td>
                            <td>{{ row.id }}</td>
                            <td class="name-cell" :title="textValue(row.name)">
                                {{ row.name || '-' }}
                            </td>
                            <td
                                class="remark-cell"
                                :title="textValue(row.des ?? row.remark)"
                            >
                                {{ row.des || row.remark || '-' }}
                            </td>
                            <td>
                                <span
                                    class="acl-status"
                                    :class="{
                                        'disabled-status':
                                            Number(row.enable) !== 1,
                                    }"
                                    ><i />{{
                                        Number(row.enable) === 1
                                            ? '正常'
                                            : '禁用'
                                    }}</span
                                >
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button
                                        data-slot="console-link"
                                        type="button"
                                        class="text-action"
                                        :disabled="busy || readOnly(row)"
                                        @click="emit('manage', row)"
                                    >
                                        管理</button
                                    ><DropdownMenu
                                        ><DropdownMenuTrigger as-child
                                            ><button
                                                data-slot="console-link"
                                                type="button"
                                                class="text-action row-more"
                                                :disabled="
                                                    busy || readOnly(row)
                                                "
                                                :aria-label="`更多操作 ${row.id}`"
                                            >
                                                更多
                                                <ChevronDown
                                                    :size="14"
                                                /></button></DropdownMenuTrigger
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
                                                    askDelete([Number(row.id)])
                                                "
                                                >删除</DropdownMenuItem
                                            ></DropdownMenuContent
                                        ></DropdownMenu
                                    >
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="6" class="empty">
                                <span>{{
                                    error ? '加载失败，请重试' : '暂无数据'
                                }}</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <PackagePagination
            v-model:page="page"
            v-model:page-size="pageSize"
            class="acl-pagination"
            :total="total"
            :disabled="busy || loading"
            numbered
            edge-links
            @update:page="load($event)"
            @update:page-size="load(1)"
        />
        <ConfirmDeleteDialog
            :open="deleteOpen"
            :description="`确认删除选中的 ${deleteIds.length} 个 ACL？删除后不可恢复。`"
            :loading="busy"
            @confirm="batch('delete', deleteIds)"
            @cancel="!busy && (deleteOpen = false)"
        />
    </section>
</template>

<style scoped>
.user-acl-workspace {
    min-width: 0;
    background: var(--card);
    padding: 12px 11px 20px;
    font-size: 16px;
    color: var(--muted-foreground);
}
.acl-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
}
.acl-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 40px;
    padding: 0 19px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--card);
    font-size: 16px;
    white-space: nowrap;
}
.acl-button.primary {
    background: var(--primary);
    border-color: var(--primary);
    color: var(--primary-foreground);
}
.acl-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
}
.acl-filters :deep([data-slot='select-trigger']) {
    width: 188px;
    height: 40px;
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 0 10px;
    background: var(--card);
}
.acl-input {
    display: flex;
    width: 363px;
    max-width: 100%;
    height: 40px;
    border: 1px solid var(--border);
    border-radius: 4px;
    overflow: hidden;
}
.acl-input span {
    display: flex;
    align-items: center;
    white-space: nowrap;
    padding: 0 10px;
    background: var(--muted);
    border-right: 1px solid var(--border);
}
.acl-input input {
    width: 0;
    flex: 1;
    min-width: 0;
    background: var(--card);
    padding: 0 10px;
    outline: none;
}
.acl-input:focus-within {
    border-color: var(--primary);
}
.acl-input input::placeholder {
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
.acl-table-scroll {
    overflow-x: auto;
}
.acl-table {
    width: 100%;
    min-width: 900px;
    table-layout: fixed;
    font-size: 16px;
}
.acl-table th {
    height: 48px;
    padding: 10px 20px;
    text-align: left;
    font-weight: 600;
    background: var(--muted);
    border-bottom: 1px solid var(--border);
}
.acl-table td {
    height: 60px;
    padding: 10px 20px;
    border-bottom: 1px solid var(--border);
    overflow-wrap: anywhere;
}
.acl-table th:first-child,
.acl-table td:first-child {
    text-align: center;
}
.acl-table input {
    width: 19px;
    height: 19px;
    vertical-align: middle;
    accent-color: var(--primary);
}
.acl-table tbody tr:hover {
    background: color-mix(in srgb, var(--primary) 4%, var(--card));
}
.name-cell,
.remark-cell {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.empty {
    text-align: center;
}
.acl-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.acl-status i {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
    background: #19be6b;
}
.acl-status.disabled-status i {
    background: #f59e0b;
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
.acl-error {
    color: var(--destructive);
    margin: 12px 0;
    font-size: 14px;
}
@media (max-width: 640px) {
    .user-acl-workspace {
        padding: 10px;
    }
    .acl-filters :deep([data-slot='select-trigger']) {
        width: 100%;
    }
    .acl-input {
        width: 100%;
    }
    .acl-table .empty {
        padding: 0;
        text-align: left;
    }
    .empty span {
        display: block;
        position: sticky;
        left: 0;
        width: calc(100vw - 52px);
        text-align: center;
    }
}
</style>
