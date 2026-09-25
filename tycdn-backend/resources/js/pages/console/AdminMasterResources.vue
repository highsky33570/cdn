<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfigFields from '@/components/console/ConfigFields.vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import RecordDetails from '@/components/console/RecordDetails.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRecord } from '@/lib/cdnflyResponse';
import { configRecord, configPatch } from '@/lib/configEditor';
import type { ConfigObject } from '@/lib/configEditor';
import { masterResources } from '@/lib/masterResources';
import type { CdnflyListData, CdnflyRecord } from '@/lib/sharedTypes';

const props = withDefaults(
    defineProps<{ resource: string; scope?: 'admin' | 'user' }>(),
    { scope: 'admin' },
);
const definition = computed(() => masterResources[props.resource]);
const endpoint = computed(() =>
    props.scope === 'user'
        ? `/api/cdn/proxy/v1/${props.resource}`
        : `/api/admin/workspace/${props.resource}`,
);
const table = ref<InstanceType<typeof ConsoleDataTable>>();
const draft = ref<ConfigObject>({}),
    original = ref<ConfigObject>({}),
    editingId = ref<number | null>(null);
const open = ref(false),
    saving = ref(false),
    error = ref('');
const fields = computed(() =>
    (!editingId.value && definition.value.createFields
        ? definition.value.createFields
        : (definition.value.fields ?? [])
    ).filter((field) => !l2ConfigFilter || field.key !== 'l2_config_id'),
);
const recent = ref('1h');
const detail = ref<CdnflyRecord | null>(null);
const showDetail = ref(false);
const usesTimeRange = computed(() => props.resource === 'attack-log');
const nodeFilter =
    typeof window !== 'undefined'
        ? new URLSearchParams(window.location.search).get('node_id')
        : null;
const requestedL2Config =
    typeof window !== 'undefined'
        ? new URLSearchParams(window.location.search).get('l2_config_id')
        : null;
const l2ConfigFilter =
    props.resource === 'l2-nodes' &&
    requestedL2Config &&
    /^[1-9]\d*$/.test(requestedL2Config)
        ? Number(requestedL2Config)
        : null;
const params = computed<Record<string, string | number>>(() => {
    const query: Record<string, string | number> = {
        ...definition.value.params,
        ...(nodeFilter ? { node_id: nodeFilter } : {}),
        ...(l2ConfigFilter ? { l2_config_id: l2ConfigFilter } : {}),
    };

    if (usesTimeRange.value) {
        const end = new Date(),
            start = new Date(
                end.getTime() -
                    (recent.value === '7d'
                        ? 168
                        : recent.value === '24h'
                          ? 24
                          : 1) *
                        3600000,
            );
        const date = (value: Date) =>
            `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')} ${String(value.getHours()).padStart(2, '0')}:${String(value.getMinutes()).padStart(2, '0')}:${String(value.getSeconds()).padStart(2, '0')}`;
        query.start = date(start);
        query.end = date(end);
    }

    return query;
});
const fetchRows = (query: Record<string, string | number>) =>
    apiRequest<CdnflyListData>(
        `${endpoint.value}?${new URLSearchParams(Object.entries(query).map(([k, v]) => [k, String(v)]))}`,
    );
async function edit(row?: CdnflyRecord) {
    error.value = '';
    editingId.value = row ? Number(row.id) : null;

    try {
        const record =
            row && definition.value.detail
                ? extractCdnflyRecord(
                      await apiRequest(`${endpoint.value}/${row.id}`),
                  )
                : (row ?? definition.value.defaults ?? {});
        original.value = configRecord(record ?? {});
        draft.value = JSON.parse(JSON.stringify(original.value));

        if (!row && l2ConfigFilter) {
            draft.value.l2_config_id = l2ConfigFilter;
        }

        open.value = true;
    } catch (e) {
        toast.error(e instanceof Error ? e.message : '加载失败');
    }
}
async function save() {
    error.value = '';
    const missing = fields.value.find(
        (f) =>
            f.required &&
            (draft.value[f.key] === null ||
                draft.value[f.key] === undefined ||
                String(draft.value[f.key]).trim() === ''),
    );

    if (missing) {
        error.value = `请填写${missing.label}`;

        return;
    }

    let payload: ConfigObject | ConfigObject[] = editingId.value
        ? configPatch(
              original.value,
              draft.value,
              fields.value.map((f) => f.key),
          )
        : Object.fromEntries(
              [
                  ...fields.value.map((f) => f.key),
                  ...Object.keys(definition.value.defaults ?? {}),
              ]
                  .filter((k) => draft.value[k] !== undefined)
                  .map((k) => [k, draft.value[k]]),
          );

    if (!Object.keys(payload).length) {
        open.value = false;

        return;
    }

    // CDNfly creates L2 node bindings as a collection; single edits use the ID endpoint.
    if (props.resource === 'l2-nodes' && !editingId.value) {
        if (l2ConfigFilter) {
            payload.l2_config_id = l2ConfigFilter;
        }

        payload = [payload];
    }

    saving.value = true;

    try {
        await apiRequest(
            `${endpoint.value}${editingId.value ? `/${editingId.value}` : ''}`,
            {
                method: editingId.value ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            },
        );
        open.value = false;
        toast.success('保存成功');
        await table.value?.reload();
    } catch (e) {
        error.value = e instanceof Error ? e.message : '保存失败';
    } finally {
        saving.value = false;
    }
}
async function remove(row: CdnflyRecord) {
    if (!window.confirm(`确认删除「${row.name ?? row.title ?? row.id}」？`)) {
        return;
    }

    try {
        await apiRequest(`${endpoint.value}/${row.id}`, { method: 'DELETE' });
        await table.value?.reload();
        toast.success('已删除');
    } catch (e) {
        toast.error(e instanceof Error ? e.message : '删除失败');
    }
}
async function cancelTask(row: CdnflyRecord) {
    if (!window.confirm(`确认取消任务 #${row.id}？`)) {
        return;
    }

    try {
        await apiRequest(`${endpoint.value}/${row.id}`, {
            method: 'PUT',
            body: JSON.stringify({ enable: 0 }),
        });
        await table.value?.reload();
        toast.success('已取消');
    } catch (e) {
        toast.error(e instanceof Error ? e.message : '取消失败');
    }
}
</script>

<template>
    <div class="console-page grid gap-5 p-4 md:p-6">
        <nav
            v-if="definition.tabs"
            class="console-tabs flex flex-wrap gap-2"
            aria-label="管理分类"
        >
            <Link
                v-for="tab in definition.tabs"
                :key="tab"
                :href="`/console/admin/workspace/${tab}`"
                class="rounded-md px-4 py-2 text-sm font-medium"
                :class="
                    resource === tab
                        ? 'bg-accent text-primary'
                        : 'text-muted-foreground hover:bg-muted'
                "
                >{{ masterResources[tab].title }}</Link
            >
        </nav>
        <ConsoleDataTable
            ref="table"
            :title="definition.title"
            :columns="definition.columns"
            :fetch-fn="fetchRows"
            :search-params="params"
            :search-key="definition.searchKey ?? 'search'"
            :page-size="10"
            :page-size-options="[10, 30, 100]"
        >
            <template #toolbar-actions>
                <SelectField
                    v-if="usesTimeRange"
                    v-model="recent"
                    class="h-9 rounded-md border bg-background px-3"
                    aria-label="时间范围"
                >
                    <SelectOption value="1h">近一小时</SelectOption>
                    <SelectOption value="24h">近一天</SelectOption>
                    <SelectOption value="7d">近七天</SelectOption>
                </SelectField>
                <Button v-if="!definition.readOnly" @click="edit()">{{
                    resource === 'user-traffic-packages' ? '分配流量包' : '新增'
                }}</Button>
            </template>
            <template #row-actions="{ row }">
                <Button
                    v-if="definition.readOnly"
                    size="sm"
                    variant="ghost"
                    @click="
                        detail = row;
                        showDetail = true;
                    "
                    >详情</Button
                >
                <template v-if="!definition.readOnly"
                    ><Button size="sm" variant="ghost" @click="edit(row)"
                        >编辑</Button
                    ><Button
                        size="sm"
                        variant="ghost"
                        class="text-destructive"
                        @click="remove(row)"
                        >删除</Button
                    ></template
                >
                <Button
                    v-if="
                        resource === 'tasks' &&
                        Number(row.enable) !== 0 &&
                        !['done', 'failed'].includes(String(row.state))
                    "
                    size="sm"
                    variant="outline"
                    @click="cancelTask(row)"
                    >取消任务</Button
                >
            </template>
        </ConsoleDataTable>
        <Dialog v-model:open="open"
            ><DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
                ><DialogHeader
                    ><DialogTitle
                        >{{ editingId ? '编辑' : '新增' }} ·
                        {{ definition.title }}</DialogTitle
                    ><DialogDescription
                        >保存后应用到主控。</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-5" @submit.prevent="save">
                    <ConfigFields
                        v-model="draft"
                        :fields="fields"
                        :disabled="saving"
                    />
                    <p
                        v-if="error"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ error }}
                    </p>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="saving"
                            @click="open = false"
                            >取消</Button
                        ><Button type="submit" :disabled="saving">{{
                            saving ? '保存中…' : '保存'
                        }}</Button></DialogFooter
                    >
                </form>
            </DialogContent></Dialog
        >
        <Dialog v-model:open="showDetail"
            ><DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-3xl"
                ><DialogHeader
                    ><DialogTitle>{{ definition.title }} · 详情</DialogTitle
                    ><DialogDescription
                        >当前记录的完整信息</DialogDescription
                    ></DialogHeader
                ><RecordDetails v-if="detail" :value="detail" /></DialogContent
        ></Dialog>
    </div>
</template>
