import { apiRequest } from './apiRequest';
import type { CdnflyListData } from './sharedTypes';

export function masterGet(
    resource: string,
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    const query = new URLSearchParams(
        Object.entries(params).map(([key, value]) => [key, String(value)]),
    );

    return apiRequest(`/api/admin/workspace/${resource}?${query}`);
}
