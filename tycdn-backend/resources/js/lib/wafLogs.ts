import {
    defaultAccessFilters,
    accessLogCell,
    accessHeaderText,
} from './accessLogs';
import { extractCdnflyRecord, isCdnflyRecord } from './cdnflyResponse';
import { formatDate, textValue } from './formatters';
import type { CdnflyRecord } from './sharedTypes';

export const wafModules: Record<string, string> = {
    sqli: 'SQL注入',
    xss: 'XSS攻击',
    upload: '文件上传',
    fi: '文件包含',
    cmdi: '命令注入',
    java: 'Java代码注入',
    jdeser: 'Java反序列化',
    php: 'PHP代码注入',
    phpdeser: 'PHP反序列化',
    ssti: '模板注入',
    waf_runtime: 'WAF运行时防护',
};
export const wafCategories: Record<string, string> = {
    sqli: 'SQL注入',
    xss: 'XSS攻击',
    upload: '文件上传',
    fi: '文件包含',
    cmdi: '命令注入',
    java: 'Java攻击',
    php: 'PHP攻击',
    ssti: '模板注入',
    operational: '运行异常',
};
export const wafParts: Record<string, string> = {
    arg: '请求参数',
    header: '请求头',
    cookie: 'Cookie',
    body: '请求体',
    uri: 'URI',
    upload: '上传文件',
};
export const wafSubtypes: Record<string, string> = {
    tautology: '恒真条件',
    union: 'UNION查询',
    comment: '注释截断',
    stacked: '堆叠查询',
    blind: '盲注',
    error_based: '报错注入',
    subquery: '子查询',
    oob: '带外注入',
    html_tag: 'HTML标签',
    js_inject: 'JavaScript注入',
    url_inject: 'URL注入',
    attr_inject: '属性注入',
    dangerous_ext: '危险扩展名',
    traversal: '路径穿越',
    webshell: 'WebShell',
    mime_spoof: 'MIME伪装',
    script_access: '脚本访问',
    ognl_el: 'OGNL/EL表达式',
    protocol: '危险协议',
    sensitive_path: '敏感路径',
    metachar: '命令分隔符',
    exec_cmd: '命令执行',
    el: '表达式注入',
    jndi: 'JNDI注入',
    classref: '类引用',
    deserial_binary: '二进制反序列化',
    deserial_json: 'JSON反序列化',
    deserial_xml: 'XML反序列化',
    code_exec: '代码执行',
    chain: '混淆执行链',
    callable: '危险回调',
    deserial_gadget: '反序列化Gadget',
    deserial_inject: '反序列化注入',
    python: 'Python模板注入',
    php: 'PHP模板注入',
    ruby: 'Ruby模板注入',
    js: 'JS模板注入',
    generic: '通用模板探测',
    cfml: 'CFML模板注入',
    dotnet: '.NET模板注入',
    inspection_incomplete: '检测未完成',
    resource_exhausted: '检测资源耗尽',
    work_budget_exhausted: '工作预算耗尽',
};
export const wafFields: {
    key: string;
    label: string;
    options?: Record<string, string>;
}[] = [
    { key: 'host', label: '域名' },
    { key: 'client_ip', label: '客户端IP' },
    { key: 'request_uri', label: '请求地址' },
    {
        key: 'action',
        label: '动作',
        options: { observe: '观察', protect: '拦截' },
    },
    { key: 'module', label: '模块', options: wafModules },
    { key: 'attack_category', label: '攻击分类', options: wafCategories },
    { key: 'attack_subtype', label: '攻击子类型', options: wafSubtypes },
    { key: 'waf_matched_part', label: '命中位置', options: wafParts },
    { key: 'waf_matched_key', label: '命中键名' },
    { key: 'waf_payload_hash', label: 'Payload Hash' },
    {
        key: 'rule_source',
        label: '规则来源',
        options: { builtin: '内置规则', custom: '自定义规则' },
    },
    { key: 'rule_id', label: '规则ID' },
    { key: 'server_port', label: '访问端口' },
    { key: 'site_id', label: '站点ID' },
    { key: 'node_id', label: '节点ID' },
    { key: 'status', label: '状态码' },
    { key: 'tls_fp', label: 'TLS指纹' },
    {
        key: 'auto_blocked',
        label: '自动封禁',
        options: { '1': '已触发', '0': '未触发' },
    },
    { key: 'country', label: '国家' },
    { key: 'province', label: '省份' },
    { key: 'isp', label: '运营商' },
];
export type WafFilters = Record<string, string>;
export function defaultWafFilters(now = new Date()): WafFilters {
    const { start, end } = defaultAccessFilters(now);

    return {
        ...Object.fromEntries(wafFields.map((f) => [f.key, ''])),
        start,
        end,
        uri_match_type: 'exact',
    };
}
export function wafParams(filters: WafFilters): Record<string, string> {
    const start = Date.parse(filters.start.replace(' ', 'T')),
        end = Date.parse(filters.end.replace(' ', 'T'));

    if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) {
        throw new Error('请选择有效的时间范围，结束时间必须晚于开始时间');
    }

    const date = (value: string) =>
        value.replace('T', ' ').length === 16
            ? `${value.replace('T', ' ')}:00`
            : value.replace('T', ' ');
    const params: Record<string, string> = {
        start: date(filters.start),
        end: date(filters.end),
        uri_match_type: filters.uri_match_type || 'exact',
    };

    for (const field of wafFields) {
        const value = filters[field.key]?.trim();

        if (value) {
            params[field.key] = value;
        }
    }

    return params;
}
export const wafSummary = [
    { key: 'total', label: '攻击总数' },
    { key: 'protect', label: '拦截次数' },
    { key: 'observe', label: '观察次数' },
    { key: 'auto_blocked', label: '自动封禁' },
    { key: 'unique_ip', label: '独立客户端IP' },
] as const;
export type WafTrendPoint = {
    time: string;
    total: number;
    protect: number;
    observe: number;
};
export type WafStats = Record<(typeof wafSummary)[number]['key'], number> & {
    trend: WafTrendPoint[];
    top: CdnflyRecord;
};
export const wafCount = (value: unknown): number =>
    Number.isFinite(Number(value)) ? Math.max(0, Number(value)) : 0;
export function normalizeWafStats(result: unknown): WafStats {
    const data = extractCdnflyRecord(result) ?? {};

    return {
        total: wafCount(data.total),
        protect: wafCount(data.protect),
        observe: wafCount(data.observe),
        auto_blocked: wafCount(data.auto_blocked),
        unique_ip: wafCount(data.unique_ip),
        trend: Array.isArray(data.trend)
            ? data.trend.filter(isCdnflyRecord).map((p) => ({
                  time: textValue(p.time),
                  total: wafCount(p.total),
                  protect: wafCount(p.protect),
                  observe: wafCount(p.observe),
              }))
            : [],
        top: isCdnflyRecord(data.top) ? data.top : {},
    };
}
export const wafRankings = [
    { key: 'domain', title: '受攻击域名', label: '域名', filter: 'host' },
    {
        key: 'client_ip',
        title: '客户端IP',
        label: '客户端IP',
        filter: 'client_ip',
    },
    { key: 'country', title: '攻击来源国家', label: '国家', filter: 'country' },
    {
        key: 'province',
        title: '攻击来源省份',
        label: '省份',
        filter: 'province',
    },
    { key: 'isp', title: '攻击来源运营商', label: '运营商', filter: 'isp' },
    { key: 'uri', title: '受攻击URI', label: 'URI', filter: 'request_uri' },
] as const;
export const wafColors = [
    '#2d8cf0',
    '#19be6b',
    '#ff9900',
    '#ed4014',
    '#9b6cf5',
    '#06b6d4',
    '#ec4899',
    '#64748b',
    '#d4a72c',
];
export type WafRank = {
    display: string;
    count: number;
    filter: Record<string, string>;
    percent: number;
    color: string;
};
export function wafRankRows(top: CdnflyRecord, key: string): WafRank[] {
    const items = top[key];
    const rows = Array.isArray(items) ? items.filter(isCdnflyRecord) : [];
    const sum = rows.reduce((n, row) => n + wafCount(row.count), 0);

    return rows
        .map((row, i) => {
            const value = textValue(
                key === 'client_ip'
                    ? row.ip
                    : key === 'attack_type'
                      ? row.category
                      : row.key,
            );
            const filterKey =
                key === 'attack_type'
                    ? 'attack_category'
                    : (wafRankings.find((r) => r.key === key)?.filter ?? key);

            return {
                display:
                    key === 'client_ip'
                        ? wafClientIp({ ...row, client_ip: row.ip })
                        : key === 'attack_type'
                          ? (wafCategories[value] ?? value)
                          : value,
                count: wafCount(row.count),
                filter: {
                    [filterKey]: value,
                    ...(key === 'uri' ? { uri_match_type: 'exact' } : {}),
                },
                percent: sum ? (wafCount(row.count) / sum) * 100 : 0,
                color: wafColors[i % wafColors.length],
            };
        })
        .filter((row) => row.display);
}
export const wafTruthy = (value: unknown): boolean =>
    value === true || value === 1 || value === '1' || value === 'true';
export function wafClientIp(row: CdnflyRecord): string {
    const location = [row.country, row.province, row.city]
        .map(textValue)
        .filter((s) => s && s !== '-')
        .join('-');

    return `${textValue(row.client_ip)}${location ? ` (${location})` : ''}`;
}
export function wafAttackType(row: CdnflyRecord): string {
    const category =
        wafCategories[textValue(row.attack_category)] ??
        textValue(row.attack_category);
    const subtype =
        wafSubtypes[textValue(row.attack_subtype)] ??
        textValue(row.attack_subtype);

    return category && category !== '-'
        ? subtype && subtype !== '-'
            ? row.module === row.attack_category
                ? subtype
                : `${category} / ${subtype}`
            : category
        : subtype;
}
export const wafColumns = [
    { key: 'time', label: '时间', width: 180 },
    { key: 'host', label: '域名', width: 200 },
    { key: 'action', label: '动作', width: 130 },
    { key: 'module', label: '模块', width: 140 },
    { key: 'attack_type', label: '攻击类型', width: 180 },
    { key: 'request_uri', label: 'URI', width: 220 },
    { key: 'client_ip', label: '客户端IP', width: 240 },
    { key: 'waf_matched_part', label: '命中位置', width: 160 },
    { key: 'waf_payload_sample', label: '命中内容', width: 220 },
] as const;
export function wafCell(row: CdnflyRecord, key: string): string {
    if (key === 'time') {
        if (row.time) {
            return textValue(row.time);
        }

        const raw = row.timestamp ?? row['@timestamp'];

        if (typeof raw === 'number' || /^\d{13}$/.test(textValue(raw))) {
            const date = new Date(Number(raw));

            return Number.isNaN(date.getTime())
                ? '-'
                : formatDate(date.toISOString());
        }

        return formatDate(raw);
    }

    if (key === 'host') {
        return accessLogCell(row, 'host');
    }

    if (key === 'action') {
        return (
            ({ protect: '拦截', observe: '观察' } as Record<string, string>)[
                textValue(row.action)
            ] ?? textValue(row.action)
        );
    }

    if (key === 'module') {
        return wafModules[textValue(row.module)] ?? textValue(row.module);
    }

    if (key === 'attack_type') {
        return wafAttackType(row);
    }

    if (key === 'client_ip') {
        return wafClientIp(row);
    }

    if (key === 'waf_matched_part') {
        return `${wafParts[textValue(row.waf_matched_part)] ?? textValue(row.waf_matched_part)}${row.waf_matched_key ? `:${textValue(row.waf_matched_key)}` : ''}`;
    }

    return textValue(row[key]);
}
export function wafDetailSections(
    row: CdnflyRecord,
): { title: string; items: { label: string; value: string }[] }[] {
    const auto = wafTruthy(row.auto_blocked);
    const pairs = (items: [string, unknown][]) =>
        items
            .filter(([, v]) => v !== undefined && v !== null && v !== '')
            .map(([label, value]) => ({ label, value: textValue(value) }));

    return [
        {
            title: '攻击概览',
            items: pairs([
                ['时间', wafCell(row, 'time')],
                ['动作', wafCell(row, 'action')],
                ['模块', wafCell(row, 'module')],
                ['攻击类型', wafAttackType(row)],
                ['状态码', row.status],
                [
                    '自动封禁',
                    auto
                        ? `已封禁${Number(row.auto_block_exp) > 0 ? `，至 ${formatDate(new Date(Number(row.auto_block_exp) * 1000).toISOString())}` : ''}`
                        : '',
                ],
            ]),
        },
        {
            title: '请求信息',
            items: pairs([
                ['域名', wafCell(row, 'host')],
                ['端口', row.server_port],
                ['客户端IP', wafClientIp(row)],
                [
                    '请求',
                    [row.method, row.request_uri]
                        .map(textValue)
                        .filter(Boolean)
                        .join(' '),
                ],
                ['TLS指纹', row.tls_fp],
                ['请求头', accessHeaderText(row.req_header)],
            ]),
        },
        {
            title: '命中证据',
            items: pairs([
                ['命中位置', wafCell(row, 'waf_matched_part')],
                ['命中内容', row.waf_payload_sample],
                [
                    '命中规则',
                    [row.rule_source, row.rule_name, row.rule_id]
                        .map(textValue)
                        .filter(Boolean)
                        .join(' · '),
                ],
                [
                    '采集状态',
                    wafTruthy(row.body_truncated) ? '请求体采集已截断' : '',
                ],
            ]),
        },
        {
            title: '技术详情',
            items: pairs([
                ['请求ID', row.request_id],
                ['站点ID', row.site_id],
                ['节点ID', row.node_id],
                ['用户ID', row.uid],
                ['模板', row.attack_template],
                ['Payload Hash', row.waf_payload_hash],
                [
                    'Body截断',
                    row.body_truncated === undefined
                        ? ''
                        : wafTruthy(row.body_truncated)
                          ? '是'
                          : '否',
                ],
                ['规则来源', row.rule_source],
                ['规则ID', row.rule_id],
                ['规则名称', row.rule_name],
                ['规则动作', row.rule_action],
                ...(auto
                    ? ([
                          ['封禁来源', row.auto_block_filter],
                          ['统计窗口', row.auto_block_window_seconds],
                          ['命中阈值', row.auto_block_hit_threshold],
                          ['封禁时长', row.auto_block_seconds],
                      ] as [string, unknown][])
                    : []),
            ]),
        },
    ];
}
export type WafAllowTarget = { siteId: string; host: string; uri: string };
function normalizeUri(value: unknown): string {
    let uri = textValue(value).trim();

    if (/^https?:\/\//i.test(uri)) {
        uri = new URL(uri).pathname;
    }

    uri = uri.split(/[?#]/)[0].trim();

    return uri.startsWith('/') ? uri : `/${uri}`;
}
export function wafAllowTarget(row: CdnflyRecord): WafAllowTarget | null {
    const siteId = textValue(row.site_id).trim(),
        host = textValue(row.host2 || row.host)
            .trim()
            .toLowerCase();

    if (!/^\d+$/.test(siteId) || !host) {
        return null;
    }

    try {
        return { siteId, host, uri: normalizeUri(row.request_uri) };
    } catch {
        return null;
    }
}
// Only combine canonical two-condition rules. Preserve all unrelated rules and
// conditions; malformed stored JSON must never be replaced by an empty list.
export function mergeWafAllowRule(
    value: unknown,
    target: WafAllowTarget,
): { exists: boolean; rules: CdnflyRecord[] } {
    const parsed =
        typeof value === 'string'
            ? value.trim()
                ? JSON.parse(value)
                : []
            : (value ?? []);

    if (!Array.isArray(parsed) || !parsed.every(isCdnflyRecord)) {
        throw new Error('现有 WAF 放行规则格式无效，请先在网站设置中检查');
    }

    const rules: CdnflyRecord[] = JSON.parse(JSON.stringify(parsed));

    for (const rule of rules) {
        if (
            rule.action !== 'allow' ||
            !wafTruthy(rule.enable) ||
            !Array.isArray(rule.matcher_groups) ||
            rule.matcher_groups.length !== 1
        ) {
            continue;
        }

        const group = rule.matcher_groups[0];

        if (
            !isCdnflyRecord(group) ||
            !Array.isArray(group.matcher) ||
            group.matcher.length !== 2 ||
            !group.matcher.every(isCdnflyRecord)
        ) {
            continue;
        }

        const host = group.matcher.find((m) => m.field === 'host'),
            uri = group.matcher.find((m) => m.field === 'uri');

        if (
            !host ||
            !uri ||
            host.op !== '=' ||
            textValue(host.value).toLowerCase() !== target.host ||
            !['=', 'in'].includes(textValue(uri.op))
        ) {
            continue;
        }

        if ([host, uri].some((m) => m.transform && m.transform !== 'none')) {
            continue;
        }

        const values =
            uri.op === '='
                ? [uri.value]
                : Array.isArray(uri.value)
                  ? uri.value
                  : textValue(uri.value).split(/[\r\n,]+/);

        if (values.some((v) => normalizeUri(v) === target.uri)) {
            return { exists: true, rules };
        }

        if (textValue(rule.name).startsWith('日志放行 ')) {
            uri.op = 'in';
            uri.value = [...new Set([...values.map(normalizeUri), target.uri])];

            return { exists: false, rules };
        }
    }

    rules.push({
        name: `日志放行 ${target.host}`,
        enable: 1,
        tz_offset: '+08:00',
        action: 'allow',
        action_config: {},
        matcher_groups: [
            {
                id: 'request',
                name: '请求',
                matcher: [
                    {
                        field: 'host',
                        op: '=',
                        transform: 'none',
                        value: target.host,
                    },
                    {
                        field: 'uri',
                        op: 'in',
                        transform: 'none',
                        value: [target.uri],
                    },
                ],
            },
        ],
    });

    return { exists: false, rules };
}
