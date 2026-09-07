<?php

namespace App\Support;

class OrderStatus
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const PROVISIONING = 'provisioning';

    public const ACTIVE = 'active';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    /**
     * Statuses where the customer's money has NOT been settled yet, so an inbound
     * paid callback must still be honoured.
     *
     * expired/cancelled are included deliberately: an on-chain transfer that
     * confirms after the order window is a REAL payment. Treating it as a replay
     * means keeping the customer's funds and never delivering the service.
     */
    public const SETTLEABLE = [
        self::PENDING,
        self::EXPIRED,
        self::CANCELLED,
    ];

    public static function isSettleable(?string $status): bool
    {
        return in_array((string) $status, self::SETTLEABLE, true);
    }

    public static function all(): array
    {
        return [
            self::PENDING,
            self::PAID,
            self::PROVISIONING,
            self::ACTIVE,
            self::FAILED,
            self::EXPIRED,
            self::CANCELLED,
        ];
    }
}
