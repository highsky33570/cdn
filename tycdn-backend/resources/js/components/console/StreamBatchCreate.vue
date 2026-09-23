<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { apiRequest } from '@/lib/apiRequest';
import { parseStreamBatch } from '@/lib/streamBatch';
import type { StreamBatchRow } from '@/lib/streamBatch';
const props = withDefaults(
    defineProps<{ scope?: 'admin' | 'user'; hideTrigger?: boolean }>(),
    {
        scope: 'user',
        hideTrigger: false,
    },
);
const emit = defineEmits<{ updated: [] }>();
const open = ref(false),
    source = ref(''),
    uid = ref(''),
    userPackage = ref(''),
    rows = ref<StreamBatchRow[]>([]),
    error = ref(''),
    busy = ref(false);
function preview() {
    error.value = '';

    try {
        if (
            !Number.isInteger(Number(userPackage.value)) ||
            Number(userPackage.value) < 1
        ) {
            throw new Error('请填写已购套餐 ID');
        }

        if (
            props.scope === 'admin' &&
            (!Number.isInteger(Number(uid.value)) || Number(uid.value) < 1)
        ) {
            throw new Error('请填写主控用户 ID');
        }

        rows.value = parseStreamBatch(source.value);
    } catch (e) {
        error.value = e instanceof Error ? e.message : '解析失败';
    }
}
async function submit() {
    busy.value = true;
    error.value = '';

    try {
        for (const row of rows.value) {
            if (row.status === 'done') {
                continue;
            }

            try {
                await apiRequest(
                    props.scope === 'admin'
                        ? '/api/admin/streams'
                        : '/api/cdn/proxy/v1/streams',
                    {
                        method: 'POST',
                        body: JSON.stringify({
                            ...(props.scope === 'admin'
                                ? { uid: Number(uid.value) }
                                : {}),
                            user_package: Number(userPackage.value),
                            listen: [
                                { protocol: row.protocol, port: row.port },
                            ],
                            backend: [
                                { addr: row.origin, weight: 1, state: 'up' },
                            ],
                            backend_port: row.originPort,
                        }),
                    },
                );
                row.status = 'done';
                row.error = '';
            } catch (e) {
                row.status = 'failed';
                row.error = e instanceof Error ? e.message : '创建失败';
                break;
            }
        }

        emit('updated');
    } finally {
        busy.value = false;
    }
}
function show() {
    source.value = '';
    rows.value = [];
    error.value = '';
    open.value = true;
}
defineExpose({ show });
</script>
<template>
    <Button
        v-if="!hideTrigger"
        type="button"
        variant="outline"
        size="sm"
        @click="show"
        >批量新增</Button
    ><Dialog
        :open="open"
        @update:open="
            (value) => {
                if (!busy) open = value;
            }
        "
        ><DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
            @interact-outside="
                (event) => {
                    if (busy) event.preventDefault();
                }
            "
            ><DialogHeader
                ><DialogTitle>批量新增转发</DialogTitle
                ><DialogDescription
                    >每行一条：协议 | 监听端口 | 源站 |
                    源站端口。先预览，再提交。</DialogDescription
                ></DialogHeader
            ><template v-if="!rows.length"
                ><label v-if="scope === 'admin'" class="grid gap-2 text-sm"
                    >主控用户 ID<Input v-model="uid" type="number" /></label
                ><label class="grid gap-2 text-sm"
                    >已购套餐 ID<Input
                        v-model="userPackage"
                        type="number" /></label
                ><textarea
                    v-model="source"
                    aria-label="批量转发记录"
                    class="min-h-48 rounded-lg border bg-background p-3 font-mono text-sm"
                /><Button @click="preview">检查并预览</Button></template
            ><template v-else
                ><p class="text-sm text-muted-foreground">
                    {{ rows.length }} 条记录 · 已购套餐 #{{ userPackage }}
                </p>
                <div class="max-h-80 overflow-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="p-3 text-left">监听</th>
                                <th class="p-3 text-left">源站</th>
                                <th class="p-3 text-left">状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in rows"
                                :key="index"
                                class="border-t"
                            >
                                <td class="p-3">
                                    {{ row.protocol }}:{{ row.port }}
                                </td>
                                <td class="p-3">
                                    {{ row.origin }}:{{ row.originPort }}
                                </td>
                                <td
                                    class="p-3"
                                    :class="row.error ? 'text-destructive' : ''"
                                >
                                    {{
                                        row.error ??
                                        {
                                            pending: '待提交',
                                            done: '已创建',
                                            failed: '失败',
                                        }[row.status]
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-if="rows.some((row) => row.status === 'failed')"
                    class="text-sm text-destructive"
                >
                    已在失败记录处停止。请先核对主控是否已创建该记录，避免重复提交。
                </p></template
            >
            <p v-if="error" role="alert" class="text-sm text-destructive">
                {{ error }}
            </p>
            <DialogFooter
                ><Button
                    variant="outline"
                    :disabled="busy"
                    @click="open = false"
                    >关闭</Button
                ><Button
                    v-if="
                        rows.length &&
                        !rows.some((row) => row.status === 'failed')
                    "
                    :disabled="
                        busy || rows.every((row) => row.status === 'done')
                    "
                    @click="submit"
                    >{{ busy ? '提交中…' : '创建以上记录' }}</Button
                ></DialogFooter
            ></DialogContent
        ></Dialog
    >
</template>
