<script setup lang="ts">
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Plus,
    RefreshCw,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
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
const pageNumbers = computed(() =>
    Array.from(
        { length: Math.min(5, pages.value) },
        (_, i) => Math.max(1, Math.min(page.value - 2, pages.value - 4)) + i,
    ),
);
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
    <section class="waf-workspace" :aria-busy="loading || busy">
        <header class="waf-heading">
            <div class="waf-title">
                <h1>WAF 规则库</h1>
                <span class="pill primary">{{ total }} 个</span>
            </div>
            <button
                class="primary-button"
                :disabled="busy"
                @click="emit('create')"
            >
                <Plus />新增规则库
            </button>
        </header>
        <div class="waf-toolbar">
            <form class="waf-filters" @submit.prevent="load(1)">
                <div class="name-search">
                    <Input
                        v-model="filters.name"
                        aria-label="规则库名称"
                        placeholder="规则库名称"
                        :disabled="busy"
                    /><button class="primary-button" :disabled="busy">
                        查询
                    </button>
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
                <button
                    :disabled="!selected.length || busy || loading"
                    @click="updateSubscriptions"
                >
                    <RefreshCw :class="{ spin: busy }" />立即更新订阅
                </button>
                <DropdownMenu
                    ><DropdownMenuTrigger as-child
                        ><button
                            :disabled="!selected.length || busy || loading"
                        >
                            批量操作<ChevronDown /></button></DropdownMenuTrigger
                    ><DropdownMenuContent align="end"
                        ><DropdownMenuItem @select="batch(1)"
                            >启用</DropdownMenuItem
                        ><DropdownMenuItem @select="batch(0)"
                            >禁用</DropdownMenuItem
                        ></DropdownMenuContent
                    ></DropdownMenu
                >
                <button :disabled="loading || busy" @click="load()">
                    <RefreshCw :class="{ spin: loading }" />刷新
                </button>
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
                                <button
                                    :disabled="busy || loading"
                                    @click="manage(row)"
                                >
                                    编辑</button
                                ><button
                                    v-if="!row.system_key"
                                    class="delete-link"
                                    :disabled="busy || loading"
                                    @click="emit('delete', row)"
                                >
                                    删除
                                </button>
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
        <nav aria-label="规则库分页" class="waf-pager">
            <span>共 {{ total }} 条</span
            ><button
                aria-label="上一页"
                :disabled="page <= 1 || loading || busy"
                @click="load(page - 1)"
            >
                <ChevronLeft /></button
            ><button
                v-for="number in pageNumbers"
                :key="number"
                :class="{ current: page === number }"
                :aria-current="page === number ? 'page' : undefined"
                :disabled="loading || busy"
                @click="load(number)"
            >
                {{ number }}</button
            ><button
                aria-label="下一页"
                :disabled="page >= pages || loading || busy"
                @click="load(page + 1)"
            >
                <ChevronRight />
            </button>
        </nav>
    </section>
</template>

<style scoped>
.waf-workspace {
    --line: #e9eff8;
    --ink: #344766;
    --muted: #8994a8;
    color: var(--ink);
    background: white;
    border-radius: 8px;
    padding: 10px;
    font-size: 12px;
    min-width: 0;
}
.waf-heading,
.waf-title,
.waf-toolbar,
.waf-filters,
.waf-actions,
.row-actions,
.waf-pager {
    display: flex;
    align-items: center;
    gap: 8px;
}
.waf-heading {
    justify-content: space-between;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--line);
}
h1 {
    font-size: 14px;
    font-weight: 600;
    color: #142b49;
}
.waf-toolbar {
    justify-content: space-between;
    flex-wrap: wrap;
    padding: 11px 0;
}
.waf-filters,
.waf-actions {
    flex-wrap: wrap;
}
button,
input,
:deep([data-slot='select-trigger']) {
    font: inherit;
}
button,
:deep([data-slot='select-trigger']),
input:not([type='checkbox']) {
    height: 28px;
    border: 1px solid #d7dce5;
    border-radius: 3px;
    background: transparent;
}
button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    padding: 0 12px;
    white-space: nowrap;
    cursor: pointer;
}
button svg {
    width: 12px;
    height: 12px;
}
button:hover:not(:disabled) {
    color: #2d8cf0;
    border-color: #2d8cf0;
}
button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.primary-button {
    background: #2d8cf0;
    border-color: #2d8cf0;
    color: white;
}
.primary-button:hover:not(:disabled) {
    color: white;
    background: #57a3f3;
}
input:not([type='checkbox']) {
    padding: 0 7px;
    min-width: 0;
}
input::placeholder {
    color: #b7bfcc;
}
:deep([data-slot='checkbox']) {
    accent-color: #2d8cf0;
    width: 14px;
    height: 14px;
    vertical-align: middle;
}
.name-search {
    display: flex;
    width: 217px;
}
.name-search input {
    width: 165px;
    border-radius: 3px 0 0 3px;
}
.name-search button {
    border-radius: 0 3px 3px 0;
}
:deep([data-slot='select-trigger']) {
    width: 117px;
    padding: 0 7px;
}
input:focus,
:deep([data-slot='select-trigger']):focus {
    outline: 1px solid #2d8cf0;
}
.waf-table-wrap {
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    min-width: 1000px;
}
th {
    height: 32px;
    background: #fafcff;
    font-weight: 500;
}
th,
td {
    padding: 8px;
    border-bottom: 1px solid var(--line);
}
th.check,
td.check {
    width: 40px;
    text-align: center;
}
.name-column {
    width: 25%;
}
.rule-name,
.description {
    max-width: 270px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.description {
    font-size: 11px;
    margin-top: 4px;
}
.muted {
    color: var(--muted);
}
.pill {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 12px;
    background: #f3f5f9;
    font-size: 11px;
    line-height: 16px;
    white-space: nowrap;
}
.pill.primary {
    color: #2d8cf0;
    background: #eff7ff;
}
.pill.success {
    color: #00ba78;
    background: #edf9f2;
}
.pill.danger {
    color: #ed4014;
    background: #fff1ee;
}
.subscription {
    display: flex;
    align-items: flex-start;
    flex-direction: column;
    gap: 3px;
}
.row-actions button {
    border: 0;
    color: #2d8cf0;
    padding: 0;
    height: 22px;
}
.row-actions .delete-link {
    color: #ed4014;
}
.empty {
    text-align: center;
    height: 40px;
    color: var(--muted);
}
.waf-pager {
    justify-content: flex-end;
    padding-top: 13px;
    gap: 4px;
}
.waf-pager span {
    margin-right: 6px;
}
.waf-pager button {
    padding: 0;
    min-width: 28px;
}
.waf-pager .current {
    border-color: #2d8cf0;
    color: #2d8cf0;
}
.waf-error {
    padding: 10px;
    color: #d9363e;
    background: #fff1f0;
    margin-bottom: 8px;
    border-radius: 4px;
}
.spin {
    animation: waf-spin 1s linear infinite;
}
@keyframes waf-spin {
    to {
        transform: rotate(360deg);
    }
}
:global(.dark .waf-workspace) {
    --line: #303a4a;
    --ink: #d8e1ef;
    --muted: #94a3b8;
    background: #151e2b;
}
:global(.dark .waf-workspace h1) {
    color: #e6eef9;
}
:global(.dark .waf-workspace th) {
    background: #1c2738;
}
:global(.dark .waf-workspace .pill) {
    background: #263449;
}
:global(.dark .waf-workspace :deep([data-slot='select-trigger'])) {
    background: #151e2b;
}
:global(.dark .waf-workspace .pill.success) {
    background: #123d33;
    color: #45d7a5;
}
:global(.dark .waf-workspace .pill.primary) {
    background: #173756;
    color: #7db9ff;
}
:global(.dark .waf-workspace .pill.danger) {
    background: #45262c;
    color: #fda4af;
}
:global(.dark .waf-workspace .waf-error) {
    background: #45262c;
    color: #fda4af;
}
@media (max-width: 640px) {
    .waf-toolbar {
        gap: 10px;
    }
    .waf-filters {
        width: 100%;
    }
    .name-search {
        width: 100%;
    }
    .name-search input {
        flex: 1;
        width: auto;
    }
    .waf-actions {
        justify-content: flex-end;
        width: 100%;
    }
    .waf-pager {
        flex-wrap: wrap;
    }
}
</style>
