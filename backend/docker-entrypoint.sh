#!/bin/sh
set -e

# Fix permissions on Docker-mounted volumes (daemon creates them as root). Only mount points:
# the rootfs is read-only in prod and only backend-file mounts the uploads volume, so anything
# that is not a mount is either absent or unwritable — and either way not ours to touch.
for dir in /app/public/uploads /app/var; do
    if mountpoint -q "$dir"; then
        chown -R appuser:appuser "$dir"
    fi
done

gosu appuser php bin/console cache:clear

exec gosu appuser "$@"
