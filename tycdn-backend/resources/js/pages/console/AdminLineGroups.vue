<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
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
import { Spinner } from '@/components/ui/spinner';
import {
    createAdminNodeGroup,
    updateAdminNodeGroup,
    deleteAdminNodeGroup,
    listAdminNodeGroups,
    listAdminRegions,
} from '@/lib/adminModulesApi';
import type { AdminNodeGroupPayload } from '@/lib/adminModulesApi';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import { getErrorMessage, textValue } from '@/lib/formatters';
import { masterGet } from '@/lib/masterApi';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import AdminNodes from './AdminNodes.vue';

const rows = ref<CdnflyRecord[]>([]),
    regions = ref<CdnflyRecord[]>([]),
    l2Configs = ref<CdnflyRecord[]>([]);
const loading = ref(false),
    error = ref(''),
    referenceError = ref('');
const page = ref(1),
    size = ref(10),
    total = ref(0);
const region = ref(''),
    search = ref(''),
    selected = ref<number[]>([]);
const nodeId = ref(
    new URLSearchParams(usePage().url.split('?')[1] ?? '').get('node_id') ?? '',
);
const lastPage = computed(() =>
    Math.max(1, Math.ceil(total.value / size.value)),
);
const pages = computed(() =>
    [
        ...new Set([
            1,
            page.value - 1,
            page.value,
            page.value + 1,
            lastPage.value,
        ]),
    ]
        .filter((p) => p > 0 && p <= lastPage.value)
        .sort((a, b) => a - b),
);
const allSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selected.value.includes(Number(row.id))),
);
let loadVersion = 0;

async function load(target = page.value): Promise<void> {
    const version = ++loadVersion;
    loading.value = true;
    error.value = '';

    try {
        const params: Record<string, string | number> = {
            page: target,
            limit: size.value,
        };

        if (region.value) {
            params.region_id = region.value;
        }

        if (search.value.trim()) {
            params.search = search.value.trim();
        }

        if (nodeId.value) {
            params.node_id = nodeId.value;
        }

        const result = await listAdminNodeGroups(params);

        if (version !== loadVersion) {
            return;
        }

        rows.value = extractCdnflyRows(result);
        total.value = extractCdnflyTotal(result, rows.value.length);

        if (target > Math.max(1, Math.ceil(total.value / size.value))) {
            await load(Math.max(1, Math.ceil(total.value / size.value)));

            return;
        }

        page.value = target;
        selected.value = selected.value.filter((id) =>
            rows.value.some((row) => Number(row.id) === id),
        );
    } catch (e) {
        if (version !== loadVersion) {
            return;
        }

        error.value = getErrorMessage(e);
        rows.value = [];
        total.value = 0;
        selected.value = [];
    } finally {
        if (version === loadVersion) {
            loading.value = false;
        }
    }
}
async function loadRegions(): Promise<void> {
    referenceError.value = '';

    try {
        regions.value = extractCdnflyRows(await listAdminRegions({ limit: 0 }));
    } catch (e) {
        referenceError.value = getErrorMessage(e);
    }
}
onMounted(() => {
    void load();
    void loadRegions();
});
function clear(): void {
    region.value = '';
    search.value = '';
    selected.value = [];
    void load(1);
}
function selectRow(id: number, value: boolean | 'indeterminate'): void {
    selected.value =
        value === true
            ? [...new Set([...selected.value, id])]
            : selected.value.filter((item) => item !== id);
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
const count = (value: unknown) =>
    value === null || value === undefined ? '—' : textValue(value);

const dialog = ref(false),
    saving = ref(false),
    formError = ref(''),
    editing = ref<CdnflyRecord | null>(null);
const form = reactive({
    region_id: '',
    name: '',
    cname_hostname: '',
    v4_cname_hostname: '',
    des: '',
    sort: '100',
    l2_config_id: '',
    backup_switch_type: 'master_down',
    ip_num: '2',
    interval: '60',
    switch_order: 'rand',
});
const advanced = ref(false),
    l2Loading = ref(false),
    l2Error = ref('');
const policySnapshot = () =>
    JSON.stringify([
        form.backup_switch_type,
        String(form.ip_num),
        String(form.interval),
        form.switch_order,
    ]);
let originalPolicy = '';
let editorVersion = 0;
async function openEditor(row: CdnflyRecord | null = null): Promise<void> {
    const version = ++editorVersion;
    editing.value = row;
    formError.value = '';
    advanced.value = false;
    l2Error.value = '';
    let policy: Record<string, unknown> = {};

    try {
        policy =
            typeof row?.backup_switch_policy === 'string'
                ? JSON.parse(row.backup_switch_policy)
                : ((row?.backup_switch_policy as Record<string, unknown>) ??
                  {});
    } catch {
        /* Missing policy uses master defaults. */
    }

    Object.assign(form, {
        region_id: textValue(row?.region_id ?? regions.value[0]?.id),
        name: textValue(row?.name),
        cname_hostname: textValue(row?.cname_hostname),
        v4_cname_hostname: textValue(row?.v4_cname_hostname),
        des: textValue(row?.des),
        sort: textValue(row?.sort ?? 100),
        l2_config_id: textValue(row?.l2_config_id),
        backup_switch_type: textValue(row?.backup_switch_type) || 'master_down',
        ip_num: textValue(policy?.ip_num ?? 2),
        interval: textValue(policy?.interval ?? 60),
        switch_order: textValue(policy?.switch_order ?? 'rand'),
    });
    originalPolicy = policySnapshot();
    dialog.value = true;

    if (row) {
        l2Loading.value = true;

        try {
            const result = await masterGet('l2-configs', {
                limit: 0,
                region_id: form.region_id,
            });

            if (version !== editorVersion) {
                return;
            }

            l2Configs.value = extractCdnflyRows(result);
        } catch (e) {
            if (version !== editorVersion) {
                return;
            }

            l2Configs.value = [];
            l2Error.value = getErrorMessage(e);
        } finally {
            if (version === editorVersion) {
                l2Loading.value = false;
            }
        }
    }
}
async function save(): Promise<void> {
    formError.value = '';

    if (!form.name.trim() || !form.region_id) {
        formError.value = '请选择区域并填写名称';

        return;
    }

    if (
        !/^\d+$/.test(String(form.sort)) ||
        !Number.isSafeInteger(Number(form.sort))
    ) {
        formError.value = '排序必须为非负整数';

        return;
    }

    const payload: Partial<AdminNodeGroupPayload> = {
        name: form.name.trim(),
        cname_hostname: form.cname_hostname.trim(),
        des: form.des,
        sort: Number(form.sort),
    };

    if (editing.value) {
        payload.v4_cname_hostname = form.v4_cname_hostname.trim();

        // Keep optional settings untouched unless the editor actually changes them.
        if (form.l2_config_id !== textValue(editing.value.l2_config_id)) {
            payload.l2_config_id = form.l2_config_id
                ? Number(form.l2_config_id)
                : null;
        }

        if (policySnapshot() !== originalPolicy) {
            payload.backup_switch_type =
                form.backup_switch_type as AdminNodeGroupPayload['backup_switch_type'];

            if (form.backup_switch_type === 'interval') {
                if (
                    ![form.ip_num, form.interval].every(
                        (v) =>
                            /^\d+$/.test(String(v)) &&
                            Number.isSafeInteger(Number(v)) &&
                            Number(v) > 0,
                    )
                ) {
                    formError.value = '启用IP数和间隔时间必须为正整数';

                    return;
                }

                payload.backup_switch_policy = {
                    ip_num: Number(form.ip_num),
                    interval: Number(form.interval),
                    switch_order: form.switch_order === 'seq' ? 'seq' : 'rand',
                };
            }
        }
    } else {
        payload.region_id = Number(form.region_id);
        payload.backup_switch_type = 'master_down';
    }

    saving.value = true;

    try {
        if (editing.value) {
            await updateAdminNodeGroup(Number(editing.value.id), payload);
        } else {
            await createAdminNodeGroup(payload as AdminNodeGroupPayload);
        }

        dialog.value = false;
        toast.success(editing.value ? '线路分组已更新' : '线路分组已新增');
        await load(editing.value ? page.value : 1);
    } catch (e) {
        formError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}

const deleteOpen = ref(false),
    deleting = ref(false),
    deleteError = ref(''),
    deleteIds = ref<number[]>([]);
function confirmDelete(ids: number[]): void {
    deleteIds.value = [...ids];
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
            await deleteAdminNodeGroup(id);
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
        toast.success('线路分组已删除');
    }
}
const resolving = ref<CdnflyRecord | null>(null);
</script>

<template>
    <div class="min-w-0 flex-1 p-4 md:p-6">
        <section
            class="line-group-panel rounded-xl border bg-card p-4 text-card-foreground shadow-sm"
            aria-label="线路分组"
        >
            <div v-if="nodeId" class="mb-3 flex items-center gap-3 text-sm">
                节点ID：{{ nodeId }} 的线路组列表<button
                    class="text-primary"
                    @click="
                        nodeId = '';
                        load(1);
                    "
                >
                    显示所有
                </button>
            </div>
            <div class="mb-2 flex gap-2">
                <Button
                    size="sm"
                    :disabled="!regions.length"
                    @click="openEditor()"
                    ><Plus />新增分组</Button
                >
                <Button
                    size="sm"
                    variant="destructive"
                    :disabled="!selected.length || loading"
                    @click="confirmDelete(selected)"
                    ><Trash2 />删除</Button
                >
            </div>
            <form
                class="mb-3 flex flex-wrap items-center gap-2"
                @submit.prevent="load(1)"
            >
                <select
                    v-model="region"
                    aria-label="区域筛选"
                    class="h-8 w-52 rounded border bg-background px-2 text-sm"
                    @change="
                        selected = [];
                        load(1);
                    "
                >
                    <option value="">所有区域</option>
                    <option
                        v-for="r in regions"
                        :key="String(r.id)"
                        :value="String(r.id)"
                    >
                        {{ r.name }}
                    </option>
                </select>
                <div class="relative w-52">
                    <Input
                        v-model="search"
                        aria-label="搜索线路分组"
                        class="h-8 pr-8"
                        placeholder="填名称或解析值搜索"
                    /><button
                        type="submit"
                        aria-label="搜索"
                        class="absolute inset-y-0 right-2 text-muted-foreground"
                    >
                        <Search class="size-4" />
                    </button>
                </div>
                <button
                    type="button"
                    class="text-sm text-primary hover:underline"
                    @click="clear"
                >
                    清除
                </button>
            </form>
            <div
                v-if="referenceError"
                role="alert"
                class="mb-3 text-sm text-destructive"
            >
                {{ referenceError }}
                <button class="underline" @click="loadRegions">重试区域</button>
            </div>
            <div
                v-if="error"
                role="alert"
                class="mb-3 text-sm text-destructive"
            >
                {{ error }}
                <button class="underline" @click="load()">重试</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left text-sm">
                    <thead class="border-b bg-muted/30 text-muted-foreground">
                        <tr>
                            <th class="w-12">
                                <Checkbox
                                    aria-label="选择本页全部分组"
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
                            <th>区域</th>
                            <th>解析值</th>
                            <th>统计</th>
                            <th>L2配置</th>
                            <th>排序</th>
                            <th class="text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="9" class="h-24 text-center">
                                <Spinner class="mx-auto" />
                            </td>
                        </tr>
                        <tr
                            v-for="row in loading ? [] : rows"
                            :key="String(row.id)"
                            class="border-b"
                        >
                            <td>
                                <Checkbox
                                    :aria-label="`选择分组 ${row.name}`"
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
                                <button
                                    class="text-primary hover:underline"
                                    @click="openEditor(row)"
                                >
                                    {{ row.name }}
                                </button>
                            </td>
                            <td>{{ regionName(row) }}</td>
                            <td>
                                {{ row.cname_hostname
                                }}<span v-if="row.v4_cname_hostname"
                                    >（IPv4: {{ row.v4_cname_hostname }}）</span
                                >
                            </td>
                            <td class="whitespace-nowrap">
                                节点数({{ count(row.node_count) }}个) 网站数({{
                                    count(row.site_count)
                                }}个) 转发数({{ count(row.stream_count) }}个)
                            </td>
                            <td>{{ row.l2_config_name }}</td>
                            <td>{{ row.sort }}</td>
                            <td>
                                <div
                                    class="flex justify-center gap-2 whitespace-nowrap text-primary"
                                >
                                    <button @click="resolving = row">
                                        配置解析</button
                                    ><button @click="openEditor(row)">
                                        编辑</button
                                    ><button
                                        @click="confirmDelete([Number(row.id)])"
                                    >
                                        删除
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!loading && !rows.length">
                            <td
                                colspan="9"
                                class="h-20 text-center text-muted-foreground"
                            >
                                暂无数据
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                class="mt-4 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                aria-label="分组分页"
            >
                <span>共 {{ total }} 条</span
                ><Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="上一页"
                    :disabled="page <= 1 || loading"
                    @click="load(page - 1)"
                    ><ChevronLeft
                /></Button>
                <Button
                    v-for="p in pages"
                    :key="p"
                    variant="outline"
                    size="icon-sm"
                    :aria-label="`第 ${p} 页`"
                    :aria-current="p === page ? 'page' : undefined"
                    :class="p === page ? 'border-primary text-primary' : ''"
                    :disabled="loading"
                    @click="load(p)"
                    >{{ p }}</Button
                >
                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="下一页"
                    :disabled="page >= lastPage || loading"
                    @click="load(page + 1)"
                    ><ChevronRight
                /></Button>
                <select
                    v-model.number="size"
                    aria-label="每页条数"
                    class="ml-2 h-8 rounded border bg-background px-2"
                    @change="
                        selected = [];
                        load(1);
                    "
                >
                    <option v-for="n in [10, 20, 50, 100]" :key="n" :value="n">
                        {{ n }} 条/页
                    </option>
                </select>
            </div>
        </section>

        <Dialog
            :open="dialog"
            @update:open="
                (value) => {
                    if (!saving) dialog = value;
                }
            "
        >
            <DialogScrollContent
                class="my-5 w-[calc(100%_-_2rem)] self-start bg-card sm:max-w-[490px]"
            >
                <DialogHeader
                    ><DialogTitle>{{
                        editing ? '编辑线路分组' : '新增线路分组'
                    }}</DialogTitle
                    ><DialogDescription class="sr-only"
                        >填写线路分组配置</DialogDescription
                    ></DialogHeader
                >
                <form
                    id="line-group-form"
                    class="group-form grid gap-5 py-3"
                    @submit.prevent="save"
                >
                    <div
                        v-if="formError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ formError }}
                    </div>
                    <div class="form-row">
                        <Label for="group-region">区域：</Label
                        ><select
                            id="group-region"
                            v-model="form.region_id"
                            :disabled="!!editing"
                            class="h-8 rounded border bg-background px-2 text-sm"
                        >
                            <option
                                v-for="r in regions"
                                :key="String(r.id)"
                                :value="String(r.id)"
                            >
                                {{ r.name }}
                            </option>
                        </select>
                    </div>
                    <div class="form-row">
                        <Label for="group-name">名称：</Label
                        ><Input
                            id="group-name"
                            v-model="form.name"
                            maxlength="255"
                            placeholder="请输入线路分组名称"
                        />
                    </div>
                    <div class="form-row">
                        <Label for="group-cname">解析值：</Label
                        ><Input
                            id="group-cname"
                            v-model="form.cname_hostname"
                            maxlength="255"
                            placeholder="留空随机生成 (不填域名部分)"
                        />
                    </div>
                    <div v-if="editing" class="form-row">
                        <Label for="group-v4">IPv4解析值：</Label
                        ><Input
                            id="group-v4"
                            v-model="form.v4_cname_hostname"
                            maxlength="255"
                            placeholder="留空随机生成 (不填域名部分)"
                        />
                    </div>
                    <div class="form-row">
                        <Label for="group-description">备注：</Label
                        ><Input
                            id="group-description"
                            v-model="form.des"
                            maxlength="1000"
                            placeholder="请输入备注"
                        />
                    </div>
                    <div class="form-row">
                        <Label for="group-sort">排序：</Label
                        ><Input
                            id="group-sort"
                            v-model="form.sort"
                            type="number"
                            min="0"
                            step="1"
                        />
                    </div>
                    <template v-if="editing">
                        <div class="form-row">
                            <Label for="group-l2">L2配置：</Label
                            ><select
                                id="group-l2"
                                v-model="form.l2_config_id"
                                :disabled="l2Loading || !!l2Error"
                                class="h-8 rounded border bg-background px-2 text-sm"
                            >
                                <option value="">无</option>
                                <option
                                    v-if="
                                        form.l2_config_id &&
                                        !l2Configs.some(
                                            (c) =>
                                                String(c.id) ===
                                                form.l2_config_id,
                                        )
                                    "
                                    :value="form.l2_config_id"
                                >
                                    {{
                                        editing.l2_config_name ||
                                        form.l2_config_id
                                    }}
                                </option>
                                <option
                                    v-for="config in l2Configs"
                                    :key="String(config.id)"
                                    :value="String(config.id)"
                                >
                                    {{ config.name }}
                                </option>
                            </select>
                        </div>
                        <p
                            v-if="l2Error"
                            role="alert"
                            class="text-sm text-destructive"
                        >
                            {{ l2Error }}
                        </p>
                        <button
                            type="button"
                            class="text-left text-sm text-primary"
                            :aria-expanded="advanced"
                            @click="advanced = !advanced"
                        >
                            备用IP切换设置
                        </button>
                        <template v-if="advanced">
                            <div class="form-row">
                                <Label for="group-switch">备用IP切换：</Label
                                ><select
                                    id="group-switch"
                                    v-model="form.backup_switch_type"
                                    class="h-8 min-w-0 rounded border bg-background px-2 text-sm"
                                >
                                    <option value="master_down">
                                        有主IP下线时
                                    </option>
                                    <option value="gt_online_ip">
                                        在线IP数少于备用IP数时
                                    </option>
                                    <option value="interval">间隔切换</option>
                                </select>
                            </div>
                            <template
                                v-if="form.backup_switch_type === 'interval'"
                            >
                                <div class="form-row">
                                    <Label for="group-ip-num">启用IP数：</Label
                                    ><Input
                                        id="group-ip-num"
                                        v-model="form.ip_num"
                                        type="number"
                                        min="1"
                                    />
                                </div>
                                <div class="form-row">
                                    <Label for="group-interval"
                                        >间隔秒数：</Label
                                    ><Input
                                        id="group-interval"
                                        v-model="form.interval"
                                        type="number"
                                        min="1"
                                    />
                                </div>
                                <div class="form-row">
                                    <Label for="group-order">切换次序：</Label
                                    ><select
                                        id="group-order"
                                        v-model="form.switch_order"
                                        class="h-8 rounded border bg-background px-2 text-sm"
                                    >
                                        <option value="seq">顺序</option>
                                        <option value="rand">随机</option>
                                    </select>
                                </div>
                            </template>
                        </template>
                    </template>
                </form>
                <DialogFooter class="border-t pt-3"
                    ><Button
                        type="submit"
                        form="line-group-form"
                        size="sm"
                        :disabled="saving"
                        ><Spinner v-if="saving" /><Check v-else />确定</Button
                    ><Button
                        size="sm"
                        variant="outline"
                        :disabled="saving"
                        @click="dialog = false"
                        >取消</Button
                    ></DialogFooter
                >
            </DialogScrollContent>
        </Dialog>
        <ConfirmDeleteDialog
            :open="deleteOpen"
            title="删除确认"
            :description="`是否删除线路分组 id: ${deleteIds.join(', ')}？`"
            :loading="deleting"
            :error="deleteError"
            @confirm="remove"
            @cancel="!deleting && (deleteOpen = false)"
        />
        <Dialog
            :open="!!resolving"
            @update:open="
                (value) => {
                    if (!value) {
                        resolving = null;
                        load();
                    }
                }
            "
            ><DialogScrollContent class="sm:max-w-[1200px]"
                ><DialogHeader
                    ><DialogTitle>配置解析 · {{ resolving?.name }}</DialogTitle
                    ><DialogDescription class="sr-only"
                        >配置当前线路分组的节点和DNS解析线路</DialogDescription
                    ></DialogHeader
                ><AdminNodes
                    v-if="resolving"
                    :key="String(resolving.id)"
                    initial-tab="topology"
                    :initial-group-id="String(resolving.id)"
                    resolution-only /></DialogScrollContent
        ></Dialog>
    </div>
</template>

<style scoped>
.line-group-panel th {
    height: 36px;
    padding: 0 12px;
    font-weight: 600;
    white-space: nowrap;
}
.line-group-panel td {
    height: 46px;
    padding: 8px 12px;
}
.form-row {
    display: grid;
    grid-template-columns: 84px minmax(0, 1fr);
    align-items: center;
    gap: 16px;
}
.form-row label {
    justify-content: flex-end;
    font-weight: 400;
}
.form-row input {
    height: 32px;
}
</style>
