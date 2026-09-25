<script setup lang="ts">
import { Plus, RefreshCw, Save, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfigFields from '@/components/console/ConfigFields.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows } from '@/lib/cdnflyResponse';
import { siteDefaultFields, streamDefaultFields } from '@/lib/configDefaults';
import { decodeValue, inferredFields, secretField } from '@/lib/configEditor';
import type { ConfigObject, ConfigValue, Field } from '@/lib/configEditor';
import {
    configSection,
    configTitles,
    settingsSections,
} from '@/lib/configSections';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const props = withDefaults(
    defineProps<{ section?: string; scope?: 'admin' | 'user' }>(),
    { section: 'system', scope: 'admin' },
);
const active = ref(props.section);
const search = ref('');
const loading = ref(false);
const saving = ref('');
const error = ref('');
const records = ref<CdnflyRecord[]>([]);
const edited = ref<Record<string, ConfigObject>>({});
const expanded = ref<Record<string, boolean>>({});
const creating = ref(false);
const createError = ref('');
const newName = ref('');
const newType = ref('');
const newScope = ref('global');
const newScopeId = ref(0);
const newValue = ref<ConfigObject>({ value: '' });
const templates = computed(() =>
    active.value === 'stream-defaults'
        ? streamDefaultFields
        : active.value === 'defaults'
          ? siteDefaultFields
          : [],
);
const newField = computed<Field>(() => ({
    ...(templates.value.find((field) => field.key === newName.value) ?? {
        label: '配置值',
        type: 'textarea',
    }),
    key: 'value',
}));
watch(newName, () => {
    const type = newField.value.type;
    newValue.value = {
        value:
            type === 'object'
                ? {}
                : type === 'array'
                  ? []
                  : type === 'toggle'
                    ? 0
                    : '',
    };
});
function startCreate() {
    newName.value = '';
    newValue.value = { value: '' };
    newType.value =
        active.value === 'stream-defaults'
            ? props.scope === 'admin'
                ? 'stream_default_config'
                : 'stream'
            : active.value === 'defaults'
              ? props.scope === 'admin'
                  ? 'site_default_config'
                  : 'site'
              : 'system';
    newScope.value = 'global';
    newScopeId.value = 0;
    createError.value = '';
    creating.value = true;
}
async function create() {
    if (!newName.value.trim() || !newType.value.trim()) {
        createError.value = '请选择或填写配置项及类型';

        return;
    }

    const row = {
        name: newName.value.trim(),
        type: newType.value.trim(),
        scope_name: newScope.value,
        scope_id: newScope.value === 'global' ? 0 : newScopeId.value,
        enable: 1,
    };

    if (records.value.some((existing) => key(existing) === key(row))) {
        createError.value = '此作用域已有该配置，请编辑现有配置';

        return;
    }

    saving.value = 'new';
    createError.value = '';

    try {
        const value = newValue.value.value;
        await apiRequest(endpoint.value, {
            method: props.scope === 'admin' ? 'PUT' : 'POST',
            body: JSON.stringify({
                ...row,
                value:
                    typeof value === 'object'
                        ? JSON.stringify(value)
                        : String(value ?? ''),
            }),
        });
        creating.value = false;
        await load();
        toast.success('配置已创建');
    } catch (e) {
        createError.value = e instanceof Error ? e.message : '创建失败';
    } finally {
        saving.value = '';
    }
}
const endpoint = computed(() =>
    props.scope === 'admin'
        ? '/api/admin/configs'
        : '/api/cdn/proxy/v1/user-configs',
);
const key = (row: CdnflyRecord) =>
    [row.scope_name ?? 'global', row.scope_id ?? 0, row.type, row.name].join(
        ':',
    );
const title = (row: CdnflyRecord) =>
    configTitles[String(row.name)] ?? String(row.name).replaceAll('_', ' ');
const original = (row: CdnflyRecord): ConfigObject => {
    const value = decodeValue(row.value);

    return { value };
};
const tabs = computed(() =>
    props.scope === 'user'
        ? settingsSections.filter((t) =>
              ['defaults', 'stream-defaults'].includes(t.key),
          )
        : settingsSections,
);
const visible = computed(() =>
    records.value.filter(
        (row) =>
            (props.scope === 'user'
                ? row.type === 'stream'
                    ? 'stream-defaults'
                    : 'defaults'
                : configSection(row)) === active.value &&
            `${title(row)} ${row.name}`
                .toLowerCase()
                .includes(search.value.toLowerCase()),
    ),
);
function fields(row: CdnflyRecord): Field[] {
    const value = edited.value[key(row)]?.value;
    const template = [...siteDefaultFields, ...streamDefaultFields].find(
        (field) => field.key === row.name,
    );

    if (
        template &&
        [
            'site',
            'stream',
            'site_default_config',
            'stream_default_config',
        ].includes(String(row.type))
    ) {
        return [{ ...template, key: 'value' }];
    }

    return [
        {
            key: 'value',
            label: title(row),
            type:
                secretField(String(row.name)) || value === '••••••••'
                    ? 'password'
                    : Array.isArray(value)
                      ? 'array'
                      : value && typeof value === 'object'
                        ? 'object'
                        : typeof value === 'boolean'
                          ? 'toggle'
                          : typeof value === 'number'
                            ? 'number'
                            : typeof value === 'string' &&
                                (value.includes('\n') || value.length > 150)
                              ? 'textarea'
                              : 'text',
            fields:
                value && typeof value === 'object' && !Array.isArray(value)
                    ? inferredFields(value)
                    : undefined,
        },
    ];
}
const changed = (row: CdnflyRecord) =>
    JSON.stringify(original(row)) !== JSON.stringify(edited.value[key(row)]);
async function load() {
    loading.value = true;
    error.value = '';

    try {
        records.value = extractCdnflyRows(await apiRequest(endpoint.value));
        edited.value = Object.fromEntries(
            records.value.map((row) => [key(row), original(row)]),
        );
    } catch (e) {
        error.value = e instanceof Error ? e.message : '加载失败';
    } finally {
        loading.value = false;
    }
}
async function save(row: CdnflyRecord) {
    saving.value = key(row);
    error.value = '';

    try {
        const value: ConfigValue = edited.value[key(row)].value;
        const payload = {
            name: row.name,
            type: row.type,
            scope_name:
                row.scope_name ?? (props.scope === 'admin' ? 'global' : 'user'),
            scope_id: row.scope_id ?? 0,
            enable: Number(row.enable ?? 1),
            value:
                typeof value === 'object'
                    ? JSON.stringify(value)
                    : String(value ?? ''),
        };

        if (props.scope === 'user' && !row.id) {
            throw new Error('配置 ID 缺失，请刷新重试');
        }

        await apiRequest(
            `${endpoint.value}${props.scope === 'user' ? `/${row.id}` : ''}`,
            {
                method: 'PUT',
                body: JSON.stringify(payload),
            },
        );
        toast.success('配置已保存');
        row.value = payload.value;
        edited.value[key(row)] = original(row);
    } catch (e) {
        error.value = e instanceof Error ? e.message : '保存失败';
    } finally {
        saving.value = '';
    }
}
onMounted(() => {
    void load();
});
</script>
<template>
    <div class="console-page flex flex-1 flex-col gap-5 p-4 md:p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">
                {{
                    settingsSections.find((t) => t.key === active)?.label ??
                    '系统配置'
                }}
            </h1>
            <div class="flex gap-2">
                <Button
                    :disabled="loading || !!saving || records.some(changed)"
                    @click="startCreate"
                    ><Plus class="size-4" />新增配置</Button
                ><Button
                    variant="outline"
                    :disabled="loading || !!saving || records.some(changed)"
                    @click="load"
                    ><RefreshCw class="size-4" />刷新</Button
                >
            </div>
        </div>
        <div class="console-panel rounded-xl border bg-card p-5">
            <ConsoleTabs v-model="active" :tabs="tabs" />
            <div class="my-5 flex items-center gap-2">
                <Search class="size-4 text-muted-foreground" /><Input
                    v-model="search"
                    placeholder="搜索配置项"
                    class="max-w-sm"
                />
            </div>
            <p
                v-if="error"
                role="alert"
                class="mb-4 rounded-lg border border-destructive/30 p-4 text-sm text-destructive"
            >
                {{ error }}
            </p>
            <p
                v-if="loading"
                role="status"
                class="p-10 text-center text-muted-foreground"
            >
                正在加载配置…
            </p>
            <p
                v-else-if="!visible.length"
                class="p-10 text-center text-muted-foreground"
            >
                {{ search ? '没有匹配的配置' : '当前作用域暂无配置' }}
            </p>
            <div v-else class="grid gap-3">
                <section
                    v-for="row in visible"
                    :key="key(row)"
                    class="overflow-hidden rounded-lg border"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 bg-muted/30 px-4 py-3 text-left"
                        :aria-expanded="!!expanded[key(row)]"
                        @click="expanded[key(row)] = !expanded[key(row)]"
                    >
                        <span class="font-medium">{{ title(row) }}</span
                        ><span class="text-xs text-muted-foreground"
                            >{{ row.scope_name || 'global' }}
                            {{ Number(row.scope_id) ? `#${row.scope_id}` : '' }}
                            {{ changed(row) ? '· 未保存' : '' }}
                            {{ expanded[key(row)] ? '−' : '＋' }}</span
                        >
                    </button>
                    <form
                        v-if="expanded[key(row)]"
                        class="grid gap-4 p-5"
                        @submit.prevent="save(row)"
                    >
                        <ConfigFields
                            v-model="edited[key(row)]"
                            :fields="fields(row)"
                            :disabled="!!saving"
                        />
                        <div class="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="!changed(row) || !!saving"
                                @click="edited[key(row)] = original(row)"
                                >取消更改</Button
                            ><Button
                                type="submit"
                                :disabled="!changed(row) || !!saving"
                                ><Save class="size-4" />{{
                                    saving === key(row) ? '保存中…' : '保存'
                                }}</Button
                            >
                        </div>
                    </form>
                </section>
            </div>
        </div>
        <Dialog v-model:open="creating">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader
                    ><DialogTitle>新增配置</DialogTitle
                    ><DialogDescription
                        >配置只在保存后生效。</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-4" @submit.prevent="create">
                    <label class="grid gap-2 text-sm"
                        >配置项
                        <SelectField
                            v-if="templates.length"
                            aria-label="配置项"
                            v-model="newName"
                            class="h-9 rounded-md border bg-background px-3"
                            required
                        >
                            <SelectOption value="" disabled
                                >请选择配置项</SelectOption
                            >
                            <SelectOption
                                v-for="field in templates"
                                :key="field.key"
                                :value="field.key"
                            >
                                {{ field.label }}
                            </SelectOption>
                        </SelectField>
                        <Input
                            v-else
                            v-model="newName"
                            required
                            placeholder="配置名称"
                        />
                    </label>
                    <label v-if="!templates.length" class="grid gap-2 text-sm"
                        >配置类型<Input v-model="newType" required
                    /></label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2 text-sm"
                            >作用域<SelectField
                                v-model="newScope"
                                class="h-9 rounded-md border bg-background px-3"
                            >
                                <SelectOption value="global">全局</SelectOption>
                                <SelectOption
                                    v-if="scope === 'admin'"
                                    value="region"
                                >
                                    区域
                                </SelectOption>
                                <SelectOption v-else value="group"
                                    >分组</SelectOption
                                >
                            </SelectField></label
                        >
                        <label
                            v-if="newScope !== 'global'"
                            class="grid gap-2 text-sm"
                            >{{ newScope === 'region' ? '区域 ID' : '分组 ID'
                            }}<Input
                                v-model="newScopeId"
                                type="number"
                                min="1"
                                required
                        /></label>
                    </div>
                    <ConfigFields
                        v-if="newName"
                        v-model="newValue"
                        :fields="[newField]"
                        :disabled="!!saving"
                    />
                    <p
                        v-if="createError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ createError }}
                    </p>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="!!saving"
                            @click="creating = false"
                            >取消</Button
                        ><Button type="submit" :disabled="!!saving || !newName"
                            >保存</Button
                        ></DialogFooter
                    >
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
