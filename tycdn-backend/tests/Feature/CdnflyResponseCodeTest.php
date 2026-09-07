<?php

namespace Tests\Feature;

use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CdnflyResponseCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cdnfly.base_url' => 'https://panel.example.test',
            'services.cdnfly.admin_api_key' => 'k',
            'services.cdnfly.admin_api_secret' => 's',
            'services.cdnfly.outbound_enabled' => true,
        ]);
    }

    /**
     * CDNfly v6 returns HTTP 200 with a *string* error code, e.g.
     * {"data":"","msg":"管理员之间不能切换","code":"admin_user-34"}.
     *
     * `(int) "admin_user-34"` is 0 in PHP, so the old check read that as success,
     * discarded the message, and let the caller fail later with something
     * unrelated ("SSO token response is incomplete").
     */
    public function test_string_error_code_is_treated_as_a_failure(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => '',
                'msg' => '管理员之间不能切换',
                'code' => 'admin_user-34',
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/管理员之间不能切换/');

        app(CdnflyApiService::class)->getSsoToken(1);
    }

    public function test_numeric_zero_code_is_still_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [['id' => 1, 'name' => '默认']],
                'count' => 1,
                'code' => 0,
            ], 200),
        ]);

        $this->assertSame(1, app(CdnflyApiService::class)->listRegions()['count']);
    }

    public function test_string_zero_code_is_success(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [], 'code' => '0'], 200),
        ]);

        $this->assertSame([], app(CdnflyApiService::class)->listRegions()['data']);
    }

    public function test_numeric_non_zero_code_is_a_failure(): void
    {
        Http::fake([
            '*' => Http::response(['data' => '', 'msg' => 'quota exceeded', 'code' => 500], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/quota exceeded/');

        app(CdnflyApiService::class)->listRegions();
    }

    /**
     * A successful SSO response must still parse — this is the shape a normal
     * (non-admin) CDNfly user returns.
     */
    public function test_successful_sso_response_returns_the_access_token(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'access_token' => 'tok-abc',
                    'username' => 'jason',
                    'uid' => 2,
                    'type' => 2,
                ],
                'msg' => '登录成功!',
                'code' => 0,
            ], 200),
        ]);

        $this->assertSame('tok-abc', app(CdnflyApiService::class)->getSsoToken(2));
    }
}
