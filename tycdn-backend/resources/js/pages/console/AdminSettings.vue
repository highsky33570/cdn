<script setup lang="ts">
import {
    AlertCircle,
    Code2,
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
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    getAdminConfigs,
    getAdminRegisterInfo,
    updateAdminConfigs,
} from '@/lib/adminModulesApi';
import type { CdnflyRecord } from '@/lib/adminModulesApi';

/**
 * One row of /v1/configs. CDNfly returns a *list* of settings, each carrying its
 * own scope and type — not a flat key/value object.
 */
type ConfigRow = {
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
const saving = ref(false);
const errorMessage = ref('');
const showJson = ref(false);
const configJson = ref('');
const rawConfigs = ref<unknown>(null);
const rawRegister = ref<unknown>(null);

const filters = reactive({ search: '' });

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
        name: text(row.name),
        value: text(row.value),
        type: text(row.type),
        scope: text(row.scope_name) || text(row.entity_name) || '-',
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
        configJson.value = JSON.stringify(configData, null, 2);
    } catch (error) {
        errorMessage.value =
            error instanceof Error ? error.message : '请求失败';
    } finally {
        loading.value = false;
    }
}

async function saveConfigs(): Promise<void> {
    saving.value = true;
    errorMessage.value = '';

    try {
        const payload = JSON.parse(configJson.value) as CdnflyRecord;
        const result = await updateAdminConfigs(payload);

        rawConfigs.value = result;
        configJson.value = JSON.stringify(result, null, 2);
        toast.success('配置已保存');
    } catch (error) {
        errorMessage.value =
            error instanceof SyntaxError
                ? '配置 JSON 格式无效'
                : error instanceof Error
                  ? error.message
                  : '请求失败';
        toast.error(errorMessage.value);
    } finally {
        saving.value = false;
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
                <CardHeader class="space-y-4">
                    <div
                        class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
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
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="loading || saving"
                            @click="loadSettings"
                        >
                            <Spinner v-if="loading" data-icon="inline-start" />
                            <RefreshCw v-else data-icon="inline-start" />
                            刷新
                        </Button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
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
                            @click="showJson = !showJson"
                        >
                            <Code2 data-icon="inline-start" />
                            {{ showJson ? '隐藏 JSON' : '编辑 JSON' }}
                        </Button>
                    </div>
                </CardHeader>

                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-sm">
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
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in visibleConfigRows"
                                    :key="row.name + row.scope"
                                    class="border-t"
                                >
                                    <td class="px-6 py-3 font-medium break-all">
                                        {{ row.name }}
                                    </td>
                                    <td class="px-4 py-3 tabular-nums">
                                        {{ row.value || '-' }}
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
                                </tr>
                                <tr v-if="visibleConfigRows.length === 0">
                                    <td
                                        colspan="6"
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

            <!--
                The JSON editor stays, because CDNfly's config list is open-ended
                and a typed form would silently drop anything it did not know
                about. It is collapsed by default so the page reads as settings
                rather than as a payload dump.
            -->
            <Card v-if="showJson" class="gap-4">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-3"
                >
                    <CardTitle class="text-base">配置 JSON</CardTitle>
                    <Button
                        size="sm"
                        :disabled="loading || saving"
                        @click="saveConfigs"
                    >
                        <Spinner v-if="saving" data-icon="inline-start" />
                        <Save v-else data-icon="inline-start" />
                        保存配置
                    </Button>
                </CardHeader>
                <CardContent>
                    <p class="mb-2 text-xs text-muted-foreground">
                        直接编辑将整体覆盖 CDNfly 全局配置，请谨慎操作。
                    </p>
                    <textarea
                        v-model="configJson"
                        class="min-h-96 w-full resize-y rounded-md border bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        spellcheck="false"
                    />
                </CardContent>
            </Card>
        </template>
    </div>
</template>
