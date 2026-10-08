#!/usr/bin/env bash
#
# Build a release tarball on this machine. Plan section 9.4.
#
# The server never runs Composer or npm: Composer's resolver peaks over 500MB
# and a Vite build is worse, either of which on a 1GB box means swapping hard
# or the OOM killer. Everything is resolved and compiled here, and the server
# only unpacks, links, migrates and reloads.
#
#   deploy/build-release.sh            -> build/releases/<id>.tar.gz
#   deploy/build-release.sh --no-tests -> skip the test suite (not for real releases)
#
# The release id is the UTC timestamp. When the project is in git, it becomes
# the short commit hash instead.

set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$(pwd)"

ID="$(date -u +%Y%m%d%H%M%S)"
if git -C "$ROOT" rev-parse --short HEAD >/dev/null 2>&1; then
    ID="$(git -C "$ROOT" rev-parse --short HEAD)"
fi

OUT="$ROOT/build/releases"
STAGE="$(mktemp -d)/release"
mkdir -p "$OUT" "$STAGE"
trap 'rm -rf "$(dirname "$STAGE")"' EXIT

# cg-cms installs from GitHub (Chrisgarlick/laravel-cms), pinned to the commit
# in composer.lock. Say so loudly when that is not the latest pushed commit,
# or when local changes to the package have not been pushed: the release
# ships the locked commit, not what is in ~/dev/cg-cms.
LOCKED="$(php -r '
    foreach (json_decode(file_get_contents("composer.lock"), true)["packages"] as $p) {
        if ($p["name"] === "chrisgarlick/cg-cms") { echo $p["source"]["reference"]; }
    }')"
REMOTE="$(git ls-remote https://github.com/Chrisgarlick/laravel-cms.git refs/heads/main 2>/dev/null | cut -f1 || true)"
echo "cg-cms: shipping ${LOCKED:0:7}"
if [[ -n "$REMOTE" && "$REMOTE" != "$LOCKED" ]]; then
    echo "  WARNING: GitHub main is ${REMOTE:0:7}. Run 'composer update chrisgarlick/cg-cms' to ship it."
fi
LOCAL_CMS="$ROOT/../../dev/cg-cms"
if [[ -d "$LOCAL_CMS/.git" ]] && [[ -n "$(git -C "$LOCAL_CMS" status --porcelain)" || "$(git -C "$LOCAL_CMS" rev-parse HEAD)" != "$LOCKED" ]]; then
    echo "  WARNING: ~/dev/cg-cms differs from the locked commit; its changes will not ship."
fi

if [[ "${1:-}" != "--no-tests" ]]; then
    echo "Running the test suite"
    php artisan test --compact
fi

echo "Building front-end assets"
npm run build --silent >/dev/null

echo "Staging the application"
rsync -a --delete \
    --exclude='/.git' --exclude='/.env' --exclude='/node_modules' --exclude='/vendor' \
    --exclude='/build' --exclude='/tests' --exclude='/storage/*' \
    --exclude='public/page-cache*' --exclude='public/media*' --exclude='public/hot' \
    --exclude='public/vendor' --exclude='.claude' --exclude='/*.md' \
    --exclude='bootstrap/cache/*.php' \
    "$ROOT/" "$STAGE/"

# Laravel's post-install package discovery needs a writable storage skeleton
# and must not see this machine's cached provider list, which names dev-only
# packages (Boost, Pail) that a --no-dev install does not have.
mkdir -p "$STAGE"/storage/{app/private,framework/cache,framework/sessions,framework/views,logs} "$STAGE/bootstrap/cache"

echo "Installing production dependencies"
(cd "$STAGE" && composer install \
    --no-dev --no-interaction --no-progress --prefer-dist \
    --optimize-autoloader --classmap-authoritative >/dev/null)

# Every class the code imports must exist without dev dependencies. A package
# that is only present locally as somebody else's dev dependency works in
# every test and fails in production: symfony/yaml did exactly that to /audit.
echo "Checking for classes a production install lacks"
grep -rhoE "^use [A-Z][A-Za-z0-9_\\\\]+" "$STAGE/app" "$STAGE/config" "$STAGE/routes" "$STAGE/bootstrap" \
    "$STAGE/vendor/chrisgarlick/cg-cms/src" | sed 's/^use //' | sort -u > "$(dirname "$STAGE")/uses.txt"
php -r '
    require $argv[1]."/vendor/autoload.php";
    $missing = [];
    foreach (file($argv[2], FILE_IGNORE_NEW_LINES) as $class) {
        if (preg_match("/^(App|Cg|Tests)\\\\/", $class)) { continue; }
        if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class) && ! function_exists($class)) {
            $missing[] = $class;
        }
    }
    if ($missing !== []) { fwrite(STDERR, "Missing without dev dependencies:\n  ".implode("\n  ", $missing)."\n"); exit(1); }
' "$STAGE" "$(dirname "$STAGE")/uses.txt"

# The admin bundle, published as the server would, without the server
# needing to do it.
mkdir -p "$STAGE/public/vendor/cg-cms"
cp -R "$STAGE/vendor/chrisgarlick/cg-cms/dist/." "$STAGE/public/vendor/cg-cms/"

# storage/ comes from shared/ on the server; ship it empty.
rm -rf "$STAGE/storage" && mkdir -p "$STAGE/storage"

echo "$ID" > "$STAGE/RELEASE"

tar -czf "$OUT/$ID.tar.gz" -C "$STAGE" .
echo "Built $OUT/$ID.tar.gz ($(du -h "$OUT/$ID.tar.gz" | cut -f1))"
