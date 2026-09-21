<script setup lang="ts">
import { onMounted, ref } from 'vue';
import MaintenanceTable from '@/components/console/MaintenanceTable.vue';
import { Button } from '@/components/ui/button';
import { isCdnflyRecord } from '@/lib/cdnflyResponse';
import { masterGet } from '@/lib/masterApi';
const sections = [
    { key: 'overview', title: '系统与 Agent 状态' },
    { key: 'master-upgrades', title: '主控版本' },
    { key: 'agent-upgrades', title: '节点版本' },
    { key: 'transfer-status', title: '迁移状态' },
    { key: 'license', title: '授权信息' },
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
async function load() {
    loading.value = true;
    await Promise.allSettled(
        sections.map(async (section) => {
            try {
                let result: unknown = await masterGet(section.key);

                while (
                    isCdnflyRecord(result) &&
                    'data' in result &&
                    ('code' in result || 'ok' in result)
                ) {
                    result = result.data;
                }

                values.value[section.key] =
                    section.key === 'transfer-status' &&
                    typeof result === 'boolean'
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
                        (typeof result === 'object' &&
                            Object.keys(result).length === 0)
                    ) {
                        notices.value[section.key] = section.emptyMessage;
                    }
                }

                errors.value[section.key] = '';
            } catch (e) {
                errors.value[section.key] =
                    e instanceof Error ? e.message : '加载失败';
            }
        }),
    );
    loading.value = false;
}
onMounted(load);
</script>
<template>
    <div class="grid gap-5 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold">系统维护</h1>
            <div class="flex gap-2">
                <Button variant="outline" :disabled="loading" @click="load"
                    >刷新</Button
                ><Button as-child
                    ><a
                        href="https://panel.tycdn.org/dashboard/sys/maintain"
                        target="_blank"
                        rel="noopener noreferrer"
                        >在主控执行升级 / 迁移 ↗</a
                    ></Button
                >
            </div>
        </div>
        <div class="grid gap-5 xl:grid-cols-2">
            <section
                v-for="section in sections"
                :key="section.key"
                class="min-w-0 overflow-hidden rounded-xl border bg-card"
                :class="
                    section.emptyMessage || section.key === 'agent-upgrades'
                        ? 'xl:col-span-2'
                        : ''
                "
            >
                <h2 class="px-5 py-4 font-semibold">{{ section.title }}</h2>
                <MaintenanceTable
                    :title="section.title"
                    :value="notices[section.key] ? null : values[section.key]"
                    :labels="labels"
                    :log="Boolean(section.emptyMessage)"
                    :loading="loading"
                    :error="errors[section.key]"
                    :empty-message="
                        notices[section.key] || section.emptyMessage
                    "
                />
            </section>
        </div>
    </div>
</template>
