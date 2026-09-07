export type ConsoleMetric = {
    label: string;
    value: string;
    detail: string;
};

export type ConsoleSection = {
    title: string;
    description: string;
    endpoint: string;
    items: string[];
};

export type ConsoleRow = {
    name: string;
    type: string;
    status: string;
    metric: string;
    endpoint: string;
};

export type ConsoleModule = {
    eyebrow: string;
    title: string;
    description: string;
    primaryAction: string;
    secondaryAction: string;
    metrics: ConsoleMetric[];
    sections: ConsoleSection[];
    rows: ConsoleRow[];
};

export type SiteTab = {
    key: string;
    label: string;
    title: string;
    description: string;
    endpoint: string;
    fields: string[];
    checks: string[];
};

const baseMetrics: ConsoleMetric[] = [
    { label: '今日请求', value: '1.28M', detail: '较昨日同口径 +8.6%' },
    { label: '今日流量', value: '642 GB', detail: '95 计费带宽 186 Mbps' },
    { label: '可用站点', value: '12', detail: '2 个站点存在待处理配置' },
];

const makeModule = (
    module: Omit<ConsoleModule, 'metrics'> & { metrics?: ConsoleMetric[] },
): ConsoleModule => ({
    ...module,
    metrics: module.metrics ?? [],
});

export const consoleModules: Record<string, ConsoleModule> = {
    sites: makeModule({
        eyebrow: '用户端 / 网站管理',
        title: '网站列表',
        description:
            '按 CDNfly 站点、站点分组接口组织域名接入、状态同步和站点入口。',
        primaryAction: '创建网站',
        secondaryAction: '同步站点',
        sections: [
            {
                title: '站点接入',
                description: '域名、站点类型、套餐归属和启停状态。',
                endpoint: 'GET/POST /v1/sites',
                items: ['域名校验', '套餐可用性', '回源协议', '站点状态'],
            },
            {
                title: '站点分组',
                description: '给站点做业务线、客户或环境维度分组。',
                endpoint: 'GET/POST /v1/site-groups',
                items: ['分组筛选', '批量移动', '默认分组', '权限隔离'],
            },
        ],
        rows: [
            {
                name: 'www.example.com',
                type: '静态网站',
                status: '运行中',
                metric: '命中率 92.4%',
                endpoint: '/v1/sites/10001',
            },
            {
                name: 'api.example.com',
                type: 'API 加速',
                status: '待配置',
                metric: '回源 38 ms',
                endpoint: '/v1/sites/10002',
            },
            {
                name: 'img.example.com',
                type: '图片分发',
                status: '运行中',
                metric: '今日 214 GB',
                endpoint: '/v1/sites/10003',
            },
        ],
    }),
    'site-groups': makeModule({
        eyebrow: '用户端 / 网站管理',
        title: '网站分组',
        description: '维护 CDNfly 站点分组，用于筛选、批量配置和运营看板聚合。',
        primaryAction: '新建分组',
        secondaryAction: '批量整理',
        sections: [
            {
                title: '分组规则',
                description: '将站点按客户、区域、业务或环境归类。',
                endpoint: 'GET/POST /v1/site-groups',
                items: ['业务分组', '测试分组', '客户分组', '默认策略'],
            },
            {
                title: '批量操作',
                description: '后续和站点列表联动，减少重复配置。',
                endpoint: 'PUT /v1/site-groups/{id}',
                items: ['批量移动', '批量停用', '批量同步', '变更审计'],
            },
        ],
        rows: [
            {
                name: '生产业务',
                type: '默认组',
                status: '12 个站点',
                metric: '今日 1.2M 请求',
                endpoint: '/v1/site-groups/1',
            },
            {
                name: '测试环境',
                type: '测试组',
                status: '3 个站点',
                metric: '今日 8.4K 请求',
                endpoint: '/v1/site-groups/2',
            },
        ],
    }),
    certificates: makeModule({
        eyebrow: '用户端 / 证书管理',
        title: '证书列表',
        description: '集中管理上传证书、申请证书和绑定站点。',
        primaryAction: '上传证书',
        secondaryAction: '申请证书',
        sections: [
            {
                title: '证书资产',
                description: '证书名称、域名范围、过期时间和绑定站点。',
                endpoint: 'GET/POST /v1/certs',
                items: ['证书上传', '到期提醒', '站点绑定', '证书校验'],
            },
            {
                title: '自动申请',
                description: '结合 DNS API 做自动签发和续期。',
                endpoint: 'GET/POST /v1/dnsapis',
                items: ['DNS API', '域名验证', '自动续期', '失败重试'],
            },
        ],
        rows: [
            {
                name: '*.example.com',
                type: '通配符证书',
                status: '正常',
                metric: '86 天后到期',
                endpoint: '/v1/certs/9001',
            },
            {
                name: 'api.example.com',
                type: '单域名证书',
                status: '待续期',
                metric: '12 天后到期',
                endpoint: '/v1/certs/9002',
            },
        ],
    }),
    dnsapis: makeModule({
        eyebrow: '用户端 / 证书管理',
        title: 'DNS API',
        description: '维护证书自动签发所需的 DNS 服务商凭据。',
        primaryAction: '新增 DNS API',
        secondaryAction: '测试解析',
        sections: [
            {
                title: '服务商凭据',
                description: '用于 ACME DNS-01 校验，不在前端暴露密钥明文。',
                endpoint: 'GET/POST /v1/dnsapis',
                items: ['服务商', 'Access Key', '默认线路', '连通性测试'],
            },
            {
                title: '签发联动',
                description: '证书申请失败时展示 DNS 校验日志和重试入口。',
                endpoint: 'POST /v1/certs',
                items: ['TXT 记录', '签发状态', '续期任务', '错误摘要'],
            },
        ],
        rows: [
            {
                name: 'Cloudflare 主账号',
                type: 'cloudflare',
                status: '可用',
                metric: '绑定 4 张证书',
                endpoint: '/v1/dnsapis/1',
            },
        ],
    }),
    cache: makeModule({
        eyebrow: '用户端 / 缓存管理',
        title: '缓存规则',
        description: '维护站点缓存策略、缓存键和忽略参数规则。',
        primaryAction: '新增规则',
        secondaryAction: '查看站点',
        sections: [
            {
                title: '缓存策略',
                description: '通过站点配置落到 CDNfly 的缓存规则字段。',
                endpoint: 'GET/PUT /v1/sites/{id}',
                items: ['文件后缀 TTL', '目录 TTL', '忽略参数', '状态码缓存'],
            },
            {
                title: '缓存键',
                description: '明确 Query、Cookie、Header 是否参与缓存。',
                endpoint: 'GET/PUT /v1/sites/{id}',
                items: [
                    'Query String',
                    'Cookie 白名单',
                    'Header 白名单',
                    'Vary 规则',
                ],
            },
        ],
        rows: [
            {
                name: '静态资源 30 天',
                type: '后缀规则',
                status: '启用',
                metric: '.js .css .png',
                endpoint: '/v1/sites/{id}',
            },
            {
                name: 'API 不缓存',
                type: '路径规则',
                status: '启用',
                metric: '/api/*',
                endpoint: '/v1/sites/{id}',
            },
        ],
    }),
    'cache-jobs': makeModule({
        eyebrow: '用户端 / 缓存管理',
        title: '刷新预热',
        description: '提交 URL、目录或站点级刷新预热任务，并追踪执行结果。',
        primaryAction: '提交刷新',
        secondaryAction: '提交预热',
        sections: [
            {
                title: '刷新任务',
                description: 'URL、目录、站点维度刷新 CDN 节点缓存。',
                endpoint: 'GET/POST /v1/jobs',
                items: ['URL 刷新', '目录刷新', '站点刷新', '任务状态'],
            },
            {
                title: '预热任务',
                description: '提前拉取热点资源，降低首次访问回源。',
                endpoint: 'GET/POST /v1/jobs',
                items: ['URL 预热', '并发限制', '失败重试', '完成率'],
            },
        ],
        rows: [
            {
                name: '刷新 /assets/app.js',
                type: 'URL 刷新',
                status: '完成',
                metric: '18/18 节点',
                endpoint: '/v1/jobs/5001',
            },
        ],
    }),
    'security-acls': makeModule({
        eyebrow: '用户端 / 安全防护',
        title: 'ACL 规则',
        description: '管理访问控制规则，并在站点安全配置中绑定。',
        primaryAction: '新增 ACL',
        secondaryAction: '导入规则',
        sections: [
            {
                title: '访问控制',
                description: '按 IP、地区、Referer、UA、URL 等维度放行或拦截。',
                endpoint: 'GET/POST /v1/acls',
                items: ['IP 黑白名单', '地区限制', 'Referer 防盗链', 'UA 规则'],
            },
            {
                title: '站点绑定',
                description: '将 ACL 规则组绑定到具体站点。',
                endpoint: 'PUT /v1/sites/{id}',
                items: ['绑定策略', '灰度启用', '命中日志', '回滚配置'],
            },
        ],
        rows: [
            {
                name: '默认防盗链',
                type: 'Referer',
                status: '启用',
                metric: '命中 1.4K',
                endpoint: '/v1/acls/3001',
            },
        ],
    }),
    'security-cc': makeModule({
        eyebrow: '用户端 / 安全防护',
        title: 'CC 防护',
        description: '按 CDNfly CC 规则、匹配器和过滤器组织防护策略。',
        primaryAction: '新增规则',
        secondaryAction: '查看命中',
        sections: [
            {
                title: '规则组',
                description: '定义频率、动作和统计周期。',
                endpoint: 'GET/POST /v1/cc-rules',
                items: ['访问频率', '封禁时长', '验证码', '观察模式'],
            },
            {
                title: '匹配条件',
                description: '组合 URI、IP、UA、Header 等匹配器。',
                endpoint: 'GET/POST /v1/cc-matchs',
                items: ['URI 匹配', 'Header 匹配', 'IP 条件', '条件组合'],
            },
            {
                title: '过滤动作',
                description: '配置放行、拦截、限速和挑战动作。',
                endpoint: 'GET/POST /v1/cc-filters',
                items: ['Block', 'Pass', 'Limit', 'Challenge'],
            },
        ],
        rows: [
            {
                name: '登录接口保护',
                type: 'CC 规则',
                status: '观察',
                metric: '60 秒 120 次',
                endpoint: '/v1/cc-rules/7001',
            },
        ],
    }),
    'security-blackip': makeModule({
        eyebrow: '用户端 / 安全防护',
        title: '黑名单 IP',
        description: '查看站点维度被防护规则封禁的 IP 和释放状态。',
        primaryAction: '查询 IP',
        secondaryAction: '导出记录',
        sections: [
            {
                title: '封禁记录',
                description: '按站点、规则、时间和封禁来源追踪。',
                endpoint: 'GET /v1/monitor/site/blackip',
                items: ['封禁 IP', '命中规则', '剩余时间', '手动释放'],
            },
        ],
        rows: [
            {
                name: '203.0.113.24',
                type: 'CC 命中',
                status: '封禁中',
                metric: '剩余 8 分钟',
                endpoint: '/v1/monitor/site/blackip',
            },
        ],
    }),
    'analytics-realtime': makeModule({
        eyebrow: '用户端 / 访问数据',
        title: '实时统计',
        description: '站点 QPS、带宽、命中率、状态码和回源趋势。',
        primaryAction: '选择站点',
        secondaryAction: '导出图表',
        metrics: baseMetrics,
        sections: [
            {
                title: '实时曲线',
                description: '按分钟聚合请求数、流量和带宽。',
                endpoint: 'GET /v1/monitor/site/realtime',
                items: ['QPS', '带宽', '命中率', '状态码'],
            },
            {
                title: '用量查询',
                description: '套餐用量和账单口径用量查询。',
                endpoint: 'GET /v1/monitor/usage',
                items: ['流量', '带宽', '请求数', '时间范围'],
            },
        ],
        rows: [
            {
                name: 'www.example.com',
                type: '实时数据',
                status: '正常',
                metric: '186 Mbps',
                endpoint: '/v1/monitor/site/realtime',
            },
        ],
    }),
    'analytics-top': makeModule({
        eyebrow: '用户端 / 访问数据',
        title: '资源排行',
        description: '查看 URL、地区、IP、Referer、UA 等访问排行。',
        primaryAction: '切换维度',
        secondaryAction: '导出 CSV',
        sections: [
            {
                title: 'Top 资源',
                description: '定位大流量文件、异常请求和热点路径。',
                endpoint: 'GET /v1/monitor/site/top',
                items: ['URL Top', 'IP Top', '地区 Top', 'Referer Top'],
            },
        ],
        rows: [
            {
                name: '/assets/video.mp4',
                type: 'URL Top',
                status: '热资源',
                metric: '今日 96 GB',
                endpoint: '/v1/monitor/site/top',
            },
        ],
    }),
    'analytics-logs': makeModule({
        eyebrow: '用户端 / 访问数据',
        title: '访问日志',
        description: '查询 CDNfly 站点访问日志，用于排障和安全追踪。',
        primaryAction: '查询日志',
        secondaryAction: '下载日志',
        sections: [
            {
                title: '日志检索',
                description: '按站点、状态码、IP、路径和时间范围过滤。',
                endpoint: 'GET /v1/monitor/site/access-log',
                items: ['时间范围', '状态码', '客户端 IP', 'URL 关键字'],
            },
        ],
        rows: [
            {
                name: '198.51.100.8 GET /api/v1/user',
                type: '200',
                status: '命中缓存',
                metric: '12 ms',
                endpoint: '/v1/monitor/site/access-log',
            },
        ],
    }),
    'analytics-usage': makeModule({
        eyebrow: '用户端 / 访问数据',
        title: '用量查询',
        description: '展示套餐流量、带宽、请求数用量和超额风险。',
        primaryAction: '切换周期',
        secondaryAction: '导出用量',
        sections: [
            {
                title: '套餐用量',
                description: '和用户套餐、订单计费口径联动。',
                endpoint: 'GET /v1/monitor/usage',
                items: ['流量用量', '带宽峰值', '请求数', '超额阈值'],
            },
        ],
        rows: [
            {
                name: '标准版套餐',
                type: '月度用量',
                status: '正常',
                metric: '642 GB / 2 TB',
                endpoint: '/v1/monitor/usage',
            },
        ],
    }),
    streams: makeModule({
        eyebrow: '用户端 / 四层转发',
        title: '转发列表',
        description: '管理 TCP/UDP 四层转发、分组和可用性。',
        primaryAction: '创建转发',
        secondaryAction: '同步转发',
        sections: [
            {
                title: '转发服务',
                description: '源端口、目标地址、协议和节点线路。',
                endpoint: 'GET/POST /v1/streams',
                items: ['TCP', 'UDP', '端口映射', '健康检查'],
            },
            {
                title: '转发分组',
                description: '按业务或客户组织四层转发。',
                endpoint: 'GET/POST /v1/stream-groups',
                items: ['分组筛选', '批量启停', '线路配置', '状态同步'],
            },
        ],
        rows: [
            {
                name: 'game.example.com:443',
                type: 'TCP',
                status: '运行中',
                metric: '46 Mbps',
                endpoint: '/v1/streams/6001',
            },
        ],
    }),
    'streams-analytics': makeModule({
        eyebrow: '用户端 / 四层转发',
        title: '转发统计',
        description: '查看四层转发实时流量、连接数和排行。',
        primaryAction: '选择转发',
        secondaryAction: '导出数据',
        sections: [
            {
                title: '实时统计',
                description: '连接数、入出流量和节点状态。',
                endpoint: 'GET /v1/monitor/stream/realtime',
                items: ['连接数', '带宽', '丢包', '节点延迟'],
            },
            {
                title: '资源排行',
                description: '按目标、客户端或线路统计 Top 数据。',
                endpoint: 'GET /v1/monitor/stream/top',
                items: ['客户端 Top', '端口 Top', '线路 Top', '异常排行'],
            },
        ],
        rows: [
            {
                name: 'game.example.com:443',
                type: '实时',
                status: '正常',
                metric: '812 连接',
                endpoint: '/v1/monitor/stream/realtime',
            },
        ],
    }),
    'billing-packages': makeModule({
        eyebrow: '用户端 / 套餐账单',
        title: '在售套餐',
        description: '展示 CDNfly 套餐组、套餐和升级包，发起本地订单支付。',
        primaryAction: '购买套餐',
        secondaryAction: '查看升级包',
        sections: [
            {
                title: '套餐组',
                description: '面向用户展示可售套餐分组。',
                endpoint: 'GET /v1/package-groups',
                items: ['基础套餐', '企业套餐', '地域套餐', '排序展示'],
            },
            {
                title: '套餐与升级包',
                description: '购买前锁定服务端价格，禁止前端传价格和 user_id。',
                endpoint: 'GET /v1/packages, GET /v1/package-ups',
                items: ['服务端计价', '支付订单', '升级包', '开通回调'],
            },
        ],
        rows: [
            {
                name: '标准版',
                type: '月付套餐',
                status: '可购买',
                metric: '2 TB 流量',
                endpoint: '/v1/packages/101',
            },
        ],
    }),
    'billing-subscriptions': makeModule({
        eyebrow: '用户端 / 套餐账单',
        title: '我的套餐',
        description: '查看已开通套餐、用量、过期时间和续费入口。',
        primaryAction: '续费套餐',
        secondaryAction: '查看用量',
        sections: [
            {
                title: '用户套餐',
                description: '当前用户拥有的套餐实例。',
                endpoint: 'GET /v1/user-packages',
                items: ['套餐状态', '到期时间', '绑定站点', '剩余用量'],
            },
            {
                title: '套餐用量',
                description: '按套餐维度查询流量和带宽使用情况。',
                endpoint: 'GET /v1/user-package/{id}/usage',
                items: ['已用流量', '剩余流量', '峰值带宽', '超额预警'],
            },
        ],
        rows: [
            {
                name: '标准版 #UP-202604',
                type: '用户套餐',
                status: '有效',
                metric: '2026-05-26 到期',
                endpoint: '/v1/user-packages/8001',
            },
        ],
    }),
    'billing-orders': makeModule({
        eyebrow: '用户端 / 套餐账单',
        title: '订单记录',
        description: '展示套餐订单、支付状态和开通结果。',
        primaryAction: '创建订单',
        secondaryAction: '刷新状态',
        sections: [
            {
                title: '订单查询',
                description: '本地订单和 CDNfly 套餐订单状态对齐。',
                endpoint: 'GET /v1/orders, GET /api/orders/{orderNo}',
                items: ['待支付', '已支付', '开通中', '开通失败原因'],
            },
            {
                title: '支付对账',
                description: '区分支付失败和通讯 CDNfly 后端开通失败。',
                endpoint: 'POST /api/payments/epusdt/notify',
                items: ['金额匹配', '订单归属', '回调签名', '补偿开通'],
            },
        ],
        rows: [
            {
                name: '202604251777127270931428',
                type: 'USDT',
                status: '待支付',
                metric: '20.0500 USDT',
                endpoint: '/api/orders/202604251777127270931428',
            },
        ],
    }),
    messages: makeModule({
        eyebrow: '用户端 / 消息中心',
        title: '消息列表',
        description: '汇总系统消息、套餐提醒、安全提醒和工单通知。',
        primaryAction: '全部已读',
        secondaryAction: '消息筛选',
        sections: [
            {
                title: '站内消息',
                description: '按 CDNfly 消息接口拉取用户消息。',
                endpoint: 'GET /v1/messages',
                items: ['未读消息', '系统通知', '套餐提醒', '安全告警'],
            },
            {
                title: '阅读状态',
                description: '标记单条或批量消息为已读。',
                endpoint: 'POST /v1/messages/read',
                items: ['单条已读', '批量已读', '未读统计', '消息归档'],
            },
        ],
        rows: [
            {
                name: '套餐即将到期',
                type: '账单提醒',
                status: '未读',
                metric: '3 天前',
                endpoint: '/v1/messages/1',
            },
        ],
    }),
    'message-subscriptions': makeModule({
        eyebrow: '用户端 / 消息中心',
        title: '订阅设置',
        description: '配置站内、邮件或后续 Webhook 类型的消息订阅。',
        primaryAction: '保存订阅',
        secondaryAction: '发送测试',
        sections: [
            {
                title: '订阅项',
                description: '选择接收套餐、支付、安全、站点状态类消息。',
                endpoint: 'GET/POST /v1/messages/sub',
                items: ['套餐提醒', '支付通知', '安全告警', '站点异常'],
            },
        ],
        rows: [
            {
                name: '支付和开通通知',
                type: '站内信',
                status: '已订阅',
                metric: '实时发送',
                endpoint: '/v1/messages/sub',
            },
        ],
    }),
    'account-profile': makeModule({
        eyebrow: '用户端 / 账户设置',
        title: '用户信息',
        description: '管理当前用户资料、绑定邮箱和默认偏好。',
        primaryAction: '保存资料',
        secondaryAction: '刷新资料',
        sections: [
            {
                title: '用户资料',
                description: '展示 CDNfly 当前用户信息，只允许修改自己的资料。',
                endpoint: 'GET/PUT /v1/user',
                items: ['昵称', '邮箱', '手机号', '安全状态'],
            },
            {
                title: '默认配置',
                description: '站点默认配置、告警偏好和控制台偏好。',
                endpoint: 'GET/PUT /v1/user-configs',
                items: ['默认套餐', '默认源站', '消息偏好', '展示偏好'],
            },
        ],
        rows: [
            {
                name: '当前登录用户',
                type: 'Profile',
                status: '已验证',
                metric: 'API Key 已同步',
                endpoint: '/v1/user',
            },
        ],
    }),
    'account-certification': makeModule({
        eyebrow: '用户端 / 账户设置',
        title: '实名认证',
        description: '展示并提交 CDNfly 用户实名认证状态。',
        primaryAction: '提交认证',
        secondaryAction: '查看状态',
        sections: [
            {
                title: '认证信息',
                description: '企业或个人实名资料。',
                endpoint: 'GET/POST /v1/user/certify',
                items: ['认证类型', '证件信息', '审核状态', '驳回原因'],
            },
        ],
        rows: [
            {
                name: '企业认证',
                type: '主体信息',
                status: '待提交',
                metric: '未影响测试环境',
                endpoint: '/v1/user/certify',
            },
        ],
    }),
    'account-api-key': makeModule({
        eyebrow: '用户端 / 账户设置',
        title: 'API Key',
        description: '展示 API Key 状态和重置入口，密钥明文不在前端持久化。',
        primaryAction: '重新同步',
        secondaryAction: '查看权限',
        sections: [
            {
                title: '密钥状态',
                description: '当前用户 CDNfly API Key 的可用性。',
                endpoint: 'GET /v1/api-key',
                items: ['Key ID', '权限范围', '最后同步', '连通性'],
            },
            {
                title: '安全限制',
                description: '前端不能传 user_id、role、status 等越权字段。',
                endpoint: 'POST /api/auth/retry-api-key',
                items: ['服务端归属', '密钥轮换', '审计日志', '最小权限'],
            },
        ],
        rows: [
            {
                name: 'CDNfly 用户 API Key',
                type: '系统托管',
                status: '可用',
                metric: '最后同步 2 小时前',
                endpoint: '/v1/api-key',
            },
        ],
    }),
    'account-login-logs': makeModule({
        eyebrow: '用户端 / 账户设置',
        title: '登录日志',
        description: '查看账号登录、失败尝试和设备来源。',
        primaryAction: '筛选日志',
        secondaryAction: '导出记录',
        sections: [
            {
                title: '登录记录',
                description: '按 IP、时间和设备查询登录日志。',
                endpoint: 'GET /v1/log/login',
                items: ['登录 IP', '登录时间', '设备信息', '异常标记'],
            },
        ],
        rows: [
            {
                name: '127.0.0.1 Chrome',
                type: '本地登录',
                status: '成功',
                metric: '刚刚',
                endpoint: '/v1/log/login',
            },
        ],
    }),
    'admin-overview': makeModule({
        eyebrow: '管理端 / 概览',
        title: '管理概览',
        description: '面向管理员汇总用户、站点、套餐销售、节点和告警。',
        primaryAction: '刷新全局数据',
        secondaryAction: '查看告警',
        metrics: [
            { label: '用户数', value: '128', detail: '今日新增 6' },
            { label: '站点数', value: '342', detail: '运行中 318' },
            { label: '节点状态', value: '24/25', detail: '1 个节点待检查' },
        ],
        sections: [
            {
                title: '平台聚合',
                description: '聚合用户、套餐、站点和节点数据。',
                endpoint: 'GET /v1/users, /v1/sites, /v1/nodes',
                items: ['用户趋势', '站点趋势', '套餐销售', '节点健康'],
            },
            {
                title: '运营告警',
                description: '展示高风险订单、开通失败和节点异常。',
                endpoint: 'GET /v1/monitor/*',
                items: ['节点离线', '开通失败', '支付异常', '流量突增'],
            },
        ],
        rows: [
            {
                name: '节点 cn-hk-01',
                type: '节点告警',
                status: '待检查',
                metric: '丢包升高',
                endpoint: '/v1/nodes/1',
            },
        ],
    }),
    'admin-users': makeModule({
        eyebrow: '管理端 / 用户管理',
        title: '用户管理',
        description: '管理平台用户、同步 API Key、充值和用户套餐。',
        primaryAction: '新建用户',
        secondaryAction: '同步 API Key',
        sections: [
            {
                title: '用户列表',
                description: '本地用户和 CDNfly 用户映射。',
                endpoint: 'GET /v1/users, GET /api/admin/users',
                items: ['用户信息', '角色状态', 'API Key 状态', '注册来源'],
            },
            {
                title: '充值与套餐',
                description: '管理员给用户充值或分配套餐。',
                endpoint: 'POST /v1/user/{id}/recharge, GET /v1/user-packages',
                items: ['余额充值', '套餐分配', '开通记录', '操作审计'],
            },
        ],
        rows: [
            {
                name: 'user@example.com',
                type: '普通用户',
                status: '正常',
                metric: '余额 120.00',
                endpoint: '/api/admin/users/1',
            },
        ],
    }),
    'admin-packages': makeModule({
        eyebrow: '管理端 / 套餐管理',
        title: '套餐管理',
        description: '维护套餐组、基础套餐、升级包和用户套餐。',
        primaryAction: '创建套餐',
        secondaryAction: '创建升级包',
        sections: [
            {
                title: '套餐配置',
                description: '套餐价格、流量、带宽、站点数和有效期。',
                endpoint: 'GET/POST /v1/packages',
                items: ['套餐价格', '资源额度', '有效期', '上下架'],
            },
            {
                title: '套餐组与升级包',
                description: '配置售卖分组和可购买升级包。',
                endpoint: 'GET/POST /v1/package-groups, /v1/package-ups',
                items: ['套餐组', '升级包', '排序', '展示控制'],
            },
        ],
        rows: [
            {
                name: '标准版',
                type: '基础套餐',
                status: '上架',
                metric: '2 TB / 月',
                endpoint: '/v1/packages/101',
            },
        ],
    }),
    'admin-sites': makeModule({
        eyebrow: '管理端 / 网站管理',
        title: '全部网站',
        description: '管理员查看和处理所有用户站点、证书、ACL、CC 和任务。',
        primaryAction: '全局搜索',
        secondaryAction: '导出站点',
        sections: [
            {
                title: '全部站点',
                description: '按用户、状态、套餐和节点筛选。',
                endpoint: 'GET /v1/sites',
                items: ['用户归属', '站点状态', '套餐绑定', '批量操作'],
            },
            {
                title: '配置资产',
                description: '证书、ACL、CC、刷新预热任务统一排查。',
                endpoint: 'GET /v1/certs, /v1/acls, /v1/jobs',
                items: ['证书', 'ACL', 'CC', '刷新预热'],
            },
        ],
        rows: [
            {
                name: 'www.example.com',
                type: '用户站点',
                status: '运行中',
                metric: '用户 #1001',
                endpoint: '/v1/sites/10001',
            },
        ],
    }),
    'admin-nodes': makeModule({
        eyebrow: '管理端 / 节点管理',
        title: '节点管理',
        description: '维护 CDNfly 节点、节点组、线路、区域和健康状态。',
        primaryAction: '新增节点',
        secondaryAction: '健康检查',
        sections: [
            {
                title: '节点与节点组',
                description: '管理节点接入、分组和状态。',
                endpoint: 'GET/POST /v1/nodes, /v1/node-groups',
                items: ['节点状态', '节点分组', '负载', '版本'],
            },
            {
                title: '线路与区域',
                description: '维护调度线路和区域配置。',
                endpoint: 'GET/POST /v1/lines, /v1/regions',
                items: ['线路', '区域', '调度策略', '可用性'],
            },
        ],
        rows: [
            {
                name: 'cn-hk-01',
                type: '边缘节点',
                status: '运行中',
                metric: 'CPU 32%',
                endpoint: '/v1/nodes/1',
            },
        ],
    }),
    'admin-dns': makeModule({
        eyebrow: '管理端 / DNS 管理',
        title: 'DNS 管理',
        description: '维护全局 DNS API、线路和解析相关配置。',
        primaryAction: '新增 DNS API',
        secondaryAction: '测试解析',
        sections: [
            {
                title: 'DNS API',
                description: '管理可用于证书签发和解析调度的 DNS 凭据。',
                endpoint: 'GET/POST /v1/dnsapis',
                items: ['服务商', '凭据状态', '默认线路', '解析测试'],
            },
            {
                title: '线路配置',
                description: '维护全局线路、区域和调度策略。',
                endpoint: 'GET/POST /v1/lines',
                items: ['线路名称', '区域绑定', '解析策略', '故障切换'],
            },
        ],
        rows: [
            {
                name: 'Cloudflare 生产',
                type: 'DNS API',
                status: '可用',
                metric: '4 个域名',
                endpoint: '/v1/dnsapis/1',
            },
        ],
    }),
    'admin-streams': makeModule({
        eyebrow: '管理端 / 四层转发',
        title: '四层转发管理',
        description: '管理员管理全局转发、转发分组和转发统计。',
        primaryAction: '全局搜索',
        secondaryAction: '查看统计',
        sections: [
            {
                title: '转发资产',
                description: '跨用户查看转发配置和状态。',
                endpoint: 'GET /v1/streams, /v1/stream-groups',
                items: ['用户归属', '端口配置', '协议', '线路'],
            },
            {
                title: '转发监控',
                description: '实时连接数、流量和排行。',
                endpoint: 'GET /v1/monitor/stream/*',
                items: ['实时流量', '连接数', 'Top 客户端', '异常排行'],
            },
        ],
        rows: [
            {
                name: 'game.example.com:443',
                type: 'TCP',
                status: '运行中',
                metric: '用户 #1001',
                endpoint: '/v1/streams/6001',
            },
        ],
    }),
    'admin-finance': makeModule({
        eyebrow: '管理端 / 财务管理',
        title: '财务管理',
        description: '管理用户充值、套餐订单、支付记录和开通补偿。',
        primaryAction: '订单查询',
        secondaryAction: '手工补偿',
        sections: [
            {
                title: '充值与订单',
                description: '用户充值、本地订单和 CDNfly 套餐订单。',
                endpoint: 'POST /v1/user/{id}/recharge, GET /v1/orders',
                items: ['充值记录', '套餐订单', '支付状态', '开通状态'],
            },
            {
                title: '支付记录',
                description: 'EPUSDT 支付回调、金额匹配和异常订单。',
                endpoint: 'GET /api/orders',
                items: ['待支付', '支付成功', '开通失败', '补偿记录'],
            },
        ],
        rows: [
            {
                name: '202604251777127270931428',
                type: 'USDT',
                status: '待支付',
                metric: '20.0500',
                endpoint: '/api/orders/202604251777127270931428',
            },
        ],
    }),
    'admin-monitoring': makeModule({
        eyebrow: '管理端 / 监控日志',
        title: '监控与日志',
        description: '汇总站点、转发、登录和操作日志。',
        primaryAction: '查询日志',
        secondaryAction: '导出日志',
        sections: [
            {
                title: '业务监控',
                description: '站点与四层转发实时监控。',
                endpoint: 'GET /v1/monitor/site/*, /v1/monitor/stream/*',
                items: ['实时统计', '资源排行', '访问日志', '黑名单'],
            },
            {
                title: '审计日志',
                description: '登录日志和操作日志。',
                endpoint: 'GET /v1/log/login, /v1/log/op',
                items: ['登录日志', '操作日志', '管理员动作', '失败原因'],
            },
        ],
        rows: [
            {
                name: 'admin 更新套餐',
                type: '操作日志',
                status: '成功',
                metric: '刚刚',
                endpoint: '/v1/log/op',
            },
        ],
    }),
    'admin-settings': makeModule({
        eyebrow: '管理端 / 系统配置',
        title: '系统配置',
        description: '维护全局配置、注册信息、充值设置、SMTP 和默认配置。',
        primaryAction: '保存配置',
        secondaryAction: '配置检查',
        sections: [
            {
                title: '全局配置',
                description: 'CDNfly 全局参数和系统默认值。',
                endpoint: 'GET/PUT /v1/configs',
                items: ['全局参数', 'CC 参数', '充值设置', 'SMTP'],
            },
            {
                title: '注册信息',
                description: '注册开关、默认套餐和用户默认配置。',
                endpoint: 'GET /v1/common/register-info, /v1/user-configs',
                items: ['注册开关', '默认套餐', '默认配置', '邮件设置'],
            },
        ],
        rows: [
            {
                name: '注册开关',
                type: '系统配置',
                status: '开启',
                metric: '需上线前复核',
                endpoint: '/v1/common/register-info',
            },
        ],
    }),
    'admin-security': makeModule({
        eyebrow: '管理端 / 安全权限',
        title: '安全与权限',
        description: '管理管理员账号、API Key、操作审计和代理白名单。',
        primaryAction: '权限检查',
        secondaryAction: '导出审计',
        sections: [
            {
                title: '账号权限',
                description: '管理员、普通用户和 API Key 权限。',
                endpoint: 'GET /v1/users, /v1/api-key',
                items: ['管理员账号', '角色', 'API Key', '权限范围'],
            },
            {
                title: '代理安全',
                description: '本地代理接口只允许白名单路径和安全字段。',
                endpoint: 'GET /api/admin/proxy/{path}',
                items: ['路径白名单', '字段黑名单', 'CORS 加固', '操作审计'],
            },
        ],
        rows: [
            {
                name: 'CORS 生产加固',
                type: '安全待办',
                status: '未开始',
                metric: '上线前处理',
                endpoint: '/v1/configs',
            },
        ],
    }),
};

export const fallbackModule: ConsoleModule = makeModule({
    eyebrow: 'Console',
    title: '模块未配置',
    description: '这个控制台入口还没有绑定到具体模块。',
    primaryAction: '返回概览',
    secondaryAction: '查看文档',
    sections: [
        {
            title: '缺少模块数据',
            description: '需要在 consoleData.ts 中补充模块定义。',
            endpoint: '-',
            items: ['页面标题', '接口映射', '表格字段', '操作入口'],
        },
    ],
    rows: [],
});

export const overviewMetrics: ConsoleMetric[] = [
    { label: '当前套餐', value: '标准版', detail: '2026-05-26 到期' },
    { label: '流量用量', value: '642 GB / 2 TB', detail: '剩余额度 68%' },
    { label: '站点请求', value: '1.28M', detail: '缓存命中率 92.4%' },
    { label: '待处理', value: '3', detail: '证书、开通和安全提醒' },
];

export const overviewSections: ConsoleSection[] = [
    {
        title: '套餐与用量',
        description: '展示当前套餐、到期时间、流量、带宽和请求用量。',
        endpoint: 'GET /v1/user-packages, GET /v1/user-package/{id}/usage',
        items: ['套餐名称', '到期时间', '流量余额', '续费入口'],
    },
    {
        title: '网站访问数据',
        description: '按站点聚合实时访问趋势、资源排行和日志入口。',
        endpoint: 'GET /v1/monitor/site/realtime, GET /v1/monitor/site/top',
        items: ['请求趋势', '带宽趋势', '命中率', '状态码'],
    },
    {
        title: '订单与开通状态',
        description: '展示本地订单支付状态和 CDNfly 产品开通状态。',
        endpoint: 'GET /api/orders/{orderNo}, GET /v1/orders',
        items: ['待支付订单', '开通中订单', '失败原因', '补偿入口'],
    },
];

export const siteTabs: SiteTab[] = [
    {
        key: 'overview',
        label: '概览',
        title: '站点概览',
        description: '汇总站点状态、套餐绑定、解析提示和今日访问数据。',
        endpoint: 'GET /v1/sites/{id}, GET /v1/monitor/site/realtime',
        fields: ['站点状态', 'CNAME', '套餐绑定', '今日请求', '命中率'],
        checks: ['确认域名已接入', '确认套餐仍在有效期内', '确认节点同步成功'],
    },
    {
        key: 'basic',
        label: '基本信息',
        title: '基本信息',
        description: '维护站点名称、分组、备注、启停状态和套餐归属。',
        endpoint: 'GET/PUT /v1/sites/{id}',
        fields: ['域名', '站点分组', '备注', '启用状态', '套餐 ID'],
        checks: [
            '禁止前端传 user_id',
            '保存前校验站点归属',
            '变更写入操作日志',
        ],
    },
    {
        key: 'http',
        label: 'HTTP',
        title: 'HTTP 设置',
        description: '配置 HTTP 访问、压缩、协议行为和客户端连接参数。',
        endpoint: 'PUT /v1/sites/{id}',
        fields: [
            'HTTP/2',
            'Gzip/Brotli',
            'WebSocket',
            'Range 回源',
            'Header 透传',
        ],
        checks: [
            '开启前检查源站兼容性',
            '协议变更需节点同步',
            '保存后提示刷新配置',
        ],
    },
    {
        key: 'https',
        label: 'HTTPS',
        title: 'HTTPS 设置',
        description: '绑定证书、强制 HTTPS、HSTS 和 TLS 版本策略。',
        endpoint: 'GET /v1/certs, PUT /v1/sites/{id}',
        fields: ['证书绑定', '强制 HTTPS', 'HSTS', 'TLS 版本', 'OCSP Stapling'],
        checks: [
            '证书过期前提醒',
            '强制 HTTPS 前检查源站协议',
            '避免误开启 HSTS',
        ],
    },
    {
        key: 'origin',
        label: '源站',
        title: '源站设置',
        description: '配置源站地址、回源协议、端口、Host、SNI 和健康策略。',
        endpoint: 'PUT /v1/sites/{id}',
        fields: ['源站列表', '回源协议', '回源 Host', 'SNI', '失败重试'],
        checks: [
            '源站地址格式校验',
            '多源站权重校验',
            '回源 Host 默认取业务域名',
        ],
    },
    {
        key: 'cache',
        label: '缓存',
        title: '缓存配置',
        description: '配置缓存 TTL、缓存键、忽略参数和状态码缓存。',
        endpoint: 'PUT /v1/sites/{id}, POST /v1/jobs',
        fields: [
            '目录 TTL',
            '文件后缀 TTL',
            '忽略 Query',
            '状态码缓存',
            '缓存键',
        ],
        checks: [
            'API 路径默认不缓存',
            '大文件单独设置 TTL',
            '变更后提示刷新缓存',
        ],
    },
    {
        key: 'purge',
        label: '刷新预热',
        title: '刷新预热',
        description: '提交站点相关刷新和预热任务。',
        endpoint: 'GET/POST /v1/jobs',
        fields: ['URL 刷新', '目录刷新', 'URL 预热', '任务队列', '执行结果'],
        checks: ['限制单次 URL 数量', '展示失败 URL', '保留最近任务记录'],
    },
    {
        key: 'security',
        label: '安全',
        title: '安全配置',
        description: '配置基础防护、Header 安全策略和防盗链。',
        endpoint: 'PUT /v1/sites/{id}',
        fields: ['防盗链', '安全 Header', '区域限制', 'UA 规则', 'IP 规则'],
        checks: ['规则变更支持回滚', '观察模式优先', '展示命中日志入口'],
    },
    {
        key: 'acl',
        label: '访问控制',
        title: '访问控制',
        description: '绑定 ACL 规则组并查看命中情况。',
        endpoint: 'GET/POST /v1/acls, PUT /v1/sites/{id}',
        fields: ['ACL 规则组', 'IP 黑白名单', 'Referer', '区域', '自定义条件'],
        checks: ['先保存 ACL 再绑定站点', '避免空规则覆盖', '保留规则版本'],
    },
    {
        key: 'cc',
        label: 'CC 防护',
        title: 'CC 防护',
        description: '绑定 CC 规则、匹配器和过滤动作。',
        endpoint: 'GET /v1/cc-rules, /v1/cc-matchs, /v1/cc-filters',
        fields: ['规则组', '匹配器', '过滤动作', '统计周期', '封禁时长'],
        checks: ['默认观察模式', '登录接口单独策略', '命中异常时可快速关闭'],
    },
    {
        key: 'advanced',
        label: '高级',
        title: '高级配置',
        description: '维护自定义 Header、回源高级参数、节点同步和兼容开关。',
        endpoint: 'PUT /v1/sites/{id}',
        fields: [
            '请求 Header',
            '响应 Header',
            '回源超时',
            '连接复用',
            '节点同步',
        ],
        checks: ['高级项默认折叠', '危险项二次确认', '记录变更差异'],
    },
    {
        key: 'logs',
        label: '访问日志',
        title: '访问日志',
        description: '按时间、IP、状态码、路径查询站点访问日志。',
        endpoint: 'GET /v1/monitor/site/access-log',
        fields: ['时间范围', '状态码', '客户端 IP', 'URL', '缓存状态'],
        checks: ['查询条件服务端校验', '大范围查询分页', '导出任务异步处理'],
    },
    {
        key: 'realtime',
        label: '实时统计',
        title: '实时统计',
        description: '查看站点实时 QPS、带宽、命中率和状态码。',
        endpoint: 'GET /v1/monitor/site/realtime',
        fields: ['QPS', '带宽', '命中率', '状态码', '回源耗时'],
        checks: ['默认最近 1 小时', '支持自动刷新', '异常峰值标记'],
    },
    {
        key: 'top',
        label: '资源排行',
        title: '资源排行',
        description: '查看 URL、IP、地区、Referer、UA 等 Top 数据。',
        endpoint: 'GET /v1/monitor/site/top',
        fields: ['URL Top', 'IP Top', '地区 Top', 'Referer Top', 'UA Top'],
        checks: ['支持维度切换', '可导出 CSV', '高流量资源可一键刷新'],
    },
];
