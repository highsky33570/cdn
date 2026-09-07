<script setup lang="ts">
import { AlertCircle, Save } from 'lucide-vue-next';
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

const open = defineModel<boolean>('open', { required: true });

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        loading?: boolean;
        error?: string;
        saveLabel?: string;
        maxWidth?: string;
    }>(),
    {
        description: undefined,
        loading: false,
        error: undefined,
        saveLabel: '保存',
        maxWidth: 'sm:max-w-2xl',
    },
);

const emit = defineEmits<{
    save: [];
    cancel: [];
}>();

function handleCancel(): void {
    emit('cancel');
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogScrollContent :class="maxWidth">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="emit('save')">
                <Alert v-if="error" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>提交失败</AlertTitle>
                    <AlertDescription>{{ error }}</AlertDescription>
                </Alert>

                <slot />

                <DialogFooter>
                    <slot name="footer">
                        <Button
                            variant="outline"
                            type="button"
                            @click="handleCancel"
                        >
                            取消
                        </Button>
                        <Button :disabled="loading" type="submit">
                            <Spinner v-if="loading" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            {{ saveLabel }}
                        </Button>
                    </slot>
                </DialogFooter>
            </form>
        </DialogScrollContent>
    </Dialog>
</template>
