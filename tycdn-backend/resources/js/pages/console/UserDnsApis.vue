<script setup lang="ts">
import {
    AlertCircle,
    KeyRound,
    Pencil,
    Plus,
    RefreshCw,
    Save,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner'
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
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
import Switch from '@/components/ui/switch/Switch.vue';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import {
    formatDate,
    getErrorMessage,
    jsonText,
    parseJsonObject,
    recordId,
    textValue,
} from '@/lib/cdnRecord';
import {
    createUserDnsApi,
    deleteUserDnsApi,
    extractCdnflyRows,
    extractCdnflyTotal,
    listUserDnsApis,
    updateUserDnsApi,
} from '@/lib/cdnUserApi';
import type { CdnDnsApiPayload, CdnflyRecord } from '@/lib/cdnUserApi';

const DNS_TYPES = [
    'CloudFlare',
    'DNSPod.cn',
    'GoDaddy.com',
    'Aliyun',
    'cloudns.net',
    'Name.com',
    'Namecheap',
    'jdcloud.com',
    'dnsdun',
] as const;

const AUTH_TEMPLATES: Record<string, Record<string, string>> = {
    CloudFlare: { CF_Key: '', CF_Email: '' },
    'DNSPod.cn': { DP_Id: '', DP_Key: '' },
    'GoDaddy.com': { GD_Key: '', GD_Secret: '' },
    Aliyun: { Ali_Key: '', Ali_Secret: '' },
    'cloudns.net': { CLOUDNS_SUB_AUTH_ID: '', CLOUDNS_AUTH_PASSWORD: '' },
    'Name.com': { Namecom_Username: '', Namecom_Token: '' },
    Namecheap: {
        NAMECHEAP_USERNAME: '',
        NAMECHEAP_API_KEY: '',
        NAMECHEAP_SOURCEIP: '',
    },
    'jdcloud.com': { JD_ACCESS_KEY_ID: '', JD_ACCESS_KEY_SECRET: '' },
    dnsdun: { Dnsdun_uid: '', Dnsdun_api_key: '' },
};

const loading = ref(false);
const saving = ref(false);
const deleteOpen = ref(false);
const deleting = ref(false);
const deleteError = ref('');
const deleteTarget = ref<CdnflyRecord | null>(null);
const dialogOpen = ref(false);
const editingRecord = ref<CdnflyRecord | null>(null);
const errorMessage = ref('');
const formError = ref('');
const page = ref(1);
const total = ref(0);
const records = ref<CdnflyRecord[]>([]);

const filters = reactive({
    search: '',
    per_page: '20',
});

const form = reactive({
    name: '',
    type: 'CloudFlare',
    auth: jsonText(AUTH_TEMPLATES.CloudFlare, '{}'),
    des: '',
});

const dialogTitle = computed(() =>
    editingRecord.value ? '编辑 DNS API' : '新增 DNS API',
);
const hasPreviousPage = computed(() => page.value > 1);
const hasNextPage = computed(
    () => page.value * Number(filters.per_page) < total.value,
);
const paginationText = computed(() =>
    total.value === 0 ? '暂无 DNS API' : `${total.value} 个 DNS API`,
);
const displayedRecords = computed(() => {
    const keyword = filters.search.trim().toLowerCase();

    if (keyword === '') {
        return records.value;
    }

    return records.value.filter((record) =>
        [record.name, record.type, record.des]
            .map((value) => textValue(value).toLowerCase())
            .some((value) => value.includes(keyword)),
    );
});

onMounted(() => {
    void loadRecords();
});

async function loadRecords(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(filters.per_page),
        };

        const result = await listUserDnsApis(params);
        const nextRows = extractCdnflyRows(result);

        records.value = nextRows;
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
    void loadRecords(1);
}

function openCreateDialog(): void {
    editingRecord.value = null;
    form.name = '';
    form.type = 'CloudFlare';
    form.auth = jsonText(AUTH_TEMPLATES.CloudFlare, '{}');
    form.des = '';
    formError.value = '';
    dialogOpen.value = true;
}

function openEditDialog(record: CdnflyRecord): void {
    editingRecord.value = record;
    form.name = textValue(record.name);
    form.type = textValue(record.type) || 'CloudFlare';
    form.auth = jsonText(
        record.auth,
        jsonText(AUTH_TEMPLATES[form.type], '{}'),
    );
    form.des = textValue(record.des ?? record.remark);
    formError.value = '';
    dialogOpen.value = true;
}

function applyAuthTemplate(): void {
    form.auth = jsonText(AUTH_TEMPLATES[form.type] ?? {}, '{}');
}

async function submitForm(): Promise<void> {
    let payload: CdnDnsApiPayload;

    try {
        payload = {
            name: form.name.trim(),
            type: form.type,
            auth: parseJsonObject(form.auth),
            des: nullableText(form.des),
        };
    } catch (error) {
        formError.value = getErrorMessage(error);

        return;
    }

    if (payload.name === '') {
        formError.value = '名称不能为空';

        return;
    }

    saving.value = true;
    formError.value = '';

    try {
        if (editingRecord.value) {
            const id = recordId(editingRecord.value);

            if (!id) {
                formError.value = 'DNS API ID 缺失';

                return;
            }

            await updateUserDnsApi(id, payload);
            toast.success('DNS API 更新请求已提交');
        } else {
            await createUserDnsApi(payload);
            toast.success('DNS API 创建请求已提交');
        }

        dialogOpen.value = false;
        await loadRecords();
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

function openDeleteRecord(record: CdnflyRecord) {
    deleteTarget.value = record;
    deleteError.value = '';
    deleteOpen.value = true;
}

async function confirmDeleteRecord(): Promise<void> {
    const id = recordId(deleteTarget.value!);
    if (!id) return;
    deleting.value = true;
    try {
        await deleteUserDnsApi(id);
        deleteOpen.value = false;
        toast.success('DNS API 删除请求已提交');
        await loadRecords();
    } catch (error) {
        deleteError.value = getErrorMessage(error);
    } finally {
        deleting.value = false;
    }
}

function prevPage(): void {
    if (hasPreviousPage.value) {
        void loadRecords(page.value - 1);
    }
}

function nextPage(): void {
    if (hasNextPage.value) {
        void loadRecords(page.value + 1);
    }
}

function recordName(record: CdnflyRecord): string {
    return textValue(record.name) || `#${textValue(record.id)}`;
}

function maskedAuth(record: CdnflyRecord): string {
    const auth = record.auth;

    if (!auth) {
        return '-';
    }

    if (typeof auth === 'string') {
        try {
            return Object.keys(JSON.parse(auth)).join(', ') || '-';
        } catch {
            return '已配置';
        }
    }

    if (typeof auth === 'object' && !Array.isArray(auth)) {
        return Object.keys(auth).join(', ') || '-';
    }

    return '已配置';
}

function nullableText(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}
</script>

<template>
    <div class="space-y-6">
        <ConsolePageHeader
            eyebrow="用户端 / DNS API"
            title="DNS API"
            description="维护 DNS 服务商凭据，用于证书自动签发。"
            :icon="KeyRound"
            :show-api-badge="false"
        />

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>
        <Card>
            <CardHeader
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <CardTitle>DNS API 列表</CardTitle>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ paginationText }}
                    </p>
                </div>
                <form
                    class="flex flex-col gap-2 sm:flex-row"
                    @submit.prevent="submitSearch"
                >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="filters.search"
                            class="w-full pl-9 sm:w-64"
                            placeholder="搜索名称或服务商"
                        />
                    </div>
                    <Select v-model="filters.per_page">
                        <SelectTrigger class="w-full sm:w-28">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="10">10 条</SelectItem>
                                <SelectItem value="20">20 条</SelectItem>
                                <SelectItem value="50">50 条</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Button type="submit" :disabled="loading">
                        <Spinner v-if="loading" data-icon="inline-start" />
                        <Search v-else data-icon="inline-start" />
                        查询
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="loading"
                        @click="loadRecords()"
                    >
                        <RefreshCw data-icon="inline-start" />
                        刷新
                    </Button>
                    <Button type="button" @click="openCreateDialog">
                        <Plus data-icon="inline-start" />
                        新增
                    </Button>
                </form>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto border-y">
                    <table class="w-full min-w-[820px] table-fixed text-sm">
                        <colgroup>
                            <col style="width: 22%" />
                            <col style="width: 16%" />
                            <col style="width: 22%" />
                            <col style="width: 12%" />
                            <col style="width: 14%" />
                            <col style="width: 14%" />
                        </colgroup>
                        <thead class="border-b text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">
                                    名称
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    服务商
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    凭据字段
                                </th>
                                <th class="px-4 py-3 text-center font-medium">
                                    状态
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    更新时间
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    操作
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-if="loading"
                                class="border-b text-muted-foreground"
                            >
                                <td class="px-4 py-12 text-center" colspan="6">
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <tr
                                v-for="record in displayedRecords"
                                :key="textValue(record.id)"
                                class="border-b"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ recordName(record) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        #{{ textValue(record.id) || '-' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ textValue(record.type) || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="truncate text-muted-foreground">
                                        {{ maskedAuth(record) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Badge variant="outline">
                                        {{
                                            record.enable === 0 ||
                                            record.enable === false
                                                ? '禁用'
                                                : '可用'
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{
                                        formatDate(
                                            record.update_at2 ??
                                                record.created_at,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openEditDialog(record)"
                                        >
                                            <Pencil data-icon="inline-start" />
                                            编辑
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            @click="openDeleteRecord(record)"
                                        >
                                            <Trash2 data-icon="inline-start" />
                                            删除
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-if="!loading && displayedRecords.length === 0"
                            >
                                <td
                                    class="px-6 py-16 text-center text-muted-foreground"
                                    colspan="6"
                                >
                                    暂无 DNS API
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

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

        <Dialog v-model:open="dialogOpen">
            <DialogScrollContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>
                        auth 字段以 JSON 格式提交，敏感信息通过安全通道传输。
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-5" @submit.prevent="submitForm">
                    <Alert v-if="formError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>提交失败</AlertTitle>
                        <AlertDescription>{{ formError }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="dns-name">名称</Label>
                            <Input
                                id="dns-name"
                                v-model="form.name"
                                autocomplete="off"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>服务商</Label>
                            <Select
                                v-model="form.type"
                                @update:model-value="applyAuthTemplate"
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem
                                            v-for="type in DNS_TYPES"
                                            :key="type"
                                            :value="type"
                                        >
                                            {{ type }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="dns-auth">auth JSON</Label>
                        <textarea
                            id="dns-auth"
                            v-model="form.auth"
                            class="min-h-40 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                            spellcheck="false"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dns-des">备注</Label>
                        <textarea
                            id="dns-des"
                            v-model="form.des"
                            class="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="dialogOpen = false"
                        >
                            取消
                        </Button>
                        <Button type="submit" :disabled="saving">
                            <Spinner v-if="saving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            保存
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <ConfirmDeleteDialog
            :open="deleteOpen"
            :description="`确认删除 DNS API「${deleteTarget ? recordName(deleteTarget) : ''}」？删除后不可恢复。`"
            :loading="deleting"
            :error="deleteError"
            @confirm="confirmDeleteRecord"
            @cancel="deleteOpen = false"
        />
    </div>
</template>
