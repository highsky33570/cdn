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

export function formatDate(value: unknown): string {
    const text = textValue(value);

    return text === '' ? '-' : text;
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
