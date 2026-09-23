import { extractCdnflyRows } from './cdnflyResponse';
import { numberValue } from './formatters';
import { streamMetric } from './streamAnalytics';

export type DashboardPeriod =
    | 'today'
    | 'yesterday'
    | 'last7'
    | 'last30'
    | 'lastMonth';
export type DashboardMetric =
    | 'bandwidth'
    | 'req'
    | 'traffic'
    | 'blackip'
    | 'users'
    | 'packages'
    | 'recharge';
export type DashboardPoint = { label: string; value: number };

/** Match the master's half-open calendar ranges, including its seven prior days plus today. */
export function dashboardRange(
    period: DashboardPeriod,
    now = new Date(),
): { start: string; end: string } {
    const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const end = new Date(start);
    end.setDate(end.getDate() + 1);

    if (period === 'yesterday') {
        start.setDate(start.getDate() - 1);
        end.setDate(end.getDate() - 1);
    }

    if (period === 'last7' || period === 'last30') {
        start.setDate(start.getDate() - (period === 'last7' ? 7 : 30));
    }

    if (period === 'lastMonth') {
        start.setDate(1);
        start.setMonth(start.getMonth() - 1);
        end.setDate(1);
        end.setMonth(now.getMonth());
        end.setFullYear(now.getFullYear());
    }

    const date = (d: Date) =>
        `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    return { start: date(start), end: date(end) };
}
export function dashboardPoints(
    result: unknown,
    metric: DashboardMetric,
): DashboardPoint[] {
    return extractCdnflyRows(result)
        .flatMap((row) => {
            const label = row.date ?? row.time;
            const value = numberValue(
                row.value ?? (metric === 'recharge' ? row.sum : row.count),
            );

            return typeof label === 'string' &&
                label &&
                value !== null &&
                value >= 0
                ? [{ label, value }]
                : [];
        })
        .sort((a, b) => a.label.localeCompare(b.label));
}
export function dashboardMetric(
    value: unknown,
    metric: DashboardMetric,
): string {
    const n = numberValue(value);

    if (n === null) {
        return '—';
    }

    if (metric === 'bandwidth' || metric === 'traffic') {
        return streamMetric(
            n,
            metric === 'bandwidth' ? 'stream-bandwidth' : 'stream-traffic',
        );
    }

    if (metric === 'recharge') {
        return `${n.toLocaleString('zh-CN', { maximumFractionDigits: 2 })} USDT`;
    }

    const suffix =
        metric === 'users'
            ? ' 人'
            : metric === 'packages'
              ? ' 个'
              : metric === 'req'
                ? '次'
                : '个';

    return (
        (n > 1e8
            ? `${(n / 1e8).toFixed(0)}亿`
            : n > 1e4
              ? `${(n / 1e4).toFixed(0)}万`
              : String(n)) + suffix
    );
}
