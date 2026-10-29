#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
  # The image intentionally has no .env file. Generate a key for this process.
  APP_KEY="$(php artisan key:generate --show --no-interaction)"
  export APP_KEY
fi

php artisan migrate --seed --force
exec apache2-foreground
