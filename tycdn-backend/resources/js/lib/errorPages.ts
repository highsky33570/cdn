import type { CdnflyRecord } from './sharedTypes';
export const errorPages = [
    { key: 'p456', label: '拉黑页面', regional: false },
    { key: 'p400', label: '400错误', regional: true },
    { key: 'p403', label: '403错误', regional: true },
    { key: 'waf_block', label: 'WAF拦截页面', regional: false },
    { key: 'p502', label: '502错误', regional: true },
    { key: 'p504', label: '504错误', regional: true },
    { key: 'p513', label: '流量超限', regional: true },
    { key: 'p512', label: '套餐到期', regional: true },
    { key: 'p514', label: '站点锁定', regional: true },
    { key: 'host_not_found', label: '域名未配置', regional: true },
    { key: 'access_ip_not_allow', label: '访问节点IP', regional: true },
    { key: 'p515', label: '连接数超限', regional: true },
];
export function parseErrorPages(value: unknown): CdnflyRecord {
    const data = typeof value === 'string' ? JSON.parse(value) : value;

    if (!data || typeof data !== 'object' || Array.isArray(data)) {
        throw new Error('页面配置格式无法识别，请检查主控配置');
    }

    for (const field of errorPages) {
        if (
            data[field.key] !== undefined &&
            typeof data[field.key] !== 'string'
        ) {
            throw new Error('页面内容格式错误');
        }
    }

    return data;
}
export const errorPageLabel = (key: string) =>
    errorPages.find((field) => field.key === key)?.label ?? key;
