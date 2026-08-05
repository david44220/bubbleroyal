FROM php:8.5-apache

WORKDIR /var/www/html

RUN a2enmod rewrite headers

COPY deploy/staging/apache.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html
