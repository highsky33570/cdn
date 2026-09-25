<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import MaintenanceTable from '@/components/console/MaintenanceTable.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { apiRequest } from '@/lib/apiRequest';
import { isCdnflyRecord } from '@/lib/cdnflyResponse';
import { masterGet } from '@/lib/masterApi';
const sections = [
    { key: 'overview', title: '系统与 Agent 状态' },
    { key: 'master-upgrades', title: '主控版本' },
    { key: 'agent-upgrades', title: '节点版本' },
    { key: 'transfer-status', title: '迁移状态' },
    { key: 'license', title: '授权信息' },
];
const logSections = [
    {
        key: 'master-upgrade-log',
        title: '主控升级日志',
        emptyMessage: '暂无主控升级日志。',
    },
    { key: 'transfer-log', title: '迁移日志', emptyMessage: '暂无迁移日志。' },
];
const values = ref<Record<string, unknown>>({}),
    errors = ref<Record<string, string>>({}),
    notices = ref<Record<string, string>>({}),
    loading = ref(false);
const pending = ref<Record<string, boolean>>({});
const logOpen = ref(false),
    logType = ref('master-upgrade-log');
const logSection = computed(
    () => logSections.find((section) => section.key === logType.value)!,
);
const upgradeOpen = ref(false),
    upgrading = ref(false),
    upgradeError = ref(''),
    targetVersion = ref('');
const master = computed(() =>
    isCdnflyRecord(values.value['master-upgrades'])
        ? values.value['master-upgrades']
        : {},
);
const upgradeRunning = computed(() =>
    [true, 1, '1'].includes(
        master.value.upgrade_run as boolean | number | string,
    ),
);
const availableVersions = computed(() => {
    const value = master.value;
    const candidates = Array.isArray(value.available_versions)
        ? value.available_versions
        : [
              {
                  version_num: value.latest_version_num,
                  version_name: value.latest_version_name,
              },
          ];

    return candidates
        .filter(isCdnflyRecord)
        .filter(
            (version) =>
                Number.isInteger(Number(version.version_num)) &&
                Number(version.version_num) > Number(value.version_num),
        );
});
watch(availableVersions, (versions) => {
    if (
        !versions.some(
            (version) => String(version.version_num) === targetVersion.value,
        )
    ) {
        targetVersion.value = versions.length
            ? String(versions[0].version_num)
            : '';
    }
});
const canUpgrade = computed(
    () =>
        !upgrading.value &&
        !pending.value['master-upgrades'] &&
        !errors.value['master-upgrades'] &&
        !upgradeRunning.value &&
        availableVersions.value.some(
            (version) => String(version.version_num) === targetVersion.value,
        ),
);
const requestTokens: Record<string, number> = {};
const labels = {
    current_version: '当前版本',
    latest_version: '最新版本',
    version: '版本',
    version_name: '当前版本',
    version_num: '当前版本号',
    latest_version_name: '最新版本',
    latest_version_num: '最新版本号',
    upgrade_run: '正在升级',
    agent_ver: '节点版本',
    name: '名称',
    ip: 'IP 地址',
    release_notes: '版本说明',
    release_note: '版本说明',
    es_status: 'Elasticsearch',
    agent_status: 'Agent 检查',
    check_at: '检查时间',
    state: '状态',
    msg: '详情',
    code: '状态码',
    expire: '到期时间',
    expire_at: '到期时间',
    domain_count: '域名数',
    node_count: '节点数',
    cert_count: '证书数',
    stream_port_count: '转发端口数',
    user_package_count: '已售套餐',
    log: '日志',
};
async function loadSection(section: {
    key: string;
    title: string;
    emptyMessage?: string;
}) {
    const token = (requestTokens[section.key] ?? 0) + 1;
    requestTokens[section.key] = token;
    pending.value[section.key] = true;
    errors.value[section.key] = '';
    notices.value[section.key] = '';
    values.value[section.key] = null;

    try {
        let result: unknown = await masterGet(section.key);

        if (token !== requestTokens[section.key]) {
            return;
        }

        while (
            isCdnflyRecord(result) &&
            'data' in result &&
            ('code' in result || 'ok' in result)
        ) {
            result = result.data;
        }

        values.value[section.key] =
            section.key === 'transfer-status' && typeof result === 'boolean'
                ? { state: result ? '迁移中' : '未在迁移' }
                : result;
        notices.value[section.key] = '';

        if (section.emptyMessage) {
            if (isCdnflyRecord(result) && result.available === false) {
                notices.value[section.key] =
                    typeof result.message === 'string'
                        ? result.message
                        : section.emptyMessage;
            } else if (
                result == null ||
                result === '' ||
                (typeof result === 'object' && Object.keys(result).length === 0)
            ) {
                notices.value[section.key] = section.emptyMessage;
            }
        }

        errors.value[section.key] = '';
    } catch (e) {
        if (token !== requestTokens[section.key]) {
            return;
        }

        errors.value[section.key] = e instanceof Error ? e.message : '加载失败';
    } finally {
        if (token === requestTokens[section.key]) {
            pending.value[section.key] = false;
        }
    }
}
async function load() {
    loading.value = true;
    await Promise.allSettled(sections.map(loadSection));
    loading.value = false;
}
function openLogs() {
    logOpen.value = true;
    void loadSection(logSection.value);
}
watch(
    logType,
    () => {
        if (logOpen.value) {
            void loadSection(logSection.value);
        }
    },
    { flush: 'sync' },
);
function openUpgrade() {
    upgradeError.value = '';
    upgradeOpen.value = true;
    void loadSection(sections[1]);
}
async function startUpgrade() {
    if (!canUpgrade.value) {
        return;
    }

    upgrading.value = true;
    upgradeError.value = '';

    try {
        await apiRequest('/api/admin/workspace/master-upgrades', {
            method: 'POST',
            body: JSON.stringify({
                action: 'start',
                version_num: Number(targetVersion.value),
            }),
        });
        toast.success('主控升级已启动');
        upgradeOpen.value = false;
        logType.value = 'master-upgrade-log';
        openLogs();
        await loadSection(sections[1]);
    } catch (e) {
        upgradeError.value = e instanceof Error ? e.message : '升级请求失败';
    } finally {
        upgrading.value = false;
    }
}
onMounted(load);
onUnmounted(() => {
    for (const key of Object.keys(requestTokens)) {
        requestTokens[key]++;
    }
});
</script>
<template>
    <div class="maintenance-workspace grid min-w-0 gap-5 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 data-typography="page-title" class="font-semibold">系统维护</h1>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" :disabled="loading" @click="load"
                    >刷新</Button
                ><Button variant="outline" @click="openLogs">查看日志</Button
                ><Button @click="openUpgrade">升级主控</Button>
            </div>
        </div>
        <div class="grid gap-5 xl:grid-cols-2">
            <section
                v-for="section in sections"
                :key="section.key"
                class="min-w-0 overflow-hidden rounded-xl border bg-card"
                :class="section.key === 'agent-upgrades' ? 'xl:col-span-2' : ''"
            >
                <h2
                    data-typography="section-title"
                    class="px-5 py-4 font-semibold"
                >
                    {{ section.title }}
                </h2>
                <MaintenanceTable
                    :title="section.title"
                    :value="notices[section.key] ? null : values[section.key]"
                    :labels="labels"
                    :loading="pending[section.key]"
                    :error="errors[section.key]"
                />
            </section>
        </div>
        <Dialog v-model:open="logOpen"
            ><DialogScrollContent
                class="max-h-[90dvh] max-w-5xl grid-rows-[auto_minmax(0,1fr)_auto] gap-0 overflow-hidden p-0"
                ><DialogHeader class="border-b px-5 py-4"
                    ><DialogTitle>查看日志</DialogTitle
                    ><DialogDescription class="sr-only"
                        >查看主控升级日志和迁移日志</DialogDescription
                    ></DialogHeader
                >
                <div class="min-h-0 min-w-0 overflow-y-auto p-5">
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-3"
                    >
                        <Select v-model="logType"
                            ><SelectTrigger class="w-52" aria-label="日志类型"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="section in logSections"
                                    :key="section.key"
                                    :value="section.key"
                                    >{{ section.title }}</SelectItem
                                ></SelectContent
                            ></Select
                        ><Button
                            variant="outline"
                            :disabled="pending[logType]"
                            @click="loadSection(logSection)"
                            >刷新日志</Button
                        >
                    </div>
                    <MaintenanceTable
                        :title="logSection.title"
                        :value="notices[logType] ? null : values[logType]"
                        :labels="labels"
                        log
                        :loading="pending[logType]"
                        :error="errors[logType]"
                        :empty-message="
                            notices[logType] || logSection.emptyMessage
                        "
                    />
                </div>
                <DialogFooter class="border-t px-5 py-4"
                    ><Button variant="outline" @click="logOpen = false"
                        >关闭</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
        <Dialog
            :open="upgradeOpen"
            @update:open="
                (value) => {
                    if (!upgrading) upgradeOpen = value;
                }
            "
            ><DialogScrollContent
                class="max-h-[90dvh] max-w-xl grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden"
                ><DialogHeader
                    ><DialogTitle>升级主控</DialogTitle
                    ><DialogDescription
                        >选择目标版本，确认后开始升级。</DialogDescription
                    ></DialogHeader
                >
                <div class="min-h-0 space-y-5 overflow-y-auto">
                    <div
                        v-if="pending['master-upgrades']"
                        class="flex items-center gap-2 py-4"
                    >
                        <Spinner />正在获取版本信息…
                    </div>
                    <Alert
                        v-else-if="errors['master-upgrades']"
                        variant="destructive"
                        ><AlertDescription
                            >{{ errors['master-upgrades']
                            }}<Button
                                variant="outline"
                                size="sm"
                                class="ml-2"
                                @click="loadSection(sections[1])"
                                >重试</Button
                            ></AlertDescription
                        ></Alert
                    ><template v-else
                        ><dl
                            class="grid grid-cols-2 gap-3 rounded-lg border bg-muted/20 p-4"
                        >
                            <dt class="text-muted-foreground">当前版本</dt>
                            <dd>
                                {{
                                    master.version_name ||
                                    master.current_version ||
                                    '—'
                                }}
                            </dd>
                            <dt class="text-muted-foreground">最新版本</dt>
                            <dd>
                                {{
                                    master.latest_version_name ||
                                    master.latest_version ||
                                    '—'
                                }}
                            </dd>
                        </dl>
                        <p
                            data-typography="body"
                            v-if="upgradeRunning"
                            role="status"
                        >
                            主控正在升级，可通过“查看日志”查看进度。
                        </p>
                        <p
                            data-typography="body"
                            v-else-if="!availableVersions.length"
                            role="status"
                        >
                            暂无可升级版本。
                        </p>
                        <div v-else class="space-y-2">
                            <Label>目标版本</Label
                            ><Select
                                v-model="targetVersion"
                                :disabled="upgrading"
                                ><SelectTrigger
                                    class="w-full"
                                    aria-label="目标版本"
                                    ><SelectValue /></SelectTrigger
                                ><SelectContent
                                    ><SelectItem
                                        v-for="version in availableVersions"
                                        :key="String(version.version_num)"
                                        :value="String(version.version_num)"
                                        >{{
                                            version.version_name ||
                                            version.version_num
                                        }}</SelectItem
                                    ></SelectContent
                                ></Select
                            >
                        </div>
                        <p
                            data-typography="body"
                            v-if="master.release_notes || master.release_note"
                            class="break-words whitespace-pre-wrap text-muted-foreground"
                        >
                            {{ master.release_notes || master.release_note }}
                        </p></template
                    ><Alert v-if="upgradeError" variant="destructive"
                        ><AlertDescription>{{
                            upgradeError
                        }}</AlertDescription></Alert
                    >
                </div>
                <DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="upgrading"
                        @click="upgradeOpen = false"
                        >关闭</Button
                    ><Button :disabled="!canUpgrade" @click="startUpgrade"
                        ><Spinner
                            v-if="upgrading"
                            class="mr-1"
                        />确认升级</Button
                    ></DialogFooter
                ></DialogScrollContent
            ></Dialog
        >
    </div>
</template>
