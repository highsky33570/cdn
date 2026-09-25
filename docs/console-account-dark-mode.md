# Account pages: dark-mode correction

Updated these five customer routes:

- `/console/billing/orders` — 消费记录
- `/console/account/login-logs` — 登录日志 and 操作日志
- `/console/messages` — 消息查询
- `/console/messages/subscriptions` — 消息订阅
- `/console/account/api-key` — API密钥

The previous scoped selectors used `:global(.dark) .page-selector`. The installed Vue style compiler reduced these selectors to `.dark`, so the declarations did not target the page elements. The pages also contained fixed white surfaces and light borders.

The five page styles now use existing console theme variables for card surfaces, foregrounds, muted surfaces/text, borders, links, focus outlines, errors and disabled inputs. Removed the broken global dark overrides. Shared custom form components, templates, scripts and API behavior remain unchanged.

Validation: production build, targeted ESLint/Prettier checks, and local browser checks for all five pages. Browser checks use the real appearance buttons and verify computed background/text colors and contrast, headers, field borders, hover states, applicable dropdown/calendar/detail popups, persisted dark mode after reload, mobile layouts, light-mode restoration and automatic system-theme changes. Also checked the operation-log tab and disabled API-key state. Browser data is mocked; no production writes were made.

The cumulative ZIP includes source and compiled assets. Extract into `tycdn-backend` and run `php artisan optimize:clear`. No migration is needed. Not deployed by the agent.
