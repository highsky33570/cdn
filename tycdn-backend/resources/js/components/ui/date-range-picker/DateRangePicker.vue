<script setup lang="ts">
import { CalendarDays } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

// ── props / emits ────────────────────────────────────
const props = withDefaults(defineProps<{
    start: string;
    end: string;
    placeholder?: string;
    triggerClass?: string;
}>(), {
    placeholder: '选择日期范围',
});

const emit = defineEmits<{
    'update:start': [value: string];
    'update:end': [value: string];
}>();

// ── 内部日期状态 ──────────────────────────────────────
function parseDate(s: string): Date | null {
    if (!s) return null;
    const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return null;
    return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
}

const startDate = ref<Date | null>(parseDate(props.start));
const endDate   = ref<Date | null>(parseDate(props.end));
const hoverDate = ref<Date | null>(null);

// ── 日历导航 ──────────────────────────────────────────
const today = new Date();

function initMonths() {
    let lm = today.getMonth() - 1;
    let ly = today.getFullYear();
    if (lm < 0) { lm = 11; ly--; }
    return { lm, ly, rm: today.getMonth(), ry: today.getFullYear() };
}
const init = initMonths();
const leftYear   = ref(init.ly);
const leftMonth  = ref(init.lm);
const rightYear  = ref(init.ry);
const rightMonth = ref(init.rm);

// ── 格式化 ────────────────────────────────────────────
function pad(n: number): string { return String(n).padStart(2, '0'); }
function fmtDate(d: Date | null): string {
    if (!d) return '';
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}
function fmtFull(d: Date | null): string {
    return d ? `${fmtDate(d)} 00:00:00` : '';
}

const triggerLabel = computed(() => {
    if (startDate.value && endDate.value) {
        return `${fmtDate(startDate.value)}  —  ${fmtDate(endDate.value)}`;
    }
    return props.placeholder;
});

// ── 日期工具 ──────────────────────────────────────────
function sameDay(a: Date | null, b: Date | null): boolean {
    return !!a && !!b &&
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate();
}
function inRange(d: Date, s: Date | null, e: Date | null): boolean {
    if (!s || !e) return false;
    const t = d.getTime();
    return t > Math.min(s.getTime(), e.getTime()) &&
           t < Math.max(s.getTime(), e.getTime());
}

// ── 日历格子 ──────────────────────────────────────────
const WEEK_LABELS = ['日', '一', '二', '三', '四', '五', '六'];
const MONTH_NAMES = ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'];

type Cell = {
    day: number;
    date: Date;
    empty: boolean;
    isToday: boolean;
    isStart: boolean;
    isEnd: boolean;
    inRange: boolean;
};

function buildMonth(year: number, month: number): Cell[] {
    const cells: Cell[] = [];
    const first = new Date(year, month, 1);
    const last  = new Date(year, month + 1, 0);
    const effEnd = hoverDate.value && startDate.value && !endDate.value
        ? hoverDate.value : endDate.value;

    for (let i = 0; i < first.getDay(); i++) {
        cells.push({ day: 0, date: new Date(0), empty: true, isToday: false, isStart: false, isEnd: false, inRange: false });
    }
    for (let d = 1; d <= last.getDate(); d++) {
        const cur = new Date(year, month, d);
        cells.push({
            day: d,
            date: cur,
            empty: false,
            isToday: sameDay(cur, today),
            isStart: sameDay(cur, startDate.value),
            isEnd: sameDay(cur, endDate.value) || (!endDate.value && sameDay(cur, hoverDate.value)),
            inRange: inRange(cur, startDate.value, effEnd),
        });
    }
    return cells;
}

const leftCells  = computed(() => buildMonth(leftYear.value,  leftMonth.value));
const rightCells = computed(() => buildMonth(rightYear.value, rightMonth.value));

// ── 交互 ─────────────────────────────────────────────
function selectDay(cell: Cell): void {
    if (cell.empty) return;
    const clicked = cell.date;
    if (!startDate.value || (startDate.value && endDate.value)) {
        startDate.value = clicked;
        endDate.value   = null;
        hoverDate.value = null;
    } else {
        if (clicked < startDate.value) {
            endDate.value   = startDate.value;
            startDate.value = clicked;
        } else {
            endDate.value = clicked;
        }
        hoverDate.value = null;
    }
}

function onHover(cell: Cell): void {
    if (!cell.empty && startDate.value && !endDate.value) {
        hoverDate.value = cell.date;
    }
}

function navMonth(dir: 1 | -1): void {
    let lm = leftMonth.value + dir;
    let ly = leftYear.value;
    let rm = rightMonth.value + dir;
    let ry = rightYear.value;
    if (lm < 0)  { lm = 11; ly--; }
    if (lm > 11) { lm = 0;  ly++; }
    if (rm < 0)  { rm = 11; ry--; }
    if (rm > 11) { rm = 0;  ry++; }
    leftMonth.value = lm; leftYear.value = ly;
    rightMonth.value = rm; rightYear.value = ry;
}

function setPreset(preset: 'today' | 'yesterday' | '7d' | '30d'): void {
    const t = new Date(); t.setHours(0, 0, 0, 0);
    if (preset === 'today') {
        startDate.value = new Date(t); endDate.value = new Date(t);
    } else if (preset === 'yesterday') {
        const y = new Date(t); y.setDate(y.getDate() - 1);
        startDate.value = y; endDate.value = new Date(y);
    } else if (preset === '7d') {
        const s = new Date(t); s.setDate(s.getDate() - 6);
        startDate.value = s; endDate.value = new Date(t);
    } else if (preset === '30d') {
        const s = new Date(t); s.setDate(s.getDate() - 29);
        startDate.value = s; endDate.value = new Date(t);
    }
}

const open = ref(false);

function confirm(): void {
    if (!startDate.value || !endDate.value) return;
    emit('update:start', fmtFull(startDate.value));
    emit('update:end',   fmtFull(endDate.value));
    open.value = false;
}

function clear(): void {
    startDate.value = null;
    endDate.value   = null;
    hoverDate.value = null;
    emit('update:start', '');
    emit('update:end',   '');
}

function cellClass(cell: Cell): string {
    const base = 'flex h-7 w-7 mx-auto items-center justify-center rounded-md text-xs select-none transition-colors';
    if (cell.empty) return `${base} invisible`;
    if (cell.isStart || cell.isEnd) return `${base} cursor-pointer bg-foreground text-background`;
    if (cell.inRange) return `${base} cursor-pointer bg-accent text-accent-foreground`;
    if (cell.isToday) return `${base} cursor-pointer font-medium hover:bg-accent`;
    return `${base} cursor-pointer hover:bg-accent`;
}
</script>

<template>
    <DropdownMenu v-model:open="open">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                :class="[
                    'inline-flex h-8 items-center gap-2 rounded-md border px-3 text-xs transition-colors',
                    'border-input bg-background hover:bg-accent',
                    triggerClass,
                ]"
            >
                <CalendarDays class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                <span :class="startDate && endDate ? 'text-foreground' : 'text-muted-foreground'">
                    {{ triggerLabel }}
                </span>
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            class="p-0 w-auto"
            :side-offset="6"
            align="start"
        >
            <div @mouseleave="hoverDate = null">
                <!-- 快捷按钮 -->
                <div class="flex gap-1.5 border-b px-3 py-2">
                    <button
                        v-for="p in [
                            { key: 'today',     label: '今天' },
                            { key: 'yesterday', label: '昨天' },
                            { key: '7d',        label: '近7天' },
                            { key: '30d',       label: '近30天' },
                        ]"
                        :key="p.key"
                        type="button"
                        class="rounded-full border px-3 py-0.5 text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                        @click="setPreset(p.key as 'today' | 'yesterday' | '7d' | '30d')"
                    >
                        {{ p.label }}
                    </button>
                </div>

                <!-- 双月日历 -->
                <div class="flex px-3 py-3 gap-0">
                    <!-- 左月 -->
                    <div class="w-52">
                        <div class="mb-2 flex items-center justify-between">
                            <button type="button" class="flex h-6 w-6 items-center justify-center rounded hover:bg-accent text-muted-foreground" @click="navMonth(-1)">‹</button>
                            <span class="text-xs font-medium">{{ leftYear }}年 {{ MONTH_NAMES[leftMonth] }}</span>
                            <span class="w-6" />
                        </div>
                        <div class="mb-1 grid grid-cols-7">
                            <div v-for="w in WEEK_LABELS" :key="w" class="text-center text-[10px] text-muted-foreground py-0.5">{{ w }}</div>
                        </div>
                        <div class="grid grid-cols-7">
                            <div
                                v-for="(cell, i) in leftCells"
                                :key="i"
                                :class="cellClass(cell)"
                                @click="selectDay(cell)"
                                @mouseenter="onHover(cell)"
                            >
                                {{ cell.empty ? '' : cell.day }}
                            </div>
                        </div>
                    </div>

                    <!-- 分隔线 -->
                    <div class="mx-3 w-px bg-border self-stretch" />

                    <!-- 右月 -->
                    <div class="w-52">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="w-6" />
                            <span class="text-xs font-medium">{{ rightYear }}年 {{ MONTH_NAMES[rightMonth] }}</span>
                            <button type="button" class="flex h-6 w-6 items-center justify-center rounded hover:bg-accent text-muted-foreground" @click="navMonth(1)">›</button>
                        </div>
                        <div class="mb-1 grid grid-cols-7">
                            <div v-for="w in WEEK_LABELS" :key="w" class="text-center text-[10px] text-muted-foreground py-0.5">{{ w }}</div>
                        </div>
                        <div class="grid grid-cols-7">
                            <div
                                v-for="(cell, i) in rightCells"
                                :key="i"
                                :class="cellClass(cell)"
                                @click="selectDay(cell)"
                                @mouseenter="onHover(cell)"
                            >
                                {{ cell.empty ? '' : cell.day }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 底部 -->
                <div class="flex items-center justify-between border-t px-3 py-2">
                    <span class="text-xs text-muted-foreground">
                        <template v-if="startDate && endDate">
                            {{ fmtDate(startDate) }} → {{ fmtDate(endDate) }}
                        </template>
                        <template v-else-if="startDate">
                            {{ fmtDate(startDate) }} → 请选择结束日期
                        </template>
                        <template v-else>请选择开始和结束日期</template>
                    </span>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-md border px-3 py-1 text-xs hover:bg-accent transition-colors" @click="clear">清除</button>
                        <button
                            type="button"
                            class="rounded-md bg-foreground px-3 py-1 text-xs text-background transition-opacity"
                            :class="startDate && endDate ? 'opacity-100 cursor-pointer' : 'opacity-40 cursor-not-allowed'"
                            @click="confirm"
                        >
                            确认
                        </button>
                    </div>
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
