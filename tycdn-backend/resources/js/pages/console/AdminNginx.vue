<script setup lang="ts">
import { Plus, Trash2, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import NginxField from '@/components/console/NginxField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import {
    nginxSections,
    nginxFields,
    getValue,
    setValue,
    object,
    fieldError,
} from '@/lib/nginxSettings';
import type { NginxField as Field } from '@/lib/nginxSettings';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const tab = ref('global'),
    section = ref('worker'),
    config = reactive<CdnflyRecord>({}),
    loading = ref(false),
    ready = ref(false),
    saving = ref(false),
    saved = ref(false),
    error = ref('');
const errors = reactive<Record<string, string>>({});
const currentSection = computed(() => nginxSections[section.value]);
const problem = (e: unknown) =>
    e instanceof Error ? e.message : '操作失败，请重试';
let pending: Record<string, string> = {};
onMounted(load);
async function load() {
    loading.value = true;
    error.value = '';

    try {
        const data = await apiRequest<CdnflyRecord>('/api/admin/nginx');
        Object.assign(config, data);
        ready.value = true;
    } catch (e) {
        error.value = problem(e);
    } finally {
        loading.value = false;
    }
}
async function change(field: Field, value: string) {
    setValue(config, field.path, value);
    saved.value = false;
    errors[field.path] = fieldError(field, value);

    if (errors[field.path]) {
        delete pending[field.path];

        return;
    }

    pending[field.path] = value.trim();
    await flush();
}
async function flush() {
    if (saving.value || !Object.keys(pending).length) {
        return;
    }

    saving.value = true;
    error.value = '';

    while (Object.keys(pending).length) {
        const patch = pending;
        pending = {};

        try {
            await apiRequest('/api/admin/nginx', {
                method: 'PUT',
                body: JSON.stringify({ patch }),
            });
            saved.value = true;
        } catch (e) {
            pending = { ...patch, ...pending };

            for (const path of Object.keys(pending)) {
                if (errors[path]) {
                    delete pending[path];
                }
            }

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
    listLoading = ref(false),
    listError = ref(''),
    selected = ref<string[]>([]);
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
            `/api/admin/nginx/overrides?page=${target}&limit=10`,
        );

        if (token !== listToken) {
            return;
        }

        rows.value = extractCdnflyRows(data);
        total.value = extractCdnflyTotal(data, rows.value.length);

        if (!rows.value.length && target > 1) {
            await loadRows(Math.max(1, Math.ceil(total.value / 10)));
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
function selectTab(next: string) {
    tab.value = next;

    if (next === 'overrides') {
        void loadRows();
    }
}
function parseRow(row: CdnflyRecord): CdnflyRecord {
    const data =
        typeof row.value === 'string' ? JSON.parse(row.value) : row.value;

    if (!data || typeof data !== 'object' || Array.isArray(data)) {
        throw new Error('配置格式错误，无法编辑');
    }

    return data;
}
function summary(row: CdnflyRecord) {
    try {
        const data = parseRow(row);

        return Object.entries(data)
            .flatMap(([key, value]) =>
                ['http', 'stream'].includes(key)
                    ? Object.keys(object(value)).map((name) => `${key}.${name}`)
                    : [key],
            )
            .map(
                (path) =>
                    nginxFields.find((f) => f.path === path)?.label ?? path,
            )
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
const editValues = reactive<Record<string, string>>({}),
    editErrors = reactive<Record<string, string>>({});
let originalPaths: string[] = [];
const activeFields = computed(() =>
    nginxFields.filter((f) => f.path in editValues),
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
    Object.keys(editErrors).forEach((key) => delete editErrors[key]);
    originalPaths = [];

    if (row) {
        try {
            const data = parseRow(row);

            for (const field of nginxFields) {
                const value = getValue(data, field.path);

                if (value !== undefined) {
                    editValues[field.path] = String(value);
                }
            }

            originalPaths = Object.keys(editValues);
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
    const field = nginxFields.find((f) => f.path === item.value);

    if (!field) {
        return;
    }

    editValues[field.path] = String(
        getValue(config, field.path) ??
            (field.kind === 'toggle' ? field.off : ''),
    );
    item.value = '';
}
async function saveOverride() {
    if (
        !Number.isInteger(Number(scopeId.value)) ||
        Number(scopeId.value) < 1 ||
        !activeFields.value.length
    ) {
        editorError.value = '请选择配置范围并至少添加一个配置项';

        return;
    }

    for (const field of activeFields.value) {
        editErrors[field.path] = fieldError(field, editValues[field.path]);
    }

    if (activeFields.value.some((field) => editErrors[field.path])) {
        editorError.value = '请检查配置值';

        return;
    }

    editorBusy.value = true;
    editorError.value = '';

    try {
        await apiRequest(
            `/api/admin/nginx/overrides/${scope.value}/${scopeId.value}`,
            {
                method: 'PUT',
                body: JSON.stringify({
                    patch: { ...editValues },
                    remove: originalPaths.filter(
                        (path) => !(path in editValues),
                    ),
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
    const failed: string[] = [];

    for (const key of deleteKeys.value) {
        try {
            await apiRequest(`/api/admin/nginx/overrides/${key}`, {
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
</script>

<template>
    <div class="flex flex-1 flex-col p-3 md:p-5">
        <section
            class="nginx-page console-panel rounded-xl border bg-card text-card-foreground"
        >
            <ConsoleTabs
                class="mb-3"
                :model-value="tab"
                :tabs="[
                    { key: 'global', label: '全局配置' },
                    { key: 'overrides', label: '区域及节点配置' },
                ]"
                @update:model-value="selectTab"
            >
                <template #actions
                    ><span
                        v-if="tab === 'global'"
                        class="ng-save-state"
                        role="status"
                        >{{
                            saving
                                ? '保存中…'
                                : Object.values(errors).some(Boolean)
                                  ? '请检查配置值'
                                  : saved
                                    ? '已保存'
                                    : ''
                        }}</span
                    ></template
                >
            </ConsoleTabs>
            <div
                v-if="tab === 'global'"
                id="ng-panel-global"
                role="tabpanel"
                aria-label="全局配置"
                :aria-busy="loading || saving"
            >
                <nav
                    class="ng-subtabs"
                    role="tablist"
                    aria-label="全局配置分类"
                >
                    <Button
                        size="sm"
                        variant="outline"
                        v-for="(entry, key) in nginxSections"
                        :id="`ng-tab-${key}`"
                        :key="key"
                        role="tab"
                        :aria-selected="section === key"
                        :aria-controls="`ng-panel-${key}`"
                        :class="
                            section === key
                                ? 'border-primary bg-background text-primary'
                                : 'bg-muted text-muted-foreground'
                        "
                        @click="section = key"
                    >
                        {{ entry.label }}
                    </Button>
                </nav>
                <div v-if="error" role="alert" class="ng-error">
                    {{ error
                    }}<Button
                        size="sm"
                        variant="outline"
                        @click="ready ? flush() : load()"
                        >重试</Button
                    >
                </div>
                <p v-if="loading" class="ng-loading">加载中…</p>
                <div
                    :id="`ng-panel-${section}`"
                    class="ng-global"
                    role="tabpanel"
                    :aria-labelledby="`ng-tab-${section}`"
                >
                    <header class="ng-section-title">
                        <h2>{{ currentSection.title }}</h2>
                        <p>{{ currentSection.help }}</p>
                    </header>
                    <fieldset :disabled="!ready">
                        <div class="ng-cards">
                            <article
                                v-for="card in currentSection.cards"
                                :key="card.title"
                                class="ng-card"
                                :class="{ 'ng-wide': card.wide }"
                            >
                                <h3>{{ card.title }}</h3>
                                <p>{{ card.help }}</p>
                                <div :class="{ 'ng-columns': card.columns }">
                                    <NginxField
                                        v-for="field in card.fields"
                                        :key="field.path"
                                        :field="field"
                                        :value="getValue(config, field.path)"
                                        :error="errors[field.path]"
                                        @change="change(field, $event)"
                                    />
                                </div>
                            </article>
                        </div>
                    </fieldset>
                </div>
            </div>
            <div
                v-else
                id="ng-panel-overrides"
                class="ng-overrides"
                role="tabpanel"
                aria-label="区域及节点配置"
                :aria-busy="listLoading || editorBusy || deleteBusy"
            >
                <header class="ng-list-heading">
                    <div>
                        <h2>区域及节点配置</h2>
                        <p>
                            为指定区域或节点覆盖全局 Nginx
                            配置，只保存需要覆盖的配置项。
                        </p>
                    </div>
                    <div class="ng-actions">
                        <Button
                            size="sm"
                            variant="outline"
                            v-if="selected.length"
                            :disabled="listLoading"
                            @click="askDelete([...selected])"
                        >
                            <Trash2 />删除选中</Button
                        ><Button size="sm" @click="openEditor()">
                            <Plus />新增设置
                        </Button>
                    </div>
                </header>
                <div v-if="listError" role="alert" class="ng-error">
                    {{ listError
                    }}<Button size="sm" variant="outline" @click="loadRows()"
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
                        pageSize: 10,
                        loading: listLoading,
                    }"
                    :get-row-key="rowKey"
                    :selected="selected"
                    :selection-disabled="deleteBusy"
                    empty-text="暂无数据"
                    @update:selected="selected = $event.map(String)"
                >
                    <template #cell-scope="{ row }"
                        >{{ row.scope_name === 'node' ? '节点' : '区域' }}
                        {{ row.scope_id }}</template
                    >
                    <template #cell-settings="{ row }">{{
                        summary(row)
                    }}</template>
                    <template #row-actions="{ row }">
                        <Button
                            size="sm"
                            variant="ghost"
                            :disabled="listLoading || deleteBusy"
                            @click="openEditor(row)"
                            >编辑</Button
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            class="text-destructive"
                            :disabled="listLoading || deleteBusy"
                            @click="askDelete([rowKey(row)])"
                            >删除</Button
                        >
                    </template>
                </ConsoleDataTable>
                <footer v-if="total" class="ng-pager">
                    <span>共 {{ total }} 条</span
                    ><Button
                        size="sm"
                        variant="outline"
                        :disabled="page <= 1 || listLoading"
                        @click="loadRows(page - 1)"
                    >
                        上一页</Button
                    ><span class="ng-page-number">{{ page }}</span
                    ><Button
                        size="sm"
                        variant="outline"
                        :disabled="page * 10 >= total || listLoading"
                        @click="loadRows(page + 1)"
                    >
                        下一页</Button
                    ><span>10 条/页</span>
                </footer>
            </div>
        </section>
        <Dialog v-model:open="editorOpen"
            ><DialogScrollContent class="ng-modal sm:max-w-3xl"
                ><DialogHeader
                    ><DialogTitle>{{
                        editing ? '编辑设置' : '新增设置'
                    }}</DialogTitle
                    ><DialogDescription
                        >选择需要覆盖的 Nginx
                        配置项，其余沿用全局配置。</DialogDescription
                    ></DialogHeader
                >
                <div v-if="editorError" role="alert" class="ng-error">
                    {{ editorError }}
                </div>
                <div v-if="targetError" role="alert" class="ng-error">
                    {{ targetError
                    }}<Button size="sm" variant="outline" @click="loadTargets()"
                        >重试</Button
                    >
                </div>
                <form @submit.prevent="saveOverride">
                    <fieldset :disabled="editorBusy">
                        <div class="ng-targets">
                            <label
                                >配置范围<select
                                    class="h-9 min-w-0 rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    v-model="scope"
                                    aria-label="配置范围"
                                    :disabled="editing"
                                    @change="
                                        scopeId = '';
                                        loadTargets();
                                    "
                                >
                                    <option value="node">节点</option>
                                    <option value="region">区域</option>
                                </select></label
                            ><label
                                >选择目标<select
                                    class="h-9 min-w-0 rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    v-model="scopeId"
                                    aria-label="选择目标"
                                    :disabled="editing || targetLoading"
                                    required
                                >
                                    <option value="">
                                        {{
                                            targetLoading ? '加载中…' : '请选择'
                                        }}
                                    </option>
                                    <option
                                        v-if="
                                            editing &&
                                            !targets.some(
                                                (target) =>
                                                    String(target.id) ===
                                                    scopeId,
                                            )
                                        "
                                        :value="scopeId"
                                    >
                                        {{ scopeId }}
                                    </option>
                                    <option
                                        v-for="target in targets"
                                        :key="String(target.id)"
                                        :value="String(target.id)"
                                    >
                                        {{ target.name }} ({{ target.id }})
                                    </option>
                                </select></label
                            >
                        </div>
                        <div class="ng-add-field">
                            <select
                                class="h-9 min-w-0 rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                v-model="item"
                                aria-label="配置项"
                            >
                                <option value="">请选择配置项</option>
                                <optgroup
                                    v-for="(group, key) in nginxSections"
                                    :key="key"
                                    :label="group.label"
                                >
                                    <option
                                        v-for="field in group.cards.flatMap(
                                            (card) => card.fields,
                                        )"
                                        :key="field.path"
                                        :value="field.path"
                                        :disabled="field.path in editValues"
                                    >
                                        {{ field.label }}
                                    </option>
                                </optgroup></select
                            ><Button
                                size="sm"
                                variant="outline"
                                type="button"
                                :disabled="!item"
                                @click="addField()"
                            >
                                <Plus />添加
                            </Button>
                        </div>
                        <div
                            v-for="field in activeFields"
                            :key="field.path"
                            class="ng-edit-field"
                        >
                            <div class="ng-edit-value">
                                <span class="ng-context">{{
                                    field.path.startsWith('http.')
                                        ? 'Http'
                                        : field.path.startsWith('stream.')
                                          ? 'Stream'
                                          : 'Worker'
                                }}</span
                                ><NginxField
                                    :field="field"
                                    :value="editValues[field.path]"
                                    :error="editErrors[field.path]"
                                    context="override"
                                    @change="
                                        editValues[field.path] = $event;
                                        editErrors[field.path] = fieldError(
                                            field,
                                            $event,
                                        );
                                    "
                                />
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                type="button"
                                :aria-label="`移除 ${field.path}`"
                                @click="
                                    delete editValues[field.path];
                                    delete editErrors[field.path];
                                "
                            >
                                <X />
                            </Button>
                        </div>
                        <div class="ng-modal-actions">
                            <Button
                                size="sm"
                                variant="outline"
                                type="button"
                                @click="editorOpen = false"
                            >
                                取消</Button
                            ><Button
                                size="sm"
                                variant="default"
                                type="submit"
                                :disabled="
                                    editorBusy || targetLoading || !!targetError
                                "
                            >
                                确定
                            </Button>
                        </div>
                    </fieldset>
                </form>
            </DialogScrollContent></Dialog
        >
        <ConfirmDeleteDialog
            :open="deleteOpen"
            :description="`确认删除 ${deleteKeys.length} 条覆盖配置？删除后将沿用全局 Nginx 设置。`"
            :loading="deleteBusy"
            :error="deleteError"
            @confirm="removeRows"
            @cancel="!deleteBusy && (deleteOpen = false)"
        />
    </div>
</template>

<style>
.nginx-page {
    min-width: 0;
    padding: 16px;
}
.ng-save-state {
    color: var(--muted-foreground);
    font-size: 12px;
}
.ng-subtabs {
    display: flex;
    gap: 4px;
    margin: 0 0 29px;
}
.ng-subtabs > button {
    border-bottom: 0;
    border-radius: 6px 6px 0 0;
}
.ng-global,
.ng-overrides {
    max-width: 967px;
}
.nginx-page h2,
.nginx-page h3 {
    font-size: 14px;
    font-weight: 600;
}
.nginx-page p {
    margin: 4px 0 10px;
    color: var(--muted-foreground);
    font-size: 12px;
    line-height: 1.6;
}
.ng-section-title {
    border-left: 3px solid var(--primary);
    padding-left: 9px;
    margin-bottom: 14px;
}
.ng-section-title p {
    margin-bottom: 0;
}
.nginx-page fieldset,
.ng-modal fieldset {
    margin: 0;
    padding: 0;
    min-width: 0;
    border: 0;
}
.ng-cards,
.ng-columns,
.ng-targets {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
.ng-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px;
    min-width: 0;
}
.ng-wide {
    grid-column: 1/-1;
}
.ng-columns {
    gap: 0 12px;
}
.ng-field {
    display: grid;
    grid-template-columns: 145px minmax(0, 1fr);
    align-items: center;
    gap: 8px;
    margin-top: 11px;
    font-size: 12px;
}
.ng-field > label {
    overflow-wrap: anywhere;
    line-height: 1.5;
}
.ng-control {
    min-width: 0;
}
.ng-input {
    display: flex;
    min-width: 0;
}
.ng-input input {
    flex: 1;
}
.ng-versions {
    display: flex;
    gap: 7px;
}
.nginx-page .ng-field-help,
.ng-modal .ng-field-help {
    font-size: 12px;
    margin: 5px 0 0;
    color: var(--muted-foreground);
    line-height: 1.6;
}
.nginx-page .ng-field-error,
.ng-modal .ng-field-error {
    color: var(--destructive);
    margin: 5px 0 0;
    font-size: 12px;
}
.ng-error {
    padding: 10px;
    background: color-mix(in oklab, var(--destructive) 10%, transparent);
    color: var(--destructive);
    margin: 10px 0;
    border-radius: 6px;
}
.ng-error button {
    margin-left: 10px;
}
.ng-loading {
    padding: 15px;
}
.ng-list-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin: 8px 0 12px;
}
.ng-list-heading p {
    margin-bottom: 0;
}
.ng-actions {
    display: flex;
    gap: 8px;
}
.ng-pager {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
    padding: 16px 0 0;
    font-size: 14px;
}
.ng-modal .ng-targets label {
    display: grid;
    gap: 6px;
}
.ng-add-field {
    display: flex;
    gap: 8px;
    margin: 16px 0;
}
.ng-add-field select {
    flex: 1;
}
.ng-edit-field {
    display: flex;
    gap: 10px;
    padding: 12px;
    margin-top: 10px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
}
.ng-edit-value {
    min-width: 0;
    flex: 1;
}
.ng-context {
    font-size: 12px;
    color: var(--muted-foreground);
}
.ng-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 18px;
}
@media (max-width: 760px) {
    .ng-cards,
    .ng-columns,
    .ng-targets {
        grid-template-columns: 1fr;
    }
    .ng-wide {
        grid-column: auto;
    }
    .ng-field {
        grid-template-columns: 135px minmax(0, 1fr);
    }
    .ng-list-heading,
    .ng-pager {
        flex-wrap: wrap;
    }
    .ng-modal .ng-field {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 370px) {
    .ng-field {
        grid-template-columns: 1fr;
        gap: 5px;
    }
}
</style>
