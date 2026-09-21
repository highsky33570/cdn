<?php

namespace App\Support;

final class SitePayload
{
    /** Laravel decodes both {} and [] as arrays. CDNfly distinguishes disabled listeners from lists. */
    public static function normalize(array $payload): array
    {
        foreach (['http_listen', 'https_listen', 'health_check', 'proxy_auth', 'cc_switch', 'waf', 'waf_ip_auto_block', 'hotlink', 'cors'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] === []) {
                $payload[$key] = (object) [];
            }
        }

        return $payload;
    }
}
