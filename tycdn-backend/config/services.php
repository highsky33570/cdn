<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'timeout' => env('RECAPTCHA_TIMEOUT', 5),

        // When Google cannot be reached, allow the request through rather than
        // locking every user out of login/registration/reset. Those endpoints are
        // independently rate limited. Set false to prefer blocking over uptime.
        'fail_open' => env('RECAPTCHA_FAIL_OPEN', true),
    ],

    'epusdt' => [
        'base_url' => env('EPUSDT_BASE_URL'),
        'create_order_path' => env('EPUSDT_CREATE_ORDER_PATH', '/payments/gmpay/v1/order/create-transaction'),
        'query_order_path' => env('EPUSDT_QUERY_ORDER_PATH'),
        'pid' => env('EPUSDT_PID'),
        'api_token' => env('EPUSDT_API_TOKEN'),
        'notify_url' => env('EPUSDT_NOTIFY_URL'),
        'redirect_url' => env('EPUSDT_REDIRECT_URL'),
        'default_currency' => env('EPUSDT_DEFAULT_CURRENCY', 'USD'),
        'default_token' => env('EPUSDT_DEFAULT_TOKEN', 'usdt'),
        'default_network' => env('EPUSDT_DEFAULT_NETWORK', 'TRON'),

        // How long a checkout stays payable. Slow chains / manual transfers may
        // need more than the original hardcoded 30 minutes.
        'order_ttl_minutes' => env('EPUSDT_ORDER_TTL_MINUTES', 30),

        // How far back the reconciliation sweep looks for unsettled orders. Must
        // comfortably exceed order_ttl_minutes so late payments are still caught.
        'reconcile_lookback_minutes' => env('EPUSDT_RECONCILE_LOOKBACK_MINUTES', 1440),
    ],

    'cdnfly' => [
        'outbound_enabled' => env('CDNFLY_OUTBOUND_ENABLED', env('APP_ENV') !== 'local'),

        // Provisioning retries per order before the reconcile sweep gives up and
        // escalates. Prevents a permanently broken order retrying forever.
        'max_provision_attempts' => env('CDNFLY_MAX_PROVISION_ATTEMPTS', 10),

        // Optional "slug:packageId" list consumed by ProductSeeder to bind local
        // products to upstream CDNfly packages.
        'package_ids' => env('CDNFLY_PACKAGE_IDS', ''),

        // 凭证加密独立密钥（与 APP_KEY 隔离，防止 APP_KEY 泄露连带暴露 CDNfly 凭证）
        // 生成方式：php artisan cdnfly:generate-key
        'encryption_key' => env('CDNFLY_ENCRYPTION_KEY'),

        // CDNfly 主控地址（必须使用 https://，服务层会拒绝 HTTP 连接）
        'base_url' => env('CDNFLY_BASE_URL', 'https://cdn.cdn666.com'),
        'timeout' => env('CDNFLY_TIMEOUT', 15),

        // 管理员 API 密钥（用于创建用户、开通API、管理套餐等）
        'admin_api_key' => env('CDNFLY_ADMIN_API_KEY'),
        'admin_api_secret' => env('CDNFLY_ADMIN_API_SECRET'),
        'admin_key_header' => env('CDNFLY_ADMIN_KEY_HEADER', 'api-key'),
        'admin_secret_header' => env('CDNFLY_ADMIN_SECRET_HEADER', 'api-secret'),
        'cname_domain_options' => env('CDNFLY_CNAME_DOMAIN_OPTIONS', ''),

        // 旧的 provision 配置（CdnflyProvisionService 仍在使用）
        'provision_url' => env('CDNFLY_PROVISION_URL'),
        'auth_type' => env('CDNFLY_AUTH_TYPE', 'bearer'),
        'auth_token' => env('CDNFLY_AUTH_TOKEN'),
        'auth_header' => env('CDNFLY_AUTH_HEADER', 'Authorization'),
        'auth_prefix' => env('CDNFLY_AUTH_PREFIX', 'Bearer '),
        'headers' => array_filter([
            'Accept' => env('CDNFLY_ACCEPT_HEADER'),
        ]),
    ],

];
