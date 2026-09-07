<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\CdnflyAccountService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;

class SyncCdnflyOnVerified
{
    public function __construct(
        private readonly CdnflyAccountService $accounts,
    ) {}

    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        try {
            $outcome = $this->accounts->ensureAccount($user);

            Log::info('邮箱验证通过，CDNfly 账号已就绪', [
                'user_id' => $user->id,
                'cdnfly_user_id' => $user->cdnfly_user_id,
                'outcome' => $outcome,
            ]);
        } catch (\Throwable $e) {
            // Not fatal: the user can retry via POST /api/auth/retry-api-key and an
            // admin can force it from the console. Both now recover from this state.
            Log::error('邮箱验证通过，但 CDNfly 账号同步失败（可稍后重试）', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
