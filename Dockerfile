FROM php:8.2-fpm

# Встановлення системних залежностей та PHP-розширень
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Директорія для PHP-сесій
RUN mkdir -p /var/lib/php/sessions \
    && chown -R www-data:www-data /var/lib/php/sessions

# Кастомна PHP конфігурація
RUN echo "session.save_handler = files" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "session.save_path = /var/lib/php/sessions" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "session.gc_maxlifetime = 7200" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "session.cookie_samesite = Lax" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 12M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "date.timezone = Europe/Kyiv" >> /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]