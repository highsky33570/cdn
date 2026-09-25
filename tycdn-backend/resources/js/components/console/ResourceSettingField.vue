<script setup lang="ts">
import { Input } from '@/components/ui/input';
import Switch from '@/components/ui/switch/Switch.vue';
import type { ResourceField } from '@/lib/resourceSettings';
defineProps<{
    field: ResourceField;
    value: string;
    disabled?: boolean;
    id: string;
}>();
defineEmits<{ change: [value: string] }>();
</script>
<template>
    <div
        class="resource-field grid min-w-0 items-start gap-2 sm:grid-cols-[145px_minmax(0,1fr)]"
    >
        <label :for="id" class="pt-2 text-sm">{{ field.label }}</label>
        <div class="min-w-0">
            <Switch
                v-if="field.kind === 'toggle'"
                :id="id"
                :aria-label="field.label"
                :checked="value === '1'"
                :disabled="disabled"
                class="mt-2"
                @update:checked="$emit('change', $event ? '1' : '0')"
            />
            <div
                data-slot="console-input-group"
                v-else
                class="flex min-w-0 items-center"
            >
                <Input
                    :id="id"
                    :aria-label="field.label"
                    :model-value="value"
                    :type="field.kind === 'number' ? 'number' : 'text'"
                    :min="field.kind === 'number' ? 0 : undefined"
                    :step="field.kind === 'number' ? 1 : undefined"
                    :disabled="disabled"
                    :class="field.unit ? 'rounded-r-none' : ''"
                    placeholder="未设置"
                    @change="
                        $emit(
                            'change',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
                <span
                    v-if="field.unit"
                    class="flex h-9 shrink-0 items-center rounded-r-md border border-l-0 border-input bg-muted px-2 text-xs text-muted-foreground"
                    >{{ field.unit }}</span
                >
            </div>
            <p
                data-typography="helper"
                v-if="field.help"
                class="mt-1 text-muted-foreground"
            >
                {{ field.help }}
            </p>
            <slot />
        </div>
    </div>
</template>
