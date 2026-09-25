<script setup lang="ts">
import { reactiveOmit } from '@vueuse/core';
import { RadioGroupItem, RadioGroupIndicator, useForwardProps } from 'reka-ui';
import type { RadioGroupItemProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
const props = defineProps<
    RadioGroupItemProps & { class?: HTMLAttributes['class'] }
>();
const forwarded = useForwardProps(reactiveOmit(props, 'class'));
</script>
<template>
    <RadioGroupItem
        v-bind="forwarded"
        data-slot="radio-group-item"
        :class="
            cn(
                'inline-flex size-4 shrink-0 items-center justify-center rounded-full border border-input bg-background text-primary outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:border-primary',
                props.class,
            )
        "
    >
        <RadioGroupIndicator class="size-2.5 rounded-full bg-current" />
    </RadioGroupItem>
</template>
