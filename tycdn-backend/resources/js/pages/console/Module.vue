<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { computed } from 'vue';
import { settingsSections } from '@/lib/configSections';
import { consoleModules, fallbackModule } from '@/lib/consoleData';
import { consoleNavigationTitle } from '@/lib/consoleNavigation';
import { masterResources } from '@/lib/masterResources';
import AdminAccessLogs from './AdminAccessLogs.vue';
import AdminBlockLogs from './AdminBlockLogs.vue';
import AdminConfigWorkspace from './AdminConfigWorkspace.vue';
import AdminDns from './AdminDns.vue';
import AdminFinance from './AdminFinance.vue';
import AdminMaintenance from './AdminMaintenance.vue';
import AdminMasterResources from './AdminMasterResources.vue';
import AdminMonitoring from './AdminMonitoring.vue';
import AdminNodeMonitoring from './AdminNodeMonitoring.vue';
import AdminNodes from './AdminNodes.vue';
import AdminOverview from './AdminOverview.vue';
import AdminPackages from './AdminPackages.vue';
import AdminSecurity from './AdminSecurity.vue';
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
    'admin-users': { component: AdminUsers },
    'admin-packages': { component: AdminPackages },
    'admin-sites': { component: AdminSites },
    'admin-nodes': { component: AdminNodes },
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
    'admin-package-upgrades': {
        component: AdminPackages,
        props: { initialTab: 'upgrades' },
    },
    'admin-certificates': {
        component: AdminSites,
        props: { initialTab: 'certificates' },
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
    'security-blackip': { component: UserSecurity, props: { view: 'blackip' } },

    'analytics-realtime': {
        component: UserAnalytics,
        props: { view: 'realtime' },
    },
    'analytics-top': { component: UserAnalytics, props: { view: 'top' } },
    'analytics-logs': { component: UserAnalytics, props: { view: 'logs' } },
    'analytics-usage': { component: UserAnalytics, props: { view: 'usage' } },

    streams: { component: UserStreams, props: { view: 'list' } },
    'stream-groups': {
        component: AdminMasterResources,
        props: { resource: 'stream-groups', scope: 'user' },
    },
    'streams-analytics': {
        component: UserStreams,
        props: { view: 'analytics' },
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
    'billing-orders': { component: UserBilling, props: { view: 'orders' } },

    messages: { component: UserMessages, props: { view: 'messages' } },
    'message-subscriptions': {
        component: UserMessages,
        props: { view: 'subscriptions' },
    },

    'account-profile': { component: UserAccount, props: { view: 'profile' } },
    'account-certification': {
        component: UserAccount,
        props: { view: 'certification' },
    },
    'account-api-key': { component: UserAccount, props: { view: 'api-key' } },
    'account-login-logs': {
        component: UserAccount,
        props: { view: 'login-logs' },
    },
};

for (const resource of Object.keys(masterResources)) {
    MODULES[`admin-workspace-${resource}`] = {
        component: AdminMasterResources,
        props: { resource },
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

MODULES['admin-streams-analytics'] = {
    component: UserStreams,
    props: { view: 'analytics', scope: 'admin' },
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
