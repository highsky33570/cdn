<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class CdnflyRequestGuard
{
    /**
     * Fields a caller must never be able to inject, because they decide *whose*
     * data is touched or *what powers* an account has.
     *
     * `type` and `status` deliberately are NOT here. They read like privileged
     * flags, but on every path the proxy allows they are ordinary resource
     * attributes and filters — which metric to chart, an HTTP status code to
     * filter access logs by, a DNS provider type, an order state. Blocking them
     * broke roughly nine console pages while protecting nothing: the account
     * record where `type` really does mean admin-vs-user lives under /v1/users,
     * and the path allowlist already refuses that outright.
     *
     * `uid` and `owner_id` are here because CDNfly's own payloads use `uid` for
     * ownership, so omitting it left the actual hole this guard exists to close.
     */
    private const PRIVILEGED_FIELDS = [
        'user_id',
        'uid',
        'owner_id',
        'cdnfly_user_id',
        'is_admin',
        'role',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function rejectPrivilegedFields(array $data): array
    {
        self::walkPrivilegedFields($data);

        return $data;
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function walkPrivilegedFields(array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isPrivilegedField($key)) {
                throw ValidationException::withMessages([
                    'cdnfly' => 'CDNfly 请求包含不允许透传的用户、状态或权限字段',
                ]);
            }

            if (is_array($value)) {
                self::walkPrivilegedFields($value);
            }
        }
    }

    private static function isPrivilegedField(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        foreach (self::PRIVILEGED_FIELDS as $field) {
            if ($normalized === strtolower(str_replace(['-', '_'], '', $field))) {
                return true;
            }
        }

        return false;
    }
}
