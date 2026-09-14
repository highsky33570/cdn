<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use App\Services\PackageAutoRenewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Balance-funded auto-renewal.
 *
 * CDNfly charges the customer's balance when a package is renewed and refuses
 * with 余额不足 when it is short. This sweep renews packages near expiry as
 * their owner; the balance is the only gate.
 */
class PackageAutoRenewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cdnfly.outbound_enabled' => true,
            'services.cdnfly.auto_renew_enabled' => true,
            'services.cdnfly.auto_renew_days' => 3,
            'services.cdnfly.auto_renew_duration' => 'month',
        ]);
    }

    public function test_a_package_near_expiry_is_renewed_as_its_owner(): void
    {
        $user = $this->cdnflyUser(cdnflyId: 7);
        $renewed = [];

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('listUserPackages')->once()->andReturn([
            'data' => [
                ['id' => 1, 'uid' => 7, 'enable' => 1, 'end_at' => now()->addDay()->toDateTimeString()],
            ],
        ]);
        $cdnfly->shouldReceive('renewUserPackage')
            ->once()
            ->andReturnUsing(function (User $u, int $id, array $data) use (&$renewed) {
                $renewed = ['user' => $u->id, 'id' => $id, 'data' => $data];

                return ['code' => 0];
            });

        $summary = app(PackageAutoRenewService::class)->run();

        $this->assertSame(1, $summary['renewed']);
        $this->assertSame($user->id, $renewed['user']);
        $this->assertSame(1, $renewed['id']);
        $this->assertSame(['duration' => 'month'], $renewed['data']);
    }

    public function test_a_package_beyond_the_window_is_left_alone(): void
    {
        $this->cdnflyUser(cdnflyId: 7);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('listUserPackages')->andReturn([
            'data' => [
                ['id' => 1, 'uid' => 7, 'enable' => 1, 'end_at' => now()->addDays(30)->toDateTimeString()],
            ],
        ]);
        $cdnfly->shouldNotReceive('renewUserPackage');

        $summary = app(PackageAutoRenewService::class)->run();

        $this->assertSame(0, $summary['renewed']);
        $this->assertSame(0, $summary['considered']);
    }

    /** Insufficient balance is expected, not a failure — the customer tops up. */
    public function test_insufficient_balance_is_counted_separately(): void
    {
        $this->cdnflyUser(cdnflyId: 7);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('listUserPackages')->andReturn([
            'data' => [
                ['id' => 1, 'uid' => 7, 'enable' => 1, 'end_at' => now()->addDay()->toDateTimeString()],
            ],
        ]);
        $cdnfly->shouldReceive('renewUserPackage')
            ->andThrow(new \RuntimeException('CDNfly renew user package failed: 余额不足'));

        $summary = app(PackageAutoRenewService::class)->run();

        $this->assertSame(0, $summary['renewed']);
        $this->assertSame(1, $summary['insufficient']);
        $this->assertSame(0, $summary['failed']);
    }

    /** A genuine error is a failure, distinct from a flat balance. */
    public function test_other_errors_count_as_failed(): void
    {
        $this->cdnflyUser(cdnflyId: 7);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('listUserPackages')->andReturn([
            'data' => [
                ['id' => 1, 'uid' => 7, 'enable' => 1, 'end_at' => now()->addDay()->toDateTimeString()],
            ],
        ]);
        $cdnfly->shouldReceive('renewUserPackage')
            ->andThrow(new \RuntimeException('CDNfly renew user package failed: 系统错误'));

        $summary = app(PackageAutoRenewService::class)->run();

        $this->assertSame(1, $summary['failed']);
        $this->assertSame(0, $summary['insufficient']);
    }

    /** A disabled package is not auto-renewed. */
    public function test_disabled_packages_are_ignored(): void
    {
        $this->cdnflyUser(cdnflyId: 7);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('listUserPackages')->andReturn([
            'data' => [
                ['id' => 1, 'uid' => 7, 'enable' => 0, 'end_at' => now()->addDay()->toDateTimeString()],
            ],
        ]);
        $cdnfly->shouldNotReceive('renewUserPackage');

        $summary = app(PackageAutoRenewService::class)->run();

        $this->assertSame(0, $summary['considered']);
    }

    /** A package whose owner has no portal credentials is skipped, not failed. */
    public function test_a_package_with_no_resolvable_owner_is_skipped(): void
    {
        // No portal user for uid 999.
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('listUserPackages')->andReturn([
            'data' => [
                ['id' => 1, 'uid' => 999, 'enable' => 1, 'end_at' => now()->addDay()->toDateTimeString()],
            ],
        ]);
        $cdnfly->shouldNotReceive('renewUserPackage');

        $summary = app(PackageAutoRenewService::class)->run();

        $this->assertSame(1, $summary['considered']);
        $this->assertSame(1, $summary['skipped']);
    }

    /** The feature can be switched off without touching the schedule. */
    public function test_nothing_runs_when_disabled(): void
    {
        config(['services.cdnfly.auto_renew_enabled' => false]);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldNotReceive('listUserPackages');

        $summary = app(PackageAutoRenewService::class)->run();

        $this->assertSame(0, $summary['considered']);
    }

    private function cdnflyUser(int $cdnflyId): User
    {
        return User::factory()->create([
            'cdnfly_user_id' => $cdnflyId,
            'cdnfly_api_key' => 'key-'.$cdnflyId,
            'cdnfly_api_secret' => 'secret-'.$cdnflyId,
        ]);
    }
}
