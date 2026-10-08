#!/usr/bin/env bash
#
# Prepare the box for the Laravel site. Idempotent: safe to run again.
#
# Changes nothing the live site depends on. It installs PHP 8.3 FPM with the
# extensions Laravel needs, git and Composer, clones the site into
# /var/www/site, creates a new `site` database, and installs the PHP, systemd
# and Postgres config, but it does NOT switch nginx, stop Kritano, or start
# the Laravel services. Those are cutover steps (plan section 8), run
# deliberately and in order.
#
#   provision.sh            from deploy/server/ on the box, as root
#
# Assumes Ubuntu with nginx and Postgres 16 already present. The droplet runs
# 24.10, which is past end of life (its packages come from old-releases), and
# no PHP 8.4 build exists for it, so the site runs on the system PHP 8.3.
# composer.json pins the platform to 8.3.11 to match, and Herd serves the site
# on 8.3 locally. Moving to an LTS droplet and PHP 8.4 is planned maintenance.

set -euo pipefail

cd "$(dirname "$0")"
SITE=/var/www/site
REPO=https://github.com/Chrisgarlick/portfolio.git
PHP=8.3

echo "== Packages"
apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq git unzip \
    "php$PHP-fpm" "php$PHP-cli" "php$PHP-pgsql" "php$PHP-intl" "php$PHP-gd" "php$PHP-curl" \
    "php$PHP-xml" "php$PHP-mbstring" "php$PHP-zip" "php$PHP-bcmath" >/dev/null
# The droplet already has Composer in /usr/local/bin; only fall back to apt's.
command -v composer >/dev/null || DEBIAN_FRONTEND=noninteractive apt-get install -y -qq composer >/dev/null

echo "== PHP-FPM pool"
# The default `www` pool would hold its own workers for nothing.
[[ -f "/etc/php/$PHP/fpm/pool.d/www.conf" ]] && mv "/etc/php/$PHP/fpm/pool.d/www.conf" "/etc/php/$PHP/fpm/pool.d/www.conf.disabled"
install -m 644 php/site.conf "/etc/php/$PHP/fpm/pool.d/site.conf"
install -m 644 php/99-site.ini "/etc/php/$PHP/fpm/conf.d/99-site.ini"
systemctl enable --now "php$PHP-fpm" >/dev/null
systemctl reload "php$PHP-fpm"

echo "== The site: one git checkout, owned by www-data"
# www-data's home is /var/www; Composer and git keep their caches there.
mkdir -p /var/www/.cache /var/www/.config
chown www-data:www-data /var/www/.cache /var/www/.config
if [[ ! -d "$SITE/.git" ]]; then
    mkdir -p "$SITE" && chown www-data:www-data "$SITE"
    sudo -u www-data -H git clone --quiet "$REPO" "$SITE"
    echo "   cloned $REPO"
fi
mkdir -p "$SITE/public/media" "$SITE/public/page-cache"
mkdir -p "$SITE"/storage/{app/private,app/public,framework/cache,framework/sessions,framework/views,logs}
chown -R www-data:www-data "$SITE"

echo "== Database"
# A new database for the new site. The old `cms` database is the import
# source and the rollback; it is renamed cms_legacy only after a clean import.
if ! sudo -u postgres psql -Atc "select 1 from pg_roles where rolname='site'" | grep -q 1; then
    DB_PASSWORD="$(openssl rand -hex 24)"
    sudo -u postgres psql -qc "create role site login password '$DB_PASSWORD'"
    echo "DB_PASSWORD=$DB_PASSWORD" > /root/site-db-password
    chmod 600 /root/site-db-password
    echo "   created role 'site'; password in /root/site-db-password"
fi
sudo -u postgres psql -Atc "select 1 from pg_database where datname='site'" | grep -q 1 \
    || sudo -u postgres createdb -O site site
# The import reads the legacy database through its own read-only grant.
sudo -u postgres psql -d cms -qc "grant connect on database cms to site; grant usage on schema public to site; grant select on all tables in schema public to site;" 2>/dev/null || true

echo "== Environment"
if [[ ! -f "$SITE/.env" ]]; then
    install -m 640 -o www-data -g www-data env.production.example "$SITE/.env"
    echo "   wrote $SITE/.env from the template: fill in the secrets before deploying"
fi

echo "== Postgres tuning"
install -m 644 postgres/99-site.conf /etc/postgresql/16/main/conf.d/99-site.conf
echo "   installed; takes effect on the next Postgres restart (shared_buffers needs one)"

echo "== systemd units (installed, not started)"
install -m 644 systemd/site-queue.service /etc/systemd/system/site-queue.service
install -m 644 systemd/site-scheduler.service /etc/systemd/system/site-scheduler.service
install -m 644 systemd/site-scheduler.timer /etc/systemd/system/site-scheduler.timer
systemctl daemon-reload

echo "== nginx (staged, not enabled)"
install -m 644 nginx/chrisgarlick.com.conf /etc/nginx/sites-available/chrisgarlick.com.laravel
[[ -f /etc/nginx/sites-available/chrisgarlick.com.kritano ]] \
    || cp /etc/nginx/sites-available/chrisgarlick.com /etc/nginx/sites-available/chrisgarlick.com.kritano

install -m 755 deploy.sh /usr/local/bin/site-deploy

echo
echo "Provisioned. Nothing live has changed. Next, per the cutover plan:"
echo "  1. fill in $SITE/.env (the first site-deploy generates APP_KEY)"
echo "  2. site-deploy"
echo "  3. sudo -u www-data php $SITE/artisan site:import-legacy --media=/var/www/chrisgarlick/media --verify"
