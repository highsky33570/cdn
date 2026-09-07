export function buildUrl(
    base: string,
    params: Record<string, string | number>,
): string {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== '') {
            search.set(key, String(value));
        }
    });

    const qs = search.toString();

    return qs === '' ? base : `${base}?${qs}`;
}
