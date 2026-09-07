<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ConsoleMetric } from '@/lib/consoleData';

defineProps<{
    metric: ConsoleMetric;
}>();
</script>

<template>
    <Card class="gap-3 py-5">
        <CardHeader class="gap-1 px-5">
            <CardTitle class="text-sm font-medium text-muted-foreground">
                {{ metric.label }}
            </CardTitle>
        </CardHeader>
        <CardContent class="px-5">
            <div class="text-2xl font-semibold tracking-normal">
                {{ metric.value }}
            </div>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ metric.detail }}
            </p>
        </CardContent>
    </Card>
</template>
