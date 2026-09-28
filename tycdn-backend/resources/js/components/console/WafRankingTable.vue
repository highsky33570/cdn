<script setup lang="ts">
import { ArrowDownUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { WafRank } from '@/lib/wafLogs';
const props = defineProps<{
    label: string;
    rows: WafRank[];
    share?: boolean;
}>();
const emit = defineEmits<{ filter: [value: Record<string, string>] }>();
const direction = ref<0 | 1 | -1>(0);
const sorted = computed(() =>
    direction.value
        ? [...props.rows].sort((a, b) => (a.count - b.count) * direction.value)
        : props.rows,
);
</script>
<template>
    <div class="min-w-0 overflow-x-auto rounded-md border">
        <table
            class="w-full min-w-[320px] table-fixed text-left text-sm"
            :aria-label="label"
        >
            <thead class="bg-muted/30 text-muted-foreground">
                <tr>
                    <th class="font-semibold">
                        <div data-slot="table-cell-content">{{ label }}</div>
                    </th>
                    <th v-if="share" class="w-1/3 border-l font-semibold">
                        <div data-slot="table-cell-content">占比</div>
                    </th>
                    <th
                        class="w-24 border-l text-right font-semibold"
                        :aria-sort="
                            direction === 0
                                ? 'none'
                                : direction === 1
                                  ? 'ascending'
                                  : 'descending'
                        "
                    >
                        <div data-slot="table-cell-content">
                            <Button
                                variant="ghost"
                                size="inline"
                                data-slot="console-sort"
                                type="button"
                                class="inline-flex items-center gap-1"
                                :aria-label="`按${label}次数排序`"
                                @click="direction = direction === -1 ? 1 : -1"
                            >
                                次数<ArrowDownUp class="size-3" />
                            </Button>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, index) in sorted"
                    :key="index"
                    class="border-t"
                >
                    <td class="">
                        <div data-slot="table-cell-content">
                            <Button
                                variant="link"
                                size="inline"
                                type="button"
                                class="flex max-w-full items-center gap-2 text-left text-primary hover:underline"
                                :title="row.display"
                                @click="emit('filter', row.filter)"
                            >
                                <span
                                    v-if="share"
                                    class="size-2 shrink-0 rounded-full"
                                    :style="{ background: row.color }"
                                /><span class="truncate">{{
                                    row.display
                                }}</span>
                            </Button>
                        </div>
                    </td>
                    <td v-if="share" class="">
                        <div data-slot="table-cell-content">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 flex-1 rounded bg-muted">
                                    <div
                                        class="h-full rounded"
                                        :style="{
                                            width: `${row.percent}%`,
                                            background: row.color,
                                        }"
                                    />
                                </div>
                                <span
                                    class="w-16 text-right text-xs text-muted-foreground"
                                    >{{ row.percent.toFixed(2) }}%</span
                                >
                            </div>
                        </div>
                    </td>
                    <td class="text-right tabular-nums">
                        <div data-slot="table-cell-content">
                            {{ row.count.toLocaleString() }}
                        </div>
                    </td>
                </tr>
                <tr v-if="!rows.length" class="border-t">
                    <td
                        :colspan="share ? 3 : 2"
                        class="text-center text-xs text-muted-foreground"
                    >
                        <div data-slot="table-cell-content">暂无数据</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
