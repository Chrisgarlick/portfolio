#!/usr/bin/env bash
#
# Activate a release on the server. Plan section 9.4.
#
#   deploy.sh /tmp/<id>.tar.gz          unpack, migrate, switch, reload, warm
#   deploy.sh --rollback                point `current` at the previous release
#
# Nothing is compiled or resolved here, so peak memory is a few tens of
# megabytes. Layout:
#
#   /var/www/site/releases/<id>/   one directory per release, kept: last 5
#   /var/www/site/shared/          .env, storage/, public/media, public/page-cache
#   /var/www/site/current          symlink to the live release
#
# Run as root; files end up owned by www-data.

set -euo pipefail

SITE=/var/www/site
RELEASES="$SITE/releases"
SHARED="$SITE/shared"
PHP_FPM=php8.3-fpm
KEEP=5

as_web() { sudo -u www-data "$@"; }
artisan() { as_web php "$1/artisan" "${@:2}"; }

if [[ "${1:-}" == "--rollback" ]]; then
    current="$(readlink -f "$SITE/current")"
    previous="$(ls -1dt "$RELEASES"/*/ | sed 's#/$##' | grep -vx "$current" | head -1)"
    [[ -n "$previous" ]] || { echo "No previous release to roll back to."; exit 1; }
    ln -sfn "$previous" "$SITE/current.tmp" && mv -Tf "$SITE/current.tmp" "$SITE/current"
    systemctl reload "$PHP_FPM"
    artisan "$SITE/current" queue:restart
    echo "Rolled back to $(basename "$previous")"
    exit 0
fi

TARBALL="${1:?Usage: deploy.sh <release.tar.gz> | --rollback}"
ID="$(basename "$TARBALL" .tar.gz)"
RELEASE="$RELEASES/$ID"

[[ -f "$SHARED/.env" ]] || { echo "Missing $SHARED/.env. Run provision.sh first."; exit 1; }

echo "Unpacking $ID"
mkdir -p "$RELEASE"
tar -xzf "$TARBALL" -C "$RELEASE"

# Shared state, linked into the release so it survives every deploy.
ln -sfn "$SHARED/.env" "$RELEASE/.env"
rm -rf "$RELEASE/storage" && ln -sfn "$SHARED/storage" "$RELEASE/storage"
ln -sfn "$SHARED/public/media" "$RELEASE/public/media"
ln -sfn "$SHARED/public/page-cache" "$RELEASE/public/page-cache"
for file in sitemap.xml robots.txt llms.txt; do
    [[ -e "$SHARED/public/$file" ]] && ln -sfn "$SHARED/public/$file" "$RELEASE/public/$file"
done
chown -R www-data:www-data "$RELEASE"

echo "Migrating"
artisan "$RELEASE" migrate --force

echo "Caching config, routes, views and events"
artisan "$RELEASE" optimize

echo "Switching current to $ID"
ln -sfn "$RELEASE" "$SITE/current.tmp" && mv -Tf "$SITE/current.tmp" "$SITE/current"

# opcache.validate_timestamps is off, so PHP-FPM must reload to see new code.
systemctl reload "$PHP_FPM"
artisan "$SITE/current" queue:restart

# Templates may have changed, so cached pages are stale. Flush, then warm at
# concurrency 1: on one vCPU, warming must never compete with visitors.
artisan "$SITE/current" cms:flush
artisan "$SITE/current" cms:seo-files
artisan "$SITE/current" cms:warm --concurrency=1 || echo "Some pages failed to warm; see above."

echo "Pruning old releases"
ls -1dt "$RELEASES"/*/ | tail -n +$((KEEP + 1)) | xargs -r rm -rf

echo "Live: $ID"
