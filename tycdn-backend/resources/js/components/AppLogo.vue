<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sky-600 text-white shadow-sm dark:bg-sky-500"
    >
        <AppLogoIcon class="size-5" />
    </div>
    <div
        class="ml-1 grid flex-1 text-left text-sm group-data-[collapsible=icon]:hidden"
    >
        <span class="mb-0.5 truncate leading-tight font-semibold">
            TY CDN Console
        </span>
    </div>
</template>
