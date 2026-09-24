import type { ConfigValue } from './configEditor';
import type { CdnflyRecord } from './sharedTypes';
export type DefaultOption = { value: string; label: string; help?: string };
export type DefaultField = {
    type: string;
    name: string;
    label: string;
    kind?:
        | 'toggle'
        | 'number'
        | 'choices'
        | 'protocols'
        | 'select'
        | 'cache'
        | 'headers'
        | 'waf'
        | 'autoblock'
        | 'ssl';
    options?: DefaultOption[];
    help?: string;
    unit?: string;
    wide?: boolean;
    regional?: boolean;
};
const f = (
    name: string,
    label: string,
    extra: Partial<DefaultField> = {},
): DefaultField => ({
    type: 'site_default_config',
    name,
    label,
    regional: true,
    ...extra,
});
const toggle = (
    name: string,
    label: string,
    extra: Partial<DefaultField> = {},
) => f(name, label, { kind: 'toggle', ...extra });
const number = (
    name: string,
    label: string,
    extra: Partial<DefaultField> = {},
) => f(name, label, { kind: 'number', ...extra });
export const balanceOptions = [
    { value: 'rr', label: '轮循' },
    { value: 'ip_hash', label: '定源' },
];
export const sslOptions: DefaultOption[] = [
    {
        value: 'recommended',
        label: '推荐配置',
        help: 'TLS 1.2/1.3，安全套件，建议默认使用。',
    },
    {
        value: 'legacy_tls12',
        label: '扩展兼容',
        help: '兼容旧 TLS 1.2 客户端，会启用部分旧套件。',
    },
    {
        value: 'legacy_full',
        label: '极限兼容',
        help: '兼容 TLS 1.0/1.1，不安全，仅临时使用。',
    },
    { value: 'custom', label: '自定义', help: '手动设置协议、套件和优先级。' },
];
const ciphers = [
    'ECDHE-ECDSA-AES128-GCM-SHA256',
    'ECDHE-RSA-AES128-GCM-SHA256',
    'ECDHE-ECDSA-AES256-GCM-SHA384',
    'ECDHE-RSA-AES256-GCM-SHA384',
    'ECDHE-ECDSA-CHACHA20-POLY1305',
    'ECDHE-RSA-CHACHA20-POLY1305',
].join(':');
const compatible = [
    ciphers,
    'ECDHE-ECDSA-AES128-SHA256',
    'ECDHE-RSA-AES128-SHA256',
    'ECDHE-ECDSA-AES256-SHA384',
    'ECDHE-RSA-AES256-SHA384',
    'ECDHE-ECDSA-AES128-SHA',
    'ECDHE-RSA-AES128-SHA',
    'ECDHE-ECDSA-AES256-SHA',
    'ECDHE-RSA-AES256-SHA',
    'AES128-GCM-SHA256',
    'AES256-GCM-SHA384',
    'AES128-SHA256',
    'AES256-SHA256',
    'AES128-SHA',
    'AES256-SHA',
].join(':');
export const sslPresets: Record<string, Record<string, string>> = {
    recommended: {
        'https_listen-ssl_protocols': 'TLSv1.2 TLSv1.3',
        'https_listen-ssl_ciphers': ciphers,
        'https_listen-ssl_prefer_server_ciphers': 'off',
    },
    legacy_tls12: {
        'https_listen-ssl_protocols': 'TLSv1.2 TLSv1.3',
        'https_listen-ssl_ciphers': compatible,
        'https_listen-ssl_prefer_server_ciphers': 'on',
    },
    legacy_full: {
        'https_listen-ssl_protocols': 'TLSv1 TLSv1.1 TLSv1.2 TLSv1.3',
        'https_listen-ssl_ciphers': 'DEFAULT:@SECLEVEL=0',
        'https_listen-ssl_prefer_server_ciphers': 'on',
    },
};
export const sslFields = [
    f('https_listen-ssl_protocols', 'SSL 协议', {
        kind: 'protocols',
        wide: true,
    }),
    f('https_listen-ssl_ciphers', 'SSL 套件', { wide: true }),
    f('https_listen-ssl_prefer_server_ciphers', '优先服务器套件', {
        kind: 'choices',
        options: [
            { value: 'on', label: '开启' },
            { value: 'off', label: '关闭' },
        ],
    }),
];
export const defaultSections: Record<
    string,
    {
        label: string;
        cards: { title: string; help: string; fields: DefaultField[] }[];
    }
> = {
    site: {
        label: '网站',
        cards: [
            {
                title: 'HTTP',
                help: '新建网站默认 HTTP 监听入口。',
                fields: [f('http_listen-port', '监听端口')],
            },
            {
                title: 'HTTPS',
                help: '监听、协议能力和 TLS 兼容策略。',
                fields: [
                    f('https_listen-port', '监听端口'),
                    toggle('https_listen-hsts', '开启HSTS'),
                    toggle('https_listen-http2', '开启HTTP2'),
                    toggle('https_listen-http3', '开启HTTP3'),
                    toggle('https_listen-force_ssl_enable', '强制HTTPS'),
                    f('__ssl', 'SSL配置', {
                        kind: 'ssl',
                        wide: true,
                        regional: false,
                    }),
                    toggle('https_listen-ocsp_stapling', 'ocsp_stapling'),
                ],
            },
            {
                title: '回源设置',
                help: '默认源站协议、端口和超时。',
                fields: [
                    f('backend_protocol', '回源协议', {
                        kind: 'choices',
                        wide: true,
                        options: [
                            {
                                value: 'http',
                                label: 'HTTP',
                                help: '使用 HTTP 连接源站',
                            },
                            {
                                value: 'https',
                                label: 'HTTPS',
                                help: '使用 HTTPS 连接源站',
                            },
                            {
                                value: 'follow',
                                label: '跟随协议',
                                help: '按访客访问协议回源',
                            },
                            {
                                value: 'followPortProtocol',
                                label: '跟随协议和端口',
                                help: '协议和端口都跟随访客请求',
                            },
                        ],
                    }),
                    number('backend_http_port', '回源http端口'),
                    number('backend_https_port', '回源https端口'),
                    number('proxy_timeout', '回源超时'),
                    number('proxy_connect_timeout', '连接超时'),
                    f('proxy_ssl_protocols', '回源SSL协议', {
                        kind: 'protocols',
                        wide: true,
                    }),
                ],
            },
            {
                title: '缓存',
                help: '创建站点时继承的默认缓存规则。',
                fields: [
                    f('proxy_cache', '缓存规则', { kind: 'cache', wide: true }),
                ],
            },
            {
                title: '源站请求头',
                help: '创建站点时继承的回源请求头。',
                fields: [
                    f('req_header', '回源请求头', {
                        kind: 'headers',
                        wide: true,
                    }),
                ],
            },
            {
                title: '访问日志',
                help: '节点日志采集默认策略。',
                fields: [
                    toggle('log_req_header', '记录请求头', {
                        help: '开启只会增加硬盘空间占用，可长期开启',
                    }),
                    toggle('log_resp_header', '记录响应头', {
                        help: '建议仅在调试时开启，始终开启会增加 CPU、硬盘空间的占用',
                    }),
                    toggle('log_req_body', '记录请求体', {
                        help: '建议仅在调试时开启，始终开启对节点性能消耗较大',
                    }),
                    number('log_req_body_max_size', '请求体大小限制', {
                        unit: 'KB',
                    }),
                ],
            },
            {
                title: '安全设置',
                help: '默认 CC 规则和内置 WAF 检测模块。',
                fields: [
                    f('cc_default_rule', '默认CC规则', { kind: 'select' }),
                    toggle('waf_enable', 'WAF防护开关', {
                        help: '控制新建站点默认是否开启站点级 WAF 总开关。',
                    }),
                    f('waf_ip_auto_block', 'WAF 攻击自动封禁', {
                        kind: 'autoblock',
                        help: '用于新建站点；同一 IP 达到阈值后自动临时封禁。',
                    }),
                    f('waf', '内置模块防护', {
                        kind: 'waf',
                        wide: true,
                        help: 'SQL 注入、XSS、文件上传等通用检测模块',
                    }),
                ],
            },
            {
                title: '其它',
                help: '负载、压缩、WebSocket 和实时数据。',
                fields: [
                    f('balance_way', '负载方式', {
                        kind: 'choices',
                        options: balanceOptions,
                    }),
                    f('spider_allow', '搜索引擎爬虫', {
                        kind: 'choices',
                        options: [
                            { value: '', label: '不处理' },
                            { value: 'allow', label: '放行' },
                            { value: 'deny', label: '拦截' },
                        ],
                    }),
                    toggle('gzip_enable', '开启Gzip'),
                    f('gzip_types', 'gzip types'),
                    toggle('websocket_enable', '开启Websocket'),
                    toggle('block_proxy', '屏蔽透明代理'),
                    toggle('recv_real_time', '数据实时返回'),
                    toggle('send_real_time', '数据实时发送'),
                ],
            },
        ],
    },
    stream: {
        label: '转发',
        cards: [
            {
                title: '转发默认值',
                help: '监听协议、负载方式和 proxy protocol。',
                fields: [
                    f('listen_protocol', '监听协议', {
                        type: 'stream_default_config',
                        kind: 'choices',
                        options: [
                            { value: 'tcp', label: 'tcp' },
                            { value: 'udp', label: 'udp' },
                        ],
                    }),
                    f('balance_way', '负载方式', {
                        type: 'stream_default_config',
                        kind: 'choices',
                        options: balanceOptions,
                    }),
                    toggle('proxy_protocol', '开启proxy protocol', {
                        type: 'stream_default_config',
                    }),
                ],
            },
        ],
    },
    cert: {
        label: '证书',
        cards: [
            {
                title: '证书默认值',
                help: '证书申请默认 CA 类型。',
                fields: [
                    f('cert_default_type', '默认证书类型', {
                        type: 'cert_default_config',
                        kind: 'choices',
                        options: [
                            { value: 'zerossl', label: 'zerossl' },
                            { value: 'lets', label: 'lets' },
                        ],
                    }),
                ],
            },
        ],
    },
};
export const defaultFields = [
    ...Object.values(defaultSections)
        .flatMap((section) => section.cards.flatMap((card) => card.fields))
        .filter((field) => field.kind !== 'ssl'),
    ...sslFields,
    f('proxy_http_version', '回源HTTP版本', {
        kind: 'choices',
        options: [
            { value: '1.0', label: '1.0' },
            { value: '1.1', label: '1.1' },
        ],
    }),
    toggle('ups_keepalive', '回源连接池'),
    number('post_size_limit', 'POST请求大小限制', { unit: 'MB' }),
];
export const defaultKey = (field: Pick<DefaultField, 'type' | 'name'>) =>
    `${field.type}:${field.name}`;
export const defaultRowKey = (row: CdnflyRecord) =>
    `${row.scope_name ?? 'global'}:${row.scope_id ?? 0}:${row.type}:${row.name}`;
export const defaultFieldFor = (row: CdnflyRecord) =>
    defaultFields.find(
        (field) => field.type === row.type && field.name === row.name,
    );
export const jsonField = (field: DefaultField) =>
    ['cache', 'headers', 'waf', 'autoblock'].includes(field.kind ?? '');
export function decodeDefault(field: DefaultField, raw: unknown): ConfigValue {
    if (!jsonField(field)) {
        return String(raw ?? '');
    }

    if (raw === undefined || raw === null || raw === '') {
        if (field.kind === 'waf') {
            return Object.fromEntries(
                Object.keys(wafModules).map((key) => [key, 'off']),
            );
        }

        return ['cache', 'headers'].includes(field.kind!) ? [] : {};
    }

    const value = typeof raw === 'string' ? JSON.parse(raw) : raw;

    if (
        !value ||
        typeof value !== 'object' ||
        Array.isArray(value) !== ['cache', 'headers'].includes(field.kind!) ||
        (Array.isArray(value) &&
            value.some(
                (row) => !row || typeof row !== 'object' || Array.isArray(row),
            ))
    ) {
        throw new Error('配置格式无法识别，请检查主控配置后重试');
    }

    if (field.kind === 'waf') {
        return {
            ...Object.fromEntries(
                Object.keys(wafModules).map((key) => [key, 'protect']),
            ),
            ...value,
        };
    }

    return value as ConfigValue;
}
export const encodeDefault = (field: DefaultField, value: ConfigValue) =>
    jsonField(field) ? JSON.stringify(value) : String(value ?? '');
export function defaultValueError(
    field: DefaultField,
    value: ConfigValue,
): string {
    const text = String(value ?? '');

    if (
        field.kind === 'number' &&
        (!/^\d+$/.test(text) || !Number.isSafeInteger(Number(text)))
    ) {
        return '请输入大于或等于 0 的整数';
    }

    if (field.kind === 'toggle' && !['0', '1'].includes(text)) {
        return '请选择开启或关闭';
    }

    if (
        field.kind === 'choices' &&
        !field.options?.some((option) => option.value === text)
    ) {
        return '请选择有效选项';
    }

    if (field.kind === 'autoblock') {
        const obj = value as Record<string, ConfigValue>;

        if (
            ['window_seconds', 'hit_threshold', 'block_seconds'].some(
                (key) =>
                    !Number.isSafeInteger(Number(obj[key])) ||
                    Number(obj[key]) <= 0,
            )
        ) {
            return '统计窗口、命中阈值和封禁时长必须为正整数';
        }
    }

    return '';
}
export const wafModules: Record<string, string> = {
    sqli: 'SQL 注入检测',
    xss: 'XSS 检测',
    upload: '文件上传检测',
    fi: '文件包含检测',
    cmdi: '命令注入检测',
    java: 'JAVA 代码注入检测',
    jdeser: 'JAVA 反序列化检测',
    php: 'PHP 代码注入检测',
    phpdeser: 'PHP 反序列化检测',
    ssti: 'SSTI 模板注入检测',
};
export const wafModes = [
    { value: 'off', label: '禁用' },
    { value: 'observe', label: '仅观察' },
    { value: 'protect', label: '防护' },
];
