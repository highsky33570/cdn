<?php

namespace App\Support;

final class EmailVerificationSignature
{
    public static function make(int|string $userId, string $emailHash, int $expires): string
    {
        return hash_hmac(
            'sha256',
            self::payload($userId, $emailHash, $expires),
            (string) config('app.key'),
        );
    }

    public static function isValid(
        int|string $userId,
        string $emailHash,
        int $expires,
        string $token,
    ): bool {
        if ($expires < now()->getTimestamp() || $token === '') {
            return false;
        }

        return hash_equals(self::make($userId, $emailHash, $expires), $token);
    }

    private static function payload(int|string $userId, string $emailHash, int $expires): string
    {
        return implode('|', [(string) $userId, $emailHash, (string) $expires]);
    }
}
