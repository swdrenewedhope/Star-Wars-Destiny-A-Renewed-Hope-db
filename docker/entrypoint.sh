#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html
as_www() { su -s /bin/bash www-data -c "$*"; }

mkdir -p vendor node_modules app/cache app/cache/${SYMFONY_ENV} app/cache/${SYMFONY_ENV}/annotations app/logs web/bundles
chown -R www-data:www-data vendor node_modules app/cache app/logs web app
chmod -R ug+rwX app app/cache app/logs

as_www "npm ci --no-fund --no-audit"

until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" --silent; do
  echo "Waiting for MySQL container.."
  sleep 1
done

as_www "php app/console doctrine:database:create --if-not-exists --env=${SYMFONY_ENV} --no-debug"

DOES_SCHEMA_EXIST=$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" -D"${DB_NAME}" -se "SHOW TABLES LIKE 'ext_translations';")

 if [ -z "$DOES_SCHEMA_EXIST" ]; then 
 as_www "php app/console doctrine:schema:create --env=${SYMFONY_ENV} --no-debug" 
 fi

mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" -D"${DB_NAME}" -e "UPDATE user SET notif_locale = 'en' WHERE notif_locale IS NULL OR notif_locale = '';"
as_www "php app/console doctrine:schema:update --force --env=${SYMFONY_ENV} --no-debug" || true

  CARD_COUNT="$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" -D"${DB_NAME}" -Nse "SELECT COUNT(*) FROM card;" 2>/dev/null || echo 0)"
  if [ "${CARD_COUNT}" = "0" ]; then 
    as_www "php -d memory_limit=-1 app/console app:import:std /var/www/html/dbJSON --env=${SYMFONY_ENV} --no-debug"
  fi

if [ "${SYMFONY_ENV}" = "dev" ]; then
    as_www "php app/console fos:user:create dev dev@localhost dev --env=${SYMFONY_ENV} -n" || true
    as_www "php app/console fos:user:activate dev --env=${SYMFONY_ENV} -n" || true
    as_www "php app/console fos:user:promote --super dev --env=${SYMFONY_ENV} -n" || true
fi

# Manually patch php7.3-4 regressions (https://github.com/doctrine/orm/issues/7402)
as_www "composer install --no-interaction --prefer-dist --no-scripts"
as_www "sed -i '2636s/continue;/break;/' /var/www/html/vendor/doctrine/orm/lib/Doctrine/ORM/UnitOfWork.php"
as_www "sed -i '2665s/continue;/break;/' /var/www/html/vendor/doctrine/orm/lib/Doctrine/ORM/UnitOfWork.php"
as_www "composer install --no-interaction --prefer-dist"

exec "$@"
