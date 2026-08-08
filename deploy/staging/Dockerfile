FROM php:8.5-apache

WORKDIR /var/www/html

RUN a2enmod rewrite headers

RUN docker-php-ext-install pdo_mysql \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY deploy/staging/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/staging/entrypoint.sh /usr/local/bin/bubble-royale-entrypoint
COPY . /var/www/html

RUN mkdir -p /var/www/html/storage/cache/rate-limits /var/www/html/storage/logs /var/www/html/storage/sessions \
    && chmod 0755 /usr/local/bin/bubble-royale-entrypoint \
    && chown -R www-data:www-data /var/www/html/storage

ENTRYPOINT ["/usr/local/bin/bubble-royale-entrypoint"]
