<script setup lang="ts">
import { computed, ref } from 'vue';
import ConfigFields from '@/components/console/ConfigFields.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { siteDefaultFields } from '@/lib/configDefaults';
import type { ConfigObject, ConfigValue } from '@/lib/configEditor';
const props = defineProps<{
    kind: 'cache' | 'headers';
    value: ConfigValue;
    disabled?: boolean;
}>();
const emit = defineEmits<{ change: [value: ConfigValue] }>();
const rows = computed(() =>
    Array.isArray(props.value) ? (props.value as ConfigObject[]) : [],
);
const isCache = computed(() => props.kind === 'cache');
const title = computed(() => (isCache.value ? '缓存规则' : '回源请求头'));
const fields = computed(
    () =>
        siteDefaultFields.find(
            (field) =>
                field.key === (isCache.value ? 'proxy_cache' : 'req_header'),
        )?.fields ?? [],
);
const open = ref(false),
    index = ref(-1),
    draft = ref<ConfigObject>({}),
    error = ref('');
function edit(i = -1) {
    index.value = i;
    error.value = '';
    draft.value = JSON.parse(
        JSON.stringify(
            i >= 0
                ? rows.value[i]
                : isCache.value
                  ? {
                        type: 'suffix',
                        content: '',
                        expire: 1,
                        unit: 'd',
                        ignore_arg: 0,
                        range: 0,
                        proxy_ignore_headers: '',
                        no_cache: [],
                    }
                  : { name: '', value: '', action: 'add', allow_repeat: false },
        ),
    );
    open.value = true;
}
function save() {
    const row = { ...draft.value };

    if (isCache.value) {
        if (
            !['index', 'all'].includes(String(row.type)) &&
            !String(row.content ?? '').trim()
        ) {
            error.value = '请输入匹配内容';

            return;
        }

        if (
            !/^\d+$/.test(String(row.expire)) ||
            !Number.isSafeInteger(Number(row.expire))
        ) {
            error.value = '请输入有效的缓存时间';

            return;
        }

        if (row.type === 'index') {
            row.type = 'full_path';
            row.content = '/';
        }

        if (row.type === 'all') {
            row.type = 'dir';
            row.content = '/';
        }

        row.expire = Number(row.expire);
    } else {
        if (!String(row.name ?? '').trim()) {
            error.value = '请输入请求头名称';

            return;
        }

        row.allow_repeat =
            row.allow_repeat === true || Number(row.allow_repeat) === 1;
    }

    const next = [...rows.value];

    if (index.value < 0) {
        next.push(row);
    } else {
        next[index.value] = row;
    }

    emit('change', next);
    open.value = false;
}
function move(i: number, step: number) {
    const next = [...rows.value];
    [next[i], next[i + step]] = [next[i + step], next[i]];
    emit('change', next);
}
function quick(event: Event) {
    const select = event.target as HTMLSelectElement,
        preset = select.value;

    if (!preset) {
        return;
    }

    select.value = '';
    index.value = -1;
    error.value = '';
    draft.value = {
        type:
            preset === 'index'
                ? 'full_path'
                : preset === 'all'
                  ? 'dir'
                  : 'suffix',
        content: ['index', 'all'].includes(preset)
            ? '/'
            : preset === 'video'
              ? 'mp4|flv|m3u8|ts|webm'
              : 'jpg|jpeg|png|gif|ico|css|js|woff|woff2',
        expire: 1,
        unit: 'd',
        ignore_arg: 0,
        range: preset === 'video' ? 1 : 0,
        proxy_ignore_headers: '',
        no_cache: [],
    };
    open.value = true;
}
</script>
<template>
    <div class="rounded-lg border bg-card p-3">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-sm font-medium">
                {{ title }}
                <span class="text-xs text-muted-foreground"
                    >{{ rows.length }} 条</span
                >
            </h4>
            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    size="sm"
                    :disabled="disabled"
                    @click="edit()"
                    >{{ isCache ? '新增规则' : '新增请求头' }}</Button
                ><SelectField
                    v-if="isCache"
                    aria-label="快速设置缓存"
                    class="h-8 rounded-md border border-input bg-background px-2 text-sm"
                    :disabled="disabled"
                    @change="quick"
                >
                    <SelectOption value="">快速设置缓存</SelectOption>
                    <SelectOption value="index">首页缓存</SelectOption>
                    <SelectOption value="all">全站缓存</SelectOption>
                    <SelectOption value="static">静态资源缓存</SelectOption>
                    <SelectOption value="video">视频文件缓存</SelectOption>
                </SelectField>
            </div>
        </div>
        <div
            v-if="!rows.length"
            class="rounded-md border border-dashed p-5 text-center text-sm text-muted-foreground"
        >
            <p>{{ isCache ? '暂无缓存规则' : '暂无回源请求头' }}</p>
            <template v-if="isCache"
                ><p class="my-3 text-xs">
                    可以新增规则，或使用快速设置生成常用缓存策略。
                </p>
                <Button
                    type="button"
                    size="sm"
                    :disabled="disabled"
                    @click="edit()"
                    >新增第一条规则</Button
                ></template
            >
        </div>
        <div
            v-for="(row, i) in rows"
            :key="i"
            class="flex flex-wrap items-center justify-between gap-3 border-t py-3 text-sm"
        >
            <div class="min-w-0 break-all">
                <strong
                    >#{{ i + 1 }} {{ isCache ? row.type : row.name }}</strong
                >
                <p class="text-xs text-muted-foreground">
                    {{
                        isCache
                            ? `${row.content} · ${row.expire} ${row.unit}`
                            : `${row.action} · ${row.value ?? ''}`
                    }}
                </p>
            </div>
            <div class="flex gap-1">
                <Button
                    v-if="isCache"
                    type="button"
                    size="sm"
                    variant="ghost"
                    :disabled="disabled || i === 0"
                    aria-label="上移规则"
                    @click="move(i, -1)"
                    >↑</Button
                ><Button
                    v-if="isCache"
                    type="button"
                    size="sm"
                    variant="ghost"
                    :disabled="disabled || i === rows.length - 1"
                    aria-label="下移规则"
                    @click="move(i, 1)"
                    >↓</Button
                ><Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    :disabled="disabled"
                    @click="edit(i)"
                    >编辑</Button
                ><Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    :disabled="disabled"
                    @click="
                        emit(
                            'change',
                            rows.filter((_, n) => n !== i),
                        )
                    "
                    >删除</Button
                >
            </div>
        </div>
        <Dialog v-model:open="open"
            ><DialogScrollContent class="sm:max-w-2xl"
                ><DialogHeader
                    ><DialogTitle
                        >{{ index < 0 ? '新增' : '编辑'
                        }}{{ title }}</DialogTitle
                    ><DialogDescription
                        >确认后应用此{{
                            isCache ? '缓存规则' : '请求头'
                        }}。</DialogDescription
                    ></DialogHeader
                >
                <div class="grid gap-4">
                    <ConfigFields
                        v-model="draft"
                        :fields="fields"
                        :disabled="disabled"
                    />
                    <p
                        v-if="error"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                            >取消</Button
                        ><Button
                            type="button"
                            :disabled="disabled"
                            @click="save"
                            >确定</Button
                        ></DialogFooter
                    >
                </div></DialogScrollContent
            ></Dialog
        >
    </div>
</template>
