import type { CdnflyRecord } from './sharedTypes';

export function isCdnflyRecord(value: unknown): value is CdnflyRecord {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

export function extractCdnflyRecord(result: unknown): CdnflyRecord | null {
    if (!isCdnflyRecord(result)) {
        return null;
    }

    if (
        'data' in result &&
        ('code' in result || 'ok' in result || !('id' in result))
    ) {
        return extractCdnflyRecord(result.data);
    }

    return result;
}

export function extractCdnflyRows(result: unknown): CdnflyRecord[] {
    if (Array.isArray(result)) {
        return result.filter(isCdnflyRecord);
    }

    if (!isCdnflyRecord(result)) {
        return [];
    }

    for (const key of ['data', 'items', 'list', 'rows', 'records']) {
        const value = result[key];

        if (Array.isArray(value)) {
            return value.filter(isCdnflyRecord);
        }

        if (isCdnflyRecord(value)) {
            const rows = extractCdnflyRows(value);

            if (rows.length > 0) {
                return rows;
            }
        }
    }

    return [];
}

/** CDNfly v6 uses count; local Laravel pagination uses total. */
export function extractCdnflyTotal(result: unknown, fallback: number): number {
    function findTotal(value: unknown): number | null {
        if (!isCdnflyRecord(value)) {
            return null;
        }

        for (const key of ['total', 'count']) {
            const raw = value[key];

            if (typeof raw !== 'number' && typeof raw !== 'string') {
                continue;
            }

            if (raw === '') {
                continue;
            }

            const count = Number(raw);

            if (Number.isInteger(count) && count >= 0) {
                return count;
            }
        }

        for (const key of ['data', 'meta', 'pagination']) {
            const count = findTotal(value[key]);

            if (count !== null) {
                return count;
            }
        }

        return null;
    }

    return findTotal(result) ?? fallback;
}

export function cdnflyJsonRows(value: unknown): CdnflyRecord[] {
    if (typeof value === 'string') {
        try {
            return extractCdnflyRows(JSON.parse(value));
        } catch {
            return [];
        }
    }

    return extractCdnflyRows(value);
}

export function cdnflyJsonObject(value: unknown): CdnflyRecord {
    if (typeof value === 'string') {
        try {
            value = JSON.parse(value);
        } catch {
            return {};
        }
    }

    return isCdnflyRecord(value) ? value : {};
}

export function cdnflyStreamSeries(result: unknown): {
    outbound: [number, number][];
    inbound: [number, number][];
} {
    const data = isCdnflyRecord(result) ? result.data : result;
    const points = (value: unknown): [number, number][] =>
        Array.isArray(value)
            ? value.filter(
                  (point): point is [number, number] =>
                      Array.isArray(point) &&
                      point.length === 2 &&
                      point.every(
                          (n) => typeof n === 'number' && Number.isFinite(n),
                      ),
              )
            : [];

    return {
        outbound: points(isCdnflyRecord(data) ? data.outbound : data),
        inbound: points(isCdnflyRecord(data) ? data.inbound : []),
    };
}

export function inclusiveUsageEnd(value: string): string {
    const date = new Date(`${value.slice(0, 10)}T00:00:00Z`);

    if (!Number.isFinite(date.getTime())) {
        throw new Error('结束日期无效');
    }

    date.setUTCDate(date.getUTCDate() + 1);

    return date.toISOString().slice(0, 10);
}

export function streamListenText(value: unknown): string {
    return (
        cdnflyJsonRows(value)
            .map(
                (listen) =>
                    `${listen.port ?? ''}${listen.protocol ? `/${listen.protocol}` : ''}`,
            )
            .filter(Boolean)
            .join(', ') || '-'
    );
}

export function streamBackendText(row: CdnflyRecord): string {
    return (
        cdnflyJsonRows(row.backend)
            .map((backend) => {
                const address = String(backend.addr ?? '');

                if (!address) {
                    return '';
                }

                const host =
                    address.includes(':') && !address.startsWith('[')
                        ? `[${address}]`
                        : address;

                return row.backend_port ? `${host}:${row.backend_port}` : host;
            })
            .filter(Boolean)
            .join(', ') || '-'
    );
}

/** Rankings share res/count/traffic/up_recv regardless of their dimension. */
export function siteRankingRows(result: unknown, type: string): CdnflyRecord[] {
    const dimensions: Record<string, string> = {
        'top-domain': 'domain',
        'top-url': 'url',
        'top-tls-fp': 'fp',
        'top-ip': 'ip',
        'top-country': 'country',
        'top-province': 'province',
        'top-isp': 'isp',
        'top-referer': 'referer',
    };
    const dimension = dimensions[type] ?? 'res';
    const rows = rankingRows(result);

    return rows.map((row) => ({
        ...row,
        [dimension]:
            row[dimension] ?? row.res ?? row.key ?? row.name ?? row.label,
        req: row.req ?? row.count ?? row.requests ?? row.value,
        traffic:
            row.traffic ?? row.bytes ?? row.size ?? row.down_send ?? row.send,
        backend_traffic:
            row.backend_traffic ??
            row.up_recv ??
            row.up_traffic ??
            row.origin_traffic,
    }));
}

function rankingRows(result: unknown): CdnflyRecord[] {
    const nativeRows = extractCdnflyRows(result);

    if (nativeRows.length > 0) {
        return nativeRows;
    }

    const payload = rankingPayload(result);

    if (Array.isArray(payload)) {
        return payload.flatMap((item) => rankingArrayRow(item));
    }

    if (!isCdnflyRecord(payload)) {
        return [];
    }

    if (
        ['res', 'domain', 'url', 'ip', 'country', 'province', 'isp'].some(
            (key) => key in payload,
        )
    ) {
        return [payload];
    }

    return Object.entries(payload).flatMap(([resource, value]) => {
        if (isCdnflyRecord(value)) {
            return [{ res: resource, ...value }];
        }

        if (Array.isArray(value)) {
            if (value.every((item) => typeof item !== 'object')) {
                return [rankingTuple(value, resource)];
            }

            return value.flatMap((item) => rankingArrayRow(item, resource));
        }

        const count = Number(value);

        return Number.isFinite(count) ? [{ res: resource, count }] : [];
    });
}

function rankingPayload(result: unknown): unknown {
    let value = result;

    for (let depth = 0; depth < 5; depth++) {
        if (typeof value === 'string') {
            try {
                value = JSON.parse(value);
                continue;
            } catch {
                return value;
            }
        }

        if (!isCdnflyRecord(value)) {
            return value;
        }

        const nested =
            value.data ??
            value.items ??
            value.list ??
            value.rows ??
            value.records;

        if (nested === undefined) {
            return value;
        }

        value = nested;
    }

    return value;
}

function rankingArrayRow(item: unknown, resource?: string): CdnflyRecord[] {
    if (isCdnflyRecord(item)) {
        return [{ ...(resource ? { res: resource } : {}), ...item }];
    }

    if (!Array.isArray(item)) {
        return [];
    }

    return [rankingTuple(item, resource)];
}

function rankingTuple(values: unknown[], resource?: string): CdnflyRecord {
    const hasResource = resource === undefined;

    return {
        res: resource ?? values[0],
        count: values[hasResource ? 1 : 0],
        traffic: values[hasResource ? 2 : 1],
        up_recv: values[hasResource ? 3 : 2],
    };
}
