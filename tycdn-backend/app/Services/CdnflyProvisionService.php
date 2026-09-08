<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductCdnflyMapping;
use App\Models\ServiceInstance;
use App\Support\OrderStatus;
use App\Support\OrderType;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CdnflyProvisionService
{
    public function __construct(
        private readonly CdnflyApiService $cdnflyApiService,
    ) {}

    public function provisionPaidOrder(Order $order): array
    {
        // Serialise per order: the EPUSDT callback, the 5-minute reconcile sweep and
        // the user-facing provision endpoint can all land here simultaneously, and a
        // double run means buying the upstream package twice.
        $lock = Cache::lock('cdnfly-provision:'.$order->id, 300);

        if (! $lock->get()) {
            Log::info('cdnfly provisioning already in progress; skipping duplicate run', [
                'order_id' => $order->id,
            ]);

            return ['status' => 'already_processing_or_active'];
        }

        try {
            return $this->runProvisioning($order);
        } finally {
            $lock->release();
        }
    }

    private function runProvisioning(Order $order): array
    {
        $freshOrder = Order::query()
            ->with(['user', 'product:id,name', 'targetServiceInstance'])
            ->findOrFail($order->id);

        if ($freshOrder->provisioned_at !== null || $freshOrder->status === OrderStatus::ACTIVE) {
            return ['status' => 'already_provisioned'];
        }

        if (! $freshOrder->user) {
            return $this->queueProvisioning(
                $freshOrder,
                null,
                'Order user is missing.',
                null,
                [],
                'configuration',
                'missing_order_user'
            );
        }

        // A recharge settles by crediting the CDNfly balance; there is no product,
        // no package and no service instance. Handled here rather than at each
        // caller so the notify callback, the reconcile sweep and the user-triggered
        // endpoint all settle it the same way — including the retry cap.
        if (OrderType::isRecharge($freshOrder->order_type)) {
            return $this->creditBalance($freshOrder);
        }

        $mapping = ProductCdnflyMapping::query()
            ->where('product_id', $freshOrder->product_id)
            ->first();

        if (! $mapping) {
            return $this->queueProvisioning(
                $freshOrder,
                null,
                'No product_cdnfly_mappings record found for product_id='.$freshOrder->product_id,
                null,
                [],
                'configuration',
                'missing_product_mapping'
            );
        }

        $provisionAction = $this->resolveProvisionAction($freshOrder);
        $serviceInstance = $this->resolveServiceInstance($freshOrder, $provisionAction);

        if ($serviceInstance === null) {
            return $this->queueProvisioning(
                $freshOrder,
                $mapping,
                'Renewal order is missing the target service instance.',
                null,
                [],
                'configuration',
                'missing_target_service'
            );
        }

        if (
            $provisionAction === 'new'
            && $serviceInstance->exists
            && in_array($serviceInstance->status, ['active', 'provisioning'], true)
        ) {
            return ['status' => 'already_processing_or_active'];
        }

        $context = $this->buildContext($freshOrder, $mapping, $serviceInstance, $provisionAction);
        $payload = $this->buildProvisionPayload($context);

        if (! $freshOrder->user->hasCdnflyApiKey()) {
            return $this->queueProvisioning(
                $freshOrder,
                $mapping,
                "User {$freshOrder->user->id} is missing CDNfly API credentials.",
                $serviceInstance,
                [
                    'request_payload' => $payload,
                    'provision_action' => $provisionAction,
                ],
                'configuration',
                'missing_user_api_credentials'
            );
        }

        if ($validationFailure = $this->validateProvisionRequest($freshOrder, $provisionAction, $payload, $serviceInstance)) {
            return $this->queueProvisioning(
                $freshOrder,
                $mapping,
                $validationFailure['reason'],
                $serviceInstance,
                [
                    'request_payload' => $payload,
                    'provision_action' => $provisionAction,
                ],
                $validationFailure['stage'],
                $validationFailure['code']
            );
        }

        if (! (bool) config('services.cdnfly.outbound_enabled', true)) {
            return $this->queueProvisioning(
                $freshOrder,
                $mapping,
                'CDNfly outbound is disabled.',
                $serviceInstance,
                [
                    'request_payload' => $payload,
                    'provision_action' => $provisionAction,
                ],
                'configuration',
                'cdnfly_outbound_disabled'
            );
        }

        $this->markProvisioning($freshOrder, $mapping, $serviceInstance, $payload, $provisionAction);

        try {
            $responseBody = $provisionAction === 'renew'
                ? $this->cdnflyApiService->renewUserPackage($freshOrder->user, (string) $serviceInstance->cdnfly_service_id, $payload)
                : $this->cdnflyApiService->purchaseUserPackage($freshOrder->user, $payload);
        } catch (\Throwable $e) {
            return $this->handleProvisionException(
                $freshOrder,
                $mapping,
                $serviceInstance,
                $payload,
                $provisionAction,
                $e
            );
        }

        $cdnflyServiceId = $provisionAction === 'renew'
            ? $this->scalarString($serviceInstance->cdnfly_service_id)
            : $this->extractFirstString($responseBody, ['data', 'data.id', 'id']);

        if ($provisionAction === 'new' && $cdnflyServiceId === null) {
            return $this->failProvisioning(
                $freshOrder,
                $mapping,
                'CDNfly purchase response is missing package id.',
                $serviceInstance,
                [
                    'response_body' => $responseBody,
                    'request_payload' => $payload,
                    'provision_action' => $provisionAction,
                ],
                'cdnfly',
                'missing_created_package_id'
            );
        }

        $detailResponse = $cdnflyServiceId !== null
            ? $this->fetchUserPackageDetails($freshOrder->user, $cdnflyServiceId)
            : null;

        $detailData = is_array($detailResponse) && is_array($detailResponse['data'] ?? null)
            ? $detailResponse['data']
            : [];

        $serviceName = $this->extractFirstString($detailData, [
            'user_package_name',
            'name',
            'package_name',
        ]) ?? $this->resolveServiceName($freshOrder, $serviceInstance, $payload);

        $openedAt = $this->extractFirstDate($detailData, [
            'start_at2',
            'start_at',
            'create_at',
        ]) ?? ($serviceInstance->opened_at ?? now());

        $expiredAt = $this->extractFirstDate($detailData, [
            'end_at2',
            'end_at',
        ]) ?? $serviceInstance->expired_at;

        $serviceInstance->update([
            'user_id' => $freshOrder->user_id,
            'product_id' => $freshOrder->product_id,
            'status' => 'active',
            'cdnfly_user_id' => $this->extractFirstString($detailData, ['uid', 'user_id'])
                ?? $this->scalarString($freshOrder->user->cdnfly_user_id),
            'cdnfly_service_id' => $cdnflyServiceId,
            'service_name' => $serviceName,
            'opened_at' => $openedAt,
            'expired_at' => $expiredAt,
            'config_snapshot' => array_merge($serviceInstance->config_snapshot ?? [], [
                'mapping' => $this->mappingSnapshot($mapping),
                'request_payload' => $payload,
                'response' => $responseBody,
                'detail_response' => $detailResponse,
                'provision_action' => $provisionAction,
            ]),
            'extra' => array_merge($serviceInstance->extra ?? [], [
                'queue_reason' => null,
                'queue_stage' => null,
                'queue_code' => null,
                'failure_stage' => null,
                'failure_code' => null,
                'last_error' => null,
                'last_attempt_at' => now()->toIso8601String(),
                'provision_action' => $provisionAction,
                'provision_attempts' => 0,
            ]),
        ]);

        $freshOrder->update([
            'status' => OrderStatus::ACTIVE,
            'provisioned_at' => now(),
        ]);

        return [
            'status' => 'success',
            'provision_action' => $provisionAction,
            'service_instance_id' => $serviceInstance->id,
            'cdnfly_service_id' => $cdnflyServiceId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(
        Order $order,
        ProductCdnflyMapping $mapping,
        ServiceInstance $serviceInstance,
        string $provisionAction
    ): array {
        $mappingPayload = is_array($mapping->provision_payload) ? $mapping->provision_payload : [];

        return [
            'action' => $provisionAction,
            'order' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'user_id' => $order->user_id,
                'product_id' => $order->product_id,
                'order_type' => $order->order_type,
                'billing_cycle' => $order->billing_cycle,
                'duration' => $this->toCdnflyDuration($order->billing_cycle),
                'quantity' => $order->quantity,
                'fiat_amount' => $order->fiat_amount,
                'fiat_currency' => $order->fiat_currency,
                'amount_usdt' => $order->amount_usdt,
                'gateway_trade_id' => $order->gateway_trade_id,
                'gateway_actual_amount' => $order->gateway_actual_amount,
                'pay_address' => $order->pay_address,
                'target_service_instance_id' => $order->target_service_instance_id,
            ],
            'user' => [
                'id' => $order->user?->id,
                'name' => $order->user?->name,
                'email' => $order->user?->email,
                'cdnfly_user_id' => $order->user?->cdnfly_user_id,
            ],
            'product' => [
                'id' => $order->product?->id,
                'name' => $order->product?->name,
            ],
            'mapping' => [
                'cdnfly_plan_id' => $mapping->cdnfly_plan_id,
                'cdnfly_group_id' => $mapping->cdnfly_group_id,
                'cdnfly_package_id' => $this->resolveMappedPackageId($mapping),
                'provision_payload' => $mappingPayload,
            ],
            'service' => [
                'id' => $serviceInstance->id,
                'service_name' => $serviceInstance->service_name,
                'cdnfly_service_id' => $serviceInstance->cdnfly_service_id,
                'desired_name' => $this->resolveServiceName($order, $serviceInstance),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildProvisionPayload(array $context): array
    {
        $mappingPayload = $context['mapping']['provision_payload'] ?? [];
        if (! is_array($mappingPayload)) {
            $mappingPayload = [];
        }

        $mappingPayload = $this->resolveTemplateValues($mappingPayload, $context);
        $action = (string) ($context['action'] ?? 'new');

        if ($action === 'renew') {
            return array_filter([
                'duration' => Arr::get($mappingPayload, 'duration', $context['order']['duration']),
                'coupon_code' => Arr::get($mappingPayload, 'coupon_code'),
            ], fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return array_filter([
            'package' => Arr::get($mappingPayload, 'package', $context['mapping']['cdnfly_package_id']),
            'duration' => Arr::get($mappingPayload, 'duration', $context['order']['duration']),
            'name' => Arr::get($mappingPayload, 'name', $context['service']['desired_name']),
            'coupon_code' => Arr::get($mappingPayload, 'coupon_code'),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveTemplateValues(mixed $value, array $context): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->resolveTemplateValues($item, $context);
            }

            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('/^\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}$/', $value, $matches) === 1) {
            return Arr::get($context, $matches[1]);
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function (array $matches) use ($context) {
            $replacement = Arr::get($context, $matches[1]);

            if (is_array($replacement) || is_object($replacement)) {
                return json_encode($replacement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }

            return (string) ($replacement ?? '');
        }, $value);
    }

    private function resolveProvisionAction(Order $order): string
    {
        return strtolower((string) $order->order_type) === 'renew' ? 'renew' : 'new';
    }

    private function resolveServiceInstance(Order $order, string $provisionAction): ?ServiceInstance
    {
        if ($provisionAction === 'renew') {
            return $order->targetServiceInstance;
        }

        return ServiceInstance::query()->firstOrNew([
            'source_order_id' => $order->id,
        ]);
    }

    private function resolveMappedPackageId(ProductCdnflyMapping $mapping): ?string
    {
        $payloadPackage = is_array($mapping->provision_payload)
            ? ($mapping->provision_payload['package'] ?? null)
            : null;

        if (is_scalar($payloadPackage) && trim((string) $payloadPackage) !== '') {
            return trim((string) $payloadPackage);
        }

        if (is_scalar($mapping->cdnfly_plan_id) && trim((string) $mapping->cdnfly_plan_id) !== '') {
            return trim((string) $mapping->cdnfly_plan_id);
        }

        return null;
    }

    private function toCdnflyDuration(?string $billingCycle): ?string
    {
        return match (strtolower(trim((string) $billingCycle))) {
            'month', 'monthly' => 'month',
            'quarter', 'quarterly' => 'quarter',
            'year', 'yearly' => 'year',
            default => null,
        };
    }

    private function resolveServiceName(Order $order, ServiceInstance $serviceInstance, array $payload = []): string
    {
        $requestedName = $this->scalarString($payload['name'] ?? null);
        if ($requestedName !== null && trim($requestedName) !== '') {
            return trim($requestedName);
        }

        $existingName = $this->scalarString($serviceInstance->service_name);
        if ($existingName !== null && trim($existingName) !== '') {
            return trim($existingName);
        }

        $productName = trim((string) ($order->product?->name ?? ''));

        return $productName !== ''
            ? $productName.' '.$order->order_no
            : 'cdn-'.$order->order_no;
    }

    /**
     * @return array{reason: string, stage: string, code: string}|null
     */
    private function validateProvisionRequest(
        Order $order,
        string $provisionAction,
        array $payload,
        ServiceInstance $serviceInstance
    ): ?array {
        if (($payload['duration'] ?? null) === null || trim((string) $payload['duration']) === '') {
            return [
                'reason' => 'Order billing cycle could not be mapped to CDNfly duration.',
                'stage' => 'configuration',
                'code' => 'invalid_billing_cycle',
            ];
        }

        if ($provisionAction === 'renew') {
            if ($order->target_service_instance_id === null) {
                return [
                    'reason' => 'Renewal order is missing target_service_instance_id.',
                    'stage' => 'configuration',
                    'code' => 'missing_target_service',
                ];
            }

            if (! $this->scalarString($serviceInstance->cdnfly_service_id)) {
                return [
                    'reason' => 'Target service instance is missing cdnfly_service_id for renewal.',
                    'stage' => 'configuration',
                    'code' => 'missing_cdnfly_service_id',
                ];
            }

            return null;
        }

        if (($payload['package'] ?? null) === null || trim((string) $payload['package']) === '') {
            return [
                'reason' => 'Product mapping is missing the CDNfly package id.',
                'stage' => 'configuration',
                'code' => 'missing_cdnfly_package_id',
            ];
        }

        return null;
    }

    private function markProvisioning(
        Order $order,
        ProductCdnflyMapping $mapping,
        ServiceInstance $serviceInstance,
        array $payload,
        string $provisionAction
    ): void {
        $serviceInstance->fill([
            'user_id' => $order->user_id,
            'product_id' => $order->product_id,
            'status' => $this->provisioningStatus($serviceInstance, $provisionAction),
            'service_name' => $this->resolveServiceName($order, $serviceInstance, $payload),
            'config_snapshot' => array_merge($serviceInstance->config_snapshot ?? [], [
                'mapping' => $this->mappingSnapshot($mapping),
                'request_payload' => $payload,
                'provision_action' => $provisionAction,
            ]),
            'extra' => array_merge($serviceInstance->extra ?? [], [
                'queue_reason' => null,
                'queue_stage' => null,
                'queue_code' => null,
                'failure_stage' => null,
                'failure_code' => null,
                'last_error' => null,
                'last_attempt_at' => now()->toIso8601String(),
                'provision_action' => $provisionAction,
            ]),
        ]);
        $serviceInstance->save();
    }

    private function fetchUserPackageDetails($user, string $cdnflyServiceId): ?array
    {
        try {
            return $this->cdnflyApiService->getUserPackage($user, $cdnflyServiceId);
        } catch (\Throwable $e) {
            Log::warning('cdnfly package detail fetch failed', [
                'cdnfly_service_id' => $cdnflyServiceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function handleProvisionException(
        Order $order,
        ProductCdnflyMapping $mapping,
        ServiceInstance $serviceInstance,
        array $payload,
        string $provisionAction,
        \Throwable $e
    ): array {
        $message = $e->getMessage();

        if (str_contains($message, 'CDNfly outbound is disabled')) {
            return $this->queueProvisioning(
                $order,
                $mapping,
                $message,
                $serviceInstance,
                [
                    'request_payload' => $payload,
                    'provision_action' => $provisionAction,
                ],
                'configuration',
                'cdnfly_outbound_disabled'
            );
        }

        if (str_contains($message, 'missing CDNfly API credentials')) {
            return $this->queueProvisioning(
                $order,
                $mapping,
                $message,
                $serviceInstance,
                [
                    'request_payload' => $payload,
                    'provision_action' => $provisionAction,
                ],
                'configuration',
                'missing_user_api_credentials'
            );
        }

        return $this->failProvisioning(
            $order,
            $mapping,
            $message,
            $serviceInstance,
            [
                'request_payload' => $payload,
                'provision_action' => $provisionAction,
            ],
            'cdnfly',
            'cdnfly_exception'
        );
    }

    private function extractFirstString(mixed $responseBody, array $paths): ?string
    {
        if (! is_array($responseBody)) {
            return null;
        }

        foreach ($paths as $path) {
            $value = Arr::get($responseBody, $path);
            if ($value === null || $value === '') {
                continue;
            }

            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function extractFirstDate(array $payload, array $keys): ?Carbon
    {
        foreach ($keys as $key) {
            $value = $this->scalarString($payload[$key] ?? null);
            if ($value === null || trim($value) === '') {
                continue;
            }

            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Settle a paid recharge by crediting the customer's CDNfly balance.
     *
     * The amount credited is what the customer actually paid, not what they were
     * quoted — an underpaid order must not top up the full figure.
     *
     * @return array<string, mixed>
     */
    private function creditBalance(Order $order): array
    {
        $cdnflyUserId = (int) ($order->user?->cdnfly_user_id ?? 0);

        if ($cdnflyUserId <= 0) {
            return $this->queueProvisioning(
                $order,
                null,
                'User has no CDNfly account to credit.',
                null,
                ['order_type' => OrderType::RECHARGE],
                'configuration',
                'missing_cdnfly_user'
            );
        }

        $amount = (float) ($order->actual_paid_amount ?? $order->fiat_amount ?? 0);

        if ($amount <= 0) {
            return $this->failProvisioning(
                $order,
                null,
                'Recharge order has no positive amount to credit.',
                null,
                ['order_type' => OrderType::RECHARGE],
                'configuration',
                'invalid_recharge_amount'
            );
        }

        if (! (bool) config('services.cdnfly.outbound_enabled', true)) {
            return $this->queueProvisioning(
                $order,
                null,
                'CDNfly outbound is disabled.',
                null,
                ['order_type' => OrderType::RECHARGE, 'amount' => $amount],
                'configuration',
                'cdnfly_outbound_disabled'
            );
        }

        try {
            $response = $this->cdnflyApiService->rechargeUser($cdnflyUserId, $amount);
        } catch (\Throwable $e) {
            Log::warning('cdnfly balance recharge failed', [
                'order_no' => $order->order_no,
                'cdnfly_user_id' => $cdnflyUserId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return $this->failProvisioning(
                $order,
                null,
                'CDNfly balance recharge failed: '.$e->getMessage(),
                null,
                ['order_type' => OrderType::RECHARGE, 'amount' => $amount],
                'cdnfly',
                'recharge_failed'
            );
        }

        // provisioned_at is what stops the reconcile sweep retrying this order, so
        // it must be set in the same step that credits the balance.
        $order->update([
            'status' => OrderStatus::ACTIVE,
            'provisioned_at' => now(),
        ]);

        Log::info('cdnfly balance recharged', [
            'order_no' => $order->order_no,
            'cdnfly_user_id' => $cdnflyUserId,
            'amount' => $amount,
        ]);

        return [
            'status' => 'success',
            'provision_action' => OrderType::RECHARGE,
            'amount' => $amount,
            'response' => $response,
        ];
    }

    private function failProvisioning(
        Order $order,
        ?ProductCdnflyMapping $mapping,
        string $reason,
        ?ServiceInstance $serviceInstance = null,
        array $extra = [],
        string $failureStage = 'cdnfly',
        string $failureCode = 'cdnfly_failed'
    ): array {
        Log::warning('cdnfly provisioning failed', [
            'order_no' => $order->order_no,
            'product_id' => $order->product_id,
            'reason' => $reason,
            'failure_stage' => $failureStage,
            'failure_code' => $failureCode,
            'extra' => $extra,
        ]);

        if ($serviceInstance === null) {
            $serviceInstance = ServiceInstance::query()->firstOrNew([
                'source_order_id' => $order->id,
            ]);
        }

        $serviceInstance->fill([
            'user_id' => $order->user_id,
            'product_id' => $order->product_id,
            'status' => $this->failedStatus($serviceInstance, $this->resolveProvisionAction($order)),
            'service_name' => $serviceInstance->service_name ?? $this->resolveServiceName($order, $serviceInstance),
            'config_snapshot' => array_merge($serviceInstance->config_snapshot ?? [], [
                'mapping' => $mapping ? $this->mappingSnapshot($mapping) : null,
            ]),
            'extra' => array_merge($serviceInstance->extra ?? [], $extra, [
                'queue_reason' => null,
                'queue_stage' => null,
                'queue_code' => null,
                'last_error' => $reason,
                'failure_stage' => $failureStage,
                'failure_code' => $failureCode,
                'last_attempt_at' => now()->toIso8601String(),
                'provision_attempts' => ((int) data_get($serviceInstance->extra, 'provision_attempts', 0)) + 1,
            ]),
        ]);
        $serviceInstance->save();

        return [
            'status' => 'failed',
            'failure_stage' => $failureStage,
            'failure_code' => $failureCode,
            'reason' => $reason,
            'service_instance_id' => $serviceInstance->id,
        ];
    }

    private function queueProvisioning(
        Order $order,
        ?ProductCdnflyMapping $mapping,
        string $reason,
        ?ServiceInstance $serviceInstance = null,
        array $extra = [],
        string $queueStage = 'configuration',
        string $queueCode = 'queued'
    ): array {
        Log::info('cdnfly provisioning queued', [
            'order_no' => $order->order_no,
            'product_id' => $order->product_id,
            'reason' => $reason,
            'queue_stage' => $queueStage,
            'queue_code' => $queueCode,
            'extra' => $extra,
        ]);

        if ($serviceInstance === null) {
            $serviceInstance = ServiceInstance::query()->firstOrNew([
                'source_order_id' => $order->id,
            ]);
        }

        $serviceInstance->fill([
            'user_id' => $order->user_id,
            'product_id' => $order->product_id,
            'status' => $this->queuedStatus($serviceInstance, $this->resolveProvisionAction($order)),
            'service_name' => $serviceInstance->service_name ?? $this->resolveServiceName($order, $serviceInstance),
            'config_snapshot' => array_merge($serviceInstance->config_snapshot ?? [], [
                'mapping' => $mapping ? $this->mappingSnapshot($mapping) : null,
            ]),
            'extra' => array_merge($serviceInstance->extra ?? [], $extra, [
                'queue_reason' => $reason,
                'queue_stage' => $queueStage,
                'queue_code' => $queueCode,
                'failure_stage' => null,
                'failure_code' => null,
                'last_error' => null,
                'last_attempt_at' => now()->toIso8601String(),
                'provision_attempts' => ((int) data_get($serviceInstance->extra, 'provision_attempts', 0)) + 1,
            ]),
        ]);
        $serviceInstance->save();

        return [
            'status' => 'queued',
            'queue_stage' => $queueStage,
            'queue_code' => $queueCode,
            'reason' => $reason,
            'service_instance_id' => $serviceInstance->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mappingSnapshot(ProductCdnflyMapping $mapping): array
    {
        return [
            'cdnfly_plan_id' => $mapping->cdnfly_plan_id,
            'cdnfly_group_id' => $mapping->cdnfly_group_id,
            'cdnfly_package_id' => $this->resolveMappedPackageId($mapping),
            'provision_payload' => $mapping->provision_payload,
        ];
    }

    private function provisioningStatus(ServiceInstance $serviceInstance, string $provisionAction): string
    {
        if ($provisionAction === 'renew' && $serviceInstance->exists) {
            return $serviceInstance->status ?: 'active';
        }

        return 'provisioning';
    }

    private function queuedStatus(ServiceInstance $serviceInstance, string $provisionAction): string
    {
        if ($provisionAction === 'renew' && $serviceInstance->exists) {
            return $serviceInstance->status ?: 'active';
        }

        return 'pending';
    }

    private function failedStatus(ServiceInstance $serviceInstance, string $provisionAction): string
    {
        if ($provisionAction === 'renew' && $serviceInstance->exists) {
            return $serviceInstance->status ?: 'active';
        }

        return 'failed';
    }

    private function scalarString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
