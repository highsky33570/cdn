<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserBlockLogTest extends TestCase
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
        $this->actingAs(User::factory()->create([
            'role' => 'user', 'cdnfly_api_key' => 'customer-key', 'cdnfly_api_secret' => 'customer-secret',
        ]));
    }

    public function test_lists_and_unlocks_use_customer_credentials_and_keep_native_ownership_checks(): void
    {
        Http::fake(['*/v1/*' => Http::response(['code' => 0, 'data' => [], 'count' => 0])]);
        foreach (['blackip', 'blackip-count', 'history-blackip'] as $resource) {
            $this->getJson('/api/cdn/proxy/v1/monitor/site/'.$resource)->assertOk();
        }
        $jobs = [['type' => 'unlock_ip', 'data' => ['site_id' => 12, 'key1' => 'site_id', 'ip' => '192.0.2.1']]];
        $this->postJson('/api/cdn/proxy/v1/jobs', $jobs)->assertOk();
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->data() === $jobs);
        foreach (Http::recorded() as [$request]) {
            $this->assertTrue($request->hasHeader('api-key', 'customer-key'));
            $this->assertTrue($request->hasHeader('api-secret', 'customer-secret'));
            $this->assertFalse($request->hasHeader('api-key', 'master-key'));
        }
        $this->postJson('/api/cdn/proxy/v1/jobs', [['type' => 'unlock_ip', 'data' => ['site_id' => 12, 'uid' => 1]]])->assertUnprocessable();
        $this->postJson('/api/admin/workspace/blackip/unlock', ['items' => [['site_id' => 12]]])->assertForbidden();
        Http::assertSentCount(4);
    }

    public function test_exports_stream_filtered_text_with_customer_credentials_and_no_scope_override(): void
    {
        Http::fake(['*/v1/monitor/site/*' => fn () => Http::response("192.0.2.1\n2001:db8::1", 200, ['Content-Type' => 'text/plain'])]);
        foreach (['blackip' => 'black_ip.txt', 'history-blackip' => 'history_black_ip.txt'] as $resource => $filename) {
            $this->get('/api/cdn/block-logs/'.$resource.'/export?'.http_build_query([
                'site_id' => 12, 'ip' => '192.0.2.1', 'filter_name' => '9', 'start' => 1790000000, 'end' => 1790050000,
                'uid' => 1, 'page' => 2, 'limit' => 10, 'access_token' => 'untrusted',
            ]))->assertOk()->assertDownload($filename)->assertStreamedContent("192.0.2.1\n2001:db8::1");
        }
        foreach (Http::recorded() as [$request]) {
            $this->assertTrue($request->hasHeader('api-key', 'customer-key'));
            $this->assertTrue($request->hasHeader('api-secret', 'customer-secret'));
            $this->assertSame('export', $request['action']);
            $this->assertSame('12', $request['site_id']);
            $this->assertSame('1790000000', $request['start']);
            foreach (['uid', 'page', 'limit', 'access_token'] as $field) {
                $this->assertArrayNotHasKey($field, $request->data());
            }
        }
        Http::assertSentCount(2);
    }

    public function test_export_rejects_invalid_ranges_and_reports_upstream_failures(): void
    {
        $this->getJson('/api/cdn/block-logs/blackip/export?start=20&end=10')->assertUnprocessable();
        $this->getJson('/api/cdn/block-logs/blackip-count/export')->assertNotFound();
        Http::assertNothingSent();
        Http::fake(['*' => Http::response(['code' => 'denied', 'msg' => 'Not permitted'], 403)]);
        $this->getJson('/api/cdn/block-logs/blackip/export')->assertStatus(500);
        $this->actingAs(User::factory()->create(['role' => 'user', 'cdnfly_api_key' => null, 'cdnfly_api_secret' => null]));
        $this->getJson('/api/cdn/block-logs/blackip/export')->assertForbidden();
        Http::assertSentCount(1);
    }
}
