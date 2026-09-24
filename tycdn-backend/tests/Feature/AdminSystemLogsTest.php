<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminSystemLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cdnfly.base_url' => 'https://master.example.test', 'services.cdnfly.admin_api_key' => 'key', 'services.cdnfly.admin_api_secret' => 'secret', 'services.cdnfly.outbound_enabled' => true]);
        Http::preventStrayRequests();
    }

    public function test_backup_logs_are_always_filtered_to_backup_jobs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::response(['code' => 0, 'count' => 241, 'data' => [['id' => 1, 'state' => 'done', 'end_at' => '2026-09-24 12:00:00']]])]);
        $this->getJson('/api/admin/logs/backup?page=2&limit=10&type=clean_url')->assertOk()->assertJsonPath('data.count', 241)->assertJsonPath('data.data.0.state', 'done');
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_starts_with($r->url(), 'https://master.example.test/v1/jobs?') && $r['type'] === 'backup' && $r['page'] === '2');
    }

    public function test_send_log_filters_and_audit_dates_are_forwarded_without_mutations(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::response(['code' => 0, 'count' => 0, 'data' => []])]);
        $this->getJson('/api/admin/logs/msg-send?uid=7&msg_id=19&msg_type=notice&media=email&state=failed&page=1&limit=10&ignored=1')->assertOk();
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_starts_with($r->url(), 'https://master.example.test/v1/log/msg-send?') && $r['uid'] === '7' && $r['msg_id'] === '19' && $r['msg_type'] === 'notice' && $r['media'] === 'email' && $r['state'] === 'failed' && ! isset($r['ignored']));
        $this->getJson('/api/admin/logs/login?uid=7&success=0&ip=203.0.113&start=2026-09-24&end=2026-09-25')->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/log/login?') && $r['success'] === '0' && $r['end'] === '2026-09-25');
        $this->getJson('/api/admin/logs/op?action=更新&type=config&content=global&diff=tcp')->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/log/op?') && $r['type'] === 'config' && $r['diff'] === 'tcp');
    }

    public function test_new_logs_require_admin_and_are_read_only(): void
    {
        $this->getJson('/api/admin/logs/backup')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/logs/backup')->assertForbidden();
        $this->getJson('/api/admin/logs/msg-send')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/logs/backup?limit=0')->assertUnprocessable();
        $this->getJson('/api/admin/logs/msg-send?uid=abc')->assertUnprocessable();
        $this->postJson('/api/admin/logs/msg-send')->assertMethodNotAllowed();
        $this->deleteJson('/api/admin/logs/backup')->assertMethodNotAllowed();
        Http::assertNothingSent();
    }
}
