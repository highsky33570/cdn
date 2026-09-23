import type { CdnflyRecord } from './sharedTypes';

export const cacheModes = [
    {
        value: 'clean_url',
        label: '刷新 URL',
        title: 'URL 列表',
        placeholder: 'https://www.example.com/static/app.js',
        helper: '每行一个完整 URL，适合清理已缓存资源。',
    },
    {
        value: 'clean_dir',
        label: '刷新目录',
        title: '目录 URL',
        placeholder: 'https://www.example.com/static/',
        helper: '每行一个目录 URL，建议目录地址以 / 结尾。',
    },
    {
        value: 'pre_cache_url',
        label: '预热',
        title: '预热 URL',
        placeholder: 'https://www.example.com/index.html',
        helper: '每行一个完整 URL，提交后提前拉取资源。',
    },
] as const;
export type CacheMode = (typeof cacheModes)[number]['value'];
export type CacheJobPayload = { type: CacheMode; data: { url: string } };
export function cacheUrls(value: string): string[] {
    return value
        .split(/\r?\n/)
        .map((url) => url.trim())
        .filter(Boolean);
}
export function validCacheUrl(value: string): boolean {
    if (!/^https?:\/\/\S+$/i.test(value)) {
        return false;
    }

    try {
        return !!new URL(value).hostname;
    } catch {
        return false;
    }
}
export function cacheJobUrl(row: CdnflyRecord): string {
    let data = row.data;

    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch {
            data = null;
        }
    }

    if (
        data &&
        typeof data === 'object' &&
        !Array.isArray(data) &&
        typeof (data as CdnflyRecord).url === 'string'
    ) {
        return String((data as CdnflyRecord).url);
    }

    return typeof row.key2 === 'string' ? row.key2 : '';
}
export function cacheJobPayload(row: CdnflyRecord): CacheJobPayload {
    const url = cacheJobUrl(row);

    if (
        !cacheModes.some((mode) => mode.value === row.type) ||
        !validCacheUrl(url)
    ) {
        throw new Error(
            `任务 ${row.id ?? ''} 缺少有效的缓存类型或 URL，无法重新提交`,
        );
    }

    return { type: row.type as CacheMode, data: { url } };
}
export function cacheJobStatus(row: CdnflyRecord): {
    group: 'done' | 'failed' | 'process';
    label: string;
    tone: string;
} {
    if (String(row.enable) === '0') {
        return { group: 'failed', label: '已取消', tone: 'danger' };
    }

    if (row.state === 'failed') {
        return { group: 'failed', label: '失败', tone: 'danger' };
    }

    if (row.state === 'pending') {
        return { group: 'process', label: '待执行', tone: 'warning' };
    }

    if (row.state === 'process') {
        return {
            group: 'process',
            label: String(row.progress || '执行中'),
            tone: 'info',
        };
    }

    if (row.state === 'done') {
        return { group: 'done', label: '完成', tone: 'success' };
    }

    return {
        group: 'process',
        label: String(row.state || '状态未知'),
        tone: 'muted',
    };
}
export function cacheJobQuery(
    type: string,
    keyword: string,
    page: number,
    limit: number,
) {
    const value = keyword.trim();

    return {
        page,
        limit,
        type: type || cacheModes.map((mode) => mode.value).join(','),
        key1: /^https?:\/\//i.test(value) ? '' : value,
        key2: /^https?:\/\//i.test(value) ? value : '',
    };
}
export function cacheJobSummary(rows: CdnflyRecord[]) {
    return rows.reduce<Record<'all' | 'done' | 'failed' | 'process', number>>(
        (result, row) => {
            result[cacheJobStatus(row).group]++;

            return result;
        },
        { all: rows.length, done: 0, failed: 0, process: 0 },
    );
}
