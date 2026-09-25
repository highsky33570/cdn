<script setup lang="ts">
import { fieldLabels, secretField } from '@/lib/configEditor';
defineProps<{ value: unknown; labels?: Record<string, string> }>();
</script>
<template>
    <dl v-if="value && typeof value === 'object'" class="grid gap-3">
        <div
            v-for="(item, key) in value"
            :key="String(key)"
            class="grid min-w-0 gap-1 border-b border-border/60 pb-3 last:border-0 last:pb-0"
        >
            <dt class="text-xs text-muted-foreground">
                {{
                    labels?.[String(key)] ??
                    fieldLabels[String(key)] ??
                    String(key).replaceAll('_', ' ')
                }}
            </dt>
            <dd class="min-w-0 text-sm break-words">
                <span v-if="secretField(String(key))">已配置</span
                ><RecordDetails
                    v-else-if="item && typeof item === 'object'"
                    :value="item"
                    :labels="labels"
                    class="mt-2 border-l pl-4"
                /><span v-else class="whitespace-pre-wrap">{{
                    item ?? '—'
                }}</span>
            </dd>
        </div>
    </dl>
    <p data-typography="body" v-else class="break-words whitespace-pre-wrap">
        {{ value ?? '暂无数据' }}
    </p>
</template>
