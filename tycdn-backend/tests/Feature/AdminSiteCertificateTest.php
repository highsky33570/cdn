<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issuing a certificate for a site.
 *
 * There is no "just enable HTTPS" flag. Setting an https listener without a
 * certificate is rejected by the master as 「https需要指定证书」, and auto_cert
 * alone does not satisfy it. The working sequence is the one the master's own
 * 申请证书 button performs (chunk-0871c1ec, applyCertRecur): create the cert,
 * then write the returned id back to https_listen.cert.
 */
class AdminSiteCertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_certificate_is_issued_then_attached_to_the_site(): void
    {
        $certPayload = null;
        $sitePayload = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('getAdminSite')
            ->once()
            ->andReturn(['data' => [
                'id' => 1,
                'domain' => 'demo.tycdn.org',
                'uid' => 7,
                'enable' => 1,
                'https_listen' => '{}',
            ]]);

        $cdnfly->shouldReceive('adminCreateCert')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$certPayload) {
                $certPayload = $payload;

                return ['data' => ['id' => 55]];
            });

        $cdnfly->shouldReceive('updateAdminSite')
            ->once()
            ->andReturnUsing(function (int $id, array $payload) use (&$sitePayload) {
                $sitePayload = $payload;

                return ['id' => $id];
            });

        $this->actingAs($this->admin())
            ->postJson('/api/admin/sites/1/certificate')
            ->assertOk()
            ->assertJsonPath('data.cert_id', 55);

        $this->assertSame('demo.tycdn.org', $certPayload['domain']);
        $this->assertSame('demo.tycdn.org免费证书', $certPayload['name']);
        $this->assertSame(7, $certPayload['uid']);
        $this->assertSame(['https_listen' => ['cert' => 55]], $sitePayload);
    }

    /** The cert is named after the first host but still covers them all. */
    public function test_a_multi_domain_site_is_named_after_its_first_host(): void
    {
        $certPayload = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('getAdminSite')->andReturn(['data' => [
            'id' => 1,
            'domain' => 'a.example.com b.example.com',
            'enable' => 1,
            'https_listen' => '{}',
        ]]);
        $cdnfly->shouldReceive('adminCreateCert')
            ->andReturnUsing(function (array $payload) use (&$certPayload) {
                $certPayload = $payload;

                return ['id' => 9];
            });
        $cdnfly->shouldReceive('updateAdminSite')->andReturn([]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/sites/1/certificate')
            ->assertOk();

        $this->assertSame('a.example.com免费证书', $certPayload['name']);
        $this->assertSame('a.example.com b.example.com', $certPayload['domain']);
    }

    /**
     * The master silently skips a disabled site, which reads as success while
     * nothing happens. Say so instead.
     */
    public function test_a_disabled_site_is_refused_with_a_reason(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('getAdminSite')->andReturn(['data' => [
            'id' => 1, 'domain' => 'demo.tycdn.org', 'enable' => 0, 'https_listen' => '{}',
        ]]);
        $cdnfly->shouldNotReceive('adminCreateCert');

        $this->actingAs($this->admin())
            ->postJson('/api/admin/sites/1/certificate')
            ->assertStatus(422)
            ->assertJsonPath('message', '网站已停用，请先启用后再申请证书');
    }

    /** An empty object is the master's way of saying "no https yet". */
    public function test_a_site_already_on_https_is_not_reissued(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('getAdminSite')->andReturn(['data' => [
            'id' => 1,
            'domain' => 'demo.tycdn.org',
            'enable' => 1,
            'https_listen' => ['port' => '443', 'cert' => 3],
        ]]);
        $cdnfly->shouldNotReceive('adminCreateCert');

        $this->actingAs($this->admin())
            ->postJson('/api/admin/sites/1/certificate')
            ->assertStatus(422)
            ->assertJsonPath('message', '该网站已开启 HTTPS，无需重复申请');
    }

    /**
     * The certificate exists in the master either way, so a retry would create
     * a duplicate. Report it rather than failing blindly.
     */
    public function test_an_unreadable_cert_id_reports_rather_than_retrying(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('getAdminSite')->andReturn(['data' => [
            'id' => 1, 'domain' => 'demo.tycdn.org', 'enable' => 1, 'https_listen' => '{}',
        ]]);
        $cdnfly->shouldReceive('adminCreateCert')->andReturn(['msg' => 'ok']);
        $cdnfly->shouldNotReceive('updateAdminSite');

        $this->actingAs($this->admin())
            ->postJson('/api/admin/sites/1/certificate')
            ->assertStatus(502)
            ->assertJsonPath('ok', false);
    }

    /**
     * The toggle sent status, which CDNfly ignores — the site stayed disabled
     * while the request reported success, and the master then skipped
     * certificate issuance without saying why.
     */
    public function test_enabling_a_site_sends_the_field_cdnfly_reads(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('updateAdminSite')
            ->once()
            ->andReturnUsing(function (int $id, array $payload) use (&$received) {
                $received = $payload;

                return [];
            });

        $this->actingAs($this->admin())
            ->putJson('/api/admin/sites/1/enable', ['enable' => true])
            ->assertOk();

        $this->assertSame(['enable' => 1], $received);
    }

    public function test_a_non_admin_cannot_issue_certificates(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->postJson('/api/admin/sites/1/certificate')
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
