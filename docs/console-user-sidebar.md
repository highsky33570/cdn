# User sidebar reference order

The user portal sidebar follows the supplied Untitled-1.html structure:

1. 服务概览
2. 统计分析: 实时监控, 数据分析, 拉黑日志, 访问日志, 四层实时监控
3. 网站管理: 网站列表, 证书管理, 刷新预热, CC规则, ACL规则
4. 四层转发: 转发列表
5. 套餐管理: 我的套餐, 套餐购买, 流量包, 用量查询
6. 账户中心: 个人资料, 账户充值, 消费记录, 日志查询, 消息查询, 消息订阅, API密钥

The two extra stream submenu entries are removed from the sidebar. Their existing routes remain available. The administrator navigation is unchanged.

The new /console/account/balance route opens the existing account recharge modal on the profile view. It uses the existing payment flow and requires an explicit payment action; opening the menu does not create an order. The route uses the existing authenticated, verified console middleware.

Build, Vue TypeScript, ESLint, Prettier, PHP route registration, and Pint checks passed. Browser verification compares the rendered six groups and 22 child links directly with the supplied HTML, checks active highlighting and the recharge modal, and covers light/dark/mobile layouts with mock API responses. No payment was made.

## Installation

The cumulative tycdn-user-sidebar.zip includes the preceding message-query update, changed source, and rebuilt assets. Extract into the Laravel backend directory, preserving paths, then run:

```sh
php artisan optimize:clear
```

Not deployed by the assistant. Environment files, vendor, and node_modules are excluded.
