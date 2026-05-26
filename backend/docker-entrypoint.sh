#!/bin/sh
# Recreate and fix var/ permissions on every container start.
# Required because the docker-compose bind mount (./backend:/var/www/html)
# overlays the var/ directory that was created during the image build,
# so permissions must be re-applied at runtime.
set -e

mkdir -p \
    var/cache/dev \
    var/cache/test \
    var/cache/prod \
    var/cache/doctrine/odm/mongodb/Hydrators \
    var/cache/doctrine/odm/mongodb/Proxies \
    var/log

chown -R www-data:www-data var 2>/dev/null || true
chmod -R 775 var

exec "$@"
