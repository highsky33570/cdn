<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
const props = defineProps<{
    modelValue?: string | number;
    value?: string | number;
    defaultValue?: string | number;
    modelModifiers?: { trim?: boolean; lazy?: boolean };
    class?: HTMLAttributes['class'];
}>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const local = ref(props.modelValue ?? props.value ?? props.defaultValue ?? '');
watch(
    () => props.modelValue ?? props.value,
    (value) => {
        if (value !== undefined) {
            local.value = value;
        }
    },
);
const current = computed({
    get: () => local.value,
    set: (value) => {
        local.value = value;

        if (!props.modelModifiers?.lazy) {
            update(String(value));
        }
    },
});
function update(value: string) {
    if (props.modelModifiers?.trim) {
        value = value.trim();
    }

    emit('update:modelValue', value);
}
</script>
<template>
    <textarea
        data-slot="textarea"
        v-model="current"
        :class="
            cn(
                'flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30',
                props.class,
            )
        "
        @change="
            modelModifiers?.lazy &&
            update(($event.target as HTMLTextAreaElement).value)
        "
    />
</template>
