<script setup lang="ts">
import {
    Activity,
    Globe,
    KeyRound,
    Network,
    ScrollText,
} from 'lucide-vue-next';
import { ref } from 'vue';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import ConsoleTabs from '@/components/console/ConsoleTabs.vue';
import type { ConsoleTab } from '@/components/console/ConsoleTabs.vue';
import {
    getAdminSiteRealtime,
    getAdminStreamRealtime,
    listAdminLoginLogs,
    listAdminOpLogs,
} from '@/lib/adminModulesApi';
import { formatDate } from '@/lib/formatters';

const loginLogColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'uid', label: '用户 ID' },
    { key: 'ip', label: 'IP', width: '140px' },
    {
        key: 'success',
        format: (v) => (v === 1 || v === '1' || v === true ? '成功' : '失败'),
        label: '状态',
        badge: true,
        width: '90px',
    },
    { key: 'ip_location', label: 'IP 归属地' },
    {
        key: 'created_at',
        altKeys: ['create_at'],
        label: '时间',
        width: '140px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];

const opLogColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'uid', label: '用户 ID' },
    { key: 'action', label: '操作', badge: true, width: '80px' },
    { key: 'type', label: '资源类型' },
    { key: 'content', label: '内容' },
    { key: 'ip', label: 'IP', width: '140px' },
    {
        key: 'created_at',
        altKeys: ['create_at'],
        label: '时间',
        width: '140px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];

// CDNfly rankings use res/count/traffic/up_recv.
const siteRealtimeColumns: ColumnDef[] = [
    { key: 'res', label: '域名' },
    {
        key: 'count',
        label: '请求次数',
        width: '120px',
        align: 'right',
        format: (v) => formatCount(v),
    },
    {
        key: 'traffic',
        label: '出站流量',
        width: '130px',
        align: 'right',
        format: (v) => formatBytes(v),
    },
    {
        key: 'up_recv',
        label: '回源流量',
        width: '130px',
        align: 'right',
        format: (v) => formatBytes(v),
    },
];

const streamRealtimeColumns: ColumnDef[] = [
    { key: 'res', label: '端口' },
    {
        key: 'new_connections',
        altKeys: ['count'],
        label: '连接数',
        width: '120px',
        align: 'right',
        format: (v) => formatCount(v),
    },
    {
        key: 'outbound_traffic',
        altKeys: ['traffic'],
        label: '流量',
        width: '130px',
        align: 'right',
        format: (v) => formatBytes(v),
    },
];

type MonitorTab = 'sites' | 'streams' | 'login' | 'ops';

const activeTab = ref<MonitorTab>('sites');

const monitorTabs: ConsoleTab[] = [
    { key: 'sites', label: '站点排行', icon: Globe },
    { key: 'streams', label: '四层排行', icon: Network },
    { key: 'login', label: '登录日志', icon: KeyRound },
    { key: 'ops', label: '操作日志', icon: ScrollText },
];

function formatCount(value: unknown): string {
    const n = Number(value);

    return Number.isFinite(n) ? n.toLocaleString() : '-';
}

function formatBytes(value: unknown): string {
    const n = Number(value);

    if (!Number.isFinite(n)) {
        return '-';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = n;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }

    return `${size.toFixed(unit === 0 ? 0 : 2)} ${units[unit]}`;
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="监控与日志"
            :icon="Activity"
            :show-api-badge="false"
        />

        <ConsoleTabs v-model="activeTab" :tabs="monitorTabs" />

        <ConsoleDataTable
            :key="activeTab"
            v-if="activeTab === 'sites'"
            title="站点排行（近 30 分钟）"
            :columns="siteRealtimeColumns"
            :fetch-fn="getAdminSiteRealtime"
            :page-size="15"
            client-side
            search-placeholder="搜索站点"
        >
            <template #actions-col><col style="width: 0" /></template>
            <template #actions-header><th /></template>
        </ConsoleDataTable>

        <ConsoleDataTable
            :key="activeTab"
            v-else-if="activeTab === 'streams'"
            title="四层排行（近 30 分钟）"
            :columns="streamRealtimeColumns"
            :fetch-fn="getAdminStreamRealtime"
            :page-size="15"
            client-side
            search-placeholder="搜索转发"
        >
            <template #actions-col><col style="width: 0" /></template>
            <template #actions-header><th /></template>
        </ConsoleDataTable>

        <ConsoleDataTable
            :key="activeTab"
            v-else-if="activeTab === 'login'"
            title="登录日志"
            :columns="loginLogColumns"
            :fetch-fn="listAdminLoginLogs"
            search-key="ip"
            search-placeholder="搜索 IP"
        >
            <template #actions-col><col style="width: 0" /></template>
            <template #actions-header><th /></template>
        </ConsoleDataTable>

        <ConsoleDataTable
            :key="activeTab"
            v-else
            title="操作日志"
            :columns="opLogColumns"
            :fetch-fn="listAdminOpLogs"
            search-key="content"
            search-placeholder="搜索操作内容"
        >
            <template #actions-col><col style="width: 0" /></template>
            <template #actions-header><th /></template>
        </ConsoleDataTable>
    </div>
</template>
