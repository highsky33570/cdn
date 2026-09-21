<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Models\ServiceInstance;
use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminRecoveryController extends Controller
{
    use ReportsCdnflyFailures;

    public function mapping(User $user, CdnflyApiService $cdnfly): JsonResponse
    {
        try {
            $candidate = $cdnfly->findUserByEmail($user->email);
            if ($candidate) {
                $candidate['linked_local_user_id'] = User::where('cdnfly_user_id', $candidate['id'])->where('id', '!=', $user->id)->value('id');
            }

            return response()->json(['ok' => true, 'data' => [
                'local' => $user->only(['id', 'name', 'email', 'cdnfly_user_id']),
                'candidate' => $candidate,
            ]]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function bind(Request $request, User $user, CdnflyApiService $cdnfly): JsonResponse
    {
        $validated = $request->validate(['cdnfly_user_id' => ['required', 'integer', 'min:1']]);
        try {
            $candidate = $cdnfly->findUserByEmail($user->email);
            if (! $candidate || $candidate['id'] !== $validated['cdnfly_user_id']) {
                throw ValidationException::withMessages(['cdnfly_user_id' => '主控账号已变更，请重新核对邮箱和映射。']);
            }
            $remote = $cdnfly->proxyAdminRequest('GET', '/v1/users/'.$candidate['id']);
            $record = $remote['data'] ?? $remote;
            if ((int) ($record['type'] ?? 0) !== 2) {
                throw ValidationException::withMessages(['cdnfly_user_id' => '个人控制台只可绑定主控普通用户账号。']);
            }
            $lock = Cache::lock('cdnfly-user-mapping:'.$candidate['id'], 30);
            if (! $lock->get()) {
                return response()->json(['ok' => false, 'message' => '正在处理此账号，请稍后重试。'], 409);
            }
            try {
                DB::transaction(function () use ($user, $candidate): void {
                    $current = User::query()->lockForUpdate()->findOrFail($user->id);
                    if (User::where('cdnfly_user_id', $candidate['id'])->where('id', '!=', $current->id)->exists()) {
                        throw ValidationException::withMessages(['cdnfly_user_id' => '该主控账号已被其他本地用户绑定。']);
                    }
                    if ($current->cdnfly_user_id && (int) $current->cdnfly_user_id !== $candidate['id']) {
                        throw ValidationException::withMessages(['cdnfly_user_id' => '已有映射，不能通过恢复操作覆盖。']);
                    }
                    if ((int) $current->cdnfly_user_id !== $candidate['id']) {
                        $current->forceFill(['cdnfly_user_id' => $candidate['id'], 'cdnfly_api_key' => null, 'cdnfly_api_secret' => null, 'cdnfly_synced_at' => null])->save();
                    }
                });
            } finally {
                $lock->release();
            }

            return response()->json(['ok' => true, 'message' => '映射已保存，可继续同步 API 密钥。']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function service(ServiceInstance $service, CdnflyApiService $cdnfly): JsonResponse
    {
        $service->load(['order', 'user']);
        try {
            $remote = $service->user?->cdnfly_user_id ? $cdnfly->listUserPackages(['uid' => $service->user->cdnfly_user_id, 'limit' => 0]) : ['data' => []];

            return response()->json(['ok' => true, 'data' => [
                'service' => $service->only(['id', 'status', 'cdnfly_service_id', 'source_order_id']),
                'order' => $service->order?->only(['order_no', 'status', 'amount_usdt', 'paid_at', 'provisioned_at', 'balance_credited_at']),
                'cdnfly_user_id' => $service->user?->cdnfly_user_id,
                'credentials_ready' => $service->user?->hasCdnflyApiKey() ?? false,
                'error' => $service->extra['last_error'] ?? null,
                'remote_packages' => $remote,
                'can_retry' => $service->status === 'failed' && $service->order?->status === 'paid' && $service->order?->provisioned_at === null && $service->user?->cdnfly_user_id && $service->user?->hasCdnflyApiKey() && ! $service->cdnfly_service_id,
            ]]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
