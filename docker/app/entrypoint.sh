#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html

log(){ echo "[entrypoint] $*"; }

SYMFONY_ENV="${SYMFONY_ENV:-prod}"
SYMFONY_DEBUG="${SYMFONY_DEBUG:-0}"

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-swddb}"
DB_USER="${DB_USER:-swddb}"
DB_PASSWORD="${DB_PASSWORD:-swddbpass}"

NODE_BIN="${NODE_BIN:-/usr/local/bin/node}"
HANDLEBARS_BIN="${HANDLEBARS_BIN:-/var/www/html/node_modules/handlebars/bin/handlebars}"
SKIP_IMPORT="${SKIP_IMPORT:-0}"

as_www() { su -s /bin/bash www-data -c "$*"; }

mkdir -p app/cache app/logs var
chown -R www-data:www-data app/cache app/logs var || true
chmod -R ug+rwX app/cache app/logs var || true
find app/cache app/logs var -type d -exec chmod 2775 {} \; || true

if [ ! -f app/config/parameters.yml ]; then
  log "Generating app/config/parameters.yml"
  cat > app/config/parameters.yml <<EOF
parameters:
  db_host: "${DB_HOST}"
  db_name: "${DB_NAME}"
  db_username: "${DB_USER}"
  db_password: "${DB_PASSWORD}"

  node_bin: "${NODE_BIN}"
  handlebars_bin: "${HANDLEBARS_BIN}"

  happyr_messages_api_key: "${HAPPYR_MESSAGES_API_KEY:-}"
  happyr_validators_api_key: "${HAPPYR_VALIDATORS_API_KEY:-}"

  website_url: "${WEBSITE_URL:-localhost:8080}"
  website_name: "${WEBSITE_NAME:-Dev}"
  game_name: "${GAME_NAME:-Dev}"
  publisher_name: "${PUBLISHER_NAME:-Dev}"

  email_sender_address: "${EMAIL_SENDER_ADDRESS:-noreply@localhost}"
  email_sender_name: "${EMAIL_SENDER_NAME:-Dev}"

  mailer_transport: "${MAILER_TRANSPORT:-smtp}"
  mailer_host: "${MAILER_HOST:-localhost}"
  mailer_port: ${MAILER_PORT:-25}
  mailer_user: "${MAILER_USER:-}"
  mailer_password: "${MAILER_PASSWORD:-}"
  mailer_encryption: ${MAILER_ENCRYPTION:-null}
  mailer_auth_mode: ${MAILER_AUTH_MODE:-null}

  google_analytics_tracking_code: "${GOOGLE_ANALYTICS_TRACKING_CODE:-UA-00000000-1}"
  google_adsense_client: "${GOOGLE_ADSENSE_CLIENT:-ca-pub-000000000000000}"
  google_adsense_slot: "${GOOGLE_ADSENSE_SLOT:-0000000000}"

  moneytizer_site_id: "${MONEYTIZER_SITE_ID:-}"
  moneytizer_ad_types: ${MONEYTIZER_AD_TYPES:-"[]"}
  cache_expiration: ${CACHE_EXPIRATION:-600}
  secret: "${SECRET:-dev-secret-change-me}"
EOF
  chown www-data:www-data app/config/parameters.yml || true
fi

log "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" --silent; do
  sleep 1
done
log "MySQL is up."

export COMPOSER_CACHE_DIR=/tmp/composer-cache
export COMPOSER_ALLOW_SUPERUSER=1

mkdir -p vendor node_modules app/cache app/logs var

chown -R www-data:www-data vendor node_modules app/cache app/logs var || true
chmod -R ug+rwX vendor node_modules app/cache app/logs var || true

if [ ! -f vendor/autoload.php ]; then
  log "Installing PHP deps (vendor missing)"
  as_www "composer install --no-interaction --no-progress --prefer-dist --no-scripts"
fi

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
  chmod a+r package-lock.json || true
fi

if [ ! -d node_modules ] || [ -z "$(ls -A node_modules 2>/dev/null)" ]; then
  echo "[entrypoint] Installing Node deps…"
  if [ -f package-lock.json ]; then
    as_www "npm ci --no-fund --no-audit --cache '${NPM_CONFIG_CACHE}'"
  else
    as_www "npm install --no-fund --no-audit --cache '${NPM_CONFIG_CACHE}' --package-lock=false"
  fi
fi


mkdir -p app/cache/${SYMFONY_ENV}/annotations
chown -R www-data:www-data app/cache app/logs var || true
chmod -R ug+rwX app/cache app/logs var || true

as_www "php app/console doctrine:database:create --if-not-exists --env=${SYMFONY_ENV} --no-debug"

log "Updating schema (idempotent)"
as_www "php app/console doctrine:schema:update --force --env=${SYMFONY_ENV} --no-debug"

as_www "php app/console doctrine:query:sql \"ALTER TABLE \\\`user\\\` MODIFY \\\`notif_locale\\\` VARCHAR(10) NULL DEFAULT 'en'\" --env=${SYMFONY_ENV} --no-debug || true"
as_www "php app/console doctrine:query:sql \"UPDATE \\\`user\\\` SET \\\`notif_locale\\\`='en' WHERE \\\`notif_locale\\\` IS NULL\" --env=${SYMFONY_ENV} --no-debug || true"

if [ "${SKIP_IMPORT}" = "0" ] && [ -d "dbJSON" ]; then
  CARD_COUNT="$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" -D"${DB_NAME}" -Nse "SELECT COUNT(*) FROM card;" 2>/dev/null || echo 0)"
  if [ "${CARD_COUNT}" = "0" ]; then
    log "Importing dbJSON (cards empty)"
    as_www "php -d memory_limit=-1 app/console app:import:std /var/www/html/dbJSON --env=${SYMFONY_ENV} --no-debug || true"
  else
    log "Skipping import (cards=${CARD_COUNT})"
  fi
fi

if [ "${FORCE_ASSETS:-0}" = "1" ] || [ ! -f web/css/app.css ]; then
  log "Dumping assetic assets"
  as_www "php -d memory_limit=-1 app/console assetic:dump --env=${SYMFONY_ENV} --no-debug || true"
fi

log "Clearing cache"
as_www "php app/console cache:clear --env=${SYMFONY_ENV} --no-debug || true"
as_www "php app/console cache:warmup --env=${SYMFONY_ENV} --no-debug || true"

if [ "${CREATE_DEV_ADMIN:-1}" = "1" ]; then
  DEV_ADMIN_USER="${DEV_ADMIN_USER:-dev}"
  DEV_ADMIN_EMAIL="${DEV_ADMIN_EMAIL:-dev@localhost}"
  DEV_ADMIN_PASS="${DEV_ADMIN_PASS:-dev}"

  EXISTING="$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" -D"${DB_NAME}" -Nse "SELECT COUNT(*) FROM user WHERE username='${DEV_ADMIN_USER}';" 2>/dev/null || echo 0)"
  if [ "${EXISTING}" = "0" ]; then
    log "Creating dev admin (${DEV_ADMIN_USER}/${DEV_ADMIN_PASS})"
    as_www "php app/console fos:user:create \"${DEV_ADMIN_USER}\" \"${DEV_ADMIN_EMAIL}\" \"${DEV_ADMIN_PASS}\" --env=${SYMFONY_ENV} --no-debug -n"
    as_www "php app/console fos:user:activate \"${DEV_ADMIN_USER}\" --env=${SYMFONY_ENV} --no-debug -n || true"
    as_www "php app/console fos:user:promote --super \"${DEV_ADMIN_USER}\" --env=${SYMFONY_ENV} --no-debug -n || true"
  else
    log "Dev admin exists"
  fi
fi

# Final perms
chown -R www-data:www-data app/cache app/logs var || true

exec "$@"
