<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\CdnflyApiService;
use App\Support\CdnflyEncrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CdnflySecurityTest extends TestCase
{
    use RefreshDatabase;
    public function test_rejects_http_base_url(): void
    {
        config([
            'services.cdnfly.base_url' => 'http://insecure.example.com',
            'services.cdnfly.outbound_enabled' => true,
            'services.cdnfly.admin_api_key' => 'key',
            'services.cdnfly.admin_api_secret' => 'secret',
        ]);

        Http::fake();

        $service = app(CdnflyApiService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CDNfly base URL must use HTTPS');

        $service->listUsers();
    }

    public function test_accepts_https_base_url(): void
    {
        config([
            'services.cdnfly.base_url' => 'https://secure.example.com',
            'services.cdnfly.outbound_enabled' => true,
            'services.cdnfly.admin_api_key' => 'key',
            'services.cdnfly.admin_api_secret' => 'secret',
        ]);

        Http::fake([
            'https://secure.example.com/*' => Http::response([
                'code' => 0,
                'data' => [],
                'total' => 0,
            ]),
        ]);

        $service = app(CdnflyApiService::class);
        $result = $service->listUsers();

        $this->assertIsArray($result);
        Http::assertSentCount(1);
    }

    public function test_login_rejects_http_url(): void
    {
        config([
            'services.cdnfly.base_url' => 'http://insecure.example.com',
            'services.cdnfly.outbound_enabled' => true,
        ]);

        Http::fake();

        $service = app(CdnflyApiService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CDNfly base URL must use HTTPS');

        $service->login('user@test.com', 'password');
    }

    // ─── Dedicated encryption key tests ───

    public function test_cdnfly_encrypter_fallback_to_app_key(): void
    {
        // 未配置 CDNFLY_ENCRYPTION_KEY 时应回退到 APP_KEY
        config(['services.cdnfly.encryption_key' => null]);

        $encrypter = new CdnflyEncrypter;

        $this->assertFalse($encrypter->usesDedicatedKey());

        $encrypted = $encrypter->encrypt('test-secret');
        $this->assertSame('test-secret', $encrypter->decrypt($encrypted));
    }

    public function test_cdnfly_encrypter_uses_dedicated_key(): void
    {
        $dedicatedKey = 'base64:'.base64_encode(random_bytes(32));
        config(['services.cdnfly.encryption_key' => $dedicatedKey]);

        $encrypter = new CdnflyEncrypter;

        $this->assertTrue($encrypter->usesDedicatedKey());

        $encrypted = $encrypter->encrypt('my-api-key');
        $this->assertSame('my-api-key', $encrypter->decrypt($encrypted));
    }

    public function test_cdnfly_encrypter_can_fallback_decrypt_app_key_data(): void
    {
        // 模拟迁移过渡期：数据用 APP_KEY 加密，但配置了 CDNFLY_ENCRYPTION_KEY
        $plaintext = 'legacy-key-value';
        $appKeyEncrypted = \Illuminate\Support\Facades\Crypt::encrypt($plaintext);

        $dedicatedKey = 'base64:'.base64_encode(random_bytes(32));
        config(['services.cdnfly.encryption_key' => $dedicatedKey]);

        $encrypter = new CdnflyEncrypter;

        // 应能通过 fallback 解密旧数据
        $this->assertSame($plaintext, $encrypter->decrypt($appKeyEncrypted));
    }

    public function test_user_model_encrypts_cdnfly_credentials(): void
    {
        config(['services.cdnfly.encryption_key' => null]);

        $user = User::factory()->create([
            'cdnfly_api_key' => 'test-key-123',
            'cdnfly_api_secret' => 'test-secret-456',
        ]);

        // 从数据库原始值读取应是密文
        $raw = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->first();

        $this->assertNotSame('test-key-123', $raw->cdnfly_api_key);
        $this->assertNotSame('test-secret-456', $raw->cdnfly_api_secret);

        // 通过模型访问应是明文
        $user->refresh();
        $this->assertSame('test-key-123', $user->cdnfly_api_key);
        $this->assertSame('test-secret-456', $user->cdnfly_api_secret);
    }

    public function test_user_model_encrypts_with_dedicated_key(): void
    {
        $dedicatedKey = 'base64:'.base64_encode(random_bytes(32));
        config(['services.cdnfly.encryption_key' => $dedicatedKey]);

        // 需要清理 singleton 让它重新创建
        $this->app->forgetInstance(CdnflyEncrypter::class);

        $user = User::factory()->create([
            'cdnfly_api_key' => 'dedicated-key-value',
            'cdnfly_api_secret' => 'dedicated-secret-value',
        ]);

        $user->refresh();
        $this->assertSame('dedicated-key-value', $user->cdnfly_api_key);
        $this->assertSame('dedicated-secret-value', $user->cdnfly_api_secret);

        // 验证用 APP_KEY 无法直接解密
        $raw = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->first();

        $this->expectException(\Illuminate\Contracts\Encryption\DecryptException::class);
        \Illuminate\Support\Facades\Crypt::decrypt($raw->cdnfly_api_key);
    }
}
