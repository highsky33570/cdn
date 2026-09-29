<?php

namespace App\Services;

use App\Models\ServiceInstance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Whether a customer already has a live package.
 *
 * The storefront sells one active subscription at a time: renew/upgrade the
 * existing one, do not stack a second tier beside it. Local service_instances
 * are authoritative when present; CDNfly user-packages cover packages that
 * were opened outside the portal.
 */
class UserActiveSubscription
{
    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function hasActive(User $user): bool
    {
        if ($this->hasLocalActive($user)) {
            return true;
        }

        return $this->hasCdnflyActive($user);
    }

    private function hasLocalActive(User $user): bool
    {
        return ServiceInstance::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'provisioning'])
            ->where(function ($query): void {
                $query->whereNull('expired_at')
                    ->orWhere('expired_at', '>', now());
            })
            ->exists();
    }

    private function hasCdnflyActive(User $user): bool
    {
        if (! $user->cdnfly_user_id || ! $user->hasCdnflyApiKey()) {
            return false;
        }

        try {
            $result = $this->cdnfly->listUserPackages([
                'uid' => $user->cdnfly_user_id,
                'page' => 1,
                'limit' => 100,
            ]);
        } catch (\Throwable $e) {
            // Soft-fail: do not block checkout solely because the master is
            // briefly unreachable when local rows already said "no".
            Log::warning('active subscription cdnfly probe failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $rows = data_get($result, 'data');

        if (! is_array($rows)) {
            return false;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if ((string) ($row['enable'] ?? '') !== '1') {
                continue;
            }

            $end = $row['end_at'] ?? $row['end_at2'] ?? null;

            if ($end !== null && $end !== '' && Carbon::parse((string) $end)->isPast()) {
                continue;
            }

            return true;
        }

        return false;
    }
}
