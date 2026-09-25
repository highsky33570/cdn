<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import Switch from '@/components/ui/switch/Switch.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
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
        <Switch
            v-if="kind === 'toggle'"
            :aria-label="label"
            :checked="flag(value)"
            :disabled="disabled"
            @update:checked="emit('change', $event ? 1 : 0)"
        />
        <div
            v-else-if="kind === 'choices'"
            class="fw-choices"
            role="group"
            :aria-label="label"
        >
            <Button
                size="sm"
                variant="outline"
                v-for="option in options"
                :key="option.value"
                type="button"
                :disabled="disabled"
                :aria-pressed="String(value) === String(option.value)"
                :class="{ chosen: String(value) === String(option.value) }"
                @click="emit('change', option.value)"
            >
                <i />{{ option.label }}
            </Button>
        </div>
        <Textarea
            v-else-if="kind === 'textarea'"
            :aria-label="label"
            :value="String(value ?? '')"
            :placeholder="placeholder"
            :disabled="disabled"
            spellcheck="false"
            @change="input($event)"
        />
        <SelectField
            v-else-if="kind === 'select'"
            :aria-label="label"
            :value="String(value ?? '')"
            :disabled="disabled"
            @change="input($event)"
        >
            <SelectOption value="" disabled>请选择</SelectOption>
            <SelectOption
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </SelectOption>
        </SelectField>
        <div data-slot="console-input-group" v-else class="fw-input">
            <Input
                :aria-label="label"
                :type="
                    kind === 'number'
                        ? 'number'
                        : kind === 'password'
                          ? 'password'
                          : 'text'
                "
                :model-value="String(value ?? '')"
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
