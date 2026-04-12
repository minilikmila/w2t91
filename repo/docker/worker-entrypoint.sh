#!/bin/sh
set -e

cd /var/www

# Wait for vendor/autoload.php (app container runs composer install via bind mount)
echo "Worker: waiting for composer install to finish..."
while [ ! -f /var/www/vendor/autoload.php ]; do
  sleep 2
done

# Wait for the cache table to exist (signals app container finished migrations)
echo "Worker: waiting for database migrations..."
RETRIES=0
while [ "$RETRIES" -lt 60 ]; do
  if php -r "
    try {
      \$h = getenv('DB_HOST') ?: 'mysql';
      \$p = getenv('DB_PORT') ?: '3306';
      \$d = getenv('DB_DATABASE') ?: 'eaglepoint';
      \$u = getenv('DB_USERNAME') ?: 'eaglepoint';
      \$w = getenv('DB_PASSWORD') ?: 'secret';
      \$pdo = new PDO('mysql:host=' . \$h . ';port=' . \$p . ';dbname=' . \$d, \$u, \$w);
      \$stmt = \$pdo->query(\"SHOW TABLES LIKE 'jobs'\");
      exit(\$stmt->rowCount() > 0 ? 0 : 1);
    } catch (Throwable \$e) { exit(1); }
  " 2>/dev/null; then
    echo "Worker: database ready."
    break
  fi
  RETRIES=$((RETRIES + 1))
  sleep 2
done

exec "$@"
