import { apiRequest } from '@/lib/apiRequest';
import { buildUrl } from '@/lib/urlHelpers';

export type LocalBillingProduct = {
    id: number;
    name: string;
    slug?: string | null;
    description?: string | null;
    price_monthly?: string | number | null;
    price_quarterly?: string | number | null;
    price_yearly?: string | number | null;
    currency?: string | null;
    features?: unknown[];
};

export type LocalBillingServiceInstance = {
    id: number;
    status: string;
    service_name?: string | null;
    product_id?: number | null;
    product_name?: string | null;
    product_slug?: string | null;
    order_no?: string | null;
    order_status?: string | null;
    gateway_provider?: string | null;
    cdnfly_user_id?: string | number | null;
    cdnfly_service_id?: string | number | null;
    opened_at?: string | null;
    expired_at?: string | null;
    queue_stage?: string | null;
    queue_code?: string | null;
    queue_reason?: string | null;
    failure_stage?: string | null;
    failure_code?: string | null;
    last_error?: string | null;
    last_attempt_at?: string | null;
};

export type LocalBillingOrder = {
    id: number;
    order_no: string;
    product_id?: number | null;
    product_name?: string | null;
    product_slug?: string | null;
    order_type?: string | null;
    billing_cycle?: string | null;
    quantity?: number | null;
    target_service_instance_id?: number | null;
    fiat_amount?: string | number | null;
    fiat_currency?: string | null;
    gateway_provider?: string | null;
    gateway_status?: string | null;
    gateway_payment_url?: string | null;
    gateway_trade_id?: string | null;
    status: string;
    pay_currency?: string | null;
    pay_address?: string | null;
    actual_paid_amount?: string | number | null;
    amount_usdt?: string | number | null;
    created_at?: string | null;
    paid_at?: string | null;
    provisioned_at?: string | null;
    gateway_expired_at?: string | null;
    service_instance?: LocalBillingServiceInstance | null;
};

export type LocalBillingCheckout = {
    order_no: string;
    order_id: number;
    product_id: number;
    product_name?: string | null;
    status: string;
    gateway_provider: string;
    trade_id?: string | null;
    amount?: string | number | null;
    currency?: string | null;
    actual_amount?: string | number | null;
    receive_address?: string | null;
    token?: string | null;
    network?: string | null;
    expiration_time?: string | number | null;
    payment_url?: string | null;
    request_id?: string | null;
};

export type LocalBillingList<T> = {
    items: T[];
    total: number;
    page: number;
    per_page: number;
};

export type LocalBillingOrderPayload = {
    product_id: number;
    order_type?: string | null;
    billing_cycle?: string | null;
    quantity?: number | null;
    service_instance_id?: number | null;
    fiat_currency?: string | null;
};

export async function listBillingProducts(): Promise<LocalBillingProduct[]> {
    return apiRequest<LocalBillingProduct[]>('/api/products');
}

export async function listBillingOrders(
    params: Record<string, string | number> = {},
): Promise<LocalBillingList<LocalBillingOrder>> {
    return apiRequest<LocalBillingList<LocalBillingOrder>>(
        buildUrl('/api/orders', params),
    );
}

export async function getBillingOrder(
    orderNo: string,
): Promise<LocalBillingOrder> {
    return apiRequest<LocalBillingOrder>(`/api/orders/${orderNo}`);
}

export async function createBillingOrder(
    payload: LocalBillingOrderPayload,
): Promise<LocalBillingCheckout> {
    return apiRequest<LocalBillingCheckout>('/api/orders', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function provisionBillingOrder(
    orderNo: string,
): Promise<Record<string, unknown>> {
    return apiRequest<Record<string, unknown>>(
        `/api/orders/${orderNo}/provision`,
        {
            method: 'POST',
        },
    );
}

export async function listBillingServiceInstances(
    params: Record<string, string | number> = {},
): Promise<LocalBillingList<LocalBillingServiceInstance>> {
    return apiRequest<LocalBillingList<LocalBillingServiceInstance>>(
        buildUrl('/api/service-instances', params),
    );
}

