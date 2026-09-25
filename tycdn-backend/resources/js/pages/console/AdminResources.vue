<script setup lang="ts">
import { Plus, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import ResourceSettingField from '@/components/console/ResourceSettingField.vue';
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
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows } from '@/lib/cdnflyResponse';
import {
    resourceSections,
    resourceFields,
    resourceFieldKey,
    resourceRowKey,
    resourceFieldFor,
    resourceValueError,
} from '@/lib/resourceSettings';
import type { ResourceField } from '@/lib/resourceSettings';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const tab = ref('global'),
    section = ref('site');
const records = ref<CdnflyRecord[]>([]),
    regions = ref<CdnflyRecord[]>([]);
const loading = ref(true),
    ready = ref(false),
    error = ref(''),
    regionError = ref('');
const values = reactive<Record<string, string>>({}),
    saving = reactive<Record<string, boolean>>({});
const errors = reactive<Record<string, string>>({}),
    invalid = reactive<Record<string, boolean>>({});
const current = computed(() => resourceSections[section.value]);
const anySaving = computed(() => Object.values(saving).some(Boolean));
const regionalFields = resourceFields.filter((field) => field.regional);
const problem = (e: unknown) =>
    e instanceof Error ? e.message : '操作失败，请重试';
const globalRow = (field: ResourceField) =>
    records.value.find(
        (row) =>
            (row.scope_name ?? 'global') === 'global' &&
            Number(row.scope_id ?? 0) === 0 &&
            row.type === field.type &&
            row.name === field.name,
    );
async function load() {
    loading.value = true;
    error.value = '';

    try {
        records.value = extractCdnflyRows(
            await apiRequest('/api/admin/configs'),
        );

        for (const field of resourceFields) {
            values[resourceFieldKey(field)] = String(
                globalRow(field)?.value ?? '',
            );
        }

        ready.value = true;
    } catch (e) {
        error.value = problem(e);
    } finally {
        loading.value = false;
    }
}
async function loadRegions() {
    regionError.value = '';

    try {
        regions.value = extractCdnflyRows(
            await apiRequest('/api/admin/regions?limit=0'),
        );
    } catch (e) {
        regionError.value = problem(e);
    }
}
onMounted(() => {
    void load();
    void loadRegions();
});
function remember(payload: CdnflyRecord) {
    const existing = records.value.find(
        (row) => resourceRowKey(row) === resourceRowKey(payload),
    );

    if (existing) {
        Object.assign(existing, payload);
    } else {
        records.value.push(payload);
    }
}
async function change(field: ResourceField, value: string) {
    const key = resourceFieldKey(field);
    values[key] = value;
    errors[key] = resourceValueError(field, value);
    invalid[key] = !!errors[key];

    if (errors[key] || saving[key]) {
        return;
    }

    if (String(globalRow(field)?.value ?? '') === value) {
        errors[key] = '';

        return;
    }

    saving[key] = true;
    const payload = {
        scope_name: 'global',
        scope_id: 0,
        type: field.type,
        name: field.name,
        value,
        enable: Number(globalRow(field)?.enable ?? 1),
    };

    try {
        await apiRequest('/api/admin/configs', {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
        remember(payload);
    } catch (e) {
        errors[key] = problem(e);
    } finally {
        saving[key] = false;
    }
}

const regionalRows = computed(() =>
    records.value.filter(
        (row) => row.scope_name === 'region' && resourceFieldFor(row)?.regional,
    ),
);
const page = ref(1),
    size = ref(10),
    selected = ref<(string | number)[]>([]);
const pages = computed(() =>
    Math.max(1, Math.ceil(regionalRows.value.length / size.value)),
);
const visibleRows = computed(() =>
    regionalRows.value.slice(
        (page.value - 1) * size.value,
        page.value * size.value,
    ),
);
watch(size, () => {
    page.value = 1;
    selected.value = [];
});
const regionName = (row: CdnflyRecord) =>
    String(
        regions.value.find(
            (region) => Number(region.id) === Number(row.scope_id),
        )?.name ??
            row.region_name ??
            `区域 #${row.scope_id}`,
    );
const fieldLabel = (field?: ResourceField) =>
    field
        ? `${field.type === 'site' ? '网站' : field.type === 'stream' ? '转发' : '公共'} · ${field.label}`
        : '';
const displayValue = (row: CdnflyRecord) =>
    resourceFieldFor(row)?.kind === 'toggle'
        ? String(row.value) === '1'
            ? '开启'
            : '关闭'
        : String(row.value ?? '') || '未设置';
const open = ref(false),
    editing = ref(false),
    editRegion = ref(''),
    editKey = ref(''),
    editValue = ref(''),
    editError = ref(''),
    editSaving = ref(false);
const editField = computed(() =>
    regionalFields.find((field) => resourceFieldKey(field) === editKey.value),
);
let editEnable = 1;
watch(editKey, () => {
    if (!editing.value) {
        editValue.value = values[editKey.value] ?? '';
    }
});
function edit(row?: CdnflyRecord) {
    editing.value = !!row;
    editError.value = '';
    editRegion.value = row ? String(row.scope_id) : '';
    editKey.value = row ? resourceFieldKey(resourceFieldFor(row)!) : '';
    editValue.value = row ? String(row.value ?? '') : '';
    editEnable = Number(row?.enable ?? 1);
    open.value = true;
}
async function saveRegion() {
    if (
        !editField.value ||
        !Number.isSafeInteger(Number(editRegion.value)) ||
        Number(editRegion.value) <= 0
    ) {
        editError.value = '请选择区域和设置项';

        return;
    }

    editError.value = resourceValueError(editField.value, editValue.value);

    if (editError.value) {
        return;
    }

    const payload = {
        scope_name: 'region',
        scope_id: Number(editRegion.value),
        type: editField.value.type,
        name: editField.value.name,
        value: editValue.value,
        enable: editEnable,
    };

    if (
        !editing.value &&
        regionalRows.value.some(
            (row) => resourceRowKey(row) === resourceRowKey(payload),
        )
    ) {
        editError.value = '该区域已有此设置，请编辑现有设置';

        return;
    }

    editSaving.value = true;

    try {
        await apiRequest('/api/admin/configs', {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
        remember(payload);
        open.value = false;
        toast.success('保存成功');
    } catch (e) {
        editError.value = problem(e);
    } finally {
        editSaving.value = false;
    }
}
const deleting = ref<CdnflyRecord[]>([]),
    deleteBusy = ref(false),
    deleteError = ref('');
function confirmDelete(rows: CdnflyRecord[]) {
    deleting.value = rows;
    deleteError.value = '';
}
async function remove() {
    deleteBusy.value = true;
    deleteError.value = '';

    try {
        for (const row of [...deleting.value]) {
            await apiRequest(
                `/api/admin/configs/resource-overrides/${row.scope_id}/${row.type}/${row.name}`,
                { method: 'DELETE' },
            );
            const key = resourceRowKey(row);
            records.value = records.value.filter(
                (item) => resourceRowKey(item) !== key,
            );
            deleting.value = deleting.value.filter(
                (item) => resourceRowKey(item) !== key,
            );
            selected.value = selected.value.filter((item) => item !== key);
        }

        toast.success('已删除，恢复使用全局配置');
    } catch (e) {
        deleteError.value = problem(e);
    } finally {
        deleteBusy.value = false;
        page.value = Math.min(page.value, pages.value);
    }
}
</script>

<template>
    <div class="console-page resource-workspace p-4 md:p-6">
        <div class="console-panel rounded-xl border bg-card p-4 md:p-5">
            <ConsoleTabs
                v-model="tab"
                :tabs="[
                    { key: 'global', label: '全局配置' },
                    { key: 'region', label: '区域配置' },
                ]"
            />
            <div
                v-if="error"
                class="my-4 flex items-center gap-3 text-sm text-destructive"
                role="alert"
            >
                {{ error
                }}<Button
                    variant="outline"
                    :disabled="loading || anySaving"
                    @click="load"
                    >重试</Button
                >
            </div>
            <p v-if="loading" role="status" class="py-8 text-muted-foreground">
                正在加载配置…
            </p>
            <template v-else-if="ready">
                <section
                    v-if="tab === 'global'"
                    class="max-w-[1000px]"
                    role="tabpanel"
                    aria-label="全局配置"
                >
                    <div
                        class="mt-3 flex gap-1"
                        role="tablist"
                        aria-label="资源分类"
                    >
                        <Button
                            v-for="(item, key) in resourceSections"
                            :key="key"
                            size="sm"
                            :variant="section === key ? 'secondary' : 'ghost'"
                            role="tab"
                            :aria-selected="section === key"
                            @click="section = String(key)"
                            >{{ item.label }}</Button
                        >
                    </div>
                    <header class="mt-7 mb-4 border-l-2 border-primary pl-3">
                        <h2 class="text-sm font-semibold">
                            {{ current.title }}
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ current.help }}
                        </p>
                    </header>
                    <div class="grid gap-3 lg:grid-cols-2">
                        <section
                            v-for="card in current.cards"
                            :key="card.title"
                            class="rounded-lg border bg-card p-4"
                            :class="card.wide ? 'lg:col-span-2' : ''"
                        >
                            <h3 class="text-sm font-semibold">
                                {{ card.title }}
                            </h3>
                            <p class="mt-1 mb-3 text-xs text-muted-foreground">
                                {{ card.help }}
                            </p>
                            <div
                                class="grid gap-3"
                                :class="card.wide ? 'lg:grid-cols-2' : ''"
                            >
                                <ResourceSettingField
                                    v-for="field in card.fields"
                                    :id="`global-${resourceFieldKey(field)}`"
                                    :key="resourceFieldKey(field)"
                                    :field="field"
                                    :value="
                                        values[resourceFieldKey(field)] ?? ''
                                    "
                                    :disabled="saving[resourceFieldKey(field)]"
                                    @change="change(field, $event)"
                                >
                                    <span
                                        v-if="saving[resourceFieldKey(field)]"
                                        role="status"
                                        class="text-xs text-muted-foreground"
                                        >保存中…</span
                                    >
                                    <div
                                        v-if="errors[resourceFieldKey(field)]"
                                        role="alert"
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        {{ errors[resourceFieldKey(field)] }}
                                        <Button
                                            v-if="
                                                !invalid[
                                                    resourceFieldKey(field)
                                                ]
                                            "
                                            variant="link"
                                            size="sm"
                                            @click="
                                                change(
                                                    field,
                                                    values[
                                                        resourceFieldKey(field)
                                                    ],
                                                )
                                            "
                                            >重试</Button
                                        >
                                    </div>
                                </ResourceSettingField>
                            </div>
                        </section>
                    </div>
                </section>
                <section
                    v-else
                    class="mt-5 max-w-[1000px]"
                    role="tabpanel"
                    aria-label="区域配置"
                >
                    <header
                        class="mb-4 flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h2 class="text-sm font-semibold">区域资源限制</h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                按区域覆盖资源限制，只保存与全局不同的站点、转发和公共端口配置。
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-if="selected.length"
                                variant="destructive"
                                size="sm"
                                :disabled="deleteBusy"
                                @click="
                                    confirmDelete(
                                        regionalRows.filter((row) =>
                                            selected.includes(
                                                resourceRowKey(row),
                                            ),
                                        ),
                                    )
                                "
                                >删除选中</Button
                            ><Button
                                size="sm"
                                :disabled="deleteBusy"
                                @click="edit()"
                                ><Plus class="size-4" />新增设置</Button
                            >
                        </div>
                    </header>
                    <ConsoleDataTable
                        embedded
                        selectable
                        title="区域资源限制"
                        :columns="[
                            { key: 'region', label: '区域' },
                            { key: 'setting', label: '设置项' },
                            { key: 'value', label: '设置值' },
                        ]"
                        :data="{
                            rows: visibleRows,
                            total: regionalRows.length,
                            page,
                            pageSize: size,
                            loading: false,
                        }"
                        :get-row-key="resourceRowKey"
                        :selected="selected"
                        :selection-disabled="deleteBusy"
                        empty-text="暂无数据"
                        @update:selected="selected = $event"
                    >
                        <template #cell-region="{ row }">{{
                            regionName(row)
                        }}</template>
                        <template #cell-setting="{ row }">{{
                            fieldLabel(resourceFieldFor(row))
                        }}</template>
                        <template #cell-value="{ row }"
                            ><span class="break-all">{{
                                displayValue(row)
                            }}</span></template
                        >
                        <template #row-actions="{ row }"
                            ><Button
                                variant="ghost"
                                size="sm"
                                :disabled="deleteBusy"
                                @click="edit(row)"
                                >编辑</Button
                            ><Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                :disabled="deleteBusy"
                                @click="confirmDelete([row])"
                                >删除</Button
                            ></template
                        >
                    </ConsoleDataTable>
                    <ConsolePagination
                        :total="regionalRows.length"
                        :page="page"
                        :previous-disabled="page <= 1"
                        :next-disabled="page >= pages"
                        @previous="page--"
                        @next="page++"
                    />
                </section>
            </template>
        </div>
        <Dialog :open="open" @update:open="!editSaving && (open = $event)">
            <DialogScrollContent class="sm:max-w-xl">
                <DialogHeader
                    ><DialogTitle>{{
                        editing ? '编辑设置' : '新增设置'
                    }}</DialogTitle
                    ><DialogDescription
                        >为指定区域设置资源限制。</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-4" @submit.prevent="saveRegion">
                    <label class="grid gap-2 text-sm"
                        >区域<SelectField
                            v-model="editRegion"
                            aria-label="区域"
                            required
                            :disabled="editing || editSaving"
                            class="h-9 rounded-md border border-input bg-background px-3"
                        >
                            <SelectOption value="" disabled
                                >请选择区域</SelectOption
                            >
                            <SelectOption
                                v-if="
                                    editing &&
                                    !regions.some(
                                        (region) =>
                                            String(region.id) === editRegion,
                                    )
                                "
                                :value="editRegion"
                            >
                                区域 #{{ editRegion }}
                            </SelectOption>
                            <SelectOption
                                v-for="region in regions"
                                :key="String(region.id)"
                                :value="String(region.id)"
                            >
                                {{ region.name }}
                            </SelectOption>
                        </SelectField></label
                    >
                    <div
                        v-if="regionError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ regionError
                        }}<Button
                            type="button"
                            variant="link"
                            @click="loadRegions"
                            ><RefreshCw class="size-4" />重试</Button
                        >
                    </div>
                    <label class="grid gap-2 text-sm"
                        >设置项<SelectField
                            v-model="editKey"
                            aria-label="设置项"
                            required
                            :disabled="editing || editSaving"
                            class="h-9 rounded-md border border-input bg-background px-3"
                        >
                            <SelectOption value="" disabled
                                >请选择设置项</SelectOption
                            >
                            <SelectOption
                                v-for="field in regionalFields"
                                :key="resourceFieldKey(field)"
                                :value="resourceFieldKey(field)"
                            >
                                {{ fieldLabel(field) }}
                            </SelectOption>
                        </SelectField></label
                    >
                    <ResourceSettingField
                        v-if="editField"
                        id="regional-value"
                        :key="editKey"
                        :field="editField"
                        :value="editValue"
                        :disabled="editSaving"
                        @change="editValue = $event"
                    />
                    <p
                        v-if="editError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ editError }}
                    </p>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="editSaving"
                            @click="open = false"
                            >取消</Button
                        ><Button type="submit" :disabled="editSaving">{{
                            editSaving ? '保存中…' : '保存'
                        }}</Button></DialogFooter
                    >
                </form>
            </DialogScrollContent>
        </Dialog>
        <ConfirmDeleteDialog
            :open="deleting.length > 0"
            :description="`删除 ${deleting.length} 项区域设置后，将恢复使用全局配置。`"
            :loading="deleteBusy"
            :error="deleteError"
            @confirm="remove"
            @cancel="!deleteBusy && (deleting = [])"
        />
    </div>
</template>
