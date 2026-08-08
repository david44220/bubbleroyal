#!/bin/sh

set -eu

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
  attempts=0
  until php /var/www/html/tools/migrate.php; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge "${MIGRATION_RETRIES:-30}" ]; then
      echo "Database migrations did not complete after ${attempts} attempts." >&2
      exit 1
    fi
    sleep 2
  done
fi

exec apache2-foreground
