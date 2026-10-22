#!/bin/bash
# Runs on the server after every deploy (and, as a copy in confighooks, after every settings change):
# saves the environment for command-line tasks, migrates the database, caches config,
# and (re)starts the queue worker and the scheduler.
set -euo pipefail

APP=/var/app/current
ENV_FILE=/etc/goalline/env

# 1. The Elastic Beanstalk environment properties, as a shell file only root and the web app can read.
#    Command-line tasks (the queue worker, the scheduler, migrations) don't get them otherwise.
mkdir -p /etc/goalline
/opt/elasticbeanstalk/bin/get-config environment | php "$APP/.platform/env-to-shell.php" > "$ENV_FILE.tmp"
chown root:webapp "$ENV_FILE.tmp"
chmod 640 "$ENV_FILE.tmp"
mv "$ENV_FILE.tmp" "$ENV_FILE"

# 2. A wrapper that runs artisan as the web app user with those settings.
cat > /usr/local/bin/goalline-artisan <<'WRAPPER'
#!/bin/bash
set -a
. /etc/goalline/env
set +a
cd /var/app/current
exec php artisan "$@"
WRAPPER
chmod 755 /usr/local/bin/goalline-artisan

run() { runuser -u webapp -- /usr/local/bin/goalline-artisan "$@"; }

# 3. Database and caches.
chown -R webapp:webapp "$APP/storage" "$APP/bootstrap/cache"
run migrate --force --no-interaction
run config:cache
run route:cache
run view:cache
run event:cache

# 4. The queue worker, kept running by systemd.
cat > /etc/systemd/system/goalline-queue.service <<'UNIT'
[Unit]
Description=GoalLine queue worker
After=network.target

[Service]
User=webapp
Group=webapp
Restart=always
RestartSec=5
ExecStart=/usr/local/bin/goalline-artisan queue:work --tries=1 --timeout=900 --sleep=3 --max-time=3600 --memory=192

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable goalline-queue
systemctl restart goalline-queue

# 5. The scheduler: reminders, recaps, and challenge wrap-ups, every minute.
echo '* * * * * webapp /usr/local/bin/goalline-artisan schedule:run >> /dev/null 2>&1' > /etc/cron.d/goalline-scheduler
chmod 644 /etc/cron.d/goalline-scheduler
