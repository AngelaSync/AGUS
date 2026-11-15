# Runs Replate on Railway (or any Docker host): PHP 8.3 + Apache, MySQL from the environment.
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/agus.conf \
    && a2enconf agus

WORKDIR /var/www/html
COPY . .
RUN rm -f .env \
    && mkdir -p storage/cache storage/logs storage/sessions public/uploads/listings \
    && chown -R www-data:www-data storage public/uploads \
    && chmod +x deploy/start.sh

ENV APP_ENV=production APP_DEBUG=false DB_DRIVER=mysql
CMD ["deploy/start.sh"]
