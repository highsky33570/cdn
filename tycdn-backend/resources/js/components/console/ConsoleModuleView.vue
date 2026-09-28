<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from 'lucide-vue-next';
import { computed } from 'vue';
import ConsoleMetricCard from '@/components/console/ConsoleMetricCard.vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { consoleModules, fallbackModule } from '@/lib/consoleData';
import type { ConsoleModule } from '@/lib/consoleData';

const props = defineProps<{
    moduleKey: string;
}>();

const module = computed<ConsoleModule>(
    () => consoleModules[props.moduleKey] ?? fallbackModule,
);
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            :eyebrow="module.eyebrow"
            :title="module.title"
            :description="module.description"
            :primary-action="module.primaryAction"
            :secondary-action="module.secondaryAction"
        />

        <div v-if="module.metrics.length > 0" class="grid gap-4 md:grid-cols-3">
            <ConsoleMetricCard
                v-for="metric in module.metrics"
                :key="metric.label"
                :metric="metric"
            />
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <Card
                v-for="section in module.sections"
                :key="section.title"
                class="gap-4"
            >
                <CardHeader class="gap-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <CardTitle class="text-base">
                                {{ section.title }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{ section.description }}
                            </CardDescription>
                        </div>
                        <Badge variant="outline" class="shrink-0"> API </Badge>
                    </div>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <code
                        class="rounded-md border bg-muted px-3 py-2 text-xs text-muted-foreground"
                    >
                        {{ section.endpoint }}
                    </code>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="item in section.items"
                            :key="item"
                            variant="secondary"
                        >
                            {{ item }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card class="gap-0 overflow-hidden">
            <CardHeader class="gap-2">
                <div
                    class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <CardTitle>当前记录</CardTitle>
                        <CardDescription>
                            页面先按 CDNfly 接口字段组织，后续直接接入接口数据。
                        </CardDescription>
                    </div>
                    <Button variant="outline" size="sm">批量操作</Button>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead
                            class="border-y bg-muted/50 text-muted-foreground"
                        >
                            <tr>
                                <th class="text-left font-medium">
                                    <div data-slot="table-cell-content">
                                        名称
                                    </div>
                                </th>
                                <th class="text-left font-medium">
                                    <div data-slot="table-cell-content">
                                        类型
                                    </div>
                                </th>
                                <th class="text-left font-medium">
                                    <div data-slot="table-cell-content">
                                        状态
                                    </div>
                                </th>
                                <th class="text-left font-medium">
                                    <div data-slot="table-cell-content">
                                        指标
                                    </div>
                                </th>
                                <th class="text-left font-medium">
                                    <div data-slot="table-cell-content">
                                        接口
                                    </div>
                                </th>
                                <th class="text-right font-medium">
                                    <div data-slot="table-cell-content">
                                        操作
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in module.rows"
                                :key="row.name"
                                class="border-b last:border-b-0"
                            >
                                <td class="font-medium">
                                    <div data-slot="table-cell-content">
                                        {{ row.name }}
                                    </div>
                                </td>
                                <td class="text-muted-foreground">
                                    <div data-slot="table-cell-content">
                                        {{ row.type }}
                                    </div>
                                </td>
                                <td class="">
                                    <div data-slot="table-cell-content">
                                        <Badge variant="secondary">
                                            {{ row.status }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="text-muted-foreground">
                                    <div data-slot="table-cell-content">
                                        {{ row.metric }}
                                    </div>
                                </td>
                                <td class="">
                                    <div data-slot="table-cell-content">
                                        <code
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ row.endpoint }}
                                        </code>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div data-slot="table-cell-content">
                                        <Button
                                            v-if="props.moduleKey === 'sites'"
                                            variant="ghost"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                href="/console/sites/www.example.com"
                                            >
                                                详情
                                                <ArrowUpRight
                                                    data-icon="inline-end"
                                                />
                                            </Link>
                                        </Button>
                                        <Button
                                            v-else
                                            variant="ghost"
                                            size="sm"
                                        >
                                            查看
                                            <ArrowUpRight
                                                data-icon="inline-end"
                                            />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="module.rows.length === 0">
                                <td
                                    class="text-center text-muted-foreground"
                                    colspan="6"
                                >
                                    <div data-slot="table-cell-content">
                                        暂无记录
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
