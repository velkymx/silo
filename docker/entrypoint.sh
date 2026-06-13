#!/usr/bin/env bash
set -euo pipefail

cd /var/www

# 1. First-boot env: copy the docker template if no .env is present.
if [ ! -f .env ]; then
    cp docker/.env.docker .env
fi

# 2. SQLite database file (persisted via the storage volume).
DB_FILE="${DB_DATABASE:-/var/www/storage/app/database.sqlite}"
mkdir -p "$(dirname "$DB_FILE")"
touch "$DB_FILE"

# 3. Application key — persisted on the storage volume so it survives container
#    recreation (a changing APP_KEY would invalidate every session each boot).
KEY_FILE=/var/www/storage/app/.appkey
if [ -f "$KEY_FILE" ]; then
    sed -i "s|^APP_KEY=.*|APP_KEY=$(cat "$KEY_FILE")|" .env
fi
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
    grep '^APP_KEY=' .env | cut -d= -f2- > "$KEY_FILE"
fi

# 4. Permissions on writable paths (volumes mount as root).
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rw storage bootstrap/cache

# 5. Schema + storage symlink.
php artisan migrate --force
php artisan storage:link || true

# 6. Idempotent admin account (credentials from env).
php artisan app:create-admin \
    --email="${ADMIN_EMAIL:-admin@example.com}" \
    --password="${ADMIN_PASSWORD:-}" \
    --name="${ADMIN_NAME:-Administrator}" || true

# 7. Cache config/routes/views for speed.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
