# Console L2 configuration update

Updates `/console/admin/workspace/l2-configs` and `/console/admin/workspace/l2-conds` to match the master panel's L2 configuration workflow.

- Two tabs, compact tables, region filter, selection, batch deletion and pagination.
- Configuration dialog: region, name, remark, cache/global mode and round-robin/URL-hash balancing.
- Configuration and condition dialogs use the shared overlay's horizontal and vertical centering, with scrolling retained when the content exceeds a small screen. Removed their explicit top-alignment override.
- Condition dialog: name, remark and visual matching rules with field/operator selectors, multiline values, add/edit/remove actions and AND semantics.
- Reads existing details before editing. Native JSON-encoded rules are decoded without changing empty values, multiline text, secondary values or additional fields. Invalid rule data blocks saving until a successful reload.
- Configuration-node links scope both the binding list and new bindings to the selected configuration.
- Uses existing API endpoints and theme colors. No schema change, migration or SQL is required.

## Verification

Read-only review of the master and console APIs found zero L2 configurations and one condition. The existing country rule was `node_country_code != cn`. Field names and allowed options were checked against the live master application's bundle.

Production build, Vue type checking, ESLint and formatting checks passed. Three rule-decoding tests passed. Browser checks used captured read responses and local mocked writes: 26 requests, 8 mock writes, no browser errors. Covered both tabs, configuration creation/editing, existing-rule preservation, visual rule editing, malformed details, region filtering, partial-delete retry, direct condition route, scoped binding creation, and light/dark/mobile rendering. No production configurations were changed by these checks.

## Applying the ZIP

The archive is relative to the `tycdn-backend` application directory. Upload its application files into that directory, including the complete `public/build` directory and its manifest together. Source files are included for future rebuilds. The package also includes the preceding line-group update's changed sources/controller so they stay consistent with the compiled frontend.

After upload, run `php artisan optimize:clear` from the backend directory and hard-refresh the browser. No database commands are needed. This package has not been deployed automatically.

The `README-*.md` files and tests are documentation/verification files and are not needed to serve the compiled frontend.
