<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Balance-funded automatic renewal.
 *
 * CDNfly is a prepaid system: recharge credits a balance, and renewing a
 * package deducts that balance at the package's price, refusing with 余额不足
 * when it is short. CDNfly itself only renews on a manual click, so this sweep
 * supplies the automation — it finds packages near expiry and renews each as
 * its owner, letting CDNfly do the charging.
 *
 * The balance is the only gate. A customer keeps their packages alive by
 * keeping the balance topped up; when it runs dry the renewal is skipped and
 * the package lapses, exactly as a prepaid plan should behave. No per-package
 * opt-in flag is kept, in line with holding all package state in CDNfly.
 */
class PackageAutoRenewService
{
    /** CDNfly's business message when the balance cannot cover a renewal. */
    private const INSUFFICIENT_BALANCE = '余额不足';

    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    /**
     * @return array{renewed:int, insufficient:int, skipped:int, failed:int, considered:int}
     */
    public function run(): array
    {
        $summary = ['renewed' => 0, 'insufficient' => 0, 'skipped' => 0, 'failed' => 0, 'considered' => 0];

        if (! config('services.cdnfly.auto_renew_enabled', true)) {
            return $summary;
        }

        if (! (bool) config('services.cdnfly.outbound_enabled', true)) {
            return $summary;
        }

        $days = max(0, (int) config('services.cdnfly.auto_renew_days', 3));
        $duration = (string) config('services.cdnfly.auto_renew_duration', 'month');
        $cutoff = Carbon::now()->addDays($days);

        // Resolve owners once: renewal must act as the package's user so the
        // charge lands on their balance, and that needs their CDNfly creds.
        $usersByCdnflyId = User::query()
            ->whereNotNull('cdnfly_user_id')
            ->whereNotNull('cdnfly_api_key')
            ->whereNotNull('cdnfly_api_secret')
            ->get()
            ->keyBy(fn (User $u): string => (string) $u->cdnfly_user_id);

        foreach ($this->expiringPackages($cutoff) as $pkg) {
            $summary['considered']++;

            $id = (int) ($pkg['id'] ?? 0);
            $uid = (string) ($pkg['uid'] ?? '');
            $user = $usersByCdnflyId->get($uid);

            if ($id <= 0 || ! $user) {
                // A package whose owner has no portal account or credentials
                // cannot be renewed on their behalf; leave it for manual action.
                $summary['skipped']++;

                continue;
            }

            try {
                $this->cdnfly->renewUserPackage($user, $id, ['duration' => $duration]);
                $summary['renewed']++;

                Log::info('package auto-renewed', [
                    'user_package_id' => $id,
                    'cdnfly_uid' => $uid,
                    'duration' => $duration,
                ]);
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), self::INSUFFICIENT_BALANCE)) {
                    $summary['insufficient']++;

                    // Not an error: the customer simply needs to top up. Logged
                    // at notice level so a reminder can be sent from here later.
                    Log::notice('package auto-renew skipped: insufficient balance', [
                        'user_package_id' => $id,
                        'cdnfly_uid' => $uid,
                    ]);

                    continue;
                }

                $summary['failed']++;

                Log::warning('package auto-renew failed', [
                    'user_package_id' => $id,
                    'cdnfly_uid' => $uid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * Active packages expiring on or before the cutoff.
     *
     * Renewal pushes end_at ~a cycle out, well past the few-day window, so a
     * package cannot be renewed twice by consecutive runs.
     *
     * @return list<array<string, mixed>>
     */
    private function expiringPackages(Carbon $cutoff): array
    {
        $payload = $this->cdnfly->listUserPackages(['limit' => 0]);
        $rows = data_get($payload, 'data');

        if (! is_array($rows)) {
            return [];
        }

        $due = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if ((string) ($row['enable'] ?? '1') === '0') {
                continue;
            }

            $endAt = $this->parseDate($row['end_at'] ?? $row['end_at2'] ?? null);

            if ($endAt === null) {
                continue;
            }

            if ($endAt->lessThanOrEqualTo($cutoff)) {
                $due[] = $row;
            }
        }

        return $due;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return null;
        }

        try {
            return Carbon::parse($text);
        } catch (\Throwable) {
            return null;
        }
    }
}
