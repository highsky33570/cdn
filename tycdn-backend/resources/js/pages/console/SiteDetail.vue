<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SiteEditor from '@/components/console/SiteEditor.vue';
withDefaults(defineProps<{ siteId: string; scope?: 'admin' | 'user' }>(), {
    scope: 'user',
});
</script>
<template>
    <Head title="网站配置" /><SiteEditor
        :key="`${scope}-${siteId}`"
        :site-id="siteId"
        :scope="scope"
    />
</template>
