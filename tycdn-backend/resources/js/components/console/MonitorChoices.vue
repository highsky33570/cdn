<script setup lang="ts">
defineProps<{
    label: string;
    options: { value: string | number; label: string }[];
    modelValue: unknown;
    multiple?: boolean;
}>();
const emit = defineEmits<{
    'update:modelValue': [value: string | number | (string | number)[]];
}>();
function select(
    value: string | number,
    current: unknown,
    multiple?: boolean,
): void {
    if (!multiple) {
        emit('update:modelValue', value);

        return;
    }

    const selected = Array.isArray(current) ? current : [];
    emit(
        'update:modelValue',
        selected.includes(value)
            ? selected.filter((v) => v !== value)
            : [...selected, value],
    );
}
</script>
<template>
    <div role="group" :aria-label="label" class="flex flex-wrap gap-2">
        <button
            data-slot="console-action"
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="
                multiple
                    ? Array.isArray(modelValue) &&
                      modelValue.includes(option.value)
                    : modelValue === option.value
            "
            class="h-8 rounded border bg-card px-3 text-sm text-muted-foreground disabled:opacity-50 aria-pressed:border-primary aria-pressed:bg-primary/5 aria-pressed:text-primary"
            @click="select(option.value, modelValue, multiple)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
