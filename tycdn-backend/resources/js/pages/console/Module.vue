<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ConsoleModuleView from '@/components/console/ConsoleModuleView.vue';
import { consoleModules, fallbackModule } from '@/lib/consoleData';
import AdminDns from './AdminDns.vue';
import AdminFinance from './AdminFinance.vue';
import AdminMonitoring from './AdminMonitoring.vue';
import AdminNodes from './AdminNodes.vue';
import AdminOverview from './AdminOverview.vue';
import AdminPackages from './AdminPackages.vue';
import AdminSecurity from './AdminSecurity.vue';
import AdminSettings from './AdminSettings.vue';
import AdminSites from './AdminSites.vue';
import AdminStreams from './AdminStreams.vue';
import AdminUsers from './AdminUsers.vue';
import UserAccount from './UserAccount.vue';
import UserAnalytics from './UserAnalytics.vue';
import UserBilling from './UserBilling.vue';
import UserCache from './UserCache.vue';
import UserCertificates from './UserCertificates.vue';
import UserMessages from './UserMessages.vue';
import UserSecurity from './UserSecurity.vue';
import UserSites from './UserSites.vue';
import UserStreams from './UserStreams.vue';

const props = defineProps<{
    moduleKey: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Console',
                href: '/console',
            },
        ],
    },
});

const module = computed(
    () => consoleModules[props.moduleKey] ?? fallbackModule,
);
const pageTitle = computed(
    () =>
        ({
            'billing-traffic-packs': '流量包',
            'billing-usage': '用量查询',
        })[props.moduleKey] ?? module.value.title,
);
</script>

<template>
    <Head :title="pageTitle" />
    <AdminOverview v-if="props.moduleKey === 'admin-overview'" />
    <AdminUsers v-else-if="props.moduleKey === 'admin-users'" />
    <AdminPackages v-else-if="props.moduleKey === 'admin-packages'" />
    <AdminSites v-else-if="props.moduleKey === 'admin-sites'" />
    <AdminNodes v-else-if="props.moduleKey === 'admin-nodes'" />
    <AdminDns v-else-if="props.moduleKey === 'admin-dns'" />
    <AdminStreams v-else-if="props.moduleKey === 'admin-streams'" />
    <AdminFinance v-else-if="props.moduleKey === 'admin-finance'" />
    <AdminMonitoring v-else-if="props.moduleKey === 'admin-monitoring'" />
    <AdminSettings v-else-if="props.moduleKey === 'admin-settings'" />
    <AdminSecurity v-else-if="props.moduleKey === 'admin-security'" />
    <UserSites v-else-if="props.moduleKey === 'sites'" />
    <UserCertificates v-else-if="props.moduleKey === 'certificates'" />
    <UserSites v-else-if="props.moduleKey === 'dnsapis'" initial-tab="dnsapi" />
    <UserCache
        v-else-if="
            props.moduleKey === 'cache' || props.moduleKey === 'cache-jobs'
        "
    />
    <UserSecurity v-else-if="props.moduleKey === 'security-acls'" view="acls" />
    <UserSecurity v-else-if="props.moduleKey === 'security-cc'" view="cc" />
    <UserSecurity
        v-else-if="props.moduleKey === 'security-blackip'"
        view="blackip"
    />
    <UserAnalytics
        v-else-if="props.moduleKey === 'analytics-realtime'"
        view="realtime"
    />
    <UserAnalytics v-else-if="props.moduleKey === 'analytics-top'" view="top" />
    <UserAnalytics
        v-else-if="props.moduleKey === 'analytics-logs'"
        view="logs"
    />
    <UserAnalytics
        v-else-if="props.moduleKey === 'analytics-usage'"
        view="usage"
    />
    <UserStreams v-else-if="props.moduleKey === 'streams'" view="list" />
    <UserStreams
        v-else-if="props.moduleKey === 'streams-analytics'"
        view="analytics"
    />
    <UserBilling
        v-else-if="props.moduleKey === 'billing-packages'"
        view="packages"
    />
    <UserBilling
        v-else-if="props.moduleKey === 'billing-subscriptions'"
        view="subscriptions"
    />
    <UserBilling
        v-else-if="props.moduleKey === 'billing-traffic-packs'"
        view="traffic-packs"
    />
    <UserBilling
        v-else-if="props.moduleKey === 'billing-usage'"
        view="usage"
    />
    <UserBilling
        v-else-if="props.moduleKey === 'billing-orders'"
        view="orders"
    />
    <UserMessages v-else-if="props.moduleKey === 'messages'" view="messages" />
    <UserMessages
        v-else-if="props.moduleKey === 'message-subscriptions'"
        view="subscriptions"
    />
    <UserAccount
        v-else-if="props.moduleKey === 'account-profile'"
        view="profile"
    />
    <UserAccount
        v-else-if="props.moduleKey === 'account-certification'"
        view="certification"
    />
    <UserAccount
        v-else-if="props.moduleKey === 'account-api-key'"
        view="api-key"
    />
    <UserAccount
        v-else-if="props.moduleKey === 'account-login-logs'"
        view="login-logs"
    />
    <ConsoleModuleView v-else :module-key="props.moduleKey" />
</template>
