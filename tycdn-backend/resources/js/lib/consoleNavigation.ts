import {
    Activity,
    ChartBar,
    CreditCard,
    Globe2,
    LayoutGrid,
    Network,
    Package,
    Server,
    Settings,
} from 'lucide-vue-next';
import type { NavItem } from '@/types';
export const mainNavItems: NavItem[] = [
    {
        title: '服务概览',
        href: '/console',
        icon: LayoutGrid,
    },
    {
        title: '统计分析',
        href: '/console/analytics/realtime',
        icon: ChartBar,
        children: [
            { title: '实时监控', href: '/console/analytics/realtime' },
            { title: '数据分析', href: '/console/analytics/top' },
            { title: '拉黑日志', href: '/console/security/blackip' },
            { title: '访问日志', href: '/console/analytics/logs' },
            { title: '四层实时监控', href: '/console/streams/analytics' },
        ],
    },
    {
        title: '网站管理',
        href: '/console/sites',
        icon: Globe2,
        children: [
            { title: '网站列表', href: '/console/sites' },
            { title: '证书管理', href: '/console/certificates' },
            { title: '刷新预热', href: '/console/cache/jobs' },
            { title: 'CC规则', href: '/console/security/cc' },
            { title: 'ACL规则', href: '/console/security/acls' },
        ],
    },
    {
        title: '四层转发',
        href: '/console/streams',
        icon: Network,
        children: [{ title: '转发列表', href: '/console/streams' }],
    },
    {
        title: '套餐管理',
        href: '/console/billing/subscriptions',
        icon: CreditCard,
        children: [
            { title: '我的套餐', href: '/console/billing/subscriptions' },
            { title: '套餐购买', href: '/console/billing/packages' },
            { title: '流量包', href: '/console/billing/traffic-packs' },
            { title: '用量查询', href: '/console/billing/usage' },
        ],
    },
    {
        title: '账户中心',
        href: '/console/account/profile',
        icon: Settings,
        children: [
            { title: '个人资料', href: '/console/account/profile' },
            { title: '消费记录', href: '/console/billing/orders' },
            { title: '日志查询', href: '/console/account/login-logs' },
            { title: '消息查询', href: '/console/messages' },
            { title: '消息订阅', href: '/console/messages/subscriptions' },
            { title: 'API密钥', href: '/console/account/api-key' },
        ],
    },
];

const child = (title: string, href: string): NavItem => ({
    title,
    href: '/console/admin/' + href,
});
export const adminNavItems: NavItem[] = [
    { title: '服务概览', href: '/console/admin', icon: Activity },
    {
        title: '统计分析',
        href: '/console/admin/analytics/realtime',
        icon: ChartBar,
        children: [
            child('实时监控', 'analytics/realtime'),
            child('数据分析', 'analytics/top'),
            child('拉黑日志', 'workspace/history-blackip'),
            child('访问日志', 'analytics/logs'),
            child('WAF日志', 'workspace/attack-log'),
            child('四层实时监控', 'streams/analytics'),
        ],
    },
    {
        title: '节点管理',
        href: '/console/admin/nodes',
        icon: Server,
        children: [
            child('节点列表', 'nodes'),
            child('线路分组', 'line-groups'),
            child('L2配置', 'workspace/l2-configs'),
            child('DNS配置', 'dns'),
            child('监控配置', 'config/node-monitor'),
            child('实时监控', 'node-monitoring'),
        ],
    },
    {
        title: '网站管理',
        href: '/console/admin/sites',
        icon: Globe2,
        children: [
            child('网站列表', 'sites'),
            child('证书管理', 'certificates'),
            child('刷新预热', 'cache/jobs'),
            child('CC规则', 'security/cc'),
            child('WAF规则', 'security/waf'),
        ],
    },
    {
        title: '四层转发',
        href: '/console/admin/streams',
        icon: Network,
        children: [child('转发列表', 'streams')],
    },
    {
        title: '全局配置',
        href: '/console/admin/config/firewall',
        icon: Settings,
        children: [
            child('防火墙配置', 'config/firewall'),
            child('Nginx配置', 'config/nginx'),
            child('资源配置', 'config/resources'),
            child('默认配置', 'config/defaults'),
            child('错误页面', 'config/errors'),
        ],
    },
    {
        title: '套餐管理',
        href: '/console/admin/packages',
        icon: Package,
        children: [
            child('基础套餐', 'packages'),
            child('已售套餐', 'sold-packages'),
            child('升级包', 'package-upgrades'),
            child('流量包', 'workspace/traffic-packages'),
            child('营销配置', 'workspace/discounts'),
            child('套餐监控', 'workspace/package-monitor'),
            child('用量查询', 'analytics/usage'),
        ],
    },
    {
        title: '财务管理',
        href: '/console/admin/finance/recharge',
        icon: CreditCard,
        children: [
            child('用户充值', 'finance/recharge'),
            child('所有订单', 'finance/orders'),
            child('充值统计', 'finance/recharge-count'),
        ],
    },
    {
        title: '系统管理',
        href: '/console/admin/settings',
        icon: Settings,
        children: [
            child('系统配置', 'settings'),
            child('后台任务', 'workspace/tasks'),
            child('用户列表', 'users'),
            child('系统日志', 'monitoring'),
            child('维护升级', 'maintenance'),
            child('公告管理', 'workspace/messages'),
            child('消息查询', 'message-query'),
        ],
    },
];
// Console-only tools remain accessible without changing the master's menu order.
export const adminUtilityNavItems: NavItem[] = [
    child('本地财务', 'finance'),
    child('套餐分组', 'package-groups'),
    child('四层默认设置', 'config/stream-defaults'),
    child('节点 IP 日志', 'workspace/node-ip-log'),
    child('安全与权限', 'security'),
];
export function consoleNavigationTitle(path: string): string | undefined {
    for (const group of [
        ...adminNavItems,
        ...mainNavItems,
        ...adminUtilityNavItems,
    ]) {
        const child = group.children?.find((item) => item.href === path);

        if (child) {
            return child.title;
        }

        if (group.href === path) {
            return group.title;
        }
    }

    if (/^\/console\/(?:admin\/)?sites\/\d+$/.test(path)) {
        return '网站配置';
    }

    return undefined;
}
