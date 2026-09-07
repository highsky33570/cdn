<?php

namespace Tests\Feature;

use App\Support\CdnflyRequestGuard;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The guard exists to stop a caller injecting *whose* data is touched. It must
 * not block ordinary resource attributes, which is what happened when `type` and
 * `status` were on the list: the analytics, top, logs, cache, messages, streams,
 * site-config and billing pages all send one or the other as a filter, so they
 * failed with "CDNfly 请求包含不允许透传的用户、状态或权限字段".
 */
class CdnflyRequestGuardTest extends TestCase
{
    /**
     * @return list<array{0: array<string, mixed>, 1: string}>
     */
    public static function legitimateFilters(): array
    {
        return [
            [['type' => 'req', 'start' => '2026-09-01', 'end' => '2026-09-02'], 'realtime metric selector'],
            [['type' => 'ip'], 'top-list tab'],
            [['status' => '404'], 'access-log HTTP status filter'],
            [['status' => 'paid'], 'order state filter'],
            [['type' => 'A', 'domain' => 'example.com'], 'dns record type'],
            [['type' => 'dir', 'page' => 1, 'limit' => 20], 'cache purge type'],
        ];
    }

    #[DataProvider('legitimateFilters')]
    public function test_ordinary_filters_pass_through(array $payload, string $why): void
    {
        $this->assertSame(
            $payload,
            CdnflyRequestGuard::rejectPrivilegedFields($payload),
            "should be allowed: {$why}"
        );
    }

    /**
     * @return list<array{0: array<string, mixed>}>
     */
    public static function ownershipFields(): array
    {
        return [
            [['user_id' => 123]],
            [['uid' => 123]],
            [['owner_id' => 123]],
            [['cdnfly_user_id' => 123]],
            [['is_admin' => true]],
            [['role' => 'admin']],
            // separators and casing must not slip past the normaliser
            [['User-Id' => 123]],
            [['USERID' => 123]],
            // recursively, not just at the top level
            [['name' => 'x', 'meta' => ['nested' => ['uid' => 9]]]],
        ];
    }

    #[DataProvider('ownershipFields')]
    public function test_ownership_and_privilege_fields_are_rejected(array $payload): void
    {
        $this->expectException(ValidationException::class);

        CdnflyRequestGuard::rejectPrivilegedFields($payload);
    }
}
