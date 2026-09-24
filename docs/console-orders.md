# Admin orders layout

Route: `/console/admin/finance/orders`

The page now uses a dedicated order workspace with entry/delete actions, type and payment-state filters, a date-range picker, searchable master users, and numbered pagination. The table includes selection plus all requested columns: ID, 用户ID, 类型, 备注, 原价, 实际支付, 更多, 支付方式, 订单号, 创建时间, 已付款, 操作. Wide data scrolls within the table. Long text has full tooltips; 更多 opens the formatted business information and handles both JSON strings and objects without crashing on malformed text.

List amounts are displayed in yuan after converting native integer cents. The order dialog labels amount inputs in cents and submits integers unchanged. Paid state has an accessible icon. Pagination uses native `count`, not `total` (which represents a monetary sum). Selected date endpoints include the final day by passing the next date as the API's exclusive end.

The entry/edit dialog includes user, six reference order types, remarks, creation/payment times, original/paid amounts, payment method, merchant order number, transaction ID and state. Existing orders load from the detail endpoint before becoming editable. Updates contain only changed fields, preserving legacy empty payment methods and unrelated native fields. Failed saves keep the entered values. Duplicate submits are blocked while saving. Deletion is confirmed in the UI and processed per selected order; failed IDs stay selected for retry without repeating successful deletions.

Read routes remain `/api/admin/workspace/master-orders` and `/{id}`. New admin-only, verified and throttled write routes are `POST /api/admin/finance/orders`, `PUT /api/admin/finance/orders/{id}` and `DELETE /api/admin/finance/orders/{id}`. The controller validates and forwards the native fields to `/v1/orders`, using master user IDs rather than portal user IDs. The existing generic financial/message resources remain read-only.

Uses shared project controls and theme colors. The shared date-range calendar now wraps its two months on narrow screens.

Validation includes backend feature tests for native payloads, partial updates, validation, authorization, reads and deletion; existing navigation tests; Vue TypeScript and ESLint checks; production Vite build; and Chromium tests with mocked responses covering table columns, unit conversion, count pagination, filters, forms, failure handling, partial deletion, dark mode and mobile layouts. No production orders were created, edited or deleted.

## Installation

The cumulative ZIP includes source and built assets along with previous console updates. Extract into the Laravel backend directory and run:

```sh
php artisan optimize:clear
```

No migrations or dependency installation are required. The update has not been deployed by this session.
