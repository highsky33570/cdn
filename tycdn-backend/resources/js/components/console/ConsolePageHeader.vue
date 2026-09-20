<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, RefreshCw } from 'lucide-vue-next';
import type { Component } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

defineProps<{
    eyebrow?: string;
    title: string;
    description?: string;
    primaryAction?: string;
    secondaryAction?: string;
    primaryHref?: string;
    icon?: Component;
    showApiBadge?: boolean;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
    >
        <div class="flex min-w-0 flex-col gap-2">
            <div
                v-if="showApiBadge === true"
                class="flex flex-wrap items-center gap-2"
            >
                <Badge v-if="showApiBadge === true" variant="outline">
                    CDNfly API
                </Badge>
            </div>
            <div class="flex items-center gap-3">
                <div
                    v-if="icon"
                    class="flex size-7 shrink-0 items-center justify-center rounded-sm border border-primary/20 bg-primary/5 text-primary [&>svg]:size-3.5"
                >
                    <component :is="icon" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-base font-semibold tracking-normal">
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="mt-0.5 max-w-3xl text-xs text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap gap-2">
            <Button v-if="secondaryAction" variant="outline" size="sm">
                <RefreshCw data-icon="inline-start" />
                {{ secondaryAction }}
            </Button>
            <Button v-if="primaryAction && primaryHref" size="sm" as-child>
                <Link :href="primaryHref">
                    {{ primaryAction }}
                    <ArrowRight data-icon="inline-end" />
                </Link>
            </Button>
            <Button v-else-if="primaryAction" size="sm">
                {{ primaryAction }}
                <ArrowRight data-icon="inline-end" />
            </Button>
        </div>
    </div>
</template>
