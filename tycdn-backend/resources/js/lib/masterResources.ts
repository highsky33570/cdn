import type { ColumnDef } from '@/components/console/ConsoleDataTable.vue';
import type { Field, ConfigObject } from './configEditor';

export type ResourceDefinition = {
    title: string;
    tabs?: string[];
    columns: ColumnDef[];
    fields?: Field[];
    createFields?: Field[];
    defaults?: ConfigObject;
    params?: Record<string, string | number>;
    searchKey?: string;
    readOnly?: boolean;
    detail?: boolean;
};
const text = (key: string, label: string, required = false): Field => ({
    key,
    label,
    required,
});
const number = (key: string, label: string): Field => ({
    key,
    label,
    type: 'number',
});
const toggle = (key: string, label: string): Field => ({
    key,
    label,
    type: 'toggle',
});
const select = (
    key: string,
    label: string,
    values: Record<string, string>,
): Field => ({
    key,
    label,
    type: 'select',
    options: Object.entries(values).map(([value, label]) => ({ value, label })),
});
const columns = (values: Record<string, string>): ColumnDef[] =>
    Object.entries(values).map(([key, label]) => ({ key, label }));
const name = text('name', '名称', true),
    des = text('des', '说明'),
    enable = toggle('enable', '启用');
const binding = text(
    'bind_package',
    '适用基础套餐 ID（逗号分隔，留空表示全部）',
);
export const conditionFields: Field[] = [
    text('item', '匹配项', true),
    text('op', '运算符', true),
    text('value', '值'),
    text('value2', '第二个值'),
];
const dates = [
    text('start_at', '生效时间（YYYY-MM-DD HH:mm:ss）'),
    text('end_at', '结束时间（YYYY-MM-DD HH:mm:ss）'),
];

export const masterResources: Record<string, ResourceDefinition> = {
    'stream-groups': {
        title: '转发分组',
        columns: columns({ id: 'ID', name: '名称', des: '说明' }),
        fields: [name, des],
    },
    'l2-configs': {
        title: 'L2 配置',
        tabs: ['l2-configs', 'l2-conds', 'l2-nodes'],
        detail: true,
        columns: columns({
            id: 'ID',
            name: '名称',
            region_name: '区域',
            mode: '模式',
            balance_way: '负载方式',
            des: '说明',
        }),
        fields: [
            number('region_id', '区域 ID'),
            name,
            des,
            select('mode', '模式', { cache: '缓存', global: '全局' }),
            select('balance_way', '负载方式', {
                rr: '轮询',
                url_hash: 'URL 哈希',
            }),
        ],
        defaults: { mode: 'cache', balance_way: 'rr' },
    },
    'l2-conds': {
        title: 'L2 条件',
        tabs: ['l2-configs', 'l2-conds', 'l2-nodes'],
        detail: true,
        columns: columns({ id: 'ID', name: '名称', des: '说明' }),
        fields: [
            name,
            des,
            {
                key: 'data',
                label: '匹配条件',
                type: 'array',
                fields: conditionFields,
            },
        ],
        defaults: { data: [] },
    },
    'l2-nodes': {
        title: 'L2 节点绑定',
        tabs: ['l2-configs', 'l2-conds', 'l2-nodes'],
        detail: true,
        columns: columns({
            id: 'ID',
            l2_config_id: 'L2 配置',
            node_id: '节点',
            ip: 'IP',
            l2_cond_id: '条件',
        }),
        fields: [
            number('l2_config_id', 'L2 配置 ID'),
            number('node_id', '节点 ID'),
            number('l2_cond_id', '条件 ID'),
        ],
    },
    'traffic-packages': {
        title: '流量包',
        tabs: ['traffic-packages', 'user-traffic-packages'],
        detail: true,
        columns: columns({
            id: 'ID',
            name: '名称',
            amount: '流量',
            price: '价格',
            valid_days: '有效天数',
            bind_package: '适用套餐',
            enable: '启用',
        }),
        fields: [
            name,
            des,
            {
                ...text('amount', '流量', true),
                help: '与主控一致，例如 100GB 或 1TB。',
            },
            binding,
            number('valid_days', '有效期（天）'),
            number('price', '价格'),
            enable,
        ],
        defaults: { enable: 1 },
    },
    'user-traffic-packages': {
        title: '已分配流量包',
        tabs: ['traffic-packages', 'user-traffic-packages'],
        detail: true,
        columns: columns({
            id: 'ID',
            uid: '用户 ID',
            name: '名称',
            amount: '流量',
            expire: '到期时间',
            enable: '启用',
        }),
        fields: [
            name,
            binding,
            text('amount', '流量（例如 100GB）', true),
            text('expire', '到期时间', true),
            enable,
            text('reason', '修改原因', true),
        ],
        createFields: [
            number('uid', '用户 ID'),
            number('traffic_package_id', '流量包 ID'),
        ],
    },
    discounts: {
        title: '折扣',
        tabs: ['discounts', 'coupons', 'coupon-historys'],
        detail: true,
        columns: columns({
            id: 'ID',
            name: '名称',
            cate: '类别',
            discount_value: '折扣值',
            start_at: '开始时间',
            end_at: '结束时间',
            enable: '启用',
        }),
        fields: [
            name,
            des,
            select('cate', '生效类别', {
                package: '套餐',
                package_up: '升级包',
                traffic: '流量包',
            }),
            select('dis_type', '折扣类型', {
                discount: '折扣',
                price: '固定价格',
            }),
            text('discount_value', '折扣值'),
            number('month_price', '月付价格'),
            number('quarter_price', '季付价格'),
            number('year_price', '年付价格'),
            text('package', '基础套餐 ID（逗号分隔）'),
            text('package_up', '升级包 ID（逗号分隔）'),
            text('traffic_package', '流量包 ID（逗号分隔）'),
            text('user_group', '用户组 ID（逗号分隔）'),
            ...dates,
            number('priority', '优先级'),
            enable,
        ],
        defaults: { enable: 1 },
    },
    coupons: {
        title: '优惠码',
        tabs: ['discounts', 'coupons', 'coupon-historys'],
        detail: true,
        columns: columns({
            id: 'ID',
            name: '名称',
            code: '优惠码',
            coupon_type: '类型',
            max_times: '使用次数上限',
            start_at: '开始时间',
            end_at: '结束时间',
            enable: '启用',
        }),
        fields: [
            name,
            des,
            text('code', '优惠码', true),
            text('cate', '生效类别（逗号分隔）', true),
            select('coupon_type', '优惠类型', {
                price: '金额',
                discount: '折扣',
            }),
            text('discount_value', '折扣值'),
            number('price_gt', '最低消费'),
            number('price_value', '抵扣金额'),
            number('max_times', '最多使用次数'),
            toggle('persist_discount', '持续折扣'),
            text('package', '基础套餐 ID（逗号分隔）'),
            text('traffic_package', '流量包 ID（逗号分隔）'),
            ...dates,
            enable,
        ],
        defaults: { enable: 1, coupon_type: 'price' },
    },
    'coupon-historys': {
        title: '优惠码使用记录',
        tabs: ['discounts', 'coupons', 'coupon-historys'],
        readOnly: true,
        columns: columns({
            id: 'ID',
            uid: '用户 ID',
            code: '优惠码',
            name: '名称',
            created_at: '使用时间',
        }),
    },
    messages: {
        title: '公告管理',
        detail: true,
        params: { type: 'announcement' },
        columns: columns({
            id: 'ID',
            title: '标题',
            sort: '排序',
            is_show: '显示',
            is_popup: '弹窗',
            create_at: '创建时间',
        }),
        fields: [
            text('title', '标题', true),
            toggle('is_external', '使用外部链接'),
            text('url', '外部链接'),
            { key: 'content', label: '公告正文', type: 'textarea' },
            number('sort', '排序'),
            toggle('is_show', '显示'),
            toggle('is_popup', '弹窗'),
            toggle('is_red', '红色标题'),
            toggle('is_bold', '加粗标题'),
        ],
        defaults: {
            type: 'announcement',
            is_external: 0,
            is_show: 1,
            is_popup: 0,
            is_red: 0,
            is_bold: 0,
            sort: 100,
        },
    },
    tasks: {
        title: '任务管理',
        readOnly: true,
        columns: columns({
            id: 'ID',
            name: '任务',
            type: '类型',
            pry: '优先级',
            state: '状态',
            enable: '启用',
            start_at: '开始时间',
            end_at: '完成时间',
            err: '错误',
        }),
    },
    'attack-log': {
        title: 'WAF 攻击日志',
        readOnly: true,
        searchKey: 'host',
        columns: [
            {
                key: 'time',
                altKeys: ['timestamp', '@timestamp'],
                label: '时间',
            },
            { key: 'host', altKeys: ['host2'], label: '域名' },
            { key: 'client_ip', label: '客户端 IP' },
            { key: 'request_uri', label: '请求路径' },
            { key: 'rule_id', label: '规则' },
            { key: 'action', label: '处置' },
            { key: 'node_id', label: '节点' },
        ],
    },
    blackip: {
        title: '当前封禁',
        tabs: ['blackip', 'history-blackip'],
        readOnly: true,
        searchKey: 'ip',
        columns: columns({
            ip: 'IP',
            domain: '域名',
            reason: '原因',
            time: '时间',
            expire: '到期时间',
        }),
    },
    'history-blackip': {
        title: '拉黑日志',
        tabs: ['blackip', 'history-blackip'],
        readOnly: true,
        searchKey: 'ip',
        columns: columns({
            ip: 'IP',
            domain: '域名',
            reason: '原因',
            time: '时间',
        }),
    },
    'node-ip-log': {
        title: '节点 IP 日志',
        readOnly: true,
        searchKey: 'ip',
        columns: columns({
            id: 'ID',
            node_id: '节点',
            ip: 'IP',
            action: '动作',
            msg: '详情',
            create_at: '时间',
        }),
    },
    'package-monitor': {
        title: '套餐监控',
        readOnly: true,
        searchKey: 'uid',
        columns: columns({
            id: '套餐 ID',
            uid: '用户 ID',
            bandwidth_usage: '带宽用量',
            bandwidth_limit: '带宽限制',
            connection_usage: '连接数',
            connection_limit: '连接数限制',
        }),
    },
};
