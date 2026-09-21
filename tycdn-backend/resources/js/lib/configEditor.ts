export type ConfigValue =
    | string
    | number
    | boolean
    | null
    | ConfigValue[]
    | { [key: string]: ConfigValue };
export type ConfigObject = { [key: string]: ConfigValue };
export type Field = {
    key: string;
    label: string;
    type?:
        | 'text'
        | 'number'
        | 'toggle'
        | 'textarea'
        | 'password'
        | 'select'
        | 'object'
        | 'array';
    options?: { value: string | number; label: string }[];
    fields?: Field[];
    initial?: ConfigValue;
    help?: string;
    required?: boolean;
};

export function decodeValue(value: unknown): ConfigValue {
    if (typeof value === 'string' && /^[\[{]/.test(value.trim())) {
        try {
            return JSON.parse(value) as ConfigValue;
        } catch {
            return value;
        }
    }

    return value === undefined
        ? null
        : (JSON.parse(JSON.stringify(value)) as ConfigValue);
}

export function configRecord(record: Record<string, unknown>): ConfigObject {
    return Object.fromEntries(
        Object.entries(record).map(([key, value]) => [key, decodeValue(value)]),
    );
}

export function getField(record: ConfigValue, path: string): ConfigValue {
    let current = record;

    for (const part of path.split('.')) {
        if (!current || typeof current !== 'object' || Array.isArray(current)) {
            return null;
        }

        current = current[part];
    }

    return current ?? null;
}

export function setField(
    record: ConfigObject,
    path: string,
    value: ConfigValue,
): ConfigObject {
    const next = JSON.parse(JSON.stringify(record)) as ConfigObject;
    const parts = path.split('.');
    let target = next;

    for (const part of parts.slice(0, -1)) {
        const existing = target[part];

        if (
            existing !== '' &&
            existing !== null &&
            existing !== undefined &&
            (typeof existing !== 'object' || Array.isArray(existing))
        ) {
            throw new Error('现有配置格式无法编辑，请重新加载并检查配置。');
        }

        if (
            target[part] === undefined ||
            target[part] === null ||
            target[part] === ''
        ) {
            target[part] = {};
        }

        target = target[part] as ConfigObject;
    }

    target[parts.at(-1)!] = value;

    return next;
}

/** Send only edited top-level fields; nested unknown fields and origin weights survive. */
export function configPatch(
    before: ConfigObject,
    after: ConfigObject,
    allowed: string[],
): ConfigObject {
    return Object.fromEntries(
        [...new Set(allowed.map((key) => key.split('.')[0]))]
            .filter(
                (key) =>
                    JSON.stringify(before[key]) !==
                        JSON.stringify(after[key]) && after[key] !== undefined,
            )
            .map((key) => [key, after[key]]),
    );
}

export function secretField(key: string): boolean {
    return /password|passwd|secret|token|private.?key|https_key|smtp_pass|api.?key|access_key/i.test(
        key,
    );
}

export const fieldLabels: Record<string, string> = {
    name: '名称',
    des: '说明',
    enable: '启用',
    enabled: '启用',
    state: '状态',
    type: '类型',
    value: '值',
    id: 'ID',
    uid: '用户 ID',
    sort: '排序',
    addr: '地址',
    weight: '权重',
    port: '端口',
    protocol: '协议',
    host: '主机名',
    path: '路径',
    interval: '间隔（秒）',
    timeout: '超时（秒）',
    email: '邮箱',
    phone: '手机',
    title: '标题',
    data: '内容',
    content: '内容',
    url: '链接',
    domain: '域名',
    method: '方法',
    action: '动作',
    country: '国家',
    province: '省份',
    isp: '运营商',
    ip: 'IP 地址',
    ssl: 'SSL',
    username: '用户名',
    password: '密码',
    token: '密钥',
    smtp_host: 'SMTP 服务器',
    smtp_port: 'SMTP 端口',
    smtp_user: 'SMTP 用户',
    smtp_password: 'SMTP 密码',
    window_seconds: '统计窗口（秒）',
    hit_threshold: '命中阈值',
    block_seconds: '封禁时长（秒）',
    http: 'HTTP 设置',
    stream: '四层设置',
    worker_processes: '工作进程数',
    worker_connections: '每进程连接数',
    logs_dir: '日志目录',
    proxy_cache_dir: '缓存目录',
    proxy_cache_max_size: '缓存容量',
    gzip_comp_level: '压缩级别',
    proxy_connect_timeout: '连接超时',
    proxy_read_timeout: '读取超时',
    proxy_send_timeout: '发送超时',
    sys_name: '系统名称',
    user_console_title: '用户后台标题',
    admin_console_title: '管理后台标题',
    footer_link: '底部链接',
    footer_copyright: '底部文字',
    need: '要求填写',
    verify: '要求验证',
    qq: 'QQ',
    username_need: '要求用户名',
};

export function inferredFields(value: ConfigObject): Field[] {
    return Object.entries(value).map(([key, item]) => ({
        key,
        label: fieldLabels[key] ?? key.replaceAll('_', ' '),
        type: secretField(key)
            ? 'password'
            : Array.isArray(item)
              ? 'array'
              : item && typeof item === 'object'
                ? 'object'
                : typeof item === 'boolean'
                  ? 'toggle'
                  : typeof item === 'number'
                    ? 'number'
                    : typeof item === 'string' &&
                        (item.length > 150 || item.includes('\n'))
                      ? 'textarea'
                      : 'text',
    }));
}
