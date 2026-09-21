import type { Field } from './configEditor';
import { siteSections } from './siteSettings';

// Names supported by the master's default-settings form; these are field
// definitions only. A value is written only when the operator saves it.
const siteNames = new Set([
    'backend_http_port',
    'backend_https_port',
    'backend_protocol',
    'balance_way',
    'block_proxy',
    'cc_default_rule',
    'gzip_enable',
    'gzip_types',
    'http_listen-port',
    'https_listen-force_ssl_enable',
    'https_listen-hsts',
    'https_listen-http2',
    'https_listen-http3',
    'https_listen-ocsp_stapling',
    'https_listen-port',
    'https_listen-ssl_ciphers',
    'https_listen-ssl_prefer_server_ciphers',
    'https_listen-ssl_protocols',
    'log_req_body',
    'log_req_body_max_size',
    'log_req_header',
    'log_resp_header',
    'post_size_limit',
    'proxy_cache',
    'proxy_connect_timeout',
    'proxy_http_version',
    'proxy_ssl_protocols',
    'proxy_timeout',
    'recv_real_time',
    'req_header',
    'send_real_time',
    'spider_allow',
    'ups_keepalive',
    'waf',
    'waf_enable',
    'waf_ip_auto_block',
    'websocket_enable',
]);
export const siteDefaultFields: Field[] = Object.values(siteSections)
    .flatMap((sections) => sections.flatMap((section) => section.fields))
    .map((field) => ({ ...field, key: field.key.replaceAll('.', '-') }))
    .filter((field) => siteNames.has(field.key));
siteDefaultFields.push(
    { key: 'gzip_types', label: 'Gzip 压缩类型' },
    {
        key: 'proxy_http_version',
        label: '回源 HTTP 版本',
        type: 'select',
        options: [
            { value: '1.0', label: 'HTTP/1.0' },
            { value: '1.1', label: 'HTTP/1.1' },
        ],
    },
    { key: 'proxy_ssl_protocols', label: '回源 SSL 协议' },
    { key: 'spider_allow', label: '允许搜索引擎', type: 'toggle' },
);
export const streamDefaultFields: Field[] = [
    {
        key: 'listen_protocol',
        label: '监听协议',
        type: 'select',
        options: [
            { value: 'tcp', label: 'TCP' },
            { value: 'udp', label: 'UDP' },
        ],
    },
    {
        key: 'balance_way',
        label: '负载方式',
        type: 'select',
        options: [
            { value: 'rr', label: '轮循' },
            { value: 'ip_hash', label: '定源' },
        ],
    },
    { key: 'proxy_protocol', label: 'Proxy Protocol', type: 'toggle' },
];
