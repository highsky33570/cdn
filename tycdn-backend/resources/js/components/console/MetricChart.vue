<script setup lang="ts">
import { computed } from 'vue';
import type { MonitorSeries } from '@/lib/monitorSeries';
const props = defineProps<{ series: MonitorSeries }>();
const max = computed(() =>
    Math.max(1, ...props.series.points.map((p) => p[1])),
);
const line = computed(() =>
    props.series.points
        .map(
            (p, i) =>
                `${40 + (i / Math.max(1, props.series.points.length - 1)) * 700},${210 - (p[1] / max.value) * 180}`,
        )
        .join(' '),
);
const time = (value: number) =>
    new Date(value < 1e12 ? value * 1000 : value).toLocaleString();
</script>
<template>
    <div class="rounded-xl border bg-card p-5">
        <div class="flex justify-between gap-4">
            <h3 data-typography="section-title" class="font-semibold">
                {{ series.name }}
            </h3>
            <span class="text-sm text-muted-foreground tabular-nums">{{
                series.points.at(-1)?.[1]?.toLocaleString()
            }}</span>
        </div>
        <svg
            viewBox="0 0 780 260"
            class="mt-4 w-full"
            role="img"
            :aria-label="`${series.name}，${series.points.length} 个监测样本`"
        >
            <g stroke="var(--border)" stroke-width="1">
                <line
                    v-for="y in [30, 90, 150, 210]"
                    :key="y"
                    x1="40"
                    x2="740"
                    :y1="y"
                    :y2="y"
                />
            </g>
            <text x="40" y="20" fill="var(--muted-foreground)" font-size="12">
                {{ max.toLocaleString() }}
            </text>
            <polyline
                :points="line"
                fill="none"
                stroke="var(--primary)"
                stroke-width="2.5"
            />
            <text x="40" y="245" fill="var(--muted-foreground)" font-size="12">
                {{ time(series.points[0][0]) }}
            </text>
            <text
                x="740"
                y="245"
                text-anchor="end"
                fill="var(--muted-foreground)"
                font-size="12"
            >
                {{ time(series.points.at(-1)![0]) }}
            </text>
        </svg>
        <details class="text-xs text-muted-foreground">
            <summary class="cursor-pointer">查看样本</summary>
            <div class="mt-3 max-h-48 overflow-auto">
                <table class="w-full">
                    <tbody>
                        <tr v-for="point in series.points" :key="point[0]">
                            <td>{{ time(point[0]) }}</td>
                            <td class="text-right">
                                {{ point[1].toLocaleString() }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</template>
