<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-10 items-center justify-center rounded-full bg-gradient-to-br from-violet-600 to-sky-500 text-white"
    >
        <AppLogoIcon class="size-5" />
    </div>
    <div
        class="ml-2 grid flex-1 text-left group-data-[collapsible=icon]:hidden"
    >
        <span class="truncate text-base leading-tight font-semibold">
            TY CDN Console
        </span>
    </div>
</template>
