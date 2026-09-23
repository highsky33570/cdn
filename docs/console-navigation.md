# Console navigation matching the panel

The administrator sidebar follows the supplied `Untitled-1.html`: nine main entries and all 40 submenu entries, in the same order and with the same labels.

1. 服务概览
2. 统计分析 — 实时监控、数据分析、拉黑日志、访问日志、WAF日志、四层实时监控
3. 节点管理 — 节点列表、线路分组、L2配置、DNS配置、监控配置、实时监控
4. 网站管理 — 网站列表、证书管理、刷新预热、CC规则、WAF规则
5. 四层转发 — 转发列表
6. 全局配置 — 防火墙配置、Nginx配置、资源配置、默认配置、错误页面
7. 套餐管理 — 基础套餐、已售套餐、升级包、流量包、营销配置、套餐监控、用量查询
8. 财务管理 — 用户充值、所有订单、充值统计
9. 系统管理 — 系统配置、后台任务、用户列表、系统日志、维护升级、公告管理、消息查询

The customer sidebar remains unchanged. Console-only tools (local finance, package groups, stream defaults, node IP logs and security/permissions) remain available under **控制台工具** in the administrator sidebar footer. Existing routes continue to work.

## Destinations

Dedicated administrator routes open existing functionality in the correct view: node topology for 线路分组, sold CDNfly packages for 已售套餐, separate CC and WAF views, and the existing user recharge workflow for 用户充值. Admin cache management reuses the cache UI with administrator API credentials; the customer cache endpoint is unchanged.

所有订单, 充值统计 and 消息查询 have separate read views backed by the master's native APIs. The existing local orders/services page remains under 控制台工具 → 本地财务. The new master record views provide filters, pagination, error states and details; they do not expose order modification or deletion. Recharge statistics use paid recharge orders, daily/monthly/yearly grouping and date-only ranges. Master order amounts are minor units and are divided by 100 for display; aggregate `sum` already uses full units. Display remains USDT without performing currency conversion.

These contracts were checked against the bundled master panel source (`app.a8204d46.js`, `chunk-47f16a12.55f118cb.js`, `chunk-d0364398.d4967d34.js`, and `chunk-6cbe8d83`). The menu order comes directly from the supplied HTML. No live production data was modified for this update.

The four new explicitly mapped workspace resources are:

| Resource | Upstream | Methods |
| --- | --- | --- |
| `cache-jobs` | `/v1/jobs` | GET, POST (cache refresh/preheat jobs only) |
| `master-orders` | `/v1/orders` | GET |
| `recharge-count` | `/v1/order/count` | GET |
| `message-query` | `/v1/messages` | GET |

Menu destinations stay inside the console. Active links, automatic desktop group expansion after navigation, and breadcrumbs use the new grouping. Mobile navigation still closes after selection.

## Verification

- Browser comparison of all nine main labels and 40 child labels against the supplied HTML, including ordering.
- Browser checks for all nine added destinations, correct CC/WAF initial views, active links, breadcrumbs, master record filters and values, literal message details, error states, utility links, light/dark themes, mobile navigation and customer cache behavior. Populated records are isolated local fixtures.
- Five PHP integration tests with 126 assertions cover new routes, administrator authorization, master query/credential forwarding, cache job validation and read-only record resources.
- TypeScript, targeted ESLint, PHP formatting, production build and diff checks.

## Deployment

Extract `tycdn-navigation-console.zip` into the Laravel backend directory containing `artisan`, merging the included source and complete `public/build` files. Upload matching compiled assets and manifest together; retain older hashed assets for existing browser tabs.

Run `php artisan optimize:clear` to load the new routes. Restart persistent application workers if the deployment uses them, then refresh the console.

No SQL, database migration, dependency installation or server-side frontend build is required. This package is prepared locally and has not been deployed to the live console.
