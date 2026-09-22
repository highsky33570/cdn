import { formatDate, textValue } from './formatters';
import type { CdnflyRecord } from './sharedTypes';

export type BlockLogTab = 'current' | 'stats' | 'history';
export type BlockLogFilters = {
    ip: string;
    site_id: string;
    filter_name: string;
    start: string;
    end: string;
};

export function blockLogQuery(
    tab: BlockLogTab,
    filters: BlockLogFilters,
): Record<string, string | number> {
    if (tab === 'stats') {
        return {};
    }

    const query: Record<string, string | number> = {};

    for (const key of ['ip', 'site_id'] as const) {
        if (filters[key].trim()) {
            query[key] = filters[key].trim();
        }
    }

    if (tab === 'current' && filters.filter_name.trim()) {
        query.filter_name = filters.filter_name.trim();
    }

    if (tab === 'history' && (filters.start || filters.end)) {
        const start = Date.parse(filters.start.replace(' ', 'T'));
        const end = Date.parse(filters.end.replace(' ', 'T'));

        if (!Number.isFinite(start) || !Number.isFinite(end) || end < start) {
            throw new Error(
                '请选择有效的开始和结束时间，结束时间不能早于开始时间',
            );
        }

        query.start = Math.floor(start / 1000);
        query.end = Math.floor(end / 1000);
    }

    return query;
}

function ruleLabel(value: unknown): string {
    const id = textValue(value);
    const custom = id.match(/^extra_f_(\d+)$/);

    if (custom) {
        return `自定义规则第${custom[1]}条`;
    }

    const names: Record<string, string> = {
        block_region: '区域屏蔽',
        block_proxy: '代理屏蔽',
        block_page_num_limit: '转iptables拉黑',
        waf_auto_block: 'WAF 攻击自动封禁',
    };

    return names[id] ?? id;
}

/** Current fname is a rule ID; history fname is the resolved display name. */
export function blockFilterLabel(row: CdnflyRecord, history = false): string {
    if (history) {
        return textValue(row.fname) || ruleLabel(row.filter) || '-';
    }

    const id = textValue(row.fname);
    const name = textValue(row.name) || ruleLabel(id);

    return id ? `${name} (ID: ${id})` : name || '-';
}

export function blockTimestamp(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const seconds = Number(value);

    if (!Number.isFinite(seconds)) {
        return '-';
    }

    const date = new Date(seconds * 1000);

    return Number.isNaN(date.getTime()) ? '-' : formatDate(date.toISOString());
}

export function manualUnlockLabel(value: unknown): string {
    if (value === 0 || value === '0' || value === false) {
        return '是';
    }

    if (value === 1 || value === '1' || value === true) {
        return '否';
    }

    return '-';
}

export function blockRowKey(row: CdnflyRecord): string {
    return JSON.stringify([row.site_id, row.ip, row.fname, row.create_at]);
}
