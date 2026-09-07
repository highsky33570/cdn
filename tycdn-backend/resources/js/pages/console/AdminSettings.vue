<script setup lang="ts">
import {
    AlertCircle,
    CheckCircle2,
    RefreshCw,
    Save,
    Settings,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner'
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
    getAdminConfigs,
    getAdminRegisterInfo,
    updateAdminConfigs,
} from '@/lib/adminModulesApi';
import type { CdnflyRecord } from '@/lib/adminModulesApi';

type Entry = {
    key: string;
    value: unknown;
};

const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const configs = ref<CdnflyRecord | null>(null);
const registerInfo = ref<CdnflyRecord | null>(null);
const configJson = ref('');

const configEntries = computed(() => toEntries(configs.value));
const registerEntries = computed(() => toEntries(registerInfo.value));

onMounted(() => {
    void loadSettings();
});

async function loadSettings(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const [configData, registerData] = await Promise.all([
            getAdminConfigs(),
            getAdminRegisterInfo(),
        ]);

        configs.value = configData;
        registerInfo.value = registerData;
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
        configs.value = await updateAdminConfigs(payload);
        configJson.value = JSON.stringify(configs.value, null, 2);
        toast.success('配置已保存');
    } catch (error) {
        errorMessage.value =
            error instanceof SyntaxError
                ? '配置 JSON 格式无效'
                : error instanceof Error
                  ? error.message
                  : '请求失败';
    } finally {
        saving.value = false;
    }
}

function toEntries(record: CdnflyRecord | null): Entry[] {
    if (!record) {
        return [];
    }

    return Object.entries(record)
        .slice(0, 12)
        .map(([key, value]) => ({
            key,
            value,
        }));
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="系统配置"
            :icon="Settings"
            :show-api-badge="false"
        />

        <div class="flex flex-wrap gap-2">
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
            <Button
                size="sm"
                :disabled="loading || saving"
                @click="saveConfigs"
            >
                <Spinner v-if="saving" data-icon="inline-start" />
                <Save v-else data-icon="inline-start" />
                保存配置
            </Button>
        </div>

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>配置请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <Alert v-if="successMessage">
            <CheckCircle2 data-icon="alert" />
            <AlertTitle>{{ successMessage }}</AlertTitle>
        </Alert>

        <div
            v-if="loading && !configs"
            class="flex min-h-64 items-center justify-center rounded-lg border"
        >
            <Spinner />
        </div>

        <template v-else>
            <div class="grid gap-4 xl:grid-cols-2">
                <Card class="gap-0 overflow-hidden">
                    <CardHeader class="flex flex-row items-center gap-3">
                        <CardTitle class="text-base">CDNfly 全局配置</CardTitle>
                        <Badge variant="outline">
                            {{ configEntries.length }} 项
                        </Badge>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[620px] text-sm">
                                <tbody>
                                    <tr
                                        v-for="entry in configEntries"
                                        :key="entry.key"
                                        class="border-t"
                                    >
                                        <td class="w-56 px-6 py-3 font-medium">
                                            {{ entry.key }}
                                        </td>
                                        <td
                                            class="max-w-[360px] truncate px-4 py-3 text-muted-foreground"
                                        >
                                            {{ formatValue(entry.value) }}
                                        </td>
                                    </tr>
                                    <tr v-if="configEntries.length === 0">
                                        <td
                                            class="px-6 py-10 text-center text-muted-foreground"
                                        >
                                            暂无配置
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card class="gap-0 overflow-hidden">
                    <CardHeader class="flex flex-row items-center gap-3">
                        <CardTitle class="text-base">注册信息</CardTitle>
                        <Badge variant="outline">
                            {{ registerEntries.length }} 项
                        </Badge>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[620px] text-sm">
                                <tbody>
                                    <tr
                                        v-for="entry in registerEntries"
                                        :key="entry.key"
                                        class="border-t"
                                    >
                                        <td class="w-56 px-6 py-3 font-medium">
                                            {{ entry.key }}
                                        </td>
                                        <td
                                            class="max-w-[360px] truncate px-4 py-3 text-muted-foreground"
                                        >
                                            {{ formatValue(entry.value) }}
                                        </td>
                                    </tr>
                                    <tr v-if="registerEntries.length === 0">
                                        <td
                                            class="px-6 py-10 text-center text-muted-foreground"
                                        >
                                            暂无注册信息
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="gap-4">
                <CardHeader>
                    <CardTitle class="text-base">配置 JSON</CardTitle>
                </CardHeader>
                <CardContent>
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
