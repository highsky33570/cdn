<script setup lang="ts">
import { Eye, EyeOff, Plus, RefreshCw, Trash2 } from 'lucide-vue-next';
import { onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import PackagePagination from '@/components/console/PackagePagination.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import Switch from '@/components/ui/switch/Switch.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { apiRequest } from '@/lib/apiRequest';
import {
    extractCdnflyRecord,
    extractCdnflyRows,
    extractCdnflyTotal,
} from '@/lib/cdnflyResponse';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const endpoint = '/api/admin/workspace/messages';
const rows = ref<CdnflyRecord[]>([]),
    total = ref(0),
    page = ref(1),
    size = ref(10);
const selected = ref<(string | number)[]>([]),
    loading = ref(false),
    error = ref(''),
    busy = ref(false);
const columns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '80px' },
    { key: 'title', label: '标题', width: '270px' },
    { key: 'is_external', label: '跳转', width: '100px' },
    { key: 'is_popup', label: '弹窗', width: '100px' },
    { key: 'style', label: '样式', width: '140px' },
    { key: 'is_show', label: '显示', width: '110px' },
    { key: 'sort', label: '排序', width: '90px' },
    {
        key: 'create_at2',
        altKeys: ['create_at'],
        label: '添加时间',
        width: '190px',
    },
    {
        key: 'update_at2',
        altKeys: ['update_at'],
        label: '更新时间',
        width: '190px',
    },
];
const enabled = (value: unknown) =>
    value === true || value === 1 || value === '1';
const message = (e: unknown) => (e instanceof Error ? e.message : '请求失败');
let listToken = 0,
    detailToken = 0;
async function load(preserveSelection = false) {
    const token = ++listToken;
    loading.value = true;
    error.value = '';

    if (!preserveSelection) {
        selected.value = [];
    }

    try {
        const result = await apiRequest(
            `${endpoint}?type=announcement&page=${page.value}&limit=${size.value}`,
        );

        if (token !== listToken) {
            return;
        }

        rows.value = extractCdnflyRows(result);
        total.value = extractCdnflyTotal(result, rows.value.length);
        const last = Math.max(1, Math.ceil(total.value / size.value));

        if (page.value > last) {
            page.value = last;
        }
    } catch (e) {
        if (token === listToken) {
            error.value = message(e);
            rows.value = [];
            total.value = 0;
        }
    } finally {
        if (token === listToken) {
            loading.value = false;
        }
    }
}
watch(page, () => void load());
watch(size, () => {
    if (page.value !== 1) {
        page.value = 1;
    } else {
        void load();
    }
});
onMounted(() => void load());
onUnmounted(() => {
    listToken++;
    detailToken++;
});

const editorOpen = ref(false),
    editId = ref<string | number | null>(null),
    detailLoading = ref(false),
    detailError = ref(''),
    saveError = ref('');
const defaults = () => ({
    title: '',
    content: '',
    url: '',
    external: false,
    sort: '100',
    show: true,
    popup: false,
    red: false,
    bold: false,
});
const form = reactive(defaults());
watch(editorOpen, (open) => {
    if (!open) {
        detailToken++;
    }
});
async function edit(row?: CdnflyRecord) {
    detailToken++;
    editId.value = row ? String(row.id) : null;
    Object.assign(form, defaults());
    detailError.value = '';
    saveError.value = '';
    detailLoading.value = false;
    editorOpen.value = true;

    if (row) {
        await loadDetail();
    }
}
async function loadDetail() {
    const token = ++detailToken;
    detailLoading.value = true;
    detailError.value = '';

    try {
        const row = extractCdnflyRecord(
            await apiRequest(`${endpoint}/${editId.value}`),
        );

        if (token !== detailToken) {
            return;
        }

        if (!row || row.id == null) {
            throw new Error('无法获取公告详情');
        }

        Object.assign(form, {
            title: String(row.title ?? ''),
            content: String(row.content ?? ''),
            url: String(row.url ?? ''),
            external: enabled(row.is_external),
            sort: String(row.sort ?? 100),
            show: enabled(row.is_show),
            popup: enabled(row.is_popup),
            red: enabled(row.is_red),
            bold: enabled(row.is_bold),
        });
    } catch (e) {
        if (token === detailToken) {
            detailError.value = message(e);
        }
    } finally {
        if (token === detailToken) {
            detailLoading.value = false;
        }
    }
}
async function save() {
    if (busy.value || detailLoading.value || detailError.value) {
        return;
    }

    saveError.value = '';

    if (!form.title.trim()) {
        saveError.value = '请输入标题';

        return;
    }

    if (form.external ? !form.url.trim() : !form.content.trim()) {
        saveError.value = form.external ? '请输入跳转链接' : '请输入公告内容';

        return;
    }

    if (form.external && !/^https?:\/\//i.test(form.url.trim())) {
        saveError.value = '跳转链接须以 http:// 或 https:// 开头';

        return;
    }

    if (
        !/^-?\d+$/.test(form.sort) ||
        !Number.isSafeInteger(Number(form.sort))
    ) {
        saveError.value = '排序必须为整数';

        return;
    }

    busy.value = true;

    try {
        const payload = {
            type: 'announcement',
            title: form.title.trim(),
            ...(form.external
                ? { url: form.url.trim() }
                : { content: form.content }),
            sort: Number(form.sort),
            is_external: Number(form.external),
            is_show: Number(form.show),
            is_popup: Number(form.popup),
            is_red: Number(form.red),
            is_bold: Number(form.bold),
        };
        await apiRequest(
            editId.value === null ? endpoint : `${endpoint}/${editId.value}`,
            {
                method: editId.value === null ? 'POST' : 'PUT',
                body: JSON.stringify(payload),
            },
        );
        editorOpen.value = false;
        toast.success('公告已保存');
        await load();
    } catch (e) {
        saveError.value = message(e);
    } finally {
        busy.value = false;
    }
}

const deleteOpen = ref(false),
    deleteIds = ref<(string | number)[]>([]),
    deleteError = ref('');
function confirmDelete(ids: (string | number)[]) {
    deleteIds.value = [...ids];
    deleteError.value = '';
    deleteOpen.value = true;
}
async function mutate(ids: (string | number)[], visible?: boolean) {
    if (busy.value || !ids.length) {
        return;
    }

    busy.value = true;
    error.value = '';
    deleteError.value = '';
    const failed: (string | number)[] = [];
    let reason = '';

    for (const id of ids) {
        try {
            await apiRequest(`${endpoint}/${id}`, {
                method: visible === undefined ? 'DELETE' : 'PUT',
                ...(visible === undefined
                    ? {}
                    : { body: JSON.stringify({ is_show: Number(visible) }) }),
            });
        } catch (e) {
            failed.push(id);
            reason = message(e);
        }
    }

    selected.value = failed;
    await load(true);

    if (visible === undefined) {
        deleteIds.value = failed;

        if (failed.length) {
            deleteError.value = `${failed.length} 条公告删除失败：${reason}`;
        } else {
            deleteOpen.value = false;
        }
    } else if (failed.length) {
        error.value = `${failed.length} 条公告更新失败：${reason}`;
    }

    if (!failed.length) {
        toast.success(
            visible === undefined
                ? '公告已删除'
                : visible
                  ? '公告已显示'
                  : '公告已隐藏',
        );
    }

    busy.value = false;
}
</script>

<template>
    <div class="announcements-workspace min-w-0 p-4 md:p-6">
        <section class="min-w-0 rounded-xl border bg-card p-4 md:p-5">
            <div
                class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-4"
            >
                <div class="flex gap-2">
                    <Button :disabled="busy" @click="edit()"
                        ><Plus class="size-4" />新增公告</Button
                    ><Button
                        variant="outline"
                        :disabled="loading || busy"
                        @click="load()"
                        ><RefreshCw class="size-4" />刷新</Button
                    >
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        :disabled="!selected.length || busy || loading"
                        @click="mutate([...selected], true)"
                        ><Eye class="size-4" />显示</Button
                    ><Button
                        variant="outline"
                        :disabled="!selected.length || busy || loading"
                        @click="mutate([...selected], false)"
                        ><EyeOff class="size-4" />隐藏</Button
                    ><Button
                        variant="destructive"
                        :disabled="!selected.length || busy || loading"
                        @click="confirmDelete(selected)"
                        ><Trash2 class="size-4" />删除</Button
                    >
                </div>
            </div>
            <Alert v-if="error" variant="destructive" class="mb-4"
                ><AlertDescription
                    >{{ error
                    }}<Button
                        variant="outline"
                        size="sm"
                        :disabled="loading || busy"
                        @click="load()"
                        >重试</Button
                    ></AlertDescription
                ></Alert
            >
            <ConsoleDataTable
                v-model:selected="selected"
                class="announcement-table"
                title="公告管理"
                :columns="columns"
                embedded
                selectable
                :selection-disabled="busy || loading"
                :data="{ rows, total, page, pageSize: size, loading }"
                empty-text="暂无数据"
            >
                <template #cell-title="{ row }"
                    ><span
                        class="block max-w-64 truncate"
                        :class="{
                            'text-destructive': enabled(row.is_red),
                            'font-bold': enabled(row.is_bold),
                        }"
                        :title="String(row.title ?? '')"
                        >{{ row.title }}</span
                    ></template
                >
                <template #cell-is_external="{ row }">{{
                    enabled(row.is_external) ? '是' : '否'
                }}</template>
                <template #cell-is_popup="{ row }">{{
                    enabled(row.is_popup) ? '弹窗展示' : '普通展示'
                }}</template>
                <template #cell-style="{ row }"
                    ><div class="flex gap-1">
                        <Badge
                            v-if="enabled(row.is_red)"
                            variant="outline"
                            class="text-destructive"
                            >加红</Badge
                        ><Badge
                            v-if="enabled(row.is_bold)"
                            variant="outline"
                            class="font-bold"
                            >加粗</Badge
                        ><span
                            v-if="!enabled(row.is_red) && !enabled(row.is_bold)"
                            class="text-muted-foreground"
                            >默认</span
                        >
                    </div></template
                >
                <template #cell-is_show="{ row }"
                    ><Badge
                        :variant="
                            enabled(row.is_show) ? 'secondary' : 'outline'
                        "
                        >{{ enabled(row.is_show) ? '已显示' : '已隐藏' }}</Badge
                    ></template
                >
                <template #row-actions="{ row }"
                    ><Button
                        variant="link"
                        size="sm"
                        :disabled="busy"
                        @click="edit(row)"
                        >编辑</Button
                    ></template
                >
            </ConsoleDataTable>
            <PackagePagination
                v-model:page="page"
                v-model:page-size="size"
                :total="total"
                :disabled="loading || busy"
                numbered
            />
        </section>
        <Dialog
            :open="editorOpen"
            @update:open="
                (value) => {
                    if (!busy) editorOpen = value;
                }
            "
        >
            <DialogScrollContent
                class="max-h-[94dvh] max-w-4xl grid-rows-[auto_minmax(0,1fr)_auto] gap-0 overflow-hidden p-0"
            >
                <DialogHeader class="border-b px-5 py-4"
                    ><DialogTitle>{{
                        editId === null ? '新增公告' : '编辑公告'
                    }}</DialogTitle
                    ><DialogDescription class="sr-only"
                        >设置公告内容与展示状态</DialogDescription
                    ></DialogHeader
                >
                <form
                    id="announcement-form"
                    class="min-h-0 space-y-4 overflow-y-auto p-5"
                    @submit.prevent="save"
                >
                    <div
                        v-if="detailLoading"
                        class="flex items-center gap-2 py-8"
                    >
                        <Spinner />正在加载公告…
                    </div>
                    <Alert v-else-if="detailError" variant="destructive"
                        ><AlertDescription
                            >{{ detailError
                            }}<Button variant="outline" @click="loadDetail"
                                >重试</Button
                            ></AlertDescription
                        ></Alert
                    >
                    <template v-else>
                        <fieldset
                            :disabled="busy"
                            class="space-y-5 rounded-xl border bg-muted/10 p-4 md:p-5"
                        >
                            <h3 class="section-heading">基础信息</h3>
                            <div class="form-row">
                                <Label for="anno-title">标题：</Label
                                ><Input
                                    id="anno-title"
                                    v-model="form.title"
                                    placeholder="请输入标题"
                                />
                            </div>
                            <div class="form-row">
                                <Label>形式：</Label>
                                <div
                                    class="flex gap-2"
                                    role="group"
                                    aria-label="公告形式"
                                >
                                    <Button
                                        type="button"
                                        :variant="
                                            !form.external
                                                ? 'default'
                                                : 'outline'
                                        "
                                        :aria-pressed="!form.external"
                                        @click="form.external = false"
                                        >显示内容</Button
                                    ><Button
                                        type="button"
                                        :variant="
                                            form.external
                                                ? 'default'
                                                : 'outline'
                                        "
                                        :aria-pressed="form.external"
                                        @click="form.external = true"
                                        >跳转链接</Button
                                    >
                                </div>
                            </div>
                            <div
                                v-if="!form.external"
                                class="form-row items-start"
                            >
                                <Label for="anno-content" class="pt-2"
                                    >内容：</Label
                                ><Textarea
                                    id="anno-content"
                                    v-model="form.content"
                                    class="min-h-60 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    placeholder="请输入html内容"
                                />
                            </div>
                            <div v-else class="form-row">
                                <Label for="anno-url">链接：</Label
                                ><Input
                                    id="anno-url"
                                    v-model="form.url"
                                    placeholder="https://"
                                />
                            </div>
                            <div class="form-row">
                                <Label for="anno-sort">排序：</Label
                                ><Input
                                    id="anno-sort"
                                    v-model="form.sort"
                                    class="max-w-56"
                                    inputmode="numeric"
                                />
                            </div>
                        </fieldset>
                        <fieldset
                            :disabled="busy"
                            class="space-y-5 rounded-xl border bg-muted/10 p-4 md:p-5"
                        >
                            <h3 class="section-heading">展示状态</h3>
                            <div class="form-row">
                                <Label for="anno-show">显示：</Label>
                                <div class="flex items-center gap-3">
                                    <Switch
                                        id="anno-show"
                                        v-model:checked="form.show"
                                        :disabled="busy"
                                    /><span class="text-muted-foreground">{{
                                        form.show ? '已显示' : '已隐藏'
                                    }}</span>
                                </div>
                            </div>
                            <div class="form-row">
                                <Label for="anno-popup">弹窗：</Label>
                                <div class="flex items-center gap-3">
                                    <Switch
                                        id="anno-popup"
                                        v-model:checked="form.popup"
                                        :disabled="busy"
                                    /><span class="text-muted-foreground">{{
                                        form.popup ? '弹窗展示' : '普通展示'
                                    }}</span>
                                </div>
                            </div>
                            <div class="form-row">
                                <Label>样式：</Label>
                                <div class="flex gap-2">
                                    <Label
                                        class="flex cursor-pointer items-center gap-2 rounded-md border bg-background p-3"
                                        ><Checkbox
                                            v-model="form.red"
                                            :disabled="busy"
                                        />加红</Label
                                    ><Label
                                        class="flex cursor-pointer items-center gap-2 rounded-md border bg-background p-3"
                                        ><Checkbox
                                            v-model="form.bold"
                                            :disabled="busy"
                                        />加粗</Label
                                    >
                                </div>
                            </div>
                        </fieldset>
                    </template>
                    <Alert v-if="saveError" variant="destructive"
                        ><AlertDescription>{{
                            saveError
                        }}</AlertDescription></Alert
                    >
                </form>
                <DialogFooter class="border-t px-5 py-4"
                    ><Button
                        variant="outline"
                        :disabled="busy"
                        @click="editorOpen = false"
                        >取消</Button
                    ><Button
                        type="submit"
                        form="announcement-form"
                        :disabled="busy || detailLoading || !!detailError"
                        ><Spinner v-if="busy" />确定</Button
                    ></DialogFooter
                >
            </DialogScrollContent>
        </Dialog>
        <Dialog
            :open="deleteOpen"
            @update:open="
                (value) => {
                    if (!busy) deleteOpen = value;
                }
            "
            ><DialogScrollContent
                ><DialogHeader
                    ><DialogTitle>删除公告</DialogTitle
                    ><DialogDescription
                        >确定删除选中的
                        {{ deleteIds.length }} 条公告？</DialogDescription
                    ></DialogHeader
                ><Alert v-if="deleteError" variant="destructive"
                    ><AlertDescription>{{
                        deleteError
                    }}</AlertDescription></Alert
                ><DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="busy"
                        @click="deleteOpen = false"
                        >取消</Button
                    ><Button
                        variant="destructive"
                        :disabled="busy"
                        @click="mutate([...deleteIds])"
                        ><Spinner v-if="busy" />确认删除</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
    </div>
</template>

<style scoped>
.announcement-table :deep(table) {
    min-width: 1430px;
}
.form-row {
    display: grid;
    grid-template-columns: 100px minmax(0, 1fr);
    align-items: center;
    gap: 16px;
}
.form-row > label {
    justify-content: flex-end;
}
.form-row.items-start {
    align-items: start;
}
.section-heading {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}
.section-heading::before {
    content: '';
    width: 4px;
    height: 18px;
    border-radius: 2px;
    background: var(--primary);
}
@media (max-width: 640px) {
    .form-row {
        grid-template-columns: minmax(0, 1fr);
        gap: 8px;
    }
    .form-row > label {
        justify-content: flex-start;
    }
}
</style>
