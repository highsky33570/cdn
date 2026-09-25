<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows } from '@/lib/cdnflyResponse';
import type { CdnflyRecord } from '@/lib/sharedTypes';
const props = withDefaults(
    defineProps<{
        modelValue: string;
        display?: string;
        disabled?: boolean;
        inputId?: string;
    }>(),
    { display: '', disabled: false },
);
const emit = defineEmits<{
    'update:modelValue': [value: string];
    select: [user: CdnflyRecord | null];
}>();
const text = ref(props.display),
    focused = ref(false),
    loading = ref(false),
    error = ref(''),
    options = ref<CdnflyRecord[]>([]),
    active = ref(-1);
let request = 0,
    timer: ReturnType<typeof setTimeout> | undefined;
watch(
    () => props.display,
    (value) => {
        if (value || !focused.value) {
            text.value = value;
        }
    },
);
watch(
    () => props.modelValue,
    (value) => {
        if (!value && !focused.value) {
            text.value = '';
        }
    },
);
onUnmounted(() => {
    request++;
    clearTimeout(timer);
});
async function search() {
    const id = ++request;
    loading.value = true;
    error.value = '';

    try {
        const result = await apiRequest(
            `/api/admin/certificate-users?${new URLSearchParams({ search: props.modelValue ? '' : text.value.trim() })}`,
        );

        if (id === request) {
            options.value = extractCdnflyRows(result);
            active.value = -1;
        }
    } catch (e) {
        if (id === request) {
            error.value = e instanceof Error ? e.message : '用户加载失败';
            options.value = [];
        }
    } finally {
        if (id === request) {
            loading.value = false;
        }
    }
}
function input() {
    focused.value = true;

    if (props.modelValue) {
        emit('update:modelValue', '');
        emit('select', null);
    }

    clearTimeout(timer);
    request++;
    options.value = [];
    timer = setTimeout(search, 250);
}
function choose(row: CdnflyRecord) {
    clearTimeout(timer);
    request++;
    loading.value = false;
    text.value = `${row.name ?? row.username ?? ''} (ID: ${row.id})`;
    focused.value = false;
    emit('update:modelValue', String(row.id));
    emit('select', row);
}
function key(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        focused.value = false;

        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        focused.value = true;
        active.value = Math.max(
            0,
            Math.min(
                options.value.length - 1,
                active.value + (event.key === 'ArrowDown' ? 1 : -1),
            ),
        );
    }

    if (event.key === 'Enter' && focused.value) {
        event.preventDefault();

        if (options.value[active.value]) {
            choose(options.value[active.value]);
        } else {
            void search();
        }
    }
}
</script>
<template>
    <div class="console-certificate-user-picker user-picker">
        <Input
            :id="inputId"
            v-model="text"
            role="combobox"
            aria-label="搜索用户"
            :aria-expanded="focused"
            aria-autocomplete="list"
            :disabled="disabled"
            placeholder="输入ID、邮箱、用户名、手机号搜索"
            @focus="
                focused = true;
                search();
            "
            @blur="focused = false"
            @input="input"
            @keydown="key"
        />
        <div
            v-if="focused && !disabled"
            role="listbox"
            aria-label="用户搜索结果"
            class="options"
        >
            <p data-typography="body" v-if="loading">加载中…</p>
            <p data-typography="body" v-else-if="error" role="alert">
                {{ error }}
                <Button
                    variant="link"
                    size="inline"
                    data-slot="console-link"
                    type="button"
                    @mousedown.prevent
                    @click="search"
                >
                    重试
                </Button>
            </p>
            <p data-typography="body" v-else-if="!options.length">
                暂无匹配用户
            </p>
            <Button
                variant="ghost"
                size="inline"
                data-slot="console-option"
                class="break-words whitespace-normal"
                v-for="(row, i) in options"
                :key="String(row.id)"
                type="button"
                role="option"
                :aria-selected="active === i"
                @mousedown.prevent
                @click="choose(row)"
            >
                {{ row.name ?? row.username }} (ID: {{ row.id }})<small>{{
                    row.email
                }}</small>
            </Button>
        </div>
    </div>
</template>
