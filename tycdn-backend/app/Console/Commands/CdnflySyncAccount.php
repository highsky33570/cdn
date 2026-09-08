<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CdnflyAccountService;
use Illuminate\Console\Command;

/**
 * Link a portal account to CDNfly from the command line.
 *
 * The upstream account is normally created when the user verifies their email,
 * and the self-service retry at POST /api/auth/sync-api-key refuses to run until
 * that verification has happened. That leaves one gap with no way out: an
 * account that was seeded rather than registered, or one created while mail
 * delivery was not configured, can never verify and therefore can never be
 * linked — every /api/cdn/* call fails and the console reports CDNfly 未连接
 * forever.
 *
 * This is the operator's way through that gap. It performs the same
 * ensureAccount() work as the HTTP path, so a partially-linked account (upstream
 * user but no API key) is repaired rather than duplicated.
 */
class CdnflySyncAccount extends Command
{
    protected $signature = 'cdnfly:sync-account
        {email : Email of the portal account to link}
        {--verify : Also mark the email verified, for accounts that cannot receive mail}';

    protected $description = 'Create or repair a portal account\'s CDNfly user and API credentials';

    public function handle(CdnflyAccountService $accounts): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->components->error("No portal account with email {$email}.");

            return self::FAILURE;
        }

        if ($this->option('verify') && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $this->components->info("Marked {$email} as verified.");
        }

        try {
            $outcome = $accounts->ensureAccount($user);
        } catch (\Throwable $e) {
            $this->components->error('CDNfly refused the request: '.$e->getMessage());

            return self::FAILURE;
        }

        $user->refresh();

        $this->components->twoColumnDetail('outcome', match ($outcome) {
            'already_ready' => 'already linked, nothing to do',
            'synced' => 'adopted the API key CDNfly already held',
            default => 'created a CDNfly user and API key',
        });
        $this->components->twoColumnDetail('cdnfly_user_id', (string) $user->cdnfly_user_id);

        // The credentials alone are not enough: /api/cdn/* also sits behind the
        // `verified` middleware, so an unverified account still sees 未连接.
        if (! $user->hasVerifiedEmail()) {
            $this->newLine();
            $this->components->warn(
                'This account is linked but its email is still unverified, so /api/cdn/* stays blocked. '
                .'Configure mail delivery, or re-run with --verify.',
            );
        }

        return self::SUCCESS;
    }
}
