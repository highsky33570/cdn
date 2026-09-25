<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { apiRequest } from '@/lib/apiRequest';
const props = withDefaults(
    defineProps<{ ids: (string | number)[]; scope?: 'admin' | 'user' }>(),
    { scope: 'user' },
);
const emit = defineEmits<{ updated: [] }>();
const busy = ref(false),
    error = ref('');
async function change(enable: number) {
    if (
        !props.ids.length ||
        !window.confirm(
            `确认${enable ? '启用' : '停用'}选中的 ${props.ids.length} 条转发？`,
        )
    ) {
        return;
    }

    busy.value = true;
    error.value = '';
    let count = 0;

    try {
        for (const id of props.ids) {
            await apiRequest(
                props.scope === 'admin'
                    ? `/api/admin/streams/${id}/enable`
                    : `/api/cdn/proxy/v1/streams/${id}`,
                { method: 'PUT', body: JSON.stringify({ enable }) },
            );
            count++;
        }
    } catch (e) {
        error.value = `已完成 ${count} 条，${e instanceof Error ? e.message : '请求失败'}`;
    } finally {
        busy.value = false;
        emit('updated');
    }
}
</script>
<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button
            type="button"
            size="sm"
            variant="outline"
            :disabled="busy || !ids.length"
            @click="change(1)"
            >批量启用</Button
        ><Button
            type="button"
            size="sm"
            variant="outline"
            :disabled="busy || !ids.length"
            @click="change(0)"
            >批量停用</Button
        ><span v-if="ids.length" class="text-xs text-muted-foreground"
            >已选 {{ ids.length }} 条</span
        >
        <p
            data-typography="helper"
            v-if="error"
            role="alert"
            class="w-full text-destructive"
        >
            {{ error }}
        </p>
    </div>
</template>
