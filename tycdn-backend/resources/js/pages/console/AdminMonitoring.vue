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

const siteRealtimeColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'name', label: '站点' },
    { key: 'domain', label: '域名' },
    { key: 'status', label: '状态', badge: true, width: '90px' },
    { key: 'bandwidth', label: '带宽', width: '100px' },
    { key: 'requests', label: '请求数', width: '100px' },
];

const streamRealtimeColumns: ColumnDef[] = [
    { key: 'id', label: 'ID', width: '70px' },
    { key: 'name', label: '转发' },
    { key: 'listen', label: '监听' },
    { key: 'status', label: '状态', badge: true, width: '90px' },
    { key: 'bandwidth', label: '带宽', width: '100px' },
    { key: 'connections', label: '连接数', width: '100px' },
];
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="监控与日志"
            :icon="Activity"
            :show-api-badge="false"
        />

        <ConsoleDataTable
            title="站点实时监控"
            :columns="siteRealtimeColumns"
            :fetch-fn="getAdminSiteRealtime"
            :page-size="15"
            search-placeholder="搜索站点"
        >
            <template #actions-col><col style="width: 0" /></template>
            <template #actions-header><th /></template>
        </ConsoleDataTable>

        <ConsoleDataTable
            title="四层实时监控"
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
