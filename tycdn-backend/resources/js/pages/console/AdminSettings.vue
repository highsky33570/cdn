<script setup lang="ts">
import {
    AlertCircle,
    Pencil,
    RefreshCw,
    Save,
    Search,
    Settings,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    getAdminConfigs,
    getAdminRegisterInfo,
    upsertAdminConfig,
} from '@/lib/adminModulesApi';

/**
 * One row of /v1/configs. CDNfly returns a *list* of settings, each carrying its
 * own scope and type — not a flat key/value object.
 */
type ConfigRow = {
    /** name alone is not unique — the same setting exists per scope. */
    key: string;
    /** CDNfly keys a config on scope + type + name. There is no row id. */
    scopeName: string;
    scopeId: number;
    name: string;
    value: string;
    type: string;
    scope: string;
    enabled: boolean;
    updatedAt: string;
};

/** One row of /v1/register-info: which identifiers signup asks for. */
type RegisterRow = {
    field: string;
    label: string;
    required: boolean;
    verified: boolean;
};

const FIELD_LABELS: Record<string, string> = {
    username: '用户名',
    email: '邮箱',
    phone: '手机号',
    captcha: '图形验证码',
};

const loading = ref(false);
const errorMessage = ref('');
const rawConfigs = ref<unknown>(null);
const rawRegister = ref<unknown>(null);

const filters = reactive({ search: '' });
const expanded = ref(new Set<string>());

function toggleExpanded(key: string): void {
    // reassigned rather than mutated: Vue does not track Set mutations
    const next = new Set(expanded.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    expanded.value = next;
}

const editing = ref<ConfigRow | null>(null);
const editValue = ref('');
const editSaving = ref(false);
const editError = ref('');

function openEdit(row: ConfigRow): void {
    editing.value = row;
    editValue.value = row.value;
    editError.value = '';
}

async function saveOne(): Promise<void> {
    const row = editing.value;

    if (!row) {
        return;
    }

    editSaving.value = true;
    editError.value = '';

    try {
        await upsertAdminConfig({
            name: row.name,
            type: row.type,
            scope_name: row.scopeName || undefined,
            scope_id: row.scopeId,
            value: editValue.value,
            enable: row.enabled ? 1 : 0,
        });
        toast.success(`${row.name} 已保存`);
        editing.value = null;
        await loadSettings();
    } catch (error) {
        editError.value = error instanceof Error ? error.message : '请求失败';
    } finally {
        editSaving.value = false;
    }
}

onMounted(() => {
    void loadSettings();
});

/**
 * CDNfly answers {data, msg, code}. Reading fields off the envelope is what made
 * this page render "data / msg / code" as though they were the settings
 * themselves — the same mistake the account pages used to make.
 */
function unwrap(payload: unknown): unknown {
    if (payload && typeof payload === 'object' && 'data' in payload) {
        return (payload as { data: unknown }).data;
    }

    return payload;
}

const configRows = computed<ConfigRow[]>(() => {
    const data = unwrap(rawConfigs.value);

    if (!Array.isArray(data)) {
        return [];
    }

    return data.filter(isRecord).map((row) => ({
        key: `${text(row.name)}@${text(row.scope_name)}@${text(row.scope_id)}`,
        name: text(row.name),
        value: text(row.value),
        type: text(row.type),
        scope: text(row.scope_name) || text(row.entity_name) || '-',
        scopeName: text(row.scope_name),
        scopeId: Number(row.scope_id) || 0,
        // enable is 1/0 upstream, not a boolean
        enabled: row.enable === 1 || row.enable === '1' || row.enable === true,
        updatedAt: text(row.update_at) || text(row.create_at),
    }));
});

const visibleConfigRows = computed(() => {
    const needle = filters.search.trim().toLowerCase();

    if (needle === '') {
        return configRows.value;
    }

    return configRows.value.filter(
        (row) =>
            row.name.toLowerCase().includes(needle) ||
            row.value.toLowerCase().includes(needle),
    );
});

const registerRows = computed<RegisterRow[]>(() => {
    const data = unwrap(rawRegister.value);

    if (!isRecord(data)) {
        return [];
    }

    return Object.entries(data)
        .filter(([, value]) => isRecord(value))
        .map(([field, value]) => {
            const entry = value as Record<string, unknown>;

            return {
                field,
                label: FIELD_LABELS[field] ?? field,
                required: entry.need === true || entry.need === 1,
                verified: entry.verify === true || entry.verify === 1,
            };
        });
});

async function loadSettings(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const [configData, registerData] = await Promise.all([
            getAdminConfigs(),
            getAdminRegisterInfo(),
        ]);

        rawConfigs.value = configData;
        rawRegister.value = registerData;
    } catch (error) {
        errorMessage.value =
            error instanceof Error ? error.message : '请求失败';
    } finally {
        loading.value = false;
    }
}

function text(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="系统配置"
            :icon="Settings"
            :show-api-badge="false"
        />

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>配置请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <div
            v-if="loading && configRows.length === 0"
            class="flex min-h-64 items-center justify-center rounded-lg border"
        >
            <Spinner />
        </div>

        <template v-else>
            <!-- 注册要求：一眼看出注册流程会向用户索取哪些身份信息 -->
            <Card>
                <CardHeader class="flex flex-row items-center gap-3">
                    <CardTitle class="text-base">注册要求</CardTitle>
                    <Badge variant="outline">
                        {{ registerRows.length }} 项
                    </Badge>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="registerRows.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        暂无注册配置。
                    </div>
                    <div
                        v-else
                        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div
                            v-for="row in registerRows"
                            :key="row.field"
                            class="rounded-lg border p-4"
                        >
                            <div class="text-sm font-medium">
                                {{ row.label }}
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Badge
                                    :variant="
                                        row.required ? 'default' : 'secondary'
                                    "
                                >
                                    {{ row.required ? '必填' : '选填' }}
                                </Badge>
                                <Badge
                                    :variant="
                                        row.verified ? 'default' : 'outline'
                                    "
                                >
                                    {{ row.verified ? '需验证' : '免验证' }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 全局配置：CDNfly 返回的是一组配置项，每项各有作用域和类型 -->
            <Card class="gap-0 overflow-hidden">
                <CardHeader>
                    <!-- one row, matching every other table in the console -->
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <CardTitle class="text-base">
                                CDNfly 全局配置
                            </CardTitle>
                            <Badge variant="outline">
                                {{ visibleConfigRows.length }} /
                                {{ configRows.length }} 项
                            </Badge>
                        </div>

                        <div
                            class="flex flex-wrap items-center gap-2 lg:justify-end"
                        >
                            <div class="relative w-full sm:w-72">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    v-model="filters.search"
                                    class="pl-9"
                                    placeholder="搜索配置项"
                                />
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="loading"
                                @click="loadSettings"
                            >
                                <Spinner
                                    v-if="loading"
                                    data-icon="inline-start"
                                />
                                <RefreshCw v-else data-icon="inline-start" />
                                刷新
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <!--
                            table-fixed with an explicit colgroup, because some
                            values are enormous: nginx-config-file is a whole
                            JSON config and the CAPTCHA templates are entire HTML
                            documents. With auto layout one of those took over
                            the row, squeezing 配置项 down until "allow" wrapped to
                            one letter per line and a single setting filled the
                            screen. Fixed columns keep every row one line high.
                        -->
                        <table class="w-full min-w-[860px] table-fixed text-sm">
                            <colgroup>
                                <col style="width: 22%" />
                                <col style="width: 38%" />
                                <col style="width: 12%" />
                                <col style="width: 10%" />
                                <col style="width: 8%" />
                                <col style="width: 10%" />
                                <col style="width: 72px" />
                            </colgroup>
                            <thead>
                                <tr
                                    class="border-t bg-muted/40 text-left text-xs text-muted-foreground"
                                >
                                    <th class="px-6 py-2 font-medium">
                                        配置项
                                    </th>
                                    <th class="px-4 py-2 font-medium">值</th>
                                    <th class="px-4 py-2 font-medium">
                                        作用域
                                    </th>
                                    <th class="px-4 py-2 font-medium">类型</th>
                                    <th class="px-4 py-2 font-medium">状态</th>
                                    <th class="px-4 py-2 font-medium">
                                        更新时间
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        操作
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in visibleConfigRows"
                                    :key="row.key"
                                    class="border-t"
                                >
                                    <td
                                        class="truncate px-6 py-3 font-medium"
                                        :title="row.name"
                                    >
                                        {{ row.name }}
                                    </td>
                                    <!--
                                        Click to expand: a short value is the
                                        whole point of the column, but a 40 KB
                                        HTML template must not be pasted into it.
                                    -->
                                    <td class="px-4 py-3">
                                        <button
                                            v-if="row.value.length > 80"
                                            type="button"
                                            class="w-full text-left"
                                            :title="
                                                expanded.has(row.key)
                                                    ? '收起'
                                                    : '展开完整值'
                                            "
                                            @click="toggleExpanded(row.key)"
                                        >
                                            <span
                                                :class="
                                                    expanded.has(row.key)
                                                        ? 'block max-h-64 overflow-y-auto font-mono text-xs break-all whitespace-pre-wrap'
                                                        : 'block truncate text-muted-foreground'
                                                "
                                            >
                                                {{ row.value }}
                                            </span>
                                            <span
                                                class="mt-1 block text-xs text-primary"
                                            >
                                                {{
                                                    expanded.has(row.key)
                                                        ? '收起'
                                                        : `展开（${row.value.length} 字符）`
                                                }}
                                            </span>
                                        </button>
                                        <span v-else class="tabular-nums">
                                            {{ row.value || '-' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ row.scope }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ row.type || '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge
                                            :variant="
                                                row.enabled
                                                    ? 'secondary'
                                                    : 'outline'
                                            "
                                        >
                                            {{ row.enabled ? '启用' : '停用' }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ row.updatedAt || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            title="编辑"
                                            @click="openEdit(row)"
                                        >
                                            <Pencil class="size-3.5" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="visibleConfigRows.length === 0">
                                    <td
                                        colspan="7"
                                        class="px-6 py-10 text-center text-muted-foreground"
                                    >
                                        {{
                                            configRows.length === 0
                                                ? '暂无配置'
                                                : '没有匹配的配置项'
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </template>

        <Dialog
            :open="editing !== null"
            @update:open="(open) => (editing = open ? editing : null)"
        >
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>编辑配置项</DialogTitle>
                    <DialogDescription>
                        {{ editing?.name }} · 作用域 {{ editing?.scope }}
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="editError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>保存失败</AlertTitle>
                    <AlertDescription>{{ editError }}</AlertDescription>
                </Alert>

                <div class="grid gap-2">
                    <Label for="config-value">值</Label>
                    <textarea
                        id="config-value"
                        v-model="editValue"
                        class="min-h-40 w-full resize-y rounded-md border bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        spellcheck="false"
                    />
                    <p class="text-xs text-muted-foreground">
                        只会提交这一项，其它配置保持不变。
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="editSaving"
                        @click="editing = null"
                    >
                        取消
                    </Button>
                    <Button :disabled="editSaving" @click="saveOne">
                        <Spinner v-if="editSaving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        保存
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
