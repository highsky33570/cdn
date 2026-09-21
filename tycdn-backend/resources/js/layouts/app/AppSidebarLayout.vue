<script setup lang="ts">
import { onMounted, onBeforeUnmount } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import CdnflyModeBanner from '@/components/console/CdnflyModeBanner.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

onMounted(() => document.documentElement.classList.add('console-theme'));
onBeforeUnmount(() =>
    document.documentElement.classList.remove('console-theme'),
);

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar" class="console-shell">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <CdnflyModeBanner />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
