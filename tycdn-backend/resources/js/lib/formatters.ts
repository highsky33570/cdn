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
 * Render a timestamp as ISO 8601 in the viewer's own timezone.
 *
 * Two very different shapes arrive here:
 *
 *   Laravel  "2026-09-08T00:01:08.000000Z"  — ISO, explicitly UTC
 *   CDNfly   "2026-09-04 18:23:03"          — no zone at all, server local time
 *
 * The first is converted to the browser's zone and stamped with that zone's
 * offset, so "2026-09-08T08:01:08+08:00" says both when it happened and which
 * clock it is being read on.
 *
 * The second gets the ISO shape but no offset. With no zone on the input there
 * is nothing to convert from, and appending the viewer's offset would assert a
 * moment the payload never claimed, shifting the value by hours for anyone
 * outside the panel's own zone. Anything unparseable passes through unchanged,
 * so a surprising format degrades to the raw string rather than "Invalid Date".
 */
export function formatDate(value: unknown): string {
    const text = textValue(value);

    if (text === '') {
        return '-';
    }

    const pad = (n: number) => String(n).padStart(2, '0');

    // zone-less "YYYY-MM-DD HH:mm:ss" — normalise the separator, add no offset
    if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/.test(text)) {
        const iso = text.replace(' ', 'T');

        return iso.length === 16 ? `${iso}:00` : iso;
    }

    const parsed = new Date(text);

    if (Number.isNaN(parsed.getTime())) {
        return text;
    }

    // getTimezoneOffset() counts minutes *behind* UTC, so the sign flips.
    const offset = -parsed.getTimezoneOffset();
    const zone =
        offset === 0
            ? 'Z'
            : `${offset < 0 ? '-' : '+'}${pad(Math.trunc(Math.abs(offset) / 60))}:${pad(Math.abs(offset) % 60)}`;

    return (
        `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}` +
        `T${pad(parsed.getHours())}:${pad(parsed.getMinutes())}:${pad(parsed.getSeconds())}${zone}`
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
