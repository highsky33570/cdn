import { apiRequest } from './apiRequest';
import { cdnflyJsonRows } from './cdnflyResponse';
import type { CdnflyRecord } from './sharedTypes';

export type SiteWorkspaceTab =
    | 'sites'
    | 'groups'
    | 'defaults'
    | 'dnsapi'
    | 'resolve';
export const siteWorkspaceTabs: { key: SiteWorkspaceTab; label: string }[] = [
    { key: 'sites', label: '网站列表' },
    { key: 'groups', label: '分组管理' },
    { key: 'defaults', label: '默认设置' },
    { key: 'dnsapi', label: 'DNS API' },
    { key: 'resolve', label: '解析检测' },
];
export function siteResource(
    resource: string,
    method = 'GET',
    data: unknown = {},
    id?: number,
) {
    const url = `/api/admin/site-resources/${resource}${id === undefined ? '' : `/${id}`}`;

    return apiRequest<CdnflyRecord>(
        method === 'GET'
            ? `${url}?${new URLSearchParams(data as Record<string, string>)}`
            : url,
        {
            method,
            ...(method === 'GET' ? {} : { body: JSON.stringify(data) }),
        },
    );
}
export function siteObject(value: unknown): CdnflyRecord {
    try {
        const parsed = typeof value === 'string' ? JSON.parse(value) : value;

        return parsed && typeof parsed === 'object' && !Array.isArray(parsed)
            ? parsed
            : {};
    } catch {
        return {};
    }
}
export function siteCname(row: CdnflyRecord): string {
    if (row.cname) {
        return String(row.cname);
    }

    const prefix = row.cname_mode && row.cname_mode !== 'site' ? 'up_' : '';

    return [row[`${prefix}cname_hostname`], row[`${prefix}cname_domain`]]
        .filter(Boolean)
        .join('.');
}
export function siteOrigins(row: CdnflyRecord): string {
    return (
        cdnflyJsonRows(row.backend)
            .map((item) => item.addr)
            .filter(Boolean)
            .join(', ') || '—'
    );
}
export function sitePorts(row: CdnflyRecord): string[] {
    return [
        String(siteObject(row.http_listen).port ?? ''),
        String(siteObject(row.https_listen).port ?? '')
            .split(/\s+/)
            .filter(Boolean)
            .map((p) => `${p}s`)
            .join(' '),
    ]
        .join(' ')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
}
export function siteStatus(row: CdnflyRecord): { text: string; tone: string } {
    if (String(row.enable) === '0') {
        return { text: '已禁用', tone: 'muted' };
    }

    if (['error', 'failed'].includes(String(row.sync_state))) {
        return { text: '同步失败', tone: 'danger' };
    }

    if (['pending', 'process'].includes(String(row.sync_state))) {
        return { text: '同步中', tone: 'warning' };
    }

    const state = String(row.site_state ?? row.state ?? '');

    if (state && state !== '200') {
        return { text: `异常 (${state})`, tone: 'danger' };
    }

    return { text: '正常', tone: 'success' };
}
export function csvCell(value: unknown): string {
    let text = String(value ?? '');

    if (/^[=+\-@\t\r]/.test(text)) {
        text = `'${text}`;
    }

    return `"${text.replaceAll('"', '""')}"`;
}
