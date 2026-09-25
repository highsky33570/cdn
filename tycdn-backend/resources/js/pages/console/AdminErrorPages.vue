<script setup lang="ts">
import { Plus, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import { errorPages, errorPageLabel, parseErrorPages } from '@/lib/errorPages';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const tab = ref('global'),
    activePage = ref(''),
    config = reactive<Record<string, string>>({});
const loading = ref(true),
    ready = ref(false),
    saving = ref(false),
    saved = ref(false),
    error = ref('');
const pending = reactive<Record<string, string>>({});
const problem = (e: unknown) =>
    e instanceof Error ? e.message : '操作失败，请重试';
const content = (key: string) =>
    config[key] ?? (key === 'waf_block' ? config.p403 : '') ?? '';
async function load() {
    loading.value = true;
    error.value = '';

    try {
        const data = parseErrorPages(
            await apiRequest('/api/admin/error-pages'),
        );

        for (const field of errorPages) {
            const value = data[field.key];

            if (typeof value === 'string') {
                config[field.key] = value;
            }
        }

        ready.value = true;
    } catch (e) {
        error.value = problem(e);
    } finally {
        loading.value = false;
    }
}
function change(key: string, event: Event) {
    const value = (event.target as HTMLTextAreaElement).value;
    config[key] = value;
    pending[key] = value;
    saved.value = false;
    void flush();
}
async function flush() {
    if (saving.value || !Object.keys(pending).length) {
        return;
    }

    saving.value = true;
    error.value = '';

    while (Object.keys(pending).length) {
        const patch = { ...pending };
        Object.keys(pending).forEach((key) => delete pending[key]);

        try {
            await apiRequest('/api/admin/error-pages', {
                method: 'PUT',
                body: JSON.stringify({ patch }),
            });
            saved.value = true;
        } catch (e) {
            const newer = { ...pending };
            Object.assign(pending, patch, newer);
            error.value = problem(e);
            saved.value = false;
            break;
        }
    }

    saving.value = false;
}
const rows = ref<CdnflyRecord[]>([]),
    total = ref(0),
    page = ref(1),
    size = ref(10),
    listLoading = ref(false),
    listError = ref(''),
    selected = ref<(string | number)[]>([]);
const rowKey = (row: CdnflyRecord) => `${row.scope_name}/${row.scope_id}`;
let listToken = 0;
async function loadRows(target = page.value) {
    const token = ++listToken;
    page.value = target;
    listLoading.value = true;
    listError.value = '';
    selected.value = [];

    try {
        const data = await apiRequest(
            `/api/admin/error-pages/overrides?page=${target}&limit=${size.value}`,
        );

        if (token !== listToken) {
            return;
        }

        rows.value = extractCdnflyRows(data);
        total.value = extractCdnflyTotal(data, rows.value.length);

        if (!rows.value.length && target > 1) {
            await loadRows(Math.max(1, Math.ceil(total.value / size.value)));
        }
    } catch (e) {
        if (token === listToken) {
            listError.value = problem(e);
        }
    } finally {
        if (token === listToken) {
            listLoading.value = false;
        }
    }
}
function selectTab(value: string) {
    tab.value = value;

    if (value === 'overrides') {
        void loadRows();
    }
}
function summary(row: CdnflyRecord) {
    try {
        return Object.keys(parseErrorPages(row.value))
            .map(errorPageLabel)
            .join('、');
    } catch {
        return '配置格式错误';
    }
}
const editorOpen = ref(false),
    editing = ref(false),
    editorBusy = ref(false),
    editorError = ref(''),
    scope = ref('node'),
    scopeId = ref(''),
    item = ref('');
const editValues = reactive<Record<string, string>>({});
let originalKeys: string[] = [];
const activeFields = computed(() =>
    errorPages.filter((field) => field.regional && field.key in editValues),
);
const targets = ref<CdnflyRecord[]>([]),
    targetLoading = ref(false),
    targetError = ref('');
let targetToken = 0;
async function loadTargets() {
    const token = ++targetToken;
    targetLoading.value = true;
    targetError.value = '';
    targets.value = [];

    try {
        const data = await apiRequest(
            `/api/admin/${scope.value === 'node' ? 'nodes' : 'regions'}?limit=0`,
        );

        if (token === targetToken) {
            targets.value = extractCdnflyRows(data);
        }
    } catch (e) {
        if (token === targetToken) {
            targetError.value = problem(e);
        }
    } finally {
        if (token === targetToken) {
            targetLoading.value = false;
        }
    }
}
function openEditor(row?: CdnflyRecord) {
    Object.keys(editValues).forEach((key) => delete editValues[key]);
    originalKeys = [];

    if (row) {
        try {
            const data = parseErrorPages(row.value);

            for (const field of errorPages) {
                const value = data[field.key];

                if (field.regional && typeof value === 'string') {
                    editValues[field.key] = value;
                }
            }

            originalKeys = Object.keys(editValues);
        } catch (e) {
            listError.value = problem(e);

            return;
        }
    }

    editing.value = !!row;
    scope.value = String(row?.scope_name ?? 'node');
    scopeId.value = String(row?.scope_id ?? '');
    item.value = '';
    editorError.value = '';
    editorOpen.value = true;
    void loadTargets();
}
function addField() {
    if (!item.value) {
        return;
    }

    editValues[item.value] = content(item.value);
    item.value = '';
}
async function saveOverride() {
    if (
        !Number.isSafeInteger(Number(scopeId.value)) ||
        Number(scopeId.value) < 1 ||
        !activeFields.value.length
    ) {
        editorError.value = '请选择配置范围并至少添加一个配置项';

        return;
    }

    if (
        activeFields.value.some(
            (field) =>
                !editValues[field.key] || editValues[field.key].length > 200000,
        )
    ) {
        editorError.value = '请输入页面内容（最多 200000 字符）';

        return;
    }

    editorBusy.value = true;
    editorError.value = '';

    try {
        await apiRequest(
            `/api/admin/error-pages/overrides/${scope.value}/${scopeId.value}`,
            {
                method: 'PUT',
                body: JSON.stringify({
                    patch: { ...editValues },
                    remove: originalKeys.filter((key) => !(key in editValues)),
                    creating: !editing.value,
                }),
            },
        );
        editorOpen.value = false;
        toast.success('保存成功');
        await loadRows();
    } catch (e) {
        editorError.value = problem(e);
    } finally {
        editorBusy.value = false;
    }
}
const deleteOpen = ref(false),
    deleteBusy = ref(false),
    deleteError = ref(''),
    deleteKeys = ref<string[]>([]);
function askDelete(keys: string[]) {
    deleteKeys.value = keys;
    deleteError.value = '';
    deleteOpen.value = true;
}
async function removeRows() {
    deleteBusy.value = true;
    deleteError.value = '';
    const failed: string[] = [];

    for (const key of deleteKeys.value) {
        try {
            await apiRequest(`/api/admin/error-pages/overrides/${key}`, {
                method: 'DELETE',
            });
        } catch (e) {
            failed.push(key);
            deleteError.value = problem(e);
        }
    }

    await loadRows();
    selected.value = failed.filter((key) =>
        rows.value.some((row) => rowKey(row) === key),
    );
    deleteBusy.value = false;

    if (failed.length) {
        deleteKeys.value = failed;
    } else {
        deleteOpen.value = false;
        toast.success('删除成功');
    }
}
onMounted(load);
</script>
<template>
    <div class="console-page error-pages-workspace p-4 md:p-6">
        <div class="console-panel rounded-xl border bg-card p-4 md:p-5">
            <ConsoleTabs
                :model-value="tab"
                :tabs="[
                    { key: 'global', label: '全局配置' },
                    { key: 'overrides', label: '区域及节点配置' },
                ]"
                @update:model-value="selectTab"
            />
            <section
                v-if="tab === 'global'"
                class="mt-3 max-w-[1000px]"
                role="tabpanel"
                aria-label="全局配置"
            >
                <p
                    v-if="loading"
                    class="py-6 text-sm text-muted-foreground"
                    role="status"
                >
                    正在加载配置…
                </p>
                <div
                    v-if="error"
                    class="my-3 flex items-center gap-2 text-sm text-destructive"
                    role="alert"
                >
                    {{ error
                    }}<Button
                        variant="outline"
                        size="sm"
                        :disabled="loading || saving"
                        @click="ready ? flush() : load()"
                        >重试</Button
                    >
                </div>
                <template v-if="ready">
                    <div class="grid gap-3 sm:grid-cols-[88px_minmax(0,1fr)]">
                        <span class="pt-2 text-sm">错误页面选择</span>
                        <div
                            role="group"
                            aria-label="错误页面选择"
                            class="flex flex-wrap gap-2"
                        >
                            <Button
                                v-for="field in errorPages"
                                :key="field.key"
                                size="sm"
                                :variant="
                                    activePage === field.key
                                        ? 'secondary'
                                        : 'outline'
                                "
                                :aria-pressed="activePage === field.key"
                                @click="activePage = field.key"
                                >{{ field.label }}</Button
                            >
                        </div>
                    </div>
                    <div
                        class="mt-5 grid gap-3 sm:grid-cols-[88px_minmax(0,1fr)]"
                    >
                        <label
                            for="error-page-content"
                            class="pt-2 text-sm sm:text-right"
                            >页面内容</label
                        >
                        <div class="min-w-0">
                            <Textarea
                                v-if="activePage"
                                id="error-page-content"
                                :key="activePage"
                                :value="content(activePage)"
                                aria-label="页面内容"
                                class="h-[380px] w-full resize-y rounded-md border border-input bg-background px-3 py-2 font-mono text-xs leading-5 text-foreground shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                maxlength="200000"
                                spellcheck="false"
                                @change="change(activePage, $event)"
                            /><span
                                v-if="activePage"
                                role="status"
                                class="text-xs text-muted-foreground"
                                >{{
                                    saving
                                        ? '保存中…'
                                        : Object.keys(pending).length
                                          ? '有未保存的更改'
                                          : saved
                                            ? '已保存'
                                            : ''
                                }}</span
                            >
                        </div>
                    </div>
                </template>
            </section>
            <section
                v-else
                class="mt-3"
                role="tabpanel"
                aria-label="区域及节点配置"
            >
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <Button
                        size="sm"
                        :disabled="deleteBusy"
                        @click="openEditor()"
                        ><Plus class="size-4" />新增设置</Button
                    ><span class="text-xs text-muted-foreground"
                        >覆盖配置 {{ total }} 项</span
                    ><Button
                        v-if="selected.length"
                        size="sm"
                        variant="destructive"
                        :disabled="deleteBusy || listLoading"
                        @click="askDelete(selected.map(String))"
                        >删除选中</Button
                    >
                </div>
                <div
                    v-if="listError"
                    role="alert"
                    class="mb-3 text-sm text-destructive"
                >
                    {{ listError
                    }}<Button
                        size="sm"
                        variant="link"
                        :disabled="listLoading"
                        @click="loadRows()"
                        >重试</Button
                    >
                </div>
                <ConsoleDataTable
                    embedded
                    selectable
                    title="区域及节点配置"
                    :columns="[
                        { key: 'scope', label: '配置范围' },
                        { key: 'settings', label: '配置项' },
                    ]"
                    :data="{
                        rows,
                        total,
                        page,
                        pageSize: size,
                        loading: listLoading,
                    }"
                    :get-row-key="rowKey"
                    :selected="selected"
                    :selection-disabled="deleteBusy"
                    empty-text="暂无数据"
                    @update:selected="selected = $event"
                >
                    <template #cell-scope="{ row }"
                        >{{ row.scope_name === 'node' ? '节点' : '区域' }} ·
                        {{
                            row.node_name ?? row.region_name ?? row.scope_id
                        }}</template
                    ><template #cell-settings="{ row }"
                        ><span class="break-words">{{
                            summary(row)
                        }}</span></template
                    ><template #row-actions="{ row }"
                        ><Button
                            size="sm"
                            variant="ghost"
                            :disabled="deleteBusy || listLoading"
                            @click="openEditor(row)"
                            >编辑</Button
                        ><Button
                            size="sm"
                            variant="ghost"
                            class="text-destructive"
                            :disabled="deleteBusy || listLoading"
                            @click="askDelete([rowKey(row)])"
                            >删除</Button
                        ></template
                    >
                </ConsoleDataTable>
                <ConsolePagination
                    :total="total"
                    :page="page"
                    :previous-disabled="page <= 1 || listLoading"
                    :next-disabled="page * size >= total || listLoading"
                    @previous="loadRows(page - 1)"
                    @next="loadRows(page + 1)"
                />
            </section>
        </div>
        <Dialog
            :open="editorOpen"
            @update:open="!editorBusy && (editorOpen = $event)"
            ><DialogScrollContent class="sm:max-w-3xl"
                ><DialogHeader
                    ><DialogTitle>{{
                        editing ? '编辑设置' : '新增设置'
                    }}</DialogTitle
                    ><DialogDescription
                        >为指定节点或区域覆盖错误页面。</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-4" @submit.prevent="saveOverride">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-2 text-sm"
                            >配置范围<SelectField
                                v-model="scope"
                                aria-label="配置范围"
                                :disabled="editing || editorBusy"
                                class="h-9 rounded-md border border-input bg-background px-3"
                                @change="
                                    scopeId = '';
                                    loadTargets();
                                "
                            >
                                <SelectOption value="node">节点</SelectOption>
                                <SelectOption value="region">区域</SelectOption>
                            </SelectField></label
                        ><label class="grid gap-2 text-sm"
                            >{{ scope === 'node' ? '节点' : '区域'
                            }}<SelectField
                                v-model="scopeId"
                                :aria-label="scope === 'node' ? '节点' : '区域'"
                                required
                                :disabled="
                                    editing || editorBusy || targetLoading
                                "
                                class="h-9 rounded-md border border-input bg-background px-3"
                            >
                                <SelectOption value="" disabled>
                                    {{ targetLoading ? '加载中…' : '请选择' }}
                                </SelectOption>
                                <SelectOption
                                    v-if="
                                        editing &&
                                        !targets.some(
                                            (row) => String(row.id) === scopeId,
                                        )
                                    "
                                    :value="scopeId"
                                >
                                    #{{ scopeId }}
                                </SelectOption>
                                <SelectOption
                                    v-for="row in targets"
                                    :key="String(row.id)"
                                    :value="String(row.id)"
                                >
                                    {{ row.name ?? row.hostname ?? row.ip }}
                                    (#{{ row.id }})
                                </SelectOption>
                            </SelectField></label
                        >
                    </div>
                    <div
                        v-if="targetError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ targetError
                        }}<Button
                            type="button"
                            variant="link"
                            @click="loadTargets"
                            >重试</Button
                        >
                    </div>
                    <div class="flex gap-2">
                        <SelectField
                            v-model="item"
                            aria-label="配置项"
                            :disabled="editorBusy"
                            class="h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <SelectOption value="">请选择配置项</SelectOption>
                            <SelectOption
                                v-for="field in errorPages.filter(
                                    (field) =>
                                        field.regional &&
                                        !(field.key in editValues),
                                )"
                                :key="field.key"
                                :value="field.key"
                            >
                                {{ field.label }}
                            </SelectOption></SelectField
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="!item || editorBusy"
                            @click="addField"
                            >添加</Button
                        >
                    </div>
                    <section
                        v-for="field in activeFields"
                        :key="field.key"
                        class="grid gap-2 rounded-lg border p-3"
                    >
                        <div class="flex items-center justify-between">
                            <label
                                :for="`override-${field.key}`"
                                class="text-sm font-medium"
                                >{{ field.label }}</label
                            ><Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                :aria-label="`移除${field.label}`"
                                :disabled="editorBusy"
                                @click="delete editValues[field.key]"
                                ><X class="size-4"
                            /></Button>
                        </div>
                        <Textarea
                            :id="`override-${field.key}`"
                            v-model="editValues[field.key]"
                            :aria-label="field.label"
                            :disabled="editorBusy"
                            required
                            maxlength="200000"
                            spellcheck="false"
                            class="h-56 w-full resize-y rounded-md border border-input bg-background px-3 py-2 font-mono text-xs leading-5 text-foreground outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </section>
                    <p
                        v-if="editorError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ editorError }}
                    </p>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="editorBusy"
                            @click="editorOpen = false"
                            >取消</Button
                        ><Button type="submit" :disabled="editorBusy">{{
                            editorBusy ? '保存中…' : '确定'
                        }}</Button></DialogFooter
                    >
                </form></DialogScrollContent
            ></Dialog
        >
        <ConfirmDeleteDialog
            :open="deleteOpen"
            :description="`确认删除 ${deleteKeys.length} 项覆盖配置？删除后恢复继承的错误页面。`"
            :loading="deleteBusy"
            :error="deleteError"
            @confirm="removeRows"
            @cancel="!deleteBusy && (deleteOpen = false)"
        />
    </div>
</template>
