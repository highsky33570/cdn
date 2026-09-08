<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CdnflyApiService
{
    private const REDACTED = '[REDACTED]';

    private const SENSITIVE_KEYS = [
        'api-key',
        'apikey',
        'api_key',
        'api-secret',
        'apisecret',
        'api_secret',
        'secret',
        'token',
        'access-token',
        'access_token',
        'authorization',
        'password',
    ];

    private function adminHttp(): PendingRequest
    {
        $this->ensureOutboundEnabled('admin request');

        return $this->secureHttp()
            ->withHeaders([
                config('services.cdnfly.admin_key_header', 'api-key') => config('services.cdnfly.admin_api_key'),
                config('services.cdnfly.admin_secret_header', 'api-secret') => config('services.cdnfly.admin_api_secret'),
            ]);
    }

    private function userHttp(User $user): PendingRequest
    {
        $this->ensureOutboundEnabled('user request');

        if (! $user->cdnfly_api_key || ! $user->cdnfly_api_secret) {
            throw new \RuntimeException("User {$user->id} is missing CDNfly API credentials.");
        }

        return $this->secureHttp()
            ->withHeaders([
                config('services.cdnfly.admin_key_header', 'api-key') => $user->cdnfly_api_key,
                config('services.cdnfly.admin_secret_header', 'api-secret') => $user->cdnfly_api_secret,
            ]);
    }

    private function bearerHttp(string $token): PendingRequest
    {
        $this->ensureOutboundEnabled('bearer request');

        return $this->secureHttp()
            // CDNfly user access tokens must be sent via the `access-token` header.
            ->withHeaders([
                'access-token' => $token,
            ]);
    }

    /**
     * 基础安全 HTTP 客户端：强制 TLS 证书校验 + 仅允许 HTTPS。
     */
    private function secureHttp(): PendingRequest
    {
        $baseUrl = $this->baseUrl();

        if (! str_starts_with($baseUrl, 'https://')) {
            throw new \RuntimeException('CDNfly base URL must use HTTPS. Refusing to send credentials over plain HTTP.');
        }

        return Http::baseUrl($baseUrl)
            ->timeout((int) config('services.cdnfly.timeout', 15))
            ->withOptions(['verify' => true])
            ->acceptJson()
            ->asJson();
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.cdnfly.base_url'), '/');
    }

    private function outboundEnabled(): bool
    {
        return (bool) config('services.cdnfly.outbound_enabled', true);
    }

    private function disabledReadPayload(string $context): array
    {
        Log::info('CDNfly outbound disabled; using empty local payload', [
            'context' => $context,
        ]);

        return [
            'code' => 0,
            'data' => [],
            'total' => 0,
            'meta' => [
                'total' => 0,
            ],
            'cdnfly_outbound_disabled' => true,
            'message' => 'CDNfly outbound is disabled.',
        ];
    }

    private function ensureOutboundEnabled(string $context): void
    {
        if ($this->outboundEnabled()) {
            return;
        }

        Log::info('CDNfly outbound blocked before request', [
            'context' => $context,
        ]);

        throw new \RuntimeException('CDNfly outbound is disabled.');
    }

    /**
     * @return array{ok: bool}
     */
    public function deleteCdnflyUser(int $cdnflyUserId): array
    {
        $response = $this->adminHttp()->delete("/v1/users/{$cdnflyUserId}");
        $data = $this->parseResponse($response, 'delete user');

        return ['ok' => true];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{raw: array}
     */
    public function updateCdnflyUser(int $cdnflyUserId, array $payload): array
    {
        $response = $this->adminHttp()->put("/v1/users/{$cdnflyUserId}", $payload);
        $data = $this->parseResponse($response, 'update user');

        return ['raw' => $data];
    }

    /**
     * @return array{cdnfly_user_id: int, raw: array}
     */
    public function createCdnflyUser(string $username, string $email, string $password): array
    {
        $response = $this->adminHttp()->post('/v1/users', [
            'name' => $username,
            'email' => $email,
            'password' => $password,
            'type' => 2,
        ]);

        $data = $this->parseResponse($response, 'create user');

        $userId = is_scalar(data_get($data, 'data'))
            ? data_get($data, 'data')
            : (data_get($data, 'data.id') ?? data_get($data, 'data.user_id'));

        // CDNfly reports duplicate name/email as code:0 with data:0, so a falsy id
        // is a real rejection rather than a malformed response. Surface its message.
        if (! $userId) {
            $upstream = trim((string) (data_get($data, 'msg') ?? data_get($data, 'message') ?? ''));

            throw new \RuntimeException($upstream !== ''
                ? 'CDNfly rejected the user creation: '.$upstream
                : 'CDNfly create user response is missing user_id.');
        }

        return [
            'cdnfly_user_id' => (int) $userId,
            'raw' => $data,
        ];
    }

    /**
     * Find the upstream account holding an email, or null.
     *
     * CDNfly enforces one account per email, so a create that fails with
     * "email ...已存在" means the account exists and its id is the only thing we
     * are missing. /v1/users takes no email filter, so page through and match
     * exactly — the page cap keeps a large panel from turning this into a scan
     * that never ends.
     *
     * @return array{id: int, username: string, email: string}|null
     */
    public function findUserByEmail(string $email): ?array
    {
        if (! $this->outboundEnabled()) {
            return null;
        }

        $needle = strtolower(trim($email));

        for ($page = 1; $page <= 50; $page++) {
            $payload = $this->listUsers(['page' => $page, 'limit' => 100]);
            $rows = $this->extractRows($payload);

            if ($rows === []) {
                return null;
            }

            foreach ($rows as $row) {
                if (strtolower(trim((string) ($row['email'] ?? ''))) !== $needle) {
                    continue;
                }

                $id = (int) ($row['id'] ?? ($row['uid'] ?? 0));

                if ($id > 0) {
                    return [
                        'id' => $id,
                        'username' => (string) ($row['name'] ?? ($row['username'] ?? '')),
                        'email' => (string) ($row['email'] ?? ''),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * CDNfly is not consistent about where a list puts its rows.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractRows(array $payload): array
    {
        foreach (['data.rows', 'data.list', 'data.items', 'data', 'rows', 'list'] as $path) {
            $value = data_get($payload, $path);

            if (is_array($value) && array_is_list($value)) {
                return array_values(array_filter($value, 'is_array'));
            }
        }

        return [];
    }

    /**
     * @return array{api_key: string, api_secret: string}
     */
    public function enableUserApiKey(int $cdnflyUserId): array
    {
        $token = $this->getSsoToken($cdnflyUserId);
        $response = $this->bearerHttp($token)->post('/v1/api-key');
        $data = $this->parseResponse($response, 'enable api key');

        $apiKey = data_get($data, 'data.api_key');
        $apiSecret = data_get($data, 'data.api_secret');

        if (! $apiKey || ! $apiSecret) {
            throw new \RuntimeException('CDNfly api key response is incomplete.');
        }

        return [
            'api_key' => (string) $apiKey,
            'api_secret' => (string) $apiSecret,
        ];
    }

    /**
     * @return array{api_key: string, api_secret: string}|null
     */
    public function getUserApiKey(int $cdnflyUserId): ?array
    {
        if (! $this->outboundEnabled()) {
            Log::info('CDNfly outbound disabled; skipped API key lookup', [
                'cdnfly_user_id' => $cdnflyUserId,
            ]);

            return null;
        }

        $token = $this->getSsoToken($cdnflyUserId);
        $response = $this->bearerHttp($token)->get('/v1/api-key');
        $data = $this->parseResponse($response, 'get api key');

        $apiKey = data_get($data, 'data.api_key');
        $apiSecret = data_get($data, 'data.api_secret');

        if (! $apiKey || ! $apiSecret) {
            return null;
        }

        return [
            'api_key' => (string) $apiKey,
            'api_secret' => (string) $apiSecret,
        ];
    }

    public function getSsoToken(int $cdnflyUserId): string
    {
        $response = $this->adminHttp()->get("/v1/users/{$cdnflyUserId}", [
            'token' => 1,
        ]);

        $data = $this->parseResponse($response, 'get sso token');

        $token = data_get($data, 'data.access_token')
            ?? data_get($data, 'data.token')
            ?? data_get($data, 'access_token')
            ?? data_get($data, 'token');

        if (! $token) {
            throw new \RuntimeException('CDNfly SSO token response is incomplete.');
        }

        return (string) $token;
    }

    /**
     * @return array{token: string|null, cdnfly_user_id: int|null, raw: array}
     */
    public function login(string $account, string $password): array
    {
        $this->ensureOutboundEnabled('login');

        $response = $this->secureHttp()
            ->post('/v1/login', [
                'account' => $account,
                'password' => $password,
            ]);

        $data = $this->parseResponse($response, 'login');

        $token = data_get($data, 'token') ?? data_get($data, 'access_token');
        $userId = data_get($data, 'user_id')
            ?? data_get($data, 'id')
            ?? data_get($data, 'data.user_id')
            ?? data_get($data, 'data.id');

        return [
            'token' => $token ? (string) $token : null,
            'cdnfly_user_id' => $userId ? (int) $userId : null,
            'raw' => $data,
        ];
    }

    public function listUsers(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list users');
        }

        $response = $this->adminHttp()->get('/v1/users', $params);

        return $this->parseResponse($response, 'list users');
    }

    public function rechargeUser(int $cdnflyUserId, float $amount): array
    {
        $response = $this->adminHttp()->post("/v1/user/{$cdnflyUserId}/recharge", [
            'amount' => $amount,
        ]);

        return $this->parseResponse($response, 'recharge user');
    }

    public function listRegions(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list regions');
        }

        $response = $this->adminHttp()->get('/v1/regions', $params);

        return $this->parseResponse($response, 'list regions');
    }

    public function listNodeGroups(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list node groups');
        }

        $response = $this->adminHttp()->get('/v1/node-groups', $params);

        return $this->parseResponse($response, 'list node groups');
    }

    // ─── Admin: Regions CRUD ─────────────────────────────────
    // A region is the first link in the chain a sellable package needs:
    // region -> node group -> package. Read-only regions meant the chain could
    // never be started from the console.
    public function createRegion(array $data): array
    {
        $this->ensureOutboundEnabled('create region');
        $response = $this->adminHttp()->post('/v1/regions', $data);

        return $this->parseResponse($response, 'create region');
    }

    public function updateRegion(int $id, array $data): array
    {
        $this->ensureOutboundEnabled('update region');
        $response = $this->adminHttp()->put("/v1/regions/{$id}", $data);

        return $this->parseResponse($response, 'update region');
    }

    public function deleteRegion(int $id): array
    {
        $this->ensureOutboundEnabled('delete region');
        $response = $this->adminHttp()->delete("/v1/regions/{$id}");

        return $this->parseResponse($response, 'delete region');
    }

    // ─── Admin: Lines CRUD ───────────────────────────────────
    public function createLine(array $data): array
    {
        $this->ensureOutboundEnabled('create line');
        $response = $this->adminHttp()->post('/v1/lines', $data);

        return $this->parseResponse($response, 'create line');
    }

    public function updateLine(int $id, array $data): array
    {
        $this->ensureOutboundEnabled('update line');
        $response = $this->adminHttp()->put("/v1/lines/{$id}", $data);

        return $this->parseResponse($response, 'update line');
    }

    public function deleteLine(int $id): array
    {
        $this->ensureOutboundEnabled('delete line');
        $response = $this->adminHttp()->delete("/v1/lines/{$id}");

        return $this->parseResponse($response, 'delete line');
    }

    /**
     * A node group is the unit a package is sold against: customers on a package
     * share every node in its group. Without create/update/delete here, a
     * reseller could list groups but never make one, so packages could only ever
     * point at groups built in the CDNfly panel itself.
     */
    public function createNodeGroup(array $data): array
    {
        $this->ensureOutboundEnabled('create node group');
        $response = $this->adminHttp()->post('/v1/node-groups', $data);

        return $this->parseResponse($response, 'create node group');
    }

    public function updateNodeGroup(int $id, array $data): array
    {
        $this->ensureOutboundEnabled('update node group');
        $response = $this->adminHttp()->put("/v1/node-groups/{$id}", $data);

        return $this->parseResponse($response, 'update node group');
    }

    public function deleteNodeGroup(int $id): array
    {
        $this->ensureOutboundEnabled('delete node group');
        $response = $this->adminHttp()->delete("/v1/node-groups/{$id}");

        return $this->parseResponse($response, 'delete node group');
    }

    public function listPackageGroups(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list package groups');
        }

        $response = $this->adminHttp()->get('/v1/package-groups', $params);

        return $this->parseResponse($response, 'list package groups');
    }

    public function listCnameDomains(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list cname domains');
        }

        $response = $this->adminHttp()->get('/v1/cname-domains', $params);

        return $this->parseResponse($response, 'list cname domains');
    }

    public function listConfigs(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list configs');
        }

        $response = $this->adminHttp()->get('/v1/configs', $params);

        return $this->parseResponse($response, 'list configs');
    }

    public function listPackages(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list packages');
        }

        $response = $this->adminHttp()->get('/v1/packages', $params);

        return $this->parseResponse($response, 'list packages');
    }

    public function getPackage(int $packageId): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get package');
        }

        $response = $this->adminHttp()->get("/v1/packages/{$packageId}");

        return $this->parseResponse($response, 'get package');
    }

    public function createPackage(array $data): array
    {
        $response = $this->adminHttp()->post('/v1/packages', $data);

        return $this->parseResponse($response, 'create package');
    }

    public function updatePackage(int $packageId, array $data): array
    {
        $response = $this->adminHttp()->put("/v1/packages/{$packageId}", $data);

        return $this->parseResponse($response, 'update package');
    }

    public function batchUpdatePackages(array $packages): array
    {
        $response = $this->adminHttp()->put('/v1/packages', $packages);

        return $this->parseResponse($response, 'batch update packages');
    }

    public function deletePackage(int $packageId): array
    {
        $response = $this->adminHttp()->delete("/v1/packages/{$packageId}");

        return $this->parseResponse($response, 'delete package');
    }

    public function listUserPackages(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list user packages');
        }

        $response = $this->adminHttp()->get('/v1/user-packages', $params);

        return $this->parseResponse($response, 'list user packages');
    }

    public function purchaseUserPackage(User $user, array $data): array
    {
        $response = $this->userHttp($user)->post('/v1/user-packages', $data);

        return $this->parseResponse($response, 'purchase user package');
    }

    public function getUserPackage(User $user, string|int $packageId, array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get user package');
        }

        $response = $this->userHttp($user)->get("/v1/user-packages/{$packageId}", $params);

        return $this->parseResponse($response, 'get user package');
    }

    public function renewUserPackage(User $user, string|int $packageId, array $data): array
    {
        $response = $this->userHttp($user)->put("/v1/user-packages/{$packageId}", $data);

        return $this->parseResponse($response, 'renew user package');
    }

    public function listSites(User $user, array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list sites');
        }

        $response = $this->userHttp($user)->get('/v1/sites', $params);

        return $this->parseResponse($response, 'list sites');
    }

    public function getSite(User $user, int $siteId): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get site');
        }

        $response = $this->userHttp($user)->get("/v1/sites/{$siteId}");

        return $this->parseResponse($response, 'get site');
    }

    public function createSite(User $user, array $data): array
    {
        $response = $this->userHttp($user)->post('/v1/sites', $data);

        return $this->parseResponse($response, 'create site');
    }

    public function updateSite(User $user, int $siteId, array $data): array
    {
        $response = $this->userHttp($user)->put("/v1/sites/{$siteId}", $data);

        return $this->parseResponse($response, 'update site');
    }

    public function deleteSite(User $user, int $siteId): array
    {
        $response = $this->userHttp($user)->delete("/v1/sites/{$siteId}");

        return $this->parseResponse($response, 'delete site');
    }

    public function listCerts(User $user, array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list certs');
        }

        $response = $this->userHttp($user)->get('/v1/certs', $params);

        return $this->parseResponse($response, 'list certs');
    }

    public function getCert(User $user, int $certId): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get cert');
        }

        $response = $this->userHttp($user)->get("/v1/certs/{$certId}");

        return $this->parseResponse($response, 'get cert');
    }

    public function createCert(User $user, array $data): array
    {
        $response = $this->userHttp($user)->post('/v1/certs', $data);

        return $this->parseResponse($response, 'create cert');
    }

    public function updateCert(User $user, int $certId, array $data): array
    {
        $response = $this->userHttp($user)->put("/v1/certs/{$certId}", $data);

        return $this->parseResponse($response, 'update cert');
    }

    public function deleteCert(User $user, int $certId): array
    {
        $response = $this->userHttp($user)->delete("/v1/certs/{$certId}");

        return $this->parseResponse($response, 'delete cert');
    }

    public function listSiteGroups(User $user, array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list site groups');
        }

        $response = $this->userHttp($user)->get('/v1/site-groups', $params);

        return $this->parseResponse($response, 'list site groups');
    }

    public function listAcls(User $user, array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list acls');
        }

        $response = $this->userHttp($user)->get('/v1/waf-rules', $params);

        return $this->parseResponse($response, 'list acls');
    }

    public function createAcl(User $user, array $data): array
    {
        $response = $this->userHttp($user)->post('/v1/waf-rules', $data);

        return $this->parseResponse($response, 'create acl');
    }

    public function updateAcl(User $user, int $aclId, array $data): array
    {
        $response = $this->userHttp($user)->put("/v1/waf-rules/{$aclId}", $data);

        return $this->parseResponse($response, 'update acl');
    }

    public function deleteAcl(User $user, int $aclId): array
    {
        $response = $this->userHttp($user)->delete("/v1/waf-rules/{$aclId}");

        return $this->parseResponse($response, 'delete acl');
    }

    public function listDnsApis(User $user, array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list dns apis');
        }

        $response = $this->userHttp($user)->get('/v1/dnsapis', $params);

        return $this->parseResponse($response, 'list dns apis');
    }

    public function proxyUserRequest(User $user, string $method, string $path, array $data = []): array
    {
        if (! $this->outboundEnabled() && strtoupper($method) === 'GET') {
            return $this->disabledReadPayload("proxy user {$path}");
        }

        $http = $this->userHttp($user);
        $response = $this->sendRequest($http, $method, $path, $data);

        return $this->parseResponse($response, strtoupper($method)." {$path}");
    }

    // ─── Admin: Nodes ────────────────────────────────────────
    public function listNodes(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list nodes');
        }

        $response = $this->adminHttp()->get('/v1/nodes', $params);

        return $this->parseResponse($response, 'list nodes');
    }

    public function getNode(int $nodeId): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get node');
        }

        $response = $this->adminHttp()->get("/v1/nodes/{$nodeId}");

        return $this->parseResponse($response, 'get node');
    }

    public function createNode(array $data): array
    {
        $response = $this->adminHttp()->post('/v1/nodes', $data);

        return $this->parseResponse($response, 'create node');
    }

    public function getMasterUpgrade(): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get master upgrade');
        }

        $response = $this->adminHttp()->get('/v1/master/upgrades');

        return $this->parseResponse($response, 'get master upgrade');
    }

    public function listPendingNodes(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list pending nodes');
        }

        $response = $this->adminHttp()->get('/v1/pending-nodes', $params);

        return $this->parseResponse($response, 'list pending nodes');
    }

    public function deletePendingNode(int $pendingNodeId): array
    {
        $response = $this->adminHttp()->delete("/v1/pending-nodes/{$pendingNodeId}");

        return $this->parseResponse($response, 'delete pending node');
    }

    public function updateNode(int $nodeId, array $data): array
    {
        $response = $this->adminHttp()->put("/v1/nodes/{$nodeId}", $data);

        return $this->parseResponse($response, 'update node');
    }

    public function batchUpdateNodes(array $nodes): array
    {
        $response = $this->adminHttp()->put('/v1/nodes', $nodes);

        return $this->parseResponse($response, 'batch update nodes');
    }

    public function deleteNode(int $nodeId): array
    {
        $response = $this->adminHttp()->delete("/v1/nodes/{$nodeId}");

        return $this->parseResponse($response, 'delete node');
    }

    // ─── Admin: Sites (all users) ─────────────────────────────
    public function listAllSites(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list all sites');
        }

        $response = $this->adminHttp()->get('/v1/sites', $params);

        return $this->parseResponse($response, 'list all sites');
    }

    public function getAdminSite(int $siteId): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get admin site');
        }

        $response = $this->adminHttp()->get("/v1/sites/{$siteId}");

        return $this->parseResponse($response, 'get admin site');
    }

    public function updateAdminSite(int $siteId, array $data): array
    {
        $this->ensureOutboundEnabled('update admin site');

        $response = $this->adminHttp()->put("/v1/sites/{$siteId}", $data);

        return $this->parseResponse($response, 'update admin site');
    }

    public function createAdminSite(array $data): array
    {
        $this->ensureOutboundEnabled('create admin site');

        $response = $this->adminHttp()->post('/v1/sites', $data);

        return $this->parseResponse($response, 'create admin site');
    }

    public function deleteAdminSite(string $siteId): array
    {
        $this->ensureOutboundEnabled('delete admin site');

        $response = $this->adminHttp()->delete("/v1/sites/{$siteId}");

        return $this->parseResponse($response, 'delete admin site');
    }

    // ─── Admin: DNS APIs ──────────────────────────────────────
    public function adminCreateDnsApi(array $data): array
    {
        $this->ensureOutboundEnabled('admin create dns api');
        $response = $this->adminHttp()->post('/v1/dnsapis', $data);

        return $this->parseResponse($response, 'admin create dns api');
    }

    public function adminUpdateDnsApi(int $id, array $data): array
    {
        $this->ensureOutboundEnabled('admin update dns api');
        $response = $this->adminHttp()->put("/v1/dnsapis/{$id}", $data);

        return $this->parseResponse($response, 'admin update dns api');
    }

    public function adminDeleteDnsApi(int $id): array
    {
        $this->ensureOutboundEnabled('admin delete dns api');
        $response = $this->adminHttp()->delete("/v1/dnsapis/{$id}");

        return $this->parseResponse($response, 'admin delete dns api');
    }

    // ─── Admin: Streams ───────────────────────────────────────
    public function adminCreateStream(array $data): array
    {
        $this->ensureOutboundEnabled('admin create stream');
        $response = $this->adminHttp()->post('/v1/streams', $data);

        return $this->parseResponse($response, 'admin create stream');
    }

    public function adminUpdateStream(int $id, array $data): array
    {
        $this->ensureOutboundEnabled('admin update stream');
        $response = $this->adminHttp()->put("/v1/streams/{$id}", $data);

        return $this->parseResponse($response, 'admin update stream');
    }

    public function adminDeleteStream(int $id): array
    {
        $this->ensureOutboundEnabled('admin delete stream');
        $response = $this->adminHttp()->delete("/v1/streams/{$id}");

        return $this->parseResponse($response, 'admin delete stream');
    }

    // ─── Admin: ACLs ──────────────────────────────────────────
    //
    // CDNfly v6 documents /v1/waf-rules under the *user* scope only — the sole
    // admin-scope WAF route is waf-rules/update-subscription. Sending the master
    // api-key here is refused, which is why the console's 新增 ACL button failed.
    //
    // The console's form is nonetheless an admin one: it names the customer the
    // rule is for. So act as that customer rather than as the panel: mint an SSO
    // token for their CDNfly user and call the user endpoint as them. That keeps
    // the operator's intent intact instead of quietly filing the rule under the
    // operator's own account.

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function adminCreateAcl(int $cdnflyUserId, array $data): array
    {
        $this->ensureOutboundEnabled('admin create acl');
        $token = $this->getSsoToken($cdnflyUserId);
        // user_id is implied by the token; leaving it in the body would be an
        // attempt to set ownership on an endpoint that does not accept it.
        unset($data['user_id']);
        $response = $this->bearerHttp($token)->post('/v1/waf-rules', $data);

        return $this->parseResponse($response, 'admin create acl');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function adminUpdateAcl(int $cdnflyUserId, int $id, array $data): array
    {
        $this->ensureOutboundEnabled('admin update acl');
        $token = $this->getSsoToken($cdnflyUserId);
        unset($data['user_id']);
        $response = $this->bearerHttp($token)->put("/v1/waf-rules/{$id}", $data);

        return $this->parseResponse($response, 'admin update acl');
    }

    /**
     * @return array<string, mixed>
     */
    public function adminDeleteAcl(int $cdnflyUserId, int $id): array
    {
        $this->ensureOutboundEnabled('admin delete acl');
        $token = $this->getSsoToken($cdnflyUserId);
        $response = $this->bearerHttp($token)->delete("/v1/waf-rules/{$id}");

        return $this->parseResponse($response, 'admin delete acl');
    }

    // ───
    public function listAllStreams(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list all streams');
        }

        $response = $this->adminHttp()->get('/v1/streams', $params);

        return $this->parseResponse($response, 'list all streams');
    }

    public function listStreamGroups(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list stream groups');
        }

        $response = $this->adminHttp()->get('/v1/stream-groups', $params);

        return $this->parseResponse($response, 'list stream groups');
    }

    // ─── Admin: Stream Groups CRUD ───────────────────────────
    public function adminCreateStreamGroup(array $data): array
    {
        $this->ensureOutboundEnabled('admin create stream group');
        $response = $this->adminHttp()->post('/v1/stream-groups', $data);

        return $this->parseResponse($response, 'admin create stream group');
    }

    public function adminUpdateStreamGroup(int $id, array $data): array
    {
        $this->ensureOutboundEnabled('admin update stream group');
        $response = $this->adminHttp()->put("/v1/stream-groups/{$id}", $data);

        return $this->parseResponse($response, 'admin update stream group');
    }

    public function adminDeleteStreamGroup(int $id): array
    {
        $this->ensureOutboundEnabled('admin delete stream group');
        $response = $this->adminHttp()->delete("/v1/stream-groups/{$id}");

        return $this->parseResponse($response, 'admin delete stream group');
    }

    // ─── Admin: Certs CRUD ───────────────────────────────────
    public function adminCreateCert(array $data): array
    {
        $this->ensureOutboundEnabled('admin create cert');
        $response = $this->adminHttp()->post('/v1/certs', $data);

        return $this->parseResponse($response, 'admin create cert');
    }

    public function adminUpdateCert(int $id, array $data): array
    {
        $this->ensureOutboundEnabled('admin update cert');
        $response = $this->adminHttp()->put("/v1/certs/{$id}", $data);

        return $this->parseResponse($response, 'admin update cert');
    }

    public function adminDeleteCert(int $id): array
    {
        $this->ensureOutboundEnabled('admin delete cert');
        $response = $this->adminHttp()->delete("/v1/certs/{$id}");

        return $this->parseResponse($response, 'admin delete cert');
    }

    // ─── Admin: DNS APIs (global) ─────────────────────────────
    public function listAllDnsApis(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list all dns apis');
        }

        $response = $this->adminHttp()->get('/v1/dnsapis', $params);

        return $this->parseResponse($response, 'list all dns apis');
    }

    // ─── Admin: Lines ─────────────────────────────────────────
    public function listLines(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list lines');
        }

        $response = $this->adminHttp()->get('/v1/lines', $params);

        return $this->parseResponse($response, 'list lines');
    }

    // ─── Admin: Configs ───────────────────────────────────────
    public function getConfigs(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get configs');
        }

        $response = $this->adminHttp()->get('/v1/configs', $params);

        return $this->parseResponse($response, 'get configs');
    }

    /**
     * Add or update one system config.
     *
     * CDNfly keys a config on scope + type + name; its rows carry no id, so
     * there is no /v1/configs/{id} to address from the config list. Per the v6
     * admin reference: 「按配置作用域、类型和名称新增或更新一条系统配置。目标
     * 配置已存在时更新其值和启用状态，不存在时创建。」
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsertConfig(array $data): array
    {
        $this->ensureOutboundEnabled('upsert config');
        $response = $this->adminHttp()->put('/v1/configs', $data);

        return $this->parseResponse($response, 'upsert config');
    }

    public function getRegisterInfo(): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get register info');
        }

        $response = $this->adminHttp()->get('/v1/common/register-info');

        return $this->parseResponse($response, 'get register info');
    }

    // ─── Admin: Logs ──────────────────────────────────────────
    public function listLoginLogs(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list login logs');
        }

        $response = $this->adminHttp()->get('/v1/log/login', $params);

        return $this->parseResponse($response, 'list login logs');
    }

    public function listOpLogs(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list op logs');
        }

        $response = $this->adminHttp()->get('/v1/log/op', $params);

        return $this->parseResponse($response, 'list op logs');
    }

    // ─── Admin: Certs (global) ────────────────────────────────
    public function listAllCerts(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list all certs');
        }

        $response = $this->adminHttp()->get('/v1/certs', $params);

        return $this->parseResponse($response, 'list all certs');
    }

    // ─── Admin: ACLs (global) ─────────────────────────────────
    public function listAllAcls(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list all acls');
        }

        $response = $this->adminHttp()->get('/v1/waf-rules', $params);

        return $this->parseResponse($response, 'list all acls');
    }

    // ─── Admin: Monitor ───────────────────────────────────────
    public function getSiteRealtimeMonitor(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get site realtime monitor');
        }

        $response = $this->adminHttp()->get('/v1/monitor/site/realtime', $params);

        return $this->parseResponse($response, 'get site realtime monitor');
    }

    public function getStreamRealtimeMonitor(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get stream realtime monitor');
        }

        $response = $this->adminHttp()->get('/v1/monitor/stream/realtime', $params);

        return $this->parseResponse($response, 'get stream realtime monitor');
    }

    // ─── Admin: User Packages CRUD ──────────────────────────
    public function adminCreateUserPackage(array $data): array
    {
        $this->ensureOutboundEnabled('admin create user package');
        $response = $this->adminHttp()->post('/v1/user-packages', $data);

        return $this->parseResponse($response, 'admin create user package');
    }

    public function adminUpdateUserPackage(int $id, array $data): array
    {
        $this->ensureOutboundEnabled('admin update user package');
        $response = $this->adminHttp()->put("/v1/user-packages/{$id}", $data);

        return $this->parseResponse($response, 'admin update user package');
    }

    public function adminDeleteUserPackage(int $id): array
    {
        $this->ensureOutboundEnabled('admin delete user package');
        $response = $this->adminHttp()->delete("/v1/user-packages/{$id}");

        return $this->parseResponse($response, 'admin delete user package');
    }

    // ─── Admin: User Package Upgrades ─────────────────────────
    public function adminListUserPackageUpgrades(int $userPackageId, array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list user package upgrades');
        }

        $response = $this->adminHttp()->get("/v1/user-package/{$userPackageId}/upgrades", $params);

        return $this->parseResponse($response, 'list user package upgrades');
    }

    public function adminAddUserPackageUpgrade(int $userPackageId, array $data): array
    {
        $this->ensureOutboundEnabled('admin add user package upgrade');
        $response = $this->adminHttp()->post("/v1/user-package/{$userPackageId}/upgrades", $data);

        return $this->parseResponse($response, 'admin add user package upgrade');
    }

    public function adminDeleteUserPackageUpgrade(int $userPackageId, int $upgradeId): array
    {
        $this->ensureOutboundEnabled('admin delete user package upgrade');
        $response = $this->adminHttp()->delete("/v1/user-package/{$userPackageId}/upgrades/{$upgradeId}");

        return $this->parseResponse($response, 'admin delete user package upgrade');
    }

    // ─── Admin: Package Groups CRUD ──────────────────────────
    public function createPackageGroup(array $data): array
    {
        $this->ensureOutboundEnabled('create package group');
        $response = $this->adminHttp()->post('/v1/package-groups', $data);

        return $this->parseResponse($response, 'create package group');
    }

    public function updatePackageGroup(int $id, array $data): array
    {
        $this->ensureOutboundEnabled('update package group');
        $response = $this->adminHttp()->put("/v1/package-groups/{$id}", $data);

        return $this->parseResponse($response, 'update package group');
    }

    public function deletePackageGroup(int $id): array
    {
        $this->ensureOutboundEnabled('delete package group');
        $response = $this->adminHttp()->delete("/v1/package-groups/{$id}");

        return $this->parseResponse($response, 'delete package group');
    }

    // ─── Admin: Package Ups CRUD ─────────────────────────────
    public function listPackageUps(array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('list package ups');
        }

        $response = $this->adminHttp()->get('/v1/package-ups', $params);

        return $this->parseResponse($response, 'list package ups');
    }

    public function createPackageUp(array $data): array
    {
        $this->ensureOutboundEnabled('create package up');
        $response = $this->adminHttp()->post('/v1/package-ups', $data);

        return $this->parseResponse($response, 'create package up');
    }

    public function updatePackageUp(int $id, array $data): array
    {
        $this->ensureOutboundEnabled('update package up');
        $response = $this->adminHttp()->put("/v1/package-ups/{$id}", $data);

        return $this->parseResponse($response, 'update package up');
    }

    public function deletePackageUp(int $id): array
    {
        $this->ensureOutboundEnabled('delete package up');
        $response = $this->adminHttp()->delete("/v1/package-ups/{$id}");

        return $this->parseResponse($response, 'delete package up');
    }

    // ─── User: Package Upgrades & Usage ──────────────────────
    public function getUserPackageUpgrades(User $user, int $userPackageId, array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get user package upgrades');
        }

        $response = $this->userHttp($user)->get("/v1/user-package/{$userPackageId}/upgrades", $params);

        return $this->parseResponse($response, 'get user package upgrades');
    }

    public function purchaseUserPackageUpgrade(User $user, int $userPackageId, array $data): array
    {
        $response = $this->userHttp($user)->post("/v1/user-package/{$userPackageId}/upgrades", $data);

        return $this->parseResponse($response, 'purchase user package upgrade');
    }

    public function getUserPackageUsage(User $user, int $userPackageId, array $params = []): array
    {
        if (! $this->outboundEnabled()) {
            return $this->disabledReadPayload('get user package usage');
        }

        $response = $this->userHttp($user)->get("/v1/user-package/{$userPackageId}/usage", $params);

        return $this->parseResponse($response, 'get user package usage');
    }

    public function proxyAdminRequest(string $method, string $path, array $data = []): array
    {
        if (! $this->outboundEnabled() && strtoupper($method) === 'GET') {
            return $this->disabledReadPayload("proxy admin {$path}");
        }

        $http = $this->adminHttp();
        $response = $this->sendRequest($http, $method, $path, $data);

        return $this->parseResponse($response, 'admin '.strtoupper($method)." {$path}");
    }

    private function sendRequest(PendingRequest $http, string $method, string $path, array $data = []): Response
    {
        return match (strtoupper($method)) {
            'GET' => $http->get($path, $data),
            'POST' => $http->post($path, $data),
            'PUT' => $http->put($path, $data),
            'PATCH' => $http->patch($path, $data),
            'DELETE' => $http->delete($path, $data),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    private function parseResponse(Response $response, string $context): array
    {
        $body = $response->json() ?? [];

        if (! $response->successful()) {
            Log::warning("CDNfly request failed: {$context}", [
                'status' => $response->status(),
                'message' => $this->extractUpstreamMessage($response, $body),
                'body' => $this->redactSensitivePayload($body),
            ]);

            throw new \RuntimeException("CDNfly {$context} failed.");
        }

        $code = data_get($body, 'code');
        if ($code !== null && ! $this->isSuccessCode($code)) {
            $message = $this->extractUpstreamMessage($response, $body);

            Log::warning("CDNfly business error: {$context}", [
                'code' => $code,
                'message' => $message,
                'body' => $this->redactSensitivePayload($body),
            ]);

            // Carry the upstream message: it is a short human string (not the raw
            // body), and without it every rejection surfaced as an unrelated error
            // further down the call chain.
            throw new \RuntimeException("CDNfly {$context} failed: {$message}");
        }

        return $body;
    }

    /**
     * CDNfly v6 signals success with code 0 but reports errors with *string* codes
     * such as "admin_user-34". A plain `(int) $code !== 0` comparison casts those
     * to 0 and treats the failure as success, so the real message was swallowed and
     * the caller failed later with something misleading.
     */
    private function isSuccessCode(mixed $code): bool
    {
        if (is_bool($code)) {
            return false;
        }

        if (is_int($code) || is_float($code)) {
            return (int) $code === 0;
        }

        if (is_string($code)) {
            $trimmed = trim($code);

            return $trimmed === '' || $trimmed === '0';
        }

        return false;
    }

    private function extractUpstreamMessage(Response $response, array $body): string
    {
        $message = data_get($body, 'msg') ?? data_get($body, 'message');

        if (is_string($message) && $message !== '') {
            return Str::limit($message, 500);
        }

        return "HTTP {$response->status()}";
    }

    private function redactSensitivePayload(mixed $payload): mixed
    {
        if (is_array($payload)) {
            $redacted = [];

            foreach ($payload as $key => $value) {
                if (is_string($key) && $this->isSensitiveKey($key)) {
                    $redacted[$key] = self::REDACTED;

                    continue;
                }

                $redacted[$key] = $this->redactSensitivePayload($value);
            }

            return $redacted;
        }

        if (is_string($payload) && strlen($payload) > 1000) {
            return Str::limit($payload, 1000);
        }

        return $payload;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        foreach (self::SENSITIVE_KEYS as $candidate) {
            if (str_contains($normalized, strtolower(str_replace(['-', '_'], '', $candidate)))) {
                return true;
            }
        }

        return false;
    }
}
