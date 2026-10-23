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

# 3. Can the server reach the database? Check fast and explain, instead of hanging for minutes.
set -a
. "$ENV_FILE"
set +a
TOKEN=$(curl -s -m 2 -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 60" || true)
MAC=$(curl -s -m 2 -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/mac || true)
echo "GoalLine check: this server's private address is $(curl -s -m 2 -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/local-ipv4 || echo unknown)"
echo "GoalLine check: this server's network (VPC) is $(curl -s -m 2 -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/network/interfaces/macs/$MAC/vpc-id || echo unknown), range $(curl -s -m 2 -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/network/interfaces/macs/$MAC/vpc-ipv4-cidr-block || echo unknown)"
echo "GoalLine check: this server's security groups are $(curl -s -m 2 -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/network/interfaces/macs/$MAC/security-group-ids | tr '\n' ' ' || echo unknown)"
echo "GoalLine check: the database ${DB_HOST:-(DB_HOST is not set)} is at address $(getent hosts "${DB_HOST:-none}" | awk '{print $1}' || echo unknown)"
if [ -z "${DB_HOST:-}" ]; then
    echo "GoalLine check FAILED: DB_HOST is not set in the environment properties."
    exit 1
fi
if ! timeout 8 bash -c "</dev/tcp/$DB_HOST/${DB_PORT:-3306}" 2>/dev/null; then
    echo "GoalLine check FAILED: nothing answered on ${DB_HOST}:${DB_PORT:-3306} within 8 seconds."
    echo "That means a firewall or network block: the database's security group needs an inbound MySQL rule from this server, and both must be in the same VPC."
    exit 1
fi
echo "GoalLine check: the database answered on port ${DB_PORT:-3306}."
# 4. Database and caches.
chown -R webapp:webapp "$APP/storage" "$APP/bootstrap/cache"
run migrate --force --no-interaction
run config:cache
run route:cache
run view:cache
run event:cache

# 5. The queue worker, kept running by systemd.
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

# 6. The scheduler: reminders, recaps, and challenge wrap-ups, every minute.
echo '* * * * * webapp /usr/local/bin/goalline-artisan schedule:run >> /dev/null 2>&1' > /etc/cron.d/goalline-scheduler
chmod 644 /etc/cron.d/goalline-scheduler
