#!/bin/sh
set -e

# Container env vars (from docker-compose) take precedence over .env values.
[ -f .env ] || cp .env.example .env

# Generate an app key on first start; it is never baked into the image.
if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

php artisan migrate --force
php artisan db:seed --force

exec "$@"
