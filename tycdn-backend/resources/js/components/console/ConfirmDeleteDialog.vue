<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    open: boolean;
    title?: string;
    description: string;
    confirmText?: string;
    loading?: boolean;
    error?: string;
}>();

defineEmits<{
    confirm: [];
    cancel: [];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="$emit('cancel')">
        <DialogScrollContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title ?? '确认删除' }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <Alert v-if="error" variant="destructive">
                <AlertTitle>操作失败</AlertTitle>
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <DialogFooter>
                <Button variant="outline" @click="$emit('cancel')">取消</Button>
                <Button variant="destructive" :disabled="loading" @click="$emit('confirm')">
                    <Spinner v-if="loading" data-icon="inline-start" />
                    <Trash2 v-else data-icon="inline-start" />
                    {{ confirmText ?? '确认删除' }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
