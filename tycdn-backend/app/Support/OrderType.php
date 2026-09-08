<?php

namespace App\Support;

/**
 * What a paid order is supposed to deliver.
 *
 * The portal sells two different things and they settle differently:
 *
 *   new / renew  -> buy or extend a CDNfly package (provisioning)
 *   recharge     -> add credit to the customer's CDNfly balance
 *
 * A recharge order carries no product and no billing cycle — just an amount —
 * so anything keyed off product_id must tolerate null for this type.
 */
class OrderType
{
    public const NEW = 'new';

    public const RENEW = 'renew';

    public const RECHARGE = 'recharge';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::NEW, self::RENEW, self::RECHARGE];
    }

    public static function isRecharge(?string $type): bool
    {
        return $type === self::RECHARGE;
    }
}
