<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class CdnflyRequestGuard
{
    private const PRIVILEGED_FIELDS = [
        'user_id',
        'role',
        'status',
        'type',
        'is_admin',
        'cdnfly_user_id',
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
