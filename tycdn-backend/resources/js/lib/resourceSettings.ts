import type { CdnflyRecord } from './sharedTypes';

export type ResourceField = {
    type: 'site' | 'stream' | 'site_stream';
    name: string;
    label: string;
    kind: 'number' | 'text' | 'toggle';
    unit?: string;
    help?: string;
    regional?: boolean;
};
type ResourceCard = {
    title: string;
    help: string;
    wide?: boolean;
    fields: ResourceField[];
};
const field = (
    type: ResourceField['type'],
    name: string,
    label: string,
    extra: Partial<ResourceField> = {},
): ResourceField => ({ type, name, label, kind: 'number', ...extra });
export const resourceSections: Record<
    string,
    { label: string; title: string; help: string; cards: ResourceCard[] }
> = {
    site: {
        label: '网站',
        title: '网站资源限制',
        help: '控制站点配置、名单、缓存操作、日志下载和默认监听能力的全局上限。',
        cards: [
            {
                title: '配置限制',
                help: '限制相关配置项的最低数量和可放大倍数。',
                fields: [
                    field(
                        'site',
                        'related-config-min-limit',
                        '相关配置限制不低于',
                        { unit: '个' },
                    ),
                    field(
                        'site',
                        'related-config-max-times-limit',
                        '相关配置最大倍数',
                        { unit: '倍' },
                    ),
                ],
            },
            {
                title: '黑白名单',
                help: '限制单个站点可维护的黑名单和白名单 IP 数量。',
                fields: [
                    field('site', 'black-ip-limit', '黑名单 IP 数量限制', {
                        unit: '个',
                        regional: true,
                    }),
                    field('site', 'white-ip-limit', '白名单 IP 数量限制', {
                        unit: '个',
                        regional: true,
                    }),
                ],
            },
            {
                title: '缓存与解锁',
                help: '限制每日清理、预热和解锁次数，避免高频操作影响节点。',
                wide: true,
                fields: [
                    field('site', 'clean_url', '日清 URL 缓存次数', {
                        unit: '次/日',
                    }),
                    field('site', 'clean_dir', '日清目录缓存次数', {
                        unit: '次/日',
                    }),
                    field('site', 'pre_cache_url', '日预热 URL 次数', {
                        unit: '次/日',
                    }),
                    field('site', 'ip-unlock-max-limit', '日解锁 IP 次数', {
                        unit: '次/日',
                    }),
                    field(
                        'site',
                        'ip-unlock-max-per-limit',
                        '每次解锁 IP 个数',
                        { unit: '个/次' },
                    ),
                ],
            },
            {
                title: '规则与域名',
                help: '限制规则组和单站域名数量、默认监听可直接开关。',
                fields: [
                    field('site', 'cc-rule-max-limit', '单个 CC 规则组', {
                        unit: '条',
                    }),
                    field(
                        'site',
                        'max-domain-persite-limit',
                        '单站最大域名数',
                        { unit: '个' },
                    ),
                    field(
                        'site',
                        'listen-default-http-80',
                        '默认监听 80 端口',
                        { kind: 'toggle', regional: true },
                    ),
                ],
            },
            {
                title: '下载日志',
                help: '控制访问日志下载次数、临时目录和文件保留时长。',
                fields: [
                    field(
                        'site',
                        'download-access-log-limit',
                        '每天允许下载次数',
                        { unit: '次/日' },
                    ),
                    field(
                        'site',
                        'download-access-log-tmp-dir',
                        '日志文件存放目录',
                        { kind: 'text' },
                    ),
                    field(
                        'site',
                        'download-access-log-retain',
                        '日志文件存放时长',
                        { unit: '小时' },
                    ),
                ],
            },
        ],
    },
    stream: {
        label: '转发',
        title: '转发资源限制',
        help: '控制四层转发端口、相关配置项和 ACL 规则数量。',
        cards: [
            {
                title: '端口限制',
                help: '设置转发业务禁止使用的端口。',
                fields: [
                    field('stream', 'custom-port-not-allow', '禁用的端口', {
                        kind: 'text',
                        help: '多个端口使用英文逗号分隔。',
                        regional: true,
                    }),
                ],
            },
            {
                title: '配置与 ACL',
                help: '限制转发相关配置数量和 ACL 规则数。',
                fields: [
                    field(
                        'stream',
                        'related-config-min-limit',
                        '相关配置限制不低于',
                        { unit: '个' },
                    ),
                    field(
                        'stream',
                        'related-config-max-times-limit',
                        '相关配置最大倍数',
                        { unit: '倍' },
                    ),
                    field('stream', 'acl-max-limit', 'ACL 规则数限制', {
                        unit: '条',
                    }),
                ],
            },
        ],
    },
    common: {
        label: '公共',
        title: '公共端口限制',
        help: '统一控制站点和转发共用的自定义端口允许与禁止范围。',
        cards: [
            {
                title: '自定义端口',
                help: '禁止列表优先生效，允许列表用于开放可配置范围。',
                wide: true,
                fields: [
                    field(
                        'site_stream',
                        'custom-port-not-allow',
                        '禁用的自定义端口',
                        {
                            kind: 'text',
                            help: '多个端口使用英文逗号分隔。',
                            regional: true,
                        },
                    ),
                    field(
                        'site_stream',
                        'custom-port-allow',
                        '允许的自定义端口',
                        {
                            kind: 'text',
                            help: '多个端口使用英文逗号分隔。',
                            regional: true,
                        },
                    ),
                ],
            },
        ],
    },
};
export const resourceFields = Object.values(resourceSections).flatMap(
    (section) => section.cards.flatMap((card) => card.fields),
);
export const resourceFieldKey = (field: Pick<ResourceField, 'type' | 'name'>) =>
    `${field.type}:${field.name}`;
export const resourceRowKey = (row: CdnflyRecord) =>
    `${row.scope_name ?? 'global'}:${row.scope_id ?? 0}:${row.type}:${row.name}`;
export const resourceFieldFor = (row: CdnflyRecord) =>
    resourceFields.find(
        (field) => field.type === row.type && field.name === row.name,
    );
export function resourceValueError(
    field: ResourceField,
    value: string,
): string {
    if (
        field.kind === 'number' &&
        (!/^\d+$/.test(value) || !Number.isSafeInteger(Number(value)))
    ) {
        return '请输入大于或等于 0 的整数';
    }

    if (field.kind === 'toggle' && !['0', '1'].includes(value)) {
        return '请选择开启或关闭';
    }

    if (field.name === 'download-access-log-tmp-dir' && !value.trim()) {
        return '请输入日志文件存放目录';
    }

    return '';
}
