#!/bin/sh
set -e

# ==============================================================================
# 1. Cấu hình cổng lắng nghe của Apache theo biến $PORT do Render cấp
# ==============================================================================
PORT="${PORT:-80}"
echo "[Render Entrypoint] Cấu hình Apache lắng nghe trên port ${PORT}..."
sed -i "s/Listen [0-9]*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:${PORT}>/" /etc/apache2/sites-available/*.conf

# ==============================================================================
# 2. Kiểm tra APP_KEY (Tự động sinh nếu chưa cấu hình trên Render)
# ==============================================================================
if [ -z "$APP_KEY" ]; then
    echo "[Render Entrypoint] Cảnh báo: Chưa có APP_KEY. Đang tự động tạo key tạm..."
    php artisan key:generate --force || true
fi

# ==============================================================================
# 3. Tạo symlink public/storage nếu chưa có
# ==============================================================================
if [ -L "/var/www/html/public/storage" ] && [ ! -e "/var/www/html/public/storage" ]; then
    rm -f /var/www/html/public/storage
fi

if [ ! -e "/var/www/html/public/storage" ]; then
    echo "[Render Entrypoint] Tạo liên kết storage (storage:link)..."
    php artisan storage:link || true
fi

# ==============================================================================
# 4. Khởi tạo Cơ sở dữ liệu
# ==============================================================================
DB_CONN="${DB_CONNECTION:-sqlite}"

if [ "$DB_CONN" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    if [ ! -f "$DB_FILE" ]; then
        if [ -f "/var/www/html/btlar" ]; then
            echo "[Render Entrypoint] Sử dụng dữ liệu mẫu từ tệp btlar -> $DB_FILE..."
            cp /var/www/html/btlar "$DB_FILE"
        else
            echo "[Render Entrypoint] Khởi tạo tệp SQLite mới: $DB_FILE..."
            touch "$DB_FILE"
        fi
        chown www-data:www-data "$DB_FILE"
        chmod 664 "$DB_FILE"

        echo "[Render Entrypoint] Đang chạy migrate SQLite..."
        php artisan migrate --force || true
    else
        if [ "${AUTORUN_MIGRATIONS:-true}" = "true" ] || [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
            echo "[Render Entrypoint] Cập nhật migrations cho SQLite..."
            php artisan migrate --force || true
        fi
    fi
    chown -R www-data:www-data "$(dirname "$DB_FILE")"
else
    # Khi dùng MySQL hoặc PostgreSQL bên ngoài (Render Postgres, Supabase, Aiven, v.v.)
    if [ "${AUTORUN_MIGRATIONS:-false}" = "true" ] || [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        echo "[Render Entrypoint] Đang chạy migrations cho $DB_CONN..."
        php artisan migrate --force || true
    fi
fi

# ==============================================================================
# 5. Xử lý bộ nhớ đệm Laravel
# ==============================================================================
echo "[Render Entrypoint] Làm sạch cache cấu hình cũ..."
php artisan config:clear || true
php artisan view:clear || true
php artisan route:clear || true

if [ "${APP_ENV}" = "production" ]; then
    echo "[Render Entrypoint] Tối ưu hóa cache cho môi trường Production..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# ==============================================================================
# 6. Đảm bảo phân quyền thư mục cho www-data
# ==============================================================================
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

echo "[Render Entrypoint] Khởi động Apache web server trên port ${PORT}..."
exec apache2-foreground
