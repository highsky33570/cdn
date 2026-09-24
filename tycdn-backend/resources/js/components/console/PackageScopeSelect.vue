<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuCheckboxItem,
} from '@/components/ui/dropdown-menu';
const model = defineModel<string[]>({ required: true });
const props = defineProps<{
    label: string;
    placeholder: string;
    options: { id: string | number; name: string }[];
    disabled?: boolean;
}>();
const choices = computed(() => [
    ...props.options,
    ...model.value
        .filter((id) => !props.options.some((item) => String(item.id) === id))
        .map((id) => ({ id, name: `#${id}` })),
]);
const summary = computed(() =>
    model.value
        .map(
            (id) =>
                choices.value.find((item) => String(item.id) === id)?.name ??
                id,
        )
        .join('、'),
);
function select(id: string, checked: boolean) {
    model.value = checked
        ? [...new Set([...model.value, id])]
        : model.value.filter((value) => value !== id);
}
</script>
<template>
    <DropdownMenu
        ><DropdownMenuTrigger as-child
            ><Button
                type="button"
                variant="outline"
                :aria-label="label"
                :disabled="disabled"
                class="w-full min-w-0 justify-between"
                ><span
                    class="truncate"
                    :class="{ 'text-muted-foreground': !model.length }"
                    >{{ summary || placeholder }}</span
                ><ChevronDown
                    class="size-4 shrink-0" /></Button></DropdownMenuTrigger
        ><DropdownMenuContent class="max-h-64 w-72 overflow-auto"
            ><DropdownMenuCheckboxItem
                v-for="item in choices"
                :key="String(item.id)"
                :model-value="model.includes(String(item.id))"
                @update:model-value="
                    (value) => select(String(item.id), !!value)
                "
                @select.prevent
                >{{ item.name }}</DropdownMenuCheckboxItem
            >
            <p v-if="!choices.length" class="p-3 text-sm text-muted-foreground">
                暂无选项
            </p></DropdownMenuContent
        ></DropdownMenu
    >
</template>
