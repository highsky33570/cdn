<?php

namespace App\Support;

/**
 * The five storefront tiers shown on the sample pricing page.
 *
 * Numeric limits go to CDNfly packages; prices/marketing go to portal products.
 * Keep both halves here so admin presets, the seeder, and the sync command
 * cannot drift apart.
 */
final class SampleSubscriptionCatalog
{
    /**
     * @return list<array{
     *   slug: string,
     *   name: string,
     *   badge: ?string,
     *   price_monthly: float,
     *   sort_order: int,
     *   description: string,
     *   features: list<string>,
     *   package: array<string, mixed>
     * }>
     */
    public static function tiers(): array
    {
        return [
            [
                'slug' => 'advanced',
                'name' => '高级版',
                'badge' => null,
                'price_monthly' => 50.0,
                'sort_order' => 10,
                'description' => '入门级防护套餐，适合中小型站点。',
                'features' => [
                    '防投诉：不支持',
                    '共享节点但是屏蔽',
                    '节点线路：优质',
                ],
                'package' => [
                    'name' => '高级版',
                    'traffic' => 512,
                    'bandwidth' => '50Mbps',
                    'domain' => 20,
                    'main_domain' => 20,
                    'connection' => 5000,
                    'http_port' => 0,
                    'stream_port' => 0,
                    'custom_cc_rule' => 1,
                    'websocket' => 1,
                    'http3' => 1,
                    'ddos_protect' => '无敌抗',
                    'buy_num_limit' => 1,
                ],
            ],
            [
                'slug' => 'professional',
                'name' => '专业版',
                'badge' => null,
                'price_monthly' => 130.0,
                'sort_order' => 20,
                'description' => '更高域名与流量配额，适合成长型业务。',
                'features' => [
                    '防投诉：不支持',
                    '共享节点但是屏蔽',
                    '节点线路：优质',
                ],
                'package' => [
                    'name' => '专业版',
                    'traffic' => 2000,
                    'bandwidth' => '100Mbps',
                    'domain' => 40,
                    'main_domain' => 40,
                    'connection' => 10000,
                    'http_port' => 2,
                    'stream_port' => 0,
                    'custom_cc_rule' => 1,
                    'websocket' => 1,
                    'http3' => 1,
                    'ddos_protect' => '无敌抗',
                    'buy_num_limit' => 1,
                ],
            ],
            [
                'slug' => 'commercial',
                'name' => '商业版',
                'badge' => null,
                'price_monthly' => 260.0,
                'sort_order' => 30,
                'description' => '面向多站点与更高月流量需求的商业套餐。',
                'features' => [
                    '防投诉：不支持',
                    '共享节点但是屏蔽',
                    '节点线路：优质',
                ],
                'package' => [
                    'name' => '商业版',
                    'traffic' => 4000,
                    'bandwidth' => '100Mbps',
                    'domain' => 80,
                    'main_domain' => 80,
                    'connection' => 20000,
                    'http_port' => 5,
                    'stream_port' => 5,
                    'custom_cc_rule' => 1,
                    'websocket' => 1,
                    'http3' => 1,
                    'ddos_protect' => '无敌抗',
                    'buy_num_limit' => 1,
                ],
            ],
            [
                'slug' => 'invincible',
                'name' => '无敌版',
                'badge' => 'recommend',
                'price_monthly' => 600.0,
                'sort_order' => 40,
                'description' => '不限流量与防投诉支持，推荐选购。',
                'features' => [
                    '防投诉：支持',
                    '共享节点但是屏蔽',
                    '节点线路：优质',
                ],
                'package' => [
                    'name' => '无敌版',
                    'traffic' => -1,
                    'bandwidth' => '100Mbps',
                    'domain' => 160,
                    'main_domain' => 160,
                    'connection' => 50000,
                    'http_port' => 10,
                    'stream_port' => 10,
                    'custom_cc_rule' => 1,
                    'websocket' => 1,
                    'http3' => 1,
                    'ddos_protect' => '无敌抗',
                    'buy_num_limit' => 1,
                ],
            ],
            [
                'slug' => 'private-custom',
                'name' => '私人定制版',
                'badge' => 'custom',
                'price_monthly' => 1199.0,
                'sort_order' => 50,
                'description' => '不限域名与流量，独立节点与顶级线路。',
                'features' => [
                    '防投诉：支持',
                    '独立节点（高峰期更稳定）',
                    '过移动屏蔽、地区屏蔽',
                    '节点线路：顶级中的顶级',
                ],
                'package' => [
                    'name' => '私人定制版',
                    'traffic' => -1,
                    'bandwidth' => '100Mbps',
                    'domain' => -1,
                    'main_domain' => -1,
                    'connection' => -1,
                    'http_port' => 20,
                    'stream_port' => 20,
                    'custom_cc_rule' => 1,
                    'websocket' => 1,
                    'http3' => 1,
                    'ddos_protect' => '无敌抗',
                    'buy_num_limit' => 1,
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_column(self::tiers(), 'slug');
    }
}
