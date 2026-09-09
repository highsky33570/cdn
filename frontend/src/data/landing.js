/**
 * Landing page copy, in one place.
 *
 * Kept out of the components so the marketing wording can be tuned without
 * touching layout, and so every unverified claim is visible in a single file
 * rather than scattered through six templates.
 *
 * NOTE ON NUMBERS — read before launch.
 * Anything in `PENDING_FACTS` is a placeholder. The competitor's site leads with
 * figures like "≤40ms" and "99.98% 可用性"; those are their measurements, not
 * ours, and publishing them as TyCDN's own would be inventing performance data
 * for a network that currently has no nodes deployed. Replace each one with a
 * real measurement before this page goes live, or delete the tile.
 */

export const PENDING_FACTS = {
  // 节点上线后用真实探测数据替换（建议取中国大陆到日本节点的 P95）
  latency: { value: '—', unit: 'ms', label: '中国大陆平均延迟', pending: true },
  // 取 CDNfly 面板「节点管理」实际在线节点数
  nodes: { value: '—', unit: '', label: '在线边缘节点', pending: true },
  // 取近 30 天可用性统计
  uptime: { value: '—', unit: '', label: '近 30 天可用性', pending: true },
}

/** 用户当前的痛点 —— 结构对标参考站，措辞按自身定位重写。 */
export const painPoints = [
  {
    n: '01',
    title: '一被攻击就"清洗"，业务跟着一起停',
    desc: '流量一超阈值就黑洞路由，攻击结束了站点还在恢复期。防护成了另一种宕机。',
  },
  {
    n: '02',
    title: '中国大陆访问慢，用户在首屏就流失',
    desc: '海外源站直连绕路，回源链路不可控。国内用户等待几秒，转化率先掉一半。',
  },
  {
    n: '03',
    title: '账单按峰值计费，一次攻击吃掉整月预算',
    desc: '按峰值带宽结算，被打一次的费用比正常业务一个月还高，成本完全不可预期。',
  },
  {
    n: '04',
    title: '规则改一次，等工单等到第二天',
    desc: '缓存、回源、WAF 规则都要提工单。业务节奏是分钟级，支持响应是天级。',
  },
  {
    n: '05',
    title: '合规审查一刀切，业务说停就停',
    desc: '内容审核标准不透明，申诉周期长。已经在跑的业务随时可能被下线。',
  },
  {
    n: '06',
    title: '看不到真实数据，出了问题只能猜',
    desc: '没有实时带宽、请求、回源与攻击视图，排障靠猜，容量规划靠感觉。',
  },
]

/** 我们的解法 —— 每条都对应上面某个痛点。 */
export const advantages = [
  {
    key: 'attack',
    tag: '不封禁',
    title: '被攻击时不停机',
    desc: '攻击流量在边缘节点清洗，源站不暴露、不黑洞。业务照常响应，防护对访客无感。',
    metric: null,
  },
  {
    key: 'route',
    tag: '中国优化',
    title: '为中国大陆访问选路',
    desc: '亚太边缘节点 + 智能回源，避开拥塞骨干。同一份内容，国内用户拿到的是就近副本。',
    metric: null,
  },
  {
    key: 'price',
    tag: '成本可控',
    title: '按套餐计费，不按攻击量计费',
    desc: '流量与带宽写在套餐里，被攻击不会产生额外账单。月初就能算清这个月要花多少。',
    metric: null,
  },
  {
    key: 'control',
    tag: '自助控制台',
    title: '规则自己改，即时生效',
    desc: '缓存、回源、WAF、四层转发全部在控制台自助配置，改完即时下发到全部节点，不用提工单。',
    metric: null,
  },
]

/**
 * 对比表 —— 客户点名「这块可以参考」的部分。
 *
 * 参考站真正有效的不是内容，是**表达方式**：每格都是一枚带色药丸，我方绿、
 * 对手红，扫一眼就分出胜负，不需要逐字读。所以这里保留药丸，但只写结构性
 * 差异（计费方式、控制权、响应路径、支付方式）——这些不随机房和时段变化，
 * 随时可自证。参考站那种「≤40ms」「99.88%」是实测数字，节点没上线之前
 * 写上去就是编的。
 */
export const comparison = {
  columns: ['TyCDN', '传统 CDN', '大厂云 CDN', '免费 CDN'],
  rows: [
    {
      label: '攻击时路由',
      cells: [
        { text: '边缘就近清洗，不绕路', tone: 'good' },
        { text: '超阈值黑洞，业务同停', tone: 'bad' },
        { text: '触发限速或切换路径', tone: 'bad' },
        { text: '直接暂停服务', tone: 'bad' },
      ],
    },
    {
      label: '攻击产生的费用',
      cells: [
        { text: '包含在套餐内', tone: 'good' },
        { text: '按峰值带宽另计', tone: 'bad' },
        { text: '按流量另计，金额不可预期', tone: 'bad' },
        { text: '不适用', tone: 'muted' },
      ],
    },
    {
      label: '大陆方向线路',
      cells: [
        { text: '亚太节点就近接入', tone: 'good' },
        { text: '视机房而定', tone: 'warn' },
        { text: '需另购加速产品', tone: 'bad' },
        { text: '无优化，随机绕行', tone: 'bad' },
      ],
    },
    {
      label: '防护策略',
      cells: [
        { text: 'DDoS / WAF / CC 分层拦截', tone: 'good' },
        { text: '规则少，依赖人工', tone: 'bad' },
        { text: '需多组件叠加配置', tone: 'warn' },
        { text: '仅基础开关', tone: 'bad' },
      ],
    },
    {
      label: '规则修改方式',
      cells: [
        { text: '控制台自助，即时下发', tone: 'good' },
        { text: '多数需提工单', tone: 'bad' },
        { text: '控制台，但配置项繁杂', tone: 'warn' },
        { text: '几乎不可调', tone: 'bad' },
      ],
    },
    {
      label: '四层转发 TCP / UDP',
      cells: [
        { text: '支持', tone: 'good' },
        { text: '部分支持', tone: 'warn' },
        { text: '需单独购买', tone: 'bad' },
        { text: '不支持', tone: 'bad' },
      ],
    },
    {
      label: '数据可见度',
      cells: [
        { text: '带宽 / 请求 / 回源 / 攻击全量可查', tone: 'good' },
        { text: '仅基础统计', tone: 'bad' },
        { text: '完整，但需额外付费', tone: 'warn' },
        { text: '几乎没有', tone: 'bad' },
      ],
    },
    {
      label: '接入时效',
      cells: [
        { text: '一条 CNAME，分钟级', tone: 'good' },
        { text: '流程偏慢，需多次验证', tone: 'bad' },
        { text: '需企业实名与合同', tone: 'bad' },
        { text: '快，但能力有限', tone: 'warn' },
      ],
    },
    {
      label: '支付方式',
      cells: [
        { text: 'USDT，链上确认自动开通', tone: 'good' },
        { text: '对公转账为主', tone: 'warn' },
        { text: '需企业实名', tone: 'bad' },
        { text: '不适用', tone: 'muted' },
      ],
    },
  ],
}

/** 首屏「适配场景」标签 —— 让访客一眼确认自己属不属于目标客户。 */
export const scenarios = [
  '网站加速',
  '下载分发',
  '音视频',
  'API / WebSocket',
  '安全防护',
  '四层转发',
  '游戏加速',
]

/** 首屏右侧地图上的浮动卡片。 */
export const mapHighlights = [
  { title: '就近接入', desc: '亚太边缘节点 · CNAME 即接' },
  { title: '边缘清洗', desc: 'DDoS / WAF / CC 自动拦截' },
  { title: '实时报表', desc: '带宽 · 回源 · 攻击全可查' },
]

/** 防护能力 —— 描述机制，不承诺具体防御量级。 */
export const defenseLayers = [
  {
    layer: 'L3 / L4',
    title: '网络层清洗',
    desc: 'SYN Flood、UDP Flood、反射放大等流量型攻击在进入业务前于边缘丢弃。',
  },
  {
    layer: 'L7',
    title: 'WAF 规则引擎',
    desc: 'SQL 注入、XSS、已知漏洞特征库，规则可按站点单独启用与调整。',
  },
  {
    layer: 'CC',
    title: 'CC / Bot 识别',
    desc: '基于频率、指纹与行为特征识别刷量请求，可选择拦截、限速或人机校验。',
  },
]

export const faqs = [
  {
    q: '接入需要改动我的服务器吗？',
    a: '不需要。在控制台添加域名后，把该域名的 CNAME 指向我们提供的加速地址即可，源站配置保持不变。整个过程通常几分钟完成。',
  },
  {
    q: '支持 HTTPS 吗？证书怎么处理？',
    a: '支持。可以上传自有证书，也可以由平台自动申请并续期 Let\'s Encrypt 免费证书，到期前自动更新，无需人工介入。',
  },
  {
    q: '被攻击时会不会额外收费或者被停服？',
    a: '不会额外收费。防护包含在套餐内，攻击流量不计入你的业务流量，也不会因为遭受攻击而暂停服务。',
  },
  {
    q: '支持哪些付款方式？',
    a: '目前支持 USDT（TRC-20）。下单后系统生成收款地址，链上确认后自动开通，无需人工审核。',
  },
  {
    q: '套餐用量超了会怎样？',
    a: '控制台可以随时查看已用流量与带宽。接近额度时会提前提醒，你可以升级套餐或购买额外流量包，不会直接断服。',
  },
  {
    q: '可以先测试再决定吗？',
    a: '可以。建议先用入门套餐接一个非核心域名实测，确认线路质量与回源表现后再迁移主站。套餐之间支持随时升级。',
  },
]

export const contact = {
  telegram: '@tycdn',
  telegramUrl: 'https://t.me/tycdn',
  email: 'support@tycdn.org',
}
