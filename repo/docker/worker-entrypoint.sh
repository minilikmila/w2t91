#!/bin/sh
set -e

cd /var/www

# Wait for the app entrypoint to finish (composer install, migrations, seeding)
# The app entrypoint touches this file after everything succeeds.
echo "Worker: waiting for app entrypoint to complete..."
RETRIES=0
while [ ! -f /var/www/storage/.entrypoint-done ]; do
  RETRIES=$((RETRIES + 1))
  if [ "$RETRIES" -ge 120 ]; then
    echo "Worker: ERROR - app entrypoint did not complete after 240 seconds."
    exit 1
  fi
  sleep 2
done
echo "Worker: app is ready, starting queue worker."

exec "$@"
