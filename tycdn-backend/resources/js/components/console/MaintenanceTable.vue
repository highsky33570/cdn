<script setup lang="ts">
import { computed } from 'vue';
import { isCdnflyRecord } from '@/lib/cdnflyResponse';
import { fieldLabels, secretField } from '@/lib/configEditor';

const props = defineProps<{
    value: unknown;
    title: string;
    labels: Record<string, string>;
    log?: boolean;
    loading?: boolean;
    error?: string;
    emptyMessage?: string;
}>();

function label(key: string): string {
    return props.labels[key] ?? fieldLabels[key] ?? key.replaceAll('_', ' ');
}
function display(value: unknown, key = ''): string {
    if (secretField(key)) {
        return value == null || value === '' ? '未配置' : '已配置';
    }

    if (value == null || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? '是' : '否';
    }

    if (Array.isArray(value)) {
        return value.map((item) => display(item)).join('\n') || '—';
    }

    if (isCdnflyRecord(value)) {
        return (
            Object.entries(value)
                .map(([name, item]) => `${label(name)}：${display(item, name)}`)
                .join('\n') || '—'
        );
    }

    return String(value);
}
function fields(value: Record<string, unknown>, prefix = ''): string[][] {
    return Object.entries(value).flatMap(([key, item]) => {
        const name = prefix ? `${prefix} / ${label(key)}` : label(key);

        if (
            isCdnflyRecord(item) &&
            !secretField(key) &&
            Object.keys(item).length
        ) {
            return fields(item, name);
        }

        return [[name, display(item, key)]];
    });
}
const table = computed(() => {
    const value = props.value;

    if (props.log && typeof value === 'string') {
        const lines = value === '' ? [] : value.split(/\r\n|\n|\r/);

        if (lines.at(-1) === '') {
            lines.pop();
        }

        return {
            columns: ['行号', '日志内容'],
            rows: lines.map((line, index) => [String(index + 1), line]),
        };
    }

    if (Array.isArray(value)) {
        if (value.length && value.every(isCdnflyRecord)) {
            const keys = [...new Set(value.flatMap((row) => Object.keys(row)))];

            return {
                columns: keys.map(label),
                rows: value.map((row) =>
                    keys.map((key) => display(row[key], key)),
                ),
            };
        }

        return {
            columns: ['序号', '内容'],
            rows: value.map((item, index) => [
                String(index + 1),
                display(item),
            ]),
        };
    }

    return {
        columns: props.log ? ['行号', '日志内容'] : ['项目', '详情'],
        rows: isCdnflyRecord(value)
            ? fields(value)
            : value == null || value === ''
              ? []
              : [['状态', display(value)]],
    };
});
</script>

<template>
    <div class="max-h-[32rem] overflow-auto">
        <table
            :aria-label="title"
            :aria-busy="loading"
            class="w-full border-collapse text-left text-sm"
        >
            <thead class="sticky top-0 z-10 bg-muted text-muted-foreground">
                <tr>
                    <th
                        v-for="(column, index) in table.columns"
                        :key="index"
                        scope="col"
                        class="border-y px-5 py-3 font-medium whitespace-nowrap"
                        :class="log && index === 0 ? 'w-20' : ''"
                    >
                        {{ column }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr
                    v-if="
                        error ||
                        (loading && !table.rows.length) ||
                        !table.rows.length
                    "
                >
                    <td
                        :colspan="Math.max(table.columns.length, 1)"
                        class="px-5 py-8 text-center"
                    >
                        <p v-if="error" role="alert" class="text-destructive">
                            {{ error }}
                        </p>
                        <p v-else role="status" class="text-muted-foreground">
                            {{
                                loading ? '加载中…' : emptyMessage || '暂无数据'
                            }}
                        </p>
                    </td>
                </tr>
                <tr
                    v-for="(row, index) in error ? [] : table.rows"
                    :key="index"
                    class="align-top transition-colors hover:bg-muted/40"
                >
                    <td
                        v-for="(cell, column) in row"
                        :key="column"
                        class="max-w-xl px-5 py-3 [overflow-wrap:anywhere] break-words whitespace-pre-wrap"
                        :class="[
                            column === 0
                                ? 'text-muted-foreground'
                                : 'text-foreground',
                            log ? 'font-mono text-xs leading-6' : '',
                        ]"
                    >
                        {{ cell }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
