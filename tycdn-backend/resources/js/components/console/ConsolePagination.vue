<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{
    total: number | null | undefined;
    page: number;
    previousDisabled?: boolean;
    nextDisabled?: boolean;
}>();

defineEmits<{ previous: []; next: [] }>();
</script>

<template>
    <nav data-slot="console-pagination" aria-label="分页">
        <span data-slot="pagination-total">共 {{ total ?? '—' }} 条</span>
        <div data-slot="pagination-controls">
            <Button
                variant="outline"
                size="sm"
                :disabled="previousDisabled || page <= 1"
                aria-label="上一页"
                @click="$emit('previous')"
                >上一页</Button
            >
            <span
                data-slot="pagination-current"
                aria-live="polite"
                aria-atomic="true"
                >第 {{ Math.max(1, page) }} 页</span
            >
            <Button
                variant="outline"
                size="sm"
                :disabled="nextDisabled || total === 0"
                aria-label="下一页"
                @click="$emit('next')"
                >下一页</Button
            >
        </div>
    </nav>
</template>
