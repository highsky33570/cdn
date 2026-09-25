<script setup lang="ts">
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Eye,
    EyeOff,
    Plus,
    Search,
    Trash2,
    Wrench,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
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
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { Spinner } from '@/components/ui/spinner';
import Switch from '@/components/ui/switch/Switch.vue';
import {
    createAdminCnameDomain,
    deleteAdminCnameDomain,
    getAdminDnsSetting,
    listAdminCnameDomains,
    saveAdminDnsSetting,
    updateAdminCnameDomain,
} from '@/lib/adminModulesApi';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import { getErrorMessage, textValue } from '@/lib/formatters';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const providers = [
    {
        value: 'aliyun',
        label: '阿里云 (aliyun.com, alibabacloud.com)',
        id: 'AccessKey ID',
        token: 'AccessKey Secret',
    },
    {
        value: 'huaweicloud',
        label: '华为云 (huaweicloud.com)',
        id: 'Access Key Id',
        token: 'Secret Access Key',
    },
    {
        value: 'dns_la',
        label: 'DNSLA (dns.la)',
        id: 'APIID',
        token: 'API 密钥',
    },
    {
        value: 'dnspod_cn',
        label: 'DNSPod (dnspod.cn)',
        id: 'ID',
        token: 'Token',
    },
    {
        value: 'dnspod_com',
        label: 'DNSPod 国际版 (dnspod.com)',
        id: 'ID',
        token: 'Token',
    },
    {
        value: 'dnsdotcom',
        label: '帝恩思 (51dns.com)',
        id: 'API Key',
        token: 'API Secret',
    },
    {
        value: 'cloudflare',
        label: 'Cloudflare (cloudflare.com)',
        id: 'Email',
        token: 'API Key',
    },
];
const tab = ref('setting'),
    settingsLoading = ref(true),
    settingsReady = ref(false),
    saving = ref(false),
    settingError = ref(''),
    showToken = ref(false);
const form = reactive({
    dns: 'aliyun',
    id: '',
    token: '',
    ttl: 600,
    weight_on: 1,
});
const provider = computed(
    () => providers.find((p) => p.value === form.dns) ?? providers[0],
);
const status = ref<{
        available: boolean;
        state?: string;
        ret?: unknown;
        task_id?: number;
    } | null>(null),
    statusError = ref('');
const statusText = computed(() => {
    if (statusError.value) {
        return statusError.value;
    }

    if (!status.value) {
        return '读取中…';
    }

    if (!status.value.available) {
        return '暂不可用';
    }

    if (status.value.ret && status.value.state !== 'done') {
        return textValue(status.value.ret);
    }

    return '没有错误';
});
async function loadStatus(): Promise<void> {
    statusError.value = '';

    try {
        status.value = await apiRequest('/api/admin/dns-setting/status');
    } catch (e) {
        statusError.value = getErrorMessage(e);
        status.value = null;
    }
}
async function loadSetting(): Promise<void> {
    settingsLoading.value = true;
    settingError.value = '';
    settingsReady.value = false;

    try {
        const data = await getAdminDnsSetting();
        Object.assign(form, {
            dns: data.dns ?? 'aliyun',
            id: data.id ?? '',
            token: data.token ?? '',
            ttl: data.ttl ?? 600,
            weight_on: data.weight_on === 0 ? 0 : 1,
        });
        settingsReady.value = true;
    } catch (e) {
        settingError.value = getErrorMessage(e);
    } finally {
        settingsLoading.value = false;
    }
}
async function saveSetting(): Promise<void> {
    if (!settingsReady.value || saving.value) {
        return;
    }

    saving.value = true;
    settingError.value = '';

    try {
        await saveAdminDnsSetting({
            ...form,
            id: form.id.trim(),
            token: form.token.trim(),
            ttl: Number(form.ttl),
        });
        toast.success('保存成功');
        await loadStatus();
    } catch (e) {
        settingError.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}
const repairing = ref(false),
    cleanupOpen = ref(false),
    repairError = ref('');
async function repair(mode: 1 | 2): Promise<void> {
    if (repairing.value) {
        return;
    }

    repairing.value = true;
    repairError.value = '';

    try {
        await apiRequest('/api/admin/dns-setting/repair', {
            method: 'POST',
            body: JSON.stringify({ mode }),
        });
        cleanupOpen.value = false;
        toast.success('请求已提交，结果请到后台任务查看');
        await loadStatus();
    } catch (e) {
        repairError.value = getErrorMessage(e);
    } finally {
        repairing.value = false;
    }
}

const rows = ref<CdnflyRecord[]>([]),
    total = ref(0),
    page = ref(1),
    size = ref(10),
    search = ref(''),
    appliedSearch = ref(''),
    selected = ref<number[]>([]),
    loading = ref(false),
    listError = ref('');
const lastPage = computed(() =>
    Math.max(1, Math.ceil(total.value / size.value)),
);
const pages = computed(() =>
    [
        ...new Set([
            1,
            page.value - 1,
            page.value,
            page.value + 1,
            lastPage.value,
        ]),
    ]
        .filter((p) => p > 0 && p <= lastPage.value)
        .sort((a, b) => a - b),
);
const allSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selected.value.includes(Number(row.id))),
);
let listVersion = 0;
async function loadDomains(target = page.value): Promise<void> {
    const version = ++listVersion;
    loading.value = true;
    listError.value = '';

    try {
        const data = await listAdminCnameDomains({
            page: target,
            limit: size.value,
            ...(appliedSearch.value ? { domain: appliedSearch.value } : {}),
        });

        if (version !== listVersion) {
            return;
        }

        rows.value = extractCdnflyRows(data);
        total.value = extractCdnflyTotal(data, rows.value.length);

        if (target > lastPage.value) {
            await loadDomains(lastPage.value);

            return;
        }

        page.value = target;
        selected.value = selected.value.filter((id) =>
            rows.value.some((r) => Number(r.id) === id),
        );
    } catch (e) {
        if (version !== listVersion) {
            return;
        }

        listError.value = getErrorMessage(e);
        rows.value = [];
        total.value = 0;
        selected.value = [];
    } finally {
        if (version === listVersion) {
            loading.value = false;
        }
    }
}
function switchTab(value: string): void {
    if (tab.value === value) {
        return;
    }

    tab.value = value;

    if (value === 'cnames') {
        void loadDomains();
    }
}
function query(): void {
    appliedSearch.value = search.value.trim();
    selected.value = [];
    void loadDomains(1);
}
function selectRow(id: number, checked: boolean | 'indeterminate'): void {
    selected.value =
        checked === true
            ? [...new Set([...selected.value, id])]
            : selected.value.filter((v) => v !== id);
}
const dialog = ref(false),
    editingId = ref<number | null>(null),
    domainSaving = ref(false),
    domainError = ref('');
const domainForm = reactive({ domain: '', des: '' });
function edit(row?: CdnflyRecord): void {
    editingId.value = row ? Number(row.id) : null;
    Object.assign(domainForm, {
        domain: textValue(row?.domain),
        des: textValue(row?.des),
    });
    domainError.value = '';
    dialog.value = true;
}
async function saveDomain(): Promise<void> {
    if (domainSaving.value) {
        return;
    }

    domainError.value = '';

    if (!domainForm.domain.trim()) {
        domainError.value = '请输入域名';

        return;
    }

    domainSaving.value = true;

    try {
        const data = { domain: domainForm.domain.trim(), des: domainForm.des };

        if (editingId.value) {
            await updateAdminCnameDomain(editingId.value, data);
        } else {
            await createAdminCnameDomain(data);
        }

        dialog.value = false;
        toast.success('保存成功');
        await loadDomains(editingId.value ? page.value : 1);
    } catch (e) {
        domainError.value = getErrorMessage(e);
    } finally {
        domainSaving.value = false;
    }
}
const deleteOpen = ref(false),
    deleteIds = ref<number[]>([]),
    deleting = ref(false),
    deleteError = ref('');
function confirmDelete(ids: number[]): void {
    deleteIds.value = [...ids];
    deleteError.value = '';
    deleteOpen.value = true;
}
async function remove(): Promise<void> {
    if (deleting.value) {
        return;
    }

    deleting.value = true;
    deleteError.value = '';
    const failed: number[] = [],
        messages: string[] = [];

    for (const id of deleteIds.value) {
        try {
            await deleteAdminCnameDomain(id);
        } catch (e) {
            failed.push(id);
            messages.push(`#${id}: ${getErrorMessage(e)}`);
        }
    }

    deleteIds.value = failed;
    await loadDomains();
    selected.value = failed.filter((id) =>
        rows.value.some((row) => Number(row.id) === id),
    );
    deleting.value = false;

    if (failed.length) {
        deleteError.value = messages.join('；');
    } else {
        deleteOpen.value = false;
        toast.success('已删除');
    }
}
onMounted(() => {
    void loadSetting();
    void loadStatus();
});
</script>

<template>
    <div class="min-w-0 flex-1 p-4 md:p-6">
        <section
            class="dns-panel rounded-xl border bg-card p-5 text-card-foreground shadow-sm"
        >
            <div
                role="tablist"
                aria-label="DNS配置分类"
                class="mb-4 flex gap-1"
            >
                <button
                    v-for="item in [
                        { key: 'setting', label: 'DNS配置' },
                        { key: 'cnames', label: 'CNAME域名' },
                    ]"
                    :key="item.key"
                    role="tab"
                    :aria-selected="tab === item.key"
                    class="rounded-md px-4 py-2 text-sm"
                    :class="
                        tab === item.key
                            ? 'bg-primary/10 font-semibold text-primary'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="switchTab(item.key)"
                >
                    {{ item.label }}
                </button>
            </div>
            <template v-if="tab === 'setting'">
                <p
                    v-if="settingError"
                    role="alert"
                    class="mb-4 text-sm text-destructive"
                >
                    {{ settingError }}
                    <button
                        v-if="!settingsReady"
                        class="underline"
                        @click="loadSetting"
                    >
                        重试
                    </button>
                </p>
                <div v-if="settingsLoading" class="py-16">
                    <Spinner class="mx-auto" />
                </div>
                <form
                    v-else
                    class="dns-form grid max-w-[680px] gap-5 pb-4"
                    @submit.prevent="saveSetting"
                >
                    <fieldset
                        :disabled="!settingsReady || saving"
                        class="contents"
                    >
                        <div class="form-row">
                            <Label for="dns-provider">DNS提供商</Label
                            ><SelectField
                                id="dns-provider"
                                v-model="form.dns"
                                class="h-8 w-full max-w-[525px] rounded border bg-background px-2 text-sm"
                            >
                                <SelectOption
                                    v-for="p in providers"
                                    :key="p.value"
                                    :value="p.value"
                                >
                                    {{ p.label }}
                                </SelectOption>
                            </SelectField>
                        </div>
                        <div class="form-row">
                            <Label for="dns-id">{{ provider.id }}</Label
                            ><Input
                                id="dns-id"
                                v-model="form.id"
                                :type="
                                    form.dns === 'cloudflare' ? 'email' : 'text'
                                "
                                required
                                autocomplete="off"
                            />
                        </div>
                        <div class="form-row">
                            <Label for="dns-token">{{ provider.token }}</Label>
                            <div class="relative">
                                <Input
                                    id="dns-token"
                                    v-model="form.token"
                                    :type="showToken ? 'text' : 'password'"
                                    required
                                    autocomplete="new-password"
                                    class="pr-9"
                                /><button
                                    type="button"
                                    :aria-label="
                                        showToken ? '隐藏密钥' : '显示密钥'
                                    "
                                    class="absolute inset-y-0 right-2 text-muted-foreground"
                                    @click="showToken = !showToken"
                                >
                                    <EyeOff
                                        v-if="showToken"
                                        class="size-4"
                                    /><Eye v-else class="size-4" />
                                </button>
                            </div>
                        </div>
                        <div class="form-row">
                            <Label for="dns-ttl">TTL</Label>
                            <div class="flex w-52">
                                <Input
                                    id="dns-ttl"
                                    v-model="form.ttl"
                                    type="number"
                                    min="60"
                                    max="86400"
                                    step="1"
                                    required
                                    class="rounded-r-none"
                                /><span
                                    class="flex items-center rounded-r border border-l-0 bg-muted/30 px-2 text-sm"
                                    >秒</span
                                >
                            </div>
                        </div>
                        <div class="form-row">
                            <Label for="dns-weight">开启IP权重</Label
                            ><Switch
                                id="dns-weight"
                                aria-label="开启IP权重"
                                :checked="form.weight_on === 1"
                                @update:checked="
                                    form.weight_on = form.weight_on ? 0 : 1
                                "
                            />
                        </div>
                    </fieldset>
                    <div class="form-row items-start">
                        <Label class="pt-1">DNS错误</Label>
                        <div>
                            <span
                                class="inline-flex max-w-full items-center gap-1 rounded-full border px-2 py-1 text-xs break-all"
                                :class="
                                    statusText === '没有错误'
                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                        : 'border-border text-muted-foreground'
                                "
                                ><span
                                    v-if="statusText === '没有错误'"
                                    class="size-1.5 rounded-full bg-emerald-500"
                                />{{ statusText }}</span
                            ><button
                                v-if="statusError"
                                type="button"
                                class="ml-2 text-sm text-primary"
                                @click="loadStatus"
                            >
                                重试状态
                            </button>
                        </div>
                    </div>
                    <p
                        v-if="repairError && !cleanupOpen"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ repairError }}
                    </p>
                    <div class="form-row">
                        <span />
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="
                                    !settingsReady || saving || repairing
                                "
                                ><Spinner v-if="saving" /><Check
                                    v-else
                                />保存配置</Button
                            ><Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="
                                    !settingsReady || saving || repairing
                                "
                                @click="repair(1)"
                                ><Spinner v-if="repairing" /><Wrench
                                    v-else
                                />记录修复</Button
                            ><Button
                                type="button"
                                size="sm"
                                class="bg-amber-500 text-white hover:bg-amber-600"
                                :disabled="
                                    !settingsReady || saving || repairing
                                "
                                @click="
                                    repairError = '';
                                    cleanupOpen = true;
                                "
                                ><Trash2 />清除CDN无关解析</Button
                            >
                        </div>
                    </div>
                </form>
            </template>
            <template v-else>
                <div class="mb-3 flex flex-wrap gap-2">
                    <Button size="sm" @click="edit()"><Plus />新增域名</Button
                    ><Button
                        size="sm"
                        variant="destructive"
                        :disabled="loading || !selected.length"
                        @click="confirmDelete(selected)"
                        ><Trash2 />删除</Button
                    >
                    <form class="relative w-56" @submit.prevent="query">
                        <Input
                            v-model="search"
                            aria-label="搜索域名"
                            placeholder="输入域名搜索"
                            class="h-8 pr-8"
                            @input="!search && query()"
                        /><button
                            type="submit"
                            aria-label="搜索"
                            class="absolute inset-y-0 right-2 text-muted-foreground"
                        >
                            <Search class="size-4" />
                        </button>
                    </form>
                </div>
                <p
                    v-if="listError"
                    role="alert"
                    class="mb-3 text-sm text-destructive"
                >
                    {{ listError }}
                    <button class="underline" @click="loadDomains()">
                        重试
                    </button>
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[650px] text-left text-sm">
                        <thead
                            class="border-b bg-muted/30 text-muted-foreground"
                        >
                            <tr>
                                <th class="w-12">
                                    <Checkbox
                                        aria-label="选择本页全部"
                                        :disabled="loading || !rows.length"
                                        :model-value="
                                            allSelected
                                                ? true
                                                : selected.length
                                                  ? 'indeterminate'
                                                  : false
                                        "
                                        @update:model-value="
                                            selected =
                                                $event === true
                                                    ? rows.map((r) =>
                                                          Number(r.id),
                                                      )
                                                    : []
                                        "
                                    />
                                </th>
                                <th>ID</th>
                                <th>域名</th>
                                <th>备注</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="5" class="h-20 text-center">
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <tr
                                v-for="row in loading ? [] : rows"
                                :key="String(row.id)"
                                class="border-b"
                            >
                                <td>
                                    <Checkbox
                                        :aria-label="`选择 ${row.domain}`"
                                        :model-value="
                                            selected.includes(Number(row.id))
                                        "
                                        @update:model-value="
                                            selectRow(Number(row.id), $event)
                                        "
                                    />
                                </td>
                                <td>{{ row.id }}</td>
                                <td>{{ row.domain }}</td>
                                <td>{{ row.des }}</td>
                                <td>
                                    <div class="flex gap-2 text-primary">
                                        <button @click="edit(row)">编辑</button
                                        ><button
                                            @click="
                                                confirmDelete([Number(row.id)])
                                            "
                                        >
                                            删除
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !rows.length">
                                <td
                                    colspan="5"
                                    class="h-14 text-center text-muted-foreground"
                                >
                                    暂无数据
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="mt-4 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <span>共 {{ total }} 条</span
                    ><Button
                        variant="outline"
                        size="icon-sm"
                        aria-label="上一页"
                        :disabled="loading || page <= 1"
                        @click="loadDomains(page - 1)"
                        ><ChevronLeft /></Button
                    ><Button
                        v-for="p in pages"
                        :key="p"
                        variant="outline"
                        size="icon-sm"
                        :aria-label="`第 ${p} 页`"
                        :aria-current="p === page ? 'page' : undefined"
                        :class="p === page ? 'border-primary text-primary' : ''"
                        :disabled="loading"
                        @click="loadDomains(p)"
                        >{{ p }}</Button
                    ><Button
                        variant="outline"
                        size="icon-sm"
                        aria-label="下一页"
                        :disabled="loading || page >= lastPage"
                        @click="loadDomains(page + 1)"
                        ><ChevronRight /></Button
                    ><SelectField
                        v-model.number="size"
                        aria-label="每页条数"
                        class="ml-2 h-8 rounded border bg-background px-2"
                        @change="
                            selected = [];
                            loadDomains(1);
                        "
                    >
                        <SelectOption
                            v-for="n in [10, 20, 50, 100]"
                            :key="n"
                            :value="n"
                        >
                            {{ n }} 条/页
                        </SelectOption>
                    </SelectField>
                </div>
            </template>
        </section>
        <Dialog
            :open="dialog"
            @update:open="(value) => !domainSaving && (dialog = value)"
            ><DialogScrollContent
                class="my-5 w-[calc(100%_-_2rem)] self-start bg-card sm:max-w-[490px]"
                ><DialogHeader
                    ><DialogTitle
                        >{{ editingId ? '编辑' : '新增' }}域名</DialogTitle
                    ><DialogDescription class="sr-only"
                        >设置CNAME域名和备注</DialogDescription
                    ></DialogHeader
                >
                <form
                    id="cname-form"
                    class="grid gap-5 py-3"
                    @submit.prevent="saveDomain"
                >
                    <p
                        v-if="domainError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ domainError }}
                    </p>
                    <div class="form-row">
                        <Label for="cname-domain">域名：</Label
                        ><Input
                            id="cname-domain"
                            v-model="domainForm.domain"
                            required
                            maxlength="255"
                            placeholder="请输入域名"
                        />
                    </div>
                    <div class="form-row">
                        <Label for="cname-des">备注：</Label
                        ><Input
                            id="cname-des"
                            v-model="domainForm.des"
                            maxlength="500"
                            placeholder="请输入备注"
                        />
                    </div>
                </form>
                <DialogFooter class="border-t pt-3"
                    ><Button
                        type="submit"
                        form="cname-form"
                        size="sm"
                        :disabled="domainSaving"
                        ><Spinner v-if="domainSaving" /><Check
                            v-else
                        />确定</Button
                    ><Button
                        variant="outline"
                        size="sm"
                        :disabled="domainSaving"
                        @click="dialog = false"
                        >取消</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <ConfirmDeleteDialog
            :open="deleteOpen"
            title="删除域名"
            :description="`是否删除域名 id: ${deleteIds.join(', ')}？`"
            :loading="deleting"
            :error="deleteError"
            @confirm="remove"
            @cancel="!deleting && (deleteOpen = false)"
        />
        <Dialog
            :open="cleanupOpen"
            @update:open="(value) => !repairing && (cleanupOpen = value)"
            ><DialogScrollContent class="bg-card sm:max-w-md"
                ><DialogHeader
                    ><DialogTitle>清除CDN无关解析</DialogTitle
                    ><DialogDescription
                        >清除已配置CNAME域名下与CDN无关的解析记录。</DialogDescription
                    ></DialogHeader
                >
                <p
                    v-if="repairError"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ repairError }}
                </p>
                <DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="repairing"
                        @click="cleanupOpen = false"
                        >取消</Button
                    ><Button
                        class="bg-amber-500 text-white hover:bg-amber-600"
                        :disabled="repairing"
                        @click="repair(2)"
                        ><Spinner v-if="repairing" />确认清除</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
    </div>
</template>

<style scoped>
.form-row {
    display: grid;
    grid-template-columns: 92px minmax(0, 1fr);
    align-items: center;
    gap: 12px;
}
.form-row.items-start {
    align-items: start;
}
.form-row label {
    justify-content: flex-end;
    font-weight: 400;
}
.form-row input {
    height: 32px;
}
.dns-panel th {
    height: 36px;
    padding: 0 12px;
    font-weight: 500;
}
.dns-panel td {
    height: 46px;
    padding: 8px 12px;
}
@media (max-width: 540px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .form-row label {
        justify-content: flex-start;
    }
    .form-row > span:empty {
        display: none;
    }
}
</style>
