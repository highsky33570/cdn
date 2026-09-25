<script setup lang="ts">
import { computed, provide, reactive, ref } from 'vue';
import { fieldEvent, isFirstInvalidField } from '@/components/ui/field-events';
import { Input } from '@/components/ui/input';
import {
    encodeSelectValue,
    decodeSelectValue,
    selectFieldOptions,
} from './field-value';
import Select from './Select.vue';
import SelectContent from './SelectContent.vue';
import SelectTrigger from './SelectTrigger.vue';
import SelectValue from './SelectValue.vue';

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    modelValue?: unknown;
    value?: unknown;
    disabled?: boolean;
    required?: boolean;
    name?: string;
    placeholder?: string;
    modelModifiers?: { number?: boolean; trim?: boolean };
}>();
const emit = defineEmits<{
    'update:modelValue': [value: string | number];
    change: [event: Event];
}>();
const local = ref<string | number>('');
const open = ref(false);
const current = computed(() => props.modelValue ?? props.value ?? local.value);
const options = reactive(new Map<string, unknown>());
provide(selectFieldOptions, (value) => {
    const key = String(value ?? '');
    options.set(key, value);

    return () => {
        options.delete(key);
    };
});
// Native selects compare the textual value when initially matching a model.
// Keep that behavior even when an API returns 1 for an option declared as "1".
const selected = computed(() => {
    const key = String(current.value);

    return encodeSelectValue(
        options.has(key) ? options.get(key) : current.value,
    );
});
function update(encoded: unknown) {
    let value = decodeSelectValue(String(encoded));

    if (props.modelModifiers?.trim && typeof value === 'string') {
        value = value.trim();
    }

    if (
        props.modelModifiers?.number &&
        value !== '' &&
        !Number.isNaN(Number(value))
    ) {
        value = Number(value);
    }

    local.value = value;
    emit('update:modelValue', value);
    emit(
        'change',
        fieldEvent('change', { value: String(value) }, (next) => {
            local.value = next;
        }),
    );
}
</script>

<template>
    <Select
        v-model:open="open"
        :model-value="selected"
        :disabled="disabled"
        @update:model-value="update"
    >
        <SelectTrigger v-bind="$attrs"
            ><SelectValue :placeholder="placeholder"
        /></SelectTrigger>
        <SelectContent class="z-[110]"><slot /></SelectContent>
    </Select>
    <Input
        v-if="required || name"
        :value="String(current)"
        :name="name"
        :required="required"
        :disabled="disabled"
        tabindex="-1"
        aria-hidden="true"
        class="sr-only"
        @invalid.prevent="open = isFirstInvalidField($event)"
    />
</template>
