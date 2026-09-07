<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RotateCdnflyApiKeys extends Command
{
    protected $signature = 'cdnfly:rotate-keys
        {--days=90 : Rotate keys older than this many days}
        {--user= : Rotate only for a specific user ID}
        {--dry-run : Show which users would be rotated without making changes}';

    protected $description = 'Rotate CDNfly API keys for users whose keys have not been synced within the specified period.';

    public function handle(CdnflyApiService $cdnfly): int
    {
        $days = (int) $this->option('days');
        $userId = $this->option('user');
        $dryRun = (bool) $this->option('dry-run');

        $query = User::query()
            ->whereNotNull('cdnfly_user_id')
            ->whereNotNull('cdnfly_api_key');

        if ($userId) {
            $query->where('id', $userId);
        } else {
            $query->where(function ($q) use ($days) {
                $q->whereNull('cdnfly_synced_at')
                    ->orWhere('cdnfly_synced_at', '<', now()->subDays($days));
            });
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->components->info('No users require key rotation.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '%s %d user(s) for API key rotation (threshold: %d days).',
            $dryRun ? 'Would rotate' : 'Rotating',
            $users->count(),
            $days,
        ));

        if ($dryRun) {
            $this->table(
                ['ID', 'Name', 'CDNfly UID', 'Last Synced'],
                $users->map(fn (User $u) => [
                    $u->id,
                    $u->name,
                    $u->cdnfly_user_id,
                    $u->cdnfly_synced_at?->toDateTimeString() ?? 'never',
                ])->toArray(),
            );

            return self::SUCCESS;
        }

        $success = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                $result = $cdnfly->enableUserApiKey($user->cdnfly_user_id);

                $user->cdnfly_api_key = $result['api_key'];
                $user->cdnfly_api_secret = $result['api_secret'];
                $user->cdnfly_synced_at = now();
                $user->save();

                $success++;

                Log::info('CDNfly API key rotated', [
                    'user_id' => $user->id,
                    'cdnfly_user_id' => $user->cdnfly_user_id,
                ]);
            } catch (\Throwable $e) {
                $failed++;

                Log::error('CDNfly API key rotation failed', [
                    'user_id' => $user->id,
                    'cdnfly_user_id' => $user->cdnfly_user_id,
                    'error' => $e->getMessage(),
                ]);

                $this->components->error("User #{$user->id}: {$e->getMessage()}");
            }
        }

        $this->components->info(sprintf(
            'Rotation complete. success=%d failed=%d',
            $success,
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
