<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\CdnflyEncrypter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CdnflyRekey extends Command
{
    protected $signature = 'cdnfly:rekey
        {--dry-run : Show which users would be re-keyed without making changes}
        {--rollback : Re-encrypt from CDNFLY_ENCRYPTION_KEY back to APP_KEY}';

    protected $description = 'Re-encrypt all CDNfly credentials from APP_KEY to CDNFLY_ENCRYPTION_KEY (or rollback).';

    public function handle(): int
    {
        $encrypter = app(CdnflyEncrypter::class);
        $dryRun = (bool) $this->option('dry-run');
        $rollback = (bool) $this->option('rollback');

        if (! $rollback && ! $encrypter->usesDedicatedKey()) {
            $this->components->error('CDNFLY_ENCRYPTION_KEY is not configured. Run `php artisan cdnfly:generate-key` first.');

            return self::FAILURE;
        }

        $users = User::query()
            ->whereNotNull('cdnfly_api_key')
            ->orWhereNotNull('cdnfly_api_secret')
            ->get();

        if ($users->isEmpty()) {
            $this->components->info('No users with CDNfly credentials found.');

            return self::SUCCESS;
        }

        $direction = $rollback ? 'CDNFLY_KEY → APP_KEY' : 'APP_KEY → CDNFLY_KEY';
        $this->components->info(sprintf(
            '%s %d user(s) credentials (%s).',
            $dryRun ? 'Would re-key' : 'Re-keying',
            $users->count(),
            $direction,
        ));

        if ($dryRun) {
            $this->table(
                ['ID', 'Name', 'Has Key', 'Has Secret'],
                $users->map(fn (User $u) => [
                    $u->id,
                    $u->name,
                    $u->getRawOriginal('cdnfly_api_key') ? 'Yes' : 'No',
                    $u->getRawOriginal('cdnfly_api_secret') ? 'Yes' : 'No',
                ])->toArray(),
            );

            return self::SUCCESS;
        }

        $success = 0;
        $failed = 0;

        DB::beginTransaction();

        try {
            foreach ($users as $user) {
                try {
                    $rawKey = $user->getRawOriginal('cdnfly_api_key');
                    $rawSecret = $user->getRawOriginal('cdnfly_api_secret');

                    // 解密（用旧密钥）
                    $plainKey = $rawKey ? $this->decryptOld($rawKey, $rollback, $encrypter) : null;
                    $plainSecret = $rawSecret ? $this->decryptOld($rawSecret, $rollback, $encrypter) : null;

                    // 加密（用新密钥）
                    $newKey = $plainKey ? $this->encryptNew($plainKey, $rollback, $encrypter) : null;
                    $newSecret = $plainSecret ? $this->encryptNew($plainSecret, $rollback, $encrypter) : null;

                    // 直接写入 raw 值，绕过 cast
                    DB::table('users')->where('id', $user->id)->update([
                        'cdnfly_api_key' => $newKey,
                        'cdnfly_api_secret' => $newSecret,
                    ]);

                    $success++;
                } catch (\Throwable $e) {
                    $failed++;

                    Log::error('CDNfly credential re-key failed', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);

                    $this->components->error("User #{$user->id}: {$e->getMessage()}");
                }
            }

            if ($failed > 0) {
                DB::rollBack();
                $this->components->error("Rolling back — {$failed} user(s) failed. Fix issues and retry.");

                return self::FAILURE;
            }

            DB::commit();

            $this->components->info("Re-key complete. {$success} user(s) migrated successfully.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->components->error("Fatal error during re-key: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * 用旧密钥解密。
     */
    private function decryptOld(string $payload, bool $rollback, CdnflyEncrypter $encrypter): string
    {
        if ($rollback) {
            // rollback 模式：旧数据用 CDNFLY_KEY 加密
            return $encrypter->decrypt($payload);
        }

        // 正常模式：旧数据用 APP_KEY 加密
        return Crypt::decrypt($payload);
    }

    /**
     * 用新密钥加密。
     */
    private function encryptNew(string $plain, bool $rollback, CdnflyEncrypter $encrypter): string
    {
        if ($rollback) {
            // rollback 模式：写回 APP_KEY 加密
            return Crypt::encrypt($plain);
        }

        // 正常模式：用 CDNFLY_KEY 加密
        return $encrypter->encrypt($plain);
    }
}
