<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminMaintenanceLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.cdnfly.base_url' => 'https://cdnfly.example.test',
            'services.cdnfly.admin_api_key' => 'master-key',
            'services.cdnfly.admin_api_secret' => 'master-secret',
            'services.cdnfly.outbound_enabled' => true,
        ]);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_missing_transfer_log_has_an_explicit_unavailable_state(): void
    {
        Http::fake(['*/v1/master/transfer-log' => Http::response([
            'code' => 'system-30', 'data' => '',
            'msg' => 'system处理失败: open /tmp/master_transfer.log: no such file or directory',
        ])]);

        $this->getJson('/api/admin/workspace/transfer-log')->assertOk()
            ->assertJsonPath('ok', true)->assertJsonPath('data.data.available', false)
            ->assertJsonPath('data.data.reason', 'not_found');
        Http::assertSentCount(1);
    }

    public function test_upgrade_requires_explicit_start_and_version_and_strips_other_fields(): void
    {
        Http::fake(['*' => Http::response(['code' => 0, 'data' => null])]);
        $this->postJson('/api/admin/workspace/master-upgrades', [])->assertUnprocessable();
        $this->postJson('/api/admin/workspace/master-upgrades', ['action' => 'stop', 'version_num' => 60001])->assertUnprocessable();
        $this->postJson('/api/admin/workspace/master-upgrades/1', ['action' => 'start', 'version_num' => 60001])->assertMethodNotAllowed();
        Http::assertNothingSent();
        $this->postJson('/api/admin/workspace/master-upgrades', ['action' => 'start', 'version_num' => 60001, 'command' => 'ignored'])->assertOk();
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === 'https://cdnfly.example.test/v1/master/upgrades?action=start&version_num=60001' && $request->data() === []);
    }

    public function test_upgrade_rejects_non_admin_and_reports_master_rejection(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->postJson('/api/admin/workspace/master-upgrades', ['action' => 'start', 'version_num' => 60001])->assertForbidden();
        Http::assertNothingSent();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::response(['code' => 'upgrade-1', 'msg' => '目标版本不可用', 'data' => null])]);
        $this->postJson('/api/admin/workspace/master-upgrades', ['action' => 'start', 'version_num' => 60001])->assertStatus(500)->assertJsonPath('ok', false);
    }

    public function test_upgrade_log_read_failure_is_unavailable_only_when_master_is_idle(): void
    {
        $states = [false, 0, '0'];
        $statuses = Http::sequence();
        foreach ($states as $running) {
            $statuses->push(['code' => 0, 'data' => ['upgrade_run' => $running]]);
        }
        Http::fake([
            '*/v1/master/upgrades/log' => Http::response($this->upgradeReadFailure()),
            '*/v1/master/upgrades' => $statuses,
        ]);
        foreach ($states as $running) {
            $this->getJson('/api/admin/workspace/master-upgrade-log')->assertOk()
                ->assertJsonPath('data.data.available', false)
                ->assertJsonPath('data.data.reason', 'unreadable_while_idle');
        }
        Http::assertSent(fn (Request $request) => $request->url() === 'https://cdnfly.example.test/v1/master/upgrades'
            && $request->method() === 'GET' && $request->hasHeader('api-key', 'master-key'));
    }

    public function test_running_and_unknown_upgrade_states_preserve_the_read_error(): void
    {
        $states = [true, 1, '1', null, '', 'false'];
        $statuses = Http::sequence();
        foreach ($states as $running) {
            $statuses->push(['code' => 0, 'data' => ['upgrade_run' => $running]]);
        }
        Http::fake([
            '*/v1/master/upgrades/log' => Http::response($this->upgradeReadFailure()),
            '*/v1/master/upgrades' => $statuses,
        ]);
        foreach ($states as $running) {
            $this->getJson('/api/admin/workspace/master-upgrade-log')->assertStatus(500)
                ->assertJsonPath('ok', false)
                ->assertJsonPath('upstream_error', 'CDNfly admin GET /v1/master/upgrades/log failed: upgrade处理失败: exit status 1');
        }
    }

    public function test_status_lookup_failure_does_not_hide_the_original_log_error(): void
    {
        Http::fake([
            '*/v1/master/upgrades/log' => Http::response($this->upgradeReadFailure()),
            '*/v1/master/upgrades' => Http::sequence()
                ->push(['code' => 'auth-1', 'msg' => '认证失败'])
                ->push('Unavailable', 503),
        ]);
        for ($i = 0; $i < 2; $i++) {
            $this->getJson('/api/admin/workspace/master-upgrade-log')->assertStatus(500)
                ->assertJsonPath('upstream_error', 'CDNfly admin GET /v1/master/upgrades/log failed: upgrade处理失败: exit status 1');
        }
    }

    public function test_status_connection_failure_preserves_the_original_log_error(): void
    {
        Http::fake(['*/v1/master/upgrades/log' => Http::response($this->upgradeReadFailure())]);
        $this->partialMock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')
            ->once()->with('GET', '/v1/master/upgrades')
            ->andThrow(new ConnectionException('Connection timed out'));
        $this->getJson('/api/admin/workspace/master-upgrade-log')->assertStatus(500)
            ->assertJsonPath('upstream_error', 'CDNfly admin GET /v1/master/upgrades/log failed: upgrade处理失败: exit status 1');
    }

    public function test_other_log_failures_and_http_errors_are_not_converted_to_empty_logs(): void
    {
        $cases = [
            ['transfer-log', '/v1/master/transfer-log', 'system-30', 'system处理失败: open /tmp/master_transfer.log: permission denied', 200],
            ['transfer-log', '/v1/master/transfer-log', 'auth-1', '认证失败', 200],
            ['master-upgrade-log', '/v1/master/upgrades/log', 'upgrade-12', 'upgrade处理失败: exit status 2', 200],
            ['master-upgrade-log', '/v1/master/upgrades/log', 'auth-1', 'upgrade处理失败: exit status 1', 200],
            ['master-upgrade-log', '/v1/master/upgrades/log', 'upgrade-12', 'upgrade处理失败: exit status 1', 500],
            ['overview', '/v1/admin/overview', 'upgrade-12', 'upgrade处理失败: exit status 1', 200],
        ];
        $responses = Http::sequence();
        foreach ($cases as [$resource, $path, $code, $message, $status]) {
            $responses->push(['code' => $code, 'msg' => $message, 'data' => ''], $status);
        }
        Http::fake(['*' => $responses]);
        foreach ($cases as [$resource]) {
            $this->getJson('/api/admin/workspace/'.$resource)->assertStatus(500)->assertJsonPath('ok', false);
        }
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/v1/master/upgrades'));
    }

    public function test_available_logs_and_successful_empty_logs_pass_through_unchanged(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['code' => 0, 'data' => "开始\n完成\n"])->push(['code' => 0, 'data' => ''])
            ->push(['code' => 0, 'data' => "开始\n完成\n"])->push(['code' => 0, 'data' => '']),
        ]);
        foreach (['master-upgrade-log' => '/v1/master/upgrades/log', 'transfer-log' => '/v1/master/transfer-log'] as $resource => $path) {
            foreach (["开始\n完成\n", ''] as $log) {
                $this->getJson('/api/admin/workspace/'.$resource)->assertOk()->assertJsonPath('data.data', $log);
            }
        }
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/v1/master/upgrades'));
    }

    public function test_non_get_requests_keep_the_original_failure(): void
    {
        Http::fake(['*/v1/master/upgrades/log' => Http::response($this->upgradeReadFailure())]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('upgrade处理失败: exit status 1');
        app(CdnflyApiService::class)->proxyAdminRequest('POST', '/v1/master/upgrades/log');
    }

    public function test_logs_still_require_an_administrator(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/workspace/master-upgrade-log')->assertForbidden();
        $this->getJson('/api/admin/workspace/transfer-log')->assertForbidden();
        Http::assertNothingSent();
    }

    private function upgradeReadFailure(): array
    {
        return ['code' => 'upgrade-12', 'msg' => 'upgrade处理失败: exit status 1', 'data' => ''];
    }
}
