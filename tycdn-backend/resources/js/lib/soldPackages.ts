import type { CdnflyRecord } from './sharedTypes';
export const soldLimits = [
    { key: 'traffic', label: '月流量', unit: 'GB' },
    { key: 'bandwidth', label: '带宽', unit: '' },
    { key: 'connection', label: '连接数', unit: '' },
    { key: 'stream_port', label: '四层端口数', unit: '' },
    { key: 'domain', label: '域名数', unit: '' },
    { key: 'main_domain', label: '主域名数', unit: '' },
    { key: 'http_port', label: '网站非标端口数', unit: '' },
];
export const soldCapabilities = [
    { key: 'custom_cc_rule', label: '自定义CC规则' },
    { key: 'websocket', label: 'WebSocket' },
    { key: 'http3', label: 'HTTP3' },
    { key: 'waf_protect', label: 'WAF防护' },
];
export function recordData(value: unknown): CdnflyRecord {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        throw new Error('套餐数据格式错误');
    }

    const row = value as CdnflyRecord;

    if ('data' in row) {
        return recordData(row.data);
    }

    return row;
}
export function resources(
    detail: CdnflyRecord,
    usage: CdnflyRecord,
    upgrades: CdnflyRecord[],
) {
    return [
        { key: 'traffic', label: '流量 (GB)' },
        { key: 'domain', label: '域名数' },
        { key: 'main_domain', label: '主域名数' },
        { key: 'http_port', label: 'HTTP端口数' },
        { key: 'stream_port', label: '转发端口数' },
    ].map((field) => {
        const raw = detail[field.key],
            usedRaw = usage[`${field.key}_usage`];
        const base =
            raw === undefined || raw === null || raw === ''
                ? null
                : Number(raw);
        const used =
            usedRaw === undefined ||
            usedRaw === null ||
            usedRaw === '' ||
            !Number.isFinite(Number(usedRaw))
                ? null
                : Number(usedRaw);
        const unlimited = base === -1;
        const extra = upgrades
            .filter((row) => row.type === field.key)
            .reduce(
                (n, row) =>
                    n +
                    Number(row.package_up_amount ?? 0) *
                        Number(row.amount ?? 0),
                0,
            );
        const total =
            base === null || !Number.isFinite(base) || !Number.isFinite(extra)
                ? null
                : unlimited
                  ? -1
                  : base + extra;
        const remaining =
            total === null || unlimited || used === null
                ? null
                : Math.max(0, total - used);
        const percent =
            total === null || unlimited || used === null
                ? 0
                : total > 0
                  ? Math.min(100, Math.max(0, (used / total) * 100))
                  : used > 0
                    ? 100
                    : 0;

        return {
            ...field,
            total,
            used: Number.isFinite(used) ? used : null,
            remaining,
            percent,
            unlimited,
            status:
                total === null || used === null
                    ? '暂无数据'
                    : unlimited || used <= total
                      ? '正常'
                      : '超额',
        };
    });
}
