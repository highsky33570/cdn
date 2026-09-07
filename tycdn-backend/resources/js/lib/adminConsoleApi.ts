import { apiRequest } from '@/lib/apiRequest';
import { buildUrl } from '@/lib/urlHelpers';

export type { Paginated } from '@/lib/sharedTypes';
import type { Paginated } from '@/lib/sharedTypes';

export type AdminRole = 'admin' | 'user';

export type AdminOverviewMetrics = {
    users_total: number;
    admins_total: number;
    cdnfly_mapped_users: number;
    api_key_ready_users: number;
    orders_total: number;
    orders_pending: number;
    orders_paid: number;
    services_total: number;
    services_active: number;
    services_failed: number;
    active_products: number;
};

export type AdminOverview = {
    metrics: AdminOverviewMetrics;
    cdnfly: {
        users_total: number | null;
        packages_total: number | null;
        user_packages_total: number | null;
        nodes_total: number | null;
        pending_nodes_total: number | null;
        sites_total: number | null;
        streams_total: number | null;
        certs_total: number | null;
        acls_total: number | null;
        dns_apis_total: number | null;
        regions_total: number | null;
        node_groups_total: number | null;
        package_groups_total: number | null;
        cname_domains_total: number | null;
        stream_groups_total: number | null;
        lines_total: number | null;
    };
    recent_users: AdminUserRecord[];
    recent_orders: AdminRecentOrder[];
    alerts: AdminOverviewAlert[];
};

export type AdminOverviewAlert = {
    level: 'info' | 'warning' | 'danger' | string;
    title: string;
    detail: string;
};

export type AdminRecentOrder = {
    id: number;
    order_no: string;
    user_id: number | null;
    status: string;
    gateway_status: string | null;
    amount_usdt: string;
    created_at: string | null;
};

export type AdminUserRecord = {
    id: number;
    name: string;
    email: string;
    role: AdminRole | string;
    email_verified: boolean;
    email_verified_at: string | null;
    cdnfly_user_id: number | null;
    cdnfly_synced_at: string | null;
    has_api_key: boolean;
    orders_count: number;
    service_instances_count: number;
    created_at: string | null;
    updated_at: string | null;
};

export type AdminUserUpdatePayload = {
    name: string;
    email: string;
    role: AdminRole;
    cdnfly_user_id: number | null;
    email_verified: boolean;
};

export type AdminUserListParams = {
    page?: number;
    per_page?: number;
    search?: string;
    role?: AdminRole;
};

export async function getAdminOverview(): Promise<AdminOverview> {
    return apiRequest<AdminOverview>('/api/admin/overview');
}

export async function listAdminUsers(
    params: AdminUserListParams = {},
): Promise<Paginated<AdminUserRecord>> {
    return apiRequest<Paginated<AdminUserRecord>>(
        buildUrl('/api/admin/users', params as Record<string, string | number>),
    );
}

export type AdminUserCreatePayload = {
    name: string;
    email: string;
    password: string;
    role: AdminRole;
};

export async function createAdminUser(
    payload: AdminUserCreatePayload,
): Promise<AdminUserRecord> {
    return apiRequest<AdminUserRecord>('/api/admin/users', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function deleteAdminUser(id: number): Promise<void> {
    await apiRequest<unknown>(`/api/admin/users/${id}`, {
        method: 'DELETE',
    });
}

export async function updateAdminUser(
    id: number,
    payload: AdminUserUpdatePayload,
): Promise<AdminUserRecord> {
    return apiRequest<AdminUserRecord>(`/api/admin/users/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function syncAdminUserApiKey(id: number): Promise<void> {
    await apiRequest<unknown>(`/api/admin/users/${id}/sync-api-key`, {
        method: 'POST',
    });
}

export async function rechargeAdminUser(
    id: number,
    amount: string,
): Promise<unknown> {
    return apiRequest<unknown>(`/api/admin/users/${id}/recharge`, {
        method: 'POST',
        body: JSON.stringify({ amount }),
    });
}
