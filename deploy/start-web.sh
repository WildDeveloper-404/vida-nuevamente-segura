#!/bin/bash
set -euo pipefail

a2dismod -q mpm_event mpm_worker
a2enmod -q mpm_prefork

if [[ "${APP_ENV:-}" != production ]]; then
    exec apache2-foreground
fi

apache2-foreground &
web_pid=$!
runuser -u www-data -- php artisan schedule:work --no-interaction &
scheduler_pid=$!

stop_services() {
    kill -TERM "$web_pid" "$scheduler_pid" 2>/dev/null || true
    wait "$web_pid" "$scheduler_pid" 2>/dev/null || true
}
trap 'stop_services; exit 0' TERM INT

# End the container when either process exits so Railway can restart both.
wait -n "$web_pid" "$scheduler_pid" || true
stop_services
exit 1
