export type NodeMetric = 'bandwidth' | 'tcp_conn' | 'sys_load' | 'disk_usage';
export type NodeUnit = 'bps' | 'MB' | '%' | 'count' | 'load';
export type NodePoint = [number, number];
export interface NodeSeries {
    name: string;
    points: NodePoint[];
}
export interface NodeChart {
    title: string;
    unit: NodeUnit;
    series: NodeSeries[];
}
function record(value: unknown): Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value)
        ? (value as Record<string, unknown>)
        : {};
}
export function nodePayload(value: unknown): unknown {
    let result = value;

    for (let i = 0; i < 4; i++) {
        const object = record(result);

        if (!('data' in object)) {
            break;
        }

        result = object.data;
    }

    return result;
}
function number(value: unknown): number | null {
    const raw = record(value).value ?? value;

    if (
        raw === null ||
        raw === undefined ||
        raw === '' ||
        typeof raw === 'boolean'
    ) {
        return null;
    }

    const result = Number(raw);

    return Number.isFinite(result) ? result : null;
}
export function nodeTime(value: unknown): number {
    if (typeof value === 'number') {
        return value < 1e12 ? value * 1000 : value;
    }

    return typeof value === 'string'
        ? Date.parse(value.replace(' ', 'T'))
        : NaN;
}
function points(value: unknown): NodePoint[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .flatMap((pair): NodePoint[] => {
            if (!Array.isArray(pair)) {
                return [];
            }

            const time = nodeTime(pair[0]),
                amount = number(pair[1]);

            return Number.isFinite(time) && amount !== null
                ? [[time, amount]]
                : [];
        })
        .sort((a, b) => a[0] - b[0]);
}
export function nodeCharts(payload: unknown, metric: NodeMetric): NodeChart[] {
    const data = nodePayload(payload);

    if (metric === 'tcp_conn') {
        return [
            {
                title: '连接数',
                unit: 'count',
                series: [{ name: '连接数', points: points(data) }],
            },
        ];
    }

    return Object.entries(record(data)).map(([key, value]): NodeChart => {
        if (metric === 'sys_load') {
            const title =
                (
                    {
                        cpu: 'CPU使用率',
                        mem: '内存使用率',
                        load: '系统负载',
                    } as Record<string, string>
                )[key] ?? key;

            return {
                title,
                unit: key === 'load' ? 'load' : '%',
                series: [{ name: title, points: points(value) }],
            };
        }

        return {
            title: key,
            unit: metric === 'bandwidth' ? 'bps' : '%',
            series: Array.isArray(value)
                ? value.map((item) => {
                      const row = record(item);

                      return {
                          name: String(row.name ?? key),
                          points: points(row.data),
                      };
                  })
                : [],
        };
    });
}
// CDNfly node-traffic values are MB, despite their bytes_* field names.
export function nodeTraffic(
    payload: unknown,
    outbound: boolean,
    inbound: boolean,
): { chart: NodeChart; total: number } {
    const data = nodePayload(payload),
        samples = new Map<number, number>();

    if (Array.isArray(data) && (outbound || inbound)) {
        for (const item of data) {
            const row = record(item),
                time = nodeTime(row.create_at);

            if (!Number.isFinite(time)) {
                continue;
            }

            const amount =
                (outbound ? (number(row.bytes_sent) ?? 0) : 0) +
                (inbound ? (number(row.bytes_received) ?? 0) : 0);
            samples.set(time, (samples.get(time) ?? 0) + amount);
        }
    }

    const values = [...samples.entries()].sort((a, b) => a[0] - b[0]);

    return {
        chart: {
            title: '节点流量',
            unit: 'MB',
            series: [{ name: '流量', points: values }],
        },
        total: values.reduce((sum, p) => sum + p[1], 0),
    };
}
export function nodeValue(value: number, unit: NodeUnit, digits = 2): string {
    if (unit === 'bps') {
        const kb = value / 1000;

        return kb >= 900000
            ? `${(kb / 1e6).toFixed(digits)} Gbps`
            : kb >= 900
              ? `${(kb / 1000).toFixed(digits)} Mbps`
              : `${kb.toFixed(digits)} Kbps`;
    }

    if (unit === 'MB') {
        return value >= 1e6
            ? `${(value / 1e6).toFixed(digits)} TB`
            : value >= 1000
              ? `${(value / 1000).toFixed(digits)} GB`
              : `${value.toFixed(digits)} MB`;
    }

    if (unit === '%') {
        return `${value.toFixed(digits)} %`;
    }

    return value.toFixed(unit === 'count' ? 0 : digits);
}
export function nodeDate(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}
