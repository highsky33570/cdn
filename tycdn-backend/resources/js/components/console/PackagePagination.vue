<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
const page = defineModel<number>('page', { required: true });
const pageSize = defineModel<number>('pageSize', { required: true });
const props = defineProps<{
    total: number;
    disabled?: boolean;
    numbered?: boolean;
    edgeLinks?: boolean;
}>();
const visiblePages = computed(() => {
    const count = Math.max(1, Math.ceil(props.total / pageSize.value));
    const start = Math.max(1, Math.min(page.value - 2, count - 4));

    return Array.from(
        { length: Math.min(5, count) },
        (_, index) => start + index,
    );
});
</script>
<template>
    <div class="mt-4 flex flex-wrap items-center justify-end gap-2 text-sm">
        <span>共 {{ total }} 条</span>
        <Button
            size="sm"
            variant="outline"
            aria-label="上一页"
            :disabled="disabled || page <= 1"
            @click="page--"
            >‹</Button
        >
        <template v-if="numbered"
            ><template v-if="edgeLinks && visiblePages[0] > 1"
                ><Button
                    size="sm"
                    variant="outline"
                    aria-label="第 1 页"
                    :disabled="disabled"
                    @click="page = 1"
                    >1</Button
                ><span v-if="visiblePages[0] > 2" aria-hidden="true"
                    >…</span
                ></template
            ><Button
                v-for="number in visiblePages"
                :key="number"
                size="sm"
                :variant="number === page ? 'default' : 'outline'"
                :aria-label="`第 ${number} 页`"
                :aria-current="number === page ? 'page' : undefined"
                :disabled="disabled"
                @click="page = number"
                >{{ number }}</Button
            ><template
                v-if="
                    edgeLinks &&
                    visiblePages[visiblePages.length - 1] <
                        Math.ceil(total / pageSize)
                "
                ><span
                    v-if="
                        visiblePages[visiblePages.length - 1] <
                        Math.ceil(total / pageSize) - 1
                    "
                    aria-hidden="true"
                    >…</span
                ><Button
                    size="sm"
                    variant="outline"
                    :aria-label="`第 ${Math.ceil(total / pageSize)} 页`"
                    :disabled="disabled"
                    @click="page = Math.ceil(total / pageSize)"
                    >{{ Math.ceil(total / pageSize) }}</Button
                ></template
            ></template
        >
        <span v-else class="min-w-7 text-center text-primary">{{ page }}</span>
        <Button
            size="sm"
            variant="outline"
            aria-label="下一页"
            :disabled="disabled || page * pageSize >= total"
            @click="page++"
            >›</Button
        >
        <select
            v-model="pageSize"
            aria-label="每页条数"
            :disabled="disabled"
            class="h-8 rounded-md border border-input bg-background px-2 text-foreground"
        >
            <option :value="10">10 条/页</option>
            <option :value="20">20 条/页</option>
            <option :value="50">50 条/页</option>
        </select>
    </div>
</template>
