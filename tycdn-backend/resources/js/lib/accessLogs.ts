import { formatDate, textValue } from './formatters';
import type { CdnflyRecord } from './sharedTypes';

export const accessFilterFields = [
    { key: 'host', label: '域名', placeholder: '输入域名' },
    { key: 'addr', label: '客户端IP', placeholder: '输入客户端IP' },
    { key: 'req_uri', label: '请求地址', placeholder: '不包含域名部分的URI' },
    { key: 'method', label: '请求方法', placeholder: '如 GET、POST' },
    { key: 'status', label: '状态码', placeholder: '输入状态码' },
    { key: 'cache_status', label: '缓存状态', placeholder: '' },
    { key: 'server_port', label: '访问端口', placeholder: '输入访问端口' },
    { key: 'tls_fp', label: 'TLS指纹', placeholder: '输入TLS指纹' },
    { key: 'node_id', label: '节点ID', placeholder: '输入节点ID' },
    { key: 'referer', label: '来源', placeholder: '输入来源' },
    { key: 'country', label: '国家', placeholder: '输入国家' },
    { key: 'province', label: '省份', placeholder: '输入省份' },
    { key: 'isp', label: '运营商', placeholder: '输入运营商' },
] as const;
export type AccessFilterKey = (typeof accessFilterFields)[number]['key'];
export type AccessLogFilters = Record<
    AccessFilterKey | 'start' | 'end' | 'uri_match_type',
    string
>;

export function defaultAccessFilters(now = new Date()): AccessLogFilters {
    const start = new Date(now);
    start.setHours(0, 0, 0, 0);
    const end = new Date(start);
    end.setDate(end.getDate() + 1);

    return {
        ...Object.fromEntries(
            accessFilterFields.map((field) => [field.key, '']),
        ),
        start: formatDate(start.toISOString()).replace(' ', 'T'),
        end: formatDate(end.toISOString()).replace(' ', 'T'),
        uri_match_type: 'exact',
    } as AccessLogFilters;
}

export function accessLogParams(
    filters: AccessLogFilters,
): Record<string, string | number> {
    const start = Date.parse(filters.start.replace(' ', 'T'));
    const end = Date.parse(filters.end.replace(' ', 'T'));

    if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) {
        throw new Error('请选择有效的时间范围，结束时间必须晚于开始时间');
    }

    const date = (value: string) =>
        value.replace('T', ' ').length === 16
            ? `${value.replace('T', ' ')}:00`
            : value.replace('T', ' ');
    const query: Record<string, string | number> = {
        start: date(filters.start),
        end: date(filters.end),
    };

    for (const { key } of accessFilterFields) {
        const value = filters[key].trim();

        if (!value) {
            continue;
        }

        if (key === 'server_port' || key === 'node_id') {
            if (
                !/^\d+$/.test(value) ||
                !Number.isSafeInteger(Number(value)) ||
                (key === 'server_port' &&
                    (Number(value) < 1 || Number(value) > 65535))
            ) {
                throw new Error(
                    key === 'server_port'
                        ? '请输入有效的访问端口'
                        : '请输入有效的节点ID',
                );
            }

            query[key] = Number(value);
        } else {
            query[key] = value;
        }
    }

    if (query.req_uri) {
        query.uri_match_type = filters.uri_match_type;
    }

    return query;
}

export const accessLogColumns = [
    { key: 'timestamp', label: '时间', width: 150 },
    { key: 'host', label: '域名', width: 240 },
    { key: 'server_port', label: '端口', width: 70 },
    { key: 'protocol', label: '协议', width: 100 },
    { key: 'method', label: '方法', width: 100 },
    { key: 'req_uri', label: 'URI', width: 200 },
    { key: 'status', label: '状态码', width: 80 },
    { key: 'addr', label: '客户IP', width: 150 },
    { key: 'tls_fp', label: 'TLS指纹', width: 180 },
    { key: 'country', label: '地理位置', width: 180 },
    { key: 'isp', label: '运营商', width: 130 },
    { key: 'sip', label: '源地址', width: 150 },
    { key: 'content_type', label: '内容类型', width: 200 },
    { key: 'referer', label: '来源', width: 160 },
    { key: 'user_agent', label: '浏览器', width: 240 },
    { key: 'up_resp_time', label: '回源耗时', width: 100 },
    { key: 'bytes_sent', label: '返回字节', width: 100 },
    { key: 'cache_status', label: '缓存命中', width: 100 },
    { key: 'l1_cache_status', label: 'L1缓存命中', width: 120 },
    { key: 'l2_cache_status', label: 'L2缓存命中', width: 120 },
    { key: 'l2_ip', label: 'L2 IP', width: 150 },
    { key: 'nid', label: '节点ID', width: 100 },
] as const;

export function accessLogCell(row: CdnflyRecord, key: string): string {
    if (key === 'timestamp') {
        if (
            row.timestamp === null ||
            row.timestamp === undefined ||
            row.timestamp === ''
        ) {
            return '-';
        }

        const date = new Date(Number(row.timestamp));

        return Number.isNaN(date.getTime())
            ? '-'
            : formatDate(date.toISOString()).slice(5);
    }

    if (key === 'host') {
        const host = textValue(row.host),
            actual = textValue(row.host2);

        return actual && host !== actual && !host.includes('no-config')
            ? `${host} (${actual})`
            : host;
    }

    if (key === 'country') {
        return row.country === '-'
            ? ''
            : [row.country, row.province, row.city]
                  .map(textValue)
                  .filter(Boolean)
                  .join('-');
    }

    return textValue(row[key]);
}

export function accessJobData(row: CdnflyRecord): CdnflyRecord {
    try {
        const data =
            typeof row.data === 'string' ? JSON.parse(row.data) : row.data;

        return data && typeof data === 'object' && !Array.isArray(data)
            ? data
            : {};
    } catch {
        return {};
    }
}

export function accessJobState(state: unknown): string {
    const labels: Record<string, string> = {
        pending: '待处理',
        process: '处理中',
        failed: '出错',
        done: '成功',
    };

    return labels[textValue(state)] ?? (textValue(state) || '-');
}

export function decodeAccessBody(value: string): string {
    if (!value) {
        return '请求体为空或未开启记录请求体';
    }

    try {
        return new TextDecoder().decode(
            Uint8Array.from(atob(value), (character) =>
                character.charCodeAt(0),
            ),
        );
    } catch {
        return value;
    }
}

export function accessHeaderText(value: unknown): string {
    const text = textValue(value);

    if (!text || text === '-') {
        return '未开启记录';
    }

    try {
        const headers = JSON.parse(text);

        if (headers && typeof headers === 'object' && !Array.isArray(headers)) {
            return Object.entries(headers)
                .map(
                    ([key, item]) =>
                        `${key}: ${Array.isArray(item) ? item.join(', ') : String(item)}`,
                )
                .join('\n');
        }
    } catch {
        /* Raw HTTP headers already contain line breaks. */
    }

    return text;
}
