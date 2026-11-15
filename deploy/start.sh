#!/bin/sh
# Container start: listen on Railway's $PORT, wait for MySQL, migrate, seed the demo data, start Apache.
set -e
# Apache may only load one MPM; mod_php needs prefork. Some hosts end up with two enabled.
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
a2enmod mpm_prefork >/dev/null 2>&1 || true
PORT="${PORT:-8080}"
sed -ri "s/Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

tries=0
until php agus migrate; do
    tries=$((tries + 1))
    if [ "$tries" -ge 20 ]; then echo "Database not reachable, giving up."; exit 1; fi
    echo "Waiting for the database..."; sleep 3
done
php agus db:seed
chown -R www-data:www-data storage public/uploads

exec apache2-foreground
