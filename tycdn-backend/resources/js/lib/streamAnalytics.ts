import { cdnflyStreamSeries, extractCdnflyRows } from './cdnflyResponse';
import { formatDate, numberValue, textValue } from './formatters';

export type StreamMetric = 'stream-bandwidth' | 'stream-traffic';
export type StreamPeriod = '1' | '6' | '12' | 'custom';
export type StreamRange = { start: string; end: string };
export type StreamSeries = {
    outbound: [number, number][];
    inbound: [number, number][];
};
export function streamRange(
    period: StreamPeriod,
    custom: StreamRange,
    now = new Date(),
): StreamRange {
    if (period !== 'custom') {
        return {
            start: formatDate(
                new Date(
                    now.getTime() - Number(period) * 3600000,
                ).toISOString(),
            ),
            end: formatDate(now.toISOString()),
        };
    }

    const start = custom.start.replace('T', ' '),
        end = custom.end.replace('T', ' ');

    if (
        !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(start) ||
        !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(end) ||
        !Number.isFinite(Date.parse(start.replace(' ', 'T'))) ||
        !Number.isFinite(Date.parse(end.replace(' ', 'T'))) ||
        Date.parse(end.replace(' ', 'T')) <= Date.parse(start.replace(' ', 'T'))
    ) {
        throw new Error('请选择有效的时间范围，结束时间必须晚于开始时间');
    }

    return {
        start: start.length === 16 ? `${start}:00` : start,
        end: end.length === 16 ? `${end}:00` : end,
    };
}
export function streamRealtimeParams(
    type: StreamMetric,
    range: StreamRange,
    port: string,
): Record<string, string> {
    return { type, start: range.start, end: range.end, port: port.trim() };
}
export function streamSeries(result: unknown): StreamSeries {
    const series = cdnflyStreamSeries(result);
    const clean = (points: [number, number][]) =>
        points
            .filter(([time, value]) => time > 0 && value >= 0)
            .map(([time, value]): [number, number] => [time, value])
            .sort((a, b) => a[0] - b[0]);

    return { outbound: clean(series.outbound), inbound: clean(series.inbound) };
}
export function streamMetric(value: unknown, type: StreamMetric): string {
    const number = numberValue(value);

    if (number === null) {
        return '—';
    }

    if (type === 'stream-bandwidth') {
        // The master returns bytes/second; network bandwidth is bits/second.
        const kbps = (number * 8) / 1000;

        if (kbps >= 900000) {
            return `${(kbps / 1000000).toFixed(2)} Gbps`;
        }

        if (kbps >= 900) {
            return `${(kbps / 1000).toFixed(2)} Mbps`;
        }

        return `${kbps.toFixed(2)} Kbps`;
    }

    const kb = number / 1000;

    if (kb >= 943718.4) {
        return `${(kb / 1000000).toFixed(2)} GB`;
    }

    if (kb >= 921.6) {
        return `${(kb / 1000).toFixed(2)} MB`;
    }

    return `${kb.toFixed(2)} KB`;
}
export function streamBytes(value: unknown): string {
    const number = numberValue(value);

    if (number === null) {
        return '—';
    }

    const unit =
        number < 1000 ? 0 : Math.min(4, Math.floor(Math.log10(number) / 3));

    return `${unit === 0 ? number : (number / 1000 ** unit).toFixed(2)} ${['Bytes', 'KB', 'MB', 'GB', 'TB'][unit]}`;
}
export type StreamRank = {
    port: string;
    count: number | null;
    traffic: number | null;
};
export function streamRanks(result: unknown): StreamRank[] {
    return extractCdnflyRows(result).map((row) => ({
        port: textValue(row.res ?? row.port),
        count: numberValue(row.count ?? row.new_connections),
        traffic: numberValue(row.traffic ?? row.outbound_traffic),
    }));
}
export function sortStreamRanks(
    rows: StreamRank[],
    key: 'count' | 'traffic',
    direction: 'asc' | 'desc',
): StreamRank[] {
    return [...rows].sort((a, b) => {
        const left = a[key],
            right = b[key];

        if (left === null) {
            return right === null ? 0 : 1;
        }

        if (right === null) {
            return -1;
        }

        return (left - right) * (direction === 'asc' ? 1 : -1);
    });
}
