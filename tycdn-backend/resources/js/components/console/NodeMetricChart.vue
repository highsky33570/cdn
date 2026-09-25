<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { nodeDate, nodeValue } from '@/lib/nodeRealtime';
import type { NodeChart, NodePoint } from '@/lib/nodeRealtime';
const props = defineProps<{
    chart: NodeChart;
    range: { start: string; end: string };
}>();
const container = ref<HTMLElement>(),
    width = ref(800),
    hover = ref<number | null>(null),
    hidden = ref<string[]>([]);
let observer: ResizeObserver | undefined;
onMounted(() => {
    observer = new ResizeObserver(([entry]) => {
        width.value = Math.max(260, entry.contentRect.width);
    });

    if (container.value) {
        observer.observe(container.value);
    }
});
onUnmounted(() => observer?.disconnect());
watch(
    () => props.chart,
    () => {
        hidden.value = hidden.value.filter((name) =>
            props.chart.series.some((line) => line.name === name),
        );

        if (hover.value !== null && !times.value.includes(hover.value)) {
            hover.value = null;
        }
    },
);
const colors = ['#2d8cf0', '#19be6b', '#ff9900'];
const lines = computed(() =>
    props.chart.series
        .map((line, i) => ({ ...line, color: colors[i % colors.length] }))
        .filter((line) => !hidden.value.includes(line.name)),
);
const times = computed(() =>
    [
        ...new Set(lines.value.flatMap((line) => line.points.map((p) => p[0]))),
    ].sort((a, b) => a - b),
);
const bounds = computed(() => ({
    start: times.value[0] ?? Date.parse(props.range.start.replace(' ', 'T')),
    end: times.value.at(-1) ?? Date.parse(props.range.end.replace(' ', 'T')),
}));
const maximum = computed(() => {
    let max = 0;

    for (const line of lines.value) {
        for (const point of line.points) {
            max = Math.max(max, point[1]);
        }
    }

    if (!max) {
        return 1;
    }

    const step = 10 ** Math.floor(Math.log10(max));

    return Math.ceil(max / step) * step;
});
const plotWidth = computed(() => width.value - 108);
const x = (time: number) =>
    86 +
    (bounds.value.end === bounds.value.start
        ? 0.5
        : (time - bounds.value.start) /
          (bounds.value.end - bounds.value.start)) *
        plotWidth.value;
const y = (value: number) => 250 - (value / maximum.value) * 220;
const path = (data: NodePoint[]) =>
    data.map((p) => `${x(p[0])},${y(p[1])}`).join(' ');
const ticks = computed(() => {
    const n = Math.max(2, Math.floor(plotWidth.value / 120));

    return [
        ...new Set(
            Array.from(
                { length: n },
                (_, i) =>
                    bounds.value.start +
                    ((bounds.value.end - bounds.value.start) * i) / (n - 1),
            ),
        ),
    ];
});
const selected = computed(() =>
    lines.value.map((line) => ({
        ...line,
        value: line.points.find((p) => p[0] === hover.value)?.[1],
    })),
);
function inspect(event: PointerEvent) {
    if (!times.value.length) {
        return;
    }

    const rect = (event.currentTarget as SVGSVGElement).getBoundingClientRect();
    const target =
        bounds.value.start +
        ((event.clientX - rect.left - 86) / plotWidth.value) *
            (bounds.value.end - bounds.value.start);
    hover.value = times.value.reduce(
        (best, time) =>
            Math.abs(time - target) < Math.abs(best - target) ? time : best,
        times.value[0],
    );
}
function toggle(name: string) {
    hidden.value = hidden.value.includes(name)
        ? hidden.value.filter((value) => value !== name)
        : [...hidden.value, name];
}
</script>
<template>
    <section
        class="node-chart min-w-0 rounded-md border bg-card p-4"
        :aria-label="`${chart.title}图表`"
    >
        <div class="relative flex flex-wrap items-center gap-3">
            <h3 data-typography="section-title" class="font-semibold">
                {{ chart.title }}
            </h3>
            <div
                v-if="chart.series.length > 1"
                class="flex flex-1 justify-center gap-4 text-xs text-muted-foreground"
            >
                <Button
                    variant="link"
                    size="inline"
                    data-slot="console-link"
                    v-for="(line, i) in chart.series"
                    :key="line.name"
                    type="button"
                    class="flex items-center gap-1"
                    :aria-pressed="!hidden.includes(line.name)"
                    :class="{ 'opacity-40': hidden.includes(line.name) }"
                    @click="toggle(line.name)"
                >
                    <span
                        class="inline-block h-2 w-4 rounded-full border-2"
                        :style="{ borderColor: colors[i % colors.length] }"
                    />{{ line.name }}
                </Button>
            </div>
        </div>
        <div ref="container" class="relative mt-2 min-w-0">
            <svg
                :viewBox="`0 0 ${width} 290`"
                class="h-[290px] w-full"
                role="img"
                :aria-label="`${chart.title}时间曲线`"
                @pointermove="inspect"
                @pointerleave="hover = null"
            >
                <g v-for="ratio in [0, 0.2, 0.4, 0.6, 0.8, 1]" :key="ratio">
                    <line
                        x1="86"
                        :x2="width - 22"
                        :y1="y(maximum * ratio)"
                        :y2="y(maximum * ratio)"
                        stroke="var(--border)"
                    />
                    <text
                        x="79"
                        :y="y(maximum * ratio) + 4"
                        text-anchor="end"
                        fill="var(--muted-foreground)"
                        font-size="11"
                    >
                        {{ nodeValue(maximum * ratio, chart.unit) }}
                    </text>
                </g>
                <text
                    v-for="(time, i) in ticks"
                    :key="i"
                    :x="x(time)"
                    y="272"
                    :text-anchor="
                        i === 0
                            ? 'start'
                            : i === ticks.length - 1
                              ? 'end'
                              : 'middle'
                    "
                    fill="var(--muted-foreground)"
                    font-size="11"
                >
                    {{
                        nodeDate(new Date(time)).slice(5, 16).replace('-', '/')
                    }}
                </text>
                <g v-for="line in lines" :key="line.name">
                    <polyline
                        :points="path(line.points)"
                        fill="none"
                        :stroke="line.color"
                        stroke-width="1.8"
                        vector-effect="non-scaling-stroke"
                    />
                    <circle
                        v-if="line.points.length === 1"
                        :cx="x(line.points[0][0])"
                        :cy="y(line.points[0][1])"
                        r="2"
                        :fill="line.color"
                    />
                </g>
                <text
                    v-if="!times.length"
                    :x="width / 2"
                    y="140"
                    text-anchor="middle"
                    fill="var(--muted-foreground)"
                    font-size="12"
                >
                    暂无数据
                </text>
                <line
                    v-if="hover !== null"
                    :x1="x(hover)"
                    :x2="x(hover)"
                    y1="30"
                    y2="250"
                    stroke="var(--muted-foreground)"
                    stroke-dasharray="3 3"
                />
            </svg>
            <div
                v-if="hover !== null"
                class="pointer-events-none absolute top-10 z-10 rounded border bg-popover p-3 text-xs text-popover-foreground shadow-md"
                :style="{
                    left: `${Math.max(0, Math.min(x(hover) + 12, width - 210))}px`,
                }"
            >
                <p data-typography="body" class="mb-1">
                    {{ nodeDate(new Date(hover)) }}
                </p>
                <p
                    data-typography="body"
                    v-for="line in selected"
                    :key="line.name"
                >
                    <span :style="{ color: line.color }">●</span>
                    {{ line.name }}:
                    {{
                        line.value === undefined
                            ? '—'
                            : nodeValue(line.value, chart.unit)
                    }}
                </p>
            </div>
        </div>
    </section>
</template>
