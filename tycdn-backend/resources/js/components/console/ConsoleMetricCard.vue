<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ConsoleMetric } from '@/lib/consoleData';

defineProps<{
    metric: ConsoleMetric;
}>();
</script>

<template>
    <Card class="gap-2 py-3">
        <CardHeader class="gap-1 px-3">
            <CardTitle class="text-xs font-medium text-muted-foreground">
                {{ metric.label }}
            </CardTitle>
        </CardHeader>
        <CardContent class="px-3">
            <div class="text-xl font-semibold tracking-normal">
                {{ metric.value }}
            </div>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ metric.detail }}
            </p>
        </CardContent>
    </Card>
</template>
