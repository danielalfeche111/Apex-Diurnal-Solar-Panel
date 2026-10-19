#!/bin/sh
set -eu

if [ ! -s /import/backup.sql ]; then
    echo 'Private backup is missing: var/backups/solar_db-portable.sql' >&2
    exit 1
fi

export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
tables="$(mysql -h database -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA = 'solar_db' AND TABLE_TYPE = 'BASE TABLE'")"
if [ "$tables" != 0 ]; then
    echo "Import refused: solar_db already contains $tables tables. Existing records were not changed." >&2
    exit 1
fi

mysql -h database -u root solar_db < /import/backup.sql

tables="$(mysql -h database -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA = 'solar_db' AND TABLE_TYPE = 'BASE TABLE'")"
triggers="$(mysql -h database -u root -N -e "SELECT COUNT(*) FROM information_schema.triggers WHERE TRIGGER_SCHEMA = 'solar_db'")"
records=0
for table in admin_users cart_items carts commercial_leads inventory inventory_transactions order_items order_status_history orders products quote_requests service_bookings user_carts users; do
    count="$(mysql -h database -u root -N solar_db -e "SELECT COUNT(*) FROM \`$table\`")"
    records=$((records + count))
done

if [ "$tables" -ne 14 ] || [ "$records" -ne 504 ] || [ "$triggers" -ne 2 ]; then
    echo "Import verification failed: $tables tables, $records records, $triggers triggers (expected 14, 504, 2)." >&2
    exit 1
fi

echo "Imported and verified $tables tables, $records records and $triggers triggers in the Docker MySQL volume."
