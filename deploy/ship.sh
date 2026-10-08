#!/usr/bin/env bash
#
# Deploy from this Mac. Plan section 9.4, revised 8 October 2026.
#
#   deploy/ship.sh               test, build, commit the build, push, update the server
#   deploy/ship.sh --no-tests    the same without the test suite (not for real releases)
#   deploy/ship.sh --rollback    put the server back on the commit before the last deploy
#
# The server is a git checkout of this repository, and site-deploy on it
# pulls main and installs from the lock files. The front-end build is
# committed (public/build), so the server never runs npm, and Composer there
# only installs what composer.lock names, never resolves anything.
#
# The server's SSH host comes from $SITE_SERVER, or from deploy/.server
# (one line, gitignored), for example: root@203.0.113.10 or an ~/.ssh/config alias.

set -euo pipefail

cd "$(dirname "$0")/.."

SERVER="${SITE_SERVER:-$(cat deploy/.server 2>/dev/null || true)}"
[[ -n "$SERVER" ]] || { echo "Set SITE_SERVER, or put the server's SSH host in deploy/.server."; exit 1; }

if [[ "${1:-}" == "--rollback" ]]; then
    ssh "$SERVER" site-deploy --rollback
    exit 0
fi

[[ "$(git branch --show-current)" == "main" ]] || { echo "Deploy from main."; exit 1; }
[[ -z "$(git status --porcelain)" ]] || { echo "Commit or stash your changes first: the server deploys what is on GitHub."; git status --short; exit 1; }

# cg-cms ships at the commit in composer.lock, not what is in ~/dev/cg-cms.
LOCKED="$(php -r '
    foreach (json_decode(file_get_contents("composer.lock"), true)["packages"] as $p) {
        if ($p["name"] === "chrisgarlick/cg-cms") { echo $p["source"]["reference"]; }
    }')"
REMOTE="$(git ls-remote https://github.com/Chrisgarlick/laravel-cms.git refs/heads/main 2>/dev/null | cut -f1 || true)"
echo "cg-cms: shipping ${LOCKED:0:7}"
if [[ -n "$REMOTE" && "$REMOTE" != "$LOCKED" ]]; then
    echo "  WARNING: GitHub main is ${REMOTE:0:7}. Run 'composer update chrisgarlick/cg-cms' to ship it."
fi
LOCAL_CMS="../../dev/cg-cms"
if [[ -d "$LOCAL_CMS/.git" ]] && [[ -n "$(git -C "$LOCAL_CMS" status --porcelain)" || "$(git -C "$LOCAL_CMS" rev-parse HEAD)" != "$LOCKED" ]]; then
    echo "  WARNING: ~/dev/cg-cms differs from the locked commit; its changes will not ship."
fi

if [[ "${1:-}" != "--no-tests" ]]; then
    echo "Running the test suite"
    php artisan test --compact
fi

echo "Building front-end assets"
npm run build --silent >/dev/null
if [[ -n "$(git status --porcelain public/build)" ]]; then
    git add public/build
    git commit --quiet -m "Build front-end assets"
    echo "  committed the new build"
fi

echo "Pushing"
git push --quiet origin main

echo "Updating the server"
ssh "$SERVER" site-deploy
