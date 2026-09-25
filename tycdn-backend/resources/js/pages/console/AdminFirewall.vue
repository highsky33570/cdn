<script setup lang="ts">
import { Plus, RefreshCw, Save, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsolePagination from '@/components/console/ConsolePagination.vue';
import FirewallField from '@/components/console/FirewallField.vue';
import { Button } from '@/components/ui/button';
import CheckboxField from '@/components/ui/checkbox/CheckboxField.vue';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows, extractCdnflyTotal } from '@/lib/cdnflyResponse';
import {
    flag,
    object,
    getPath,
    setPath,
    templates,
    limitFields,
    recommendedLimits,
    validateLimits,
    overrideNames,
} from '@/lib/firewallSettings';
import type { CdnflyRecord } from '@/lib/sharedTypes';

const tab = ref('cc'),
    config = reactive<CdnflyRecord>({}),
    loading = ref(true),
    ready = ref(false),
    saving = ref(false),
    error = ref(''),
    saved = ref(false),
    imageBusy = ref(false);
const rules = ref<CdnflyRecord[]>([]),
    ccPage = ref('slider_html');
const ruleOptions = computed(() =>
    rules.value.map((row) => ({
        value: String(row.id),
        label: String(row.name),
    })),
);
const internalRules = ref<{ period: string | number; reqs: string | number }[]>(
    [],
);
const message = (e: unknown) =>
    e instanceof Error ? e.message : '操作失败，请重试';
const clone = (v: unknown) => JSON.parse(JSON.stringify(v));
const value = (path: string) => getPath(config, path);
const options = (entries: [string | number, string][]) =>
    entries.map(([value, label]) => ({ value, label }));
const cleanup = options([
    [0, '不清理'],
    [1, '节点空间不足时，清空访问日志'],
    [2, '同时清空访问日志和缓存'],
]);
const blockWays = options([
    ['ipset', 'ipset(全局)'],
    ['exit', '断开连接 (网站级别)'],
    ['page', '返回页面 (网站级别)'],
]);
const limitGroups = ['参数检测', 'JSON 内容', '请求内容与上传'];
let pending: CdnflyRecord = {};
onMounted(async () => {
    void apiRequest('/api/admin/cc/rule?limit=0&internal=1')
        .then((data) => {
            rules.value = extractCdnflyRows(data);
        })
        .catch((e) => toast.error(message(e)));
    await load();
});
async function load() {
    loading.value = true;
    error.value = '';

    try {
        const data = await apiRequest<CdnflyRecord>('/api/admin/firewall');
        Object.assign(config, data);
        config.waf_resource_limits = {
            ...recommendedLimits(),
            ...object(data.waf_resource_limits),
        };
        config.log = {
            log_level: 'info',
            host: '127.0.0.1',
            port: 514,
            ...object(data.log),
        };
        config.auto_switch = {
            enable: false,
            qps_50x: 10,
            qps_total: 500,
            rule: 2,
            seconds: 300,
            ...object(data.auto_switch),
        };
        internalRules.value = Array.from({ length: 3 }, (_, i) => {
            const row = object(
                (data.internal_rule as unknown[] | undefined)?.[i],
            );

            return {
                period: String(row.period ?? ''),
                reqs: String(row.reqs ?? ''),
            };
        });
        ready.value = true;
    } catch (e) {
        error.value = message(e);
    } finally {
        loading.value = false;
    }
}
async function change(path: string, next: unknown) {
    setPath(config, path, next);
    saved.value = false;
    const key = path.split('.')[0];

    if (key === 'white_time' && Number(next) < 600) {
        error.value = '临时白名单时间不能小于 600 秒';

        return;
    }

    if (key === 'waf_resource_limits') {
        const problem = validateLimits(object(config.waf_resource_limits));

        if (problem) {
            error.value = problem;

            return;
        }
    }

    pending[key] = clone(config[key]);
    await flush();
}
async function flush() {
    if (saving.value || !Object.keys(pending).length) {
        return;
    }

    saving.value = true;
    error.value = '';

    while (Object.keys(pending).length) {
        const patch = pending;
        pending = {};

        try {
            await apiRequest('/api/admin/firewall', {
                method: 'PUT',
                body: JSON.stringify({ patch }),
            });
            saved.value = true;
        } catch (e) {
            pending = { ...patch, ...pending };
            error.value = message(e);
            saved.value = false;
            break;
        }
    }

    saving.value = false;
}
async function saveInternal() {
    const rows = internalRules.value.map((row) => ({
        period: row.period === '' ? '' : Number(row.period),
        reqs: row.reqs === '' ? '' : Number(row.reqs),
    }));

    if (
        rows.some(
            (row) =>
                (row.period === '' && row.reqs !== '') ||
                (row.period !== '' && row.reqs === '') ||
                (row.period !== '' &&
                    (!Number.isInteger(row.period) ||
                        Number(row.period) < 1 ||
                        !Number.isInteger(row.reqs) ||
                        Number(row.reqs) < 1)),
        )
    ) {
        error.value = '请为每条规则填写有效的统计时长和最大次数，或同时留空';

        return;
    }

    await change('internal_rule', rows);
}
async function updateImages() {
    if (saving.value || Object.keys(pending).length) {
        error.value = '请先保存图片配置后重试';

        return;
    }

    imageBusy.value = true;

    try {
        await apiRequest('/api/admin/firewall/images', { method: 'POST' });
        toast.success('已通知节点更新图片，可在后台任务中查看进度');
    } catch (e) {
        error.value = message(e);
    } finally {
        imageBusy.value = false;
    }
}
const overrides = ref<CdnflyRecord[]>([]),
    overrideLoading = ref(false),
    overrideError = ref(''),
    overrideTotal = ref(0),
    page = ref(1),
    selected = ref<string[]>([]);
const rowKey = (row: CdnflyRecord) => `${row.scope_name}/${row.scope_id}`;
async function loadOverrides(target = page.value) {
    overrideLoading.value = true;
    overrideError.value = '';
    page.value = target;
    selected.value = [];

    try {
        const data = await apiRequest(
            `/api/admin/firewall/overrides?page=${target}&limit=10`,
        );
        overrides.value = extractCdnflyRows(data);
        overrideTotal.value = extractCdnflyTotal(data, overrides.value.length);

        if (target > 1 && !overrides.value.length) {
            await loadOverrides(target - 1);
        }
    } catch (e) {
        overrideError.value = message(e);
    } finally {
        overrideLoading.value = false;
    }
}
function selectTab(next: string) {
    tab.value = next;

    if (next === 'overrides') {
        void loadOverrides();
    }
}
function overrideSummary(row: CdnflyRecord) {
    try {
        return Object.keys(object(JSON.parse(String(row.value))))
            .map((key) => overrideNames[key] || key)
            .join('、');
    } catch {
        return '配置格式错误';
    }
}
const editorOpen = ref(false),
    editorBusy = ref(false),
    editorError = ref(''),
    editing = ref(false),
    scope = ref('node'),
    scopeId = ref(''),
    item = ref(''),
    editConfig = reactive<CdnflyRecord>({}),
    targets = ref<CdnflyRecord[]>([]),
    targetLoading = ref(false);
const activeFields = computed(() =>
    Object.keys(editConfig).filter((key) => overrideNames[key]),
);
let targetRequest = 0;
async function loadTargets() {
    const token = ++targetRequest;
    targetLoading.value = true;

    try {
        const data = await apiRequest(
            `/api/admin/${scope.value === 'node' ? 'nodes' : 'regions'}?limit=0`,
        );

        if (token === targetRequest) {
            targets.value = extractCdnflyRows(data);
        }
    } catch (e) {
        if (token === targetRequest) {
            editorError.value = message(e);
        }
    } finally {
        if (token === targetRequest) {
            targetLoading.value = false;
        }
    }
}
function openEditor(row?: CdnflyRecord) {
    editing.value = !!row;
    editorError.value = '';
    item.value = '';
    Object.keys(editConfig).forEach((k) => delete editConfig[k]);
    scope.value = String(row?.scope_name || 'node');
    scopeId.value = String(row?.scope_id || '');

    if (row) {
        try {
            Object.assign(editConfig, object(JSON.parse(String(row.value))));
        } catch {
            overrideError.value = '配置格式错误，无法编辑';

            return;
        }
    }

    editorOpen.value = true;
    void loadTargets();
}
function addOverride() {
    const key = item.value;

    if (!key) {
        return;
    }

    editConfig[key] = clone(
        (key === 'key' ? '' : config[key]) ??
            (key === 'waf_resource_limits'
                ? recommendedLimits()
                : key === 'auto_switch'
                  ? {
                        enable: false,
                        qps_50x: 10,
                        qps_total: 500,
                        rule: 2,
                        seconds: 300,
                    }
                  : key === 'log'
                    ? {
                          log_level: 'info',
                          debug_ip: '',
                          host: '127.0.0.1',
                          port: 514,
                      }
                    : overrideKind(key) === 'toggle'
                      ? 0
                      : ''),
    );

    if (overrideKind(key) === 'toggle') {
        editConfig[key] = flag(editConfig[key]) ? 1 : 0;
    }

    item.value = '';
}
function overrideKind(key: string) {
    return [
        'waf_enable',
        'cc_enable',
        'ssl_handshake_limit',
        'default_page_refuse',
        'icmp_drop',
    ].includes(key)
        ? 'toggle'
        : [
                'block_time',
                'white_time',
                'tmp_white_total_limit',
                'tmp_white_per_limit',
            ].includes(key)
          ? 'number'
          : key.endsWith('_html') ||
              ['custom_white', 'custom_black'].includes(key)
            ? 'textarea'
            : key === 'key'
              ? 'password'
              : 'text';
}
async function saveOverride() {
    if (
        !Number.isInteger(Number(scopeId.value)) ||
        Number(scopeId.value) < 1 ||
        !activeFields.value.length
    ) {
        editorError.value = '请选择配置范围并至少添加一个配置项';

        return;
    }

    if (editConfig.waf_resource_limits) {
        const problem = validateLimits(object(editConfig.waf_resource_limits));

        if (problem) {
            editorError.value = problem;

            return;
        }
    }

    const patch = Object.fromEntries(
        activeFields.value.map((key) => [key, editConfig[key]]),
    );
    editorBusy.value = true;
    editorError.value = '';

    try {
        await apiRequest(
            `/api/admin/firewall/overrides/${scope.value}/${scopeId.value}`,
            {
                method: 'PUT',
                body: JSON.stringify({
                    patch,
                    remove: editing.value
                        ? Object.keys(overrideNames).filter(
                              (key) => !(key in editConfig),
                          )
                        : [],
                    creating: !editing.value,
                }),
            },
        );
        editorOpen.value = false;
        toast.success('保存成功');
        await loadOverrides();
    } catch (e) {
        editorError.value = message(e);
    } finally {
        editorBusy.value = false;
    }
}
const deleteOpen = ref(false),
    deleteError = ref(''),
    deleting = ref(false),
    deleteKeys = ref<string[]>([]);
function askDelete(keys: string[]) {
    deleteKeys.value = keys;
    deleteError.value = '';
    deleteOpen.value = true;
}
async function removeOverrides() {
    deleting.value = true;
    const failed: string[] = [];
    let detail = '';

    for (const key of deleteKeys.value) {
        try {
            await apiRequest(`/api/admin/firewall/overrides/${key}`, {
                method: 'DELETE',
            });
        } catch (e) {
            failed.push(key);
            detail = message(e);
        }
    }

    await loadOverrides();
    deleting.value = false;

    if (failed.length) {
        deleteKeys.value = failed;
        deleteError.value = detail;
    } else {
        deleteOpen.value = false;
        toast.success('删除成功');
    }
}
</script>

<template>
    <div class="flex flex-1 flex-col p-3 md:p-5">
        <section class="firewall-page">
            <nav class="fw-tabs" role="tablist" aria-label="防火墙设置">
                <Button
                    size="sm"
                    variant="ghost"
                    v-for="entry in [
                        { key: 'cc', label: 'CC全局配置' },
                        { key: 'waf', label: 'WAF全局配置' },
                        { key: 'overrides', label: '区域及节点配置' },
                    ]"
                    :id="`fw-tab-${entry.key}`"
                    :key="entry.key"
                    role="tab"
                    :aria-selected="tab === entry.key"
                    :aria-controls="`fw-panel-${entry.key}`"
                    :class="{ active: tab === entry.key }"
                    @click="selectTab(entry.key)"
                >
                    {{ entry.label }}</Button
                ><span
                    v-if="tab !== 'overrides'"
                    class="fw-save-state"
                    role="status"
                    >{{ saving ? '保存中…' : saved ? '已保存' : '' }}</span
                >
            </nav>
            <div v-if="error" role="alert" class="fw-error">
                {{ error }}
                <Button
                    size="sm"
                    variant="outline"
                    @click="ready ? flush() : load()"
                    >重试</Button
                >
            </div>
            <p data-typography="body" v-if="loading" class="fw-loading">
                加载中…
            </p>
            <div
                v-if="tab !== 'overrides'"
                :id="`fw-panel-${tab}`"
                role="tabpanel"
                :aria-labelledby="`fw-tab-${tab}`"
                class="fw-global"
                :aria-busy="loading || saving"
            >
                <fieldset :disabled="!ready">
                    <template v-if="tab === 'cc'">
                        <header class="fw-section-title">
                            <h2 data-typography="section-title">CC 防护策略</h2>
                            <p data-typography="description">
                                控制全局拦截方式、黑白名单时长和临时白名单阈值。
                            </p>
                        </header>
                        <div class="fw-grid">
                            <article class="fw-card fw-center">
                                <FirewallField
                                    label="CC 防护开关"
                                    help="控制全局 CC 防护是否启用。"
                                    kind="toggle"
                                    :value="value('cc_enable')"
                                    @change="change('cc_enable', flag($event))"
                                />
                            </article>
                            <article class="fw-card">
                                <h3 data-typography="label">名单有效期</h3>
                                <p data-typography="description">
                                    设置黑名单拦截时长和临时白名单放行时长。
                                </p>
                                <FirewallField
                                    v-for="field in [
                                        {
                                            key: 'block_time',
                                            label: '黑名单时间',
                                            min: 0,
                                        },
                                        {
                                            key: 'white_time',
                                            label: '临时白名单',
                                            min: 600,
                                        },
                                    ]"
                                    :key="field.key"
                                    :label="field.label"
                                    kind="number"
                                    :min="field.min"
                                    unit="秒"
                                    :value="value(field.key)"
                                    @change="change(field.key, $event)"
                                />
                                <p data-typography="helper" class="fw-hint">
                                    临时白名单时间不能小于 600 秒。
                                </p>
                            </article>
                            <article class="fw-card fw-full">
                                <h3 data-typography="label">默认拉黑方式</h3>
                                <p data-typography="description">
                                    选择默认拦截动作，并在非 ipset
                                    模式下配置高攻击量切换策略。
                                </p>
                                <FirewallField
                                    label=""
                                    kind="choices"
                                    :options="blockWays"
                                    :value="value('default_block_way')"
                                    @change="
                                        change('default_block_way', $event)
                                    "
                                />
                                <p data-typography="body">
                                    ipset 几乎不消耗带宽和
                                    CPU；断开连接、返回页面会有额外消耗。建议默认使用
                                    ipset，并在攻击量升高时自动切换为 ipset。
                                </p>
                                <template
                                    v-if="
                                        value('default_block_way') !== 'ipset'
                                    "
                                    ><FirewallField
                                        label="自动切换 ipset"
                                        kind="toggle"
                                        :value="value('ipset_auto_enable')"
                                        @change="
                                            change('ipset_auto_enable', $event)
                                        " /><FirewallField
                                        v-if="flag(value('ipset_auto_enable'))"
                                        label="攻击请求阈值"
                                        kind="number"
                                        unit="请求/秒"
                                        :value="value('site_block_qps')"
                                        @change="
                                            change('site_block_qps', $event)
                                        "
                                /></template>
                            </article>
                            <article class="fw-card fw-full">
                                <h3 data-typography="label">临时白名单阈值</h3>
                                <p data-typography="description">
                                    达到阈值后进入临时白名单，降低误拦截概率。
                                </p>
                                <div class="fw-grid">
                                    <FirewallField
                                        label="总请求数"
                                        kind="number"
                                        :min="0"
                                        unit="次 / 5秒"
                                        :value="value('tmp_white_total_limit')"
                                        @change="
                                            change(
                                                'tmp_white_total_limit',
                                                $event,
                                            )
                                        "
                                    /><FirewallField
                                        label="同 URL 请求"
                                        kind="number"
                                        :min="0"
                                        unit="次 / 5秒"
                                        :value="value('tmp_white_per_limit')"
                                        @change="
                                            change(
                                                'tmp_white_per_limit',
                                                $event,
                                            )
                                        "
                                    />
                                </div>
                            </article>
                            <article
                                v-for="field in [
                                    { key: 'custom_white', label: '白名单 IP' },
                                    { key: 'custom_black', label: '黑名单 IP' },
                                ]"
                                :key="field.key"
                                class="fw-card"
                            >
                                <h3 data-typography="label">
                                    {{ field.label }}
                                </h3>
                                <p data-typography="description">
                                    一行一个，支持 # 作为注释。
                                </p>
                                <FirewallField
                                    :label="field.label"
                                    kind="textarea"
                                    :value="value(field.key)"
                                    placeholder="如 192.168.1.10，192.168.1.0/25"
                                    @change="change(field.key, $event)"
                                />
                            </article>
                        </div>
                        <header class="fw-section-title">
                            <h2 data-typography="section-title">
                                节点访问与默认页
                            </h2>
                            <p data-typography="description">
                                保护节点入口、默认页和自动清理策略，减少误开放风险。
                            </p>
                        </header>
                        <div class="fw-grid">
                            <article
                                v-for="field in [
                                    {
                                        key: 'ssl_handshake_limit',
                                        label: '防止TLS握手攻击',
                                        help: '限制异常 TLS 握手占用，降低节点握手洪泛风险。',
                                    },
                                    {
                                        key: 'default_page_refuse',
                                        label: '禁止节点IP及未绑定域名访问',
                                        help: '阻断直接访问节点 IP 或未绑定域名的请求。',
                                    },
                                ]"
                                :key="field.key"
                                class="fw-card"
                            >
                                <FirewallField
                                    :label="field.label"
                                    :help="field.help"
                                    kind="toggle"
                                    :value="value(field.key)"
                                    @change="change(field.key, $event)"
                                />
                            </article>
                            <article class="fw-card fw-full">
                                <h3 data-typography="label">默认页防护</h3>
                                <p data-typography="description">
                                    配置默认页触发 CC
                                    防护的方式，并选择命中的规则组。
                                </p>
                                <div class="fw-inline">
                                    <FirewallField
                                        label="启用方式"
                                        kind="choices"
                                        :options="
                                            options([
                                                [1, '强制开启'],
                                                [0, '自动开启'],
                                            ])
                                        "
                                        :value="value('force_default_page_cc')"
                                        @change="
                                            change(
                                                'force_default_page_cc',
                                                $event,
                                            )
                                        "
                                    /><FirewallField
                                        v-if="
                                            !flag(
                                                value('force_default_page_cc'),
                                            )
                                        "
                                        label="自动开启阈值"
                                        kind="number"
                                        :min="0"
                                        unit="请求/秒，开启"
                                        :value="
                                            value('auto_default_page_cc_qps')
                                        "
                                        @change="
                                            change(
                                                'auto_default_page_cc_qps',
                                                $event,
                                            )
                                        "
                                    />
                                </div>
                                <FirewallField
                                    label="规则组"
                                    kind="select"
                                    :options="ruleOptions"
                                    :value="value('default_page_rule')"
                                    @change="
                                        change(
                                            'default_page_rule',
                                            Number($event),
                                        )
                                    "
                                />
                            </article>
                            <article class="fw-card fw-center">
                                <FirewallField
                                    label="禁止PING"
                                    help="关闭节点 ICMP 回应，减少暴露面。"
                                    kind="toggle"
                                    :value="value('icmp_drop')"
                                    @change="change('icmp_drop', $event)"
                                />
                            </article>
                            <article class="fw-card">
                                <h3 data-typography="label">密钥</h3>
                                <p data-typography="description">
                                    节点侧 OpenResty 配置通信使用的共享密钥。
                                </p>
                                <FirewallField
                                    label="共享密钥"
                                    kind="password"
                                    :value="value('key')"
                                    @change="change('key', $event)"
                                />
                            </article>
                            <article class="fw-card fw-full">
                                <h3 data-typography="label">
                                    自动清理节点日志
                                </h3>
                                <p data-typography="description">
                                    节点磁盘不足时的访问日志和缓存清理策略。
                                </p>
                                <FirewallField
                                    label="清理策略"
                                    kind="choices"
                                    :options="cleanup"
                                    :value="value('auto_delete_access_log')"
                                    @change="
                                        change('auto_delete_access_log', $event)
                                    "
                                />
                            </article>
                        </div>
                        <header class="fw-section-title">
                            <h2 data-typography="section-title">
                                自动切换与防 CC 图片
                            </h2>
                            <p data-typography="description">
                                按 QPS 自动切换规则组，并管理防 CC
                                图片资源更新。
                            </p>
                        </header>
                        <div class="fw-grid">
                            <article class="fw-card">
                                <FirewallField
                                    label="CC规则自动切换"
                                    help="按错误请求和总 QPS 自动切换到指定规则组。"
                                    kind="toggle"
                                    :value="value('auto_switch.enable')"
                                    @change="
                                        change(
                                            'auto_switch.enable',
                                            flag($event),
                                        )
                                    "
                                /><template
                                    v-if="flag(value('auto_switch.enable'))"
                                    ><FirewallField
                                        v-for="field in [
                                            {
                                                key: 'qps_50x',
                                                label: '502/504 QPS',
                                            },
                                            {
                                                key: 'qps_total',
                                                label: '总QPS',
                                            },
                                            {
                                                key: 'seconds',
                                                label: '切换时长',
                                            },
                                        ]"
                                        :key="field.key"
                                        :label="field.label"
                                        kind="number"
                                        :min="0"
                                        :value="
                                            value(`auto_switch.${field.key}`)
                                        "
                                        @change="
                                            change(
                                                `auto_switch.${field.key}`,
                                                $event,
                                            )
                                        " /><FirewallField
                                        label="切换规则组"
                                        kind="select"
                                        :value="value('auto_switch.rule')"
                                        :options="ruleOptions"
                                        @change="
                                            change(
                                                'auto_switch.rule',
                                                Number($event),
                                            )
                                        "
                                /></template>
                            </article>
                            <article class="fw-card">
                                <h3 data-typography="label">防 CC 图片更新</h3>
                                <p data-typography="description">
                                    选择图片资源地址，并主动通知节点刷新资源。
                                </p>
                                <div class="fw-inline">
                                    <FirewallField
                                        label=""
                                        kind="choices"
                                        :options="
                                            options([
                                                ['system', '系统默认'],
                                                ['custom', '自定义'],
                                            ])
                                        "
                                        :value="value('cc_img_url_type')"
                                        @change="
                                            change('cc_img_url_type', $event)
                                        "
                                    /><Button
                                        size="sm"
                                        variant="default"
                                        class="fw-primary"
                                        :disabled="imageBusy || saving"
                                        @click="updateImages"
                                    >
                                        <RefreshCw />立即更新
                                    </Button>
                                </div>
                                <template
                                    v-if="value('cc_img_url_type') === 'custom'"
                                    ><FirewallField
                                        label="图片下载地址"
                                        :value="value('cc_img_url')"
                                        @change="
                                            change('cc_img_url', $event)
                                        " /><FirewallField
                                        label="更新时间"
                                        kind="number"
                                        :min="0"
                                        :max="23"
                                        unit="点，每天"
                                        :value="value('at_hour')"
                                        @change="change('at_hour', $event)"
                                /></template>
                                <p data-typography="body">
                                    可选择自定义图片下载地址。<a
                                        href="https://doc.cdnfly.cn/dajianshengchengfangcctupianfuwuqi.html"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        >查看安装教程</a
                                    >
                                </p>
                            </article>
                        </div>
                        <header class="fw-section-title">
                            <h2 data-typography="section-title">
                                防 CC 页面模板
                            </h2>
                            <p data-typography="description">
                                维护各类验证页
                                HTML，当前选择切换时只展示对应模板。
                            </p>
                        </header>
                        <div class="fw-templates">
                            <FirewallField
                                label="防CC页面"
                                kind="choices"
                                :options="
                                    Object.entries(templates).map(
                                        ([value, label]) => ({ value, label }),
                                    )
                                "
                                :value="ccPage"
                                @change="ccPage = String($event)"
                            /><FirewallField
                                :key="ccPage"
                                :label="`${templates[ccPage as keyof typeof templates]}页面 HTML`"
                                kind="textarea"
                                :value="value(ccPage)"
                                @change="change(ccPage, $event)"
                            />
                        </div>
                        <header class="fw-section-title">
                            <h2 data-typography="section-title">
                                调试与内置资源防护
                            </h2>
                            <p data-typography="description">
                                开启诊断日志，保护证书验证路径和防 CC
                                页静态资源。
                            </p>
                        </header>
                        <div class="fw-grid">
                            <article class="fw-card">
                                <FirewallField
                                    label="诊断日志"
                                    help="开启后只记录指定客户端 IP 的调试日志。"
                                    kind="toggle"
                                    :value="
                                        value('log.log_level') === 'debug'
                                            ? 1
                                            : 0
                                    "
                                    @change="
                                        change(
                                            'log.log_level',
                                            $event === 1 ? 'debug' : 'info',
                                        )
                                    "
                                /><FirewallField
                                    v-if="value('log.log_level') === 'debug'"
                                    label="客户端 IP"
                                    :value="value('log.debug_ip')"
                                    @change="change('log.debug_ip', $event)"
                                />
                            </article>
                            <article class="fw-card">
                                <h3 data-typography="label">
                                    .well-known 防护
                                </h3>
                                <p data-typography="description">
                                    保护证书验证路径，避免异常 404
                                    请求持续回源主控。
                                </p>
                                <FirewallField
                                    label="404 阈值"
                                    kind="number"
                                    :min="0"
                                    unit="次/60秒"
                                    :value="
                                        value('well_known_auto_protect_count')
                                    "
                                    @change="
                                        change(
                                            'well_known_auto_protect_count',
                                            $event,
                                        )
                                    "
                                />
                                <p data-typography="body">
                                    超过阈值后 300 秒内仅允许已验证通过的 IP
                                    回源，仍可正常申请证书。
                                </p>
                            </article>
                            <article class="fw-card fw-full">
                                <h3 data-typography="label">内置资源防护</h3>
                                <p data-typography="description">
                                    保护防 CC 页面中的图片、JS
                                    等静态资源，达到阈值后自动启用防护。
                                </p>
                                <div class="fw-grid">
                                    <FirewallField
                                        label="触发阈值"
                                        kind="number"
                                        :min="0"
                                        unit="请求/秒"
                                        :value="value('internal_qps')"
                                        @change="change('internal_qps', $event)"
                                    /><FirewallField
                                        label="拉黑时长"
                                        kind="number"
                                        :min="0"
                                        unit="秒"
                                        :value="
                                            value('internal_res_block_time')
                                        "
                                        @change="
                                            change(
                                                'internal_res_block_time',
                                                $event,
                                            )
                                        "
                                    />
                                </div>
                                <div class="fw-rule-heading">
                                    <div>
                                        <h3 data-typography="label">
                                            资源规则
                                        </h3>
                                        <p data-typography="description">
                                            每行配置一个统计窗口和最大请求次数，留空则不启用。
                                        </p>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="default"
                                        class="fw-primary"
                                        :disabled="saving"
                                        @click="saveInternal"
                                    >
                                        <Save />保存
                                    </Button>
                                </div>
                                <table class="fw-rule-table">
                                    <thead>
                                        <tr>
                                            <th>统计时长(秒)</th>
                                            <th>最大次数</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row, i) in internalRules"
                                            :key="i"
                                        >
                                            <td>
                                                <Input
                                                    v-model="row.period"
                                                    :aria-label="`统计时长 ${i + 1}`"
                                                    type="number"
                                                    min="1"
                                                    placeholder="为空则不启用"
                                                />
                                            </td>
                                            <td>
                                                <Input
                                                    v-model="row.reqs"
                                                    :aria-label="`最大次数 ${i + 1}`"
                                                    type="number"
                                                    min="1"
                                                    placeholder="为空则不启用"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </article>
                        </div>
                    </template>
                    <template v-else
                        ><article class="fw-card">
                            <FirewallField
                                label="WAF 防护开关"
                                help="作为未配置区域或节点覆盖时的默认状态；区域和节点可单独反向覆盖此开关。"
                                kind="toggle"
                                :value="value('waf_enable') ?? 1"
                                @change="change('waf_enable', $event)"
                            />
                        </article>
                        <article class="fw-card fw-limits">
                            <div class="fw-rule-heading">
                                <div>
                                    <h3 data-typography="label">
                                        单次请求处理上限
                                    </h3>
                                    <p data-typography="description">
                                        控制 WAF
                                        检查单个请求的范围，避免复杂或恶意请求占用过多节点资源；修改后自动保存。
                                    </p>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    :disabled="saving"
                                    @click="
                                        change('waf_resource_limits', {
                                            ...object(
                                                config.waf_resource_limits,
                                            ),
                                            ...recommendedLimits(),
                                        })
                                    "
                                >
                                    恢复推荐值
                                </Button>
                            </div>
                            <p data-typography="body" class="fw-warning">
                                数值越大，WAF 能处理的请求越复杂、内容越大，但
                                CPU
                                和内存消耗也越高。请求超过任一限制时会被直接拦截（返回
                                403）。
                            </p>
                            <section v-for="group in limitGroups" :key="group">
                                <h4 data-typography="section-title">
                                    {{ group }}
                                </h4>
                                <div class="fw-grid">
                                    <FirewallField
                                        v-for="field in limitFields.filter(
                                            (f) => f.group === group,
                                        )"
                                        :key="field.key"
                                        :label="field.label"
                                        :help="field.help"
                                        kind="number"
                                        :min="field.min"
                                        :max="field.max"
                                        :unit="field.unit"
                                        :value="
                                            Number(
                                                value(
                                                    `waf_resource_limits.${field.key}`,
                                                ),
                                            ) / field.scale
                                        "
                                        @change="
                                            change(
                                                `waf_resource_limits.${field.key}`,
                                                Number($event) * field.scale,
                                            )
                                        "
                                    />
                                </div>
                            </section></article
                    ></template>
                </fieldset>
            </div>
            <div
                v-else
                id="fw-panel-overrides"
                role="tabpanel"
                aria-labelledby="fw-tab-overrides"
                class="fw-overrides"
                :aria-busy="overrideLoading || editorBusy || deleting"
            >
                <header class="fw-card fw-rule-heading">
                    <div>
                        <h2 data-typography="section-title">区域及节点配置</h2>
                        <p data-typography="description">
                            按区域或单节点覆盖全局 CC/WAF 运行参数。
                        </p>
                        <span class="fw-count"
                            >共 {{ overrideTotal }} 条覆盖配置</span
                        >
                    </div>
                    <Button
                        size="sm"
                        variant="default"
                        class="fw-primary"
                        @click="openEditor()"
                    >
                        <Plus />新增设置
                    </Button>
                </header>
                <div v-if="overrideError" role="alert" class="fw-error">
                    {{ overrideError }}
                    <Button size="sm" variant="outline" @click="loadOverrides()"
                        >重试</Button
                    >
                </div>
                <div class="fw-table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>
                                    <CheckboxField
                                        aria-label="选择全部配置"
                                        :checked="
                                            overrides.length > 0 &&
                                            selected.length === overrides.length
                                        "
                                        @change="
                                            selected =
                                                selected.length ===
                                                overrides.length
                                                    ? []
                                                    : overrides.map(rowKey)
                                        "
                                    />
                                </th>
                                <th>配置范围</th>
                                <th>配置项</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in overrides" :key="rowKey(row)">
                                <td>
                                    <CheckboxField
                                        v-model="selected"
                                        :value="rowKey(row)"
                                        :aria-label="`选择配置 ${rowKey(row)}`"
                                    />
                                </td>
                                <td>
                                    <span class="fw-count"
                                        >{{
                                            row.scope_name === 'node'
                                                ? '节点'
                                                : '区域'
                                        }}
                                        {{ row.scope_id }}</span
                                    >
                                </td>
                                <td>{{ overrideSummary(row) }}</td>
                                <td>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="fw-link"
                                        @click="openEditor(row)"
                                    >
                                        编辑</Button
                                    ><Button
                                        size="sm"
                                        variant="ghost"
                                        class="fw-link"
                                        @click="askDelete([rowKey(row)])"
                                    >
                                        删除
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="!overrides.length">
                                <td colspan="4" class="fw-empty">
                                    {{
                                        overrideLoading
                                            ? '加载中…'
                                            : overrideError
                                              ? '加载失败'
                                              : '暂无数据'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ConsolePagination
                    :total="overrideTotal"
                    :page="page"
                    :previous-disabled="page === 1 || overrideLoading"
                    :next-disabled="
                        page * 10 >= overrideTotal || overrideLoading
                    "
                    @previous="loadOverrides(page - 1)"
                    @next="loadOverrides(page + 1)"
                />
            </div>
        </section>
        <Dialog v-model:open="editorOpen"
            ><DialogScrollContent class="fw-modal sm:max-w-3xl"
                ><DialogHeader
                    ><DialogTitle>{{
                        editing ? '编辑设置' : '新增设置'
                    }}</DialogTitle
                    ><DialogDescription
                        >为指定区域或节点覆盖全局配置；未选择的配置项沿用全局设置。</DialogDescription
                    ></DialogHeader
                >
                <div v-if="editorError" role="alert" class="fw-error">
                    {{ editorError }}
                </div>
                <form @submit.prevent="saveOverride">
                    <div class="fw-grid">
                        <label
                            >配置范围<SelectField
                                v-model="scope"
                                aria-label="配置范围"
                                :disabled="editing"
                                @change="
                                    scopeId = '';
                                    loadTargets();
                                "
                            >
                                <SelectOption value="node">节点</SelectOption>
                                <SelectOption value="region">区域</SelectOption>
                            </SelectField></label
                        ><label
                            >选择目标<SelectField
                                v-model="scopeId"
                                aria-label="选择目标"
                                :disabled="editing || targetLoading"
                                required
                            >
                                <SelectOption value="">
                                    {{ targetLoading ? '加载中…' : '请选择' }}
                                </SelectOption>
                                <SelectOption
                                    v-if="
                                        editing &&
                                        !targets.some(
                                            (t) => String(t.id) === scopeId,
                                        )
                                    "
                                    :value="scopeId"
                                >
                                    {{ scopeId }}
                                </SelectOption>
                                <SelectOption
                                    v-for="target in targets"
                                    :key="String(target.id)"
                                    :value="String(target.id)"
                                >
                                    {{ target.name }} ({{ target.id }})
                                </SelectOption>
                            </SelectField></label
                        >
                    </div>
                    <div class="fw-add-field">
                        <SelectField v-model="item" aria-label="配置项">
                            <SelectOption value="">请选择配置项</SelectOption>
                            <SelectOption
                                v-for="(label, key) in overrideNames"
                                :key="key"
                                :value="key"
                                :disabled="key in editConfig"
                            >
                                {{ label }}
                            </SelectOption></SelectField
                        ><Button
                            size="sm"
                            variant="outline"
                            type="button"
                            :disabled="!item"
                            @click="addOverride"
                        >
                            <Plus />添加
                        </Button>
                    </div>
                    <article
                        v-for="key in activeFields"
                        :key="key"
                        class="fw-card"
                    >
                        <div class="fw-rule-heading">
                            <h3 data-typography="label">
                                {{ overrideNames[key] }}
                            </h3>
                            <Button
                                size="sm"
                                variant="outline"
                                type="button"
                                :aria-label="`移除 ${overrideNames[key]}`"
                                @click="delete editConfig[key]"
                            >
                                <X />
                            </Button>
                        </div>
                        <template v-if="key === 'waf_resource_limits'"
                            ><div class="fw-grid">
                                <FirewallField
                                    v-for="field in limitFields"
                                    :key="field.key"
                                    :label="field.label"
                                    kind="number"
                                    :unit="field.unit"
                                    :min="field.min"
                                    :max="field.max"
                                    :value="
                                        Number(
                                            getPath(
                                                editConfig,
                                                `${key}.${field.key}`,
                                            ),
                                        ) / field.scale
                                    "
                                    @change="
                                        setPath(
                                            editConfig,
                                            `${key}.${field.key}`,
                                            Number($event) * field.scale,
                                        )
                                    "
                                /></div></template
                        ><template v-else-if="key === 'auto_switch'"
                            ><FirewallField
                                label="开关"
                                kind="toggle"
                                :value="
                                    getPath(editConfig, 'auto_switch.enable')
                                "
                                @change="
                                    setPath(
                                        editConfig,
                                        'auto_switch.enable',
                                        flag($event),
                                    )
                                " /><FirewallField
                                v-for="field in [
                                    { key: 'qps_50x', label: '502/504 QPS' },
                                    { key: 'qps_total', label: '总QPS' },
                                    { key: 'seconds', label: '切换时长' },
                                ]"
                                :key="field.key"
                                :label="field.label"
                                kind="number"
                                :value="
                                    getPath(
                                        editConfig,
                                        `auto_switch.${field.key}`,
                                    )
                                "
                                @change="
                                    setPath(
                                        editConfig,
                                        `auto_switch.${field.key}`,
                                        $event,
                                    )
                                " /><FirewallField
                                label="规则组"
                                kind="select"
                                :options="ruleOptions"
                                :value="getPath(editConfig, 'auto_switch.rule')"
                                @change="
                                    setPath(
                                        editConfig,
                                        'auto_switch.rule',
                                        Number($event),
                                    )
                                " /></template
                        ><template v-else-if="key === 'log'"
                            ><FirewallField
                                label="诊断日志"
                                kind="toggle"
                                :value="
                                    getPath(editConfig, 'log.log_level') ===
                                    'debug'
                                        ? 1
                                        : 0
                                "
                                @change="
                                    setPath(
                                        editConfig,
                                        'log.log_level',
                                        $event === 1 ? 'debug' : 'info',
                                    )
                                " /><FirewallField
                                label="客户端 IP"
                                :value="getPath(editConfig, 'log.debug_ip')"
                                @change="
                                    setPath(editConfig, 'log.debug_ip', $event)
                                " /></template
                        ><FirewallField
                            v-else-if="key === 'default_page_rule'"
                            label="规则组"
                            kind="select"
                            :options="ruleOptions"
                            :value="editConfig[key]"
                            @change="editConfig[key] = Number($event)"
                        /><FirewallField
                            v-else-if="key === 'auto_delete_access_log'"
                            label="清理策略"
                            kind="choices"
                            :options="cleanup"
                            :value="editConfig[key]"
                            @change="editConfig[key] = $event"
                        /><FirewallField
                            v-else
                            :label="overrideNames[key]"
                            :kind="overrideKind(key)"
                            :value="editConfig[key]"
                            :min="key === 'white_time' ? 600 : 0"
                            @change="editConfig[key] = $event"
                        />
                    </article>
                    <div class="fw-modal-actions">
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            :disabled="editorBusy"
                            @click="editorOpen = false"
                        >
                            取消</Button
                        ><Button
                            size="sm"
                            variant="default"
                            class="fw-primary"
                            :disabled="editorBusy || targetLoading"
                        >
                            确定
                        </Button>
                    </div>
                </form></DialogScrollContent
            ></Dialog
        >
        <ConfirmDeleteDialog
            :open="deleteOpen"
            :description="`确认删除 ${deleteKeys.length} 条覆盖配置？删除后将沿用全局设置。`"
            :loading="deleting"
            :error="deleteError"
            @confirm="removeOverrides"
            @cancel="!deleting && (deleteOpen = false)"
        />
    </div>
</template>

<style>
.firewall-page {
    --fw-line: var(--border);
    --fw-ink: var(--foreground);
    --fw-muted: var(--muted-foreground);
    background: var(--background);
    color: var(--fw-ink);
    border-radius: 8px;
    padding: 12px;
    font-size: var(--console-text-body);
    min-width: 0;
}
.fw-tabs {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}
.firewall-page .fw-tabs button {
    border: 0;
    border-bottom: 2px solid transparent;
    border-radius: 6px 6px 0 0;
    padding: 0 14px;
}
.firewall-page .fw-tabs button.active {
    background: var(--accent);
    border-bottom-color: var(--primary);
    color: var(--primary);
}
.fw-save-state {
    color: var(--muted-foreground);
    margin-left: auto;
    font-size: var(--console-text-helper);
}
.fw-global {
    max-width: 814px;
}
.fw-global fieldset {
    border: 0;
    margin: 0;
    padding: 0;
    min-width: 0;
}
.fw-section-title {
    border-left: 3px solid var(--primary);
    padding-left: 9px;
    margin: 22px 0 16px;
}
.fw-section-title:first-child {
    margin-top: 0;
}
.firewall-page h2,
.firewall-page h3,
.fw-modal h3 {
    font-size: var(--console-text-section-title);
    font-weight: 600;
    color: var(--foreground);
}
.firewall-page h2 {
    font-size: var(--console-text-section-title);
}
.firewall-page p,
.fw-modal p {
    font-size: var(--console-text-body);
    color: var(--fw-muted, var(--muted-foreground));
    line-height: 1.7;
    margin: 4px 0 10px;
}
.fw-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
.fw-card {
    background: var(--card);
    border: 1px solid var(--fw-line, var(--border));
    padding: 14px;
    border-radius: 6px;
    min-width: 0;
}
.fw-full {
    grid-column: 1/-1;
}
.fw-center {
    display: flex;
    align-items: center;
    min-height: 90px;
}
.fw-center > .fw-field {
    width: 100%;
}
.fw-field {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 9px 0;
    min-width: 0;
}
.fw-label {
    flex: 0 0 96px;
    line-height: 1.5;
}
.fw-label small {
    display: block;
    font-size: var(--console-text-helper);
    line-height: 1.4;
    color: var(--fw-muted, var(--muted-foreground));
    margin-top: 3px;
}
.fw-field > :deep([data-slot='select-trigger']),
.fw-input {
    flex: 1;
    min-width: 0;
}
.fw-input {
    display: flex;
}
.fw-input > input {
    min-width: 0;
    flex: 1;
    width: 100%;
}
.fw-input > span {
    display: flex;
    align-items: center;
    padding: 0 5px;
    background: var(--muted);
    border: 1px solid var(--input);
    border-left: 0;
    border-radius: 0 3px 3px 0;
    white-space: nowrap;
    font-size: var(--console-text-body);
}
.fw-input:has(> span) > input {
    border-radius: 3px 0 0 3px;
}
.firewall-page input:not([type='checkbox']),
.firewall-page :deep([data-slot='select-trigger']),
.fw-modal input:not([type='checkbox']),
.fw-modal :deep([data-slot='select-trigger']) {
    height: 28px;
    border: 1px solid var(--input);
    border-radius: 3px;
    background: var(--background);
    padding: 0 7px;
    font: inherit;
    color: inherit;
}
.firewall-page :deep([data-slot='textarea']),
.fw-modal :deep([data-slot='textarea']) {
    width: 100%;
    min-height: 122px;
    padding: 7px;
    border: 1px solid var(--input);
    border-radius: 3px;
    background: var(--background);
    font: inherit;
    color: inherit;
    resize: vertical;
}
.firewall-page input::placeholder,
.firewall-page :deep([data-slot='textarea'])::placeholder {
    color: var(--muted-foreground);
}
.fw-toggle-field {
    justify-content: space-between;
}
.fw-toggle-field .fw-label {
    flex: 1;
    font-weight: 600;
    color: var(--foreground);
}
.fw-toggle-field small {
    font-weight: 400;
}
.fw-choices {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.fw-choices button.chosen {
    border-color: var(--ring);
    background: var(--accent);
    color: var(--primary);
}
.fw-choices i {
    width: 13px;
    height: 13px;
    border: 1px solid var(--input);
    border-radius: 50%;
}
.fw-choices .chosen i {
    border-color: var(--primary);
    background: var(--primary);
    box-shadow: inset 0 0 0 2px var(--background);
}
.fw-wide-field:has(:deep([data-slot='textarea'])) {
    display: block;
}
.fw-wide-field:has(:deep([data-slot='textarea'])) > .fw-label {
    display: none;
}
.fw-inline {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.fw-inline .fw-field {
    flex: 1;
}
.fw-inline .fw-label {
    flex-basis: 82px;
}
.fw-hint {
    margin-top: 18px !important;
}
.firewall-page a {
    color: var(--primary);
}
.fw-templates {
    margin: 0 0 10px;
}
.fw-templates :deep([data-slot='textarea']) {
    height: 195px;
    font: 11px/1.6 monospace;
}
.fw-templates .fw-field:has(:deep([data-slot='textarea'])) {
    margin-left: 96px;
}
.fw-rule-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.fw-card .fw-rule-heading:not(:first-child) {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--fw-line, var(--border));
}
.fw-rule-table {
    width: 100%;
    border-collapse: collapse;
}
.fw-rule-table th,
.fw-rule-table td {
    padding: 7px;
    text-align: left;
    border-bottom: 1px solid var(--fw-line, var(--border));
    font-weight: 400;
}
.fw-rule-table input {
    width: 90%;
}
.fw-limits {
    margin-top: 12px;
}
.fw-limits h4 {
    margin: 14px 0 8px;
}
.fw-limits .fw-field {
    margin: 0;
    align-items: flex-start;
}
.fw-limits .fw-label {
    flex: 1;
}
.fw-limits .fw-input {
    flex: 0 0 124px;
}
.fw-limits .fw-input > span {
    background: none;
    border: 0;
    color: var(--fw-muted);
}
.firewall-page .fw-warning {
    padding: 8px 10px;
    background: var(--muted);
    color: var(--foreground);
    border-left: 2px solid var(--primary);
    border-radius: 3px;
}
.fw-error {
    background: color-mix(in srgb, var(--destructive) 10%, transparent);
    color: var(--destructive);
    padding: 10px;
    margin-bottom: 12px;
    border-radius: 4px;
    font-size: var(--console-text-body);
}
.fw-loading {
    padding: 15px;
}
.fw-overrides {
    max-width: 967px;
}
.fw-count {
    display: inline-flex;
    background: var(--accent);
    border-radius: 12px;
    color: var(--primary);
    padding: 3px 9px;
    font-size: var(--console-text-body);
}
.fw-table-scroll {
    overflow: auto;
    border: 1px solid var(--fw-line);
    border-radius: 6px;
    margin-top: 14px;
}
.fw-table-scroll table {
    width: 100%;
    min-width: 620px;
    border-collapse: collapse;
}
.fw-table-scroll th,
.fw-table-scroll td {
    text-align: left;
    padding: 10px;
    border-bottom: 1px solid var(--fw-line);
    font-weight: 400;
}
.fw-table-scroll th {
    background: var(--card);
}
.fw-table-scroll th:first-child {
    width: 54px;
}
.fw-table-scroll th:last-child {
    width: 140px;
}
.fw-table-scroll .fw-empty {
    text-align: center;
    color: var(--fw-muted);
    height: 40px;
}
.firewall-page .fw-link {
    color: var(--primary);
    border: 0;
    padding: 0 6px;
}
.fw-modal {
    --fw-line: var(--border);
    --fw-muted: var(--muted-foreground);
    font-size: var(--console-text-body);
}
.fw-modal form > .fw-grid label {
    display: grid;
    gap: 6px;
}
.fw-modal .fw-card {
    margin-top: 12px;
}
.fw-add-field {
    display: flex;
    gap: 8px;
    margin: 16px 0;
}
.fw-add-field :deep([data-slot='select-trigger']) {
    flex: 1;
}
.fw-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 18px;
}
.firewall-page :deep([data-slot='checkbox']) {
    accent-color: var(--primary);
}
@media (max-width: 700px) {
    .fw-grid {
        grid-template-columns: 1fr;
    }
    .fw-full {
        grid-column: auto;
    }
    .fw-templates .fw-field:has(:deep([data-slot='textarea'])) {
        margin-left: 0;
    }
    .fw-field.fw-wide-field:not(:has(:deep([data-slot='textarea']))) {
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .fw-limits .fw-input {
        flex-basis: 130px;
    }
    .fw-global {
        width: 100%;
    }
    .fw-rule-heading {
        flex-wrap: wrap;
    }
    .fw-modal .fw-grid {
        grid-template-columns: 1fr;
    }
    .fw-tabs {
        gap: 2px;
    }
    .fw-tabs button {
        padding: 0 8px;
    }
}
</style>
