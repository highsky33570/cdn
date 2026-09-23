import type { CdnflyRecord } from './sharedTypes';
export type CcKind = 'rule' | 'matcher' | 'filter';
export const ccKinds: { key: CcKind; label: string }[] = [
    { key: 'rule', label: '规则组' },
    { key: 'matcher', label: '匹配器' },
    { key: 'filter', label: '过滤器' },
];
export const ccFilterLabels: Record<string, string> = {
    req_rate: '请求频率',
    browser_verify_auto: '无感验证',
    delay_jump_filter: '5秒盾',
    click_filter: '点击验证',
    easy_click_filter: '点击验证(简单)',
    slide_filter: '滑动验证',
    easy_slide_filter: '滑动验证(简单)',
    captcha_filter: '验证码',
    rotate_filter: '旋转图片',
    '302_challenge': '302跳转',
    url_auth: 'URL鉴权',
};
export function ccSystem(row: CdnflyRecord) {
    return Number(row.internal) === 1;
}
export function ccEnabled(value: unknown) {
    return Number(value) === 1;
}
export function ccStatus(row: CdnflyRecord): { label: string; tone: string } {
    if (!ccEnabled(row.enable)) {
        return { label: '禁用', tone: 'warning' };
    }

    if (row.state && row.state !== 'done') {
        return {
            label:
                (
                    {
                        pending: '待同步',
                        process: '同步中',
                        failed: '同步失败',
                    } as Record<string, string>
                )[String(row.state)] ?? String(row.state),
            tone: row.state === 'failed' ? 'danger' : 'warning',
        };
    }

    return { label: '正常', tone: 'success' };
}
export type CcFilters = {
    internal: string;
    is_show: string;
    enable: string;
    name: string;
    id: string;
    uid: string;
};
export function emptyCcFilters(): CcFilters {
    return { internal: '', is_show: '', enable: '', name: '', id: '', uid: '' };
}
export function ccQuery(
    kind: CcKind,
    filters: CcFilters,
    page: number,
    limit: number,
) {
    return {
        page,
        limit,
        ...Object.fromEntries(
            Object.entries(filters)
                .filter(
                    ([key, value]) =>
                        value.trim() !== '' &&
                        (key !== 'is_show' || kind === 'rule'),
                )
                .map(([key, value]) => [key, value.trim()]),
        ),
    };
}
export function ccSummary(rows: CdnflyRecord[], total: number) {
    return {
        all: total,
        system: rows.filter(ccSystem).length,
        custom: rows.filter((row) => !ccSystem(row)).length,
        shown: rows.filter((row) => ccEnabled(row.is_show)).length,
        disabled: rows.filter((row) => !ccEnabled(row.enable)).length,
    };
}
