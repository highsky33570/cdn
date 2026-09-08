<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminNodeController extends Controller
{
    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listNodes($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
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
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->getNode($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatedPendingNodeInitPayload($request);

        try {
            $data = $this->cdnfly->createNode($payload);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function pending(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listPendingNodes($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function destroyPending(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deletePendingNode($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
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
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
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
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deleteNode($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function nodeGroups(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listNodeGroups($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function storeNodeGroup(Request $request): JsonResponse
    {
        $payload = $this->validatedNodeGroupPayload($request, true);

        try {
            $data = $this->cdnfly->createNodeGroup($payload);

            return response()->json(['ok' => true, 'data' => $data], 201);
        } catch (\Throwable $e) {
            return $this->upstreamFailure($e);
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
            return $this->upstreamFailure($e);
        }
    }

    public function destroyNodeGroup(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deleteNodeGroup($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->upstreamFailure($e);
        }
    }

    public function regions(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listRegions($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function storeRegion(Request $request): JsonResponse
    {
        $payload = $this->validatedRegionPayload($request, true);

        try {
            $data = $this->cdnfly->createRegion($payload);

            return response()->json(['ok' => true, 'data' => $data], 201);
        } catch (\Throwable $e) {
            return $this->upstreamFailure($e);
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
            return $this->upstreamFailure($e);
        }
    }

    public function destroyRegion(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deleteRegion($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->upstreamFailure($e);
        }
    }

    public function storeLine(Request $request): JsonResponse
    {
        $payload = $this->validatedLinePayload($request, true);

        try {
            $data = $this->cdnfly->createLine($payload);

            return response()->json(['ok' => true, 'data' => $data], 201);
        } catch (\Throwable $e) {
            return $this->upstreamFailure($e);
        }
    }

    public function updateLine(Request $request, int $id): JsonResponse
    {
        $payload = $this->validatedLinePayload($request, false);

        if ($payload === []) {
            return response()->json(['ok' => false, 'message' => '没有可更新的字段'], 422);
        }

        try {
            $data = $this->cdnfly->updateLine($id, $payload);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->upstreamFailure($e);
        }
    }

    public function destroyLine(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deleteLine($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->upstreamFailure($e);
        }
    }

    public function lines(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listLines($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    /**
     * Report why CDNfly refused, not merely that it did.
     *
     * The rest of this controller answers a flat "CDNfly 通讯失败", which is
     * fine for endpoints whose paths have been exercised for months. These
     * writes are new, and the one thing that could be wrong about them is the
     * upstream path or a field name — exactly what a generic message hides.
     * The exception already carries CDNfly's own short message, and these
     * routes are admin-only, so showing it leaks nothing a panel operator
     * cannot already read in the panel.
     */
    private function upstreamFailure(\Throwable $e): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'CDNfly 通讯失败：'.$e->getMessage(),
        ], 502);
    }

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

        return collect($validated)
            ->reject(fn ($value): bool => $value === '' || $value === null)
            ->all();
    }

    /**
     * Mirrors AdminLinePayload in resources/js/lib/adminModulesApi.ts.
     *
     * @return array<string, mixed>
     */
    private function validatedLinePayload(Request $request, bool $creating): array
    {
        $requiredWhenCreating = $creating ? 'required' : 'sometimes';

        $validated = $request->validate([
            'name' => [$requiredWhenCreating, 'string', 'max:255'],
            // The v6 docs say a line is added *to a node group* and needs an L1
            // master node ("创建时需提供节点组和 L1 主节点"), while the console form
            // sends region_id. Rather than pick a winner and reject the other,
            // both pass through and CDNfly decides — its rejection now reaches the
            // operator verbatim instead of being hidden behind a generic 502.
            'region_id' => ['sometimes', 'integer', 'min:1'],
            'node_group_id' => ['sometimes', 'integer', 'min:1'],
            'cname_hostname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'des' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sort' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'backup_switch_type' => ['sometimes', 'nullable', 'string', Rule::in(['master_down', 'interval'])],
            'backup_switch_policy' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'l2_config_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        return collect($validated)
            ->reject(fn ($value): bool => $value === '' || $value === null)
            ->all();
    }

    /**
     * Mirrors AdminNodeGroupPayload in resources/js/lib/adminModulesApi.ts.
     *
     * validate() returns only the keys it was given rules for, so any field
     * missing here is silently dropped before it reaches CDNfly — the group would
     * be created without its failover policy and nothing would report why.
     *
     * @return array<string, mixed>
     */
    private function validatedNodeGroupPayload(Request $request, bool $creating): array
    {
        $requiredWhenCreating = $creating ? 'required' : 'sometimes';

        $validated = $request->validate([
            'name' => [$requiredWhenCreating, 'string', 'max:255'],
            'region_id' => [$requiredWhenCreating, 'integer', 'min:1'],
            'des' => ['sometimes', 'nullable', 'string', 'max:1000'],
            // Documented as optional on POST /v1/node-groups: "未传 CNAME 主机名时
            // 服务端自动生成", and an L2 config that must match the region.
            'cname_hostname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'l2_config_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'backup_switch_type' => ['sometimes', 'nullable', 'string', Rule::in(['master_down', 'interval'])],
            // A JSON string built by the client ({ip_num, interval, switch_order}),
            // passed through as CDNfly expects it rather than re-encoded here.
            'backup_switch_policy' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return collect($validated)
            ->reject(fn ($value): bool => $value === '' || $value === null)
            ->all();
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
    private function buildInstallCommand(array $master): ?string
    {
        $required = [
            'version_name',
            'ip',
            'es_ip',
            'es_pwd',
            'master_host',
            'master_port',
        ];

        foreach ($required as $field) {
            if ($this->stringField($master, $field) === '') {
                return null;
            }
        }

        $command = sprintf(
            'curl -v -m 5 http://dl2.lotcdn.com/cdnfly/agent.sh -o agent.sh || curl -v -m 5 http://us.lotcdn.com/cdnfly/agent.sh -o agent.sh && chmod +x agent.sh && ./agent.sh --master-ver %s --master-ip %s --es-ip %s --es-pwd %s --master-host %s --master-port %s',
            $this->stringField($master, 'version_name'),
            $this->stringField($master, 'ip'),
            $this->stringField($master, 'es_ip'),
            $this->stringField($master, 'es_pwd'),
            $this->stringField($master, 'master_host'),
            $this->stringField($master, 'master_port'),
        );

        $ccImgUrl = $this->stringField($master, 'cc_img_url');

        if ($ccImgUrl !== '') {
            $command .= ' --cc-img-url '.$ccImgUrl;
        }

        return $command;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringField(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
