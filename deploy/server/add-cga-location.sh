#!/bin/sh
#
# Adds CG Analytics' /_cg/e location to the live chrisgarlick.com vhost, once.
# Run from the Mac (it reads nothing from the repo on the server):
#
#   ssh -i ~/.ssh/keys/chrisgarlick root@165.227.226.255 sh -s < deploy/server/add-cga-location.sh
#
# The same block is in deploy/server/nginx/chrisgarlick.com.conf. It goes in
# before the favicon location, with a dated backup beside the file; if
# `nginx -t` fails, the backup goes back and nginx is left as it was. Running
# it again changes nothing.
set -eu
F=/etc/nginx/sites-available/chrisgarlick.com.laravel

if grep -q 'location = /_cg/e' "$F"; then
    echo "already there: nothing changed"
    exit 0
fi
grep -q 'location = /favicon.ico' "$F" || { echo "no favicon location in $F to put it before: add it by hand" >&2; exit 1; }

B="$F.before-cga-$(date +%Y%m%d-%H%M%S)"
cp -p "$F" "$B"

awk '
/location = \/favicon.ico/ && !done {
    print "    # CG Analytics'"'"' first-party endpoint (~/dev/chris/analytics, option A of"
    print "    # deploy/nginx-site-snippet.conf): straight to the daemon on this server,"
    print "    # which trusts X-Real-IP from this loopback hop and checks the Origin."
    print "    # Never logged: access_log off keeps visitors'"'"' IPs out of Sentry'"'"'s logs."
    print "    location = /_cg/e {"
    print "        access_log off;"
    print "        client_max_body_size 4k;"
    print "        proxy_http_version 1.1;"
    print "        proxy_set_header Host $host;"
    print "        proxy_set_header X-Real-IP $remote_addr;"
    print "        proxy_set_header X-Forwarded-For \"\";"
    print "        proxy_set_header Cookie \"\";"
    print "        proxy_read_timeout 5s;"
    print "        proxy_pass http://127.0.0.1:9410/e;"
    print "    }"
    print ""
    done = 1
}
{ print }
' "$B" > "$F"

if nginx -t 2>/dev/null; then
    systemctl reload nginx
    echo "added /_cg/e to $F and reloaded nginx (backup: $B)"
else
    cp -p "$B" "$F"
    nginx -t || true
    echo "nginx -t failed: put the backup back, nothing changed" >&2
    exit 1
fi
