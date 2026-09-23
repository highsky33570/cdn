<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import { buildUrl } from '@/lib/urlHelpers';

const props = defineProps<{ ip: string | null }>();
defineEmits<{ close: [] }>();
const rows = ref<CdnflyRecord[]>([]),
    error = ref(''),
    loading = ref(false);
const page = ref(1),
    total = ref(0),
    start = ref(''),
    end = ref('');
let requestId = 0;
function localDate(date: Date) {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}
watch(
    () => props.ip,
    (ip) => {
        ++requestId;
        rows.value = [];
        error.value = '';
        total.value = 0;
        page.value = 1;

        if (!ip) {
            return;
        }

        end.value = localDate(new Date());
        start.value = localDate(new Date(Date.now() - 3600000));
        void load(1);
    },
);
async function load(target = 1) {
    if (!props.ip) {
        return;
    }

    const ticket = ++requestId;

    if (!start.value || !end.value || start.value >= end.value) {
        error.value = '结束时间必须晚于开始时间';
        loading.value = false;

        return;
    }

    error.value = '';
    loading.value = true;
    rows.value = [];

    try {
        const data = await apiRequest(
            buildUrl('/api/admin/workspace/node-ip-log', {
                ip: props.ip,
                type: 'aval',
                start: start.value.replace('T', ' '),
                end: end.value.replace('T', ' '),
                page: target,
                limit: 10,
            }),
        );

        if (ticket !== requestId) {
            return;
        }

        rows.value = extractCdnflyRows(data);
        total.value = extractCdnflyTotal(data, rows.value.length);
        page.value = target;
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
</script>
<template>
    <Dialog :open="ip !== null" @update:open="!$event && $emit('close')">
        <DialogScrollContent class="sm:max-w-3xl">
            <DialogHeader
                ><DialogTitle>IP 监控日志</DialogTitle
                ><DialogDescription
                    >{{ ip }} · 可用性检测</DialogDescription
                ></DialogHeader
            >
            <form class="flex flex-wrap gap-2" @submit.prevent="load(1)">
                <input
                    v-model="start"
                    type="datetime-local"
                    step="1"
                    aria-label="日志开始时间"
                    class="rounded border bg-background p-2 text-sm"
                />
                <input
                    v-model="end"
                    type="datetime-local"
                    step="1"
                    aria-label="日志结束时间"
                    class="rounded border bg-background p-2 text-sm"
                />
                <Button :disabled="loading" type="submit">查询</Button>
            </form>
            <p v-if="error" role="alert" class="text-sm text-destructive">
                {{ error }}
            </p>
            <table class="w-full text-left text-sm" aria-label="IP 监控日志">
                <thead class="bg-muted/25">
                    <tr>
                        <th class="p-3">检测时间</th>
                        <th>失败个数</th>
                        <th>总检测点</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in rows"
                        :key="index"
                        class="border-b"
                    >
                        <td class="p-3">{{ row.create_at }}</td>
                        <td>{{ row.failed }}</td>
                        <td>{{ row.total }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td
                            colspan="3"
                            class="p-6 text-center text-muted-foreground"
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
            <div class="flex items-center justify-end gap-3 text-sm">
                <span>共 {{ total }} 条</span
                ><Button
                    variant="outline"
                    :disabled="loading || page <= 1"
                    @click="load(page - 1)"
                    >上一页</Button
                ><span>{{ page }}</span
                ><Button
                    variant="outline"
                    :disabled="loading || page * 10 >= total"
                    @click="load(page + 1)"
                    >下一页</Button
                >
            </div>
        </DialogScrollContent>
    </Dialog>
</template>
