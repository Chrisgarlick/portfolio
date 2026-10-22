# PHP-FPM for chrisgarlick.com, from the official image.
#
# The droplet runs Ubuntu 24.10, which stopped receiving PHP security
# updates when it reached end of life in July 2025. PHP runs in this
# container so it can be patched (a rebuild on a newer tag) until the site
# moves to an LTS server. The site's code stays on the host at
# /var/www/site, bind-mounted at the same path.
#
# Build from deploy/server:  docker build -f php-docker/Dockerfile -t site-php:8.3.36 .
FROM php:8.3.36-fpm-bookworm

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libbz2-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" bcmath bz2 calendar exif gd intl pcntl pdo_pgsql pgsql sockets zip opcache \
 && rm -rf /var/lib/apt/lists/*

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY php/99-site.ini "$PHP_INI_DIR/conf.d/99-site.ini"
COPY php-docker/zz-site.conf /usr/local/etc/php-fpm.d/zz-site.conf
