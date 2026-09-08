import { apiRequest } from '@/lib/apiRequest';
import { buildUrl } from '@/lib/urlHelpers';

export type { CdnflyRecord, CdnflyListData } from '@/lib/sharedTypes';
import type { CdnflyRecord, CdnflyListData } from '@/lib/sharedTypes';

export type CdnSitePayload = {
    user_package?: number | null;
    domain?: string | null;
    http_listen?: Record<string, unknown> | null;
    https_listen?: Record<string, unknown> | null;
    backend?: Array<Record<string, unknown>> | null;
    backend_protocol?: string | null;
    backend_http_port?: number | null;
    backend_https_port?: number | null;
    backend_host?: string | null;
    balance_way?: string | null;
    groups?: string | null;
    enable?: boolean | number | null;
};

export type CdnCertPayload = {
    name: string;
    type?: string | null;
    domain?: string | null;
    cert?: string | null;
    key?: string | null;
    dnsapi?: number | null;
    auto_renew?: boolean | number | null;
    enable?: boolean | number | null;
    reissue?: boolean | number | null;
    des?: string | null;
};

export type CdnDnsApiPayload = {
    name: string;
    type: string;
    auth: Record<string, unknown>;
    des?: string | null;
    enable?: boolean | number | null;
};

export type CdnAclPayload = {
    name: string;
    default_action: string;
    data: unknown[];
    des?: string | null;
    enable?: boolean | number | null;
};

export type CdnCcMatcherPayload = {
    name: string;
    data: Record<string, unknown>;
    des?: string | null;
    enable?: boolean | number | null;
};

export type CdnCcFilterPayload = {
    name: string;
    type: string;
    within_second: number;
    max_req: number;
    max_req_per_uri?: number | null;
    extra?: Record<string, unknown>;
    des?: string | null;
    enable?: boolean | number | null;
};

export type CdnCcRulePayload = {
    name: string;
    data: unknown[];
    sort?: number | null;
    des?: string | null;
    enable?: boolean | number | null;
    is_show?: boolean | number | null;
};

export type CdnJobPayload = {
    type: string;
    data: Record<string, unknown>;
};

export type CdnStreamPayload = {
    user_package: number;
    listen: Array<Record<string, unknown>>;
    backend_port: number;
    backend: Array<Record<string, unknown>>;
    groups?: string | number | null;
    des?: string | null;
    balance_way?: string | null;
    proxy_protocol?: boolean | number | null;
    conn_limit?: number | null;
    acl?: Record<string, unknown> | null;
    enable?: boolean | number | null;
};

export type CdnStreamGroupPayload = {
    name: string;
    des?: string | null;
};

export type CdnUserPackagePayload = {
    package: number;
    duration: string;
    name?: string | null;
    coupon_code?: string | null;
};

export type CdnUserPackageRenewPayload = {
    package?: number | null;
    duration?: string | null;
    coupon_code?: string | null;
};

export type CdnMessageSubPayload = {
    msg_type: string;
    phone?: boolean | number | null;
    email?: boolean | number | null;
};

export type CdnApiKeyPayload = {
    ip?: string | null;
    reset?: boolean | number | null;
};

export type CdnCertifyPayload = {
    cert_name: string;
    cert_no: string;
};

export type CdnSiteGroupPayload = {
    name: string;
    des?: string | null;
};

export type CdnUserConfigPayload = {
    type: string;
    name: string;
    value: string;
    scope_name?: string;
    scope_id?: number | null;
    enable?: boolean | number | null;
};

export async function listUserSites(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl('/api/cdn/sites', params));
}

export async function getUserSite(id: number): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/cdn/sites/${id}`);
}

export async function createUserSite(
    payload: CdnSitePayload,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>('/api/cdn/sites', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function updateUserSite(
    id: number,
    payload: Partial<CdnSitePayload>,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/cdn/sites/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function deleteUserSite(id: number): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/cdn/sites/${id}`, {
        method: 'DELETE',
    });
}

export async function listUserCerts(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl('/api/cdn/certs', params));
}

export async function createUserCert(
    payload: CdnCertPayload,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>('/api/cdn/certs', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function updateUserCert(
    id: number,
    payload: Partial<CdnCertPayload>,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/cdn/certs/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function deleteUserCert(id: number): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(`/api/cdn/certs/${id}`, {
        method: 'DELETE',
    });
}

export async function listUserDnsApis(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/dnsapis', params);
}

export async function createUserDnsApi(
    payload: CdnDnsApiPayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/dnsapis', 'POST', payload);
}

export async function updateUserDnsApi(
    id: number,
    payload: Partial<CdnDnsApiPayload>,
): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/dnsapis/${id}`, 'PUT', payload);
}

export async function deleteUserDnsApi(id: number): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/dnsapis/${id}`, 'DELETE');
}

export async function listUserAcls(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/waf-rules', params);
}

export async function createUserAcl(
    payload: CdnAclPayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/waf-rules', 'POST', payload);
}

export async function updateUserAcl(
    id: number,
    payload: Partial<CdnAclPayload>,
): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/waf-rules/${id}`, 'PUT', payload);
}

export async function deleteUserAcl(id: number): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/waf-rules/${id}`, 'DELETE');
}

export async function listUserCcMatchers(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/cc-matchs', params);
}

export async function createUserCcMatcher(
    payload: CdnCcMatcherPayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/cc-matchs', 'POST', payload);
}

export async function updateUserCcMatcher(
    id: number,
    payload: Partial<CdnCcMatcherPayload>,
): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/cc-matchs/${id}`, 'PUT', payload);
}

export async function deleteUserCcMatcher(id: number): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/cc-matchs/${id}`, 'DELETE');
}

export async function listUserCcFilters(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/cc-filters', params);
}

export async function createUserCcFilter(
    payload: CdnCcFilterPayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/cc-filters', 'POST', payload);
}

export async function updateUserCcFilter(
    id: number,
    payload: Partial<CdnCcFilterPayload>,
): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/cc-filters/${id}`, 'PUT', payload);
}

export async function deleteUserCcFilter(id: number): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/cc-filters/${id}`, 'DELETE');
}

export async function listUserCcRules(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/cc-rules', params);
}

export async function createUserCcRule(
    payload: CdnCcRulePayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/cc-rules', 'POST', payload);
}

export async function updateUserCcRule(
    id: number,
    payload: Partial<CdnCcRulePayload>,
): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/cc-rules/${id}`, 'PUT', payload);
}

export async function deleteUserCcRule(id: number): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/cc-rules/${id}`, 'DELETE');
}

export async function listUserJobs(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/jobs', params);
}

export async function createUserJobs(
    payload: CdnJobPayload[],
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/jobs', 'POST', payload);
}

export async function listUserBlackIps(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/monitor/site/blackip', params);
}

export async function unlockUserBlackIps(
    jobs: CdnJobPayload[],
): Promise<CdnflyRecord> {
    return createUserJobs(jobs);
}

export async function getUserBlackIpCount(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/monitor/site/blackip-count', params);
}

export async function listUserHistoryBlackIps(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/monitor/site/history-blackip', params);
}

export async function listUserStreams(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/streams', params);
}

export async function getUserStream(id: number): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/streams/${id}`, 'GET');
}

export async function createUserStream(
    payload: CdnStreamPayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/streams', 'POST', payload);
}

export async function updateUserStream(
    id: number,
    payload: Partial<CdnStreamPayload>,
): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/streams/${id}`, 'PUT', payload);
}

export async function deleteUserStream(id: number): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/streams/${id}`, 'DELETE');
}

export async function listUserStreamGroups(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/stream-groups', params);
}

export async function createUserStreamGroup(
    payload: CdnStreamGroupPayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/stream-groups', 'POST', payload);
}

export async function updateUserStreamGroup(
    id: number,
    payload: Partial<CdnStreamGroupPayload>,
): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/stream-groups/${id}`, 'PUT', payload);
}

export async function deleteUserStreamGroup(id: number): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/stream-groups/${id}`, 'DELETE');
}

export async function getUserStreamRealtime(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/monitor/stream/realtime', params);
}

export async function getUserStreamTop(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/monitor/stream/top', params);
}

export async function getUserSiteRealtime(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/monitor/site/realtime', params);
}

export async function getUserSiteTop(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/monitor/site/top', params);
}

export async function getUserUsage(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/monitor/usage', params);
}

export async function listUserAccessLogs(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/monitor/site/access-log', params);
}

export async function listAccessLogJobs(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/jobs', { ...params, type: 'down_http_access_log' });
}

export async function createAccessLogJob(
    start: string,
    end: string,
    domain?: string,
): Promise<CdnflyRecord> {
    const data: Record<string, string> = { start, end };

    if (domain) {
data.domain = domain;
}

    return proxyRequest('/v1/jobs', 'POST', {
        type: 'down_http_access_log',
        data,
    });
}

export function accessLogDownloadUrl(
    jobId: number | string,
    baseUrl: string,
): string {
    return `${baseUrl}/monitor/site/download-access-log/${jobId}`;
}

export async function listUserPackages(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/user-packages', params);
}

export async function listSalePackages(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/packages', params);
}

export async function listSalePackageGroups(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/package-groups', params);
}

export async function listSalePackageUps(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/package-ups', params);
}

export async function createUserPackage(
    payload: CdnUserPackagePayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/user-packages', 'POST', payload);
}

export async function updateUserPackage(
    id: number,
    payload: CdnUserPackageRenewPayload,
): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/user-packages/${id}`, 'PUT', payload);
}

export async function deleteUserPackage(id: number): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/user-packages/${id}`, 'DELETE');
}

export async function getUserPackageUpgrades(
    id: number,
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet(`/v1/user-package/${id}/upgrades`, params);
}

export type CdnUserPackageUpgradePayload = {
    package_up_id: number;
    coupon_code?: string;
};

export async function purchaseUserPackageUpgrade(
    id: number,
    payload: CdnUserPackageUpgradePayload,
): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/user-package/${id}/upgrades`, 'POST', payload);
}

export async function getUserPackageUsage(
    id: number,
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet(`/v1/user-package/${id}/usage`, params);
}

export async function listUserOrders(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/orders', params);
}

export async function listUserMessages(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/messages', params);
}

export async function getUserMessage(id: number): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/messages/${id}`, 'GET');
}

export async function markUserMessageRead(id: number): Promise<CdnflyRecord> {
    return proxyRequest('/v1/messages/read', 'POST', { id: String(id) });
}

export async function getUserMessageSubscriptions(): Promise<CdnflyListData> {
    return proxyGet('/v1/messages/sub', {});
}

export async function updateUserMessageSubscription(
    payload: CdnMessageSubPayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/messages/sub', 'PUT', payload);
}

export async function getUserApiKey(): Promise<CdnflyRecord> {
    return proxyRequest('/v1/api-key', 'GET');
}

export async function createUserApiKey(): Promise<CdnflyRecord> {
    return proxyRequest('/v1/api-key', 'POST', {});
}

export async function updateUserApiKey(
    payload: CdnApiKeyPayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/api-key', 'PUT', payload);
}

export async function deleteUserApiKey(): Promise<CdnflyRecord> {
    return proxyRequest('/v1/api-key', 'DELETE');
}

export async function listUserLoginLogs(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/log/login', params);
}

export async function getUserOverview(): Promise<CdnflyRecord> {
    return proxyRequest('/v1/user/overview', 'GET');
}

export async function getUserCertify(): Promise<CdnflyRecord> {
    return proxyRequest('/v1/user/certify', 'GET');
}

export async function listUserSiteGroups(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/site-groups', params);
}

export async function createUserSiteGroup(
    payload: CdnSiteGroupPayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/site-groups', 'POST', payload);
}

export async function updateUserSiteGroup(
    id: number,
    payload: Partial<CdnSiteGroupPayload>,
): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/site-groups/${id}`, 'PUT', payload);
}

export async function deleteUserSiteGroup(id: number): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/site-groups/${id}`, 'DELETE');
}

export async function listUserConfigs(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/user-configs', params);
}

export async function createUserConfig(
    payload: CdnUserConfigPayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/user-configs', 'POST', payload);
}

export async function updateUserConfig(
    id: number,
    payload: Partial<CdnUserConfigPayload>,
): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/user-configs/${id}`, 'PUT', payload);
}

export async function deleteUserConfig(id: number): Promise<CdnflyRecord> {
    return proxyRequest(`/v1/user-configs/${id}`, 'DELETE');
}

export async function listUserDomains(
    params: Record<string, string | number> = {},
): Promise<CdnflyListData> {
    return proxyGet('/v1/domains', params);
}

export async function postCnameCheck(
    data: Record<string, { cname: string; domain: string }>,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/cname-check', 'POST', data);
}

export async function submitUserCertify(
    payload: CdnCertifyPayload,
): Promise<CdnflyRecord> {
    return proxyRequest('/v1/user/certify', 'POST', payload);
}

/**
 * Unwrap a single-object CDNfly response.
 *
 * Every endpoint answers with an envelope — {data, msg, code} — and proxyRequest
 * returns it whole. extractCdnflyRows() handles the case where `data` is an
 * array, but there was nothing for the case where it is a single object, so
 * callers were reading fields off the envelope: `record.api_key` was undefined
 * while `record.data.api_key` held the value. Symptoms were pages rendering
 * `data`/`code`/`msg` as if they were fields, and boolean flags reading false
 * because the field simply was not at that level.
 */
export function extractCdnflyRecord(result: unknown): CdnflyRecord | null {
    if (!isRecord(result)) {
        return null;
    }

    const inner = result.data;

    if (isRecord(inner)) {
        return inner;
    }

    // already unwrapped, or an envelope with no object payload
    return result;
}

export function extractCdnflyRows(result: unknown): CdnflyRecord[] {
    if (Array.isArray(result)) {
        return result.filter(isRecord);
    }

    if (!isRecord(result)) {
        return [];
    }

    for (const key of ['data', 'items', 'list', 'rows', 'records']) {
        const value = result[key];

        if (Array.isArray(value)) {
            return value.filter(isRecord);
        }

        if (isRecord(value)) {
            const nested = extractCdnflyRows(value);

            if (nested.length > 0) {
                return nested;
            }
        }
    }

    return [];
}

export function extractCdnflyTotal(
    result: CdnflyListData,
    fallback: number,
): number {
    if (typeof result.total === 'number') {
        return result.total;
    }

    if (isRecord(result.meta) && typeof result.meta.total === 'number') {
        return result.meta.total;
    }

    if (isRecord(result.data) && typeof result.data.total === 'number') {
        return result.data.total;
    }

    return fallback;
}

function proxyPath(path: string): string {
    return `/api/cdn/proxy/${path.replace(/^\/+/, '')}`;
}

function proxyGet(
    path: string,
    params: Record<string, string | number>,
): Promise<CdnflyListData> {
    return apiRequest<CdnflyListData>(buildUrl(proxyPath(path), params));
}

function proxyRequest<TPayload>(
    path: string,
    method: string,
    payload?: TPayload,
): Promise<CdnflyRecord> {
    return apiRequest<CdnflyRecord>(proxyPath(path), {
        method,
        body: payload === undefined ? undefined : JSON.stringify(payload),
    });
}

function isRecord(value: unknown): value is CdnflyRecord {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}
