import type { Field } from './configEditor';
export type SiteSection = { title: string; help?: string; fields: Field[] };
const text = (key: string, label: string): Field => ({ key, label });
const num = (key: string, label: string): Field => ({
    key,
    label,
    type: 'number',
});
const flag = (key: string, label: string): Field => ({
    key,
    label,
    type: 'toggle',
});
const area = (key: string, label: string): Field => ({
    key,
    label,
    type: 'textarea',
});
const options = (
    key: string,
    label: string,
    values: [string | number, string][],
): Field => ({
    key,
    label,
    type: 'select',
    options: values.map(([value, label]) => ({ value, label })),
});
const object = (key: string, label: string, fields: Field[]): Field => ({
    key,
    label,
    type: 'object',
    fields,
});
const array = (
    key: string,
    label: string,
    fields?: Field[],
    initial?: Field['initial'],
): Field => ({ key, label, type: 'array', fields, initial });
const headerFields = [
    text('name', '名称'),
    text('value', '值'),
    options('action', '操作', [
        ['add', '设置'],
        ['del', '删除'],
    ]),
    flag('allow_repeat', '允许重复 Header'),
];
const matchFields = [
    options('item', '匹配项', [
        ['ip', '客户端 IP'],
        ['host', '域名'],
        ['uri', 'URI'],
        ['req_uri', '完整请求 URI'],
        ['req_method', '请求方法'],
        ['user_agent', 'User Agent'],
        ['referer', 'Referer'],
        ['country_iso_code', '国家代码'],
        ['province', '省份'],
        ['isp', '运营商'],
    ]),
    options('op', '运算符', [
        ['=', '等于'],
        ['!=', '不等于'],
        ['contain', '包含'],
        ['!contain', '不包含'],
        ['regex', '正则'],
        ['exists', '存在'],
        ['!exists', '不存在'],
    ]),
    area('value', '匹配值'),
    text('value2', '第二个值'),
];
const wafMatchFields = [
    text('field', '匹配字段'),
    text('key', '字段名称'),
    text('op', '运算符'),
    text('transform', '转换方式'),
    area('value', '匹配值（列表条件每行一项）'),
];
export const siteTabs = [
    { key: 'basic', label: '基本配置' },
    { key: 'origin', label: '回源设置' },
    { key: 'https', label: 'HTTPS设置' },
    { key: 'cache', label: '缓存设置' },
    { key: 'cc', label: 'CC防护' },
    { key: 'waf', label: 'WAF防护' },
    { key: 'advanced', label: '高级设置' },
];
export const siteSections: Record<string, SiteSection[]> = {
    basic: [
        {
            title: '基础设置',
            fields: [
                num('user_package', '网站套餐'),
                area('domain', '访问域名'),
                text('groups', '网站分组'),
                flag('enable', '网站启用'),
                flag('enable_ipv6', 'IPv6'),
            ],
        },
        {
            title: '访问设置',
            fields: [text('http_listen.port', 'HTTP 监听端口')],
        },
        {
            title: '源站列表',
            fields: [
                array(
                    'backend',
                    '源站',
                    [
                        text('addr', '源站地址'),
                        num('weight', '权重'),
                        options('state', '状态', [
                            ['up', '上线'],
                            ['down', '下线'],
                        ]),
                    ],
                    { addr: '', weight: 1, state: 'up' },
                ),
                options('balance_way', '负载方式', [
                    ['rr', '轮循'],
                    ['ip_hash', '定源'],
                ]),
                array(
                    'condition_backend',
                    '条件源站',
                    [
                        array('matcher', '匹配条件', matchFields),
                        area('backend', '源站地址'),
                    ],
                    { matcher: [], backend: '' },
                ),
            ],
        },
        {
            title: '源站可用性',
            fields: [
                object('health_check', '健康检查', [
                    flag('enable', '开启健康检查'),
                    text('host', '检测域名'),
                    text('path', '检测路径'),
                    text('status_code', '正常状态码'),
                    num('interval', '检测间隔（秒）'),
                ]),
            ],
        },
    ],
    origin: [
        {
            title: '回源协议与端口',
            fields: [
                options('backend_protocol', '回源协议', [
                    ['http', 'HTTP'],
                    ['https', 'HTTPS'],
                    ['follow', '跟随访客协议'],
                ]),
                flag('backend_port_mapping', '跟随访客端口'),
                text('backend_http_port', 'HTTP 回源端口'),
                text('backend_https_port', 'HTTPS 回源端口'),
                text('backend_host', '回源 Host'),
            ],
        },
        {
            title: '回源超时与连接复用',
            fields: [
                num('proxy_timeout', '回源超时（秒）'),
                num('proxy_connect_timeout', '连接超时（秒）'),
                flag('ups_keepalive', '回源 Keepalive'),
                num('ups_keepalive_timeout', '空闲超时（秒）'),
            ],
        },
        {
            title: '回源鉴权',
            fields: [
                object('proxy_auth', '对象存储鉴权', [
                    flag('enable', '开启鉴权'),
                    options('type', '服务商', [
                        ['aws_s3', 'AWS S3'],
                        ['aliyun_oss', '阿里云 OSS'],
                        ['qcloud_cos', '腾讯云 COS'],
                    ]),
                    text('bucket_name', '存储桶'),
                    text('region', '区域'),
                    text('access_key_id', 'Access Key ID'),
                    {
                        key: 'access_key_secret',
                        label: 'Access Key Secret',
                        type: 'password',
                    },
                    { key: 'secret_id', label: 'Secret ID', type: 'password' },
                    {
                        key: 'secret_key',
                        label: 'Secret Key',
                        type: 'password',
                    },
                    {
                        key: 'secret_access_key',
                        label: 'Secret Access Key',
                        type: 'password',
                    },
                ]),
            ],
        },
    ],
    https: [
        {
            title: 'HTTPS 证书与端口',
            fields: [
                num('https_listen.cert', '证书'),
                text('https_listen.port', '监听端口'),
            ],
        },
        {
            title: 'HTTPS 增强功能',
            fields: [
                flag('https_listen.force_ssl_enable', '强制 HTTPS'),
                text('https_listen.force_ssl_port', '强制 HTTPS 端口'),
                flag('https_listen.hsts', 'HSTS'),
                flag('https_listen.http2', 'HTTP/2'),
                flag('https_listen.http3', 'HTTP/3'),
                flag('https_listen.ocsp_stapling', 'OCSP Stapling'),
            ],
        },
        {
            title: 'SSL 协议与套件',
            fields: [
                text('https_listen.ssl_protocols', 'SSL 协议'),
                area('https_listen.ssl_ciphers', 'SSL 套件'),
                flag(
                    'https_listen.ssl_prefer_server_ciphers',
                    '优先服务器套件',
                ),
            ],
        },
    ],
    cache: [
        {
            title: '缓存规则',
            help: '按顺序匹配。可使用上移、下移调整优先级。',
            fields: [
                array(
                    'proxy_cache',
                    '缓存规则',
                    [
                        options('type', '匹配范围', [
                            ['index', '首页'],
                            ['all', '全站'],
                            ['dir', '目录'],
                            ['suffix', '后缀'],
                            ['full_path', '单个路径'],
                        ]),
                        text('content', '匹配内容'),
                        num('expire', '有效期'),
                        options('unit', '时间单位', [
                            ['s', '秒'],
                            ['m', '分钟'],
                            ['h', '小时'],
                            ['d', '天'],
                        ]),
                        flag('ignore_arg', '忽略 URL 参数'),
                        text('proxy_ignore_headers', '忽略源站缓存响应头'),
                        flag('range', '分片回源'),
                        array('no_cache', '不缓存条件', [
                            text('variable', '变量'),
                            text('string', '匹配内容'),
                        ]),
                    ],
                    {
                        type: 'suffix',
                        content: '',
                        expire: 1,
                        unit: 'd',
                        ignore_arg: 0,
                        proxy_ignore_headers: '',
                        range: 0,
                        no_cache: [],
                    },
                ),
            ],
        },
    ],
    cc: [
        {
            title: 'CC 防护',
            fields: [
                num('cc_default_rule', '默认防护规则'),
                object('cc_switch', '自动切换', [
                    flag('enable', '开启自动切换'),
                    num('switch', 'QPS 阈值'),
                    num('rule', '切换规则'),
                ]),
                array(
                    'extra_cc_rule',
                    '自定义规则',
                    [
                        text('des', '规则说明'),
                        flag('enable', '启用'),
                        options('mode', '匹配模式', [
                            ['continue', '继续匹配'],
                            ['break', '停止匹配'],
                        ]),
                        array('matcher', '匹配条件', matchFields),
                        object('filter', '处理规则', [
                            options('type', '处理类型', [
                                ['req_rate', '请求频率限制'],
                                ['browser_verify_auto', '自动浏览器验证'],
                                ['delay_jump_filter', '延迟跳转'],
                                ['click_filter', '点击验证'],
                                ['easy_click_filter', '简易点击验证'],
                                ['slide_filter', '滑动验证'],
                                ['easy_slide_filter', '简易滑动验证'],
                                ['captcha_filter', '验证码'],
                                ['rotate_filter', '旋转验证'],
                                ['302_challenge', '302 验证'],
                                ['url_auth', 'URL 鉴权'],
                            ]),
                            num('within_second', '统计窗口（秒）'),
                            num('max_challenge', '最多挑战次数'),
                            num('max_per_uri', '单 URI 次数限制'),
                            object('extra', 'URL 鉴权配置', [
                                flag('ip', '绑定客户端 IP'),
                                {
                                    key: 'key',
                                    label: '鉴权密钥',
                                    type: 'password',
                                },
                                text('mode', '鉴权模式'),
                                text('sign_name', '签名参数'),
                                text('time_name', '时间参数'),
                                num('time_diff', '时间有效期（秒）'),
                                num('sign_use_times', '签名可用次数'),
                            ]),
                        ]),
                    ],
                    {
                        des: '',
                        enable: 1,
                        mode: 'continue',
                        matcher: [],
                        filter: {
                            type: 'req_rate',
                            within_second: 10,
                            max_challenge: 100,
                            max_per_uri: 20,
                            extra: {},
                        },
                    },
                ),
            ],
        },
        {
            title: '黑白名单与访问限制',
            fields: [
                area('black_ip', '黑名单 IP'),
                area('white_ip', '白名单 IP'),
                num('block_time', '黑名单有效期（秒）'),
                num('white_time', '白名单有效期（秒）'),
                flag('block_proxy', '透明代理拦截'),
                area('block_region', '屏蔽区域'),
                text('cookie_domain', '验证 Cookie 域名'),
            ],
        },
    ],
    waf: [
        {
            title: '站点 WAF',
            fields: [
                flag('waf_enable', 'WAF 总开关'),
                object('waf_ip_auto_block', '攻击自动封禁', [
                    flag('enable', '开启自动封禁'),
                    num('window_seconds', '统计窗口（秒）'),
                    num('hit_threshold', '命中阈值'),
                    num('block_seconds', '封禁时长（秒）'),
                ]),
            ],
        },
        {
            title: '内置模块防护',
            fields: [
                object(
                    'waf',
                    '检测模块',
                    Object.entries({
                        sqli: 'SQL 注入',
                        xss: 'XSS',
                        upload: '文件上传',
                        fi: '文件包含',
                        cmdi: '命令注入',
                        java: 'JAVA 代码注入',
                        jdeser: 'JAVA 反序列化',
                        php: 'PHP 代码注入',
                        phpdeser: 'PHP 反序列化',
                        ssti: '模板注入',
                    }).map(([key, label]) =>
                        options(key, label, [
                            ['off', '禁用'],
                            ['observe', '观察'],
                            ['protect', '防护'],
                        ]),
                    ),
                ),
            ],
        },
        {
            title: '规则库与放行规则',
            help: '放行规则优先于后续 WAF 检测。',
            fields: [
                array(
                    'waf_allow_rule',
                    '放行规则',
                    [
                        text('name', '规则名称'),
                        flag('enable', '启用'),
                        text('tz_offset', '时区偏移'),
                        array(
                            'matcher_groups',
                            '匹配组',
                            [
                                array('matcher', '条件', wafMatchFields, {
                                    field: 'uri',
                                    op: 'contain',
                                    transform: 'none',
                                    value: '',
                                }),
                            ],
                            { matcher: [] },
                        ),
                    ],
                    {
                        name: '',
                        enable: 1,
                        tz_offset: '+08:00',
                        action: 'allow',
                        action_config: {},
                        matcher_groups: [],
                    },
                ),
            ],
        },
    ],
    advanced: [
        {
            title: '上传大小与访问策略',
            fields: [
                text('post_size_limit', '上传大小限制'),
                object('hotlink', '防盗链', [
                    flag('enable', '开启防盗链'),
                    area('domain', '允许来源'),
                    flag('allow_empty', '允许空 Referer'),
                    text('scope_type', '匹配类型'),
                    text('scope_content', '匹配内容'),
                ]),
                object('cors', '跨域 CORS', [
                    flag('enable', '开启跨域'),
                    text('allow_origin', '允许来源'),
                    text('allow_methods', '允许方法'),
                    text('allow_headers', '允许请求头'),
                    text('expose_headers', '暴露响应头'),
                    flag('allow_credentials', '允许凭据'),
                    num('max_age', '预检缓存（秒）'),
                ]),
            ],
        },
        {
            title: '性能与协议',
            fields: [
                flag('gzip_enable', 'Gzip 压缩'),
                flag('websocket_enable', 'WebSocket'),
                text('spider_to_sip', '搜索引擎回源 IP'),
                flag('recv_real_time', '数据实时返回'),
                flag('send_real_time', '数据实时发送'),
            ],
        },
        {
            title: '自定义页面与转发',
            fields: [
                ...[403, 404, 500, 502, 504].map((code) =>
                    area(`page_${code}`, `${code} 错误页面`),
                ),
                array(
                    'url_rewrite',
                    'URL 转向',
                    [
                        text('match', '匹配路径'),
                        text('redirect', '目标地址'),
                        options('code', '转向类型', [
                            ['301', '301 永久跳转'],
                            ['302', '302 临时跳转'],
                            ['307', '307 保持请求方法'],
                            ['internal', 'URI 重写'],
                        ]),
                        ...Object.entries({
                            host: '域名',
                            user_agent: 'User Agent',
                            referer: 'Referer',
                            country_code: '国家代码',
                            accept_language: '语言',
                            province: '省份',
                            city: '城市',
                            isp: '运营商',
                            asnumber: 'ASN',
                        }).map(([key, label]) => text(key, `${label}（正则）`)),
                    ],
                    {
                        match: '(.*)',
                        redirect: '',
                        code: '301',
                        host: '.*',
                        user_agent: '.*',
                        referer: '.*',
                        country_code: '.*',
                        accept_language: '.*',
                        province: '.*',
                        city: '.*',
                        isp: '.*',
                        asnumber: '.*',
                    },
                ),
            ],
        },
        {
            title: 'Header 设置',
            fields: [
                array('req_header', '回源请求头', headerFields, {
                    name: '',
                    value: '',
                    action: 'add',
                    allow_repeat: false,
                }),
                array('resp_header', '客户端响应头', headerFields, {
                    name: '',
                    value: '',
                    action: 'add',
                    allow_repeat: false,
                }),
            ],
        },
        {
            title: '日志记录',
            fields: [
                flag('log_req_header', '记录请求头'),
                flag('log_resp_header', '记录响应头'),
                flag('log_req_body', '记录请求体'),
                num('log_req_body_max_size', '请求体记录大小'),
            ],
        },
        {
            title: '其他',
            fields: [
                flag('acme_proxy_to_orgin', '源站申请证书'),
                flag('is_default_server', '默认站点'),
                options('l2_state', 'L2 配置', [
                    ['inherit', '使用套餐配置'],
                    ['off', '不配置 L2'],
                    ['custom', '自定义 L2'],
                ]),
                num('l2_config_id', 'L2 配置 ID'),
            ],
        },
    ],
};
export const editableSiteKeys = [
    ...new Set(
        Object.values(siteSections).flatMap((sections) =>
            sections.flatMap((section) =>
                section.fields.map((field) => field.key.split('.')[0]),
            ),
        ),
    ),
];
