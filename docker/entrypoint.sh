#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html

as_www() { su -s /bin/bash www-data -c "$*"; }

mkdir -p app/cache app/logs
chown -R www-data:www-data app web
chmod -R ug+rwX app/cache app/logs
find app/cache app/logs -type d -exec chmod 2775 {} \;

until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" --silent; do
  sleep 1
done

export COMPOSER_CACHE_DIR=/tmp/composer-cache
export COMPOSER_ALLOW_SUPERUSER=1

mkdir -p vendor node_modules app/cache app/cache/${SYMFONY_ENV}/annotations app/logs web/bundles
chown -R www-data:www-data vendor node_modules app/cache app/logs web app
chmod -R ug+rwX app app/cache app/logs

export NPM_CONFIG_CACHE="${NPM_CONFIG_CACHE:-/tmp/.npm}"
mkdir -p "$NPM_CONFIG_CACHE"
chown -R www-data:www-data "$NPM_CONFIG_CACHE" || true
chmod -R ug+rwX "$NPM_CONFIG_CACHE" || true

NPM_CONFIG_CACHE="${NPM_CONFIG_CACHE:-/tmp/.npm}"
as_www "mkdir -p '${NPM_CONFIG_CACHE}'"

NPM_CONFIG_CACHE="${NPM_CONFIG_CACHE:-/tmp/.npm}"
mkdir -p "${NPM_CONFIG_CACHE}" node_modules
chown -R www-data:www-data "${NPM_CONFIG_CACHE}" node_modules || true

if [ -f package-lock.json ]; then
  chmod a+r package-lock.json
fi

if [ ! -d node_modules ] || [ -z "$(ls -A node_modules 2>/dev/null)" ]; then
  if [ -f package-lock.json ]; then
    as_www "npm ci --no-fund --no-audit --cache '${NPM_CONFIG_CACHE}'"
  else
    as_www "npm install --no-fund --no-audit --cache "${NPM_CONFIG_CACHE}" --package-lock=false"
  fi
  chown -R www-data:www-data node_modules
fi

as_www "composer install --no-interaction --prefer-dist --no-scripts"

# Manually patch php7.3-4 regressions (https://github.com/doctrine/orm/issues/7402)
as_www "sed -i '2636s/continue;/break;/' /var/www/html/vendor/doctrine/orm/lib/Doctrine/ORM/UnitOfWork.php"
as_www "sed -i '2665s/continue;/break;/' /var/www/html/vendor/doctrine/orm/lib/Doctrine/ORM/UnitOfWork.php"

as_www "composer install --no-interaction --prefer-dist"
as_www "php app/console doctrine:database:create --if-not-exists --env=${SYMFONY_ENV} --no-debug"

DOES_SCHEMA_EXIST=$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" -D"${DB_NAME}" -se "SHOW TABLES LIKE 'ext_translations';")

 if [ -z "$DOES_SCHEMA_EXIST" ]; then 
 as_www "php app/console doctrine:schema:create --env=${SYMFONY_ENV} --no-debug" 
 fi

mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" -D"${DB_NAME}" -e "UPDATE user SET notif_locale = 'en' WHERE notif_locale IS NULL OR notif_locale = '';"
as_www "php app/console doctrine:schema:update --force --env=${SYMFONY_ENV} --no-debug" || true

  CARD_COUNT="$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" -D"${DB_NAME}" -Nse "SELECT COUNT(*) FROM card;" 2>/dev/null || echo 0)"
  if [ "${CARD_COUNT}" = "0" ]; then 
    as_www "php -d memory_limit=-1 app/console app:import:std /var/www/html/dbJSON --env=${SYMFONY_ENV} --no-debug || true"
  fi

if [ "${SYMFONY_ENV}" = "dev" ]; then
    as_www "php app/console fos:user:create dev dev@localhost dev --env=${SYMFONY_ENV} -n" || true
    as_www "php app/console fos:user:activate dev --env=${SYMFONY_ENV} -n" || true
    as_www "php app/console fos:user:promote --super dev --env=${SYMFONY_ENV} -n" || true
fi

exec "$@"
