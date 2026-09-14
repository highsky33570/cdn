<?php

namespace Tests\Feature;

use App\Services\EpusdtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * GMPay signing, pinned to the vendor's own published test vector.
 *
 * Epusdt v2 hard-switched from MD5-with-appended-token to HMAC-SHA256 keyed by
 * the merchant secret, and refuses the old scheme outright — 「不再接受旧版
 * MD5 签名，也不提供算法协商或回退」. Signing the old way produced a perfectly
 * well-formed request that the gateway rejected as unauthenticated, which looks
 * like a credentials problem rather than an algorithm one.
 *
 * The vector below is copied verbatim from wiki/API.md so a future change to
 * the canonical string — ordering, separator, which fields are skipped — fails
 * here rather than in production against real money.
 */
class EpusdtSignatureTest extends TestCase
{
    use RefreshDatabase;

    private const VENDOR_SECRET = 'epusdt_secret_key';

    private const VENDOR_PARAMS = [
        'pid' => '1000',
        'order_id' => 'ORD202605230001',
        'currency' => 'cny',
        'token' => 'usdt',
        'network' => 'tron',
        'amount' => 100,
        'notify_url' => 'https://merchant.example/notify',
        'redirect_url' => 'https://merchant.example/return',
        'name' => 'VIP',
    ];

    private const VENDOR_SIGNATURE =
        '6f874b1919d95081835e2809b620e354a5866f5a6dbb2e432d1627f1eb10059d';

    public function test_signing_matches_the_vendor_test_vector(): void
    {
        $this->configure(apiToken: self::VENDOR_SECRET);

        $this->assertSame(
            self::VENDOR_SIGNATURE,
            app(EpusdtService::class)->sign(self::VENDOR_PARAMS),
        );
    }

    /** A 64-char lowercase hex digest, not a 32-char MD5. */
    public function test_the_signature_is_sha256_shaped(): void
    {
        $this->configure();

        $signature = app(EpusdtService::class)->sign(['order_id' => 'A', 'amount' => 1]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $signature);
    }

    /** Empty and null values are excluded from the canonical string. */
    public function test_blank_values_do_not_participate(): void
    {
        $this->configure();

        $service = app(EpusdtService::class);

        $this->assertSame(
            $service->sign(['order_id' => 'A', 'amount' => 1]),
            $service->sign(['order_id' => 'A', 'amount' => 1, 'name' => '', 'redirect_url' => null]),
        );
    }

    public function test_a_callback_signed_with_our_secret_verifies(): void
    {
        $this->configure(pid: '1000');

        $service = app(EpusdtService::class);

        $payload = [
            'pid' => '1000',
            'trade_id' => '20260523171652123456001',
            'order_id' => 'ORD202605230001',
            'amount' => 100,
            'actual_amount' => 14.29,
            'receive_address' => 'TTestTronAddress001',
            'token' => 'USDT',
            'block_transaction_id' => '0xabc123',
            'status' => 2,
        ];
        $payload['signature'] = $service->sign($payload);

        $this->assertTrue($service->verifySignature($payload));
    }

    /**
     * A callback minted for another merchant on the same gateway must not be
     * replayable at us, even though its signature is internally valid.
     */
    public function test_a_callback_for_another_merchant_is_rejected(): void
    {
        $this->configure(pid: '1000');

        $service = app(EpusdtService::class);

        $payload = ['pid' => '2000', 'order_id' => 'ORD1', 'status' => 2];
        $payload['signature'] = $service->sign($payload);

        $this->assertFalse($service->verifySignature($payload));
    }

    /**
     * token and network select a chain together. Exactly one is a parameter
     * error at the gateway, so it is caught here where the message can say why.
     */
    public function test_token_without_network_is_refused_before_the_request(): void
    {
        $this->configure(token: 'usdt', network: '');

        Http::fake();

        $this->expectExceptionMessageMatches('/token and network must be set together/');

        app(EpusdtService::class)->createTransaction(['order_id' => 'A', 'amount' => 5]);
    }

    /** Both blank is legitimate: it creates a placeholder order. */
    public function test_both_blank_creates_a_placeholder_order(): void
    {
        $this->configure(token: '', network: '');

        $sent = null;

        Http::fake(['*' => function ($request) use (&$sent) {
            $sent = $request->data();

            return Http::response(['status_code' => 200, 'data' => ['trade_id' => 'T1']]);
        }]);

        app(EpusdtService::class)->createTransaction(['order_id' => 'A', 'amount' => 5]);

        $this->assertArrayNotHasKey('token', $sent);
        $this->assertArrayNotHasKey('network', $sent);
        $this->assertSame('1000', $sent['pid']);
    }

    private function configure(
        string $apiToken = 'test-secret',
        string $pid = '1000',
        string $token = 'usdt',
        string $network = 'tron',
    ): void {
        config([
            'services.epusdt.base_url' => 'https://pay.example.test',
            'services.epusdt.create_order_path' => '/payments/gmpay/v1/order/create-transaction',
            'services.epusdt.api_token' => $apiToken,
            'services.epusdt.pid' => $pid,
            'services.epusdt.notify_url' => 'https://console.example.test/api/payments/epusdt/notify',
            'services.epusdt.redirect_url' => '',
            'services.epusdt.default_currency' => 'usd',
            'services.epusdt.default_token' => $token,
            'services.epusdt.default_network' => $network,
        ]);
    }
}
