<?php

namespace App\Console\Commands;

use App\Services\PaymentReconciliationService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Reconcile pending payments and retry paid order provisioning.';

    public function handle(PaymentReconciliationService $reconciliation): int
    {
        $summary = $reconciliation->reconcile();

        $this->components->info(sprintf(
            'Payment reconciliation completed. expired=%d epusdt_paid=%d provisioning_attempts=%d',
            $summary['expired_orders'],
            $summary['epusdt_orders_paid'],
            $summary['provisioning_attempts'],
        ));

        return self::SUCCESS;
    }
}
