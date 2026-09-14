<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EpusdtService
{
    /**
     * GMPay request/callback signature.
     *
     * Non-empty params sorted by name (ASCII), joined as `k=v` with `&`,
     * then HMAC-SHA256 keyed by the merchant secret, lowercase hex.
     *
     * Epusdt v2 hard-switched from the old MD5-with-appended-token scheme and
     * explicitly refuses it — 「已硬切至 HMAC-SHA256，不再接受旧版 MD5 签名，
     * 也不提供算法协商或回退」 (wiki/API.md). Signing the old way produced a
     * well-formed request the gateway rejected as unauthenticated.
     *
     * Verified against the vendor's published test vector; see
     * EpusdtSignatureTest.
     */
    public function sign(array $params): string
    {
        unset($params['signature']);

        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_scalar($value)) {
                $value = (string) $value;
            } else {
                continue;
            }

            $filtered[$key] = $value;
        }

        ksort($filtered, SORT_STRING);

        $canonical = implode('&', Arr::map(
            $filtered,
            fn (string $value, string $key): string => $key.'='.$value,
        ));

        return hash_hmac('sha256', $canonical, $this->apiToken());
    }

    public function createTransaction(array $params): array
    {
        // Required by the gateway: pid, order_id, currency, amount,
        // notify_url (wiki/API.md 请求参数).
        $payload = [
            'pid' => $this->pid(),
            'order_id' => (string) $params['order_id'],
            'amount' => round((float) $params['amount'], 2),
            'currency' => strtolower((string) ($params['currency'] ?? config('services.epusdt.default_currency', 'usd'))),
            'notify_url' => (string) ($params['notify_url'] ?? config('services.epusdt.notify_url')),
        ];

        // token and network are "conditionally required": both together select
        // a chain, both absent creates a status-4 placeholder the checkout page
        // resolves later. Sending exactly one is a parameter error, so the pair
        // is kept together rather than defaulted independently.
        $token = strtolower(trim((string) ($params['token'] ?? config('services.epusdt.default_token', ''))));
        $network = strtolower(trim((string) ($params['network'] ?? config('services.epusdt.default_network', ''))));

        if (($token === '') !== ($network === '')) {
            throw new \RuntimeException(
                'EPUSDT token and network must be set together or left blank together; '
                ."got token='{$token}', network='{$network}'."
            );
        }

        if ($token !== '') {
            $payload['token'] = $token;
            $payload['network'] = $network;
        }

        $redirectUrl = $params['redirect_url'] ?? config('services.epusdt.redirect_url');
        if ($redirectUrl !== null && $redirectUrl !== '') {
            $payload['redirect_url'] = (string) $redirectUrl;
        }

        $payload['signature'] = $this->sign($payload);

        $response = Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->post($this->createOrderUrl(), $payload);

        return $this->parseCreateTransactionResponse($response);
    }

    public function queryTransaction(string $orderNo): ?array
    {
        $path = trim((string) config('services.epusdt.query_order_path', ''));
        if ($path === '') {
            return null;
        }

        $payload = [
            'pid' => $this->pid(),
            'order_id' => $orderNo,
        ];
        $payload['signature'] = $this->sign($payload);

        $response = Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->post($this->endpointUrl($path), $payload);

        return $this->parseCreateTransactionResponse($response);
    }

    /**
     * Authenticate a callback.
     *
     * The signature is what proves the caller holds our secret. The pid is
     * checked too so a callback minted for a different merchant on the same
     * gateway cannot be replayed at us.
     */
    public function verifySignature(array $params): bool
    {
        $signature = $params['signature'] ?? '';
        $pid = $params['pid'] ?? '';

        if (! is_string($signature) || $signature === '' || ! is_scalar($pid)) {
            return false;
        }

        if (! hash_equals($this->pid(), trim((string) $pid))) {
            return false;
        }

        return hash_equals(strtolower($signature), $this->sign($params));
    }

    public function createOrderPath(): string
    {
        return (string) config('services.epusdt.create_order_path', '/payments/gmpay/v1/order/create-transaction');
    }

    /**
     * The merchant id the gateway uses to look up our secret key.
     *
     * Mandatory on every GMPay request and present on every callback, so a
     * blank value is a misconfiguration rather than a supported mode.
     */
    private function pid(): string
    {
        $pid = trim((string) config('services.epusdt.pid'));

        if ($pid === '') {
            throw new \RuntimeException('EPUSDT_PID is not configured.');
        }

        return $pid;
    }

    private function apiToken(): string
    {
        $token = (string) config('services.epusdt.api_token');

        if ($token === '') {
            throw new \RuntimeException('EPUSDT_API_TOKEN is not configured.');
        }

        return $token;
    }

    private function createOrderUrl(): string
    {
        return $this->endpointUrl($this->createOrderPath());
    }

    private function endpointUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('services.epusdt.base_url'), '/');
        $path = '/'.ltrim($path, '/');

        if ($baseUrl === '') {
            throw new \RuntimeException('EPUSDT_BASE_URL is not configured.');
        }

        return $baseUrl.$path;
    }

    private function parseCreateTransactionResponse(Response $response): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            $this->failGateway('Epusdt response is not valid JSON.', $response);
        }

        if (! $response->successful()) {
            $this->failGateway('Epusdt HTTP request failed.', $response, $json);
        }

        if (($json['status_code'] ?? null) !== 200) {
            $this->failGateway('Epusdt rejected the transaction request.', $response, $json);
        }

        if (! isset($json['data']) || ! is_array($json['data'])) {
            $this->failGateway('Epusdt response is missing the data field.', $response, $json);
        }

        return $json;
    }

    /**
     * The raw gateway body can carry addresses and merchant detail, and exception
     * messages end up in API responses and log sinks. Record only the status
     * fields needed to diagnose, and throw a message safe to propagate.
     *
     * @param  array<mixed>|null  $json
     */
    private function failGateway(string $message, Response $response, ?array $json = null): never
    {
        Log::error('epusdt gateway error', [
            'message' => $message,
            'http_status' => $response->status(),
            'gateway_status_code' => $json['status_code'] ?? null,
        ]);

        throw new \RuntimeException($message);
    }
}
