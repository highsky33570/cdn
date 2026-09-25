<script setup lang="ts">
import { parseDate } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import { CalendarDays } from 'lucide-vue-next';
import {
    PopoverRoot,
    PopoverTrigger,
    PopoverPortal,
    PopoverContent,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import Calendar from '@/components/ui/calendar/Calendar.vue';
import { fieldEvent, isFirstInvalidField } from '@/components/ui/field-events';
import { Input } from '@/components/ui/input';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        modelValue?: string | number;
        value?: string | number;
        type?: string;
        disabled?: boolean;
        readonly?: boolean;
        min?: string;
        max?: string;
        step?: number | string;
        placeholder?: string;
        required?: boolean;
        name?: string;
    }>(),
    { type: 'date' },
);
const emit = defineEmits<{
    'update:modelValue': [value: string];
    input: [event: Event];
    change: [event: Event];
}>();
const open = ref(false);
const local = ref('');
const current = computed(() =>
    String(props.modelValue ?? props.value ?? local.value),
);
const day = ref<DateValue>();
const hour = ref('00');
const minute = ref('00');
const second = ref('00');
const withTime = computed(() => props.type === 'datetime-local');
const withSeconds = computed(
    () => withTime.value && props.step !== undefined && Number(props.step) < 60,
);
function date(value: string | undefined) {
    try {
        return value ? parseDate(value.slice(0, 10)) : undefined;
    } catch {
        return undefined;
    }
}
function sync() {
    day.value = date(current.value);
    const time = current.value.slice(11).split(':');
    hour.value = time[0] || '00';
    minute.value = time[1] || '00';
    second.value = time[2] || '00';
}
watch(open, (value) => {
    if (value) {
        sync();
    }
});
watch(current, sync, { immediate: true });
const draft = computed(() =>
    day.value
        ? `${day.value.toString()}${withTime.value ? `T${hour.value}:${minute.value}${withSeconds.value ? `:${second.value}` : ''}` : ''}`
        : '',
);
const valid = computed(
    () =>
        !!draft.value &&
        (!props.min || draft.value >= props.min) &&
        (!props.max || draft.value <= props.max),
);
function commit(value: string) {
    local.value = value;
    emit('update:modelValue', value);
    emit('input', fieldEvent('input', { value }));
    emit('change', fieldEvent('change', { value }));
    open.value = false;
}
function choose(value: DateValue | undefined) {
    day.value = value;

    if (!withTime.value && valid.value) {
        commit(draft.value);
    }
}
function numbers(count: number) {
    return Array.from({ length: count }, (_, index) =>
        String(index).padStart(2, '0'),
    );
}
</script>
<template>
    <Input
        v-if="type !== 'date' && type !== 'datetime-local'"
        v-bind="$attrs"
        :model-value="current"
        :type="type"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :name="name"
        :placeholder="placeholder"
        @update:model-value="
            local = String($event);
            emit('update:modelValue', String($event));
        "
        @input="emit('input', $event)"
        @change="emit('change', $event)"
    />
    <template v-else
        ><PopoverRoot v-model:open="open">
            <PopoverTrigger as-child
                ><Button
                    v-bind="$attrs"
                    type="button"
                    variant="outline"
                    data-slot="date-picker"
                    :disabled="disabled || readonly"
                    class="min-w-0 justify-between font-normal"
                    :class="!current && 'text-muted-foreground'"
                    ><span class="truncate">{{
                        current.replace('T', ' ') ||
                        placeholder ||
                        (withTime ? '请选择日期和时间' : '请选择日期')
                    }}</span
                    ><CalendarDays
                        class="size-4 shrink-0 text-muted-foreground" /></Button
            ></PopoverTrigger>
            <PopoverPortal
                ><PopoverContent
                    align="start"
                    :side-offset="6"
                    class="z-[100] max-w-[calc(100vw-2rem)] rounded-md border bg-popover text-popover-foreground shadow-md"
                    aria-label="选择日期"
                >
                    <Calendar
                        :model-value="day"
                        :min-value="date(min)"
                        :max-value="date(max)"
                        initial-focus
                        @update:model-value="
                            choose($event as DateValue | undefined)
                        "
                    />
                    <div
                        v-if="withTime"
                        class="flex items-center gap-2 border-t p-3"
                    >
                        <span class="text-sm">时间</span>
                        <SelectField v-model="hour" aria-label="小时"
                            ><SelectOption
                                v-for="n in numbers(24)"
                                :key="n"
                                :value="n"
                                >{{ n }}</SelectOption
                            ></SelectField
                        ><span>:</span>
                        <SelectField v-model="minute" aria-label="分钟"
                            ><SelectOption
                                v-for="n in numbers(60)"
                                :key="n"
                                :value="n"
                                >{{ n }}</SelectOption
                            ></SelectField
                        >
                        <template v-if="withSeconds"
                            ><span>:</span
                            ><SelectField v-model="second" aria-label="秒"
                                ><SelectOption
                                    v-for="n in numbers(60)"
                                    :key="n"
                                    :value="n"
                                    >{{ n }}</SelectOption
                                ></SelectField
                            ></template
                        >
                    </div>
                    <div class="flex justify-between gap-2 border-t p-3">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="commit('')"
                            >清除</Button
                        ><Button
                            v-if="withTime"
                            type="button"
                            size="sm"
                            :disabled="!valid"
                            @click="commit(draft)"
                            >确定</Button
                        >
                    </div>
                </PopoverContent></PopoverPortal
            >
        </PopoverRoot>
        <Input
            v-if="required || name"
            :value="current"
            :name="name"
            :required="required"
            :disabled="disabled"
            tabindex="-1"
            aria-hidden="true"
            class="sr-only"
            @invalid.prevent="open = isFirstInvalidField($event)"
    /></template>
</template>
