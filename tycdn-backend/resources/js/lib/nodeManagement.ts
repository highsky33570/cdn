import type { CdnflyRecord } from './sharedTypes';

export type NodeListFilters = {
    search: string;
    region: string;
    status: string;
    type: string;
};

export function nodeListQuery(
    filters: NodeListFilters,
    page: number,
    limit: number,
): Record<string, string | number> {
    const query: Record<string, string | number> = { 'sub-ip': 1, page, limit };

    if (filters.search.trim()) {
        query.search = filters.search.trim();
    }

    if (filters.region !== 'all') {
        query.region_id = filters.region;
    }

    if (filters.status !== 'all') {
        query.enable = filters.status;
    }

    if (filters.type !== 'all') {
        query.type = filters.type;
    }

    return query;
}

/** The upstream count counts nodes, while data also includes their secondary IPs. */
export function nodeTree(
    rows: CdnflyRecord[],
): { node: CdnflyRecord; children: CdnflyRecord[] }[] {
    return rows
        .filter((row) => !Number(row.pid))
        .map((node) => ({
            node,
            children: rows.filter((row) => Number(row.pid) === Number(node.id)),
        }));
}

export function nodeBandwidth(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '未知';
    }

    const n = Number(value);

    if (!Number.isFinite(n) || n <= 0) {
        return '未知';
    }

    if (n >= 900_000_000) {
        return `${(n / 1e9).toFixed(2)} Gbps`;
    }

    if (n >= 900_000) {
        return `${(n / 1e6).toFixed(2)} Mbps`;
    }

    return `${(n / 1e3).toFixed(2)} Kbps`;
}

export function nodeStatus(node: CdnflyRecord): {
    label: string;
    tone: 'success' | 'warning' | 'danger';
} {
    if (Number(node.enable) !== 1) {
        const reasons: Record<string, string> = {
            ip_down: 'IP不可用',
            bandwidth: '带宽超限',
            sync_error: '同步错误',
            traffic: '流量超限',
            disable_time: '时间段',
        };
        const reason = reasons[String(node.disable_by)];

        return { label: reason ? `禁用（${reason}）` : '禁用', tone: 'danger' };
    }

    if (Number(node.pid) > 0 || node.state === 'done') {
        return { label: '正常', tone: 'success' };
    }

    const states: Record<string, string> = {
        pending: '待同步',
        process: '同步中',
        failed: '同步失败',
    };

    return { label: states[String(node.state)] ?? '状态未知', tone: 'warning' };
}
