<script setup lang="ts">
import type { Component } from 'vue';

/**
 * The row of headline numbers a page opens with.
 *
 * Admin pages went straight to a table, which answers "what rows exist" but
 * never "is anything wrong". These cards carry the figures someone actually
 * arrives wanting, so the table becomes the detail rather than the headline.
 *
 * `tone: 'warning'` is for a number that demands action today — money taken but
 * not delivered, a node offline. It is deliberately the only visual emphasis
 * available: if everything can shout, nothing does.
 */
export interface ConsoleStat {
    key: string;
    label: string;
    value: string | number;
    hint?: string;
    icon?: Component;
    tone?: 'default' | 'warning';
}

withDefaults(
    defineProps<{
        stats: ConsoleStat[];
        /** Columns at the widest breakpoint. */
        columns?: 3 | 4;
    }>(),
    { columns: 4 },
);
</script>

<template>
    <div
        class="grid gap-3 sm:grid-cols-2"
        :class="columns === 3 ? 'xl:grid-cols-3' : 'xl:grid-cols-4'"
    >
        <div
            v-for="stat in stats"
            :key="stat.key"
            class="rounded-xl border p-4"
            :class="
                stat.tone === 'warning'
                    ? 'border-destructive/40 bg-destructive/5'
                    : 'bg-card'
            "
        >
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs text-muted-foreground">
                    {{ stat.label }}
                </span>
                <component
                    :is="stat.icon"
                    v-if="stat.icon"
                    class="size-4"
                    :class="
                        stat.tone === 'warning'
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                    "
                />
            </div>
            <div
                class="mt-2 text-2xl font-semibold"
                :class="stat.tone === 'warning' ? 'text-destructive' : ''"
            >
                {{ stat.value }}
            </div>
            <div v-if="stat.hint" class="mt-1 text-xs text-muted-foreground">
                {{ stat.hint }}
            </div>
        </div>
    </div>
</template>
