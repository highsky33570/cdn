export const DNS_TYPES = [
    'CloudFlare',
    'DNSPod.cn',
    'GoDaddy.com',
    'Aliyun',
    'cloudns.net',
    'Name.com',
    'Namecheap',
    'jdcloud.com',
    'dnsdun',
] as const;

export const AUTH_TEMPLATES: Record<string, Record<string, string>> = {
    CloudFlare: { CF_Key: '', CF_Email: '' },
    'DNSPod.cn': { DP_Id: '', DP_Key: '' },
    'GoDaddy.com': { GD_Key: '', GD_Secret: '' },
    Aliyun: { Ali_Key: '', Ali_Secret: '' },
    'cloudns.net': { CLOUDNS_SUB_AUTH_ID: '', CLOUDNS_AUTH_PASSWORD: '' },
    'Name.com': { Namecom_Username: '', Namecom_Token: '' },
    Namecheap: {
        NAMECHEAP_USERNAME: '',
        NAMECHEAP_API_KEY: '',
        NAMECHEAP_SOURCEIP: '',
    },
    'jdcloud.com': { JD_ACCESS_KEY_ID: '', JD_ACCESS_KEY_SECRET: '' },
    dnsdun: { Dnsdun_uid: '', Dnsdun_api_key: '' },
};

type ConfigValueType = 'number' | 'boolean' | 'select' | 'text' | 'json';
type ConfigMeta = {
    value: string;
    label: string;
    valueType: ConfigValueType;
    options?: string[];
};

export const SITE_CONFIG_NAMES: ConfigMeta[] = [
    { value: 'http_listen-port', label: 'HTTP 监听端口', valueType: 'number' },
    {
        value: 'https_listen-port',
        label: 'HTTPS 监听端口',
        valueType: 'number',
    },
    {
        value: 'backend_http_port',
        label: '回源 HTTP 端口',
        valueType: 'number',
    },
    {
        value: 'backend_https_port',
        label: '回源 HTTPS 端口',
        valueType: 'number',
    },
    { value: 'proxy_timeout', label: '回源超时', valueType: 'number' },
    {
        value: 'ups_keepalive_conn',
        label: '连接池最大空闲数',
        valueType: 'number',
    },
    {
        value: 'ups_keepalive_timeout',
        label: '空闲连接超时',
        valueType: 'number',
    },
    { value: 'post_size_limit', label: '上传最大大小', valueType: 'number' },
    { value: 'recv_real_time', label: '实时接收', valueType: 'number' },
    { value: 'send_real_time', label: '实时发送', valueType: 'number' },
    { value: 'https_listen-hsts', label: '开启 HSTS', valueType: 'boolean' },
    { value: 'https_listen-http2', label: '开启 HTTP/2', valueType: 'boolean' },
    {
        value: 'https_listen-force_ssl_enable',
        label: '强制 HTTPS',
        valueType: 'boolean',
    },
    { value: 'ups_keepalive', label: '启用回源连接池', valueType: 'boolean' },
    { value: 'range', label: '分片回源', valueType: 'boolean' },
    { value: 'gzip_enable', label: '启用 Gzip', valueType: 'boolean' },
    {
        value: 'websocket_enable',
        label: '开启 WebSocket',
        valueType: 'boolean',
    },
    { value: 'block_proxy', label: '屏蔽代理', valueType: 'boolean' },
    {
        value: 'https_listen-ocsp_stapling',
        label: '开启 OCSP Stapling',
        valueType: 'boolean',
    },
    {
        value: 'https_listen-ssl_prefer_server_ciphers',
        label: 'SSL 优先服务器密码',
        valueType: 'boolean',
    },
    {
        value: 'backend_protocol',
        label: '回源协议',
        valueType: 'select',
        options: ['http', 'https'],
    },
    {
        value: 'proxy_http_version',
        label: '回源 HTTP 版本',
        valueType: 'select',
        options: ['1.0', '1.1'],
    },
    {
        value: 'balance_way',
        label: '负载方式',
        valueType: 'select',
        options: ['rr', 'ip_hash'],
    },
    { value: 'proxy_ssl_protocols', label: '回源 SSL 协议', valueType: 'text' },
    {
        value: 'https_listen-ssl_protocols',
        label: 'SSL 协议',
        valueType: 'text',
    },
    { value: 'https_listen-ssl_ciphers', label: 'SSL 套件', valueType: 'text' },
    { value: 'gzip_types', label: 'Gzip 压缩类型', valueType: 'text' },
    { value: 'proxy_cache', label: '网站缓存', valueType: 'json' },
    { value: 'cc_default_rule', label: '默认 CC 规则', valueType: 'json' },
    { value: 'req_header', label: '请求头', valueType: 'json' },
    { value: 'extra_cc_rule', label: '自定义 CC 规则', valueType: 'json' },
];
