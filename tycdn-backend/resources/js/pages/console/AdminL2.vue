<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, Plus, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { Spinner } from '@/components/ui/spinner';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { listAdminRegions } from '@/lib/adminModulesApi';
import { apiRequest } from '@/lib/apiRequest';
import {
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnflyResponse';
import { getErrorMessage, textValue } from '@/lib/formatters';
import {
    decodeL2Rules,
    l2MatchItems,
    l2MatchOperators,
} from '@/lib/l2Conditions';
import type { L2Rule } from '@/lib/l2Conditions';
import { masterGet } from '@/lib/masterApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';

type Tab = 'l2-configs' | 'l2-conds';
const props = withDefaults(defineProps<{ initialTab?: Tab }>(), {
    initialTab: 'l2-configs',
});
const active = ref<Tab>(props.initialTab);
const rows = ref<CdnflyRecord[]>([]),
    regions = ref<CdnflyRecord[]>([]);
const region = ref(''),
    nodeId = ref(
        new URLSearchParams(usePage().url.split('?')[1] ?? '').get('node_id') ??
            '',
    );
const page = ref(1),
    size = ref(10),
    total = ref(0),
    selected = ref<number[]>([]);
const loading = ref(false),
    error = ref(''),
    regionError = ref('');
const isConfig = computed(() => active.value === 'l2-configs');
const lastPage = computed(() =>
    Math.max(1, Math.ceil(total.value / size.value)),
);

const allSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selected.value.includes(Number(row.id))),
);
let requestVersion = 0;
async function load(target = page.value): Promise<void> {
    const version = ++requestVersion,
        resource = active.value;
    loading.value = true;
    error.value = '';

    try {
        const params: Record<string, string | number> = {
            page: target,
            limit: size.value,
        };

        if (resource === 'l2-configs' && region.value) {
            params.region_id = region.value;
        }

        if (resource === 'l2-configs' && nodeId.value) {
            params.node_id = nodeId.value;
        }

        const result = await masterGet(resource, params);

        if (version !== requestVersion) {
            return;
        }

        rows.value = extractCdnflyRows(result);
        total.value = extractCdnflyTotal(result, rows.value.length);

        if (target > lastPage.value) {
            await load(lastPage.value);

            return;
        }

        page.value = target;
        selected.value = selected.value.filter((id) =>
            rows.value.some((row) => Number(row.id) === id),
        );
    } catch (e) {
        if (version !== requestVersion) {
            return;
        }

        error.value = getErrorMessage(e);
        rows.value = [];
        total.value = 0;
        selected.value = [];
    } finally {
        if (version === requestVersion) {
            loading.value = false;
        }
    }
}
async function loadRegions(): Promise<void> {
    regionError.value = '';

    try {
        regions.value = extractCdnflyRows(await listAdminRegions({ limit: 0 }));
    } catch (e) {
        regionError.value = getErrorMessage(e);
    }
}
onMounted(() => {
    void load();
    void loadRegions();
});
function switchTab(tab: Tab): void {
    if (tab === active.value) {
        return;
    }

    active.value = tab;
    rows.value = [];
    total.value = 0;
    selected.value = [];
    page.value = 1;
    void load(1);
}
function selectRow(id: number, checked: boolean | 'indeterminate'): void {
    selected.value =
        checked === true
            ? [...new Set([...selected.value, id])]
            : selected.value.filter((value) => value !== id);
}
function regionName(row: CdnflyRecord): string {
    return (
        textValue(row.region_name) ||
        textValue(
            regions.value.find((r) => String(r.id) === String(row.region_id))
                ?.name,
        ) ||
        textValue(row.region_id)
    );
}

const dialog = ref(false),
    saving = ref(false),
    detailLoading = ref(false),
    detailError = ref(''),
    formError = ref('');
const editingId = ref<number | null>(null),
    editTab = ref<Tab>('l2-configs');
const form = reactive({
    region_id: '',
    name: '',
    des: '',
    mode: 'cache',
    balance_way: 'rr',
});
const rules = ref<L2Rule[]>([]),
    ruleIndex = ref<number | null>(null);
const matcher = reactive({ item: '', op: '', value: '', value2: '' });
const ruleError = ref(''),
    valueInput = ref<HTMLTextAreaElement>();
let editorVersion = 0;
function resetMatcher(): void {
    Object.assign(matcher, { item: '', op: '', value: '', value2: '' });
    ruleIndex.value = null;
    ruleError.value = '';
}
async function edit(row?: CdnflyRecord): Promise<void> {
    const version = ++editorVersion;
    editTab.value = active.value;
    editingId.value = row ? Number(row.id) : null;
    formError.value = '';
    detailError.value = '';
    resetMatcher();
    rules.value = [];
    Object.assign(form, {
        region_id: textValue(regions.value[0]?.id),
        name: '',
        des: '',
        mode: 'cache',
        balance_way: 'rr',
    });
    dialog.value = true;
    detailLoading.value = !!row;

    if (!row) {
        return;
    }

    try {
        const record = extractCdnflyRecord(
            await apiRequest(`/api/admin/workspace/${editTab.value}/${row.id}`),
        );

        if (version !== editorVersion) {
            return;
        }

        if (!record) {
            throw new Error('无法读取配置详情，请重试');
        }

        Object.assign(form, {
            region_id: textValue(record.region_id),
            name: textValue(record.name),
            des: textValue(record.des),
            mode: textValue(record.mode) || 'cache',
            balance_way: textValue(record.balance_way) || 'rr',
        });

        if (editTab.value === 'l2-conds') {
            rules.value = decodeL2Rules(record.data);
        }
    } catch (e) {
        if (version === editorVersion) {
            detailError.value = getErrorMessage(e);
        }
    } finally {
        if (version === editorVersion) {
            detailLoading.value = false;
        }
    }
}
function editRule(index: number): void {
    ruleIndex.value = index;
    Object.assign(matcher, rules.value[index]);
    ruleError.value = '';
    valueInput.value?.focus();
}
function addRule(): void {
    if (!matcher.item || !matcher.op) {
        ruleError.value = '请选择匹配项和操作符';

        return;
    }

    const rule: L2Rule = {
        ...(ruleIndex.value === null ? {} : rules.value[ruleIndex.value]),
        item: matcher.item,
        op: matcher.op,
        value: matcher.value,
        value2: matcher.value2,
    };

    if (ruleIndex.value === null) {
        rules.value.push(rule);
    } else {
        rules.value.splice(ruleIndex.value, 1, rule);
    }

    resetMatcher();
}
function removeRule(index: number): void {
    rules.value.splice(index, 1);

    if (ruleIndex.value === index) {
        resetMatcher();
    } else if (ruleIndex.value !== null && ruleIndex.value > index) {
        ruleIndex.value--;
    }
}
async function save(): Promise<void> {
    if (detailLoading.value || detailError.value) {
        return;
    }

    formError.value = '';

    if (!form.name.trim()) {
        formError.value = '请填写名称';

        return;
    }

    const payload: Record<string, unknown> = {
        name: form.name.trim(),
        des: form.des,
    };

    if (editTab.value === 'l2-configs') {
        if (!form.region_id) {
            formError.value = '请选择区域';

            return;
        }

        payload.mode = form.mode;
        payload.balance_way = form.balance_way;

        if (!editingId.value) {
            payload.region_id = Number(form.region_id);
        }
    } else {
        if (
            ruleIndex.value !== null ||
            matcher.item ||
            matcher.op ||
            matcher.value
        ) {
            formError.value = '请先添加、保存或取消当前正在编辑的规则';

            return;
        }

        payload.data = rules.value.map((rule) => ({ ...rule }));
    }

    saving.value = true;

    try {
        await apiRequest(
            `/api/admin/workspace/${editTab.value}${editingId.value ? `/${editingId.value}` : ''}`,
            {
                method: editingId.value ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            },
        );
        dialog.value = false;
        toast.success(editingId.value ? '已更新' : '已新增');
        await load(editingId.value ? page.value : 1);
    } catch (e) {
        formError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}
const deleteOpen = ref(false),
    deleting = ref(false),
    deleteError = ref(''),
    deleteIds = ref<number[]>([]),
    deleteTab = ref<Tab>('l2-configs');
function confirmDelete(ids: number[]): void {
    deleteIds.value = [...ids];
    deleteTab.value = active.value;
    deleteError.value = '';
    deleteOpen.value = true;
}
async function remove(): Promise<void> {
    deleting.value = true;
    deleteError.value = '';
    const failed: number[] = [],
        messages: string[] = [];

    for (const id of deleteIds.value) {
        try {
            await apiRequest(`/api/admin/workspace/${deleteTab.value}/${id}`, {
                method: 'DELETE',
            });
        } catch (e) {
            failed.push(id);
            messages.push(`#${id}: ${getErrorMessage(e)}`);
        }
    }

    deleteIds.value = failed;
    await load();
    selected.value = failed.filter((id) =>
        rows.value.some((row) => Number(row.id) === id),
    );
    deleting.value = false;

    if (failed.length) {
        deleteError.value = messages.join('；');
    } else {
        deleteOpen.value = false;
        toast.success('已删除');
    }
}
</script>

<template>
    <div class="min-w-0 flex-1 p-4 md:p-6">
        <section
            class="l2-panel rounded-xl border bg-card p-4 text-card-foreground shadow-sm"
            aria-label="L2 配置"
        >
            <h2 data-typography="page-title" class="mb-3 font-semibold">
                L2 配置
            </h2>
            <div class="mb-3 flex gap-1" role="tablist" aria-label="L2配置分类">
                <Button
                    variant="ghost"
                    type="button"
                    data-slot="console-tab"
                    v-for="tab in [
                        { key: 'l2-configs', label: 'L2配置' },
                        { key: 'l2-conds', label: 'L2条件' },
                    ] as const"
                    :key="tab.key"
                    role="tab"
                    :aria-selected="active === tab.key"
                    class="rounded-md px-4 py-2 text-sm"
                    :class="
                        active === tab.key
                            ? 'bg-primary/10 font-semibold text-primary'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="switchTab(tab.key)"
                >
                    {{ tab.label }}
                </Button>
            </div>
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <Button
                    size="sm"
                    :disabled="isConfig && !regions.length"
                    @click="edit()"
                    ><Plus />{{ isConfig ? '新增配置' : '新增条件' }}</Button
                >
                <Button
                    variant="destructive"
                    size="sm"
                    :disabled="loading || !selected.length"
                    @click="confirmDelete(selected)"
                    ><Trash2 />删除</Button
                >
                <SelectField
                    v-if="isConfig"
                    v-model="region"
                    aria-label="区域筛选"
                    class="h-8 w-52 rounded border bg-background px-2 text-sm"
                    @change="
                        selected = [];
                        load(1);
                    "
                >
                    <SelectOption value="">所有区域</SelectOption>
                    <SelectOption
                        v-for="r in regions"
                        :key="String(r.id)"
                        :value="String(r.id)"
                    >
                        {{ r.name }}
                    </SelectOption>
                </SelectField>
                <span v-if="isConfig && nodeId" class="text-sm"
                    >节点ID：{{ nodeId }}
                    <Button
                        variant="link"
                        size="inline"
                        type="button"
                        data-slot="console-link"
                        class="text-primary"
                        @click="
                            nodeId = '';
                            load(1);
                        "
                    >
                        显示所有
                    </Button></span
                >
            </div>
            <p
                data-typography="body"
                v-if="regionError && isConfig"
                role="alert"
                class="mb-3 text-destructive"
            >
                {{ regionError }}
                <Button
                    variant="link"
                    size="inline"
                    type="button"
                    data-slot="console-link"
                    class="underline"
                    @click="loadRegions"
                    >重试区域</Button
                >
            </p>
            <p
                data-typography="body"
                v-if="error"
                role="alert"
                class="mb-3 text-destructive"
            >
                {{ error }}
                <Button
                    variant="link"
                    size="inline"
                    type="button"
                    data-slot="console-link"
                    class="underline"
                    @click="load()"
                    >重试</Button
                >
            </p>
            <div class="overflow-x-auto">
                <table
                    class="w-full text-left text-sm"
                    :class="isConfig ? 'min-w-[850px]' : 'min-w-[600px]'"
                >
                    <thead class="border-b bg-muted/30 text-muted-foreground">
                        <tr>
                            <th class="w-12">
                                <Checkbox
                                    aria-label="选择本页全部"
                                    :disabled="loading || !rows.length"
                                    :model-value="
                                        allSelected
                                            ? true
                                            : selected.length
                                              ? 'indeterminate'
                                              : false
                                    "
                                    @update:model-value="
                                        selected =
                                            $event === true
                                                ? rows.map((row) =>
                                                      Number(row.id),
                                                  )
                                                : []
                                    "
                                />
                            </th>
                            <th>ID</th>
                            <th>名称</th>
                            <template v-if="isConfig"
                                ><th>区域</th>
                                <th>模式</th>
                                <th>负载方式</th></template
                            >
                            <th v-else>备注</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td
                                :colspan="isConfig ? 7 : 5"
                                class="h-24 text-center"
                            >
                                <Spinner class="mx-auto" />
                            </td>
                        </tr>
                        <tr
                            v-for="row in loading ? [] : rows"
                            :key="`${active}-${row.id}`"
                            class="border-b"
                        >
                            <td>
                                <Checkbox
                                    :aria-label="`选择 ${row.name}`"
                                    :model-value="
                                        selected.includes(Number(row.id))
                                    "
                                    @update:model-value="
                                        selectRow(Number(row.id), $event)
                                    "
                                />
                            </td>
                            <td>{{ row.id }}</td>
                            <td>
                                <Button
                                    variant="link"
                                    size="inline"
                                    type="button"
                                    data-slot="console-link"
                                    v-if="isConfig"
                                    class="text-primary hover:underline"
                                    @click="edit(row)"
                                >
                                    {{ row.name }}</Button
                                ><span v-else>{{ row.name }}</span>
                            </td>
                            <template v-if="isConfig"
                                ><td>{{ regionName(row) }}</td>
                                <td>
                                    {{
                                        row.mode === 'cache'
                                            ? '缓存'
                                            : row.mode === 'global'
                                              ? '全局'
                                              : row.mode
                                    }}
                                </td>
                                <td>
                                    {{
                                        row.balance_way === 'rr'
                                            ? '轮询'
                                            : row.balance_way === 'url_hash'
                                              ? 'URL哈希'
                                              : row.balance_way
                                    }}
                                </td></template
                            >
                            <td v-else>{{ row.des }}</td>
                            <td>
                                <div
                                    class="flex gap-2 whitespace-nowrap text-primary"
                                >
                                    <Button
                                        variant="link"
                                        size="inline"
                                        type="button"
                                        data-slot="console-link"
                                        v-if="isConfig"
                                        @click="
                                            router.visit(
                                                `/console/admin/workspace/l2-nodes?l2_config_id=${row.id}`,
                                            )
                                        "
                                    >
                                        配置节点</Button
                                    ><Button
                                        variant="link"
                                        size="inline"
                                        type="button"
                                        data-slot="console-link"
                                        @click="edit(row)"
                                        >编辑</Button
                                    ><Button
                                        variant="link"
                                        size="inline"
                                        type="button"
                                        data-slot="console-link"
                                        @click="confirmDelete([Number(row.id)])"
                                    >
                                        删除
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!loading && !rows.length">
                            <td
                                :colspan="isConfig ? 7 : 5"
                                class="h-20 text-center text-muted-foreground"
                            >
                                暂无数据
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ConsolePagination
                aria-label="L2分页"
                :total="total"
                :page="page"
                :previous-disabled="loading || page <= 1"
                :next-disabled="loading || page >= lastPage"
                @previous="load(page - 1)"
                @next="load(page + 1)"
            />
        </section>

        <Dialog
            :open="dialog"
            @update:open="
                (value) => {
                    if (!saving) {
                        dialog = value;
                        if (!value) editorVersion++;
                    }
                }
            "
        >
            <DialogScrollContent
                class="my-5 w-[calc(100%_-_2rem)] bg-card"
                :class="
                    editTab === 'l2-configs'
                        ? 'sm:max-w-[490px]'
                        : 'sm:max-w-[800px]'
                "
            >
                <DialogHeader
                    ><DialogTitle
                        >{{ editingId ? '编辑' : '新增'
                        }}{{
                            editTab === 'l2-configs' ? 'L2配置' : '条件'
                        }}</DialogTitle
                    ><DialogDescription class="sr-only">{{
                        editTab === 'l2-configs'
                            ? '设置区域、模式和负载方式'
                            : '设置条件名称与匹配规则'
                    }}</DialogDescription></DialogHeader
                >
                <div v-if="detailLoading" class="py-10">
                    <Spinner class="mx-auto" />
                </div>
                <p
                    data-typography="body"
                    v-else-if="detailError"
                    role="alert"
                    class="text-destructive"
                >
                    {{ detailError }}
                    <Button
                        variant="link"
                        size="inline"
                        type="button"
                        data-slot="console-link"
                        class="underline"
                        @click="edit({ id: editingId })"
                    >
                        重试
                    </Button>
                </p>
                <form
                    v-else
                    id="l2-form"
                    class="grid gap-5 py-3"
                    @submit.prevent="save"
                >
                    <p
                        data-typography="body"
                        v-if="formError"
                        role="alert"
                        class="text-destructive"
                    >
                        {{ formError }}
                    </p>
                    <div v-if="editTab === 'l2-configs'" class="form-row">
                        <Label for="l2-region">区域：</Label
                        ><SelectField
                            id="l2-region"
                            v-model="form.region_id"
                            :disabled="!!editingId"
                            class="h-8 rounded border bg-background px-2 text-sm"
                        >
                            <SelectOption
                                v-for="r in regions"
                                :key="String(r.id)"
                                :value="String(r.id)"
                            >
                                {{ r.name }}
                            </SelectOption>
                        </SelectField>
                    </div>
                    <div class="form-row">
                        <Label for="l2-name">名称：</Label
                        ><Input
                            id="l2-name"
                            v-model="form.name"
                            maxlength="255"
                            :placeholder="
                                editTab === 'l2-configs'
                                    ? '请输入L2配置名称'
                                    : '请输入名称'
                            "
                        />
                    </div>
                    <div class="form-row">
                        <Label for="l2-des">备注：</Label
                        ><Input
                            id="l2-des"
                            v-model="form.des"
                            placeholder="请输入备注"
                        />
                    </div>
                    <template v-if="editTab === 'l2-configs'">
                        <div class="form-row items-start">
                            <Label class="pt-2">模式：</Label>
                            <div>
                                <div
                                    class="flex"
                                    role="group"
                                    aria-label="模式"
                                >
                                    <Button
                                        variant="outline"
                                        data-slot="console-action"
                                        v-for="choice in [
                                            { value: 'cache', label: '缓存' },
                                            { value: 'global', label: '全局' },
                                        ]"
                                        :key="choice.value"
                                        type="button"
                                        class="choice"
                                        :class="
                                            form.mode === choice.value
                                                ? 'border-primary text-primary'
                                                : ''
                                        "
                                        :aria-pressed="
                                            form.mode === choice.value
                                        "
                                        @click="form.mode = choice.value"
                                    >
                                        {{ choice.label }}
                                    </Button>
                                </div>
                                <p
                                    data-typography="helper"
                                    class="mt-1 leading-5 text-muted-foreground"
                                >
                                    缓存模式时，只有配置了缓存的URL才会走L2节点回源；<br />全局模式时，所有URL都会走L2节点回源
                                </p>
                            </div>
                        </div>
                        <div class="form-row items-start">
                            <Label class="pt-2">负载方式：</Label>
                            <div>
                                <div
                                    class="flex"
                                    role="group"
                                    aria-label="负载方式"
                                >
                                    <Button
                                        variant="outline"
                                        data-slot="console-action"
                                        v-for="choice in [
                                            { value: 'rr', label: '轮询' },
                                            {
                                                value: 'url_hash',
                                                label: 'URL哈希',
                                            },
                                        ]"
                                        :key="choice.value"
                                        type="button"
                                        class="choice"
                                        :class="
                                            form.balance_way === choice.value
                                                ? 'border-primary text-primary'
                                                : ''
                                        "
                                        :aria-pressed="
                                            form.balance_way === choice.value
                                        "
                                        @click="form.balance_way = choice.value"
                                    >
                                        {{ choice.label }}
                                    </Button>
                                </div>
                                <p
                                    data-typography="helper"
                                    class="mt-1 leading-5 text-muted-foreground"
                                >
                                    轮询模式时，会轮流选择一个L2节点回源；<br />URL哈希模式时，同一个URL会固定使用同一个L2节点回源
                                </p>
                            </div>
                        </div>
                    </template>
                    <div v-else class="form-row items-start">
                        <Label class="pt-3">规则：</Label>
                        <div class="min-w-0">
                            <div class="overflow-x-auto">
                                <table
                                    class="rule-table w-full min-w-[480px] text-left text-sm"
                                >
                                    <thead
                                        class="border-b bg-muted/30 text-muted-foreground"
                                    >
                                        <tr>
                                            <th>匹配项</th>
                                            <th>操作符</th>
                                            <th>匹配值</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(rule, index) in rules"
                                            :key="index"
                                            class="border-b"
                                        >
                                            <td>
                                                {{
                                                    l2MatchItems[rule.item] ||
                                                    rule.item
                                                }}
                                            </td>
                                            <td>
                                                {{
                                                    l2MatchOperators[rule.op] ||
                                                    rule.op
                                                }}
                                            </td>
                                            <td class="max-w-56">
                                                <div
                                                    class="truncate whitespace-pre-line"
                                                    :title="rule.value"
                                                >
                                                    {{ rule.value || '（空）' }}
                                                </div>
                                            </td>
                                            <td>
                                                <div
                                                    class="flex gap-2 text-primary"
                                                >
                                                    <Button
                                                        variant="link"
                                                        size="inline"
                                                        data-slot="console-link"
                                                        type="button"
                                                        @click="editRule(index)"
                                                    >
                                                        编辑</Button
                                                    ><Button
                                                        variant="link"
                                                        size="inline"
                                                        data-slot="console-link"
                                                        type="button"
                                                        @click="
                                                            removeRule(index)
                                                        "
                                                    >
                                                        删除
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!rules.length">
                                            <td
                                                colspan="4"
                                                class="h-14 text-center text-muted-foreground"
                                            >
                                                暂无数据
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                class="mt-5 grid gap-2 rounded border bg-muted/10 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,2fr)_auto]"
                            >
                                <SelectField
                                    v-model="matcher.item"
                                    aria-label="匹配项"
                                    class="h-8 min-w-0 rounded border bg-background px-2 text-sm"
                                >
                                    <SelectOption value=""
                                        >请选择匹配项</SelectOption
                                    >
                                    <SelectOption
                                        v-if="
                                            matcher.item &&
                                            !l2MatchItems[matcher.item]
                                        "
                                        :value="matcher.item"
                                    >
                                        {{ matcher.item }}
                                    </SelectOption>
                                    <SelectOption
                                        v-for="(label, key) in l2MatchItems"
                                        :key="key"
                                        :value="key"
                                    >
                                        {{ label }}
                                    </SelectOption>
                                </SelectField>
                                <SelectField
                                    v-model="matcher.op"
                                    aria-label="操作符"
                                    class="h-8 min-w-0 rounded border bg-background px-2 text-sm"
                                >
                                    <SelectOption value=""
                                        >请选择操作符</SelectOption
                                    >
                                    <SelectOption
                                        v-if="
                                            matcher.op &&
                                            !l2MatchOperators[matcher.op]
                                        "
                                        :value="matcher.op"
                                    >
                                        {{ matcher.op }}
                                    </SelectOption>
                                    <SelectOption
                                        v-for="(label, key) in l2MatchOperators"
                                        :key="key"
                                        :value="key"
                                    >
                                        {{ label }}
                                    </SelectOption>
                                </SelectField>
                                <Textarea
                                    ref="valueInput"
                                    v-model="matcher.value"
                                    aria-label="匹配值"
                                    rows="4"
                                    class="min-w-0 rounded border bg-background px-2 py-1 text-sm"
                                    placeholder="输入匹配值(可为空)，一行一个，如：&#10;value1&#10;value2"
                                />
                                <div class="flex gap-2 sm:flex-col">
                                    <Button
                                        variant="link"
                                        size="inline"
                                        data-slot="console-link"
                                        type="button"
                                        class="text-sm text-primary"
                                        @click="addRule"
                                    >
                                        {{
                                            ruleIndex === null ? '添加' : '保存'
                                        }}</Button
                                    ><Button
                                        variant="link"
                                        size="inline"
                                        data-slot="console-link"
                                        v-if="
                                            ruleIndex !== null ||
                                            matcher.item ||
                                            matcher.op ||
                                            matcher.value
                                        "
                                        type="button"
                                        class="text-sm text-muted-foreground"
                                        @click="resetMatcher"
                                    >
                                        取消
                                    </Button>
                                </div>
                            </div>
                            <p
                                data-typography="body"
                                v-if="ruleError"
                                role="alert"
                                class="mt-1 text-destructive"
                            >
                                {{ ruleError }}
                            </p>
                            <p
                                data-typography="helper"
                                class="mt-1 text-muted-foreground"
                            >
                                多个匹配条件的关系为且，即需要满足所有条件才算匹配
                            </p>
                        </div>
                    </div>
                </form>
                <DialogFooter class="border-t pt-3"
                    ><Button
                        size="sm"
                        type="submit"
                        form="l2-form"
                        :disabled="saving || detailLoading || !!detailError"
                        ><Spinner v-if="saving" /><Check v-else />确定</Button
                    ><Button
                        size="sm"
                        variant="outline"
                        :disabled="saving"
                        @click="
                            dialog = false;
                            editorVersion++;
                        "
                        >取消</Button
                    ></DialogFooter
                >
            </DialogScrollContent>
        </Dialog>
        <ConfirmDeleteDialog
            :open="deleteOpen"
            title="删除确认"
            :description="`是否删除${deleteTab === 'l2-configs' ? 'L2配置' : '条件'} id: ${deleteIds.join(', ')}？`"
            :loading="deleting"
            :error="deleteError"
            @confirm="remove"
            @cancel="!deleting && (deleteOpen = false)"
        />
    </div>
</template>

<style scoped>
.l2-panel th,
.rule-table th {
    height: 36px;
    padding: 0 12px;
    font-weight: 600;
    white-space: nowrap;
}
.l2-panel td,
.rule-table td {
    height: 46px;
    padding: 8px 12px;
}
.form-row {
    display: grid;
    grid-template-columns: 76px minmax(0, 1fr);
    align-items: center;
    gap: 16px;
}
.form-row.items-start {
    align-items: start;
}
.form-row label {
    justify-content: flex-end;
    font-weight: 400;
}
.form-row input {
    height: 32px;
}
.choice {
    height: 30px;
    padding: 0 14px;
    border: 1px solid var(--border);
    font-size: var(--console-text-body);
}
.choice + .choice {
    margin-left: -1px;
}
.choice[aria-pressed='true'] {
    position: relative;
    border-color: var(--primary);
    color: var(--primary);
}
@media (max-width: 540px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .form-row label {
        justify-content: flex-start;
    }
}
</style>
