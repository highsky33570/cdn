<script setup lang="ts">
import { computed } from 'vue';
import DefaultCollectionEditor from '@/components/console/DefaultCollectionEditor.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import RadioGroup from '@/components/ui/radio-group/RadioGroup.vue';
import RadioGroupItem from '@/components/ui/radio-group/RadioGroupItem.vue';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import Switch from '@/components/ui/switch/Switch.vue';
import type { ConfigObject, ConfigValue } from '@/lib/configEditor';
import { wafModules, wafModes } from '@/lib/defaultSettings';
import type { DefaultField, DefaultOption } from '@/lib/defaultSettings';
const props = defineProps<{
    field: DefaultField;
    value: ConfigValue;
    disabled?: boolean;
    id: string;
    options?: DefaultOption[];
}>();
const emit = defineEmits<{ change: [value: ConfigValue] }>();
const object = computed<ConfigObject>(() =>
    props.value &&
    typeof props.value === 'object' &&
    !Array.isArray(props.value)
        ? props.value
        : {},
);
const block = computed(() => ({
    enable: 0,
    window_seconds: 60,
    hit_threshold: 20,
    block_seconds: 600,
    ...object.value,
}));
const protocols = ['SSLv2', 'SSLv3', 'TLSv1', 'TLSv1.1', 'TLSv1.2', 'TLSv1.3'];
const choices = computed(() => props.options ?? props.field.options ?? []);
function protocol(name: string, checked: boolean) {
    const current = String(props.value ?? '')
        .split(/\s+/)
        .filter(Boolean);
    emit(
        'change',
        (checked
            ? [...new Set([...current, name])]
            : current.filter((item) => item !== name)
        ).join(' '),
    );
}
function batch(event: Event) {
    const el = event.target as HTMLSelectElement;

    if (!el.value) {
        return;
    }

    emit('change', {
        ...object.value,
        ...Object.fromEntries(
            Object.keys(wafModules).map((key) => [key, el.value]),
        ),
    });
    el.value = '';
}
</script>
<template>
    <DefaultCollectionEditor
        v-if="field.kind === 'cache' || field.kind === 'headers'"
        :kind="field.kind"
        :value="value"
        :disabled="disabled"
        @change="$emit('change', $event)"
    />
    <div v-else-if="field.kind === 'waf'" class="grid gap-3">
        <div class="flex justify-end">
            <SelectField
                aria-label="批量配置内置模块"
                class="h-8 rounded-md border border-input bg-background px-2 text-sm"
                :disabled="disabled"
                @change="batch"
            >
                <SelectOption value="">批量配置为</SelectOption>
                <SelectOption
                    v-for="mode in wafModes"
                    :key="mode.value"
                    :value="mode.value"
                >
                    {{ mode.label }}
                </SelectOption>
            </SelectField>
        </div>
        <div class="grid gap-2 rounded-lg border p-3 sm:grid-cols-2">
            <label
                v-for="(label, key) in wafModules"
                :key="key"
                class="flex items-center justify-between gap-2 rounded-md border bg-card p-2 text-sm"
                ><span>{{ label }}</span
                ><SelectField
                    :aria-label="label"
                    :value="object[key] ?? 'off'"
                    :disabled="disabled"
                    class="h-8 rounded-md border border-input bg-background px-2"
                    @change="
                        emit('change', {
                            ...object,
                            [key]: ($event.target as HTMLSelectElement).value,
                        })
                    "
                >
                    <SelectOption
                        v-for="mode in wafModes"
                        :key="mode.value"
                        :value="mode.value"
                    >
                        {{ mode.label }}
                    </SelectOption>
                </SelectField></label
            >
        </div>
    </div>
    <div
        v-else-if="field.kind === 'autoblock'"
        class="grid gap-3 rounded-lg border p-3"
    >
        <Switch
            :id="id"
            :aria-label="field.label"
            :checked="Number(block.enable) === 1"
            :disabled="disabled"
            @update:checked="
                emit('change', { ...block, enable: $event ? 1 : 0 })
            "
        />
        <div class="grid gap-3 sm:grid-cols-2">
            <label
                v-for="(label, key) in {
                    window_seconds: '统计窗口（秒）',
                    hit_threshold: '命中阈值（次）',
                    block_seconds: '封禁时长（秒）',
                }"
                :key="key"
                class="grid gap-1 text-xs"
                >{{ label
                }}<Input
                    :aria-label="label"
                    :model-value="String(block[key])"
                    type="number"
                    min="1"
                    :disabled="disabled"
                    @change="
                        emit('change', {
                            ...block,
                            [key]: Number(
                                ($event.target as HTMLInputElement).value,
                            ),
                        })
                    "
            /></label>
        </div>
    </div>
    <Switch
        v-else-if="field.kind === 'toggle'"
        :id="id"
        :aria-label="field.label"
        :checked="String(value) === '1'"
        :disabled="disabled"
        class="mt-2"
        @update:checked="$emit('change', $event ? '1' : '0')"
    />
    <div
        v-else-if="field.kind === 'protocols'"
        class="flex flex-wrap gap-3 pt-2"
        role="group"
        :aria-label="field.label"
    >
        <label
            v-for="name in protocols"
            :key="name"
            class="flex items-center gap-1 text-sm"
            ><Checkbox
                :aria-label="name"
                :model-value="
                    String(value ?? '')
                        .split(/\s+/)
                        .includes(name)
                "
                :disabled="disabled"
                @update:model-value="protocol(name, $event === true)"
            />{{ name }}</label
        >
    </div>
    <RadioGroup
        :model-value="String(value)"
        :disabled="disabled"
        @update:model-value="$emit('change', String($event))"
        :name="id"
        v-else-if="field.kind === 'choices'"
        :class="
            choices.some((option) => option.help)
                ? 'grid max-w-[600px] gap-2 sm:grid-cols-2'
                : 'flex flex-wrap items-center gap-3 pt-2'
        "
        :aria-label="field.label"
    >
        <label
            v-for="option in choices"
            :key="option.value"
            :class="
                option.help
                    ? [
                          'flex cursor-pointer items-start gap-2 rounded-md border p-3 text-sm',
                          String(value) === option.value
                              ? 'border-primary bg-accent'
                              : 'border-input bg-background',
                      ]
                    : 'flex items-center gap-1 text-sm'
            "
            ><RadioGroupItem
                :value="option.value"
                :disabled="disabled"
                class="mt-0.5 accent-primary"
            /><span
                >{{ option.label
                }}<small
                    v-if="option.help"
                    class="mt-1 block text-xs text-muted-foreground"
                    >{{ option.help }}</small
                ></span
            ></label
        >
    </RadioGroup>
    <SelectField
        v-else-if="field.kind === 'select'"
        :id="id"
        :aria-label="field.label"
        :value="String(value ?? '')"
        :disabled="disabled"
        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
        @change="$emit('change', ($event.target as HTMLSelectElement).value)"
    >
        <SelectOption value="" disabled>请选择</SelectOption>
        <SelectOption
            v-if="
                value !== '' &&
                !choices.some((option) => option.value === String(value))
            "
            :value="String(value)"
        >
            {{ value }}
        </SelectOption>
        <SelectOption
            v-for="option in choices"
            :key="option.value"
            :value="option.value"
        >
            {{ option.label }}
        </SelectOption>
    </SelectField>
    <div v-else class="flex min-w-0 items-center">
        <Input
            :id="id"
            :aria-label="field.label"
            :model-value="String(value ?? '')"
            :type="field.kind === 'number' ? 'number' : 'text'"
            :min="field.kind === 'number' ? 0 : undefined"
            :disabled="disabled"
            :class="field.unit ? 'rounded-r-none' : ''"
            @change="$emit('change', ($event.target as HTMLInputElement).value)"
        /><span
            v-if="field.unit"
            class="flex h-9 items-center rounded-r-md border border-l-0 border-input bg-muted px-2 text-xs text-muted-foreground"
            >{{ field.unit }}</span
        >
    </div>
</template>
