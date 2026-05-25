#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html || true

SYMFONY_ENV="${SYMFONY_ENV:-prod}"
SYMFONY_DEBUG="${SYMFONY_DEBUG:-0}"

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-swddb}"
DB_USER="${DB_USER:-swddb}"
DB_PASSWORD="${DB_PASSWORD:-swddb}"

NODE_BIN="${NODE_BIN:-/usr/local/bin/node}"
HANDLEBARS_BIN="${HANDLEBARS_BIN:-/var/www/html/node_modules/handlebars/bin/handlebars}"

as_www() { su -s /bin/bash www-data -c "$*"; }

mkdir -p app/cache app/logs var
chown -R www-data:www-data app var web
chmod -R ug+rwX app/cache app/logs var
find app/cache app/logs var -type d -exec chmod 2775 {} \;

until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" --silent; do
  sleep 1
done

export COMPOSER_CACHE_DIR=/tmp/composer-cache
export COMPOSER_ALLOW_SUPERUSER=1

mkdir -p vendor node_modules app/cache app/logs var

chown -R www-data:www-data vendor node_modules app/cache app/logs var
chmod -R ug+rwX vendor node_modules app/cache app/logs var

as_www "composer install --no-interaction --prefer-dist"

mkdir -p web/bundles
chown -R www-data:www-data web
chown -R www-data:www-data app
chmod -R ug+rwX app

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
    npm ci --no-fund --no-audit --cache "${NPM_CONFIG_CACHE}"
  else
    npm install --no-fund --no-audit --cache "${NPM_CONFIG_CACHE}" --package-lock=false
  fi
  chown -R www-data:www-data node_modules
fi

mkdir -p app/cache/${SYMFONY_ENV}/annotations
chown -R www-data:www-data app/cache app/logs var || true
chmod -R ug+rwX app/cache app/logs var || true

as_www "php app/console doctrine:database:create --if-not-exists --env=${SYMFONY_ENV} --no-debug"

if ! as_www "php app/console doctrine:schema:validate --env=${SYMFONY_ENV} --no-debug" > /dev/null 2>&1; then
    as_www "php app/console doctrine:schema:create --env=${SYMFONY_ENV} --no-debug"
fi


mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" -D"${DB_NAME}" -e "UPDATE user SET notif_locale = 'en' WHERE notif_locale IS NULL OR notif_locale = '';" || true
as_www "php app/console doctrine:schema:update --force --env=${SYMFONY_ENV} --no-debug"

  CARD_COUNT="$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" -D"${DB_NAME}" -Nse "SELECT COUNT(*) FROM card;" 2>/dev/null || echo 0)"
  if [ "${CARD_COUNT}" = "0" ]; then 
    as_www "php -d memory_limit=-1 app/console app:import:std /var/www/html/dbJSON --env=${SYMFONY_ENV} --no-debug || true"
  fi

if [ "${CREATE_DEV_ADMIN:-1}" = "1" ]; then
  DEV_ADMIN_USER="${DEV_ADMIN_USER:-dev}"
  DEV_ADMIN_EMAIL="${DEV_ADMIN_EMAIL:-dev@localhost}"
  DEV_ADMIN_PASS="${DEV_ADMIN_PASS:-dev}"

  EXISTING="$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" -D"${DB_NAME}" -Nse "SELECT COUNT(*) FROM user WHERE username='${DEV_ADMIN_USER}';" 2>/dev/null || echo 0)"
fi

  if [ "${EXISTING}" = "0" ]; then
    as_www "php app/console fos:user:create \"${DEV_ADMIN_USER}\" \"${DEV_ADMIN_EMAIL}\" \"${DEV_ADMIN_PASS}\" --env=${SYMFONY_ENV} --no-debug -n"
    as_www "php app/console fos:user:activate \"${DEV_ADMIN_USER}\" --env=${SYMFONY_ENV} --no-debug -n || true"
    as_www "php app/console fos:user:promote --super \"${DEV_ADMIN_USER}\" --env=${SYMFONY_ENV} --no-debug -n || true"
  fi


chown -R www-data:www-data app/cache app/logs var || true

exec "$@"
