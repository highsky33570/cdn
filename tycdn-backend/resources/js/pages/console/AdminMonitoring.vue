<script setup lang="ts">
import { Activity } from 'lucide-vue-next';
import ConsoleDataTable from '@/components/console/ConsoleDataTable.vue';
import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import {
    getAdminSiteRealtime,
    getAdminStreamRealtime,
    listAdminLoginLogs,
    listAdminOpLogs,
} from '@/lib/adminModulesApi';
import { formatDate } from '@/lib/formatters';

const loginLogColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'username', label: '账号' },
    { key: 'ip', label: 'IP', width: '140px' },
    { key: 'status', label: '状态', badge: true, width: '90px' },
    { key: 'reason', label: '原因' },
    {
        key: 'created_at',
        label: '时间',
        width: '140px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];

const opLogColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'username', label: '账号' },
    { key: 'method', label: '方法', badge: true, width: '80px' },
    { key: 'path', label: '路径' },
    { key: 'ip', label: 'IP', width: '140px' },
    {
        key: 'created_at',
        label: '时间',
        width: '140px',
        format: (v) => formatDate(v as string | null | undefined),
    },
];

// /v1/monitor/site/top?type=top-domain returns a domain ranking, not site rows:
// {domain, req, traffic, backend_traffic}. The old ID/站点/状态 columns matched
// nothing this endpoint returns, so even a successful call rendered as dashes.
const siteRealtimeColumns: ColumnDef[] = [
    { key: 'domain', label: '域名' },
    {
        key: 'req',
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
        key: 'backend_traffic',
        label: '回源流量',
        width: '130px',
        align: 'right',
        format: (v) => formatBytes(v),
    },
];

const streamRealtimeColumns: ColumnDef[] = [
    { key: 'port', label: '端口' },
    {
        key: 'conn',
        label: '连接数',
        width: '120px',
        align: 'right',
        format: (v) => formatCount(v),
    },
    {
        key: 'traffic',
        label: '流量',
        width: '130px',
        align: 'right',
        format: (v) => formatBytes(v),
    },
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

        <ConsoleDataTable
            title="站点排行（近 30 分钟）"
            :columns="siteRealtimeColumns"
            :fetch-fn="getAdminSiteRealtime"
            :page-size="15"
            search-placeholder="搜索站点"
        >
            <template #actions-col><col style="width: 0" /></template>
            <template #actions-header><th /></template>
        </ConsoleDataTable>

        <ConsoleDataTable
            title="四层排行（近 30 分钟）"
            :columns="streamRealtimeColumns"
            :fetch-fn="getAdminStreamRealtime"
            :page-size="15"
            search-placeholder="搜索转发"
        >
            <template #actions-col><col style="width: 0" /></template>
            <template #actions-header><th /></template>
        </ConsoleDataTable>

        <ConsoleDataTable
            title="登录日志"
            :columns="loginLogColumns"
            :fetch-fn="listAdminLoginLogs"
            search-placeholder="搜索账号 / IP"
        >
            <template #actions-col><col style="width: 0" /></template>
            <template #actions-header><th /></template>
        </ConsoleDataTable>

        <ConsoleDataTable
            title="操作日志"
            :columns="opLogColumns"
            :fetch-fn="listAdminOpLogs"
            search-placeholder="搜索账号 / 路径"
        >
            <template #actions-col><col style="width: 0" /></template>
            <template #actions-header><th /></template>
        </ConsoleDataTable>
    </div>
</template>
