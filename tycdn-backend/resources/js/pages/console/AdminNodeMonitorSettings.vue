<script setup lang="ts">
import { Copy } from 'lucide-vue-next';
import { onMounted, ref, computed, reactive } from 'vue';
import { toast } from 'vue-sonner';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import MonitorChoices from '@/components/console/MonitorChoices.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { Spinner } from '@/components/ui/spinner';
import Switch from '@/components/ui/switch/Switch.vue';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import { getErrorMessage, textValue } from '@/lib/formatters';
import { masterGet } from '@/lib/masterApi';
import {
    decodeMonitorConfig,
    encodeMonitorConfig,
    monitorGroups,
    monitorEvents,
    monitorTemplates,
    smsTemplate,
} from '@/lib/nodeMonitor';
import type { MonitorConfig } from '@/lib/nodeMonitor';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const tab = ref('monitor'),
    loading = ref(true),
    loadError = ref(''),
    saveError = ref(''),
    saving = ref(false),
    saved = ref(false);
const config = ref<MonitorConfig | null>(null),
    row = ref<CdnflyRecord | null>(null),
    smsProvider = ref(''),
    smsError = ref('');
const periodMode = ref('custom'),
    templateType = ref('email');
let committed = '',
    queued: string | null = null;
async function load(): Promise<void> {
    loading.value = true;
    loadError.value = '';

    try {
        const rows = extractCdnflyRows(await apiRequest('/api/admin/configs'));
        const match = rows.find(
            (r) =>
                r.name === 'node_monitor_config' &&
                r.type === 'system' &&
                r.scope_name === 'global' &&
                Number(r.scope_id) === 0,
        );

        if (!match) {
            throw new Error('未找到节点监控配置');
        }

        const parsed = decodeMonitorConfig(match.value);
        row.value = match;
        config.value = parsed;
        committed = encodeMonitorConfig(parsed);
        periodMode.value =
            parsed.notification_period === '0-24' ? 'allDay' : 'custom';
        smsError.value = '';

        try {
            const sms = rows.find(
                (r) =>
                    r.name === 'sms_config' &&
                    r.type === 'system' &&
                    r.scope_name === 'global' &&
                    Number(r.scope_id) === 0,
            );
            smsProvider.value = String(
                JSON.parse(String(sms?.value ?? '{}')).type ?? '',
            );
        } catch {
            smsProvider.value = '';
            smsError.value = '无法读取短信提供商，暂不能转换模板';
        }
    } catch (e) {
        loadError.value = getErrorMessage(e);
        config.value = null;
    } finally {
        loading.value = false;
    }
}
async function save(): Promise<void> {
    if (!config.value || !row.value) {
        return;
    }

    saved.value = false;
    saveError.value = '';

    try {
        queued = encodeMonitorConfig(config.value);
    } catch (e) {
        saveError.value = getErrorMessage(e);

        return;
    }

    if (saving.value) {
        return;
    }

    saving.value = true;

    try {
        while (queued !== null) {
            const value = queued;
            queued = null;

            if (value === committed) {
                continue;
            }

            try {
                await apiRequest('/api/admin/configs', {
                    method: 'PUT',
                    body: JSON.stringify({
                        name: 'node_monitor_config',
                        type: 'system',
                        scope_name: 'global',
                        scope_id: 0,
                        value,
                        ...(row.value.enable === undefined
                            ? {}
                            : { enable: Number(row.value.enable) }),
                    }),
                });
                committed = value;
            } catch (e) {
                saveError.value = getErrorMessage(e);
                queued = null;

                return;
            }
        }

        saved.value = !saveError.value;
    } finally {
        saving.value = false;
    }
}
function choose(key: string, value: unknown): void {
    if (config.value) {
        config.value[key] = value;
        void save();
    }
}
function period(value: unknown): void {
    periodMode.value = String(value);

    if (value === 'allDay') {
        choose('notification_period', '0-24');
    }
}
async function copyTemplate(key: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(
            smsTemplate(textValue(config.value?.[key]), smsProvider.value),
        );
        toast.success('模板已复制');
    } catch (e) {
        toast.error(getErrorMessage(e));
    }
}
const basicNumbers = [
    {
        key: 'check_port',
        label: '默认监控端口',
        unit: '',
        min: 1,
        max: 65536,
        step: 1,
    },
    {
        key: 'check_timeout',
        label: '默认检查超时',
        unit: '秒',
        min: 0.01,
        max: 5,
        step: 0.01,
    },
];
const thresholds = [
    { key: 'failed_times', label: '连续失败次数', unit: '', min: 1, max: 10 },
    {
        key: 'failed_rate',
        label: '监控点失败比率',
        unit: '%',
        min: 1,
        max: 100,
    },
    {
        key: 'bw_exceed_times',
        label: '连续带宽超限次数',
        unit: '',
        min: 1,
        max: Number.MAX_SAFE_INTEGER,
    },
];
const textFields = [
    { key: 'email', label: '通知的邮箱地址', placeholder: '输入邮箱地址' },
    { key: 'phone', label: '通知的手机号码', placeholder: '输入手机号码' },
];
const filters = reactive({
    type: '',
    action: '',
    ip: '',
    node_group_id: '',
    node_id: '',
    line_id: '',
});
const filterInputs = [
    { key: 'ip', label: '节点IP' },
    { key: 'node_group_id', label: '线路组ID' },
    { key: 'node_id', label: '节点ID' },
    { key: 'line_id', label: '线路ID' },
] as const;
const logs = ref<CdnflyRecord[]>([]),
    logLoading = ref(false),
    logError = ref(''),
    page = ref(1),
    size = ref(10),
    total = ref(0);
const lastPage = computed(() =>
    Math.max(1, Math.ceil(total.value / size.value)),
);

let logVersion = 0;
async function loadLogs(target = page.value): Promise<void> {
    const version = ++logVersion;
    logLoading.value = true;
    logError.value = '';

    try {
        const data = await masterGet('ip-switch', {
            page: target,
            limit: size.value,
            ...Object.fromEntries(
                Object.entries(filters)
                    .filter(([, v]) => v.trim())
                    .map(([k, v]) => [k, v.trim()]),
            ),
        });

        if (version !== logVersion) {
            return;
        }

        logs.value = extractCdnflyRows(data);
        total.value = extractCdnflyTotal(data, logs.value.length);

        if (target > lastPage.value) {
            await loadLogs(lastPage.value);

            return;
        }

        page.value = target;
    } catch (e) {
        if (version !== logVersion) {
            return;
        }

        logError.value = getErrorMessage(e);
        logs.value = [];
        total.value = 0;
    } finally {
        if (version === logVersion) {
            logLoading.value = false;
        }
    }
}
function switchTab(value: string): void {
    tab.value = value;

    if (value === 'logs') {
        void loadLogs();
    }
}
function clearFilters(): void {
    Object.assign(filters, {
        type: '',
        action: '',
        ip: '',
        node_group_id: '',
        node_id: '',
        line_id: '',
    });
    void loadLogs(1);
}
const detail = ref<CdnflyRecord | null>(null),
    detailOpen = ref(false);
function channelStatus(channel: string): string {
    const r = detail.value ?? {},
        need = r[`${channel}_need_send`],
        sent = r[`${channel}_is_sent`],
        state = r[`${channel}_send_state`];

    if (need === null || need === undefined || need === '') {
        return '--';
    }

    if (Number(need) !== 1) {
        return '无需发送';
    }

    if (Number(sent) === 1 || state === 'done') {
        return '发送完成';
    }

    return (
        (
            {
                failed: '发送失败',
                process: '发送中',
                pending: '待发送',
            } as Record<string, string>
        )[String(state)] ?? '未发送'
    );
}
function yesNo(value: unknown): string {
    return value === null || value === undefined || value === ''
        ? '--'
        : Number(value) === 1
          ? '是'
          : '否';
}
onMounted(load);
</script>

<template>
    <div class="min-w-0 flex-1 p-4 md:p-6">
        <section
            class="monitor-panel rounded-xl border bg-card p-5 text-card-foreground shadow-sm"
        >
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold">节点监控设置</h2>
                <span
                    v-if="saving"
                    role="status"
                    class="text-xs text-muted-foreground"
                    >保存中…</span
                ><span
                    v-else-if="saved"
                    role="status"
                    class="text-xs text-emerald-600 dark:text-emerald-400"
                    >已保存</span
                >
            </div>
            <div
                role="tablist"
                aria-label="节点监控设置"
                class="mb-4 flex gap-1"
            >
                <button
                    v-for="item in [
                        { key: 'monitor', label: '监控配置' },
                        { key: 'notify', label: '通知配置' },
                        { key: 'logs', label: '切换日志' },
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
            <p
                v-if="saveError"
                role="alert"
                class="mb-4 text-sm text-destructive"
            >
                {{ saveError }}
                <button class="underline" :disabled="saving" @click="save">
                    重试保存
                </button>
            </p>
            <template v-if="tab !== 'logs'">
                <div v-if="loading" class="py-16">
                    <Spinner class="mx-auto" />
                </div>
                <p
                    v-else-if="loadError"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ loadError }}
                    <button class="underline" @click="load">重试加载</button>
                </p>
                <div v-else-if="config" class="max-w-[1050px] space-y-4">
                    <template v-if="tab === 'monitor'">
                        <section class="settings-section">
                            <div class="flex justify-between">
                                <h3>探测基础</h3>
                                <span
                                    class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-1 text-xs text-emerald-600 dark:text-emerald-400"
                                    >{{
                                        config.global_check_on
                                            ? '监控已开启'
                                            : '监控已关闭'
                                    }}</span
                                >
                            </div>
                            <p class="section-hint">
                                配置节点默认探测方式和请求参数，新建节点会优先沿用这里的默认值。
                            </p>
                            <div class="form-row">
                                <Label for="monitor-enabled">监控开关</Label
                                ><Switch
                                    id="monitor-enabled"
                                    aria-label="监控开关"
                                    :checked="config.global_check_on === 1"
                                    @update:checked="
                                        choose(
                                            'global_check_on',
                                            config.global_check_on ? 0 : 1,
                                        )
                                    "
                                />
                            </div>
                            <div class="form-row">
                                <Label>默认监控协议</Label
                                ><MonitorChoices
                                    label="默认监控协议"
                                    :model-value="config.check_protocol"
                                    :options="[
                                        { value: 'http', label: 'HTTP' },
                                        { value: 'tcp', label: 'TCP' },
                                        { value: 'ping', label: 'PING' },
                                    ]"
                                    @update:model-value="
                                        choose('check_protocol', $event)
                                    "
                                />
                            </div>
                            <template
                                v-for="field in basicNumbers"
                                :key="field.key"
                                ><div
                                    v-if="
                                        field.key !== 'check_port' ||
                                        config.check_protocol !== 'ping'
                                    "
                                    class="form-row"
                                >
                                    <Label :for="field.key">{{
                                        field.label
                                    }}</Label>
                                    <div
                                        data-slot="console-input-group"
                                        class="number-field"
                                    >
                                        <Input
                                            :id="field.key"
                                            :model-value="
                                                textValue(config[field.key])
                                            "
                                            type="number"
                                            :min="field.min"
                                            :max="field.max"
                                            :step="field.step"
                                            @update:model-value="
                                                config[field.key] = $event
                                            "
                                            @change="save"
                                        /><span v-if="field.unit">{{
                                            field.unit
                                        }}</span>
                                    </div>
                                </div></template
                            >
                            <template v-if="config.check_protocol === 'http'"
                                ><div
                                    v-for="field in [
                                        {
                                            key: 'check_host',
                                            label: '默认监控域名',
                                        },
                                        {
                                            key: 'check_path',
                                            label: '默认监控路径',
                                        },
                                    ]"
                                    :key="field.key"
                                    class="form-row"
                                >
                                    <Label :for="field.key">{{
                                        field.label
                                    }}</Label
                                    ><Input
                                        :id="field.key"
                                        :model-value="
                                            textValue(config[field.key])
                                        "
                                        class="w-full max-w-[328px]"
                                        @update:model-value="
                                            config[field.key] = $event
                                        "
                                        @change="save"
                                    /></div
                            ></template>
                        </section>
                        <section class="settings-section">
                            <h3>判定策略</h3>
                            <p class="section-hint">
                                设置失败阈值和处理动作，决定节点在异常时是否自动暂停。
                            </p>
                            <div class="form-row items-start">
                                <Label class="pt-2">默认监控组</Label>
                                <div>
                                    <MonitorChoices
                                        label="默认监控组"
                                        :model-value="config.check_node_group"
                                        :options="monitorGroups"
                                        multiple
                                        @update:model-value="
                                            choose('check_node_group', $event)
                                        "
                                    />
                                    <p
                                        class="mt-2 max-w-[640px] rounded border bg-card p-2 text-xs leading-6 text-muted-foreground"
                                    >
                                        选择多个监控组时，任意一个监控组里的监控点失败比率高于设定的值，则会禁用此节点。比如选择电信、联通和移动组，表示节点在电信、联通和移动三个运营商的任意一个运营商不可用，则会禁用此节点。
                                    </p>
                                </div>
                            </div>
                            <div class="form-row">
                                <Label>默认动作</Label
                                ><MonitorChoices
                                    label="默认动作"
                                    :model-value="config.check_action"
                                    :options="[
                                        { value: 'none', label: '不处理' },
                                        { value: 'pause', label: '暂停' },
                                    ]"
                                    @update:model-value="
                                        choose('check_action', $event)
                                    "
                                />
                            </div>
                            <div class="form-row">
                                <Label>间隔时间</Label
                                ><MonitorChoices
                                    label="间隔时间"
                                    :model-value="config.interval"
                                    :options="[
                                        { value: 30, label: '30秒' },
                                        { value: 60, label: '1分钟' },
                                        { value: 300, label: '5分钟' },
                                    ]"
                                    @update:model-value="
                                        choose('interval', $event)
                                    "
                                />
                            </div>
                            <div
                                v-for="field in thresholds"
                                :key="field.key"
                                class="form-row"
                            >
                                <Label :for="field.key">{{
                                    field.label
                                }}</Label>
                                <div
                                    data-slot="console-input-group"
                                    class="number-field"
                                >
                                    <Input
                                        :id="field.key"
                                        :model-value="
                                            textValue(config[field.key])
                                        "
                                        type="number"
                                        :min="field.min"
                                        :max="field.max"
                                        step="1"
                                        @update:model-value="
                                            config[field.key] = $event
                                        "
                                        @change="save"
                                    /><span v-if="field.unit">{{
                                        field.unit
                                    }}</span>
                                </div>
                            </div>
                        </section>
                    </template>
                    <template v-else>
                        <section class="settings-section">
                            <h3>通知渠道</h3>
                            <p class="section-hint">
                                选择发送时间、接收方式和订阅事件，修改后会自动保存。
                            </p>
                            <div class="form-row items-start">
                                <Label class="pt-2">通知时间段</Label>
                                <div>
                                    <MonitorChoices
                                        label="通知时间段"
                                        :model-value="periodMode"
                                        :options="[
                                            { value: 'allDay', label: '全天' },
                                            {
                                                value: 'custom',
                                                label: '自定义',
                                            },
                                        ]"
                                        @update:model-value="period"
                                    /><Input
                                        v-if="periodMode === 'custom'"
                                        aria-label="自定义通知时间段"
                                        :model-value="
                                            textValue(
                                                config.notification_period,
                                            )
                                        "
                                        class="mt-1 max-w-52"
                                        placeholder="8-22"
                                        @update:model-value="
                                            config.notification_period = $event
                                        "
                                        @change="save"
                                    />
                                </div>
                            </div>
                            <div class="form-row">
                                <Label>通知方式</Label
                                ><MonitorChoices
                                    label="通知方式"
                                    :model-value="config.notify_method"
                                    :options="[
                                        { value: 'email', label: '电子邮件' },
                                        { value: 'sms', label: '手机短信' },
                                    ]"
                                    multiple
                                    @update:model-value="
                                        choose('notify_method', $event)
                                    "
                                />
                            </div>
                            <div class="form-row">
                                <Label>消息类型订阅</Label
                                ><MonitorChoices
                                    label="消息类型订阅"
                                    :model-value="config.notify_msg_type"
                                    :options="monitorEvents"
                                    multiple
                                    @update:model-value="
                                        choose('notify_msg_type', $event)
                                    "
                                />
                            </div>
                            <div
                                v-for="field in textFields"
                                :key="field.key"
                                class="form-row"
                            >
                                <Label :for="field.key">{{ field.label }}</Label
                                ><Input
                                    :id="field.key"
                                    :model-value="textValue(config[field.key])"
                                    :placeholder="field.placeholder"
                                    class="max-w-[328px]"
                                    @update:model-value="
                                        config[field.key] = $event
                                    "
                                    @change="save"
                                />
                            </div>
                        </section>
                        <section class="settings-section">
                            <h3>通知模板</h3>
                            <p class="section-hint">
                                按通知类型维护邮件正文或短信模板
                                ID，短信模式可复制转换后的模板内容。
                            </p>
                            <div class="form-row">
                                <Label>模板类型</Label
                                ><MonitorChoices
                                    label="模板类型"
                                    v-model="templateType"
                                    :options="[
                                        { value: 'email', label: '邮件模板' },
                                        { value: 'sms', label: '短信模板' },
                                    ]"
                                />
                            </div>
                            <p
                                v-if="smsError && templateType === 'sms'"
                                class="mb-3 text-sm text-destructive"
                            >
                                {{ smsError }}
                            </p>
                            <div
                                v-for="group in monitorTemplates"
                                :key="group.label"
                                class="form-row items-start"
                            >
                                <Label class="pt-2">{{ group.label }}</Label>
                                <div class="grid max-w-[710px] gap-2">
                                    <div
                                        v-for="item in group.items"
                                        :key="item.key"
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <div
                                            class="flex min-w-0 flex-1 basis-[400px]"
                                        >
                                            <Label
                                                :for="item.key"
                                                class="template-label"
                                                >{{ item.label
                                                }}{{
                                                    templateType === 'sms'
                                                        ? 'ID'
                                                        : ''
                                                }}</Label
                                            ><Input
                                                :id="item.key"
                                                :model-value="
                                                    textValue(
                                                        config[
                                                            item.key +
                                                                (templateType ===
                                                                'sms'
                                                                    ? '_id'
                                                                    : '')
                                                        ],
                                                    )
                                                "
                                                class="min-w-0 rounded-l-none"
                                                @update:model-value="
                                                    config[
                                                        item.key +
                                                            (templateType ===
                                                            'sms'
                                                                ? '_id'
                                                                : '')
                                                    ] = $event
                                                "
                                                @change="save"
                                            />
                                        </div>
                                        <Button
                                            v-if="templateType === 'sms'"
                                            variant="outline"
                                            size="sm"
                                            :disabled="
                                                ![
                                                    'aliyun',
                                                    'qcloud',
                                                    'smsbao',
                                                    'submail',
                                                ].includes(smsProvider)
                                            "
                                            @click="copyTemplate(item.key)"
                                            ><Copy />复制模板</Button
                                        >
                                    </div>
                                </div>
                            </div>
                        </section>
                    </template>
                </div>
            </template>
            <template v-else>
                <form
                    class="mb-3 flex flex-wrap items-center gap-2 rounded border bg-muted/10 p-3"
                    @submit.prevent="loadLogs(1)"
                >
                    <SelectField
                        v-model="filters.type"
                        aria-label="日志类型"
                        class="h-8 w-44 rounded border bg-background px-2 text-sm"
                        @change="loadLogs(1)"
                    >
                        <SelectOption value="">所有类型</SelectOption>
                        <SelectOption
                            v-for="event in monitorEvents"
                            :key="event.value"
                            :value="event.value"
                        >
                            {{ event.value }}
                        </SelectOption></SelectField
                    ><SelectField
                        v-model="filters.action"
                        aria-label="日志动作"
                        class="h-8 w-44 rounded border bg-background px-2 text-sm"
                        @change="loadLogs(1)"
                    >
                        <SelectOption value="">所有动作</SelectOption>
                        <SelectOption value="启用">启用</SelectOption>
                        <SelectOption value="禁用">禁用</SelectOption>
                    </SelectField>
                    <div
                        v-for="field in filterInputs"
                        :key="field.key"
                        class="flex w-44"
                    >
                        <Label
                            :for="`filter-${field.key}`"
                            class="flex shrink-0 items-center rounded-l border border-r-0 bg-card px-2 text-sm font-normal"
                            >{{ field.label }}</Label
                        ><Input
                            :id="`filter-${field.key}`"
                            v-model="filters[field.key]"
                            :placeholder="`请输入${field.label}`"
                            class="min-w-0 rounded-l-none"
                            @change="loadLogs(1)"
                        />
                    </div>
                    <button
                        type="button"
                        class="text-sm text-primary"
                        @click="clearFilters"
                    >
                        清除
                    </button>
                </form>
                <p
                    v-if="logError"
                    role="alert"
                    class="mb-3 text-sm text-destructive"
                >
                    {{ logError }}
                    <button class="underline" @click="loadLogs()">重试</button>
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[950px] text-left text-sm">
                        <thead
                            class="border-b bg-muted/30 text-muted-foreground"
                        >
                            <tr>
                                <th
                                    v-for="title in [
                                        '切换时间',
                                        '类型',
                                        '线路组',
                                        '节点id',
                                        '线路id',
                                        'IP',
                                        '动作',
                                        '操作',
                                    ]"
                                    :key="title"
                                >
                                    {{ title }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="logLoading">
                                <td colspan="8" class="h-20 text-center">
                                    <Spinner class="mx-auto" />
                                </td>
                            </tr>
                            <tr
                                v-for="(log, index) in logLoading ? [] : logs"
                                :key="textValue(log.id) || index"
                                class="border-b"
                            >
                                <td>{{ log.create_at }}</td>
                                <td>{{ log.type }}</td>
                                <td>{{ log.node_group_id }}</td>
                                <td>{{ log.node_id }}</td>
                                <td>{{ log.line_id }}</td>
                                <td>{{ log.ip }}</td>
                                <td>{{ log.action }}</td>
                                <td>
                                    <button
                                        class="text-primary"
                                        @click="
                                            detail = log;
                                            detailOpen = true;
                                        "
                                    >
                                        通知详情
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!logLoading && !logs.length">
                                <td
                                    colspan="8"
                                    class="h-14 text-center text-muted-foreground"
                                >
                                    暂无数据
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ConsolePagination
                    :total="total"
                    :page="page"
                    :previous-disabled="logLoading || page <= 1"
                    :next-disabled="logLoading || page >= lastPage"
                    @previous="loadLogs(page - 1)"
                    @next="loadLogs(page + 1)"
                />
            </template>
        </section>
        <Dialog v-model:open="detailOpen"
            ><DialogScrollContent class="bg-card sm:max-w-[760px]"
                ><DialogHeader
                    ><DialogTitle>通知详情</DialogTitle
                    ><DialogDescription
                        >本次切换的邮件和短信发送结果</DialogDescription
                    ></DialogHeader
                >
                <div v-if="detail" class="grid gap-4 sm:grid-cols-2">
                    <section
                        v-for="channel in [
                            { key: 'email', label: '电子邮件' },
                            { key: 'phone', label: '手机短信' },
                        ]"
                        :key="channel.key"
                        class="rounded border p-4"
                    >
                        <h3 class="mb-3 font-semibold">
                            {{ channel.label }} ·
                            {{ channelStatus(channel.key) }}
                        </h3>
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <dt>需要发送</dt>
                            <dd>
                                {{ yesNo(detail[channel.key + '_need_send']) }}
                            </dd>
                            <dt>已发送</dt>
                            <dd>
                                {{ yesNo(detail[channel.key + '_is_sent']) }}
                            </dd>
                            <dt>失败次数</dt>
                            <dd>
                                {{ detail[channel.key + '_fail_times'] ?? 0 }}
                            </dd>
                            <dt>发送时间</dt>
                            <dd>{{ detail[channel.key + '_time'] || '--' }}</dd>
                        </dl>
                        <p class="mt-4 text-sm break-all whitespace-pre-wrap">
                            {{
                                textValue(detail[channel.key + '_ret']) || '--'
                            }}
                        </p>
                    </section>
                </div></DialogScrollContent
            ></Dialog
        >
    </div>
</template>

<style scoped>
.settings-section {
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--muted) 15%, var(--card));
    padding: 16px;
}
.settings-section h3 {
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 7px;
}
.settings-section h3::before {
    content: '';
    width: 3px;
    height: 14px;
    background: var(--primary);
    border-radius: 2px;
}
.section-hint {
    font-size: 12px;
    color: var(--muted-foreground);
    margin: 6px 0 16px;
}
.form-row {
    display: grid;
    grid-template-columns: 110px minmax(0, 1fr);
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}
.form-row:last-child {
    margin-bottom: 8px;
}
.form-row.items-start {
    align-items: start;
}
.form-row > label {
    justify-content: flex-end;
    text-align: right;
    font-weight: 400;
    line-height: 1.3;
}
.form-row input,
.monitor-panel input {
    height: 32px;
}
.number-field {
    display: flex;
    max-width: 328px;
}
.number-field input {
    min-width: 0;
}
.number-field:has(span) input {
    border-radius: 4px 0 0 4px;
}
.number-field span {
    display: flex;
    align-items: center;
    border: 1px solid var(--border);
    border-left: 0;
    padding: 0 8px;
    font-size: 12px;
    border-radius: 0 4px 4px 0;
    background: var(--card);
}
.template-label {
    display: flex;
    align-items: center;
    white-space: nowrap;
    border: 1px solid var(--border);
    border-right: 0;
    padding: 0 8px;
    font-size: 13px;
    font-weight: 400;
    background: var(--card);
}
.monitor-panel th {
    height: 36px;
    padding: 0 10px;
    font-weight: 500;
}
.monitor-panel td {
    height: 46px;
    padding: 8px 10px;
}
@media (max-width: 600px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .form-row > label {
        justify-content: flex-start;
        text-align: left;
    }
    .settings-section {
        padding: 12px;
    }
    .template-label {
        font-size: 11px;
        padding: 0 4px;
    }
}
</style>
