<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

withDefaults(
    defineProps<{
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: '浅色' },
    { value: 'dark', Icon: Moon, label: '深色' },
    { value: 'system', Icon: Monitor, label: '跟随系统' },
] as const;
</script>

<template>
    <div
        class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            :aria-label="label"
            :aria-pressed="appearance === value"
            :title="label"
            @click="updateAppearance(value)"
            :class="[
                'flex items-center justify-center rounded-md transition-colors',
                compact ? 'size-8' : 'px-3.5 py-1.5',
                appearance === value
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <component
                :is="Icon"
                class="h-4 w-4"
                :class="{ '-ml-1': !compact }"
            />
            <span :class="compact ? 'sr-only' : 'ml-1.5 text-sm'">{{
                label
            }}</span>
        </button>
    </div>
</template>
