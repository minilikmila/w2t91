#!/bin/sh
set -e

cd /var/www

# Wait for vendor/autoload.php (app container runs composer install via bind mount)
echo "Worker: waiting for composer install to finish..."
while [ ! -f /var/www/vendor/autoload.php ]; do
  sleep 2
done

# Wait for queue/cache tables to be fully usable before starting the worker.
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
      \$cacheTable = \$pdo->query(\"SHOW TABLES LIKE 'cache'\");
      \$jobsTable = \$pdo->query(\"SHOW TABLES LIKE 'jobs'\");

      if (\$cacheTable->rowCount() === 0 || \$jobsTable->rowCount() === 0) {
        exit(1);
      }

      \$pdo->query(\"SELECT 1 FROM cache LIMIT 1\");
      exit(0);
    } catch (Throwable \$e) { exit(1); }
  " 2>/dev/null; then
    echo "Worker: database ready."
    break
  fi
  RETRIES=$((RETRIES + 1))
  sleep 2
done

exec "$@"
