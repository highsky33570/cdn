export const l2MatchItems: Record<string, string> = {
    host: '域名',
    req_uri: '请求URI',
    uri: '请求URI(不带参数)',
    node_country_code: '节点国家代码',
    node_isp: '节点运营商',
    node_province: '节点省份',
    node_city: '节点城市',
    node_id: '节点ID',
    protocol: '协议',
};
export const l2MatchOperators: Record<string, string> = {
    '=': '等于',
    '!=': '不等于',
    contain: '包含',
    '!contain': '不包含',
    prefix: '前缀匹配',
    suffix: '后缀匹配',
    regex: '正则匹配',
    '!regex': '正则不匹配',
};
export type L2Rule = Record<string, unknown> & {
    item: string;
    op: string;
    value: string;
    value2: string;
};

/** Native condition details encode data as JSON; preserve empty and multiline values. */
export function decodeL2Rules(value: unknown): L2Rule[] {
    let parsed: unknown = value;

    if (typeof value === 'string') {
        try {
            parsed = JSON.parse(value);
        } catch {
            throw new Error('条件规则数据格式无效，请重新加载');
        }
    }

    if (!Array.isArray(parsed)) {
        throw new Error('条件规则数据格式无效，请重新加载');
    }

    return parsed.map((rule) => {
        if (
            !rule ||
            typeof rule !== 'object' ||
            Array.isArray(rule) ||
            typeof rule.item !== 'string' ||
            typeof rule.op !== 'string' ||
            typeof rule.value !== 'string' ||
            (rule.value2 !== undefined && typeof rule.value2 !== 'string')
        ) {
            throw new Error('条件规则数据格式无效，请重新加载');
        }

        return { ...rule, value2: rule.value2 ?? '' };
    });
}
