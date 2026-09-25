<script setup lang="ts">
import { Plus, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, shallowRef, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import DefaultSettingControl from '@/components/console/DefaultSettingControl.vue';
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
import type { ConfigValue } from '@/lib/configEditor';
import type { DefaultField } from '@/lib/defaultSettings';
import {
    defaultSections,
    defaultFields,
    defaultKey,
    defaultRowKey,
    defaultFieldFor,
    defaultValueError,
} from '@/lib/defaultSettings';
import {
    decodeDefault,
    encodeDefault,
    sslFields,
    sslOptions,
    sslPresets,
} from '@/lib/defaultSettings';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const tab = ref('global'),
    section = ref('site');
const records = ref<CdnflyRecord[]>([]),
    regions = ref<CdnflyRecord[]>([]);
const loading = ref(true),
    ready = ref(false),
    error = ref(''),
    regionError = ref('');
const values = reactive<Record<string, ConfigValue>>({}),
    saving = reactive<Record<string, boolean>>({});
const errors = reactive<Record<string, string>>({}),
    invalid = reactive<Record<string, boolean>>({});
const current = computed(() => defaultSections[section.value]);
const malformed = reactive<Record<string, boolean>>({});
const sslMode = ref('custom'),
    sslBusy = ref(false);
const rules = ref<CdnflyRecord[]>([]),
    rulesError = ref('');
const ruleOptions = computed(() =>
    rules.value.map((row) => ({
        value: String(row.id),
        label: String(row.name),
    })),
);
async function loadRules() {
    rulesError.value = '';

    try {
        rules.value = extractCdnflyRows(
            await apiRequest('/api/admin/cc/rule?limit=0&internal=1'),
        );
    } catch (e) {
        rulesError.value = problem(e);
    }
}
async function selectSsl(mode: string) {
    sslMode.value = mode;

    if (!sslPresets[mode]) {
        return;
    }

    sslBusy.value = true;

    try {
        for (const field of sslFields) {
            await change(field, sslPresets[mode][field.name]);
        }
    } finally {
        sslBusy.value = false;
    }
}
const anySaving = computed(() => Object.values(saving).some(Boolean));
const regionalFields = defaultFields.filter((field) => field.regional);
const problem = (e: unknown) =>
    e instanceof Error ? e.message : '操作失败，请重试';
const globalRow = (field: DefaultField) =>
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

        for (const field of defaultFields) {
            malformed[defaultKey(field)] = false;
            errors[defaultKey(field)] = '';

            try {
                values[defaultKey(field)] = decodeDefault(
                    field,
                    globalRow(field)?.value,
                );
            } catch (e) {
                malformed[defaultKey(field)] = true;
                errors[defaultKey(field)] = problem(e);
            }
        }

        sslMode.value =
            Object.keys(sslPresets).find((mode) =>
                sslFields.every(
                    (field) =>
                        values[defaultKey(field)] ===
                        sslPresets[mode][field.name],
                ),
            ) ?? 'custom';
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
    void loadRules();
});
function remember(payload: CdnflyRecord) {
    const existing = records.value.find(
        (row) => defaultRowKey(row) === defaultRowKey(payload),
    );

    if (existing) {
        Object.assign(existing, payload);
    } else {
        records.value.push(payload);
    }
}
async function change(field: DefaultField, value: ConfigValue) {
    const key = defaultKey(field);
    values[key] = value;
    errors[key] = defaultValueError(field, value);
    invalid[key] = !!errors[key];

    if (errors[key] || saving[key]) {
        return;
    }

    if (String(globalRow(field)?.value ?? '') === encodeDefault(field, value)) {
        errors[key] = '';

        return;
    }

    saving[key] = true;
    const payload = {
        scope_name: 'global',
        scope_id: 0,
        type: field.type,
        name: field.name,
        value: encodeDefault(field, value),
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
        (row) => row.scope_name === 'region' && defaultFieldFor(row)?.regional,
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
const fieldLabel = (field?: DefaultField) =>
    field
        ? `${field.type === 'site_default_config' ? '网站' : field.type === 'stream_default_config' ? '转发' : '证书'} · ${field.label}`
        : '';
const displayValue = (row: CdnflyRecord) =>
    ['cache', 'headers', 'waf', 'autoblock'].includes(
        defaultFieldFor(row)?.kind ?? '',
    )
        ? '点击编辑查看'
        : defaultFieldFor(row)?.kind === 'toggle'
          ? String(row.value) === '1'
              ? '开启'
              : '关闭'
          : String(row.value ?? '') || '未设置';
const open = ref(false),
    editing = ref(false),
    editRegion = ref(''),
    editKey = ref(''),
    editValue = shallowRef<ConfigValue>(''),
    editError = ref(''),
    editSaving = ref(false);
const editField = computed(() =>
    regionalFields.find((field) => defaultKey(field) === editKey.value),
);
let editEnable = 1;
watch(editKey, () => {
    if (!editing.value) {
        editValue.value = JSON.parse(
            JSON.stringify(values[editKey.value] ?? ''),
        );
    }
});
function edit(row?: CdnflyRecord) {
    editing.value = !!row;
    editError.value = '';
    editRegion.value = row ? String(row.scope_id) : '';
    editKey.value = row ? defaultKey(defaultFieldFor(row)!) : '';

    try {
        editValue.value = row
            ? decodeDefault(defaultFieldFor(row)!, row.value)
            : '';
    } catch (e) {
        toast.error(problem(e));

        return;
    }

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

    editError.value = defaultValueError(editField.value, editValue.value);

    if (editError.value) {
        return;
    }

    const payload = {
        scope_name: 'region',
        scope_id: Number(editRegion.value),
        type: editField.value.type,
        name: editField.value.name,
        value: encodeDefault(editField.value, editValue.value),
        enable: editEnable,
    };

    if (
        !editing.value &&
        regionalRows.value.some(
            (row) => defaultRowKey(row) === defaultRowKey(payload),
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
                `/api/admin/configs/default-overrides/${row.scope_id}/${row.type}/${row.name}`,
                { method: 'DELETE' },
            );
            const key = defaultRowKey(row);
            records.value = records.value.filter(
                (item) => defaultRowKey(item) !== key,
            );
            deleting.value = deleting.value.filter(
                (item) => defaultRowKey(item) !== key,
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
    <div class="console-page defaults-workspace p-4 md:p-6">
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
                role="alert"
                class="my-4 text-sm text-destructive"
            >
                {{ error
                }}<Button
                    variant="outline"
                    :disabled="loading || anySaving"
                    @click="load"
                    >重试</Button
                >
            </div>
            <p
                data-typography="body"
                v-if="loading"
                role="status"
                class="py-8 text-muted-foreground"
            >
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
                        class="my-3 flex gap-1"
                        role="tablist"
                        aria-label="默认配置分类"
                    >
                        <Button
                            v-for="(item, key) in defaultSections"
                            :key="key"
                            size="sm"
                            :variant="section === key ? 'secondary' : 'ghost'"
                            role="tab"
                            :aria-selected="section === key"
                            @click="section = String(key)"
                            >{{ item.label }}</Button
                        >
                    </div>
                    <div class="grid gap-3">
                        <section
                            v-for="card in current.cards"
                            :key="card.title"
                            class="rounded-lg border bg-card p-4"
                        >
                            <header class="mb-4 border-l-2 border-primary pl-3">
                                <h2
                                    data-typography="section-title"
                                    class="font-semibold"
                                >
                                    {{ card.title }}
                                </h2>
                                <p
                                    data-typography="description"
                                    class="mt-1 text-muted-foreground"
                                >
                                    {{ card.help }}
                                </p>
                            </header>
                            <div
                                class="grid items-start gap-x-6 gap-y-4 lg:grid-cols-2"
                            >
                                <div
                                    v-for="field in card.fields"
                                    :key="defaultKey(field)"
                                    class="min-w-0"
                                    :class="field.wide ? 'lg:col-span-2' : ''"
                                    :data-setting="defaultKey(field)"
                                >
                                    <template v-if="field.kind === 'ssl'">
                                        <div
                                            class="grid gap-2 sm:grid-cols-[110px_minmax(0,1fr)]"
                                        >
                                            <label class="pt-2 text-sm"
                                                >SSL配置</label
                                            ><DefaultSettingControl
                                                id="ssl-mode"
                                                :field="{
                                                    ...field,
                                                    kind: 'choices',
                                                    options: sslOptions,
                                                }"
                                                :value="sslMode"
                                                :disabled="
                                                    sslBusy ||
                                                    sslFields.some(
                                                        (item) =>
                                                            saving[
                                                                defaultKey(item)
                                                            ] ||
                                                            malformed[
                                                                defaultKey(item)
                                                            ],
                                                    )
                                                "
                                                @change="
                                                    selectSsl(String($event))
                                                "
                                            />
                                        </div>
                                        <div
                                            v-if="sslMode === 'custom'"
                                            class="mt-4 grid gap-3"
                                        >
                                            <div
                                                v-for="item in sslFields"
                                                :key="item.name"
                                                class="grid min-w-0 gap-2 sm:grid-cols-[110px_minmax(0,1fr)]"
                                            >
                                                <label
                                                    :for="item.name"
                                                    class="pt-2 text-sm"
                                                    >{{ item.label }}</label
                                                ><DefaultSettingControl
                                                    :id="item.name"
                                                    :field="item"
                                                    :value="
                                                        values[
                                                            defaultKey(item)
                                                        ] ?? ''
                                                    "
                                                    :disabled="
                                                        saving[
                                                            defaultKey(item)
                                                        ] ||
                                                        malformed[
                                                            defaultKey(item)
                                                        ]
                                                    "
                                                    @change="
                                                        change(item, $event)
                                                    "
                                                />
                                            </div>
                                        </div>
                                        <template
                                            v-for="item in sslFields"
                                            :key="item.name"
                                            ><div
                                                v-if="errors[defaultKey(item)]"
                                                role="alert"
                                                class="mt-2 text-sm text-destructive"
                                            >
                                                {{ item.label }}：{{
                                                    errors[defaultKey(item)]
                                                }}<Button
                                                    v-if="
                                                        !malformed[
                                                            defaultKey(item)
                                                        ]
                                                    "
                                                    size="sm"
                                                    variant="link"
                                                    @click="
                                                        change(
                                                            item,
                                                            values[
                                                                defaultKey(item)
                                                            ],
                                                        )
                                                    "
                                                    >重试</Button
                                                >
                                            </div></template
                                        >
                                    </template>
                                    <template v-else>
                                        <div
                                            :class="
                                                [
                                                    'cache',
                                                    'headers',
                                                    'waf',
                                                    'autoblock',
                                                ].includes(field.kind ?? '')
                                                    ? 'grid gap-2'
                                                    : 'grid min-w-0 gap-2 sm:grid-cols-[110px_minmax(0,1fr)]'
                                            "
                                        >
                                            <label
                                                v-if="
                                                    ![
                                                        'cache',
                                                        'headers',
                                                    ].includes(field.kind ?? '')
                                                "
                                                :for="`global-${defaultKey(field)}`"
                                                class="pt-2 text-sm"
                                                :class="
                                                    [
                                                        'waf',
                                                        'autoblock',
                                                    ].includes(field.kind ?? '')
                                                        ? 'font-semibold'
                                                        : ''
                                                "
                                                >{{ field.label }}</label
                                            >
                                            <div class="min-w-0">
                                                <DefaultSettingControl
                                                    v-if="
                                                        !malformed[
                                                            defaultKey(field)
                                                        ]
                                                    "
                                                    :id="`global-${defaultKey(field)}`"
                                                    :field="field"
                                                    :value="
                                                        values[
                                                            defaultKey(field)
                                                        ] ?? ''
                                                    "
                                                    :options="
                                                        field.name ===
                                                        'cc_default_rule'
                                                            ? ruleOptions
                                                            : undefined
                                                    "
                                                    :disabled="
                                                        saving[
                                                            defaultKey(field)
                                                        ]
                                                    "
                                                    @change="
                                                        change(field, $event)
                                                    "
                                                />
                                                <p
                                                    data-typography="helper"
                                                    v-if="field.help"
                                                    class="mt-1 text-muted-foreground"
                                                >
                                                    {{ field.help }}
                                                </p>
                                                <div
                                                    v-if="
                                                        field.name ===
                                                            'cc_default_rule' &&
                                                        rulesError
                                                    "
                                                    role="alert"
                                                    class="text-sm text-destructive"
                                                >
                                                    {{ rulesError
                                                    }}<Button
                                                        variant="link"
                                                        size="sm"
                                                        @click="loadRules"
                                                        >重试加载规则</Button
                                                    >
                                                </div>
                                                <span
                                                    v-if="
                                                        saving[
                                                            defaultKey(field)
                                                        ]
                                                    "
                                                    role="status"
                                                    class="text-xs text-muted-foreground"
                                                    >保存中…</span
                                                >
                                                <div
                                                    v-if="
                                                        errors[
                                                            defaultKey(field)
                                                        ]
                                                    "
                                                    role="alert"
                                                    class="text-sm text-destructive"
                                                >
                                                    {{
                                                        errors[
                                                            defaultKey(field)
                                                        ]
                                                    }}<Button
                                                        v-if="
                                                            !invalid[
                                                                defaultKey(
                                                                    field,
                                                                )
                                                            ] &&
                                                            !malformed[
                                                                defaultKey(
                                                                    field,
                                                                )
                                                            ]
                                                        "
                                                        size="sm"
                                                        variant="link"
                                                        @click="
                                                            change(
                                                                field,
                                                                values[
                                                                    defaultKey(
                                                                        field,
                                                                    )
                                                                ],
                                                            )
                                                        "
                                                        >重试</Button
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </section>
                    </div>
                </section>
                <section
                    v-else
                    class="mt-4"
                    role="tabpanel"
                    aria-label="区域配置"
                >
                    <div class="mb-3 flex gap-2">
                        <Button size="sm" :disabled="deleteBusy" @click="edit()"
                            ><Plus class="size-4" />新增设置</Button
                        ><Button
                            v-if="selected.length"
                            size="sm"
                            variant="destructive"
                            :disabled="deleteBusy"
                            @click="
                                confirmDelete(
                                    regionalRows.filter((row) =>
                                        selected.includes(defaultRowKey(row)),
                                    ),
                                )
                            "
                            >删除选中</Button
                        >
                    </div>
                    <ConsoleDataTable
                        embedded
                        selectable
                        title="区域默认设置"
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
                        :get-row-key="defaultRowKey"
                        :selected="selected"
                        :selection-disabled="deleteBusy"
                        empty-text="暂无数据"
                        @update:selected="selected = $event"
                    >
                        <template #cell-region="{ row }">{{
                            regionName(row)
                        }}</template
                        ><template #cell-setting="{ row }">{{
                            fieldLabel(defaultFieldFor(row))
                        }}</template
                        ><template #cell-value="{ row }"
                            ><span class="break-all">{{
                                displayValue(row)
                            }}</span></template
                        ><template #row-actions="{ row }"
                            ><Button
                                size="sm"
                                variant="ghost"
                                :disabled="deleteBusy"
                                @click="edit(row)"
                                >编辑</Button
                            ><Button
                                size="sm"
                                variant="ghost"
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
        <Dialog :open="open" @update:open="!editSaving && (open = $event)"
            ><DialogScrollContent class="sm:max-w-3xl"
                ><DialogHeader
                    ><DialogTitle>{{
                        editing ? '编辑设置' : '新增设置'
                    }}</DialogTitle
                    ><DialogDescription
                        >为指定区域设置默认值。</DialogDescription
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
                                :key="defaultKey(field)"
                                :value="defaultKey(field)"
                            >
                                {{ fieldLabel(field) }}
                            </SelectOption>
                        </SelectField></label
                    >
                    <DefaultSettingControl
                        v-if="editField"
                        id="regional-value"
                        :key="editKey"
                        :field="editField"
                        :value="editValue"
                        :options="
                            editField.name === 'cc_default_rule'
                                ? ruleOptions
                                : undefined
                        "
                        :disabled="editSaving"
                        @change="editValue = $event"
                    />
                    <p
                        data-typography="body"
                        v-if="editError"
                        role="alert"
                        class="text-destructive"
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
                </form></DialogScrollContent
            ></Dialog
        >
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
