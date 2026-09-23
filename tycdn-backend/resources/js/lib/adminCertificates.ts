import type { CdnflyRecord } from './sharedTypes';
export const certificateTypes: Record<string, string> = {
    custom: '自己上传',
    lets: "Let's Encrypt",
    zerossl: 'ZeroSSL',
};
export function certificateExpiry(row: CdnflyRecord): number | null {
    const raw = row.expire_time2 || row.expire_time;

    if (!raw) {
        return null;
    }

    const time =
        typeof raw === 'number'
            ? raw < 1e10
                ? raw * 1000
                : raw
            : Date.parse(String(raw).replace(' ', 'T'));

    return Number.isFinite(time) ? time : null;
}
export function certificateDays(
    row: CdnflyRecord,
    now = Date.now(),
): number | null {
    const time = certificateExpiry(row);

    return time === null ? null : Math.ceil((time - now) / 86400000);
}
export function certificateExpiryHint(
    row: CdnflyRecord,
    now = Date.now(),
): string {
    const days = certificateDays(row, now);

    return days === null
        ? ''
        : days < 0
          ? `已过期 ${Math.abs(days)} 天`
          : days === 0
            ? '今天到期'
            : `还剩 ${days} 天`;
}
export function certificateStatus(row: CdnflyRecord): {
    text: string;
    tone: string;
    tip: string;
} {
    const tip = String(row.task_ret ?? '');

    if (String(row.enable) === '0') {
        return { text: '禁用', tone: 'danger', tip };
    }

    if (
        row.type !== 'custom' &&
        row.task_enable !== null &&
        row.task_enable !== undefined &&
        String(row.task_enable) === '0'
    ) {
        return { text: '签发失败，已取消', tone: 'danger', tip };
    }

    if (
        row.type !== 'custom' &&
        row.issue_state &&
        row.issue_state !== 'done'
    ) {
        return {
            text:
                row.issue_state === 'failed'
                    ? '签发失败，重试中'
                    : row.issue_state === 'pending'
                      ? '待签发'
                      : '签发中',
            tone: row.issue_state === 'failed' ? 'danger' : 'warning',
            tip,
        };
    }

    if (row.sync_state && row.sync_state !== 'done') {
        return {
            text:
                row.sync_state === 'failed'
                    ? '同步失败'
                    : row.sync_state === 'pending'
                      ? '待同步'
                      : '同步中',
            tone: row.sync_state === 'failed' ? 'danger' : 'warning',
            tip,
        };
    }

    return { text: '正常', tone: 'success', tip: '' };
}
export function certificateSummary(rows: CdnflyRecord[], now = Date.now()) {
    return {
        all: rows.length,
        normal: rows.filter((r) => certificateStatus(r).tone === 'success')
            .length,
        expiring: rows.filter((r) => {
            const days = certificateDays(r, now);

            return days !== null && days >= 0 && days <= 30;
        }).length,
        expired: rows.filter((r) => {
            const time = certificateExpiry(r);

            return time !== null && time < now;
        }).length,
        renew: rows.filter((r) => Number(r.auto_renew) === 1).length,
        noRenew: rows.filter((r) => Number(r.auto_renew) !== 1).length,
    };
}
