<script setup lang="ts">
import { inject, watch } from 'vue';
import { encodeSelectValue, selectFieldOptions } from './field-value';
import SelectItem from './SelectItem.vue';
const props = defineProps<{ value: unknown; disabled?: boolean }>();
const register = inject(selectFieldOptions, undefined);
watch(
    () => props.value,
    (value, _old, cleanup) => {
        const unregister = register?.(value);

        if (unregister) {
            cleanup(unregister);
        }
    },
    { immediate: true },
);
</script>
<template>
    <SelectItem
        :value="encodeSelectValue(value)"
        :data-field-value="String(value ?? '')"
        :disabled="disabled"
        ><slot
    /></SelectItem>
</template>
