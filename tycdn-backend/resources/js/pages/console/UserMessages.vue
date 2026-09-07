<script setup lang="ts">
import {
    AlertCircle,
    Bell,
    CheckCircle2,
    Eye,
    Mail,
    Save,
    Search,
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
    recordId,
    textValue,
} from '@/lib/cdnRecord';
import {
    extractCdnflyRows,
    extractCdnflyTotal,
    getUserMessage,
    getUserMessageSubscriptions,
    listUserMessages,
    markUserMessageRead,
    updateUserMessageSubscription,
} from '@/lib/cdnUserApi';
import type { CdnMessageSubPayload, CdnflyRecord } from '@/lib/cdnUserApi';

type MessagesView = 'messages' | 'subscriptions';

const props = defineProps<{
    view: MessagesView;
}>();

const loading = ref(false);
const saving = ref(false);
const markingId = ref<number | null>(null);
const errorMessage = ref('');
const formError = ref('');
const detailDialogOpen = ref(false);
const detailRecord = ref<CdnflyRecord | null>(null);
const rawSubscriptions = ref('');
const page = ref(1);
const total = ref(0);
const messages = ref<CdnflyRecord[]>([]);
const subscriptions = ref<CdnflyRecord[]>([]);

const filters = reactive({
    type: 'all',
    per_page: '20',
});

const form = reactive({
    msg_type: 'package-expire',
    phone: '0',
    email: '1',
});

const title = computed(() =>
    props.view === 'subscriptions' ? '消息订阅' : '消息中心',
);
const description = computed(() =>
    props.view === 'subscriptions'
        ? '管理手机、邮箱通知开关。'
        : '查询系统消息，支持查看详情与标记已读。',
);
const activeRows = computed(() =>
    props.view === 'subscriptions' ? subscriptions.value : messages.value,
);
const hasPreviousPage = computed(() => page.value > 1);
const hasNextPage = computed(
    () => page.value * Number(filters.per_page) < total.value,
);

onMounted(() => {
    void loadCurrent(1);
});

async function loadCurrent(targetPage = page.value): Promise<void> {
    if (props.view === 'subscriptions') {
        await loadSubscriptions();

        return;
    }

    await loadMessages(targetPage);
}

async function loadMessages(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(filters.per_page),
        };

        if (filters.type !== 'all') {
            params.type = filters.type;
        }

        const result = await listUserMessages(params);
        const nextRows = extractCdnflyRows(result);

        messages.value = nextRows;
        total.value = extractCdnflyTotal(result, nextRows.length);
        page.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function loadSubscriptions(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const result = await getUserMessageSubscriptions();
        const nextRows = extractCdnflyRows(result);

        subscriptions.value = nextRows;
        total.value = nextRows.length;
        rawSubscriptions.value = jsonText(result, '{}');
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function openMessage(record: CdnflyRecord): Promise<void> {
    const id = recordId(record);

    if (!id) {
        errorMessage.value = '消息 ID 缺失';

        return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        detailRecord.value = await getUserMessage(id);
        detailDialogOpen.value = true;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function markRead(record: CdnflyRecord): Promise<void> {
    const id = recordId(record);

    if (!id) {
        errorMessage.value = '消息 ID 缺失';

        return;
    }

    markingId.value = id;
    errorMessage.value = '';

    try {
        await markUserMessageRead(id);
        toast.success('已读请求已提交');
        await loadMessages();
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        markingId.value = null;
    }
}

async function submitSubscription(): Promise<void> {
    const payload: CdnMessageSubPayload = {
        msg_type: form.msg_type,
        phone: form.phone === '1',
        email: form.email === '1',
    };

    saving.value = true;
    formError.value = '';

    try {
        await updateUserMessageSubscription(payload);
        toast.success('消息订阅更新请求已提交');
        await loadSubscriptions();
    } catch (error) {
        formError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

function submitSearch(): void {
    page.value = 1;
    void loadMessages(1);
}

function prevPage(): void {
    if (hasPreviousPage.value) {
        void loadMessages(page.value - 1);
    }
}

function nextPage(): void {
    if (hasNextPage.value) {
        void loadMessages(page.value + 1);
    }
}

function messageTitle(record: CdnflyRecord): string {
    return textValue(record.title ?? record.name ?? record.subject) || '-';
}

function messageStatus(record: CdnflyRecord): string {
    return textValue(record.read ?? record.is_read ?? record.status) || '-';
}

function subscriptionType(record: CdnflyRecord): string {
    return textValue(record.msg_type ?? record.type ?? record.name) || '-';
}
</script>

<template>
    <div class="space-y-6">
        <ConsolePageHeader
            eyebrow="用户端 / 消息"
            :title="title"
            :description="description"
            :icon="props.view === 'subscriptions' ? Bell : Mail"
            :show-api-badge="false"
        />

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle data-icon="alert" />
            <AlertTitle>请求失败</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>
        <Card v-if="props.view === 'subscriptions'">
            <CardHeader>
                <CardTitle class="text-base">订阅设置</CardTitle>
            </CardHeader>
            <CardContent class="space-y-5">
                <form
                    class="grid gap-4 lg:grid-cols-[180px_160px_160px_auto]"
                    @submit.prevent="submitSubscription"
                >
                    <div class="grid gap-2">
                        <Label>消息类型</Label>
                        <Select v-model="form.msg_type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="package-expire"
                                        >package-expire</SelectItem
                                    >
                                    <SelectItem value="traffic-exceed"
                                        >traffic-exceed</SelectItem
                                    >
                                    <SelectItem value="announcement"
                                        >announcement</SelectItem
                                    >
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>手机通知</Label>
                        <Select v-model="form.phone">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="1">开启</SelectItem>
                                    <SelectItem value="0">关闭</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>邮箱通知</Label>
                        <Select v-model="form.email">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="1">开启</SelectItem>
                                    <SelectItem value="0">关闭</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-end">
                        <Button type="submit" :disabled="saving">
                            <Spinner v-if="saving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            保存
                        </Button>
                    </div>
                </form>
                <Alert v-if="formError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>提交失败</AlertTitle>
                    <AlertDescription>{{ formError }}</AlertDescription>
                </Alert>
                <div class="overflow-x-auto border-y">
                    <table class="w-full min-w-[680px] table-fixed text-sm">
                        <colgroup>
                            <col style="width: 34%" />
                            <col style="width: 22%" />
                            <col style="width: 22%" />
                            <col style="width: 22%" />
                        </colgroup>
                        <thead class="border-b text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">
                                    类型
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    手机
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    邮箱
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    状态
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td class="px-6 py-16 text-center" colspan="4">
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <tr
                                v-for="item in subscriptions"
                                :key="subscriptionType(item)"
                                class="border-b"
                            >
                                <td class="px-4 py-3">
                                    {{ subscriptionType(item) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ textValue(item.phone) || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ textValue(item.email) || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge variant="secondary">
                                        {{ textValue(item.status) || '-' }}
                                    </Badge>
                                </td>
                            </tr>
                            <tr v-if="!loading && activeRows.length === 0">
                                <td
                                    class="px-6 py-16 text-center text-muted-foreground"
                                    colspan="4"
                                >
                                    暂无订阅记录
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <details v-if="rawSubscriptions" class="text-sm">
                    <summary class="cursor-pointer text-muted-foreground">
                        详情数据
                    </summary>
                    <pre
                        class="mt-3 max-h-72 overflow-auto rounded-md border bg-muted/30 p-3 text-xs"
                        >{{ rawSubscriptions }}</pre
                    >
                </details>
            </CardContent>
        </Card>

        <Card v-else>
            <CardHeader class="space-y-4">
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <CardTitle class="text-base">消息列表</CardTitle>
                    <div class="text-sm text-muted-foreground">
                        {{ total === 0 ? '暂无消息' : `${total} 条消息` }}
                    </div>
                </div>
                <form
                    class="grid gap-3 lg:grid-cols-[180px_120px_auto]"
                    @submit.prevent="submitSearch"
                >
                    <Select v-model="filters.type">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">全部类型</SelectItem>
                                <SelectItem value="package-expire"
                                    >package-expire</SelectItem
                                >
                                <SelectItem value="traffic-exceed"
                                    >traffic-exceed</SelectItem
                                >
                                <SelectItem value="announcement"
                                    >announcement</SelectItem
                                >
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.per_page">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
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
                </form>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto border-y">
                    <table class="w-full min-w-[860px] table-fixed text-sm">
                        <colgroup>
                            <col style="width: 32%" />
                            <col style="width: 18%" />
                            <col style="width: 14%" />
                            <col style="width: 18%" />
                            <col style="width: 18%" />
                        </colgroup>
                        <thead class="border-b text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">
                                    标题
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    类型
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    状态
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    时间
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    操作
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td class="px-6 py-16 text-center" colspan="5">
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <tr
                                v-for="message in messages"
                                :key="
                                    textValue(message.id) ||
                                    messageTitle(message)
                                "
                                class="border-b"
                            >
                                <td class="px-4 py-3">
                                    <div class="truncate font-medium">
                                        {{ messageTitle(message) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        #{{ textValue(message.id) || '-' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ textValue(message.type) || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge variant="secondary">
                                        {{ messageStatus(message) }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{
                                        formatDate(
                                            message.create_at2 ??
                                                message.created_at,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openMessage(message)"
                                        >
                                            <Eye data-icon="inline-start" />
                                            查看
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :disabled="
                                                markingId === recordId(message)
                                            "
                                            @click="markRead(message)"
                                        >
                                            <Spinner
                                                v-if="
                                                    markingId ===
                                                    recordId(message)
                                                "
                                                data-icon="inline-start"
                                            />
                                            <CheckCircle2
                                                v-else
                                                data-icon="inline-start"
                                            />
                                            已读
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && activeRows.length === 0">
                                <td
                                    class="px-6 py-16 text-center text-muted-foreground"
                                    colspan="5"
                                >
                                    暂无消息
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="props.view === 'messages'"
            class="flex items-center justify-end gap-2"
        >
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

        <Dialog v-model:open="detailDialogOpen">
            <DialogScrollContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>消息详情</DialogTitle>
                    <DialogDescription>
                        消息详情内容。
                    </DialogDescription>
                </DialogHeader>
                <pre
                    class="max-h-[520px] overflow-auto rounded-md border bg-muted/30 p-3 text-xs"
                    >{{ jsonText(detailRecord, '{}') }}</pre
                >
                <DialogFooter>
                    <Button @click="detailDialogOpen = false">关闭</Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
