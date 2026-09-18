#!/bin/sh
set -e

# Wait for MySQL to accept connections before Laravel tries to use it —
# matters on a fresh `docker compose up`, where the db container's process
# starts before it's actually ready to accept connections.
if [ -n "$DB_HOST" ]; then
  echo "Waiting for database at $DB_HOST:${DB_PORT:-3306}..."
  until php -r "new PDO('mysql:host=$DB_HOST;port=${DB_PORT:-3306}', '$DB_USERNAME', '$DB_PASSWORD');" 2>/dev/null; do
    sleep 1
  done
  echo "Database is up."
fi

# Config/route caching is safe to redo on every boot (idempotent, and
# reflects whatever environment variables this container actually has) —
# unlike `migrate`, which is intentionally NOT run here. Run migrations
# yourself, once per deploy: `docker compose run --rm artisan migrate --force`.
php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
