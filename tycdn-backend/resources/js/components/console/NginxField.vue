<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Switch from '@/components/ui/switch/Switch.vue';
import type { NginxField } from '@/lib/nginxSettings';
const props = defineProps<{
    field: NginxField;
    value: unknown;
    disabled?: boolean;
    error?: string;
    context?: string;
}>();
const emit = defineEmits<{ change: [value: string] }>();
const id = computed(
    () =>
        `nginx-${props.context ?? 'global'}-${props.field.path.replaceAll('.', '-')}`,
);
const on = computed(() => String(props.value) === props.field.on);
</script>
<template>
    <div class="ng-field" :class="{ 'has-error': error }">
        <label :id="`${id}-label`" :for="id">{{ field.label }}</label>
        <div class="ng-control">
            <Switch
                v-if="field.kind === 'toggle'"
                :id="id"
                :checked="on"
                :aria-labelledby="`${id}-label`"
                :disabled="disabled"
                @update:checked="
                    emit('change', $event ? field.on! : field.off!)
                "
            />
            <div
                v-else-if="field.kind === 'version'"
                :id="id"
                class="ng-versions"
                role="group"
                :aria-labelledby="`${id}-label`"
            >
                <Button
                    size="sm"
                    v-for="version in ['1.0', '1.1']"
                    :key="version"
                    type="button"
                    :disabled="disabled"
                    :variant="
                        String(value) === version ? 'secondary' : 'outline'
                    "
                    :aria-pressed="String(value) === version"
                    @click="emit('change', version)"
                >
                    {{ version }}
                </Button>
            </div>
            <div v-else class="ng-input">
                <Input
                    :id="id"
                    :type="field.kind === 'number' ? 'number' : 'text'"
                    :model-value="String(value ?? '')"
                    :class="field.unit ? 'rounded-r-none' : ''"
                    :min="field.min"
                    :max="field.max"
                    :disabled="disabled"
                    :aria-invalid="!!error"
                    :aria-describedby="
                        error
                            ? `${id}-error`
                            : field.help
                              ? `${id}-help`
                              : undefined
                    "
                    autocomplete="off"
                    @change="
                        emit(
                            'change',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                /><span
                    v-if="field.unit"
                    class="flex h-9 shrink-0 items-center rounded-r-md border border-l-0 border-input bg-muted px-2 text-xs text-muted-foreground"
                    >{{ field.unit }}</span
                >
            </div>
            <p v-if="field.help" :id="`${id}-help`" class="ng-field-help">
                {{ field.help }}
            </p>
            <p v-if="error" :id="`${id}-error`" class="ng-field-error">
                {{ error }}
            </p>
        </div>
    </div>
</template>
