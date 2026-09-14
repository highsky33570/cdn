<?php

namespace App\Console\Commands;

use App\Services\PackageAutoRenewService;
use Illuminate\Console\Command;

class AutoRenewPackages extends Command
{
    protected $signature = 'cdnfly:auto-renew';

    protected $description = 'Renew CDNfly packages nearing expiry by charging each customer\'s balance.';

    public function handle(PackageAutoRenewService $service): int
    {
        $summary = $service->run();

        $this->components->info(sprintf(
            'Auto-renew done. considered=%d renewed=%d insufficient=%d skipped=%d failed=%d',
            $summary['considered'],
            $summary['renewed'],
            $summary['insufficient'],
            $summary['skipped'],
            $summary['failed'],
        ));

        return self::SUCCESS;
    }
}
