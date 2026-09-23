import type { CdnflyRecord } from './sharedTypes';
export const flag = (value: unknown) =>
    value === true || value === 1 || value === '1';
export const templates = {
    slider_html: '滑动',
    captcha_html: '验证码',
    click_html: '点击',
    delay_jump_html: '5秒盾',
    rotate_html: '图片旋转',
    easy_click_html: '点击(简单)',
    easy_slider_html: '滑动(简单)',
};
export const limitFields = [
    {
        key: 'candidate_per_param',
        label: '单个参数检查数量',
        help: '一个参数经过解码后，最多拆分出多少项交给 WAF 检查。',
        unit: '个',
        min: 256,
        max: 4096,
        default: 2048,
        group: '参数检测',
        scale: 1,
    },
    {
        key: 'candidate_per_request',
        label: '单次请求检查数量',
        help: '一次请求中，所有参数合计最多检查多少项。',
        unit: '个',
        min: 1024,
        max: 16384,
        default: 4096,
        group: '参数检测',
        scale: 1,
    },
    {
        key: 'ffi_per_request',
        label: '最多检测次数',
        help: '一次请求最多允许 WAF 执行多少次检测。',
        unit: '次',
        min: 2048,
        max: 32768,
        default: 8192,
        group: '参数检测',
        scale: 1,
    },
    {
        key: 'json_max_leaves',
        label: 'JSON 字段和值数量',
        help: '单个 JSON 参数最多读取多少个字段和值。',
        unit: '个',
        min: 64,
        max: 4096,
        default: 2048,
        group: 'JSON 内容',
        scale: 1,
    },
    {
        key: 'json_max_nodes',
        label: 'JSON 结构总数',
        help: '一次请求最多处理多少个 JSON 对象、数组和值。',
        unit: '个',
        min: 1024,
        max: 32768,
        default: 9216,
        group: 'JSON 内容',
        scale: 1,
    },
    {
        key: 'json_max_depth',
        label: 'JSON 嵌套层数',
        help: 'JSON 对象和数组最多允许嵌套多少层。',
        unit: '层',
        min: 16,
        max: 128,
        default: 64,
        group: 'JSON 内容',
        scale: 1,
    },
    {
        key: 'json_max_string_bytes',
        label: 'JSON 单项文本大小',
        help: '单个 JSON 文本值最多允许多大。',
        unit: 'KB',
        min: 16,
        max: 1024,
        default: 256,
        group: 'JSON 内容',
        scale: 1024,
    },
    {
        key: 'json_body_bytes',
        label: 'JSON 请求体大小',
        help: '一次请求最多完整解析多大的 JSON 内容。',
        unit: 'KB',
        min: 256,
        max: 4096,
        default: 1024,
        group: 'JSON 内容',
        scale: 1024,
    },
    {
        key: 'multipart_max_parts',
        label: '表单字段和文件数量',
        help: '一次表单上传最多包含多少个字段和文件。',
        unit: '项',
        min: 16,
        max: 512,
        default: 128,
        group: '请求内容与上传',
        scale: 1,
    },
    {
        key: 'body_scan_bytes',
        label: '单次请求扫描大小',
        help: 'WAF 最多检查多大的请求内容，包括上传文件和解压后的内容。',
        unit: 'MB',
        min: 1,
        max: 64,
        default: 16,
        group: '请求内容与上传',
        scale: 1048576,
    },
];
export function recommendedLimits(): CdnflyRecord {
    return Object.fromEntries(
        limitFields.map((f) => [f.key, f.default * f.scale]),
    );
}
export function object(value: unknown): CdnflyRecord {
    return value && typeof value === 'object' && !Array.isArray(value)
        ? (value as CdnflyRecord)
        : {};
}
export function getPath(config: CdnflyRecord, path: string): unknown {
    return path.split('.').reduce<unknown>((v, k) => object(v)[k], config);
}
export function setPath(config: CdnflyRecord, path: string, value: unknown) {
    const parts = path.split('.');
    let target = config;

    for (const key of parts.slice(0, -1)) {
        target[key] = { ...object(target[key]) };
        target = object(target[key]);
    }

    target[parts.at(-1)!] = value;
}
export function validateLimits(limits: CdnflyRecord): string {
    for (const f of limitFields) {
        const value = Number(limits[f.key]);

        if (
            !Number.isInteger(value) ||
            value < f.min * f.scale ||
            value > f.max * f.scale
        ) {
            return `${f.label}必须在 ${f.min}–${f.max} ${f.unit}之间`;
        }
    }

    if (Number(limits.json_max_leaves) > Number(limits.candidate_per_param)) {
        return 'JSON 字段和值数量不能大于单个参数检查数量';
    }

    if (
        Number(limits.candidate_per_param) >
        Number(limits.candidate_per_request)
    ) {
        return '单个参数检查数量不能大于单次请求检查数量';
    }

    if (Number(limits.json_body_bytes) > Number(limits.body_scan_bytes)) {
        return 'JSON 请求体大小不能大于单次请求扫描大小';
    }

    return '';
}
export const overrideNames: Record<string, string> = {
    waf_enable: 'WAF防护开关',
    waf_resource_limits: 'WAF单次请求处理上限',
    cc_enable: 'CC防护开关',
    block_time: '黑名单时间',
    white_time: '临时白名单时间',
    tmp_white_total_limit: '白名单5秒总请求数',
    tmp_white_per_limit: '白名单5秒同URL请求数',
    custom_white: '白名单IP',
    custom_black: '黑名单IP',
    ssl_handshake_limit: '防止TLS握手攻击',
    default_page_refuse: '禁止节点IP及未绑定域名访问',
    default_page_rule: '默认页规则组',
    icmp_drop: '禁止PING',
    resolver: 'DNS服务器',
    key: '密钥',
    auto_delete_access_log: '自动清理节点日志',
    auto_switch: '自动规则切换配置',
    log: '日志配置',
    ...Object.fromEntries(
        Object.entries(templates).map(([k, v]) => [k, `${v}页面`]),
    ),
};
