#!/usr/bin/env bash
#
# Phase 0 gate benchmark.
#
# Run this ON THE TARGET BOX, not on a laptop. An M-series Mac flatters every
# number below and tells you nothing about a shared vCPU on a 1GB droplet.
#
#   Gate (laravel_cms_plan.md section 10):
#     cached page TTFB   under   5 ms
#     cold render TTFB   under  40 ms
#     cached throughput  over 1000 rps on 1 vCPU
#     cold throughput    over   25 rps on 1 vCPU
#     peak RSS           under 700 MB of 1024 MB
#
# IMPORTANT: the "cached" number is only meaningful once nginx is configured
# with the try_files page-cache rule from plan section 9.3. Without it every
# request still boots PHP, which merely finds the cache already written. This
# script measures the disk-served path separately so the difference is visible
# even on a dev machine that has no such rule.
#
# Usage: bench/phase0.sh [base-url]
#
set -uo pipefail

BASE="${1:-http://portfolio.test}"
SLUG="the-agency-workflows-worth-automating-first-part-1"
DETAIL="${BASE}/article/${SLUG}"
INDEX="${BASE}/article"
CACHE_DIR="${CG_PAGE_CACHE_PATH:-page-cache}"

# The cached file as nginx will serve it: straight off disk, no PHP.
STATIC_DETAIL="${BASE}/${CACHE_DIR}/article/${SLUG}/index.html"
STATIC_INDEX="${BASE}/${CACHE_DIR}/article/index.html"

command -v ab >/dev/null 2>&1 || { echo "apachebench (ab) not found: apt-get install apache2-utils"; exit 1; }

FILTER='Requests per second|Time per request|Failed requests|Non-2xx'

hr() { printf '%.0s-' $(seq 72); echo; }

ms() { awk -v t="$1" 'BEGIN{printf "%.2f", t*1000}'; }

# Best-of-N TTFB. Reports the floor the architecture allows; throughput below
# reports what it sustains under concurrency.
ttfb() {
  local url="$1" label="$2" n="${3:-20}" best=999 t
  for _ in $(seq "$n"); do
    t=$(curl -s -o /dev/null -w '%{time_starttransfer}' "$url")
    if awk -v a="$t" -v b="$best" 'BEGIN{exit !(a<b)}'; then best="$t"; fi
  done
  printf '  %-38s %8s ms\n' "$label" "$(ms "$best")"
}

throughput() {
  ab -q -n "$2" -c "$3" "$1" 2>/dev/null | grep -E "$FILTER" | sed 's/^/    /'
}

# Compressed transfer size. No --compressed: that decompresses and reports the
# original size, which is not what goes over the wire.
gzip_bytes() {
  curl -s -H 'Accept-Encoding: gzip' -o /dev/null -w '%{size_download}' "$1"
}

echo
echo "Phase 0 gate  ${BASE}"
hr

echo "warming..."
curl -s -o /dev/null "$INDEX"
curl -s -o /dev/null "$DETAIL"

echo
echo "1. DISK-SERVED (what nginx does in production: no PHP at all)"
if curl -s -o /dev/null -w '%{http_code}' "$STATIC_DETAIL" | grep -q 200; then
  ttfb "$STATIC_DETAIL" "detail TTFB (best of 20)"
  ttfb "$STATIC_INDEX"  "index TTFB  (best of 20)"
  echo
  echo "  throughput, 20 concurrent:"
  throughput "$STATIC_DETAIL" 2000 20
else
  echo "  cached file not reachable at ${STATIC_DETAIL}"
  echo "  run: php artisan cms:warm"
fi

echo
hr
echo "2. THROUGH PHP, CACHE ALREADY WRITTEN"
echo "   (what you get WITHOUT the nginx try_files rule: the cache is written"
echo "    but every request still boots Laravel. Compare with section 1.)"
ttfb "$DETAIL" "detail TTFB (best of 20)"
echo
echo "  throughput, 20 concurrent:"
throughput "$DETAIL" 500 20

echo
hr
echo "3. COLD RENDER (cache purged before each request)"
cold_best=999
for _ in $(seq 10); do
  php artisan cms:flush-cache >/dev/null 2>&1
  t=$(curl -s -o /dev/null -w '%{time_starttransfer}' "$DETAIL")
  if awk -v a="$t" -v b="$cold_best" 'BEGIN{exit !(a<b)}'; then cold_best="$t"; fi
done
printf '  %-38s %8s ms\n' "detail TTFB (best of 10)" "$(ms "$cold_best")"

echo
echo "  throughput, caching off, 4 concurrent (matches pm.max_children):"
CG_PAGE_CACHE_ENABLED=false throughput "$DETAIL" 200 4

echo
hr
echo "4. PAYLOAD"
php artisan cms:warm >/dev/null 2>&1
for url in "$INDEX" "$DETAIL"; do
  raw=$(curl -s "$url" | wc -c | tr -d ' ')
  gz=$(gzip_bytes "$url")
  printf '  %-52s %7s B raw   %7s B gzip\n' "${url#"$BASE"}" "$raw" "$gz"
done

echo
hr
echo "5. MEMORY"
if command -v free >/dev/null 2>&1; then
  free -m | sed 's/^/  /'
  echo
  echo "  top consumers:"
  ps -eo rss,comm --sort=-rss 2>/dev/null | sed -n '2,9p' \
    | awk '{printf "    %8.1f MB  %s\n", $1/1024, $2}'
else
  echo "  free(1) unavailable on this platform."
  echo "  This is precisely why the gate must be run on the droplet."
fi

echo
hr
printf '%s\n' \
  'GATE' \
  '  cached TTFB       < 5 ms      (section 1, needs the nginx try_files rule)' \
  '  cold TTFB         < 40 ms     (section 3)' \
  '  cached throughput > 1000 rps  (section 1)' \
  '  cold throughput   > 25 rps    (section 3)' \
  '  peak RSS          < 700 MB    (section 5)' \
  '' \
  'Miss any of these on the target box and the storage or caching design' \
  'changes now, per Phase 0 of laravel_cms_plan.md, not in week six.'
echo
