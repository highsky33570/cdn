<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getField, setField, inferredFields } from '@/lib/configEditor';
import type { ConfigObject, ConfigValue, Field } from '@/lib/configEditor';

const props = defineProps<{
    modelValue: ConfigObject;
    fields?: Field[];
    disabled?: boolean;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: ConfigObject] }>();
const visibleFields = computed(
    () => props.fields ?? inferredFields(props.modelValue),
);
const read = (field: Field) => getField(props.modelValue, field.key);
const write = (field: Field, value: ConfigValue) =>
    emit('update:modelValue', setField(props.modelValue, field.key, value));
const object = (field: Field): ConfigObject => {
    const v = read(field);

    return v && typeof v === 'object' && !Array.isArray(v) ? v : {};
};
const array = (field: Field): ConfigValue[] =>
    Array.isArray(read(field)) ? (read(field) as ConfigValue[]) : [];
const malformed = (field: Field) =>
    read(field) !== null &&
    read(field) !== '' &&
    typeof read(field) !== 'object';
function updateItem(field: Field, index: number, value: ConfigValue) {
    const items = [...array(field)];
    items[index] = value;
    write(field, items);
}
function remove(field: Field, index: number) {
    write(
        field,
        array(field).filter((_, i) => i !== index),
    );
}
function move(field: Field, index: number, direction: number) {
    const items = [...array(field)];
    const next = index + direction;

    if (next < 0 || next >= items.length) {
        return;
    }

    [items[index], items[next]] = [items[next], items[index]];
    write(field, items);
}
function add(field: Field) {
    const initial =
        field.initial ??
        (field.fields
            ? Object.fromEntries(
                  field.fields.map((f) => [
                      f.key,
                      f.initial ??
                          (f.type === 'number'
                              ? 0
                              : f.type === 'toggle'
                                ? 0
                                : f.type === 'array'
                                  ? []
                                  : f.type === 'object'
                                    ? {}
                                    : ''),
                  ]),
              )
            : '');
    write(field, [...array(field), JSON.parse(JSON.stringify(initial))]);
}
</script>

<template>
    <div class="config-fields grid gap-5">
        <div
            v-for="field in visibleFields"
            :key="field.key"
            class="config-field min-w-0"
            :data-field="field.key"
        >
            <template v-if="field.type === 'object' || field.type === 'array'">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <Label class="font-semibold">{{ field.label }}</Label>
                    <Button
                        v-if="field.type === 'array'"
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="disabled || malformed(field)"
                        @click="add(field)"
                        ><Plus class="size-3.5" />新增</Button
                    >
                </div>
                <p v-if="malformed(field)" class="text-sm text-destructive">
                    现有配置无法解析，已保留原值。请重新加载后重试。
                </p>
                <ConfigFields
                    v-else-if="field.type === 'object'"
                    :model-value="object(field)"
                    :fields="field.fields"
                    :disabled="disabled"
                    class="rounded-lg border bg-muted/20 p-4"
                    @update:model-value="write(field, $event)"
                />
                <div v-else class="grid gap-3">
                    <p
                        v-if="!array(field).length"
                        class="rounded-lg border border-dashed p-5 text-center text-sm text-muted-foreground"
                    >
                        暂无{{ field.label }}
                    </p>
                    <div
                        v-for="(item, index) in array(field)"
                        :key="index"
                        class="rounded-lg border bg-muted/20 p-4"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-xs text-muted-foreground">{{
                                String(index + 1).padStart(2, '0')
                            }}</span>
                            <div class="flex gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :disabled="disabled || index === 0"
                                    aria-label="上移"
                                    @click="move(field, index, -1)"
                                    ><ArrowUp class="size-4"
                                /></Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :disabled="
                                        disabled ||
                                        index === array(field).length - 1
                                    "
                                    aria-label="下移"
                                    @click="move(field, index, 1)"
                                    ><ArrowDown class="size-4"
                                /></Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :disabled="disabled"
                                    aria-label="删除此项"
                                    @click="remove(field, index)"
                                    ><Trash2 class="size-4 text-destructive"
                                /></Button>
                            </div>
                        </div>
                        <ConfigFields
                            v-if="
                                item &&
                                typeof item === 'object' &&
                                !Array.isArray(item)
                            "
                            :model-value="item"
                            :fields="field.fields"
                            :disabled="disabled"
                            @update:model-value="
                                updateItem(field, index, $event)
                            "
                        />
                        <Input
                            v-else
                            :model-value="String(item ?? '')"
                            :disabled="disabled"
                            :aria-label="field.label"
                            @update:model-value="
                                updateItem(field, index, String($event))
                            "
                        />
                    </div>
                </div>
            </template>
            <label
                v-else-if="field.type === 'toggle'"
                class="flex cursor-pointer items-center justify-between gap-4 rounded-lg border p-3"
            >
                <span class="text-sm font-medium">{{ field.label }}</span
                ><input
                    type="checkbox"
                    role="switch"
                    class="size-4 accent-[var(--primary)]"
                    :checked="
                        [true, 1, '1', 'on'].includes(
                            read(field) as string | number | boolean,
                        )
                    "
                    :disabled="disabled"
                    @change="
                        write(
                            field,
                            typeof read(field) === 'boolean'
                                ? ($event.target as HTMLInputElement).checked
                                : ($event.target as HTMLInputElement).checked
                                  ? 1
                                  : 0,
                        )
                    "
                />
            </label>
            <label v-else class="grid gap-2 text-sm">
                <span class="font-medium"
                    >{{ field.label
                    }}<span v-if="field.required" class="ml-1 text-destructive"
                        >*</span
                    ></span
                >
                <select
                    v-if="field.type === 'select'"
                    :aria-label="field.label"
                    class="h-9 w-full rounded-md border bg-background px-3"
                    :value="read(field) ?? ''"
                    :disabled="disabled"
                    @change="
                        write(
                            field,
                            field.options?.find(
                                (o) =>
                                    String(o.value) ===
                                    ($event.target as HTMLSelectElement).value,
                            )?.value ??
                                ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="" disabled>请选择</option>
                    <option
                        v-if="
                            read(field) !== null &&
                            !field.options?.some(
                                (o) => String(o.value) === String(read(field)),
                            )
                        "
                        :value="String(read(field))"
                    >
                        {{ read(field) }}
                    </option>
                    <option
                        v-for="option in field.options"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <textarea
                    v-else-if="field.type === 'textarea'"
                    class="min-h-24 w-full rounded-md border bg-background px-3 py-2"
                    :value="
                        Array.isArray(read(field))
                            ? (read(field) as ConfigValue[]).join('\n')
                            : String(read(field) ?? '')
                    "
                    :disabled="disabled"
                    :required="field.required"
                    @input="
                        write(
                            field,
                            ($event.target as HTMLTextAreaElement).value,
                        )
                    "
                />
                <Input
                    v-else
                    :type="
                        field.type === 'password'
                            ? 'password'
                            : field.type === 'number'
                              ? 'number'
                              : 'text'
                    "
                    :model-value="
                        field.type === 'password' && read(field) === '••••••••'
                            ? ''
                            : String(read(field) ?? '')
                    "
                    :placeholder="
                        field.type === 'password' && read(field) === '••••••••'
                            ? '已配置，留空保持不变'
                            : undefined
                    "
                    :autocomplete="
                        field.type === 'password' ? 'new-password' : undefined
                    "
                    :disabled="disabled"
                    :required="field.required"
                    @update:model-value="
                        write(
                            field,
                            field.type === 'password' &&
                                String($event) === '' &&
                                read(field) === '••••••••'
                                ? '••••••••'
                                : field.type === 'number' &&
                                    String($event) !== ''
                                  ? Number($event)
                                  : String($event),
                        )
                    "
                />
            </label>
            <p
                v-if="field.help"
                class="mt-2 text-xs leading-relaxed text-muted-foreground"
            >
                {{ field.help }}
            </p>
        </div>
    </div>
</template>
