# ==============================================================================
# GIAI ĐOẠN 1: Biên dịch Frontend Assets với Node.js & Vite
# ==============================================================================
FROM node:20-bookworm-slim AS frontend-builder

WORKDIR /app

# Cài đặt dependencies (sử dụng cache layer)
COPY package*.json ./
RUN npm ci || npm install

# Copy mã nguồn cần thiết và chạy build Vite
COPY . .
RUN npm run build

# ==============================================================================
# GIAI ĐOẠN 2: Môi trường PHP 8.4 + Apache cho Laravel trên Render
# ==============================================================================
FROM php:8.4-apache AS runner

WORKDIR /var/www/html

# Cài đặt thư viện hệ thống và các tiện ích mở rộng PHP cần thiết
# (GD cho hình ảnh/QR code/PDF, Zip cho Openspout, Intl, Pdo MySQL/PgSQL/SQLite, Opcache, BCMath)
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        zip \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        intl \
        bcmath \
        opcache \
        exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Kích hoạt các module Apache cần thiết cho Laravel và .htaccess
RUN a2enmod rewrite headers deflate filter

# Cấu hình VirtualHost trỏ vào thư mục public của Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Cấu hình PHP cho production
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Cài đặt Composer chính thức từ image Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Cài đặt PHP dependencies (tận dụng cache layer của Docker)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Copy toàn bộ mã nguồn dự án vào container
COPY . .

# Copy tài nguyên frontend đã build từ giai đoạn frontend-builder
COPY --from=frontend-builder /app/public/build ./public/build

# Sinh autoloader tối ưu cho production
RUN composer dump-autoload --optimize --no-dev --no-scripts

# Tạo các thư mục lưu trữ của Laravel và phân quyền cho người dùng www-data
RUN mkdir -p /var/www/html/storage/framework/cache/data \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/views \
             /var/www/html/storage/logs \
             /var/www/html/bootstrap/cache \
             /var/www/html/database \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Copy và cấp quyền thực thi cho entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh && chmod +x /usr/local/bin/docker-entrypoint.sh

# Cổng mặc định (Render sẽ tự động gán và ghi đè qua biến PORT)
ENV PORT=80
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
