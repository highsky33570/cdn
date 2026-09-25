<script setup lang="ts">
import { computed } from 'vue';
import { fieldEvent } from '@/components/ui/field-events';
import Checkbox from './Checkbox.vue';
const props = withDefaults(
    defineProps<{
        modelValue?: boolean | (string | number)[];
        checked?: boolean;
        indeterminate?: boolean;
        value?: string | number;
        disabled?: boolean;
    }>(),
    { modelValue: undefined },
);
const emit = defineEmits<{
    'update:modelValue': [value: boolean | (string | number)[]];
    change: [event: Event];
}>();
const state = computed(() =>
    props.indeterminate
        ? 'indeterminate'
        : Array.isArray(props.modelValue)
          ? props.modelValue.includes(props.value ?? 'on')
          : (props.modelValue ?? props.checked ?? false),
);
function update(value: boolean | 'indeterminate') {
    const checked = value === true;
    const item = props.value ?? 'on';
    const next = Array.isArray(props.modelValue)
        ? checked
            ? [...new Set([...props.modelValue, item])]
            : props.modelValue.filter((v) => v !== item)
        : checked;
    emit('update:modelValue', next);
    emit('change', fieldEvent('change', { value: String(item), checked }));
}
</script>
<template>
    <Checkbox
        :model-value="state"
        :value="value"
        :disabled="disabled"
        @update:model-value="update"
    />
</template>
