#!/bin/sh
set -e
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
cd /var/www

# Bootstrap env for first run (Compose also injects DB_*; Laravel still needs a .env file)
if [ ! -f .env ] && [ -f .env.example ]; then
  echo "No .env file; copying .env.example -> .env"
  cp .env.example .env
fi

# Composer lives in the image at /usr/local/bin/composer (see Dockerfile)
/usr/local/bin/composer install --no-interaction --prefer-dist --optimize-autoloader

if [ -f .env ] && ! grep -qE '^APP_KEY=.+' .env; then
  php artisan key:generate --force
fi

RUN_DB_WAIT=0
if [ -f .env ]; then
  if [ "${AUTORUN_MIGRATIONS:-1}" = "1" ] || [ "${AUTORUN_SEED:-1}" = "1" ]; then
    RUN_DB_WAIT=1
  fi
fi

if [ "$RUN_DB_WAIT" = "1" ]; then
  echo "Waiting for database at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
  DB_READY=0
  i=0
  while [ "$i" -lt 90 ]; do
    if php -r "
      try {
        \$h = getenv('DB_HOST') ?: 'mysql';
        \$p = getenv('DB_PORT') ?: '3306';
        \$u = getenv('DB_USERNAME') ?: 'eaglepoint';
        \$w = getenv('DB_PASSWORD') ?: 'secret';
        new PDO('mysql:host=' . \$h . ';port=' . \$p, \$u, \$w);
        exit(0);
      } catch (Throwable \$e) {
        exit(1);
      }
    " 2>/dev/null; then
      echo "Database is reachable."
      DB_READY=1
      break
    fi
    i=$((i + 1))
    sleep 2
  done
  if [ "$DB_READY" != "1" ]; then
    echo "ERROR: MySQL did not become reachable in time. Check DB_* and the mysql service."
    exit 1
  fi
fi

if [ "${AUTORUN_MIGRATIONS:-1}" = "1" ] && [ -f .env ]; then
  php artisan migrate --force
fi

if [ "${AUTORUN_SEED:-1}" = "1" ] && [ -f .env ]; then
  echo "Ensuring database seed (skipped if roles already exist)..."
  php docker/seed-if-empty.php
fi

exec /usr/local/bin/docker-php-entrypoint "$@"
