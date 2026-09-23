<script setup lang="ts">
import { computed } from 'vue';
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
            <button
                v-if="field.kind === 'toggle'"
                :id="id"
                type="button"
                role="switch"
                class="ng-switch"
                :class="{ on }"
                :aria-checked="on"
                :aria-labelledby="`${id}-label`"
                :disabled="disabled"
                @click="emit('change', on ? field.off! : field.on!)"
            >
                <span />
            </button>
            <div
                v-else-if="field.kind === 'version'"
                :id="id"
                class="ng-versions"
                role="group"
                :aria-labelledby="`${id}-label`"
            >
                <button
                    v-for="version in ['1.0', '1.1']"
                    :key="version"
                    type="button"
                    :disabled="disabled"
                    :class="{ chosen: String(value) === version }"
                    :aria-pressed="String(value) === version"
                    @click="emit('change', version)"
                >
                    {{ version }}
                </button>
            </div>
            <div v-else class="ng-input">
                <input
                    :id="id"
                    :type="field.kind === 'number' ? 'number' : 'text'"
                    :value="String(value ?? '')"
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
                /><span v-if="field.unit">{{ field.unit }}</span>
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
