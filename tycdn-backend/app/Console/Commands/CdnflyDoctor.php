<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Answer "why does the console say CDNfly 未连接?" from the server itself.
 *
 * The console only ever reports the symptom: every upstream call failed, so the
 * badge reads 未连接 and the tiles stay blank. The cause sits somewhere in a
 * chain spanning a browser, nginx, php-fpm, the portal's own config, the
 * network, and CDNfly's business rules — and reading it from a browser console
 * means guessing. This walks the chain in order and stops at the first broken
 * link.
 */
class CdnflyDoctor extends Command
{
    protected $signature = 'cdnfly:doctor {--user= : Email of the account to check user-scoped credentials for}';

    protected $description = 'Diagnose the CDNfly integration: configuration, reachability, admin auth and per-user credentials';

    private bool $failed = false;

    public function handle(CdnflyApiService $api): int
    {
        $this->components->info('CDNfly integration check');

        if (! $this->checkConfig()) {
            return $this->verdict();
        }

        if (! $this->checkReachable()) {
            return $this->verdict();
        }

        $this->checkAdminAuth($api);
        $this->checkUser($api);

        return $this->verdict();
    }

    private function checkConfig(): bool
    {
        $baseUrl = (string) config('services.cdnfly.base_url');
        $adminKey = (string) config('services.cdnfly.admin_api_key');
        $adminSecret = (string) config('services.cdnfly.admin_api_secret');
        $outbound = (bool) config('services.cdnfly.outbound_enabled', true);

        $this->newLine();
        $this->components->twoColumnDetail('<options=bold>1. Configuration</>', '');
        $this->components->twoColumnDetail('base_url', $baseUrl !== '' ? $baseUrl : '<fg=red>not set</>');
        $this->components->twoColumnDetail('admin api-key', $this->mask($adminKey));
        $this->components->twoColumnDetail('admin api-secret', $this->mask($adminSecret));
        $this->components->twoColumnDetail('outbound_enabled', $outbound ? 'true' : '<fg=red>false</>');

        if ($baseUrl === '') {
            $this->problem('CDNFLY_BASE_URL is empty, so every call fails before it leaves the server.');

            return false;
        }

        if ($adminKey === '' || $adminSecret === '') {
            $this->problem('CDNFLY_ADMIN_API_KEY / CDNFLY_ADMIN_API_SECRET are empty. Copy them from the panel: 系统设置 -> API Key.');

            return false;
        }

        if (! $outbound) {
            // Not fatal for reads — the service answers those from a stub — but it
            // is exactly what makes the console look permanently disconnected.
            $this->problem('CDNFLY_OUTBOUND_ENABLED=false, so reads return empty stubs and the console reports 未连接. Set it to true.');

            return false;
        }

        return true;
    }

    private function checkReachable(): bool
    {
        $baseUrl = rtrim((string) config('services.cdnfly.base_url'), '/');

        $this->newLine();
        $this->components->twoColumnDetail('<options=bold>2. Reachability</>', '');

        try {
            $response = Http::withoutVerifying()->timeout(10)->get($baseUrl.'/v1/users', ['limit' => 1]);
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail($baseUrl, '<fg=red>unreachable</>');
            $this->problem('Cannot open a connection: '.$e->getMessage());

            return false;
        }

        $this->components->twoColumnDetail($baseUrl, 'HTTP '.$response->status());

        // 401/403 here is the healthy answer: the panel is up and refused an
        // unauthenticated call. 502/504 means the web server is up but the
        // cdnfly-go process behind it is not.
        if (in_array($response->status(), [502, 503, 504], true)) {
            $this->problem('The panel host answered '.$response->status().'. The CDNfly service behind it is down — check: systemctl status cdnfly');

            return false;
        }

        return true;
    }

    private function checkAdminAuth(CdnflyApiService $api): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<options=bold>3. Admin credentials</>', '');

        try {
            $result = $api->listUsers(['limit' => 1]);
            $this->components->twoColumnDetail('GET /v1/users', '<fg=green>ok</> ('.$this->describeCount($result).')');
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail('GET /v1/users', '<fg=red>failed</>');
            $this->problem('Admin call rejected: '.$e->getMessage());
        }
    }

    private function checkUser(CdnflyApiService $api): void
    {
        $email = (string) $this->option('user');

        $this->newLine();
        $this->components->twoColumnDetail('<options=bold>4. User credentials</>', '');

        if ($email === '') {
            $this->components->twoColumnDetail('skipped', 'pass --user=you@example.com to check an account');

            return;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->problem("No portal account with email {$email}.");

            return;
        }

        // Checked first because it gates the whole /api/cdn/* route group: a
        // linked account with an unverified email still sees 未连接.
        $this->components->twoColumnDetail(
            'email verified',
            $user->hasVerifiedEmail() ? 'yes' : '<fg=red>no — /api/cdn/* is blocked by the `verified` middleware</>',
        );
        $this->components->twoColumnDetail('cdnfly_user_id', $user->cdnfly_user_id ? (string) $user->cdnfly_user_id : '<fg=red>not linked</>');

        if (! $user->hasVerifiedEmail()) {
            $this->problem(
                'This account cannot reach CDNfly until its email is verified. With MAIL_MAILER=log no mail is delivered, '
                ."so configure a real mailer or run: php artisan cdnfly:sync-account {$email} --verify",
            );
        }

        if (! $user->cdnfly_user_id) {
            $this->problem("This account has no upstream CDNfly user. Create one with: php artisan cdnfly:sync-account {$email}");

            return;
        }

        // Decryption failure is silent in the cast, so an empty value here usually
        // means CDNFLY_ENCRYPTION_KEY changed after the credentials were stored.
        $this->components->twoColumnDetail(
            'api-key stored',
            $user->cdnfly_api_key ? $this->mask((string) $user->cdnfly_api_key) : '<fg=red>missing or undecryptable</>',
        );

        if (! $user->cdnfly_api_key || ! $user->cdnfly_api_secret) {
            $this->problem(
                "Credentials missing. Issue them with: php artisan cdnfly:sync-account {$email} "
                .'— or, if they were written before CDNFLY_ENCRYPTION_KEY changed: php artisan cdnfly:rekey',
            );

            return;
        }

        try {
            $api->proxyUserRequest($user, 'GET', '/v1/user/overview');
            $this->components->twoColumnDetail('GET /v1/user/overview', '<fg=green>ok</>');
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail('GET /v1/user/overview', '<fg=red>failed</>');
            $this->problem('User call rejected: '.$e->getMessage());
        }
    }

    private function problem(string $message): void
    {
        $this->failed = true;
        $this->newLine();
        $this->components->error($message);
    }

    private function verdict(): int
    {
        $this->newLine();

        if ($this->failed) {
            $this->components->error('CDNfly integration is not healthy. Fix the first error above and re-run.');

            return self::FAILURE;
        }

        $this->components->info('CDNfly integration looks healthy.');

        return self::SUCCESS;
    }

    /**
     * CDNfly is not consistent about where a list puts its size: some endpoints
     * answer {data: {rows, total}}, others {data: [...]}, others put total at the
     * top level. Report whichever is actually there instead of "unknown".
     *
     * @param  array<string, mixed>  $result
     */
    private function describeCount(array $result): string
    {
        foreach (['data.total', 'total', 'data.count', 'count'] as $path) {
            $value = data_get($result, $path);

            if (is_numeric($value)) {
                return 'total: '.(int) $value;
            }
        }

        foreach (['data.rows', 'data.list', 'data'] as $path) {
            $value = data_get($result, $path);

            if (is_array($value) && array_is_list($value)) {
                return count($value).' row(s) returned';
            }
        }

        return 'authenticated, size not reported';
    }

    private function mask(string $value): string
    {
        if ($value === '') {
            return '<fg=red>not set</>';
        }

        return strlen($value) <= 8
            ? str_repeat('*', strlen($value))
            : substr($value, 0, 4).str_repeat('*', 6).substr($value, -4);
    }
}
