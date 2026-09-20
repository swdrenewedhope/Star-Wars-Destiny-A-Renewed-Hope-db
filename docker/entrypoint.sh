#!/usr/bin/env bash
set -euo pipefail

npm ci --no-fund --no-audit
composer install --no-interaction --no-scripts # patch php7.3-4 doctrine regressions (https://github.com/doctrine/orm/issues/7402)
sed -i '2636s/continue;/break;/' /var/www/html/vendor/doctrine/orm/lib/Doctrine/ORM/UnitOfWork.php
sed -i '2665s/continue;/break;/' /var/www/html/vendor/doctrine/orm/lib/Doctrine/ORM/UnitOfWork.php

until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" --silent; do echo "Waiting for MySQL container.."; sleep 0.5; done
php app/console doctrine:database:create --if-not-exists --env=${SYMFONY_ENV}
php app/console doctrine:schema:update --force --env=${SYMFONY_ENV}
php -d memory_limit=-1 app/console app:import:std /var/www/html/dbJSON --env=${SYMFONY_ENV}
mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" -D"${DB_NAME}" -e "UPDATE user SET notif_locale = 'en' WHERE notif_locale IS NULL OR notif_locale = '';"

if [ "${SYMFONY_ENV}" = "dev" ]; then
	rm -f app/logs/dev.log && chmod 777 -R /var/www/html;
    php app/console fos:user:create dev dev@localhost dev -n || true
    php app/console fos:user:activate dev -n || true
    php app/console fos:user:promote --super dev -n || true
	composer install --no-interaction
	cp -f web/app.php web/app_dev.php && php app/console server:run 0.0.0.0:80 & tail -F app/logs/dev.log
fi

if [ "${SYMFONY_ENV}" = "prod" ]; then 
	composer install --no-interaction --optimize-autoloader; php -d memory_limit=-1 app/console cache:warmup --env=prod
	cat > /usr/local/etc/php/conf.d/settings.ini <<'EOF'
	display_errors = Off
	log_errors = On
	error_log = /proc/self/fd/2
	error_reporting = E_ALL & ~E_DEPRECATED & ~E_NOTICE
	opcache.memory_consumption = 128
	opcache.max_accelerated_files = 20000
	opcache.validate_timestamps = 0
	realpath_cache_size = 4096K
	realpath_cache_ttl = 31536000
EOF
fi; chown -R www-data:www-data app/cache app/logs; exec "$@"