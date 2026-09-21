# Log tables and primary button color

The maintenance page now uses tables for system status, master versions, node versions, migration status, and authorization information. Node records use columns from the returned API data. Nested status fields use aligned project/detail rows. Upgrade and migration logs have line-number and content columns, with wrapping, scrolling, and sticky headers. Empty, unavailable, and failed responses remain inside the corresponding table. Refreshing displays newly available content.

Primary buttons use `#2d8cf0` with white text in light and dark modes across the console, its dialogs, and the public frontend. Hover and focus states remain visible. The existing unavailable-maintenance-log backend fix is included in the package.

## Deploy

The archive `tycdn-log-tables-and-buttons.zip` uses project-relative paths: `tycdn-backend/`, `frontend/`, and `docs/`. Extract into the directory containing those project folders, or map each folder to its corresponding deployed application.

1. Deploy the included backend PHP/source changes and the complete rebuilt `tycdn-backend/public/build/` directory, including its manifest. Retain older hashed assets while open browser tabs may still reference them.
2. In the deployed Laravel backend directory, run `php artisan optimize:clear`. Reload persistent PHP workers if the existing deployment uses them.
3. The public frontend change is `frontend/src/styles/global.css`. In the frontend directory, run `npm run build` using the existing production environment and publish the resulting `dist/` directory through the normal deployment process. Preserve the production API, dashboard, authentication, and CAPTCHA settings. Public JavaScript artifacts from the local validation build are deliberately not included because this workspace does not have the complete production build environment.
4. Refresh the pages after deployment.

No SQL, migrations, or dependency changes are required.

## Validation

- Console and public frontend production builds passed. The public validation build used `https://console.tycdn.org` for the API/dashboard URLs.
- Frontend lint and Vue/TypeScript checks passed.
- Local browser checks verified maintenance tables, node rows, nested status values including zero/false, preserved blank log lines and line numbering, refresh from unavailable to populated/error/empty, mobile layout, and dialog button color.
- Browser checks verify computed primary backgrounds of `rgb(45, 140, 240)` with white text in both themes, including the public header and login button.
- API data in browser checks were fixtures. No live data was modified. Production deployment has not been performed from this workspace.
