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
# 2. Khởi tạo file .env nếu chưa có
# ==============================================================================
if [ ! -f /var/www/html/.env ]; then
    echo "[Render Entrypoint] Khởi tạo .env từ .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Luôn đẩy log Laravel ra stderr để hiển thị trực tiếp trên tab Logs của Render
sed -i "s|^LOG_CHANNEL=.*|LOG_CHANNEL=stderr|" /var/www/html/.env

# Đồng bộ các biến môi trường quan trọng vào .env
if [ -n "$APP_KEY" ]; then
    echo "[Render Entrypoint] Đã nhận APP_KEY từ cấu hình Render."
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" /var/www/html/.env
else
    echo "[Render Entrypoint] Chưa có APP_KEY. Đang tự sinh key cho Laravel..."
    php artisan key:generate --force || true
fi

if [ -n "$APP_ENV" ]; then
    sed -i "s|^APP_ENV=.*|APP_ENV=${APP_ENV}|" /var/www/html/.env
fi

if [ -n "$APP_DEBUG" ]; then
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=${APP_DEBUG}|" /var/www/html/.env
fi

FEATURE_CART="${FEATURE_CART:-true}"
if grep -q "^FEATURE_CART=" /var/www/html/.env; then
    sed -i "s|^FEATURE_CART=.*|FEATURE_CART=${FEATURE_CART}|" /var/www/html/.env
else
    echo "FEATURE_CART=${FEATURE_CART}" >> /var/www/html/.env
fi

if [ -n "$APP_URL" ]; then
    sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" /var/www/html/.env
fi

if [ -n "$GEMINI_API_KEY" ]; then
    echo "[Render Entrypoint] Đã nhận cấu hình GEMINI_API_KEY cho Trợ lý AI."
    if grep -q "^GEMINI_API_KEY=" /var/www/html/.env; then
        sed -i "s|^GEMINI_API_KEY=.*|GEMINI_API_KEY=${GEMINI_API_KEY}|" /var/www/html/.env
    else
        echo "GEMINI_API_KEY=${GEMINI_API_KEY}" >> /var/www/html/.env
    fi
fi

if [ -n "$GEMINI_MODEL" ]; then
    if grep -q "^GEMINI_MODEL=" /var/www/html/.env; then
        sed -i "s|^GEMINI_MODEL=.*|GEMINI_MODEL=${GEMINI_MODEL}|" /var/www/html/.env
    else
        echo "GEMINI_MODEL=${GEMINI_MODEL}" >> /var/www/html/.env
    fi
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
# 4. Khởi tạo Cơ sở dữ liệu SQLite / MySQL / PostgreSQL
# ==============================================================================
DB_CONN="${DB_CONNECTION:-sqlite}"

if [ "$DB_CONN" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    
    # Đồng bộ vào .env
    sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=sqlite|" /var/www/html/.env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_FILE}|" /var/www/html/.env

    if [ ! -f "$DB_FILE" ] || [ ! -s "$DB_FILE" ]; then
        if [ -f "/var/www/html/btlar" ]; then
            echo "[Render Entrypoint] Sử dụng dữ liệu có sẵn từ btlar -> $DB_FILE..."
            cp /var/www/html/btlar "$DB_FILE"
        else
            echo "[Render Entrypoint] Khởi tạo tệp SQLite mới: $DB_FILE..."
            touch "$DB_FILE"
        fi
        chown www-data:www-data "$DB_FILE"
        chmod 664 "$DB_FILE"

        echo "[Render Entrypoint] Chạy migrations cho SQLite..."
        php artisan migrate --force || true
    else
        chown www-data:www-data "$DB_FILE"
        chmod 664 "$DB_FILE"
        if [ "${AUTORUN_MIGRATIONS:-true}" = "true" ] || [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
            echo "[Render Entrypoint] Cập nhật migrations cho SQLite..."
            php artisan migrate --force || true
        fi
    fi
    chown -R www-data:www-data "$(dirname "$DB_FILE")"
    chmod 775 "$(dirname "$DB_FILE")"
else
    # Khi dùng MySQL hoặc PostgreSQL bên ngoài
    if [ "${AUTORUN_MIGRATIONS:-false}" = "true" ] || [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        echo "[Render Entrypoint] Chạy migrations cho $DB_CONN..."
        php artisan migrate --force || true
    fi
fi

# Nạp toàn bộ dữ liệu mẫu (sản phẩm, danh mục, blog, đánh giá, cộng đồng, khuyến mãi, kho bãi)
echo "[Render Entrypoint] Đảm bảo nạp đầy đủ dữ liệu mẫu (DatabaseSeeder)..."
php artisan db:seed --force || true

# Gán hình ảnh sản phẩm, danh mục và thư viện ảnh từ storage
echo "[Render Entrypoint] Gán hình ảnh cho danh mục và sản phẩm..."
php artisan categories:link-photos --force || true
php artisan products:link-photos --force || true
php artisan products:link-gallery || true


# ==============================================================================
# 5. Xử lý bộ nhớ đệm Laravel
# ==============================================================================
echo "[Render Entrypoint] Làm sạch cache Laravel..."
php artisan config:clear || true
php artisan view:clear || true
php artisan route:clear || true

if [ "${APP_ENV}" = "production" ]; then
    echo "[Render Entrypoint] Tối ưu hóa cấu hình cho môi trường Production..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# ==============================================================================
# 6. Đảm bảo phân quyền thư mục cho www-data
# ==============================================================================
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database /var/www/html/.env
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod 664 /var/www/html/.env

echo "[Render Entrypoint] Khởi động Apache trên port ${PORT}..."
exec apache2-foreground
