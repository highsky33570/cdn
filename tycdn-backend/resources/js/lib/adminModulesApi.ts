import { apiRequest } from '@/lib/apiRequest';
import { buildUrl } from '@/lib/urlHelpers';

export type {
    CdnflyRecord,
    CdnflyListData,
    Paginated,
} from '@/lib/sharedTypes';
import type {
    CdnflyRecord,
    CdnflyListData,
    Paginated,
} from '@/lib/sharedTypes';
export type AdminNodePayload = {
    name: string;
    ip: string;
    node_group_id?: number | null;
    region_id?: number | null;
    line_id?: number | null;
    status?: number | null;
    weight?: number | null;
    bandwidth?: number | null;
    des?: string | null;
};
export type AdminPendingNodeInitPayload = {
    pending_node_id: number;
    region_id: number;
    name: string;
    des: string;
    type: 'L1' | 'L2';
};
export type AdminNodeInstallCommand = {
    command: string | null;
    version_name: string;
    master_ip: string;
    es_ip: string;
    master_host: string;
    master_port: string;
    cc_img_url: string;
    has_es_pwd: boolean;
    cdnfly_outbound_disabled: boolean;
};

// ─── Order / Service types ─────────────────────────────
export type AdminOrderRecord = {
    id: number;
    order_no: string;
    user_id: number | null;
    user_name: string | null;
    user_email: string | null;
    product_id: number | null;
    product_name: string | null;
    product_slug: string | null;
    product_snapshot: unknown;
    order_type: string | null;
    billing_cycle: string | null;
    quantity: number | null;
    status: string;
    gateway_status: string | null;
    amount_usdt: string;
    amount_cny: string;
    epusdt_order_id: string | null;
    epusdt_trade_id: string | null;
    expire_at: string | null;
    paid_at: string | null;
    provisioned_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type AdminServiceRecord = {
    id: number;
    user_id: number | null;
    user_name: string | null;
    user_email: string | null;
    order_no: string | null;
    source_order_id: number | null;
    product_id: number | null;
    product_name: string | null;
    product_slug: string | null;
    service_name: string | null;
    cdnfly_user_id: string | null;
    cdnfly_service_id: string | null;
    status: string;
    error_message: string | null;
    opened_at: string | null;
    expired_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

// ─── Nodes ─────────────────────────────────────────────
export async function listAdminNodes(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl('/api/admin/nodes', params));
}

export async function getAdminNodeInstallCommand(): Promise<AdminNodeInstallCommand> {
    return apiRequest<AdminNodeInstallCommand>(
        '/api/admin/node-install-command',
    );
}

export async function listAdminPendingNodes(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(
        buildUrl('/api/admin/pending-nodes', params),
    );
}

export async function deleteAdminPendingNode(
    id: number,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/pending-nodes/${id}`, {
        method: 'DELETE',
    });
}

export async function initializeAdminPendingNode(
    payload: AdminPendingNodeInitPayload,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>('/api/admin/nodes', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function getAdminNode(id: number): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/nodes/${id}`);
}

export async function createAdminNode(
    payload: AdminNodePayload,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>('/api/admin/nodes', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function updateAdminNode(
    id: number,
    payload: Partial<AdminNodePayload>,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/nodes/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function setAdminNodeEnabled(
    id: number,
    enable: boolean,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/nodes/${id}/enable`, {
        method: 'PUT',
        body: JSON.stringify({ enable }),
    });
}

export async function deleteAdminNode(id: number): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/nodes/${id}`, {
        method: 'DELETE',
    });
}

// ─── Node Groups ──────────────────────────────────────
export type AdminNodeGroupPayload = {
    region_id: number;
    name: string;
    des?: string;
    backup_switch_type?: 'master_down' | 'interval';
    backup_switch_policy?: string;
};

export async function listAdminNodeGroups(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(
        buildUrl('/api/admin/node-groups', params),
    );
}

export async function createAdminNodeGroup(
    payload: AdminNodeGroupPayload,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>('/api/admin/node-groups', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function updateAdminNodeGroup(
    id: number,
    payload: Partial<AdminNodeGroupPayload>,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/node-groups/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function deleteAdminNodeGroup(id: number): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/node-groups/${id}`, {
        method: 'DELETE',
    });
}

// ─── Regions ──────────────────────────────────────────
export type AdminRegionPayload = {
    name: string;
    des?: string;
    sort?: number;
    l2_check_port?: number;
};

export async function listAdminRegions(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl('/api/admin/regions', params));
}

export async function createAdminRegion(
    payload: AdminRegionPayload,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>('/api/admin/regions', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function updateAdminRegion(
    id: number,
    payload: Partial<AdminRegionPayload>,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/regions/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function deleteAdminRegion(id: number): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/regions/${id}`, {
        method: 'DELETE',
    });
}

// ─── Lines ────────────────────────────────────────────
export type AdminBackupSwitchPolicy = {
    ip_num?: number;
    interval?: number;
    switch_order?: 'rand' | 'seq';
};

export type AdminLinePayload = {
    region_id: number;
    name: string;
    cname_hostname?: string;
    des?: string;
    sort?: number;
    backup_switch_type?: 'master_down' | 'interval';
    backup_switch_policy?: string;
    l2_config_id?: string;
};

export async function listAdminLines(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl('/api/admin/lines', params));
}

export async function createAdminLine(
    payload: AdminLinePayload,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>('/api/admin/lines', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function updateAdminLine(
    id: number,
    payload: Partial<AdminLinePayload>,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/lines/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function deleteAdminLine(id: number): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/lines/${id}`, {
        method: 'DELETE',
    });
}

// ─── Sites (admin) ─────────────────────────────────────
export async function listAdminSites(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl('/api/admin/sites', params));
}

export async function getAdminSite(id: number): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/sites/${id}`);
}

export async function setAdminSiteEnabled(
    id: number,
    enable: boolean,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/sites/${id}/enable`, {
        method: 'PUT',
        body: JSON.stringify({ enable }),
    });
}

export type AdminSitePayload = {
    user_package: number;
    domain: string;
    backend: { addr: string; weight?: number; state?: string }[];
    groups?: string;
};

export async function createAdminSite(
    payload: AdminSitePayload,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>('/api/admin/sites', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function updateAdminSite(
    id: number,
    payload: Partial<AdminSitePayload>,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/admin/sites/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function deleteAdminSite(id: number): Promise<void> {
    await apiRequest<unknown>(`/api/admin/sites/${id}`, {
        method: 'DELETE',
    });
}

// ─── Certs CRUD ───────────────────────────────────────
export type AdminCertPayload = {
    name: string;
    des?: string;
    type: 'custom' | 'lets' | 'zerossl';
    dnsapi?: string;
    domain?: string;
    key?: string;
    cert?: string;
    auto_renew?: boolean | number;
    enable?: boolean | number;
    reissue?: string;
};

export async function listAdminAllCerts(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl('/api/admin/all-certs', params));
}

export function createAdminCert(data: AdminCertPayload) {
    return apiRequest('/api/admin/all-certs', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export function updateAdminCert(id: number, data: Partial<AdminCertPayload>) {
    return apiRequest(`/api/admin/all-certs/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export function deleteAdminCert(id: number) {
    return apiRequest(`/api/admin/all-certs/${id}`, { method: 'DELETE' });
}

export async function listAdminAllAcls(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl('/api/admin/all-acls', params));
}

// ─── Streams ───────────────────────────────────────────
export type AdminStreamPayload = {
    user_package: number;
    listen: string;
    balance_way?: string;
    proxy_protocol?: boolean | number;
    backend_port: number;
    backend: string;
    conn_limit?: number | string;
    acl?: string;
    enable?: boolean | number;
};

export async function listAdminStreams(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl('/api/admin/streams', params));
}

export function createAdminStream(data: AdminStreamPayload) {
    return apiRequest('/api/admin/streams', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export function updateAdminStream(
    id: number,
    data: Partial<AdminStreamPayload>,
) {
    return apiRequest(`/api/admin/streams/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

// ─── Stream Groups ────────────────────────────────────
export type AdminStreamGroupPayload = {
    name: string;
    des?: string;
};

export async function listAdminStreamGroups(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(
        buildUrl('/api/admin/stream-groups', params),
    );
}

export function createAdminStreamGroup(data: AdminStreamGroupPayload) {
    return apiRequest('/api/admin/stream-groups', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export function updateAdminStreamGroup(
    id: number,
    data: Partial<AdminStreamGroupPayload>,
) {
    return apiRequest(`/api/admin/stream-groups/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export function deleteAdminStreamGroup(id: number) {
    return apiRequest(`/api/admin/stream-groups/${id}`, { method: 'DELETE' });
}

// ─── DNS ───────────────────────────────────────────────
/**
 * DNS provider credentials.
 *
 * Routed through the proxy, not /api/admin/dns-apis: CDNfly v6 documents
 * /v1/dnsapis under the *user* scope only, and the admin routes sent the master
 * api-key, which the endpoint refuses — the 新增 button failed with a 502 that
 * looked like a connectivity problem. The proxy sends the caller's own CDNfly
 * credentials, which is what these endpoints expect, and it is the reseller's
 * own DNS credential either way.
 */
const DNS_APIS = '/api/cdn/proxy/v1/dnsapis';

export async function listAdminDnsApis(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl(DNS_APIS, params));
}

export async function listAdminDnsLines(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl('/api/admin/dns-lines', params));
}

// ─── Finance ───────────────────────────────────────────
export async function listAdminOrders(
    params: Record<string, string | number> = {},
): Promise<Paginated<AdminOrderRecord>> {
    return apiRequest<Paginated<AdminOrderRecord>>(
        buildUrl('/api/admin/orders', params),
    );
}

export async function listAdminServices(
    params: Record<string, string | number> = {},
): Promise<Paginated<AdminServiceRecord>> {
    return apiRequest<Paginated<AdminServiceRecord>>(
        buildUrl('/api/admin/services', params),
    );
}

export function updateAdminOrderStatus(id: number, status: string) {
    return apiRequest(`/api/admin/orders/${id}/status`, {
        method: 'PUT',
        body: JSON.stringify({ status }),
    });
}

export async function listAdminCdnflyUserPackages(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(
        buildUrl('/api/admin/cdnfly-user-packages', params),
    );
}

export type AdminUserPackagePayload = {
    uid: number;
    package: number;
    duration: string;
    name?: string;
    coupon_code?: string;
};

export function createAdminUserPackage(data: AdminUserPackagePayload) {
    return apiRequest('/api/admin/cdnfly-user-packages', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export function updateAdminUserPackage(
    id: number,
    data: Record<string, unknown>,
) {
    return apiRequest(`/api/admin/cdnfly-user-packages/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export function deleteAdminUserPackage(id: number) {
    return apiRequest(`/api/admin/cdnfly-user-packages/${id}`, {
        method: 'DELETE',
    });
}

export async function listAdminUserPackageUpgrades(
    id: number,
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(
        buildUrl(`/api/admin/cdnfly-user-packages/${id}/upgrades`, params),
    );
}

export function addAdminUserPackageUpgrade(id: number, packageUpId: number) {
    return apiRequest(`/api/admin/cdnfly-user-packages/${id}/upgrades`, {
        method: 'POST',
        body: JSON.stringify({ package_up_id: packageUpId }),
    });
}

export function removeAdminUserPackageUpgrade(id: number, upgradeId: number) {
    return apiRequest(
        `/api/admin/cdnfly-user-packages/${id}/upgrades/${upgradeId}`,
        { method: 'DELETE' },
    );
}

// ─── Package Groups ──────────────────────────────────
export async function listAdminPackageGroups(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(
        buildUrl('/api/admin/package-groups', params),
    );
}

export function createAdminPackageGroup(data: Record<string, unknown>) {
    return apiRequest('/api/admin/package-groups', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export function updateAdminPackageGroup(
    id: number,
    data: Record<string, unknown>,
) {
    return apiRequest(`/api/admin/package-groups/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export function deleteAdminPackageGroup(id: number) {
    return apiRequest(`/api/admin/package-groups/${id}`, { method: 'DELETE' });
}

// ─── Package Ups ─────────────────────────────────────
export async function listAdminPackageUps(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(
        buildUrl('/api/admin/package-ups', params),
    );
}

export function createAdminPackageUp(data: Record<string, unknown>) {
    return apiRequest('/api/admin/package-ups', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export function updateAdminPackageUp(
    id: number,
    data: Record<string, unknown>,
) {
    return apiRequest(`/api/admin/package-ups/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export function deleteAdminPackageUp(id: number) {
    return apiRequest(`/api/admin/package-ups/${id}`, { method: 'DELETE' });
}

// ─── Monitor / Logs ────────────────────────────────────
export async function listAdminLoginLogs(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(
        buildUrl('/api/admin/logs/login', params),
    );
}

export async function listAdminOpLogs(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl('/api/admin/logs/op', params));
}

/**
 * Realtime monitoring.
 *
 * Routed through the proxy because CDNfly v6 places
 * /v1/monitor/site/realtime and /v1/monitor/stream/realtime under the *user*
 * scope; the admin routes sent the master api-key and came back 502, which is
 * what the 加载失败 banners on this page were.
 *
 * Known limit: v6 has no panel-wide equivalent — its admin monitor endpoints
 * cover nodes and user packages, not every customer's sites. So this shows the
 * signed-in operator's own sites and streams, not the whole platform's.
 */
export async function getAdminSiteRealtime(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(
        buildUrl('/api/cdn/proxy/v1/monitor/site/realtime', params),
    );
}

export async function getAdminStreamRealtime(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(
        buildUrl('/api/cdn/proxy/v1/monitor/stream/realtime', params),
    );
}

// ─── Admin DNS API CRUD ──────────────────────────────
export function createAdminDnsApi(data: Record<string, unknown>) {
    return apiRequest(DNS_APIS, { method: 'POST', body: JSON.stringify(data) });
}
export function updateAdminDnsApi(id: number, data: Record<string, unknown>) {
    return apiRequest(`${DNS_APIS}/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}
export function deleteAdminDnsApi(id: number) {
    return apiRequest(`${DNS_APIS}/${id}`, { method: 'DELETE' });
}

// ─── Admin Stream enable/delete ──────────────────────
export function setAdminStreamEnabled(id: number, enable: number) {
    return apiRequest(`/api/admin/streams/${id}/enable`, {
        method: 'PUT',
        body: JSON.stringify({ enable }),
    });
}
export function deleteAdminStream(id: number) {
    return apiRequest(`/api/admin/streams/${id}`, { method: 'DELETE' });
}

// ─── Admin ACL CRUD ──────────────────────────────────
export function createAdminAcl(data: Record<string, unknown>) {
    return apiRequest('/api/admin/acls', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}
export function updateAdminAcl(id: number, data: Record<string, unknown>) {
    return apiRequest(`/api/admin/acls/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}
/**
 * Deleting needs the owner's CDNfly user id, because /v1/waf-rules is a
 * user-scope endpoint and the server acts as that user via an SSO token. Read it
 * off the row's `user_id`.
 */
export function deleteAdminAcl(id: number, userId: number) {
    return apiRequest(`/api/admin/acls/${id}`, {
        method: 'DELETE',
        body: JSON.stringify({ user_id: userId }),
    });
}

/**
 * CC protection: matchers, filters and rules.
 *
 * These go through /api/cdn/proxy rather than /api/admin/*, for two reasons the
 * old /api/admin/cc-* paths got wrong — they had no routes at all, so the page
 * failed with "请求的接口不存在".
 *
 * 1. CDNfly v6 documents cc-matchs / cc-filters / cc-rules under the *user*
 *    scope, not the admin scope. The proxy sends the caller's own CDNfly
 *    credentials, which is the scope these endpoints expect.
 * 2. Upstream the resource is "cc-matchs", not "cc-matchers". The old client
 *    invented the English plural and would have 404'd against CDNfly even with
 *    a route in place.
 *
 * The proxy allowlist already grants all methods on these three paths.
 */
const CC = {
    matcher: '/api/cdn/proxy/v1/cc-matchs',
    filter: '/api/cdn/proxy/v1/cc-filters',
    rule: '/api/cdn/proxy/v1/cc-rules',
} as const;

export async function listAdminCcMatchers(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl(CC.matcher, params));
}
export function createAdminCcMatcher(data: Record<string, unknown>) {
    return apiRequest(CC.matcher, {
        method: 'POST',
        body: JSON.stringify(data),
    });
}
export function updateAdminCcMatcher(
    id: number,
    data: Record<string, unknown>,
) {
    return apiRequest(`${CC.matcher}/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}
export function deleteAdminCcMatcher(id: number) {
    return apiRequest(`${CC.matcher}/${id}`, { method: 'DELETE' });
}

export async function listAdminCcFilters(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl(CC.filter, params));
}
export function createAdminCcFilter(data: Record<string, unknown>) {
    return apiRequest(CC.filter, {
        method: 'POST',
        body: JSON.stringify(data),
    });
}
export function updateAdminCcFilter(id: number, data: Record<string, unknown>) {
    return apiRequest(`${CC.filter}/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}
export function deleteAdminCcFilter(id: number) {
    return apiRequest(`${CC.filter}/${id}`, { method: 'DELETE' });
}

export async function listAdminCcRules(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl(CC.rule, params));
}
export function createAdminCcRule(data: Record<string, unknown>) {
    return apiRequest(CC.rule, { method: 'POST', body: JSON.stringify(data) });
}
export function updateAdminCcRule(id: number, data: Record<string, unknown>) {
    return apiRequest(`${CC.rule}/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}
export function deleteAdminCcRule(id: number) {
    return apiRequest(`${CC.rule}/${id}`, { method: 'DELETE' });
}

// ─── Config ────────────────────────────────────────────
export async function getAdminConfigs(): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>('/api/admin/configs');
}

export async function updateAdminConfigs(
    payload: Record<string, unknown>,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>('/api/admin/configs', {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function getAdminRegisterInfo(): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>('/api/admin/register-info');
}
