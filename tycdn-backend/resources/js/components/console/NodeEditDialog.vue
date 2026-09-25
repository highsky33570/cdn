<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CheckboxField from '@/components/ui/checkbox/CheckboxField.vue';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import RadioGroup from '@/components/ui/radio-group/RadioGroup.vue';
import RadioGroupItem from '@/components/ui/radio-group/RadioGroupItem.vue';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import Switch from '@/components/ui/switch/Switch.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import {
    addAdminNodeSubIps,
    getAdminNode,
    updateAdminNode,
} from '@/lib/adminModulesApi';
import type { CdnflyRecord } from '@/lib/adminModulesApi';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows } from '@/lib/cdnflyResponse';
import {
    cacheGigabytes,
    nginxNodeConfig,
    nodeDetail,
    nodeObject,
    trafficPayload,
    trafficSettings,
} from '@/lib/nodeEdit';

const props = defineProps<{ node: CdnflyRecord | null }>();
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ saved: [] }>();
const tabs = [
    { key: 'basic', label: '基本设置' },
    { key: 'config', label: '节点设置' },
    { key: 'location', label: 'IP归属' },
    { key: 'auto', label: '自动禁用' },
    { key: 'subip', label: '添加子IP' },
];
const tab = ref('basic'),
    loading = ref(false),
    saving = ref(false),
    error = ref(''),
    configError = ref(''),
    configLoading = ref(false),
    configReady = ref(false);
const detail = ref<Record<string, unknown>>({}),
    originalConfig = ref<Record<string, unknown>>({}),
    configEnable = ref<number | undefined>();
const basic = reactive({
    name: '',
    des: '',
    sort: '100',
    ip: '',
    type: 'L1' as 'L1' | 'L2',
});
const config = reactive({ cache: '', size: '', logs: '' }),
    inherited = reactive({ cache: '', size: '', logs: '' });
const location = reactive<Record<string, string>>({
    country: '',
    province: '',
    city: '',
    isp: '',
    areacode: '',
});
const locationFields = [
    { key: 'country', label: '国家' },
    { key: 'province', label: '省份/州' },
    { key: 'city', label: '城市' },
    { key: 'isp', label: 'ISP' },
    { key: 'areacode', label: '国家码' },
];
const traffic = reactive(trafficSettings(null)),
    bandwidth = ref(''),
    bandwidthUnit = ref('Mbps'),
    disableTime = ref(''),
    subips = ref('');
let generation = 0;
const message = (e: unknown) =>
    e instanceof Error ? e.message : '操作失败，请重试';
watch(
    () => [open.value, props.node?.id],
    () => {
        generation++;

        if (!open.value || !props.node?.id) {
            return;
        }

        tab.value = 'basic';
        detail.value = {};
        error.value = '';
        configError.value = '';
        configReady.value = false;
        configLoading.value = false;
        subips.value = '';
        void loadDetail();
    },
);
async function loadDetail() {
    const id = Number(props.node?.id),
        ticket = generation;
    loading.value = true;
    error.value = '';

    try {
        const row = nodeDetail(await getAdminNode(id));

        if (ticket !== generation) {
            return;
        }

        if (!row.id) {
            throw new Error('未能读取节点详情');
        }

        const ipLocation = nodeObject(row.ip_location),
            quota = trafficSettings(row.traffic_limit);
        detail.value = row;
        Object.assign(basic, {
            name: String(row.name ?? ''),
            des: String(row.des ?? ''),
            sort: String(row.sort ?? 100),
            ip: String(row.ip ?? ''),
            type: row.type === 'L2' ? 'L2' : 'L1',
        });

        for (const field of locationFields) {
            location[field.key] = String(ipLocation[field.key] ?? '');
        }

        Object.assign(traffic, quota);
        const bw = String(row.bw_limit ?? '').match(/^([\d.]+)(Mbps|Gbps)$/);
        bandwidth.value = bw?.[1] ?? '';
        bandwidthUnit.value = bw?.[2] ?? 'Mbps';
        disableTime.value = String(row.disable_time ?? '');
    } catch (e) {
        if (ticket === generation) {
            error.value = message(e);
        }
    } finally {
        if (ticket === generation) {
            loading.value = false;
        }
    }
}
async function loadConfig() {
    const ticket = generation;
    configLoading.value = true;
    configError.value = '';

    try {
        const rows = extractCdnflyRows(await apiRequest('/api/admin/configs'));

        if (ticket !== generation) {
            return;
        }

        const find = (scope: string, id: unknown) =>
            rows.find(
                (row) =>
                    row.name === 'nginx-config-file' &&
                    row.type === 'nginx_config' &&
                    row.scope_name === scope &&
                    String(row.scope_id) === String(id),
            );
        const global = nodeObject(find('global', 0)?.value),
            region = nodeObject(find('region', detail.value.region_id)?.value),
            local = find('node', detail.value.id);
        const defaults = {
            ...global,
            ...region,
            http: { ...nodeObject(global.http), ...nodeObject(region.http) },
        };
        inherited.cache = String(defaults.http.proxy_cache_dir ?? '');
        inherited.size = cacheGigabytes(defaults.http.proxy_cache_max_size);
        inherited.logs = String(region.logs_dir ?? global.logs_dir ?? '');
        originalConfig.value = nodeObject(local?.value);
        configEnable.value =
            local?.enable === undefined ? undefined : Number(local.enable);
        const http = nodeObject(originalConfig.value.http);
        config.cache = String(http.proxy_cache_dir ?? '');
        config.size = cacheGigabytes(http.proxy_cache_max_size);
        config.logs = String(originalConfig.value.logs_dir ?? '');
        configReady.value = true;
    } catch (e) {
        if (ticket === generation) {
            configError.value = message(e);
        }
    } finally {
        if (ticket === generation) {
            configLoading.value = false;
        }
    }
}
function selectTab(value: string) {
    tab.value = value;
    error.value = '';

    if (value === 'config' && !configReady.value && !configLoading.value) {
        void loadConfig();
    }
}
async function save() {
    if (saving.value || loading.value) {
        return;
    }

    const id = Number(detail.value.id);

    if (!id) {
        return;
    }

    saving.value = true;
    error.value = '';

    try {
        if (tab.value === 'basic') {
            if (!basic.name.trim() || !basic.ip.trim()) {
                throw new Error('名称和 IP 不能为空');
            }

            if (
                !String(basic.sort).trim() ||
                !Number.isInteger(Number(basic.sort)) ||
                Number(basic.sort) < 0
            ) {
                throw new Error('请输入有效的排序值');
            }

            await updateAdminNode(id, {
                name: basic.name.trim(),
                des: basic.des.trim(),
                sort: Number(basic.sort),
                ip: basic.ip.trim(),
                type: basic.type,
            });
        } else if (tab.value === 'config') {
            if (!configReady.value || configError.value) {
                return;
            }

            await apiRequest('/api/admin/configs', {
                method: 'PUT',
                body: JSON.stringify({
                    scope_name: 'node',
                    scope_id: id,
                    type: 'nginx_config',
                    name: 'nginx-config-file',
                    value: JSON.stringify(
                        nginxNodeConfig(originalConfig.value, config),
                    ),
                    ...(configEnable.value === undefined
                        ? {}
                        : { enable: configEnable.value }),
                }),
            });
        } else if (tab.value === 'location') {
            await updateAdminNode(id, {
                ip_location: Object.fromEntries(
                    Object.entries(location).map(([key, value]) => [
                        key,
                        value.trim(),
                    ]),
                ),
            });
        } else if (tab.value === 'auto') {
            if (
                bandwidth.value.trim() &&
                !/^\d+(?:\.\d+)?$/.test(bandwidth.value.trim())
            ) {
                throw new Error('请输入有效的带宽限制');
            }

            const periods = disableTime.value.trim();

            if (
                periods &&
                !/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d-(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\s+(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d-(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d)*$/.test(
                    periods,
                )
            ) {
                throw new Error('禁用时间段格式不正确');
            }

            await updateAdminNode(id, {
                bw_limit: bandwidth.value.trim()
                    ? bandwidth.value.trim() + bandwidthUnit.value
                    : '',
                traffic_limit: trafficPayload(traffic),
                disable_time: periods,
            });
        } else {
            const ips = [
                ...new Set(
                    subips.value
                        .split(/\r?\n/)
                        .map((ip) => ip.trim())
                        .filter(Boolean),
                ),
            ];

            if (!ips.length) {
                throw new Error('请输入至少一个子 IP，每行一个');
            }

            await addAdminNodeSubIps(id, ips);
        }

        toast.success(tab.value === 'subip' ? '子 IP 已添加' : '修改成功');
        open.value = false;
        emit('saved');
    } catch (e) {
        error.value = message(e);
    } finally {
        saving.value = false;
    }
}
</script>
<template>
    <Dialog v-model:open="open">
        <DialogScrollContent
            class="node-edit-dialog w-[calc(100%-24px)] max-w-[440px] gap-0 bg-card p-0"
            @escape-key-down="saving && $event.preventDefault()"
            @pointer-down-outside="saving && $event.preventDefault()"
        >
            <DialogHeader class="border-b px-4 py-3 text-left"
                ><DialogTitle class="text-sm font-normal">编辑节点</DialogTitle
                ><DialogDescription class="sr-only"
                    >编辑节点基本设置、节点设置、IP归属、自动禁用或添加子IP。</DialogDescription
                ></DialogHeader
            >
            <div class="px-4 pt-4 pb-5">
                <div
                    class="edit-tabs mb-3 flex border-b"
                    role="tablist"
                    aria-label="编辑节点设置"
                >
                    <button
                        v-for="item in tabs"
                        :key="item.key"
                        type="button"
                        role="tab"
                        :aria-selected="tab === item.key"
                        :disabled="loading || saving"
                        @click="selectTab(item.key)"
                    >
                        {{ item.label }}
                    </button>
                </div>
                <p
                    v-if="loading"
                    class="p-6 text-center text-xs text-muted-foreground"
                >
                    加载中…
                </p>
                <p
                    v-if="error"
                    role="alert"
                    class="mb-3 text-xs text-destructive"
                >
                    {{ error }}
                    <button
                        v-if="!detail.id"
                        type="button"
                        class="underline"
                        @click="loadDetail"
                    >
                        重试
                    </button>
                </p>
                <form v-if="!loading && detail.id" @submit.prevent="save">
                    <fieldset :disabled="saving" class="min-w-0">
                        <div v-if="tab === 'basic'" class="edit-fields">
                            <label for="edit-node-name">名称</label
                            ><Input
                                id="edit-node-name"
                                v-model="basic.name"
                                required
                            />
                            <label for="edit-node-des">备注</label
                            ><Input
                                id="edit-node-des"
                                v-model="basic.des"
                                placeholder="请输入备注"
                            />
                            <label for="edit-node-sort">排序</label
                            ><Input
                                id="edit-node-sort"
                                v-model="basic.sort"
                                type="number"
                                min="0"
                                step="1"
                                required
                            />
                            <label for="edit-node-ip">IP</label>
                            <div>
                                <Input
                                    id="edit-node-ip"
                                    v-model="basic.ip"
                                    required
                                />
                                <p class="help">
                                    如果新IP是不同节点，请使用待初始化里的替换节点功能
                                </p>
                            </div>
                            <span class="field-label">类型</span>
                            <div>
                                <RadioGroup
                                    v-model="basic.type"
                                    aria-label="节点类型"
                                    class="flex flex-wrap gap-2"
                                >
                                    <label class="radio-label"
                                        ><RadioGroupItem
                                            value="L1"
                                        />L1边缘节点</label
                                    ><label class="radio-label"
                                        ><RadioGroupItem
                                            value="L2"
                                        />L2中间节点</label
                                    >
                                </RadioGroup>
                                <p class="help">
                                    L1边缘节点是用户实际访问的节点;<br />L2中间节点是L1与源服务器之间的节点，用于汇聚L1节点请求，提高缓存命中率，或者优化回源线路
                                </p>
                            </div>
                        </div>
                        <template v-else-if="tab === 'config'">
                            <p
                                v-if="configLoading"
                                class="p-6 text-center text-xs text-muted-foreground"
                            >
                                加载中…
                            </p>
                            <p
                                v-else-if="configError"
                                role="alert"
                                class="text-xs text-destructive"
                            >
                                {{ configError }}
                                <button
                                    type="button"
                                    class="underline"
                                    @click="loadConfig"
                                >
                                    重试加载
                                </button>
                            </p>
                            <div v-else-if="configReady" class="edit-fields">
                                <label for="edit-node-cache">缓存目录</label
                                ><Input
                                    id="edit-node-cache"
                                    v-model="config.cache"
                                    :placeholder="inherited.cache"
                                /><label for="edit-node-size">缓存上限</label>
                                <div
                                    data-slot="console-input-group"
                                    class="input-group"
                                >
                                    <Input
                                        id="edit-node-size"
                                        v-model="config.size"
                                        type="number"
                                        min="0"
                                        step="any"
                                        :placeholder="inherited.size"
                                    /><span>GB</span>
                                </div>
                                <label for="edit-node-logs">日志目录</label
                                ><Input
                                    id="edit-node-logs"
                                    v-model="config.logs"
                                    :placeholder="inherited.logs"
                                />
                            </div>
                        </template>
                        <div v-else-if="tab === 'location'" class="edit-fields">
                            <span class="field-label">说明</span>
                            <p class="help mt-0!">
                                这些数据用于条件源站和L2条件里的国家代码，省份，城市，ISP的匹配。默认由节点IP自动解析生成；只有IP库解析不准、节点实际出口/Anycast与管理IP不一致，或需要临时修正条件调度时才编辑。
                            </p>
                            <template
                                v-for="field in locationFields"
                                :key="field.key"
                                ><label :for="`edit-node-${field.key}`">{{
                                    field.label
                                }}</label
                                ><Input
                                    :id="`edit-node-${field.key}`"
                                    v-model="location[field.key]"
                            /></template>
                        </div>
                        <div
                            v-else-if="tab === 'auto'"
                            class="edit-fields auto-fields"
                        >
                            <label for="edit-node-bandwidth">带宽限制</label>
                            <div
                                data-slot="console-input-group"
                                class="input-group"
                            >
                                <Input
                                    id="edit-node-bandwidth"
                                    v-model="bandwidth"
                                    placeholder="留空不限制"
                                /><SelectField
                                    v-model="bandwidthUnit"
                                    aria-label="带宽单位"
                                >
                                    <SelectOption value="Mbps"
                                        >Mbps</SelectOption
                                    >
                                    <SelectOption value="Gbps"
                                        >Gbps</SelectOption
                                    >
                                </SelectField>
                            </div>
                            <span class="field-label">流量限制</span
                            ><Switch
                                :checked="traffic.enable"
                                aria-label="流量限制"
                                @update:checked="
                                    traffic.enable = !traffic.enable
                                "
                            />
                            <label for="edit-node-day">统计周期:</label>
                            <div class="grid justify-start gap-2">
                                <div
                                    data-slot="console-input-group"
                                    class="input-group w-[125px]!"
                                >
                                    <span>每月</span
                                    ><Input
                                        id="edit-node-day"
                                        v-model="traffic.from_day"
                                        type="number"
                                        min="1"
                                        max="31"
                                        required
                                    /><span>日,</span>
                                </div>
                                <div
                                    data-slot="console-input-group"
                                    class="input-group"
                                >
                                    <Input
                                        v-model="traffic.from_hour"
                                        aria-label="统计起始时间"
                                        class="w-[80px]!"
                                        placeholder="12:00:00"
                                        required
                                    /><span>起，统计一个月</span>
                                </div>
                            </div>
                            <label for="edit-node-total">流量限制:</label>
                            <div
                                data-slot="console-input-group"
                                class="input-group"
                            >
                                <Input
                                    id="edit-node-total"
                                    v-model="traffic.traffic_total"
                                    type="number"
                                    min="0"
                                    step="any"
                                    required
                                /><span>GB</span>
                            </div>
                            <span class="field-label">流量类型:</span>
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="radio-label"
                                    ><CheckboxField
                                        v-model="traffic.type"
                                        value="outbound"
                                    />出站流量</label
                                ><label class="radio-label"
                                    ><CheckboxField
                                        v-model="traffic.type"
                                        value="inbound"
                                    />入站流量</label
                                >
                            </div>
                            <label for="edit-node-nics">网卡过滤:</label
                            ><Input
                                id="edit-node-nics"
                                v-model="traffic.excl_nic"
                                placeholder="如eth0，多个网卡空格分隔"
                            />
                            <label for="edit-node-disable">禁用时间段</label
                            ><Input
                                id="edit-node-disable"
                                v-model="disableTime"
                                placeholder="格式为00:00:00-03:00:00 08:00:00-22:00:00，多个时间段空格分隔"
                            />
                        </div>
                        <div v-else class="edit-fields">
                            <label for="edit-node-subips">子IP</label
                            ><Textarea
                                id="edit-node-subips"
                                v-model="subips"
                                rows="5"
                                placeholder="一行一个"
                                required
                            />
                        </div>
                        <div class="edit-actions mt-6 flex gap-2">
                            <button
                                data-slot="console-action"
                                type="submit"
                                class="primary"
                                :disabled="
                                    saving ||
                                    (tab === 'config' &&
                                        (!configReady || !!configError))
                                "
                            >
                                {{ saving ? '保存中…' : '确定' }}</button
                            ><button
                                data-slot="console-action"
                                type="button"
                                class="cancel"
                                @click="open = false"
                            >
                                取消
                            </button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </DialogScrollContent>
    </Dialog>
</template>
<style scoped>
.edit-tabs {
    gap: 3px;
}
.edit-tabs button {
    margin-bottom: -1px;
    white-space: nowrap;
    padding: 5px 12px;
    font-size: 12px;
    border: 1px solid var(--border);
    border-radius: 3px 3px 0 0;
    background: var(--muted);
}
.edit-tabs button[aria-selected='true'] {
    color: #2d8cf0;
    border-color: #2d8cf0;
    border-bottom-color: var(--card);
    background: var(--card);
}
.edit-fields {
    display: grid;
    grid-template-columns: 56px minmax(0, 1fr);
    gap: 22px 10px;
    align-items: start;
    font-size: 12px;
}
.edit-fields > label,
.field-label {
    padding-top: 5px;
    text-align: right;
}
.edit-fields input:not([type='radio']):not([type='checkbox']),
.edit-fields :deep([data-slot='textarea']),
.edit-fields :deep([data-slot='select-trigger']) {
    min-width: 0;
    width: 100%;
    height: 28px;
    padding: 3px 7px;
    border: 1px solid var(--border);
    border-radius: 3px;
    background: var(--card);
    outline-color: #2d8cf0;
}
.edit-fields input::placeholder,
.edit-fields :deep([data-slot='textarea'])::placeholder {
    color: var(--muted-foreground);
    opacity: 0.55;
}
.edit-fields :deep([data-slot='textarea']) {
    height: auto;
    resize: vertical;
}
.help {
    margin-top: 5px;
    font-size: 11px;
    line-height: 1.7;
    color: var(--muted-foreground);
}
.radio-label {
    display: flex;
    align-items: center;
    gap: 3px;
    white-space: nowrap;
}
.radio-label input {
    accent-color: #2d8cf0;
}
.input-group {
    display: flex;
    min-width: 0;
    align-items: stretch;
}
.input-group input {
    flex: 1;
    border-radius: 3px 0 0 3px !important;
}
.input-group span,
.input-group :deep([data-slot='select-trigger']) {
    display: flex;
    align-items: center;
    white-space: nowrap;
    padding: 3px 7px;
    background: var(--muted);
    border: 1px solid var(--border);
    border-radius: 0 3px 3px 0;
    font-size: 12px;
}
.input-group > * + * {
    margin-left: -1px;
}
.input-group :deep([data-slot='select-trigger']) {
    width: 70px;
}
.edit-actions {
    margin-left: 66px;
}
.edit-actions button {
    border-radius: 3px;
    padding: 5px 12px;
    font-size: 12px;
}
.primary {
    background: #2d8cf0;
    color: white;
    border: 1px solid #2d8cf0;
}
.cancel {
    border: 1px solid var(--border);
}
button:disabled {
    opacity: 0.5;
    cursor: default;
}
.auto-fields {
    grid-template-columns: 70px minmax(0, 1fr);
    gap: 20px 10px;
}
@media (max-width: 450px) {
    .edit-tabs button {
        padding: 5px 7px;
    }
}
</style>
