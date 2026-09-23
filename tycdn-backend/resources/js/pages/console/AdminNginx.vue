<script setup lang="ts">
import { Plus, Trash2, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import NginxField from '@/components/console/NginxField.vue';
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
        <section class="nginx-page">
            <nav class="ng-tabs" role="tablist" aria-label="Nginx 配置">
                <button
                    v-for="entry in [
                        { key: 'global', label: '全局配置' },
                        { key: 'overrides', label: '区域及节点配置' },
                    ]"
                    :id="`ng-tab-${entry.key}`"
                    :key="entry.key"
                    role="tab"
                    :aria-selected="tab === entry.key"
                    :aria-controls="`ng-panel-${entry.key}`"
                    :class="{ active: tab === entry.key }"
                    @click="selectTab(entry.key)"
                >
                    {{ entry.label }}</button
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
                >
            </nav>
            <div
                v-if="tab === 'global'"
                id="ng-panel-global"
                role="tabpanel"
                aria-labelledby="ng-tab-global"
                :aria-busy="loading || saving"
            >
                <nav
                    class="ng-subtabs"
                    role="tablist"
                    aria-label="全局配置分类"
                >
                    <button
                        v-for="(entry, key) in nginxSections"
                        :id="`ng-tab-${key}`"
                        :key="key"
                        role="tab"
                        :aria-selected="section === key"
                        :aria-controls="`ng-panel-${key}`"
                        :class="{ active: section === key }"
                        @click="section = key"
                    >
                        {{ entry.label }}
                    </button>
                </nav>
                <div v-if="error" role="alert" class="ng-error">
                    {{ error
                    }}<button @click="ready ? flush() : load()">重试</button>
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
                aria-labelledby="ng-tab-overrides"
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
                        <button
                            v-if="selected.length"
                            :disabled="listLoading"
                            @click="askDelete([...selected])"
                        >
                            <Trash2 />删除选中</button
                        ><button class="ng-primary" @click="openEditor()">
                            <Plus />新增设置
                        </button>
                    </div>
                </header>
                <div v-if="listError" role="alert" class="ng-error">
                    {{ listError }}<button @click="loadRows()">重试</button>
                </div>
                <div class="ng-table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>
                                    <input
                                        type="checkbox"
                                        aria-label="选择全部配置"
                                        :disabled="listLoading || !rows.length"
                                        :checked="
                                            rows.length > 0 &&
                                            selected.length === rows.length
                                        "
                                        @change="
                                            selected =
                                                selected.length === rows.length
                                                    ? []
                                                    : rows.map(rowKey)
                                        "
                                    />
                                </th>
                                <th>配置范围</th>
                                <th>配置项</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="rowKey(row)">
                                <td>
                                    <input
                                        v-model="selected"
                                        type="checkbox"
                                        :value="rowKey(row)"
                                        :aria-label="`选择配置 ${rowKey(row)}`"
                                    />
                                </td>
                                <td>
                                    {{
                                        row.scope_name === 'node'
                                            ? '节点'
                                            : '区域'
                                    }}
                                    {{ row.scope_id }}
                                </td>
                                <td>{{ summary(row) }}</td>
                                <td>
                                    <button
                                        class="ng-link"
                                        @click="openEditor(row)"
                                    >
                                        编辑</button
                                    ><button
                                        class="ng-link"
                                        @click="askDelete([rowKey(row)])"
                                    >
                                        删除
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td class="ng-empty" colspan="4">
                                    {{
                                        listLoading
                                            ? '加载中…'
                                            : listError
                                              ? '加载失败'
                                              : '暂无数据'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <footer v-if="total" class="ng-pager">
                    <span>共 {{ total }} 条</span
                    ><button
                        :disabled="page <= 1 || listLoading"
                        @click="loadRows(page - 1)"
                    >
                        上一页</button
                    ><span class="ng-page-number">{{ page }}</span
                    ><button
                        :disabled="page * 10 >= total || listLoading"
                        @click="loadRows(page + 1)"
                    >
                        下一页</button
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
                    }}<button @click="loadTargets()">重试</button>
                </div>
                <form @submit.prevent="saveOverride">
                    <fieldset :disabled="editorBusy">
                        <div class="ng-targets">
                            <label
                                >配置范围<select
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
                            <select v-model="item" aria-label="配置项">
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
                            ><button
                                type="button"
                                :disabled="!item"
                                @click="addField()"
                            >
                                <Plus />添加
                            </button>
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
                            <button
                                type="button"
                                :aria-label="`移除 ${field.path}`"
                                @click="
                                    delete editValues[field.path];
                                    delete editErrors[field.path];
                                "
                            >
                                <X />
                            </button>
                        </div>
                        <div class="ng-modal-actions">
                            <button type="button" @click="editorOpen = false">
                                取消</button
                            ><button
                                class="ng-primary"
                                :disabled="
                                    editorBusy || targetLoading || !!targetError
                                "
                            >
                                确定
                            </button>
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
            @update:open="deleteOpen = $event"
        />
    </div>
</template>

<style>
.nginx-page,
.ng-modal {
    --ng-line: #e8eef7;
    --ng-muted: #8392a9;
    --ng-ink: #344e73;
    color: var(--ng-ink);
    font-size: 12px;
}
.nginx-page {
    min-width: 0;
    border-radius: 8px;
    background: white;
    padding: 12px;
}
.nginx-page button,
.ng-modal button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    height: 28px;
    padding: 0 12px;
    border: 1px solid #d7dce5;
    border-radius: 3px;
    background: white;
    color: inherit;
    font: inherit;
    white-space: nowrap;
    cursor: pointer;
}
.nginx-page button:disabled,
.ng-modal button:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}
.nginx-page button svg,
.ng-modal button svg {
    width: 12px;
    height: 12px;
}
.nginx-page button.ng-primary,
.ng-modal button.ng-primary {
    background: #2d8cf0;
    color: white;
    border-color: #2d8cf0;
}
.nginx-page .ng-tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.nginx-page .ng-tabs button {
    border: 0;
    border-radius: 6px;
    background: transparent;
}
.nginx-page .ng-tabs button.active {
    background: #e6f2ff;
    color: #2d8cf0;
}
.ng-save-state {
    margin-left: auto;
    color: var(--ng-muted);
    font-size: 11px;
}
.nginx-page .ng-subtabs {
    display: flex;
    gap: 4px;
    margin: 0 0 29px;
}
.nginx-page .ng-subtabs button {
    border-bottom: 0;
    border-radius: 3px 3px 0 0;
    background: #f7f7f9;
    height: 27px;
    padding: 0 14px;
}
.nginx-page .ng-subtabs button.active {
    color: #2d8cf0;
    background: white;
    border-color: #2d8cf0;
}
.ng-global,
.ng-overrides {
    max-width: 967px;
}
.nginx-page h2,
.nginx-page h3 {
    font-size: 12px;
    font-weight: 600;
    color: #173452;
}
.nginx-page h2 {
    font-size: 14px;
}
.nginx-page p {
    margin: 4px 0 10px;
    color: var(--ng-muted);
    font-size: 11px;
    line-height: 1.6;
}
.ng-section-title {
    border-left: 3px solid #2d8cf0;
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
    background: #fafcff;
    border: 1px solid var(--ng-line);
    border-radius: 6px;
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
.nginx-page input:not([type='checkbox']),
.ng-modal input:not([type='checkbox']),
.ng-modal select {
    border: 1px solid #d7dce5;
    border-radius: 3px;
    height: 28px;
    background: white;
    color: inherit;
    padding: 0 7px;
    font: inherit;
    min-width: 0;
    width: 100%;
}
.ng-input input {
    flex: 1;
}
.ng-input:has(span) input {
    border-radius: 3px 0 0 3px;
}
.ng-input > span {
    display: flex;
    align-items: center;
    padding: 0 6px;
    border: 1px solid #d7dce5;
    border-left: 0;
    border-radius: 0 3px 3px 0;
    background: #f7f7f9;
}
.nginx-page input:focus,
.ng-modal input:focus,
.ng-modal select:focus {
    outline: 0;
    border-color: #83baff;
    box-shadow: 0 0 0 1px #e3f1ff;
}
.nginx-page button.ng-switch,
.ng-modal button.ng-switch {
    width: 37px;
    height: 19px;
    padding: 2px;
    border: 0;
    border-radius: 12px;
    background: #ccc;
    justify-content: flex-start;
    vertical-align: middle;
}
.ng-switch span {
    width: 15px;
    height: 15px;
    background: white;
    border-radius: 50%;
    box-shadow: 0 1px 2px #0001;
}
.nginx-page button.ng-switch.on,
.ng-modal button.ng-switch.on {
    background: #2d8cf0;
    justify-content: flex-end;
}
.ng-versions {
    display: flex;
    gap: 7px;
}
.nginx-page .ng-versions button,
.ng-modal .ng-versions button {
    border-color: #d8e7fc;
}
.nginx-page .ng-versions button.chosen,
.ng-modal .ng-versions button.chosen {
    border-color: #83baff;
    background: #edf6ff;
    color: #2d8cf0;
}
.nginx-page .ng-field-help,
.ng-modal .ng-field-help {
    font-size: 11px;
    margin: 5px 0 0;
    color: var(--ng-muted);
    line-height: 1.6;
}
.nginx-page .ng-field-error,
.ng-modal .ng-field-error {
    color: #d43b43;
    margin: 5px 0 0;
    font-size: 11px;
}
.nginx-page .has-error input,
.ng-modal .has-error input {
    border-color: #d43b43;
}
.ng-error {
    padding: 10px;
    background: #fff1f0;
    color: #bd3038;
    margin: 10px 0;
    border-radius: 4px;
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
.ng-table-scroll {
    overflow-x: auto;
}
.ng-table-scroll table {
    width: 100%;
    min-width: 620px;
    border-collapse: collapse;
}
.ng-table-scroll th,
.ng-table-scroll td {
    text-align: left;
    font-weight: 400;
    border-bottom: 1px solid var(--ng-line);
    padding: 10px 14px;
    overflow-wrap: anywhere;
}
.ng-table-scroll th {
    background: #fafcff;
}
.ng-table-scroll th:first-child {
    width: 54px;
}
.ng-table-scroll th:nth-child(2) {
    width: 28%;
}
.ng-table-scroll th:last-child {
    width: 140px;
    text-align: center;
}
.ng-table-scroll td:last-child {
    text-align: center;
}
.ng-table-scroll .ng-empty {
    color: var(--ng-muted);
    text-align: center;
    height: 40px;
}
.nginx-page .ng-link {
    color: #2d8cf0;
    border: 0;
    background: transparent;
    padding: 0 6px;
}
.nginx-page input[type='checkbox'] {
    accent-color: #2d8cf0;
}
.ng-pager {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
    padding: 16px 0 0;
}
.ng-page-number {
    color: #2d8cf0;
    border: 1px solid #2d8cf0;
    border-radius: 3px;
    padding: 4px 9px;
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
    background: #fafcff;
    border: 1px solid var(--ng-line);
    border-radius: 6px;
}
.ng-edit-value {
    min-width: 0;
    flex: 1;
}
.ng-context {
    font-size: 11px;
    color: var(--ng-muted);
}
.ng-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 18px;
}
.dark .nginx-page,
.dark .ng-modal {
    --ng-line: #303a4a;
    --ng-muted: #94a3b8;
    --ng-ink: #d8e1ef;
    background: #151e2b;
    color: var(--ng-ink);
}
.dark .nginx-page h2,
.dark .nginx-page h3 {
    color: #e5edf9;
}
.dark .nginx-page .ng-card,
.dark .ng-modal .ng-edit-field,
.dark .nginx-page th {
    background: #1c2738;
}
.dark .nginx-page input:not([type='checkbox']),
.dark .ng-modal input:not([type='checkbox']),
.dark .ng-modal select,
.dark .nginx-page .ng-input > span,
.dark .ng-modal .ng-input > span {
    background: #151e2b;
    border-color: #435067;
    color: #d8e1ef;
}
.dark .nginx-page button:not(.ng-primary):not(.ng-switch),
.dark .ng-modal button:not(.ng-primary):not(.ng-switch) {
    background: #202d40;
    border-color: #435067;
    color: #cbd9ee;
}
.dark .nginx-page .ng-tabs button.active,
.dark .nginx-page .ng-subtabs button.active,
.dark .nginx-page .ng-versions button.chosen,
.dark .ng-modal .ng-versions button.chosen {
    background: #173756;
    border-color: #65aaff;
    color: #7db9ff;
}
.dark .ng-error {
    background: #45262c;
    color: #fda4af;
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
    .ng-list-heading {
        flex-wrap: wrap;
    }
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
