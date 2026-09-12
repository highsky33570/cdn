import { apiRequest } from '@/lib/apiRequest';

export type AdminPackagePayload = Record<string, unknown>;

export type AdminPackageBatchItem = AdminPackagePayload & {
    id: number;
};

export type AdminPackageBatchResult = {
    updated: unknown[];
    failed: Array<{
        id: number;
        message: string;
    }>;
    updated_count: number;
    failed_count: number;
};

export type AdminPackageOption = {
    id: string | number;
    name: string;
};

export type AdminPackageOptions = {
    regions: AdminPackageOption[];
    node_groups: AdminPackageOption[];
    package_groups: AdminPackageOption[];
    cname_domains: AdminPackageOption[];
};

export async function listAdminPackages(
    params: Record<string, string | number | boolean> = {},
): Promise<unknown> {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        search.set(key, String(value));
    });

    const suffix = search.toString() === '' ? '' : `?${search.toString()}`;

    return apiRequest<unknown>(`/api/admin/packages${suffix}`);
}

export async function listAdminPackageOptions(): Promise<AdminPackageOptions> {
    return apiRequest<AdminPackageOptions>('/api/admin/package-options');
}

export async function getAdminPackage(id: number): Promise<unknown> {
    return apiRequest<unknown>(`/api/admin/packages/${id}`);
}

export async function createAdminPackage(
    payload: AdminPackagePayload,
): Promise<unknown> {
    return apiRequest<unknown>('/api/admin/packages', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function updateAdminPackage(
    id: number,
    payload: AdminPackagePayload,
): Promise<unknown> {
    return apiRequest<unknown>(`/api/admin/packages/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function batchUpdateAdminPackages(
    packages: AdminPackageBatchItem[],
): Promise<AdminPackageBatchResult> {
    return apiRequest<AdminPackageBatchResult>('/api/admin/packages/batch', {
        method: 'PUT',
        body: JSON.stringify({ packages }),
    });
}

export async function deleteAdminPackage(id: number): Promise<unknown> {
    return apiRequest<unknown>(`/api/admin/packages/${id}`, {
        method: 'DELETE',
    });
}

// ─── 门户商品 ─────────────────────────────────────────
/**
 * What a CDNfly package is actually sold as.
 *
 * CDNfly's own month/quarter/year prices bill against the customer's CDNfly
 * balance, which a portal order never credits — so those stay at 0 and these
 * are the prices customers pay.
 */
export interface AdminPackageProduct {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_monthly: number;
    price_quarterly: number;
    price_yearly: number;
    currency: string;
    is_active: boolean;
    sort_order: number;
    features: string[];
}

/** Keyed by CDNfly package id. */
export async function listAdminPackageProducts(): Promise<
    Record<string, AdminPackageProduct>
> {
    return apiRequest<Record<string, AdminPackageProduct>>(
        '/api/admin/package-products',
    );
}

export function saveAdminPackageProduct(
    packageId: number | string,
    portal: Record<string, unknown>,
) {
    return apiRequest(`/api/admin/package-products/${packageId}`, {
        method: 'PUT',
        body: JSON.stringify({ portal }),
    });
}
