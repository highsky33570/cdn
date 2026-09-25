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
        class="console-user-acl-workspace user-acl-workspace"
        :aria-busy="loading || busy"
        aria-label="ACL 规则"
    >
        <div class="acl-actions">
            <Button
                variant="default"
                data-slot="console-action"
                type="button"
                class="acl-button primary"
                :disabled="busy || loading"
                @click="emit('create')"
            >
                添加ACL
            </Button>
            <DropdownMenu
                ><DropdownMenuTrigger as-child
                    ><Button
                        variant="outline"
                        data-slot="console-action"
                        type="button"
                        class="acl-button"
                        :disabled="busy || loading"
                    >
                        更多操作
                        <ChevronDown :size="16" /></Button></DropdownMenuTrigger
                ><DropdownMenuContent
                    class="console-user-acl-workspace"
                    align="start"
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
        <p data-typography="body" v-if="error" class="acl-error" role="alert">
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
                                    <Button
                                        variant="link"
                                        size="inline"
                                        data-slot="console-link"
                                        type="button"
                                        class="text-action"
                                        :disabled="busy || readOnly(row)"
                                        @click="emit('manage', row)"
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
                                                    busy || readOnly(row)
                                                "
                                                :aria-label="`更多操作 ${row.id}`"
                                            >
                                                更多
                                                <ChevronDown
                                                    :size="
                                                        14
                                                    " /></Button></DropdownMenuTrigger
                                        ><DropdownMenuContent
                                            class="console-user-acl-workspace"
                                            align="end"
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
