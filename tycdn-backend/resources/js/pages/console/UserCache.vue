<script setup lang="ts">
import {
    AlertCircle,
    ArrowUpToLine,
    Clock3,
    Flame,
    RefreshCw,
    Search,
    Send,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner'
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    formatDate,
    getErrorMessage,
    jsonText,
    textValue,
} from '@/lib/cdnRecord';
import {
    createUserJobs,
    extractCdnflyRows,
    extractCdnflyTotal,
    listUserJobs,
} from '@/lib/cdnUserApi';
import type { CdnJobPayload, CdnflyRecord } from '@/lib/cdnUserApi';

const TYPE_ALL = 'all';
const JOB_TYPES = [
    { value: 'clean_url', label: '刷新 URL', icon: RefreshCw },
    { value: 'clean_dir', label: '刷新目录', icon: ArrowUpToLine },
    { value: 'pre_cache_url', label: '预热 URL', icon: Flame },
] as const;

const loading = ref(false);
const submitting = ref(false);
const errorMessage = ref('');
const formError = ref('');
const page = ref(1);
const total = ref(0);
const jobs = ref<CdnflyRecord[]>([]);

const filters = reactive({
    type: TYPE_ALL,
    key1: '',
    key2: '',
    per_page: '20',
});

const form = reactive({
    type: 'clean_url',
    urls: '',
});

const hasPreviousPage = computed(() => page.value > 1);
const hasNextPage = computed(
    () => page.value * Number(filters.per_page) < total.value,
);
const paginationText = computed(() =>
    total.value === 0 ? '暂无任务' : `${total.value} 个任务`,
);

onMounted(() => {
    void loadJobs();
});

async function loadJobs(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(filters.per_page),
        };

        if (filters.type !== TYPE_ALL) {
            params.type = filters.type;
        }

        if (filters.key1.trim() !== '') {
            params.key1 = filters.key1.trim();
        }

        if (filters.key2.trim() !== '') {
            params.key2 = encodeURIComponent(filters.key2.trim());
        }

        const result = await listUserJobs(params);
        const nextRows = extractCdnflyRows(result);

        jobs.value = nextRows;
        total.value = extractCdnflyTotal(result, nextRows.length);
        page.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

function submitSearch(): void {
    page.value = 1;
    void loadJobs(1);
}

async function submitJobs(): Promise<void> {
    const urls = form.urls
        .split(/\r?\n/)
        .map((item) => item.trim())
        .filter(Boolean);

    if (urls.length === 0) {
        formError.value = '至少填写一个 URL 或目录';

        return;
    }

    const jobsPayload: CdnJobPayload[] = urls.map((url) => ({
        type: form.type,
        data: { url },
    }));

    submitting.value = true;
    formError.value = '';

    try {
        await createUserJobs(jobsPayload);
        toast.success(`已提交 ${jobsPayload.length} 个缓存任务`);
        form.urls = '';
        filters.type = form.type;
        await loadJobs(1);
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        submitting.value = false;
    }
}

function prevPage(): void {
    if (hasPreviousPage.value) {
        void loadJobs(page.value - 1);
    }
}

function nextPage(): void {
    if (hasNextPage.value) {
        void loadJobs(page.value + 1);
    }
}

function typeLabel(value: unknown): string {
    const type = textValue(value);

    return JOB_TYPES.find((item) => item.value === type)?.label || type || '-';
}

function stateLabel(value: unknown): string {
    const state = textValue(value);

    if (state === 'done') {
        return '完成';
    }

    if (state === 'process') {
        return '执行中';
    }

    if (state === 'pending') {
        return '排队中';
    }

    return state || '-';
}

function stateVariant(value: unknown): 'default' | 'outline' | 'secondary' {
    const state = textValue(value);

    if (state === 'done') {
        return 'secondary';
    }

    if (state === 'process') {
        return 'default';
    }

    return 'outline';
}
</script>

<template>
    <div class="space-y-6">
        <ConsolePageHeader
            eyebrow="用户端 / 缓存管理"
            title="刷新预热"
            description="提交刷新 URL、刷新目录和预热 URL 任务，并查看执行状态。"
            :icon="Clock3"
            :show-api-badge="false"
        />

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>
        <div class="grid gap-6 xl:grid-cols-[minmax(0,420px)_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle>提交任务</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        每行一个 URL 或目录。刷新目录使用目录地址，预热仅支持
                        URL。
                    </p>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-5" @submit.prevent="submitJobs">
                        <Alert v-if="formError" variant="destructive">
                            <AlertCircle data-icon="alert" />
                            <AlertTitle>提交失败</AlertTitle>
                            <AlertDescription>{{ formError }}</AlertDescription>
                        </Alert>
                        <div class="grid gap-2">
                            <Label>任务类型</Label>
                            <Select v-model="form.type">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem
                                            v-for="type in JOB_TYPES"
                                            :key="type.value"
                                            :value="type.value"
                                        >
                                            {{ type.label }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="cache-urls">URL / 目录</Label>
                            <textarea
                                id="cache-urls"
                                v-model="form.urls"
                                class="min-h-48 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="https://www.example.com/assets/app.js"
                                spellcheck="false"
                            />
                        </div>
                        <Button type="submit" :disabled="submitting">
                            <Spinner
                                v-if="submitting"
                                data-icon="inline-start"
                            />
                            <Send v-else data-icon="inline-start" />
                            提交任务
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <CardTitle>任务记录</CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ paginationText }}
                        </p>
                    </div>
                    <form
                        class="grid gap-2 md:grid-cols-[140px_1fr_1fr_auto]"
                        @submit.prevent="submitSearch"
                    >
                        <Select v-model="filters.type">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="TYPE_ALL">
                                        全部类型
                                    </SelectItem>
                                    <SelectItem
                                        v-for="type in JOB_TYPES"
                                        :key="type.value"
                                        :value="type.value"
                                    >
                                        {{ type.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Input
                            v-model="filters.key1"
                            placeholder="域名 / site_id"
                        />
                        <Input v-model="filters.key2" placeholder="URL" />
                        <Button type="submit" :disabled="loading">
                            <Spinner v-if="loading" data-icon="inline-start" />
                            <Search v-else data-icon="inline-start" />
                            查询
                        </Button>
                    </form>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto border-y">
                        <table class="w-full min-w-[900px] table-fixed text-sm">
                            <colgroup>
                                <col style="width: 10%" />
                                <col style="width: 14%" />
                                <col style="width: 20%" />
                                <col style="width: 26%" />
                                <col style="width: 10%" />
                                <col style="width: 10%" />
                                <col style="width: 10%" />
                            </colgroup>
                            <thead class="border-b text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium">
                                        ID
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        类型
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        域名 / site_id
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        数据
                                    </th>
                                    <th
                                        class="px-4 py-3 text-center font-medium"
                                    >
                                        进度
                                    </th>
                                    <th
                                        class="px-4 py-3 text-center font-medium"
                                    >
                                        状态
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        创建时间
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-if="loading"
                                    class="border-b text-muted-foreground"
                                >
                                    <td
                                        class="px-4 py-12 text-center"
                                        colspan="7"
                                    >
                                        <Spinner class="mx-auto" />
                                    </td>
                                </tr>
                                <tr
                                    v-for="job in jobs"
                                    :key="textValue(job.id)"
                                    class="border-b"
                                >
                                    <td class="px-4 py-3">
                                        #{{ textValue(job.id) || '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ typeLabel(job.type) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="truncate">
                                            {{ textValue(job.key1) || '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div
                                            class="truncate font-mono text-xs text-muted-foreground"
                                        >
                                            {{
                                                textValue(job.key2) ||
                                                jsonText(job.data, '-')
                                            }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        {{ textValue(job.progress) || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <Badge
                                            :variant="stateVariant(job.state)"
                                        >
                                            {{ stateLabel(job.state) }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(job.create_at2) }}
                                    </td>
                                </tr>
                                <tr v-if="!loading && jobs.length === 0">
                                    <td
                                        class="px-6 py-16 text-center text-muted-foreground"
                                        colspan="7"
                                    >
                                        暂无任务
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="flex items-center justify-end gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasPreviousPage || loading"
                @click="prevPage"
            >
                上一页
            </Button>
            <span class="text-sm text-muted-foreground">第 {{ page }} 页</span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!hasNextPage || loading"
                @click="nextPage"
            >
                下一页
            </Button>
        </div>
    </div>
</template>
