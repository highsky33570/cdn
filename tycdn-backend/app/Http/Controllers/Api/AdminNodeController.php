<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminNodeController extends Controller
{
    use ReportsCdnflyFailures;

    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listNodes($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function installCommand(): JsonResponse
    {
        try {
            $data = $this->cdnfly->getMasterUpgrade();
            $master = $this->cdnflyData($data);

            return response()->json([
                'ok' => true,
                'data' => [
                    'command' => $this->buildInstallCommand($master),
                    'version_name' => $this->stringField($master, 'version_name'),
                    'master_ip' => $this->stringField($master, 'ip'),
                    'es_ip' => $this->stringField($master, 'es_ip'),
                    'master_host' => $this->stringField($master, 'master_host'),
                    'master_port' => $this->stringField($master, 'master_port'),
                    'cc_img_url' => $this->stringField($master, 'cc_img_url'),
                    'has_es_pwd' => $this->stringField($master, 'es_pwd') !== '',
                    'cdnfly_outbound_disabled' => (bool) data_get($data, 'cdnfly_outbound_disabled', false),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->getNode($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatedPendingNodeInitPayload($request);

        try {
            $data = $this->cdnfly->createNode($payload);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function pending(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listPendingNodes($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroyPending(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deletePendingNode($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $payload = $this->validatedNodePayload($request, false);

        if ($payload === []) {
            return response()->json(['ok' => false, 'message' => '没有可更新的字段'], 422);
        }

        try {
            $data = $this->cdnfly->updateNode($id, $payload);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function setEnabled(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'enable' => ['required', 'boolean'],
        ]);

        $enable = (bool) $validated['enable'];
        $payload = [
            'id' => $id,
            'enable' => $enable ? 1 : 0,
            'target' => 'node',
        ];

        if (! $enable) {
            $payload['disable_by'] = 'admin';
        }

        try {
            $data = $this->cdnfly->batchUpdateNodes([$payload]);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deleteNode($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function nodeGroups(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listNodeGroups($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function storeNodeGroup(Request $request): JsonResponse
    {
        $payload = $this->validatedNodeGroupPayload($request, true);

        try {
            $data = $this->cdnfly->createNodeGroup($payload);

            return response()->json(['ok' => true, 'data' => $data], 201);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function updateNodeGroup(Request $request, int $id): JsonResponse
    {
        $payload = $this->validatedNodeGroupPayload($request, false);

        if ($payload === []) {
            return response()->json(['ok' => false, 'message' => '没有可更新的字段'], 422);
        }

        try {
            $data = $this->cdnfly->updateNodeGroup($id, $payload);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroyNodeGroup(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deleteNodeGroup($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function regions(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listRegions($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function storeRegion(Request $request): JsonResponse
    {
        $payload = $this->validatedRegionPayload($request, true);

        try {
            $data = $this->cdnfly->createRegion($payload);

            return response()->json(['ok' => true, 'data' => $data], 201);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function updateRegion(Request $request, int $id): JsonResponse
    {
        $payload = $this->validatedRegionPayload($request, false);

        if ($payload === []) {
            return response()->json(['ok' => false, 'message' => '没有可更新的字段'], 422);
        }

        try {
            $data = $this->cdnfly->updateRegion($id, $payload);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroyRegion(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deleteRegion($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * The DNS lines nodes can be assigned to.
     *
     * Not a CRUD resource — CDNfly keeps them in a single system config row,
     * double-JSON-encoded. See CdnflyApiService::listDnsLines().
     */
    public function dnsLines(): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'data' => $this->cdnfly->listDnsLines()]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * Assign node IPs to a DNS line within a node group.
     *
     * This used to validate a line as though it were a named object with a
     * region, CNAME, sort order and failover policy — none of which CDNfly
     * accepts here. Its real payload, taken from the panel's own source, is an
     * array of node assignments.
     */
    public function storeLine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignments' => ['required', 'array', 'min:1', 'max:200'],
            'assignments.*.node_group_id' => ['required', 'integer', 'min:1'],
            'assignments.*.node_id' => ['required', 'integer', 'min:1'],
            'assignments.*.node_ip_id' => ['required', 'integer', 'min:1'],
            'assignments.*.line_id' => ['required', 'integer', 'min:0'],
            'assignments.*.line_name' => ['required', 'string', 'max:100'],
            // Present only when the node is being added as a backup IP.
            'assignments.*.is_backup' => ['sometimes', 'integer', 'in:0,1'],
        ]);

        try {
            $data = $this->cdnfly->assignLines($validated['assignments']);

            return response()->json(['ok' => true, 'data' => $data], 201);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * Unassign one or more nodes from a line. `{id}` may be a comma-separated
     * list, matching how the panel removes a multi-IP node in one call.
     */
    public function destroyLine(string $id): JsonResponse
    {
        $ids = array_values(array_filter(
            array_map('trim', explode(',', $id)),
            static fn (string $v): bool => ctype_digit($v) && (int) $v > 0,
        ));

        if ($ids === []) {
            return response()->json(['ok' => false, 'message' => '缺少有效的线路 ID'], 422);
        }

        try {
            $data = $this->cdnfly->deleteLines($ids);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function lines(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listLines($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    /**
     * Mirrors AdminRegionPayload in resources/js/lib/adminModulesApi.ts.
     *
     * @return array<string, mixed>
     */
    private function validatedRegionPayload(Request $request, bool $creating): array
    {
        $validated = $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'des' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sort' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'l2_check_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
        ]);

        return $this->castIntegers(
            collect($validated)
                ->reject(fn ($value): bool => $value === '' || $value === null)
                ->all(),
            ['sort', 'l2_check_port'],
        );
    }

    /**
     * Mirrors the payload the CDNfly panel itself sends to /v1/node-groups.
     *
     * Captured from the panel's own bundle (chunk-579346f2), because sending a
     * field its create struct does not accept is rejected with a bare
     * "数据类型错误" that names neither the field nor the expected type.
     *
     *   create: {region_id, name, cname_hostname, des, sort, backup_switch_type,
     *            l2_config_id}
     *   edit:   the above plus {id, v4_cname_hostname, backup_switch_policy}
     *
     * Two things that cost a failed create here:
     *
     * 1. `backup_switch_policy` is **not sent on create** — only on edit, and
     *    then as a JSON *string* (the panel does JSON.parse on it when loading).
     *    We were sending it on create, which is what produced 数据类型错误.
     *
     * 2. `sort` and `l2_config_id` go through the panel's `integerOrNull`: a
     *    blank becomes null, a numeric string becomes an int. They are integers
     *    upstream, not the strings this used to declare.
     */
    private function validatedNodeGroupPayload(Request $request, bool $creating): array
    {
        $requiredWhenCreating = $creating ? 'required' : 'sometimes';

        $validated = $request->validate([
            'name' => [$requiredWhenCreating, 'string', 'max:255'],
            'region_id' => [$requiredWhenCreating, 'integer', 'min:1'],
            'des' => ['sometimes', 'nullable', 'string', 'max:1000'],
            // Optional upstream: "未传 CNAME 主机名时服务端自动生成".
            'cname_hostname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'v4_cname_hostname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'l2_config_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'backup_switch_type' => ['sometimes', 'nullable', 'string', Rule::in(['master_down', 'interval'])],
            // A JSON string, e.g. {"ip_num":2,"interval":60,"switch_order":"rand"}.
            'backup_switch_policy' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $payload = $this->castIntegers(
            collect($validated)
                ->reject(fn ($value): bool => $value === '' || $value === null)
                ->all(),
            ['region_id', 'sort', 'l2_config_id'],
        );

        // The create endpoint has no such field; only the edit endpoint takes it.
        if ($creating) {
            unset($payload['backup_switch_policy'], $payload['v4_cname_hostname']);
        }

        return $payload;
    }

    private function validatedNodePayload(Request $request, bool $creating): array
    {
        $requiredWhenCreating = $creating ? 'required' : 'sometimes';

        $validated = $request->validate([
            'name' => [$requiredWhenCreating, 'string', 'max:255'],
            'ip' => [$requiredWhenCreating, 'string', 'max:255'],
            'node_group_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'region_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'line_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', 'integer', Rule::in([0, 1])],
            'weight' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'bandwidth' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'des' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        return collect($validated)
            ->reject(fn ($value): bool => $value === '')
            ->all();
    }

    /**
     * @return array{pending_node_id: int, region_id: int, name: string, des: string, type: string}
     */
    private function validatedPendingNodeInitPayload(Request $request): array
    {
        $validated = $request->validate([
            'pending_node_id' => ['required', 'integer', 'min:1'],
            'region_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'des' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::in(['L1', 'L2'])],
        ]);

        return [
            'pending_node_id' => (int) $validated['pending_node_id'],
            'region_id' => (int) $validated['region_id'],
            'name' => trim((string) $validated['name']),
            'des' => (string) ($validated['des'] ?? ''),
            'type' => (string) $validated['type'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cdnflyData(array $payload): array
    {
        $data = $payload['data'] ?? $payload;

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $master
     */
    /**
     * Build the node install command for whichever master generation is running.
     *
     * v6 changed both the installer and its arguments, and this method only knew
     * the v5 form — so against a v6 master the console handed operators a command
     * that always failed:
     *
     *   v5: agent.sh     --master-ver v6.0.11 …   looks up the agent version via
     *                                             the update API, then fetches
     *                                             cdnfly-agent-<ver>-<OS>.tar.gz
     *                                             — a name v6 never publishes,
     *                                             so it 404s on every OS.
     *
     *   v6: agent_v6.sh  --ver v6.0.3 …           the agent version is passed in
     *                                             directly and it fetches
     *                                             cdnfly-go-agent-<ver>-linux-amd64.tar.gz
     *
     * The agent version for v6 comes from the master's own `agent_ver`, an
     * integer like 60003 meaning v6.0.3.
     */
    private function buildInstallCommand(array $master): ?string
    {
        $required = ['version_name', 'ip', 'es_ip', 'es_pwd', 'master_host', 'master_port'];

        foreach ($required as $field) {
            if ($this->stringField($master, $field) === '') {
                return null;
            }
        }

        $command = $this->isV6Master($master)
            ? $this->buildV6InstallCommand($master)
            : $this->buildLegacyInstallCommand($master);

        if ($command === null) {
            return null;
        }

        $ccImgUrl = $this->stringField($master, 'cc_img_url');

        if ($ccImgUrl !== '') {
            $command .= " --cc-img-url '".$ccImgUrl."'";
        }

        return $command;
    }

    /**
     * @param  array<string, mixed>  $master
     */
    private function isV6Master(array $master): bool
    {
        $version = ltrim($this->stringField($master, 'version_name'), 'vV');

        return (int) strtok($version, '.') >= 6;
    }

    /**
     * @param  array<string, mixed>  $master
     */
    private function buildV6InstallCommand(array $master): ?string
    {
        $agentVersion = $this->agentVersionName($master);

        // Without the agent version there is nothing for --ver, and guessing it
        // is what produced the broken command in the first place.
        if ($agentVersion === null) {
            return null;
        }

        return sprintf(
            '(curl -fL --connect-timeout 10 --max-time 60 http://dl2.lotcdn.com/cdnfly/agent_v6.sh -o agent_v6.sh'
            .' || curl -fL --connect-timeout 10 --max-time 60 http://us.lotcdn.com/cdnfly/agent_v6.sh -o agent_v6.sh)'
            ." && chmod 700 agent_v6.sh && ./agent_v6.sh --ver '%s' --master-ip '%s' --es-ip '%s'"
            ." --es-pwd '%s' --master-host '%s' --master-port '%s'",
            $agentVersion,
            $this->stringField($master, 'ip'),
            $this->stringField($master, 'es_ip'),
            $this->stringField($master, 'es_pwd'),
            $this->stringField($master, 'master_host'),
            $this->stringField($master, 'master_port'),
        );
    }

    /**
     * @param  array<string, mixed>  $master
     */
    private function buildLegacyInstallCommand(array $master): string
    {
        return sprintf(
            'curl -v -m 5 http://dl2.lotcdn.com/cdnfly/agent.sh -o agent.sh || curl -v -m 5 http://us.lotcdn.com/cdnfly/agent.sh -o agent.sh && chmod +x agent.sh && ./agent.sh --master-ver %s --master-ip %s --es-ip %s --es-pwd %s --master-host %s --master-port %s',
            $this->stringField($master, 'version_name'),
            $this->stringField($master, 'ip'),
            $this->stringField($master, 'es_ip'),
            $this->stringField($master, 'es_pwd'),
            $this->stringField($master, 'master_host'),
            $this->stringField($master, 'master_port'),
        );
    }

    /**
     * agent_ver is a packed integer: 60003 -> v6.0.3, 51827 -> v5.18.27.
     *
     * @param  array<string, mixed>  $master
     */
    private function agentVersionName(array $master): ?string
    {
        $raw = $this->stringField($master, 'agent_ver');

        if (! ctype_digit($raw) || strlen($raw) < 5) {
            return null;
        }

        $major = (int) substr($raw, 0, strlen($raw) - 4);
        $minor = (int) substr($raw, -4, 2);
        $patch = (int) substr($raw, -2);

        return sprintf('v%d.%d.%d', $major, $minor, $patch);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    /**
     * Send numbers as numbers.
     *
     * Laravel's `integer` rule accepts the string "7" and validated() hands
     * back the string, so a form posting "7" reached CDNfly as "7". The panel
     * runs the same fields through its own integerOrNull and sends 7. CDNfly
     * rejects type mismatches on these endpoints with a bare 数据类型错误, so
     * matching the panel exactly is cheaper than discovering which fields it
     * happens to tolerate.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function castIntegers(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                $payload[$key] = (int) $payload[$key];
            }
        }

        return $payload;
    }

    private function stringField(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
