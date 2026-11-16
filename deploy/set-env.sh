#!/usr/bin/env bash
#
# Sets public, non-secret settings in the live .env, then re-caches config.
#
#   deploy/set-env.sh KEY=value [KEY=value ...]
#   deploy/set-env.sh CG_SEO_LINKEDIN=https://www.linkedin.com/in/chris-garlick-59a8bb91
#
# Each KEY's existing line (commented out or not) is removed and the new one
# appended. Nothing from the file is printed, only the keys that were set.
# For URLs and labels: secrets go in by hand, not through a command line.
# A value with spaces is written quoted: deploy/set-env.sh 'CG_SEO_DESCRIPTION=One developer ...'

set -euo pipefail

cd "$(dirname "$0")/.."
SERVER="$(cat deploy/.server)"

[[ $# -gt 0 ]] || { echo "Usage: deploy/set-env.sh KEY=value [KEY=value ...]"; exit 1; }

for pair in "$@"; do
    [[ "$pair" =~ ^[A-Z][A-Z0-9_]*=[^\'\"\$\`\\]*$ ]] || { echo "Refusing '$pair': KEY=value, with no quotes, \$, backticks or backslashes."; exit 1; }
done

# The pairs go over as arguments to a fixed script, so a value is never
# parsed as shell on the server. ssh joins its arguments into one command
# line, so each is quoted (%q) to arrive whole, spaces and all.
ssh "$SERVER" "bash -s -- $(printf '%q ' "$@")" <<'REMOTE'
set -euo pipefail
ENV=/var/www/site/.env
for pair in "$@"; do
    key="${pair%%=*}"
    value="${pair#*=}"
    sed -i "/^#\?[[:space:]]*${key}=/d" "$ENV"
    if [[ "$value" == *" "* ]]; then
        printf '%s="%s"\n' "$key" "$value" >> "$ENV"
    else
        printf '%s\n' "$pair" >> "$ENV"
    fi
    echo "set $key"
done
docker exec -u www-data -w /var/www/site site-php php artisan optimize >/dev/null
# opcache.validate_timestamps is off: reload FPM so it reads the new config,
# then drop the cached pages that were rendered with the old one.
docker kill --signal=USR2 site-php >/dev/null
sleep 2
docker exec -u www-data -w /var/www/site site-php php artisan cms:flush-cache >/dev/null
echo "config re-cached, PHP reloaded, page cache flushed"
REMOTE
