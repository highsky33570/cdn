<script setup lang="ts">
import { RadioGroupRoot, useForwardPropsEmits } from 'reka-ui';
import type { RadioGroupRootProps, RadioGroupRootEmits } from 'reka-ui';
const props = defineProps<RadioGroupRootProps>();
const emit = defineEmits<RadioGroupRootEmits>();
const forwarded = useForwardPropsEmits(props, emit);
</script>
<template>
    <RadioGroupRoot v-bind="forwarded" data-slot="radio-group"
        ><slot
    /></RadioGroupRoot>
</template>
