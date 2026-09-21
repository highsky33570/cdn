export type MonitorSeries = { name: string; points: [number, number][] };
/** Node charts mix point arrays, named series, and interface-keyed groups. */
export function monitorSeries(value: unknown, name = '指标'): MonitorSeries[] {
    if (Array.isArray(value)) {
        if (
            value.some(
                (row) =>
                    row &&
                    typeof row === 'object' &&
                    'create_at' in row &&
                    ('bytes_sent' in row || 'bytes_received' in row),
            )
        ) {
            return [
                ['bytes_sent', '出站流量（B）'],
                ['bytes_received', '入站流量（B）'],
            ].flatMap(([key, label]) => {
                const points: [number, number][] = value.flatMap((row) => {
                    if (
                        !row ||
                        typeof row !== 'object' ||
                        row[key] === null ||
                        row[key] === undefined
                    ) {
                        return [];
                    }

                    const timestamp =
                        Number(row.create_at) ||
                        Date.parse(String(row.create_at));
                    const amount = Number(row[key]);

                    return Number.isFinite(timestamp) && Number.isFinite(amount)
                        ? [[timestamp, amount] as [number, number]]
                        : [];
                });

                return points.length ? [{ name: label, points }] : [];
            });
        }

        if (
            value.length &&
            value.every(
                (p) =>
                    Array.isArray(p) &&
                    p.length === 2 &&
                    p.every((n) => typeof n === 'number' && Number.isFinite(n)),
            )
        ) {
            return [{ name, points: value as [number, number][] }];
        }

        return value.flatMap((v, i) => monitorSeries(v, `${name} ${i + 1}`));
    }

    if (!value || typeof value !== 'object') {
        return [];
    }

    const record = value as Record<string, unknown>;

    if ('data' in record) {
        return monitorSeries(
            record.data,
            typeof record.name === 'string' ? record.name : name,
        );
    }

    return Object.entries(record).flatMap(([key, v]) => monitorSeries(v, key));
}
