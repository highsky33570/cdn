<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EpusdtService
{
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

        return strtolower(md5(implode('&', Arr::map($filtered, fn (string $value, string $key) => $key.'='.$value)).$this->apiToken()));
    }

    public function createTransaction(array $params): array
    {
        $payload = [
            'pid' => $this->pid(),
            'order_id' => (string) $params['order_id'],
            'amount' => round((float) $params['amount'], 2),
            'notify_url' => (string) ($params['notify_url'] ?? config('services.epusdt.notify_url')),
            'currency' => strtolower((string) ($params['currency'] ?? config('services.epusdt.default_currency', 'usd'))),
            'token' => strtolower((string) ($params['token'] ?? config('services.epusdt.default_token', 'usdt'))),
            'network' => strtoupper((string) ($params['network'] ?? config('services.epusdt.default_network', 'TRON'))),
        ];

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
