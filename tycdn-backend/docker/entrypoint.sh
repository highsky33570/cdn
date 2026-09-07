#!/bin/sh
set -e

# Config/route/view caches MUST be built here, at container start, not at image
# build time. The image is built without a .env (see .dockerignore), so caching
# during build would freeze every env() value as null -- including APP_KEY -- and
# the container would boot with no database, mail or gateway credentials.

if [ -z "${APP_KEY}" ]; then
  echo "FATAL: APP_KEY is not set. Pass the environment (env_file / --env-file) to this container." >&2
  exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
