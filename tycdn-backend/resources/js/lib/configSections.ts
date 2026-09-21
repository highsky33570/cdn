export const settingsSections = [
    { key: 'system', label: '系统配置' },
    { key: 'account', label: '账号与安全' },
    { key: 'payment', label: '认证与支付' },
    { key: 'mail', label: '发信配置' },
    { key: 'notifications', label: '通知设置' },
    { key: 'cleanup', label: '数据清理' },
    { key: 'firewall', label: '防火墙配置' },
    { key: 'nginx', label: 'Nginx 配置' },
    { key: 'resources', label: '资源配置' },
    { key: 'defaults', label: '网站默认设置' },
    { key: 'stream-defaults', label: '转发默认设置' },
    { key: 'errors', label: '错误页面' },
    { key: 'node-monitor', label: '节点监控配置' },
];
export function configSection(row: Record<string, unknown>): string {
    const name = String(row.name ?? '');
    const type = String(row.type ?? '');

    if (/node_monitor/.test(name)) {
        return 'node-monitor';
    }

    if (/nginx/.test(name) || type === 'nginx') {
        return 'nginx';
    }

    if (/page_|error_page/.test(name)) {
        return 'errors';
    }

    if (type === 'waf' || /waf|cc_config|firewall/.test(name)) {
        return 'firewall';
    }

    if (['stream_default_config', 'stream'].includes(type)) {
        return 'stream-defaults';
    }

    if (['site_default_config', 'site'].includes(type)) {
        return 'defaults';
    }

    if (/smtp|sms_config|mail_/.test(name)) {
        return 'mail';
    }

    if (/notify|notification|_templ/.test(name)) {
        return 'notifications';
    }

    if (/clean|retention|backup|log_keep/.test(name)) {
        return 'cleanup';
    }

    if (/pay|recharge|certify/.test(name)) {
        return 'payment';
    }

    if (/login|register|captcha|auth|session/.test(name)) {
        return 'account';
    }

    if (/limit|resource/.test(name)) {
        return 'resources';
    }

    return 'system';
}
export const configTitles: Record<string, string> = {
    system_info: '系统信息',
    'nginx-config-file': 'Nginx 配置',
    node_monitor_config: '节点监控与通知',
    register_info: '注册要求',
    smtp_config: '邮件发送',
    sms_config: '短信发送',
    package_expire_close_site: '套餐到期关闭网站',
    traffic_excceed_close_site: '流量超限关闭网站',
    package_allow_upgrade: '允许自主升级套餐',
    package_allow_downgrade: '允许自主降级套餐',
    package_purchase_notice: '购买前说明',
    maintain: '维护状态',
    auto_upgrade_agent: '自动升级节点',
    https_cert: '后台 HTTPS 证书',
    https_key: '后台 HTTPS 私钥',
    tcp_coefficient: 'TCP 流量系数',
    'bind-master-host': '后台绑定域名',
    login_session_expire: '登录有效期',
    login_captcha: '登录验证码',
    dns: 'DNS 配置',
};
