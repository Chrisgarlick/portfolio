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
PACKAGE="$(cd "$ROOT/../../dev/cg-cms" && pwd)"

ID="$(date -u +%Y%m%d%H%M%S)"
if git -C "$ROOT" rev-parse --short HEAD >/dev/null 2>&1; then
    ID="$(git -C "$ROOT" rev-parse --short HEAD)"
fi

OUT="$ROOT/build/releases"
STAGE="$(mktemp -d)/release"
mkdir -p "$OUT" "$STAGE"
trap 'rm -rf "$(dirname "$STAGE")"' EXIT

if [[ "${1:-}" != "--no-tests" ]]; then
    echo "Running the test suite"
    php artisan test --compact
    (cd "$PACKAGE" && npm run check --silent)
fi

echo "Building front-end assets"
npm run build --silent >/dev/null
(cd "$PACKAGE" && npm run build --silent >/dev/null)

echo "Staging the application"
rsync -a --delete \
    --exclude='/.git' --exclude='/.env' --exclude='/node_modules' --exclude='/vendor' \
    --exclude='/build' --exclude='/tests' --exclude='/storage/*' \
    --exclude='public/page-cache*' --exclude='public/media*' --exclude='public/hot' \
    --exclude='public/vendor' --exclude='.claude' --exclude='/*.md' \
    --exclude='bootstrap/cache/*.php' \
    "$ROOT/" "$STAGE/"

# The package, trimmed to what the server runs. Composer would otherwise copy
# its node_modules (over 100MB) into vendor/ before anything could remove it.
PKG_STAGE="$(dirname "$STAGE")/cg-cms"
rsync -a --exclude='/node_modules' --exclude='/resources/js' --exclude='/scripts' \
    --exclude='/package.json' --exclude='/package-lock.json' --exclude='/tsconfig.json' \
    --exclude='/vite.admin.config.ts' --exclude='/.git' --exclude='/vendor' --exclude='/tests' \
    "$PACKAGE/" "$PKG_STAGE/"

# The package is a path repository relative to this checkout. Point it at the
# trimmed copy, and mirror it (copy, not symlink) so the tarball carries it.
php -r '
    $file = $argv[1];
    $json = json_decode(file_get_contents($file), true);
    foreach ($json["repositories"] as &$repo) {
        if (($repo["name"] ?? "") === "cg-cms") {
            $repo["url"] = $argv[2];
            $repo["options"] = ["symlink" => false];
        }
    }
    file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
' "$STAGE/composer.json" "$PKG_STAGE"

# The lock file records the same relative path for the package's dist.
php -r '
    $file = $argv[1];
    $lock = json_decode(file_get_contents($file), true);
    foreach (["packages", "packages-dev"] as $section) {
        foreach ($lock[$section] ?? [] as $i => $package) {
            if ($package["name"] === "chrisgarlick/cg-cms") {
                $lock[$section][$i]["dist"]["url"] = $argv[2];
                $lock[$section][$i]["transport-options"] = ["symlink" => false, "relative" => false];
            }
        }
    }
    file_put_contents($file, json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
' "$STAGE/composer.lock" "$PKG_STAGE"

# Laravel's post-install package discovery needs a writable storage skeleton
# and must not see this machine's cached provider list, which names dev-only
# packages (Boost, Pail) that a --no-dev install does not have.
mkdir -p "$STAGE"/storage/{app/private,framework/cache,framework/sessions,framework/views,logs} "$STAGE/bootstrap/cache"

echo "Installing production dependencies"
(cd "$STAGE" && COMPOSER_MIRROR_PATH_REPOS=1 composer install \
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
cp -R "$PACKAGE/dist/." "$STAGE/public/vendor/cg-cms/"

# storage/ comes from shared/ on the server; ship it empty.
rm -rf "$STAGE/storage" && mkdir -p "$STAGE/storage"

echo "$ID" > "$STAGE/RELEASE"

tar -czf "$OUT/$ID.tar.gz" -C "$STAGE" .
echo "Built $OUT/$ID.tar.gz ($(du -h "$OUT/$ID.tar.gz" | cut -f1))"
