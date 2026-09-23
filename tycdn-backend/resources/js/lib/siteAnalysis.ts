export type TopTab = {
    key: string;
    label: string;
    cols: {
        key: string;
        label: string;
        type: 'text' | 'bytes' | 'count' | 'action';
    }[];
};

export const topTabs: TopTab[] = [
    ['top-domain', '域名排行', 'domain', '域名'],
    ['top-url', '热门URL', 'url', 'URL'],
    ['top-tls-fp', 'TLS指纹', 'fp', 'TLS指纹'],
    ['top-ip', 'Top客户端IP', 'ip', '客户端IP'],
    ['top-country', '国家排行', 'country', '国家'],
    ['top-province', '省份排行', 'province', '省份'],
    ['top-isp', '运营商排行', 'isp', '运营商'],
    ['top-referer', '热门Referer', 'referer', '来源'],
].map(([key, label, dimension, title]) => ({
    key,
    label,
    cols: [
        { key: dimension, label: title, type: 'text' },
        { key: 'req', label: '请求次数', type: 'count' },
        { key: 'traffic', label: '出站流量', type: 'bytes' },
        { key: 'backend_traffic', label: '回源流量', type: 'bytes' },
        { key: '_action', label: '操作', type: 'action' },
    ],
}));

/** Match the master's decimal traffic units while distinguishing zero from unavailable data. */
export function formatRankingMetric(
    value: unknown,
    kind: 'bytes' | 'count',
): string {
    if (
        (typeof value !== 'number' && typeof value !== 'string') ||
        String(value).trim() === ''
    ) {
        return '-';
    }

    const number = Number(value);

    if (!Number.isFinite(number)) {
        return '-';
    }

    if (kind === 'count') {
        return String(number);
    }

    const units = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const power =
        number > 0
            ? Math.min(4, Math.max(0, Math.floor(Math.log10(number) / 3)))
            : 0;

    return `${power ? (number / 1000 ** power).toFixed(2) : number} ${units[power]}`;
}

/** Build an internal log link; resource strings are data, never navigation targets. */
export function rankingLogUrl(
    scope: 'admin' | 'user',
    type: string,
    resource: string,
    context: Record<string, string> = {},
): string {
    const params = new URLSearchParams();

    for (const key of ['start', 'end', 'domain', 'server_port']) {
        if (context[key]) {
            params.set(key, context[key]);
        }
    }

    const filters: Record<string, string> = {
        'top-domain': 'host',
        'top-url': 'req_uri',
        'top-tls-fp': 'tls_fp',
        'top-ip': 'addr',
        'top-country': 'country',
        'top-province': 'province',
        'top-isp': 'isp',
        'top-referer': 'referer',
    };
    const filter = filters[type];

    if (filter) {
        params.set('filter', filter);
    }

    if (type === 'top-domain' || type === 'top-url') {
        // Keep the original URI intact (nested paths, escaped bytes and query strings).
        const match = resource.match(
            /^(?:[a-z][a-z\d+.-]*:\/\/)?(\[[^\]]+\]|[^/:?#]+)(?::(\d+))?([^#]*)/i,
        );

        if (match && !resource.startsWith('/')) {
            params.set('domain', match[1]);

            if (match[2]) {
                params.set('server_port', match[2]);
            } else if (type === 'top-url' && /^https?:\/\//i.test(resource)) {
                params.set(
                    'server_port',
                    /^https:/i.test(resource) ? '443' : '80',
                );
            }

            if (type === 'top-url') {
                const uri = match[3] || '/';
                params.set('req_uri', uri.startsWith('?') ? `/${uri}` : uri);
            }
        } else if (type === 'top-url') {
            params.set('req_uri', resource);
        } else {
            params.set('domain', resource);
        }

        if (type === 'top-url') {
            params.set('uri_match_type', 'exact');
        }
    } else if (filter) {
        params.set(filter, resource);
    }

    return `/console/${scope === 'admin' ? 'admin/' : ''}analytics/logs?${params}`;
}
