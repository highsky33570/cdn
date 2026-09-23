# Line-group edit modal

The name and Edit actions in `/console/admin/line-groups` now open a compact editor matching the supplied screenshot.

- 420px dialog with a small title, aligned labels and inputs, separated footer, and blue Confirm button before Cancel.
- The editor is centered horizontally and vertically using the shared dialog overlay. The explicit top-alignment override was removed; tall content remains scrollable on smaller screens.
- Fields: name, resolution value, IPv4 resolution value, remark, sort and L2 configuration. The immutable region is omitted from this edit form.
- L2 dropdown displays 请选择 and 无匹配数据 when no configurations are returned. Existing assignments remain visible, and can be explicitly cleared.
- Backup-IP switching is always visible as three choices: 有主IP下线时, 在线IP数少于备用IP数时 and 间隔切换. Interval mode exposes its existing IP-count, interval and order controls.
- The create-group dialog retains its region field and existing layout.

Existing API requests and payload rules are retained. Unchanged L2 and switching settings are not sent as edits. Save errors preserve the draft.

Production build, Vue type checks, ESLint and formatting passed. Browser checks using captured group records and local L2 fixtures covered empty/populated L2 options, preserving unchanged settings, assigning/clearing L2, switching policies, interval values, failure/retry, light/dark/mobile layout and the create dialog. No production writes were performed.

Extract the archive into `tycdn-backend` preserving paths, upload the included application files and complete `public/build`, run `php artisan optimize:clear`, and hard-refresh. Earlier console changes are included to keep sources and compiled assets consistent. No SQL or database migration is needed. Not deployed automatically.
