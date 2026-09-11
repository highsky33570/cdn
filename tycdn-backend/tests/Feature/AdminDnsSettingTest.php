<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The master's global DNS resolution settings.
 *
 * This is what CDNfly means by 请先设置DNS, and it is NOT /v1/dnsapis — that one
 * is a per-user ACME credential that lives on the certificate page. Both take
 * credentials for the same providers, which is precisely why they get confused:
 * filling in the certificate one leaves the master with no way to write
 * resolution records, so it refuses CNAME domains and generates no DNS lines.
 *
 * Payload shape verified against the master panel (chunk-45f4d7f2, component
 * "dns-setting"): {id, weight_on, token, dns, ttl, rnd, gateway}.
 */
class AdminDnsSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_setting_is_written_into_the_global_dns_config_row(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('saveDnsSetting')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$received) {
                $received = $payload;

                return ['code' => 0];
            });

        $this->actingAs($this->admin())
            ->putJson('/api/admin/dns-setting', [
                'dns' => 'cloudflare',
                'id' => 'me@example.com',
                'token' => 'secret-key',
                'ttl' => 600,
                'weight_on' => 1,
            ])
            ->assertOk();

        $this->assertSame('cloudflare', $received['dns']);
        $this->assertSame('me@example.com', $received['id']);
    }

    public function test_an_unknown_provider_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/admin/dns-setting', [
                'dns' => 'route53',
                'id' => 'x',
                'token' => 'y',
                'ttl' => 600,
                'weight_on' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dns');
    }

    /**
     * Cloudflare sends this field verbatim as X-Auth-Email. Pasting a
     * credential *name* there (easy to do — the certificate DNS API tab names
     * its entries) gets rejected by Cloudflare with 「Invalid format for
     * X-Auth-Email header」, which never says which field is at fault.
     */
    public function test_cloudflare_requires_an_email_rather_than_a_credential_name(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/admin/dns-setting', [
                'dns' => 'cloudflare',
                'id' => 'ty-cloudflare',
                'token' => 'y',
                'ttl' => 600,
                'weight_on' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id');
    }

    /** Every other provider uses a key id there, so no email rule applies. */
    public function test_other_providers_accept_a_non_email_id(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('saveDnsSetting')->once()->andReturn(['code' => 0]);

        $this->actingAs($this->admin())
            ->putJson('/api/admin/dns-setting', [
                'dns' => 'aliyun',
                'id' => 'LTAI5tSomeAccessKeyId',
                'token' => 'y',
                'ttl' => 600,
                'weight_on' => 1,
            ])
            ->assertOk();
    }

    public function test_a_ttl_below_the_provider_minimum_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/admin/dns-setting', [
                'dns' => 'aliyun',
                'id' => 'x',
                'token' => 'y',
                'ttl' => 1,
                'weight_on' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ttl');
    }

    /**
     * The panel sends a fresh random `rnd` on every save so the stored value
     * always differs; without it a re-save with identical credentials is a
     * no-op and the master never regenerates the line list.
     */
    public function test_each_save_carries_a_fresh_rnd_so_the_master_revalidates(): void
    {
        $this->fakeCdnfly();

        $values = [];

        Http::fake([
            'https://panel.example.test/v1/configs' => function ($request) use (&$values) {
                $values[] = json_decode($request->data()['value'], true);

                return Http::response(['code' => 0, 'data' => []]);
            },
        ]);

        $service = app(CdnflyApiService::class);

        $service->saveDnsSetting([
            'dns' => 'aliyun', 'id' => 'a', 'token' => 'b', 'ttl' => 600, 'weight_on' => 1,
        ]);
        $service->saveDnsSetting([
            'dns' => 'aliyun', 'id' => 'a', 'token' => 'b', 'ttl' => 600, 'weight_on' => 1,
        ]);

        $this->assertCount(2, $values);
        $this->assertArrayHasKey('rnd', $values[0]);
        $this->assertSame('', $values[0]['gateway']);
        $this->assertSame(600, $values[0]['ttl']);
    }

    /** An unconfigured master must report that plainly, not fail. */
    public function test_an_unset_dns_config_reports_as_unconfigured(): void
    {
        $this->fakeCdnfly();

        Http::fake([
            'https://panel.example.test/v1/configs/*' => Http::response([
                'code' => 0,
                'data' => ['value' => '{}'],
            ]),
        ]);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/dns-setting')
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.lines_configured', false);
    }

    public function test_a_configured_master_reports_its_provider_and_line_state(): void
    {
        $this->fakeCdnfly();

        Http::fake([
            'https://panel.example.test/v1/configs/*' => Http::response([
                'code' => 0,
                'data' => ['value' => json_encode([
                    'dns' => 'cloudflare',
                    'id' => 'me@example.com',
                    'token' => 'secret',
                    'ttl' => 300,
                    'weight_on' => 1,
                    'lines' => json_encode([['id' => 0, 'name' => '默认']]),
                ])],
            ]),
        ]);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/dns-setting')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.lines_configured', true)
            ->assertJsonPath('data.dns', 'cloudflare')
            ->assertJsonPath('data.ttl', 300);
    }

    public function test_a_non_admin_cannot_change_dns_settings(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->putJson('/api/admin/dns-setting', [
                'dns' => 'aliyun',
                'id' => 'x',
                'token' => 'y',
                'ttl' => 600,
                'weight_on' => 1,
            ])
            ->assertForbidden();
    }

    private function fakeCdnfly(): void
    {
        config([
            'services.cdnfly.base_url' => 'https://panel.example.test',
            'services.cdnfly.admin_api_key' => 'k',
            'services.cdnfly.admin_api_secret' => 's',
            'services.cdnfly.outbound_enabled' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
