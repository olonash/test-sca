#!/bin/sh

set -eu

TZ=${TZ:-Africa/Nairobi}
export TZ
php -r 'new DateTimeZone($argv[1]);' "$TZ"

printf 'SHELL=/bin/sh\nPATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\nCRON_TZ=%s\nTZ=%s\n0 0 * * * root cd /app && /usr/local/bin/php /app/command/export-database.php >> /proc/1/fd/1 2>&1\n' \
    "$TZ" "$TZ" > /etc/cron.d/database-export
chmod 0644 /etc/cron.d/database-export

exec cron -f -L 15