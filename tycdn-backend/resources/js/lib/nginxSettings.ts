import type { CdnflyRecord } from './sharedTypes';

export type NginxField = {
    path: string;
    label: string;
    kind?: 'toggle' | 'version' | 'number';
    unit?: string;
    help?: string;
    min?: number;
    max?: number;
    on?: string;
    off?: string;
};
export type NginxCard = {
    title: string;
    help: string;
    wide?: boolean;
    columns?: boolean;
    fields: NginxField[];
};
const field = (path: string, extra: Partial<NginxField> = {}): NginxField => ({
    path,
    label: path.split('.').at(-1)!,
    ...extra,
});
const toggle = (path: string, extra: Partial<NginxField> = {}): NginxField =>
    field(path, { kind: 'toggle', on: 'on', off: 'off', ...extra });
const numeric = (path: string, extra: Partial<NginxField> = {}): NginxField =>
    field(path, { kind: 'number', min: 1, ...extra });
export const nginxSections: Record<
    string,
    { label: string; title: string; help: string; cards: NginxCard[] }
> = {
    worker: {
        label: 'Worker',
        title: 'Worker 运行参数',
        help: '控制 Nginx worker 进程、连接容量和日志落盘位置。',
        cards: [
            {
                title: '进程与连接',
                help: '调整 worker 数量和单进程连接上限。',
                fields: [
                    field('worker_processes'),
                    numeric('worker_connections'),
                ],
            },
            {
                title: '资源与日志',
                help: '限制文件句柄，控制优雅退出时间和日志目录。',
                fields: [
                    numeric('worker_rlimit_nofile'),
                    field('worker_shutdown_timeout'),
                    field('logs_dir', { label: '日志目录' }),
                ],
            },
        ],
    },
    http: {
        label: 'Http',
        title: 'HTTP 全局参数',
        help: '按压缩、回源缓存和连接响应三类管理常用 Nginx HTTP 配置。',
        cards: [
            {
                title: 'Gzip 压缩',
                help: '控制压缩等级、协议版本、最小压缩体积和 Vary 响应头。',
                fields: [
                    numeric('http.gzip_comp_level', { max: 9 }),
                    field('http.gzip_http_version', { kind: 'version' }),
                    field('http.gzip_min_length'),
                    toggle('http.gzip_vary'),
                ],
            },
            {
                title: '回源与缓存',
                help: '管理回源出口、缓存目录、缓存上限、缓冲和失败重试策略。',
                fields: [
                    toggle('http.server_addr_outgoing', {
                        label: '入口IP回源',
                        on: '1',
                        off: '0',
                    }),
                    toggle('http.proxy_request_buffering'),
                    toggle('http.proxy_buffering'),
                    field('http.proxy_cache_dir'),
                    field('http.proxy_cache_max_size'),
                    field('http.proxy_cache_methods'),
                    field('http.proxy_http_version', { kind: 'version' }),
                    field('http.proxy_max_temp_file_size'),
                    field('http.proxy_next_upstream'),
                    field('http.proxy_connect_timeout'),
                    field('http.proxy_send_timeout'),
                    field('http.proxy_read_timeout'),
                ],
            },
            {
                title: '连接与响应',
                help: '配置请求缓冲、长连接、响应标识和请求头缓冲。',
                wide: true,
                columns: true,
                fields: [
                    numeric('http.client_body_buffer_size', {
                        unit: 'KB',
                        help: '不建议设置过大，否则节点内存消耗会升高。',
                    }),
                    field('http.server'),
                    field('http.client_max_body_size'),
                    field('http.default_type'),
                    numeric('http.keepalive_requests'),
                    field('http.keepalive_timeout'),
                    toggle('http.log_not_found'),
                    toggle('http.server_tokens'),
                    field('http.large_client_header_buffers'),
                    numeric('http.server_names_hash_max_size'),
                    numeric('http.server_names_hash_bucket_size'),
                ],
            },
        ],
    },
    stream: {
        label: 'Stream',
        title: 'Stream 代理参数',
        help: '控制 TCP/UDP 代理连接建立和数据转发超时时间。',
        cards: [
            {
                title: '代理超时',
                help: '用于四层代理的连接与读写超时。',
                wide: true,
                columns: true,
                fields: [
                    field('stream.proxy_connect_timeout'),
                    field('stream.proxy_timeout'),
                ],
            },
        ],
    },
};
export const nginxFields = Object.values(nginxSections).flatMap((section) =>
    section.cards.flatMap((card) => card.fields),
);
export const object = (value: unknown): CdnflyRecord =>
    value && typeof value === 'object' && !Array.isArray(value)
        ? (value as CdnflyRecord)
        : {};
export const getValue = (config: CdnflyRecord, path: string): unknown =>
    path.split('.').reduce<unknown>((value, key) => object(value)[key], config);
export function setValue(config: CdnflyRecord, path: string, value: unknown) {
    const [section, key] = path.split('.');

    if (key) {
        config[section] = { ...object(config[section]), [key]: value };
    } else {
        config[section] = value;
    }
}
export function fieldError(field: NginxField, value: unknown): string {
    const text = String(value ?? '').trim();

    if (!text) {
        return `${field.label}不能为空`;
    }

    if (/[;{}\r\n\x00]/.test(text)) {
        return `${field.label}请输入单个配置值`;
    }

    if (
        field.kind === 'number' &&
        (!/^\d+$/.test(text) ||
            Number(text) < (field.min ?? 1) ||
            (field.max !== undefined && Number(text) > field.max))
    ) {
        return `${field.label}请输入${field.max ? ` ${field.min}–${field.max} 范围内的` : '有效的正'}整数`;
    }

    if (field.kind === 'version' && !['1.0', '1.1'].includes(text)) {
        return `${field.label}请选择协议版本`;
    }

    if (field.kind === 'toggle' && ![field.on, field.off].includes(text)) {
        return `${field.label}请选择开关状态`;
    }

    if (field.path === 'worker_processes' && !/^(auto|[1-9]\d*)$/.test(text)) {
        return 'worker_processes 请输入 auto 或正整数';
    }

    return '';
}
