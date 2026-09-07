<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { PlugZap } from 'lucide-vue-next';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

const page = usePage();

const cdnfly = computed(() => page.props.cdnfly);
const outboundDisabled = computed(
    () => cdnfly.value?.outbound_enabled === false,
);
</script>

<template>
    <Alert
        v-if="outboundDisabled"
        class="mx-4 mt-4 border-amber-500/40 bg-amber-500/10 text-amber-950 md:mx-6 dark:text-amber-100"
    >
        <PlugZap data-icon="alert" />
        <AlertTitle>CDNfly 通讯已关闭</AlertTitle>
        <AlertDescription>
            当前处于开发保护模式。Laravel 会阻止真实 CDNfly
            出站请求；页面可继续完善交互，字段只按
            <a
                class="font-medium underline underline-offset-4"
                :href="cdnfly?.docs_url ?? 'https://doc.cdnfly.com'"
                target="_blank"
                rel="noreferrer"
            >
                doc.cdnfly.com
            </a>
            已确认的 API 文档接入。
        </AlertDescription>
    </Alert>
</template>
