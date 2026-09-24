<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/formatters';
import { streamMetric } from '@/lib/streamAnalytics';
import type {
    StreamMetric,
    StreamRange,
    StreamSeries,
} from '@/lib/streamAnalytics';

const props = defineProps<{
    type: StreamMetric;
    series: StreamSeries;
    range: StreamRange;
    loading: boolean;
    error: string;
    heading?: string;
    area?: boolean;
}>();
const emit = defineEmits<{ retry: [] }>();
const title = computed(() =>
    props.type === 'stream-bandwidth' ? '带宽' : '流量',
);
const plot = ref<HTMLElement | null>(null),
    width = ref(700),
    hover = ref<number | null>(null);
let observer: ResizeObserver | undefined;
onMounted(() => {
    if (plot.value) {
        width.value = plot.value.clientWidth;
        observer = new ResizeObserver(([entry]) => {
            width.value = Math.max(240, entry.contentRect.width);
        });
        observer.observe(plot.value);
    }
});
onUnmounted(() => observer?.disconnect());
watch(
    () => props.series,
    () => {
        hover.value = null;
    },
);
const lines = computed(() =>
    [
        {
            key: 'outbound',
            label: props.series.inbound.length
                ? `出站${title.value}`
                : title.value,
            points: props.series.outbound,
            color: 'var(--stream-line)',
        },
        {
            key: 'inbound',
            label: `入站${title.value}`,
            points: props.series.inbound,
            color: 'var(--stream-inbound)',
        },
    ].filter((line) => line.points.length),
);
const times = computed(() =>
    [
        ...new Set(lines.value.flatMap((line) => line.points.map((p) => p[0]))),
    ].sort((a, b) => a - b),
);
const bounds = computed(() => {
    const start =
        times.value[0] ?? Date.parse(props.range.start.replace(' ', 'T'));
    const end =
        times.value.at(-1) ?? Date.parse(props.range.end.replace(' ', 'T'));

    return { start, end };
});
const maximum = computed(() =>
    Math.max(1, ...lines.value.flatMap((line) => line.points.map((p) => p[1]))),
);
const plotWidth = computed(() => Math.max(1, width.value - 100));
const x = (time: number) =>
    bounds.value.end === bounds.value.start
        ? 85 + plotWidth.value / 2
        : 85 +
          ((time - bounds.value.start) /
              (bounds.value.end - bounds.value.start)) *
              plotWidth.value;
const y = (value: number) => 250 - (value / maximum.value) * 210;
const linePath = (points: [number, number][]) =>
    points.map(([time, value]) => `${x(time)},${y(value)}`).join(' ');
const curvePath = (points: [number, number][]) =>
    points
        .map(([time, value], index) => {
            if (!index) {
return `M ${x(time)},${y(value)}`;
}

            const previous = points[index - 1];
            const middle = (x(previous[0]) + x(time)) / 2;

            return `C ${middle},${y(previous[1])} ${middle},${y(value)} ${x(time)},${y(value)}`;
        })
        .join(' ');
const areaPath = (points: [number, number][]) =>
    points.length
        ? `${curvePath(points)} L ${x(points.at(-1)![0])},250 L ${x(points[0][0])},250 Z`
        : '';
const ticks = computed(() => {
    const all = times.value.length
        ? times.value
        : [bounds.value.start, bounds.value.end];
    const count = Math.min(
        all.length,
        Math.max(2, Math.floor(plotWidth.value / 150)),
    );

    return [
        ...new Set(
            Array.from(
                { length: count },
                (_, i) =>
                    all[
                        Math.round(
                            (i / Math.max(1, count - 1)) * (all.length - 1),
                        )
                    ],
            ),
        ),
    ];
});
const shortTime = (value: number) =>
    formatDate(new Date(value).toISOString()).slice(5, 16).replace('-', '/');
const selected = computed(() =>
    hover.value === null
        ? null
        : {
              time: hover.value,
              values: lines.value.map((line) => ({
                  label: line.label,
                  color: line.color,
                  value: line.points.find((p) => p[0] === hover.value)?.[1],
              })),
          },
);
function inspect(event: PointerEvent): void {
    if (!times.value.length) {
        return;
    }

    const rect = (event.currentTarget as SVGSVGElement).getBoundingClientRect();
    const time =
        bounds.value.start +
        ((event.clientX - rect.left - 85) / plotWidth.value) *
            (bounds.value.end - bounds.value.start);
    hover.value = times.value.reduce(
        (closest, value) =>
            Math.abs(value - time) < Math.abs(closest - time) ? value : closest,
        times.value[0],
    );
}
</script>
<template>
    <section
        class="stream-chart relative min-w-0 rounded-md border bg-card p-4"
        :aria-label="`${title}图表`"
        :aria-busy="loading"
        :style="
            area
                ? {
                      '--stream-line': 'var(--primary)',
                      '--stream-inbound': 'var(--chart-2)',
                  }
                : undefined
        "
    >
        <h3 class="text-sm font-semibold">{{ heading ?? title }}</h3>
        <div
            v-if="series.inbound.length && !loading && !error"
            class="absolute top-4 right-4 flex gap-3 text-xs text-muted-foreground"
        >
            <span
                v-for="line in lines"
                :key="line.key"
                class="flex items-center gap-1"
                ><span
                    class="h-0.5 w-3"
                    :style="{ background: line.color }"
                />{{ line.label }}</span
            >
        </div>
        <div ref="plot" class="mt-2">
            <svg
                :viewBox="`0 0 ${width} 290`"
                class="w-full"
                :class="area ? 'h-80 sm:h-96' : 'h-72'"
                preserveAspectRatio="none"
                role="img"
                :aria-label="`${title}时间曲线`"
                @pointermove="inspect"
                @pointerleave="hover = null"
            >
                <g v-for="ratio in [0, 0.2, 0.4, 0.6, 0.8, 1]" :key="ratio">
                    <line
                        x1="85"
                        :x2="width - 15"
                        :y1="y(maximum * ratio)"
                        :y2="y(maximum * ratio)"
                        stroke="var(--border)"
                    />
                    <text
                        x="77"
                        :y="y(maximum * ratio) + 3"
                        text-anchor="end"
                        fill="var(--muted-foreground)"
                        font-size="10"
                    >
                        {{ streamMetric(maximum * ratio, type) }}
                    </text>
                </g>
                <template v-if="!loading && !error">
                    <g v-for="line in lines" :key="line.key">
                        <template v-if="area">
                            <path
                                :d="areaPath(line.points)"
                                :fill="line.color"
                                fill-opacity="0.08"
                            />
                            <path
                                :d="curvePath(line.points)"
                                fill="none"
                                :stroke="line.color"
                                stroke-width="2"
                                vector-effect="non-scaling-stroke"
                            />
                        </template>
                        <polyline
                            v-else
                            :points="linePath(line.points)"
                            fill="none"
                            :stroke="line.color"
                            stroke-width="2"
                            vector-effect="non-scaling-stroke"
                        />
                        <circle
                            v-for="point in line.points.length <= 80
                                ? line.points
                                : []"
                            :key="point[0]"
                            :cx="x(point[0])"
                            :cy="y(point[1])"
                            r="2"
                            fill="var(--card)"
                            :stroke="line.color"
                        >
                            <title>
                                {{
                                    formatDate(new Date(point[0]).toISOString())
                                }}
                                · {{ line.label }}
                                {{ streamMetric(point[1], type) }}
                            </title>
                        </circle>
                    </g>
                </template>
                <text
                    v-for="(time, index) in ticks"
                    :key="time"
                    :x="x(time)"
                    y="276"
                    :text-anchor="
                        ticks.length === 1
                            ? 'middle'
                            : index === 0
                              ? 'start'
                              : index === ticks.length - 1
                                ? 'end'
                                : 'middle'
                    "
                    fill="var(--muted-foreground)"
                    font-size="10"
                >
                    {{ shortTime(time) }}
                </text>
                <line
                    v-if="hover !== null && !loading && !error"
                    pointer-events="none"
                    :x1="x(hover)"
                    :x2="x(hover)"
                    y1="38"
                    y2="250"
                    stroke="var(--muted-foreground)"
                    stroke-dasharray="3 3"
                />
            </svg>
        </div>
        <div
            v-if="loading"
            class="absolute inset-x-4 top-14 bottom-8 flex items-center justify-center bg-card/90"
        >
            <Spinner /><span class="sr-only">加载{{ title }}</span>
        </div>
        <div
            v-else-if="error"
            class="absolute inset-x-5 top-14 bottom-10 flex flex-col items-center justify-center gap-2 bg-card/95 text-sm text-destructive"
        >
            <p role="alert" class="max-w-full break-words">{{ error }}</p>
            <Button
                variant="outline"
                size="sm"
                :aria-label="`重试${title}`"
                @click="emit('retry')"
                >重试</Button
            >
        </div>
        <p
            v-else-if="!times.length"
            class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-muted-foreground"
        >
            暂无{{ title }}数据
        </p>
        <div
            v-if="selected && !loading && !error"
            role="status"
            class="pointer-events-none absolute top-12 right-5 rounded border bg-popover p-3 text-xs text-popover-foreground shadow-md"
        >
            <p class="mb-2">
                {{ formatDate(new Date(selected.time).toISOString()) }}
            </p>
            <p
                v-for="value in selected.values"
                :key="value.label"
                class="flex justify-between gap-5"
            >
                <span :style="{ color: value.color }">{{ value.label }}</span
                >{{ streamMetric(value.value, type) }}
            </p>
        </div>
    </section>
</template>
<style scoped>
.stream-chart {
    --stream-line: #2d8cf0;
    --stream-inbound: #19be6b;
}
:global(.dark .stream-chart) {
    --stream-line: #60a5fa;
    --stream-inbound: #34d399;
}
</style>
