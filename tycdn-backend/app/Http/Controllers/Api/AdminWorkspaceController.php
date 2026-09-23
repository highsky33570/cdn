<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use App\Support\ConfigSecrets;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Explicit master resources, behind the administrator middleware. */
class AdminWorkspaceController extends Controller
{
    use ReportsCdnflyFailures;

    public const RESOURCES = [
        'cache-jobs' => ['/v1/jobs', ['GET', 'POST']],
        'master-orders' => ['/v1/orders', ['GET']],
        'recharge-count' => ['/v1/order/count', ['GET']],
        'message-query' => ['/v1/messages', ['GET']],
        'stream-groups' => ['/v1/stream-groups', ['GET', 'POST', 'PUT', 'DELETE']],
        'stream-top' => ['/v1/monitor/stream/top', ['GET']],
        'l2-configs' => ['/v1/l2-configs', ['GET', 'POST', 'PUT', 'DELETE']],
        'l2-conds' => ['/v1/l2-conds', ['GET', 'POST', 'PUT', 'DELETE']],
        'l2-nodes' => ['/v1/l2-nodes', ['GET', 'POST', 'PUT', 'DELETE']],
        'traffic-packages' => ['/v1/traffic-packages', ['GET', 'POST', 'PUT', 'DELETE']],
        'user-traffic-packages' => ['/v1/user-traffic-packages', ['GET', 'POST', 'PUT', 'DELETE']],
        'discounts' => ['/v1/discounts', ['GET', 'POST', 'PUT', 'DELETE']],
        'coupons' => ['/v1/coupons', ['GET', 'POST', 'PUT', 'DELETE']],
        'coupon-historys' => ['/v1/coupon-historys', ['GET']],
        'messages' => ['/v1/messages', ['GET', 'POST', 'PUT', 'DELETE']],
        'tasks' => ['/v1/tasks', ['GET', 'PUT']],
        'overview' => ['/v1/admin/overview', ['GET']],
        'master-account' => ['/v1/user', ['GET']],
        'new-user-count' => ['/v1/new-user/count', ['GET']],
        'package-sold-count' => ['/v1/package-sold/count', ['GET']],
        'agent-check' => ['/v1/maintain/agent-check', ['POST']],
        'license' => ['/v1/common/auth', ['GET', 'POST']],
        'usage-count' => ['/v1/monitor/usage-count', ['GET']],
        'usage' => ['/v1/monitor/usage', ['GET']],
        'site-realtime' => ['/v1/monitor/site/realtime', ['GET']],
        'site-top' => ['/v1/monitor/site/top', ['GET']],
        'access-log' => ['/v1/monitor/site/access-log', ['GET']],
        'attack-log' => ['/v1/monitor/site/attack-log', ['GET']],
        'attack-stats' => ['/v1/monitor/site/attack-log/stats', ['GET']],
        'blackip' => ['/v1/monitor/site/blackip', ['GET', 'DELETE']],
        'blackip-count' => ['/v1/monitor/site/blackip-count', ['GET']],
        'history-blackip' => ['/v1/monitor/site/history-blackip', ['GET']],
        'stream-realtime' => ['/v1/monitor/stream/realtime', ['GET']],
        'node-realtime' => ['/v1/monitor/node/realtime', ['GET']],
        'node-top' => ['/v1/monitor/node/top', ['GET']],
        'node-ip-log' => ['/v1/monitor/node/ip-log', ['GET']],
        'node-traffic' => ['/v1/node-traffic', ['GET']],
        'package-monitor' => ['/v1/monitor/user-package', ['GET']],
        'package-nodes' => ['/v1/monitor/user-package/nodes', ['GET']],
        'master-upgrades' => ['/v1/master/upgrades', ['GET']],
        'master-upgrade-log' => ['/v1/master/upgrades/log', ['GET']],
        'agent-upgrades' => ['/v1/agent/upgrades', ['GET']],
        'agent-upgrade-log' => ['/v1/agent/upgrades/log', ['GET']],
        'transfer-status' => ['/v1/master/transfer-status', ['GET']],
        'transfer-log' => ['/v1/master/transfer-log', ['GET']],
    ];

    public function handle(Request $request, string $resource, CdnflyApiService $cdnfly, ?int $id = null): JsonResponse
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);
        [$path, $methods] = self::RESOURCES[$resource];
        abort_unless(in_array($request->method(), $methods, true), 405);
        abort_if($request->isMethod('DELETE') && $id === null, 405);
        if (in_array($resource, ['master-account', 'agent-check', 'license', 'new-user-count', 'package-sold-count'], true)) {
            abort_if($id !== null, 404);
        }
        if ($resource === 'tasks' && $request->isMethod('PUT')) {
            $request->validate(['enable' => ['required', 'integer', 'in:0']]);
            abort_if($id === null, 405);
            $payload = ['enable' => 0];
        } else {
            $payload = $request->isMethod('GET') ? $request->query() : $request->all();
        }
        if ($resource === 'cache-jobs' && $request->isMethod('POST')) {
            $request->validate([
                '*' => ['required', 'array:type,data'],
                '*.type' => ['required', 'in:clean_url,clean_dir,pre_cache_url'],
                '*.data' => ['required', 'array:url'],
                '*.data.url' => ['required', 'string', 'max:8192'],
            ]);
            abort_unless(array_is_list($payload) && count($payload) > 0 && count($payload) <= 1000, 422);
        }
        if ($resource === 'usage-count' && $request->isMethod('GET')) {
            $range = $request->validate([
                'start' => ['required', 'string', 'date_format:Y-m-d,Y-m-d H:i:s'],
                'end' => ['required', 'string', 'date_format:Y-m-d,Y-m-d H:i:s'],
            ]);
            $end = CarbonImmutable::parse($range['end']);
            if (! $end->greaterThan(CarbonImmutable::parse($range['start']))) {
                throw ValidationException::withMessages(['end' => '结束时间必须晚于开始时间。']);
            }
            // usage-count aggregates calendar days with an exclusive end date.
            // Cover the last partial day for older clients still sending times;
            // date-only and midnight end boundaries are already exclusive.
            if (strlen($range['end']) > 10 && $end->format('H:i:s') !== '00:00:00') {
                $end = $end->addDay();
            }
            $payload['start'] = substr($range['start'], 0, 10);
            $payload['end'] = $end->format('Y-m-d');
        }
        if ($resource === 'master-account' || ($request->isMethod('POST') && in_array($resource, ['agent-check', 'license'], true))) {
            $payload = [];
        }
        try {
            $data = $cdnfly->proxyAdminRequest($request->method(), $path.($id === null ? '' : '/'.$id), $payload);
            if ($resource === 'master-account') {
                $account = $data['data'] ?? $data;
                $data = ['data' => array_intersect_key($account, array_flip(['id', 'name']))];
            }

            return response()->json(['ok' => true, 'data' => ConfigSecrets::mask($data)]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
