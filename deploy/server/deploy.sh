#!/usr/bin/env bash
#
# Update the site in place. Plan section 9.4, revised 8 October 2026.
#
#   site-deploy              pull main, install, migrate, reload, warm
#   site-deploy --rollback   return to the commit before the last deploy
#
# The site is one folder, /var/www/site: a git checkout of
# Chrisgarlick/portfolio, owned by www-data. No releases directory and no
# symlinks. Two things keep the 1GB box safe:
#
#   - The front-end build (public/build) is committed, so npm never runs here.
#   - `composer install` only installs the versions in composer.lock. It is
#     resolving new versions (`composer update`) that needs 500MB or more, and
#     that only ever happens on the Mac.
#
# Visitors keep getting pages throughout: nginx serves the page cache straight
# from disk, so maintenance mode only affects uncached pages and the admin.
#
# Run as root, normally through deploy/ship.sh on the Mac.

set -euo pipefail

SITE=/var/www/site
PHP_FPM=php8.3-fpm
BRANCH=main
PREVIOUS_FILE="$SITE/storage/app/deploy-previous"

as_web() { sudo -u www-data -H "$@"; }
artisan() { as_web php "$SITE/artisan" "$@"; }

cd "$SITE"
[[ -f .env ]] || { echo "Missing $SITE/.env. Run provision.sh and fill it in first."; exit 1; }

current="$(as_web git rev-parse HEAD)"

# The very first deploy has no vendor/ yet, so artisan cannot run until
# Composer has, and no app key until one is generated.
if [[ ! -f vendor/autoload.php ]]; then
    echo "First deploy: installing dependencies"
    as_web composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --quiet
fi
# Only a real key counts: a blank value, or one with just a comment after
# it, still needs generating.
if ! grep -qE '^APP_KEY=base64:' .env; then
    artisan key:generate --force
fi

if [[ "${1:-}" == "--rollback" ]]; then
    [[ -s "$PREVIOUS_FILE" ]] || { echo "No previous deploy recorded to roll back to."; exit 1; }
    target="$(cat "$PREVIOUS_FILE")"
    echo "Rolling back from ${current:0:7} to ${target:0:7}"
else
    as_web git fetch --quiet origin "$BRANCH"
    target="$(as_web git rev-parse "origin/$BRANCH")"

    if [[ "$target" == "$current" ]]; then
        echo "Already on ${current:0:7}; reinstalling and reloading anyway."
    else
        echo "Deploying ${current:0:7} -> ${target:0:7}"
        as_web git --no-pager log --oneline "$current..$target" | sed 's/^/  /'
    fi
fi

# If anything below fails, the site stays in maintenance mode on purpose:
# half-updated code is worse than a holding page. Fix forward, or roll back.
trap 'echo; echo "Deploy FAILED. The site is in maintenance mode (cached pages are still served)."; echo "Roll back with: site-deploy --rollback"' ERR

artisan down --retry=15 >/dev/null

as_web git reset --quiet --hard "$target"
[[ "$target" != "$current" ]] && echo "$current" > "$PREVIOUS_FILE" && chown www-data:www-data "$PREVIOUS_FILE"

echo "Installing PHP dependencies (from composer.lock, nothing resolved)"
as_web composer install --no-dev --no-interaction --no-progress --prefer-dist \
    --optimize-autoloader --classmap-authoritative --quiet

echo "Publishing the admin assets, migrating, caching"
artisan vendor:publish --tag=cg-cms-assets --force >/dev/null
artisan migrate --force
artisan optimize >/dev/null

# opcache.validate_timestamps is off, so PHP-FPM must reload to see new code.
systemctl reload "$PHP_FPM"
artisan queue:restart >/dev/null
artisan up >/dev/null
trap - ERR

# Templates may have changed, so cached pages are stale. Flush, then warm at
# concurrency 1: on one vCPU, warming must never compete with visitors.
artisan cms:flush-cache >/dev/null
artisan cms:seo-files >/dev/null
artisan cms:warm --concurrency=1 >/dev/null || echo "Some pages failed to warm; they render on their first visit."

echo "Live: $(as_web git --no-pager log -1 --format='%h %s')"
