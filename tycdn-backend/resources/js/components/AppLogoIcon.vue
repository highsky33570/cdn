<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 64 64"
        :class="className"
        fill="none"
        v-bind="$attrs"
    >
        <path
            d="M16 30.5 32 21l16 9.5v19L32 59 16 49.5v-19Z"
            stroke="currentColor"
            stroke-width="4"
            stroke-linejoin="round"
        />
        <path
            d="M16 31l16 9 16-9M32 40v18"
            stroke="currentColor"
            stroke-width="4"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <path
            d="M20 16.5 32 9l12 7.5M32 9v12"
            stroke="currentColor"
            stroke-width="4"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <circle cx="16" cy="31" r="4" fill="currentColor" />
        <circle cx="48" cy="31" r="4" fill="currentColor" />
        <circle cx="32" cy="9" r="4" fill="currentColor" />
    </svg>
</template>
