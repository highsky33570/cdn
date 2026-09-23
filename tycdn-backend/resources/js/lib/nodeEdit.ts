export function nodeObject(value: unknown): Record<string, unknown> {
    if (value === null || value === undefined || value === '') {
        return {};
    }

    const decoded = typeof value === 'string' ? JSON.parse(value) : value;

    if (!decoded || typeof decoded !== 'object' || Array.isArray(decoded)) {
        throw new Error('节点配置格式不正确');
    }

    return decoded as Record<string, unknown>;
}
export function nodeDetail(value: unknown): Record<string, unknown> {
    const row = nodeObject(value);

    return 'data' in row ? nodeDetail(row.data) : row;
}
export function cacheGigabytes(value: unknown): string {
    if (value === undefined || value === null || value === '') {
        return '';
    }

    const match = String(value).match(/^(\d+(?:\.\d+)?)([gmk]?)$/i);

    if (!match) {
        throw new Error('缓存上限格式不正确');
    }

    const amount = Number(match[1]),
        unit = match[2].toLowerCase();

    return String(
        unit === 'g'
            ? amount
            : unit === 'm'
              ? amount / 1024
              : unit === 'k'
                ? amount / 1024 ** 2
                : amount / 1024 ** 3,
    );
}
export function nginxNodeConfig(
    original: Record<string, unknown>,
    fields: { cache: string; size: string; logs: string },
): Record<string, unknown> {
    const config = JSON.parse(JSON.stringify(original)) as Record<
            string,
            unknown
        >,
        http = { ...nodeObject(config.http) };

    if (
        String(fields.size).trim() &&
        (!Number.isFinite(Number(fields.size)) || Number(fields.size) < 0)
    ) {
        throw new Error('请输入有效的缓存上限');
    }

    for (const [key, value] of Object.entries({
        proxy_cache_dir: fields.cache.trim(),
        proxy_cache_max_size: String(fields.size).trim()
            ? `${Number(fields.size)}g`
            : '',
    })) {
        if (value) {
            http[key] = value;
        } else {
            delete http[key];
        }
    }

    config.http = http;

    if (fields.logs.trim()) {
        config.logs_dir = fields.logs.trim();
    } else {
        delete config.logs_dir;
    }

    return config;
}
export function trafficSettings(value: unknown) {
    const row = nodeObject(value);
    const types = row.type ?? row.type_list ?? ['inbound', 'outbound'];

    if (!Array.isArray(types)) {
        throw new Error('流量类型格式不正确');
    }

    return {
        enable: row.enable === true || row.enable === 1,
        from_day: String(row.from_day ?? 1),
        from_hour: String(row.from_hour ?? '12:00:00'),
        traffic_total: String(row.traffic_total ?? 500),
        type: types.map(String),
        excl_nic: String(row.excl_nic ?? ''),
    };
}
export function trafficPayload(value: ReturnType<typeof trafficSettings>) {
    const day = Number(value.from_day),
        total = Number(value.traffic_total);

    if (
        !String(value.from_day).trim() ||
        !Number.isInteger(day) ||
        day < 1 ||
        day > 31
    ) {
        throw new Error('统计日期必须为 1–31');
    }

    if (!/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/.test(value.from_hour)) {
        throw new Error('统计时间格式应为 HH:mm:ss');
    }

    if (
        !String(value.traffic_total).trim() ||
        !Number.isFinite(total) ||
        total < 0
    ) {
        throw new Error('请输入有效的流量限制');
    }

    if (value.enable && !value.type.length) {
        throw new Error('请选择至少一种流量类型');
    }

    return { ...value, from_day: day, traffic_total: total };
}
