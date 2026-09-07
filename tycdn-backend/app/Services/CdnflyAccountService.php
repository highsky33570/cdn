<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Owns the "every portal user has a CDNfly account + API credentials" invariant.
 *
 * Previously this lived only in the Verified listener, which fires exactly once.
 * If CDNfly was unreachable at that moment the user was stranded permanently:
 * both recovery paths (user retry, admin sync) refused to act without an existing
 * cdnfly_user_id, and nothing ever created one. Centralising it here means every
 * entry point can recover the account from any partial state.
 */
class CdnflyAccountService
{
    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    /**
     * Bring the user up to "has CDNfly account and API credentials", from any
     * starting point: no account at all, account but no key, or fully ready.
     *
     * @return string one of already_ready|synced|created
     *
     * @throws \Throwable when CDNfly is unreachable or rejects the request
     */
    public function ensureAccount(User $user): string
    {
        if ($user->hasCdnflyApiKey()) {
            return 'already_ready';
        }

        if (! $user->cdnfly_user_id) {
            $created = $this->createUpstreamUser($user);

            $user->cdnfly_user_id = $created['cdnfly_user_id'];
            $user->save();
        }

        // An earlier partial run may already have enabled a key upstream; reuse it
        // rather than issuing a second one and desynchronising the stored copy.
        $existing = $this->cdnfly->getUserApiKey($user->cdnfly_user_id);
        $credentials = $existing ?: $this->cdnfly->enableUserApiKey($user->cdnfly_user_id);

        $user->update([
            'cdnfly_api_key' => $credentials['api_key'],
            'cdnfly_api_secret' => $credentials['api_secret'],
            'cdnfly_synced_at' => now(),
        ]);

        return $existing ? 'synced' : 'created';
    }

    /**
     * CDNfly usernames are globally unique, and it ships its own `admin` and
     * `jason` accounts, so a portal username can collide with one we do not own.
     * It reports that as code:0 with data:0.
     *
     * Retry once under a portal-scoped name rather than adopting the existing
     * upstream account — adopting would hand this user someone else's API
     * credentials, which is privilege escalation, not a convenience.
     *
     * @return array{cdnfly_user_id: int, raw: array}
     */
    private function createUpstreamUser(User $user): array
    {
        try {
            return $this->cdnfly->createCdnflyUser(
                $user->name,
                $user->email,
                $this->generatePassword(),
            );
        } catch (\RuntimeException $e) {
            $scopedName = $user->name.'-ty'.$user->id;

            Log::warning('CDNfly rejected the username; retrying with a portal-scoped name', [
                'user_id' => $user->id,
                'attempted' => $user->name,
                'retry_as' => $scopedName,
                'error' => $e->getMessage(),
            ]);

            return $this->cdnfly->createCdnflyUser(
                $scopedName,
                $user->email,
                $this->generatePassword(),
            );
        }
    }

    /**
     * 128 bits of entropy, with a fixed suffix so it satisfies the usual
     * upper/lower/digit policy on the CDNfly side. Never shown to the user: the
     * portal talks to CDNfly with API credentials, not this password.
     */
    private function generatePassword(): string
    {
        $random = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');

        return $random.'Aa1';
    }
}
