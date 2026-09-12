<script setup lang="ts">
import type { Component } from 'vue';
import { Badge } from '@/components/ui/badge';

/**
 * The console's section switcher.
 *
 * Admin pages used to stack several independent tables down one screen, each
 * with its own search box, page-size select and 暂无记录 block. That is three
 * toolbars and three empty states competing for the same attention. One tab bar
 * shows one list at a time, so there is a single toolbar and a single empty
 * state on screen.
 *
 * Extracted after the same markup was hand-written in 节点管理, DNS 管理 and
 * 财务管理 — a fourth copy would have guaranteed they drifted apart.
 */
export interface ConsoleTab {
    key: string;
    label: string;
    icon?: Component;
    /**
     * Rendered as a badge. Omit, or pass 0, for tabs where a count means
     * nothing. `null` is allowed because a count that has not loaded yet is a
     * real state — and it renders the same as none rather than as "0".
     */
    count?: number | null;
}

defineProps<{
    tabs: ConsoleTab[];
    modelValue: string;
}>();

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <!--
        The actions slot holds a page-level action that belongs beside the tabs
        rather than inside one of them — 节点管理's 执行命令, for instance, which
        is about the page as a whole.
    -->
    <div
        class="flex flex-col gap-3 border-b pb-3 md:flex-row md:items-center md:justify-between"
    >
        <div class="flex flex-wrap gap-1">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    modelValue === tab.key
                        ? 'bg-primary/10 text-primary'
                        : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                "
                @click="$emit('update:modelValue', tab.key)"
            >
                <component :is="tab.icon" v-if="tab.icon" class="size-4" />
                {{ tab.label }}
                <Badge
                    v-if="tab.count"
                    variant="secondary"
                    class="ml-1 px-1.5 py-0 text-xs"
                >
                    {{ tab.count }}
                </Badge>
            </button>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
