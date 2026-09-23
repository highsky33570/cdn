# Browser translation

The website and console explicitly opted out of translation in their HTML shells, using both `translate="no"` and `<meta name="google" content="notranslate">`. These directives have been removed. Console routes and the dashboard alias now declare `lang="zh-CN"` to match their Chinese text; the website already used that language.

The old comments described a workaround for translation-related rendering errors. Their referenced Vue issue does not concern translation, so those comments were removed as well. No global DOM patches, Google scripts or browser-setting overrides were added.

The website production build and existing dashboard tests are checked. This verifies removal of the application opt-out; Chrome's actual translation service and every translated interactive screen have not been tested end to end.

## Deployment

This small package uses project-root paths, unlike the previous backend-only packages:

1. Upload `tycdn-backend/resources/views/app.blade.php` to that location in the backend.
2. Upload the **contents** of `frontend/dist` to the website's public directory for `tycdn.org`.
3. `frontend/index.html` is the updated website source; keep it in the frontend source project.
4. Run `php artisan view:clear` from `tycdn-backend`, then hard-refresh both sites. If Chrome still remembers a per-site translation preference, use its Translate control manually.

No SQL or database migration is needed. No backend JavaScript rebuild is needed for this Blade-only change. The package has not been deployed automatically.
