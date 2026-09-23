export type MonitorConfig = Record<string, unknown> & {
    check_node_group: string[];
    notify_method: string[];
    notify_msg_type: string[];
};
export const monitorGroups = [
    { value: '1', label: '国内组' },
    { value: '3', label: '电信组' },
    { value: '4', label: '联通组' },
    { value: '5', label: '移动组' },
    { value: '2', label: '国外组' },
];
export const monitorEvents = [
    { value: '节点IP解析', label: 'IP可用性监控' },
    { value: '带宽监控', label: '带宽超限' },
    { value: '备用IP', label: '备用IP切换' },
    { value: '备用默认解析', label: '备用默认解析切换' },
    { value: '备用线路组', label: '备用线路组切换' },
];
export const monitorTemplates = [
    {
        label: 'IP可用性通知模板',
        items: [
            { key: 'ip_enable_templ', label: 'IP启用模板' },
            { key: 'ip_disable_templ', label: 'IP禁用模板' },
        ],
    },
    {
        label: '带宽超限通知模板',
        items: [
            { key: 'bandwidth_exceed_templ', label: '带宽超限模板' },
            { key: 'bandwidth_restore_templ', label: '带宽恢复模板' },
        ],
    },
    {
        label: '备用IP切换通知模板',
        items: [
            { key: 'backup_ip_enable_templ', label: '备用IP启用模板' },
            { key: 'backup_ip_disable_templ', label: '备用IP禁用模板' },
        ],
    },
    {
        label: '备用默认解析切换通知模板',
        items: [
            { key: 'backup_default_line_enable_templ', label: '切换的模板' },
            { key: 'backup_default_line_disable_templ', label: '禁用的模板' },
        ],
    },
    {
        label: '备用线路组切换通知模板',
        items: [
            { key: 'backup_node_group_enable_templ', label: '切换的模板' },
            { key: 'backup_node_group_disable_templ', label: '禁用的模板' },
        ],
    },
];
const numericFields = [
    {
        key: 'check_port',
        label: '默认监控端口',
        min: 1,
        max: 65536,
        integer: true,
    },
    {
        key: 'check_timeout',
        label: '默认检查超时',
        min: 0,
        max: 5,
        exclusive: true,
    },
    { key: 'interval', label: '间隔时间', min: 30, max: 300, integer: true },
    {
        key: 'failed_times',
        label: '连续失败次数',
        min: 1,
        max: 10,
        integer: true,
    },
    {
        key: 'failed_rate',
        label: '监控点失败比率',
        min: 1,
        max: 100,
        integer: true,
    },
    {
        key: 'bw_exceed_times',
        label: '连续带宽超限次数',
        min: 1,
        max: Number.MAX_SAFE_INTEGER,
        integer: true,
    },
];
export function decodeMonitorConfig(value: unknown): MonitorConfig {
    const data = typeof value === 'string' ? JSON.parse(value) : value;

    if (!data || typeof data !== 'object' || Array.isArray(data)) {
        throw new Error('节点监控配置格式无效，请重新加载');
    }

    const result = { ...data };

    for (const [key, separator] of [
        ['check_node_group', ','],
        ['notify_method', ' '],
        ['notify_msg_type', ' '],
    ]) {
        const raw = result[key];

        if (typeof raw !== 'string' && !Array.isArray(raw)) {
            throw new Error('节点监控配置不完整，请重新加载');
        }

        result[key] = Array.isArray(raw)
            ? [...raw]
            : raw.split(separator).filter(Boolean);
    }

    for (const { key } of numericFields) {
        if (String(result[key] ?? '').trim() !== '') {
            result[key] = Number(result[key]);
        }
    }

    result.global_check_on =
        result.global_check_on === true ||
        String(result.global_check_on) === '1'
            ? 1
            : 0;

    return result as MonitorConfig;
}
export function encodeMonitorConfig(config: MonitorConfig): string {
    const value: Record<string, unknown> = { ...config };

    for (const field of numericFields) {
        const raw = String(config[field.key] ?? '').trim(),
            n = Number(raw);

        if (
            !raw ||
            !Number.isFinite(n) ||
            (field.integer && !Number.isSafeInteger(n)) ||
            (field.exclusive ? n <= field.min : n < field.min) ||
            n > field.max
        ) {
            throw new Error(
                `${field.label}超出有效范围（${field.exclusive ? '>' : ''}${field.min}–${field.max}）`,
            );
        }

        value[field.key] = n;
    }

    const period = String(value.notification_period ?? '').match(
        /^(\d{1,2})-(\d{1,2})$/,
    );

    if (
        !period ||
        Number(period[1]) >= Number(period[2]) ||
        Number(period[2]) > 24
    ) {
        throw new Error('通知时间段格式为开始小时-结束小时，例如 8-22');
    }

    value.check_node_group = config.check_node_group.join(',');
    value.notify_method = config.notify_method.join(' ');
    value.notify_msg_type = config.notify_msg_type.join(' ');

    return JSON.stringify(value);
}
export function smsTemplate(text: string, provider: string): string {
    if (['smsbao', 'submail'].includes(provider)) {
        return text.replaceAll('{{', '{').replaceAll('}}', '}');
    }

    if (provider === 'aliyun') {
        return text.replaceAll('{{', '${').replaceAll('}}', '}');
    }

    if (provider === 'qcloud') {
        let i = 0;

        return text
            .replace(/【.*】/g, '')
            .replace(/\{\{.*?\}\}/g, () => `{${++i}}`);
    }

    throw new Error('当前短信提供商不支持模板转换');
}
