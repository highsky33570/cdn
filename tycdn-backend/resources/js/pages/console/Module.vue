<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { computed } from 'vue';
import { settingsSections } from '@/lib/configSections';
import { consoleModules, fallbackModule } from '@/lib/consoleData';
import { consoleNavigationTitle } from '@/lib/consoleNavigation';
import { masterResources } from '@/lib/masterResources';
import AdminAccessLogs from './AdminAccessLogs.vue';
import AdminAnnouncements from './AdminAnnouncements.vue';
import AdminBlockLogs from './AdminBlockLogs.vue';
import AdminCache from './AdminCache.vue';
import AdminCertificates from './AdminCertificates.vue';
import AdminConfigWorkspace from './AdminConfigWorkspace.vue';
import AdminDefaults from './AdminDefaults.vue';
import AdminDns from './AdminDns.vue';
import AdminErrorPages from './AdminErrorPages.vue';
import AdminFinance from './AdminFinance.vue';
import AdminFirewall from './AdminFirewall.vue';
import AdminL2 from './AdminL2.vue';
import AdminLineGroups from './AdminLineGroups.vue';
import AdminMaintenance from './AdminMaintenance.vue';
import AdminMarketing from './AdminMarketing.vue';
import AdminMasterResources from './AdminMasterResources.vue';
import AdminMasterUsers from './AdminMasterUsers.vue';
import AdminMessageQuery from './AdminMessageQuery.vue';
import AdminMonitoring from './AdminMonitoring.vue';
import AdminNginx from './AdminNginx.vue';
import AdminNodeMonitoring from './AdminNodeMonitoring.vue';
import AdminNodeMonitorSettings from './AdminNodeMonitorSettings.vue';
import AdminNodes from './AdminNodes.vue';
import AdminOrders from './AdminOrders.vue';
import AdminOverview from './AdminOverview.vue';
import AdminPackageMonitor from './AdminPackageMonitor.vue';
import AdminPackages from './AdminPackages.vue';
import AdminPackageUpgrades from './AdminPackageUpgrades.vue';
import AdminRecharge from './AdminRecharge.vue';
import AdminRechargeStats from './AdminRechargeStats.vue';
import AdminResources from './AdminResources.vue';
import AdminSecurity from './AdminSecurity.vue';
import AdminSites from './AdminSites.vue';
import AdminSoldPackages from './AdminSoldPackages.vue';
import AdminStreamAnalytics from './AdminStreamAnalytics.vue';
import AdminStreams from './AdminStreams.vue';
import AdminTasks from './AdminTasks.vue';
import AdminTrafficPackages from './AdminTrafficPackages.vue';
import AdminUsage from './AdminUsage.vue';
import AdminWafLogs from './AdminWafLogs.vue';
import UserAccount from './UserAccount.vue';
import UserAccountLogs from './UserAccountLogs.vue';
import UserAnalytics from './UserAnalytics.vue';
import UserApiKey from './UserApiKey.vue';
import UserBilling from './UserBilling.vue';
import UserCache from './UserCache.vue';
import UserCertificates from './UserCertificates.vue';
import UserMessageQuery from './UserMessageQuery.vue';
import UserMessages from './UserMessages.vue';
import UserOrders from './UserOrders.vue';
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

/**
 * moduleKey -> the component to render, plus any props it needs.
 *
 * This replaced a 37-branch v-if / v-else-if chain. Inertia keeps this component
 * mounted across console navigations and only swaps `moduleKey`, so Vue patched
 * one branch of that chain into the next *in place*, reusing DOM nodes. Where the
 * branches did not line up structurally -- several pages have fragment roots,
 * since <Head> renders nothing into the DOM -- the node bookkeeping desynced and
 * the renderer threw:
 *
 *   NotFoundError: Failed to execute 'insertBefore' on 'Node'
 *
 * After that the component tree is inconsistent, so every later patch fails and
 * navigation is dead until a full reload.
 *
 * A lookup plus `:key` on <component> makes each module a clean unmount/mount,
 * which removes the whole class of problem and is far easier to extend.
 */
const MODULES: Record<
    string,
    { component: Component; props?: Record<string, string> }
> = {
    'admin-overview': { component: AdminOverview },
    'admin-analytics-logs': { component: AdminAccessLogs },
    'admin-users': { component: AdminMasterUsers },
    'admin-packages': { component: AdminPackages },
    'admin-sites': { component: AdminSites },
    'admin-nodes': { component: AdminNodes },
    'admin-line-groups': {
        component: AdminLineGroups,
    },
    'admin-cache-jobs': { component: AdminCache },
    'admin-security-cc': { component: AdminSecurity, props: { view: 'cc' } },
    'admin-security-waf': { component: AdminSecurity, props: { view: 'waf' } },
    'admin-sold-packages': { component: AdminSoldPackages },
    'admin-finance-recharge': {
        component: AdminRecharge,
    },
    'admin-finance-orders': {
        component: AdminOrders,
    },
    'admin-finance-recharge-count': {
        component: AdminRechargeStats,
    },
    'admin-message-query': {
        component: AdminMessageQuery,
    },
    'admin-dns': { component: AdminDns },
    'admin-streams': { component: AdminStreams },
    'admin-finance': { component: AdminFinance },
    'admin-monitoring': { component: AdminMonitoring },
    'admin-settings': { component: AdminConfigWorkspace },
    'admin-maintenance': { component: AdminMaintenance },
    'admin-node-monitoring': { component: AdminNodeMonitoring },
    'admin-package-groups': {
        component: AdminPackages,
        props: { initialTab: 'groups' },
    },
    'admin-package-upgrades': { component: AdminPackageUpgrades },
    'admin-certificates': {
        component: AdminCertificates,
    },
    'admin-security': { component: AdminSecurity },
    'admin-workspace-blackip': { component: AdminBlockLogs },
    'admin-workspace-history-blackip': { component: AdminBlockLogs },

    sites: { component: UserSites },
    'site-groups': { component: UserSites, props: { initialTab: 'groups' } },
    certificates: { component: UserCertificates },
    dnsapis: { component: UserSites, props: { initialTab: 'dnsapi' } },

    cache: { component: UserCache },
    'cache-jobs': { component: UserCache },

    'security-acls': { component: UserSecurity, props: { view: 'acls' } },
    'security-cc': { component: UserSecurity, props: { view: 'cc' } },
    'security-blackip': { component: AdminBlockLogs, props: { scope: 'user' } },

    'analytics-realtime': {
        component: UserAnalytics,
        props: { view: 'realtime' },
    },
    'analytics-top': { component: UserAnalytics, props: { view: 'top' } },
    'analytics-logs': { component: AdminAccessLogs, props: { scope: 'user' } },
    'analytics-usage': { component: UserAnalytics, props: { view: 'usage' } },

    streams: { component: UserStreams, props: { view: 'list' } },
    'stream-groups': {
        component: AdminMasterResources,
        props: { resource: 'stream-groups', scope: 'user' },
    },
    'streams-analytics': {
        component: AdminStreamAnalytics,
        props: { scope: 'user' },
    },

    'billing-packages': { component: UserBilling, props: { view: 'packages' } },
    'billing-subscriptions': {
        component: UserBilling,
        props: { view: 'subscriptions' },
    },
    'billing-traffic-packs': {
        component: UserBilling,
        props: { view: 'traffic-packs' },
    },
    'billing-usage': { component: UserBilling, props: { view: 'usage' } },
    'billing-orders': { component: UserOrders },

    messages: { component: UserMessageQuery },
    'message-subscriptions': {
        component: UserMessages,
    },

    'account-profile': { component: UserAccount, props: { view: 'profile' } },
    'account-certification': {
        component: UserAccount,
        props: { view: 'certification' },
    },
    'account-api-key': { component: UserApiKey },
    'account-login-logs': {
        component: UserAccountLogs,
    },
};

for (const resource of Object.keys(masterResources)) {
    MODULES[`admin-workspace-${resource}`] = {
        component: AdminMasterResources,
        props: { resource },
    };
}

MODULES['admin-workspace-attack-log'] = { component: AdminWafLogs };
MODULES['admin-workspace-tasks'] = { component: AdminTasks };
MODULES['admin-workspace-messages'] = { component: AdminAnnouncements };
MODULES['admin-workspace-package-monitor'] = { component: AdminPackageMonitor };

for (const resource of ['discounts', 'coupons', 'coupon-historys']) {
    MODULES[`admin-workspace-${resource}`] = {
        component: AdminMarketing,
        props: { initialTab: resource },
    };
}

MODULES['admin-workspace-traffic-packages'] = {
    component: AdminTrafficPackages,
};
MODULES['admin-workspace-user-traffic-packages'] = {
    component: AdminTrafficPackages,
    props: { initialTab: 'sold' },
};

for (const resource of ['l2-configs', 'l2-conds']) {
    MODULES[`admin-workspace-${resource}`] = {
        component: AdminL2,
        props: { initialTab: resource },
    };
}

for (const { key: section } of settingsSections) {
    MODULES[`admin-config-${section}`] = {
        component: AdminConfigWorkspace,
        props: { section },
    };
}

for (const view of ['realtime', 'top', 'usage']) {
    MODULES[`admin-analytics-${view}`] = {
        component: UserAnalytics,
        props: { view, scope: 'admin' },
    };
}

MODULES['admin-config-node-monitor'] = { component: AdminNodeMonitorSettings };
MODULES['admin-analytics-usage'] = { component: AdminUsage };
MODULES['admin-config-firewall'] = { component: AdminFirewall };
MODULES['admin-config-nginx'] = { component: AdminNginx };
MODULES['admin-config-resources'] = { component: AdminResources };
MODULES['admin-config-defaults'] = { component: AdminDefaults };
MODULES['admin-config-errors'] = { component: AdminErrorPages };

MODULES['admin-streams-analytics'] = {
    component: AdminStreamAnalytics,
};
MODULES['stream-defaults'] = {
    component: AdminConfigWorkspace,
    props: { section: 'stream-defaults', scope: 'user' },
};

const module = computed(
    () => consoleModules[props.moduleKey] ?? fallbackModule,
);
const page = usePage();
const pageTitle = computed(
    () =>
        consoleNavigationTitle(page.url.split('?')[0]) ??
        {
            'billing-traffic-packs': '流量包',
            'billing-usage': '用量查询',
        }[props.moduleKey] ??
        module.value.title,
);

const resolved = computed(() => MODULES[props.moduleKey]);
const activeComponent = computed(() => resolved.value?.component);
const activeProps = computed(() =>
    resolved.value
        ? (resolved.value.props ?? {})
        : { moduleKey: props.moduleKey },
);
</script>

<template>
    <Head :title="pageTitle" />
    <component
        v-if="activeComponent"
        :is="activeComponent"
        :key="props.moduleKey"
        v-bind="activeProps"
    />
    <p v-else class="rounded-lg border bg-card p-6 text-muted-foreground">
        页面不存在，请从菜单选择功能。
    </p>
</template>
