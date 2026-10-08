#!/bin/sh
set -eu
cd /app
lock=$(sha256sum package-lock.json | cut -d' ' -f1)
if [ ! -f node_modules/.rrhh-lock ] || [ "$(cat node_modules/.rrhh-lock)" != "$lock" ]; then
    npm ci
    printf '%s\n' "$lock" > node_modules/.rrhh-lock
fi
exec "$@"
