<?php

namespace App\Console\Commands;

use App\Exceptions\CdnflyAccountExistsException;
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
        {--verify : Also mark the email verified, for accounts that cannot receive mail}
        {--adopt : Link to the CDNfly account that already holds this email instead of creating one}';

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
            $outcome = $accounts->ensureAccount($user, (bool) $this->option('adopt'));
        } catch (CdnflyAccountExistsException $e) {
            // Not an error to retry blindly: taking over an account hands this
            // portal user whatever that upstream account can do. Show what was
            // found so the operator can check it is the orphan they think it is —
            // a panel administrator's account would be a very different thing to
            // adopt.
            $this->components->error($e->getMessage());
            $this->newLine();
            $this->line('  CDNfly allows one account per email, so no new one can be created.');
            $this->line('  If that account belongs to this portal user, link to it with:');
            $this->newLine();
            $this->line("  <options=bold>php artisan cdnfly:sync-account {$email} --adopt</>");
            $this->newLine();
            $this->line('  Check it first at '.rtrim((string) config('services.cdnfly.base_url'), '/').'/dashboard — adopting');
            $this->line('  hands this portal user that account\'s API credentials.');

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->components->error('CDNfly refused the request: '.$e->getMessage());

            return self::FAILURE;
        }

        $user->refresh();

        $this->components->twoColumnDetail('outcome', match ($outcome) {
            'already_ready' => 'already linked, nothing to do',
            'adopted' => 'adopted the CDNfly account that already held this email',
            'synced' => 'adopted the API key CDNfly already held',
            default => 'created a CDNfly user and API key',
        });
        $this->components->twoColumnDetail(
            'cdnfly_user_id',
            $user->cdnfly_user_id ? (string) $user->cdnfly_user_id : '<fg=red>not linked</>',
        );

        // Never report success on a half-linked row. Printing 'already linked'
        // beside an empty cdnfly_user_id is how this went unnoticed the first
        // time: the operator is told the work is done while the account is still
        // unusable.
        if (! $user->cdnfly_user_id || ! $user->hasCdnflyApiKey()) {
            $this->newLine();
            $this->components->error(
                'The account is still incomplete: it needs both a cdnfly_user_id and stored credentials',
            );

            return self::FAILURE;
        }

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
