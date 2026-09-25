<script setup lang="ts">
import { CalendarDays } from 'lucide-vue-next';
import {
    PopoverRoot,
    PopoverTrigger,
    PopoverPortal,
    PopoverContent,
} from 'reka-ui';
import { computed, onUnmounted, ref, watch } from 'vue';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import DatePicker from '@/components/ui/date-picker/DatePicker.vue';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';

import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import { monitorGroups } from '@/lib/nodeMonitor';
import { nodeDate } from '@/lib/nodeRealtime';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import { buildUrl } from '@/lib/urlHelpers';

const props = defineProps<{ ip: string | null; nodeId: number | null }>();
defineEmits<{ close: [] }>();
const types = [
    { value: 'aval-log', label: '可用性监控日志' },
    { value: 'aval-switch', label: '可用性切换日志' },
    { value: 'bw-log', label: '带宽监控日志' },
    { value: 'bw-switch', label: '带宽切换日志' },
];
const logType = ref('aval-log'),
    group = ref('all'),
    rows = ref<CdnflyRecord[]>([]),
    loading = ref(false),
    error = ref('');
const page = ref(1),
    limit = ref('10'),
    total = ref(0),
    start = ref(''),
    end = ref('');
const rangeOpen = ref(false),
    draftStart = ref(''),
    draftEnd = ref(''),
    rangeError = ref('');
const isSwitch = computed(() => logType.value.endsWith('-switch'));
const pageCount = computed(() =>
    Math.max(1, Math.ceil(total.value / Number(limit.value))),
);

let requestId = 0;
onUnmounted(() => requestId++);
watch(
    () => [props.ip, props.nodeId],
    () => {
        requestId++;
        rows.value = [];
        error.value = '';
        total.value = 0;
        page.value = 1;
        loading.value = false;
        rangeOpen.value = false;

        if (!props.ip) {
            return;
        }

        logType.value = 'aval-log';
        group.value = 'all';
        limit.value = '10';
        end.value = nodeDate(new Date());
        start.value = nodeDate(new Date(Date.now() - 3600000));
        void load(1);
    },
    { immediate: true },
);
function changeType() {
    rangeOpen.value = false;
    void load(1);
}
function openRange(value: boolean) {
    rangeOpen.value = value;

    if (value) {
        draftStart.value = start.value.replace(' ', 'T');
        draftEnd.value = end.value.replace(' ', 'T');
        rangeError.value = '';
    }
}
function applyRange() {
    const from = new Date(draftStart.value),
        to = new Date(draftEnd.value);

    if (
        !Number.isFinite(from.getTime()) ||
        !Number.isFinite(to.getTime()) ||
        from >= to
    ) {
        rangeError.value = '结束时间必须晚于开始时间';

        return;
    }

    start.value = nodeDate(from);
    end.value = nodeDate(to);
    rangeOpen.value = false;
    void load(1);
}
async function load(target = 1) {
    if (!props.ip) {
        return;
    }

    const ticket = ++requestId;
    error.value = '';
    rows.value = [];
    total.value = 0;
    page.value = target;
    loading.value = true;

    if (isSwitch.value && !props.nodeId) {
        error.value = '节点 ID 缺失';
        loading.value = false;

        return;
    }

    const query: Record<string, string | number> = {
        page: target,
        limit: Number(limit.value),
    };

    if (isSwitch.value) {
        Object.assign(query, {
            node_id: props.nodeId!,
            type: logType.value === 'aval-switch' ? '节点IP解析' : '带宽监控',
        });
    } else {
        Object.assign(query, {
            ip: props.ip,
            type: logType.value === 'aval-log' ? 'aval' : 'bw',
            start: start.value,
            end: end.value,
            ...(group.value === 'all' ? {} : { group_id: group.value }),
        });
    }

    try {
        const data = await apiRequest(
            buildUrl(
                `/api/admin/workspace/${isSwitch.value ? 'ip-switch' : 'node-ip-log'}`,
                query,
            ),
        );

        if (ticket !== requestId) {
            return;
        }

        rows.value = extractCdnflyRows(data);
        total.value = extractCdnflyTotal(data, rows.value.length);
    } catch (e) {
        if (ticket === requestId) {
            error.value = e instanceof Error ? e.message : '日志加载失败';
        }
    } finally {
        if (ticket === requestId) {
            loading.value = false;
        }
    }
}
const time = (value: unknown) => String(value ?? '—').replace(/^\d{4}-/, '');
</script>
<template>
    <Dialog :open="ip !== null" @update:open="!$event && $emit('close')">
        <DialogScrollContent
            class="node-log-dialog w-[calc(100%-24px)] max-w-[414px] gap-0 bg-card p-0"
        >
            <DialogHeader class="border-b px-3 py-3 text-left"
                ><DialogTitle class="text-sm font-normal">监控日志</DialogTitle
                ><DialogDescription class="sr-only"
                    >{{ ip }} 的节点监控和切换日志</DialogDescription
                ></DialogHeader
            >
            <div class="px-4 pt-5 pb-2">
                <div class="log-filters mb-5">
                    <label for="node-log-type">日志查看</label>
                    <Select v-model="logType" @update:model-value="changeType"
                        ><SelectTrigger id="node-log-type" class="log-select"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent
                            ><SelectItem
                                v-for="item in types"
                                :key="item.value"
                                :value="item.value"
                                class="text-xs"
                                >{{ item.label }}</SelectItem
                            ></SelectContent
                        ></Select
                    >
                    <template v-if="!isSwitch">
                        <label for="node-log-range">时间段</label>
                        <PopoverRoot :open="rangeOpen" @update:open="openRange"
                            ><PopoverTrigger
                                id="node-log-range"
                                class="range-trigger"
                                aria-label="日志时间段"
                                ><span>{{ start }} - {{ end }}</span
                                ><CalendarDays
                                    class="size-3 shrink-0 text-muted-foreground" /></PopoverTrigger
                            ><PopoverPortal
                                ><PopoverContent
                                    side="bottom"
                                    align="end"
                                    :side-offset="5"
                                    :collision-padding="12"
                                    class="z-[60] w-[330px] max-w-[calc(100vw-24px)] rounded border bg-popover p-3 text-xs text-popover-foreground shadow-lg"
                                    ><form
                                        class="grid gap-3"
                                        @submit.prevent="applyRange"
                                    >
                                        <label class="grid gap-1"
                                            >开始时间<DatePicker
                                                v-model="draftStart"
                                                type="datetime-local"
                                                step="1"
                                                aria-label="日志开始时间"
                                                class="w-full rounded border bg-card p-2"
                                                required /></label
                                        ><label class="grid gap-1"
                                            >结束时间<DatePicker
                                                v-model="draftEnd"
                                                type="datetime-local"
                                                step="1"
                                                aria-label="日志结束时间"
                                                class="w-full rounded border bg-card p-2"
                                                required
                                        /></label>
                                        <p
                                            v-if="rangeError"
                                            role="alert"
                                            class="text-destructive"
                                        >
                                            {{ rangeError }}
                                        </p>
                                        <div class="flex justify-end gap-2">
                                            <button
                                                data-slot="console-segment"
                                                type="button"
                                                class="rounded border px-3 py-1"
                                                @click="rangeOpen = false"
                                            >
                                                取消</button
                                            ><button
                                                data-slot="console-action"
                                                type="submit"
                                                class="rounded bg-[#2d8cf0] px-3 py-1 text-white"
                                            >
                                                确定
                                            </button>
                                        </div>
                                    </form></PopoverContent
                                ></PopoverPortal
                            ></PopoverRoot
                        >
                        <label for="node-log-group">监控组</label>
                        <Select v-model="group" @update:model-value="load(1)"
                            ><SelectTrigger
                                id="node-log-group"
                                class="log-select"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem value="all" class="text-xs"
                                    >所有监控组</SelectItem
                                ><SelectItem
                                    v-for="item in monitorGroups"
                                    :key="item.value"
                                    :value="item.value"
                                    class="text-xs"
                                    >{{ item.label }}</SelectItem
                                ></SelectContent
                            ></Select
                        >
                    </template>
                </div>
                <p
                    v-if="error"
                    role="alert"
                    class="mb-3 text-xs text-destructive"
                >
                    {{ error }}
                    <button class="underline" @click="load(page)">重试</button>
                </p>
                <table
                    class="w-full table-fixed text-left text-xs"
                    aria-label="监控日志"
                    :aria-busy="loading"
                    tabindex="0"
                    title="点击刷新"
                    @click="!loading && load(page)"
                    @keydown.enter.prevent="!loading && load(page)"
                >
                    <thead class="bg-muted/20">
                        <tr>
                            <th>{{ isSwitch ? '切换时间' : '检测时间' }}</th>
                            <th>{{ isSwitch ? '动作' : '失败个数' }}</th>
                            <th v-if="!isSwitch">总检测点</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="index">
                            <td>{{ time(row.create_at) }}</td>
                            <template v-if="!isSwitch"
                                ><td>{{ row.failed ?? '—' }}</td>
                                <td>{{ row.total ?? '—' }}</td></template
                            >
                            <td v-else>{{ row.action ?? '—' }}</td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td
                                :colspan="isSwitch ? 2 : 3"
                                class="py-6! text-center text-muted-foreground"
                            >
                                {{
                                    loading
                                        ? '加载中…'
                                        : error
                                          ? '数据加载失败'
                                          : '暂无数据'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <ConsolePagination
                    aria-label="监控日志分页"
                    :total="total"
                    :page="page"
                    :previous-disabled="loading || page <= 1"
                    :next-disabled="loading || page >= pageCount"
                    @previous="load(page - 1)"
                    @next="load(page + 1)"
                />
            </div>
        </DialogScrollContent>
    </Dialog>
</template>
<style scoped>
.log-filters {
    display: grid;
    grid-template-columns: 58px minmax(0, 1fr);
    gap: 21px 9px;
    align-items: center;
    font-size: 12px;
}
.log-filters label {
    text-align: right;
}
:deep(.log-select) {
    height: 28px;
    min-height: 28px;
    width: 100%;
    border-radius: 3px;
    padding: 3px 7px;
    font-size: 12px;
    box-shadow: none;
}
.range-trigger {
    display: flex;
    align-items: center;
    gap: 6px;
    height: 28px;
    padding: 3px 6px;
    border: 1px solid var(--border);
    border-radius: 3px;
    font-size: 11px;
    text-align: left;
}
.range-trigger span {
    flex: 1;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
th {
    padding: 8px;
    font-weight: 600;
}
td {
    padding: 9px 8px;
    font-size: 11px;
}
th,
td {
    border-bottom: 1px solid var(--border);
}
.page-button {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 27px;
    height: 28px;
    border: 1px solid var(--border);
    border-radius: 3px;
}
.page-button[aria-current='page'] {
    color: #2d8cf0;
    border-color: #2d8cf0;
}
.page-button:disabled {
    opacity: 0.45;
}
</style>
