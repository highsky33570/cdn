<script setup lang="ts">
import { flag } from '@/lib/firewallSettings';
defineProps<{
    label: string;
    value: unknown;
    kind?: string;
    help?: string;
    unit?: string;
    min?: number;
    max?: number;
    options?: { value: string | number; label: string }[];
    disabled?: boolean;
    placeholder?: string;
}>();
const emit = defineEmits<{ change: [value: unknown] }>();
function input(event: Event, number = false) {
    const value = (event.target as HTMLInputElement).value;
    emit('change', number && value !== '' ? Number(value) : value);
}
</script>
<template>
    <div
        class="fw-field"
        :class="{
            'fw-toggle-field': kind === 'toggle',
            'fw-wide-field': kind === 'textarea' || kind === 'choices',
        }"
    >
        <div v-if="label" class="fw-label">
            <span>{{ label }}</span
            ><small v-if="help">{{ help }}</small>
        </div>
        <button
            v-if="kind === 'toggle'"
            type="button"
            role="switch"
            :aria-label="label"
            :aria-checked="flag(value)"
            :disabled="disabled"
            class="fw-switch"
            :class="{ on: flag(value) }"
            @click="emit('change', flag(value) ? 0 : 1)"
        >
            <span />
        </button>
        <div
            v-else-if="kind === 'choices'"
            class="fw-choices"
            role="group"
            :aria-label="label"
        >
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                :disabled="disabled"
                :aria-pressed="String(value) === String(option.value)"
                :class="{ chosen: String(value) === String(option.value) }"
                @click="emit('change', option.value)"
            >
                <i />{{ option.label }}
            </button>
        </div>
        <textarea
            v-else-if="kind === 'textarea'"
            :aria-label="label"
            :value="String(value ?? '')"
            :placeholder="placeholder"
            :disabled="disabled"
            spellcheck="false"
            @change="input($event)"
        />
        <select
            v-else-if="kind === 'select'"
            :aria-label="label"
            :value="String(value ?? '')"
            :disabled="disabled"
            @change="input($event)"
        >
            <option value="" disabled>请选择</option>
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>
        <div v-else class="fw-input">
            <input
                :aria-label="label"
                :type="
                    kind === 'number'
                        ? 'number'
                        : kind === 'password'
                          ? 'password'
                          : 'text'
                "
                :value="value ?? ''"
                :min="min"
                :max="max"
                :disabled="disabled"
                :placeholder="placeholder"
                autocomplete="off"
                @change="input($event, kind === 'number')"
            /><span v-if="unit">{{ unit }}</span>
        </div>
    </div>
</template>
