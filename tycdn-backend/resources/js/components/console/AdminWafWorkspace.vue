<script setup lang="ts">
import { ChevronDown, Plus, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import { Button } from '@/components/ui/button';
import CheckboxField from '@/components/ui/checkbox/CheckboxField.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { updateAdminAcl } from '@/lib/adminModulesApi';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const emit = defineEmits<{
    create: [];
    manage: [row: CdnflyRecord];
    delete: [row: CdnflyRecord];
}>();
const rows = ref<CdnflyRecord[]>([]),
    total = ref(0),
    page = ref(1),
    loading = ref(false),
    busy = ref(false),
    error = ref(''),
    selected = ref<number[]>([]);
const filters = reactive({ name: '', scope: '', enable: '' });
const pages = computed(() => Math.max(1, Math.ceil(total.value / 10)));

const allSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selected.value.includes(Number(row.id))),
);
const enabled = (value: unknown) =>
    value === true || value === 1 || value === '1';
const message = (e: unknown) =>
    e instanceof Error ? e.message : '操作失败，请重试';
function interval(value: unknown) {
    const minutes = Number(value || 360);

    return minutes % 1440 === 0
        ? `每 ${minutes / 1440} 天`
        : minutes % 60 === 0
          ? `每 ${minutes / 60} 小时`
          : `每 ${minutes} 分钟`;
}
let request = 0;
onMounted(() => void load());
onUnmounted(() => request++);
defineExpose({ refresh: () => load() });
async function load(target = page.value) {
    const token = ++request;
    loading.value = true;
    error.value = '';
    selected.value = [];
    page.value = target;

    try {
        const query: Record<string, string | number> = {
            page: target,
            limit: 10,
            system_key: '',
        };

        for (const [key, value] of Object.entries(filters)) {
            if (value !== '') {
                query[key] = value.trim();
            }
        }

        const response = await apiRequest(
            `/api/admin/all-acls?${new URLSearchParams(Object.entries(query).map(([key, value]) => [key, String(value)]))}`,
        );

        if (token !== request) {
            return;
        }

        const raw = extractCdnflyRows(response);
        rows.value = raw.filter((row) => row.system_key !== 'nday');
        total.value =
            raw.length === rows.value.length
                ? extractCdnflyTotal(response, raw.length)
                : rows.value.length;

        if (page.value > pages.value) {
            void load(pages.value);
        }
    } catch (e) {
        if (token === request) {
            error.value = message(e);
            rows.value = [];
            total.value = 0;
        }
    } finally {
        if (token === request) {
            loading.value = false;
        }
    }
}
function toggle(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((value) => value !== id)
        : [...selected.value, id];
}
async function manage(row: CdnflyRecord) {
    busy.value = true;

    try {
        const response = await apiRequest<CdnflyRecord>(
            `/api/admin/acls/${row.id}`,
        );
        const detail =
            response.data &&
            typeof response.data === 'object' &&
            !Array.isArray(response.data)
                ? (response.data as CdnflyRecord)
                : response;

        if (!detail.id || detail.data === undefined) {
            throw new Error('无法读取完整规则库，请重试');
        }

        emit('manage', detail);
    } catch (e) {
        toast.error(message(e));
    } finally {
        busy.value = false;
    }
}
async function batch(enable: number) {
    if (!selected.value.length || busy.value) {
        return;
    }

    busy.value = true;
    const failed: number[] = [];
    let failure = '';

    for (const id of [...selected.value]) {
        try {
            await updateAdminAcl(id, { enable });
        } catch (e) {
            failed.push(id);
            failure = message(e);
        }
    }

    await load();
    selected.value = failed.filter((id) =>
        rows.value.some((row) => Number(row.id) === id),
    );

    if (failed.length) {
        error.value = `${failed.length} 个规则库操作失败：${failure}`;
        toast.error(error.value);
    } else {
        toast.success('操作成功');
    }

    busy.value = false;
}
async function updateSubscriptions() {
    if (!selected.value.length || busy.value) {
        return;
    }

    const invalid = rows.value.filter(
        (row) =>
            selected.value.includes(Number(row.id)) &&
            (row.scope !== 'global' ||
                !enabled(row.enable) ||
                !enabled(row.subscribe_enable) ||
                !String(row.subscribe_url || '').trim()),
    );

    if (invalid.length) {
        toast.error(
            `以下规则库不可更新：${invalid.map((row) => row.name || row.id).join('、')}`,
        );

        return;
    }

    busy.value = true;

    try {
        const response = await apiRequest<CdnflyRecord>(
            '/api/admin/acls/update-subscription',
            {
                method: 'POST',
                body: JSON.stringify({ ids: [...selected.value] }),
            },
        );
        const result = (response.data || response) as CdnflyRecord;
        const summary = `订阅更新完成：变更 ${Number(result.changed_count || 0)}，未变化 ${Number(result.unchanged_count || 0)}，失败 ${Number(result.failed_count || 0)}`;

        if (Number(result.failed_count || 0)) {
            toast.warning(summary);
        } else {
            toast.success(summary);
        }

        await load();
    } catch (e) {
        error.value = message(e);
        toast.error(error.value);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <section
        class="console-admin-waf-workspace waf-workspace"
        :aria-busy="loading || busy"
    >
        <header class="waf-heading">
            <div class="waf-title">
                <h1 data-typography="page-title">WAF 规则库</h1>
                <span class="pill primary">{{ total }} 个</span>
            </div>
            <Button
                variant="default"
                type="button"
                data-slot="console-action"
                class="primary-button"
                :disabled="busy"
                @click="emit('create')"
            >
                <Plus />新增规则库
            </Button>
        </header>
        <div class="waf-toolbar">
            <form class="waf-filters" @submit.prevent="load(1)">
                <div data-slot="console-input-group" class="name-search">
                    <Input
                        v-model="filters.name"
                        aria-label="规则库名称"
                        placeholder="规则库名称"
                        :disabled="busy"
                    /><Button
                        variant="default"
                        type="submit"
                        data-slot="console-action"
                        class="primary-button"
                        :disabled="busy"
                    >
                        查询
                    </Button>
                </div>
                <SelectField
                    v-model="filters.scope"
                    aria-label="规则范围"
                    :disabled="busy"
                    @change="load(1)"
                >
                    <SelectOption value="">规则范围</SelectOption>
                    <SelectOption value="global">全局</SelectOption>
                    <SelectOption value="user">用户</SelectOption>
                </SelectField>
                <SelectField
                    v-model="filters.enable"
                    aria-label="启用状态"
                    :disabled="busy"
                    @change="load(1)"
                >
                    <SelectOption value="">启用状态</SelectOption>
                    <SelectOption value="1">启用</SelectOption>
                    <SelectOption value="0">停用</SelectOption>
                </SelectField>
            </form>
            <div class="waf-actions">
                <Button
                    variant="outline"
                    type="button"
                    data-slot="console-action"
                    :disabled="!selected.length || busy || loading"
                    @click="updateSubscriptions"
                >
                    <RefreshCw :class="{ spin: busy }" />立即更新订阅
                </Button>
                <DropdownMenu
                    ><DropdownMenuTrigger as-child
                        ><Button
                            variant="outline"
                            type="button"
                            data-slot="console-action"
                            :disabled="!selected.length || busy || loading"
                        >
                            批量操作<ChevronDown /></Button></DropdownMenuTrigger
                    ><DropdownMenuContent
                        class="console-admin-waf-workspace"
                        align="end"
                        ><DropdownMenuItem @select="batch(1)"
                            >启用</DropdownMenuItem
                        ><DropdownMenuItem @select="batch(0)"
                            >禁用</DropdownMenuItem
                        ></DropdownMenuContent
                    ></DropdownMenu
                >
                <Button
                    variant="outline"
                    type="button"
                    data-slot="console-action"
                    :disabled="loading || busy"
                    @click="load()"
                >
                    <RefreshCw :class="{ spin: loading }" />刷新
                </Button>
            </div>
        </div>
        <div v-if="error" role="alert" class="waf-error">{{ error }}</div>
        <div class="waf-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="check">
                            <CheckboxField
                                aria-label="选择全部规则库"
                                :checked="allSelected"
                                :disabled="loading || busy || !rows.length"
                                @change="
                                    selected = allSelected
                                        ? []
                                        : rows.map((row) => Number(row.id))
                                "
                            />
                        </th>
                        <th>ID</th>
                        <th class="name-column">名称</th>
                        <th>类型</th>
                        <th>范围</th>
                        <th>归属</th>
                        <th>状态</th>
                        <th>订阅</th>
                        <th>更新时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="String(row.id)">
                        <td class="check">
                            <CheckboxField
                                :aria-label="`选择规则库 ${row.name}`"
                                :checked="selected.includes(Number(row.id))"
                                :disabled="loading || busy"
                                @change="toggle(Number(row.id))"
                            />
                        </td>
                        <td>{{ row.id }}</td>
                        <td>
                            <div
                                class="rule-name"
                                :title="String(row.name || '')"
                            >
                                {{ row.name || '-' }}
                            </div>
                            <div
                                class="muted description"
                                :title="String(row.des || '')"
                            >
                                {{ row.des || '未填写备注' }}
                            </div>
                        </td>
                        <td><span class="pill">普通</span></td>
                        <td>
                            <span
                                class="pill"
                                :class="{ primary: row.scope === 'global' }"
                                >{{
                                    row.scope === 'global' ? '全局' : '用户'
                                }}</span
                            >
                        </td>
                        <td>
                            {{
                                row.scope === 'global'
                                    ? '全局规则'
                                    : row.username ||
                                      (row.uid ? `UID ${row.uid}` : '当前用户')
                            }}
                        </td>
                        <td>
                            <span
                                class="pill"
                                :class="{ success: enabled(row.enable) }"
                                >{{
                                    enabled(row.enable) ? '启用' : '停用'
                                }}</span
                            >
                        </td>
                        <td>
                            <div
                                v-if="enabled(row.subscribe_enable)"
                                class="subscription"
                            >
                                <span
                                    class="pill"
                                    :class="{
                                        success:
                                            row.subscribe_status === 'success',
                                        danger:
                                            row.subscribe_status === 'failed',
                                    }"
                                    >{{
                                        row.subscribe_status === 'success'
                                            ? '成功'
                                            : row.subscribe_status === 'failed'
                                              ? '失败'
                                              : '待更新'
                                    }}</span
                                ><span
                                    class="muted description"
                                    :title="String(row.subscribe_version || '')"
                                    >{{
                                        row.subscribe_version || '未同步'
                                    }}</span
                                ><span class="muted">{{
                                    interval(row.subscribe_interval_minutes)
                                }}</span>
                            </div>
                            <span v-else class="muted">未订阅</span>
                        </td>
                        <td class="muted">
                            {{ row.update_at2 || row.update_at || '-' }}
                        </td>
                        <td>
                            <div class="row-actions">
                                <Button
                                    variant="link"
                                    size="inline"
                                    type="button"
                                    data-slot="console-link"
                                    :disabled="busy || loading"
                                    @click="manage(row)"
                                >
                                    编辑</Button
                                ><Button
                                    variant="link"
                                    size="inline"
                                    type="button"
                                    data-slot="console-link"
                                    v-if="!row.system_key"
                                    class="delete-link"
                                    :disabled="busy || loading"
                                    @click="emit('delete', row)"
                                >
                                    删除
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="10" class="empty">
                            {{
                                loading
                                    ? '加载中…'
                                    : error
                                      ? '加载失败，请刷新重试'
                                      : '暂无数据'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <ConsolePagination
            aria-label="规则库分页"
            :total="total"
            :page="page"
            :previous-disabled="page <= 1 || loading || busy"
            :next-disabled="page >= pages || loading || busy"
            @previous="load(page - 1)"
            @next="load(page + 1)"
        />
    </section>
</template>
