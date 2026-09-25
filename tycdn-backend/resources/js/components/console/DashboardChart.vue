<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, useId, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { dashboardMetric } from '@/lib/adminDashboard';
import type { DashboardPoint, DashboardMetric } from '@/lib/adminDashboard';

const props = defineProps<{
    title: string;
    points: DashboardPoint[];
    metric: DashboardMetric;
    kind: 'bar' | 'line';
    loading: boolean;
    error: string;
}>();
defineEmits<{ retry: [] }>();
const plot = ref<HTMLElement>(),
    width = ref(350),
    hover = ref<number | null>(null);
const gradient = useId().replaceAll(':', '');
let observer: ResizeObserver | undefined;
onMounted(() => {
    if (plot.value) {
        observer = new ResizeObserver(([entry]) => {
            width.value = Math.max(200, entry.contentRect.width);
        });
        observer.observe(plot.value);
    }
});
onUnmounted(() => observer?.disconnect());
watch(
    () => props.points,
    () => {
        hover.value = null;
    },
);
const height = computed(() => (props.kind === 'bar' ? 180 : 350));
const left = computed(() => (props.kind === 'bar' ? 36 : 90));
const bottom = computed(() => height.value - 30);
const maximum = computed(() => {
    const value = Math.max(0, ...props.points.map((p) => p.value));

    if (!value) {
        return 1;
    }

    const step = 10 ** Math.floor(Math.log10(value));

    return Math.ceil(value / step) * step;
});
const plotWidth = computed(() => Math.max(1, width.value - left.value - 16));
const x = (i: number) =>
    left.value +
    (props.kind === 'bar'
        ? (i + 0.5) / props.points.length
        : props.points.length === 1
          ? 0.5
          : i / (props.points.length - 1)) *
        plotWidth.value;
const y = (n: number) =>
    bottom.value - (n / maximum.value) * (bottom.value - 15);
const path = computed(() =>
    props.points.map((p, i) => `${x(i)},${y(p.value)}`).join(' '),
);
const labelIndexes = computed(() => {
    const count = Math.min(
        props.points.length,
        Math.max(
            2,
            Math.floor(plotWidth.value / (props.kind === 'bar' ? 55 : 140)),
        ),
    );

    return [
        ...new Set(
            Array.from({ length: count }, (_, i) =>
                Math.round(
                    (i * (props.points.length - 1)) / Math.max(1, count - 1),
                ),
            ),
        ),
    ];
});
function hoverPlot(event: MouseEvent) {
    if (!props.points.length || !plot.value) {
        return;
    }

    const n =
        (event.clientX - plot.value.getBoundingClientRect().left - left.value) /
        plotWidth.value;
    hover.value = Math.max(
        0,
        Math.min(
            props.points.length - 1,
            props.kind === 'bar'
                ? Math.floor(n * props.points.length)
                : Math.round(n * (props.points.length - 1)),
        ),
    );
}
const axis = (n: number) =>
    props.kind === 'bar'
        ? Number(n.toFixed(2)).toLocaleString()
        : dashboardMetric(n, props.metric);
</script>
<template>
    <section
        class="dashboard-chart min-w-0"
        :aria-label="`${title}图表`"
        :aria-busy="loading"
    >
        <p
            data-typography="section-title"
            v-if="kind === 'bar'"
            class="px-3 pt-3 text-muted-foreground"
        >
            {{ title }}
        </p>
        <div
            ref="plot"
            class="relative"
            :style="{ height: `${height}px` }"
            @mousemove="hoverPlot"
            @mouseleave="hover = null"
        >
            <div
                v-if="loading || error || !points.length"
                class="absolute inset-0 z-10 flex items-center justify-center text-sm text-muted-foreground"
                :role="error ? 'alert' : 'status'"
            >
                <span v-if="loading">加载中…</span
                ><span v-else-if="error" class="px-4 text-destructive"
                    >{{ error }}
                    <Button
                        variant="link"
                        size="inline"
                        type="button"
                        data-slot="console-link"
                        class="underline"
                        @click="$emit('retry')"
                    >
                        重试
                    </Button></span
                ><span v-else>暂无数据</span>
            </div>
            <svg
                :viewBox="`0 0 ${width} ${height}`"
                class="block h-full w-full"
                role="img"
                :aria-label="`${title}，${points.length} 个数据点`"
            >
                <defs>
                    <linearGradient :id="gradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#69cdff" />
                        <stop offset="100%" stop-color="#1495eb" />
                    </linearGradient>
                </defs>
                <g v-for="tick in [0, 1, 2, 3, 4, 5]" :key="tick">
                    <line
                        :x1="left"
                        :x2="width - 16"
                        :y1="y((maximum * tick) / 5)"
                        :y2="y((maximum * tick) / 5)"
                        class="stroke-border"
                    />
                    <text
                        :x="left - 6"
                        :y="y((maximum * tick) / 5) + 4"
                        text-anchor="end"
                        class="fill-muted-foreground"
                        font-size="10"
                    >
                        {{ axis((maximum * tick) / 5) }}
                    </text>
                </g>
                <template v-if="!loading && !error && points.length">
                    <template v-if="kind === 'bar'">
                        <rect
                            v-for="(point, i) in points"
                            :key="point.label"
                            class="dashboard-bar"
                            :style="{
                                animationDelay: `${Math.min(i * 45, 240)}ms`,
                            }"
                            :x="x(i) - (plotWidth / points.length) * 0.34"
                            :y="y(point.value)"
                            :width="(plotWidth / points.length) * 0.68"
                            :height="bottom - y(point.value)"
                            rx="2"
                            :fill="`url(#${gradient})`"
                            tabindex="0"
                            :aria-label="`${point.label} ${dashboardMetric(point.value, metric)}`"
                            @focus="hover = i"
                            @blur="hover = null"
                        >
                            <title>
                                {{ point.label }}：{{
                                    dashboardMetric(point.value, metric)
                                }}
                            </title>
                        </rect>
                    </template>
                    <template v-else>
                        <polygon
                            :points="`${x(0)},${bottom} ${path} ${x(points.length - 1)},${bottom}`"
                            fill="var(--primary)"
                            opacity="0.16"
                        />
                        <polyline
                            :points="path"
                            fill="none"
                            stroke="var(--primary)"
                            stroke-width="2"
                        />
                        <circle
                            v-for="(point, i) in points"
                            :key="point.label"
                            :cx="x(i)"
                            :cy="y(point.value)"
                            r="2"
                            fill="var(--card)"
                            stroke="var(--primary)"
                            tabindex="0"
                            :aria-label="`${point.label} ${dashboardMetric(point.value, metric)}`"
                            @focus="hover = i"
                            @blur="hover = null"
                        >
                            <title>
                                {{ point.label }}：{{
                                    dashboardMetric(point.value, metric)
                                }}
                            </title>
                        </circle>
                    </template>
                    <text
                        v-for="i in labelIndexes"
                        :key="i"
                        :x="x(i)"
                        :y="height - 10"
                        :text-anchor="
                            kind === 'line' && i === 0
                                ? 'start'
                                : kind === 'line' && i === points.length - 1
                                  ? 'end'
                                  : 'middle'
                        "
                        class="fill-muted-foreground"
                        font-size="10"
                    >
                        {{ points[i].label.slice(5, kind === 'bar' ? 10 : 16) }}
                    </text>
                </template>
            </svg>
            <div
                v-if="hover !== null && points[hover] && !loading && !error"
                role="status"
                class="pointer-events-none absolute top-3 right-3 rounded border bg-popover px-3 py-2 text-xs text-popover-foreground shadow"
            >
                {{ points[hover].label }}<br />{{ title }}：{{
                    dashboardMetric(points[hover].value, metric)
                }}
            </div>
        </div>
    </section>
</template>

<style scoped>
.dashboard-bar {
    transform-box: fill-box;
    transform-origin: center bottom;
    animation: bar-grow 700ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes bar-grow {
    from {
        transform: scaleY(0);
    }
    to {
        transform: scaleY(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .dashboard-bar {
        animation: none;
    }
}
</style>
