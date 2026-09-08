export function textValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return '';
}

export function numberValue(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);

        return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
}

export function recordId(record: Record<string, unknown>): number | null {
    return numberValue(record.id);
}

export function jsonText(value: unknown, fallback: string): string {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();

        if (trimmed === '') {
            return fallback;
        }

        try {
            return JSON.stringify(JSON.parse(trimmed), null, 2);
        } catch {
            return trimmed;
        }
    }

    return JSON.stringify(value, null, 2);
}

export function parseJsonObject(value: string): Record<string, unknown> {
    const parsed = JSON.parse(value);

    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        throw new Error('JSON 必须是对象');
    }

    return parsed as Record<string, unknown>;
}

export function parseJsonArray(value: string): unknown[] {
    const parsed = JSON.parse(value);

    if (!Array.isArray(parsed)) {
        throw new Error('JSON 必须是数组');
    }

    return parsed;
}

/**
 * Render a timestamp in the viewer's own timezone.
 *
 * Two very different shapes arrive here:
 *
 *   Laravel  "2026-09-08T00:01:08.000000Z"  — ISO, explicitly UTC
 *   CDNfly   "2026-09-04 18:23:03"          — no zone at all, server local time
 *
 * The first is converted to local time. The second is returned untouched: with
 * no offset there is nothing to convert from, and guessing would silently shift
 * the value by hours. Anything unparseable also passes through unchanged, so a
 * surprising format degrades to the raw string rather than "Invalid Date".
 */
export function formatDate(value: unknown): string {
    const text = textValue(value);

    if (text === '') {
        return '-';
    }

    // zone-less "YYYY-MM-DD HH:mm:ss" — display as given
    if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/.test(text)) {
        return text.replace('T', ' ');
    }

    const parsed = new Date(text);

    if (Number.isNaN(parsed.getTime())) {
        return text;
    }

    const pad = (n: number) => String(n).padStart(2, '0');

    return (
        `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())} ` +
        `${pad(parsed.getHours())}:${pad(parsed.getMinutes())}:${pad(parsed.getSeconds())}`
    );
}

export function formatMoney(
    amount: string | number | null | undefined,
    currency: string | null | undefined,
): string {
    if (amount === null || amount === undefined || amount === '') {
        return '-';
    }

    const numeric = Number(amount);

    if (!Number.isFinite(numeric)) {
        return String(amount);
    }

    return `${numeric.toFixed(2)} ${currency ?? 'USD'}`;
}

export function yesNo(value: unknown): string {
    return value === 1 || value === true || value === '1' ? '启用' : '禁用';
}

export function getErrorMessage(error: unknown): string {
    return error instanceof Error ? error.message : '请求失败';
}
