<script setup lang="ts">
import ConsolePagination from '@/components/console/ConsolePagination.vue';
const page = defineModel<number>('page', { required: true });
const pageSize = defineModel<number>('pageSize', { required: true });
defineProps<{
    total: number;
    disabled?: boolean;
    numbered?: boolean;
    edgeLinks?: boolean;
}>();
</script>
<template>
    <ConsolePagination
        :total="total"
        :page="page"
        :previous-disabled="disabled || page <= 1"
        :next-disabled="disabled || page * pageSize >= total"
        @previous="page--"
        @next="page++"
    />
</template>
