export type MetricSeries = {
    label: string;
    color: string;
    points: [number, number][];
};

function numericValue(value: unknown): number | null {
    if (
        (typeof value !== 'number' && typeof value !== 'string') ||
        (typeof value === 'string' && value.trim() === '')
    ) {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

function timestampValue(value: unknown): number | null {
    const numeric = numericValue(value);

    if (numeric !== null) {
        return numeric < 10_000_000_000 ? numeric * 1000 : numeric;
    }

    const parsed = typeof value === 'string' ? Date.parse(value) : NaN;

    return Number.isFinite(parsed) ? parsed : null;
}

function pointRows(value: unknown): [number, number][] {
    if (!Array.isArray(value)) {
        return [];
    }

    const points: [number, number][] = [];

    for (const item of value) {
        if (!Array.isArray(item) || item.length < 2) {
            continue;
        }

        const timestamp = timestampValue(item[0]);
        const number = numericValue(item[1]);

        if (timestamp !== null && number !== null) {
            points.push([timestamp, number]);
        }
    }

    return points;
}

/** CDNfly returns plain tuples for rates and { name, value: tuples } for status codes. */
export function extractMetricSeries(
    result: unknown,
    metric: { label: string; color: string },
): MetricSeries[] {
    const raw = (result as { data?: unknown } | null)?.data;
    const colors = [metric.color, '#7ac36a', '#f5a623', '#ef5b5b', '#8b5cf6'];
    const groups = new Map<string, [number, number][]>();
    const add = (label: string, points: [number, number][]) => {
        if (points.length) {
            groups.set(label, [...(groups.get(label) ?? []), ...points]);
        }
    };

    if (Array.isArray(raw)) {
        add(metric.label, pointRows(raw));

        for (const item of raw) {
            if (!item || typeof item !== 'object' || Array.isArray(item)) {
                continue;
            }

            const row = item as Record<string, unknown>;
            const name = row.status ?? row.code ?? row.name ?? row.series;
            const label =
                typeof name === 'string' || typeof name === 'number'
                    ? String(name)
                    : '';

            if (Array.isArray(row.value) || Array.isArray(row.data)) {
                add(label || metric.label, pointRows(row.value ?? row.data));
                continue;
            }

            const timestamp = timestampValue(
                row.time ?? row.timestamp ?? row.ts ?? row.date,
            );

            if (timestamp === null) {
                continue;
            }

            const value = numericValue(row.value ?? row.count ?? row.rate);

            if (label) {
                if (value !== null) {
                    add(label, [[timestamp, value]]);
                }

                continue;
            }

            for (const [key, value] of Object.entries(row)) {
                if (['time', 'timestamp', 'ts', 'date'].includes(key)) {
                    continue;
                }

                const number = numericValue(value);

                if (number !== null) {
                    add(key, [[timestamp, number]]);
                }
            }
        }
    } else if (raw && typeof raw === 'object') {
        for (const [label, value] of Object.entries(raw)) {
            const points =
                value && typeof value === 'object' && !Array.isArray(value)
                    ? ((value as { data?: unknown; value?: unknown }).data ??
                      (value as { value?: unknown }).value)
                    : value;
            add(label, pointRows(points));
        }
    }

    // Native cache-hit values are already percentages, including values below 1%.
    return [...groups].map(([label, points], index) => ({
        label,
        color: colors[index % colors.length],
        points: points.sort(([a], [b]) => a - b),
    }));
}
