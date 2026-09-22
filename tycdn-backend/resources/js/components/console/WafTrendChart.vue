<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import type { WafTrendPoint } from '@/lib/wafLogs';
const props = defineProps<{ points: WafTrendPoint[] }>();
const plot = ref<HTMLElement | null>(null);
const width = ref(1200);
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
const series = [
    { key: 'total', label: '攻击总数', color: 'var(--waf-total)' },
    { key: 'protect', label: '拦截', color: 'var(--waf-protect)' },
    { key: 'observe', label: '观察', color: 'var(--waf-observe)' },
] as const;
const hidden = ref<string[]>([]),
    hover = ref<number | null>(null);
const visible = computed(() =>
    series.filter((s) => !hidden.value.includes(s.key)),
);
const maximum = computed(() =>
    Math.max(
        1,
        ...props.points.flatMap((p) => visible.value.map((s) => p[s.key])),
    ),
);
const empty = computed(
    () => !props.points.some((p) => p.total || p.protect || p.observe),
);
const plotWidth = computed(() => width.value - 70);
const x = (i: number) =>
    45 + (i / Math.max(1, props.points.length - 1)) * plotWidth.value;
const y = (value: number) => 200 - (value / maximum.value) * 150;
const line = (key: 'total' | 'protect' | 'observe') =>
    props.points.map((p, i) => `${x(i)},${y(p[key])}`).join(' ');
const ticks = computed(() => {
    const count = Math.min(
        props.points.length,
        Math.max(2, Math.min(7, Math.floor(width.value / 150))),
    );

    return [
        ...new Set(
            Array.from({ length: count }, (_, i) =>
                Math.round(
                    (i / Math.max(1, count - 1)) * (props.points.length - 1),
                ),
            ),
        ),
    ];
});
const levels = computed(() =>
    maximum.value <= 1
        ? [0, 1]
        : [0, Math.ceil(maximum.value / 2), maximum.value],
);
const selected = computed(() =>
    hover.value === null ? null : props.points[hover.value],
);
function toggle(key: string): void {
    hidden.value = hidden.value.includes(key)
        ? hidden.value.filter((k) => k !== key)
        : [...hidden.value, key];
}
</script>
<template>
    <div class="waf-trend relative rounded-md border p-3" aria-label="攻击趋势">
        <div class="flex justify-center gap-4 text-xs text-muted-foreground">
            <button
                v-for="s in series"
                :key="s.key"
                type="button"
                :aria-pressed="!hidden.includes(s.key)"
                :class="{ 'opacity-40': hidden.includes(s.key) }"
                class="flex items-center gap-1"
                @click="toggle(s.key)"
            >
                <span
                    class="inline-block h-0.5 w-4"
                    :style="{ background: s.color }"
                />{{ s.label }}
            </button>
        </div>
        <div ref="plot">
            <svg
                :viewBox="`0 0 ${width} 240`"
                class="h-60 w-full"
                preserveAspectRatio="none"
                role="img"
                aria-label="攻击总数、拦截和观察的时间趋势"
                @mouseleave="hover = null"
            >
                <g v-for="level in levels" :key="level">
                    <line
                        x1="45"
                        :x2="width - 25"
                        :y1="y(level)"
                        :y2="y(level)"
                        stroke="var(--border)"
                    />
                    <text
                        x="35"
                        :y="y(level) + 4"
                        text-anchor="end"
                        fill="var(--muted-foreground)"
                        font-size="10"
                    >
                        {{ level }}
                    </text>
                </g>
                <g v-for="s in visible" :key="s.key">
                    <polyline
                        :points="line(s.key)"
                        fill="none"
                        :stroke="s.color"
                        stroke-width="2"
                        vector-effect="non-scaling-stroke"
                    />
                    <circle
                        v-for="(point, index) in points"
                        :key="index"
                        :cx="x(index)"
                        :cy="y(point[s.key])"
                        r="2"
                        fill="var(--card)"
                        :stroke="s.color"
                    />
                </g>
                <text
                    v-for="index in ticks"
                    :key="index"
                    :x="x(index)"
                    y="227"
                    :text-anchor="
                        index === 0
                            ? 'start'
                            : index === points.length - 1
                              ? 'end'
                              : 'middle'
                    "
                    fill="var(--muted-foreground)"
                    font-size="10"
                >
                    {{ points[index].time.slice(5, 16) }}
                </text>
                <line
                    v-if="hover !== null"
                    :x1="x(hover)"
                    :x2="x(hover)"
                    y1="45"
                    y2="200"
                    stroke="var(--muted-foreground)"
                    stroke-dasharray="3 3"
                />
                <rect
                    v-for="(point, index) in points"
                    :key="index"
                    :x="
                        x(index) -
                        plotWidth / 2 / Math.max(1, points.length - 1)
                    "
                    y="40"
                    :width="plotWidth / Math.max(1, points.length - 1)"
                    height="165"
                    fill="transparent"
                    @mouseenter="hover = index"
                    @touchstart.passive="hover = index"
                >
                    <title>
                        {{ point.time }} · 攻击总数 {{ point.total }} · 拦截
                        {{ point.protect }} · 观察 {{ point.observe }}
                    </title>
                </rect>
            </svg>
        </div>
        <p
            v-if="empty"
            class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-muted-foreground"
        >
            暂无攻击趋势数据
        </p>
        <div
            v-if="selected"
            role="status"
            class="pointer-events-none absolute top-12 right-5 rounded border bg-popover p-3 text-xs text-popover-foreground shadow-md"
        >
            <p class="mb-2">{{ selected.time }}</p>
            <p
                v-for="s in visible"
                :key="s.key"
                class="flex justify-between gap-6"
            >
                <span :style="{ color: s.color }">{{ s.label }}</span
                >{{ selected[s.key].toLocaleString() }}
            </p>
        </div>
    </div>
</template>
<style scoped>
.waf-trend {
    --waf-total: #2d8cf0;
    --waf-protect: #ed4014;
    --waf-observe: #ff9900;
}
:global(.dark .waf-trend) {
    --waf-total: #60a5fa;
    --waf-protect: #f87171;
    --waf-observe: #fbbf24;
}
</style>
