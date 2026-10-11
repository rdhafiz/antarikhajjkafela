#!/usr/bin/env bash
#
# Activates an uploaded release on the server (run by the GitHub Actions deploy).
#
#   bash deploy/activate.sh <app-dir> <release-name>
#
# Layout on the server:
#   <app-dir>/shared/.env        production settings (created once by hand)
#   <app-dir>/shared/storage     uploads, logs, sessions cache (kept between releases)
#   <app-dir>/releases/<name>    one folder per deploy
#   <app-dir>/current            symlink to the live release
#
# The web root (public_html) is a symlink to <app-dir>/current/public.

set -euo pipefail

APP_DIR="$1"
RELEASE="$2"
RELEASE_DIR="$APP_DIR/releases/$RELEASE"
SHARED="$APP_DIR/shared"
PHP="${PHP_BIN:-php}"
KEEP=5

cd "$RELEASE_DIR"

if [ ! -f "$SHARED/.env" ]; then
    echo "Missing $SHARED/.env: create it first (see docs/DEPLOYMENT.md)." >&2
    exit 1
fi

# Shared files and folders.
mkdir -p "$SHARED/storage/app/public" "$SHARED/storage/app/private" \
         "$SHARED/storage/framework/cache/data" "$SHARED/storage/framework/sessions" \
         "$SHARED/storage/framework/views" "$SHARED/storage/logs"
ln -sfn "$SHARED/.env" .env
rm -rf storage
ln -sfn "$SHARED/storage" storage
ln -sfn "$SHARED/storage/app/public" public/storage

# Database changes, permissions for new modules, caches.
"$PHP" artisan migrate --force
"$PHP" artisan access:sync
"$PHP" artisan optimize

# Go live: switch the symlink in one step.
ln -sfn "$RELEASE_DIR" "$APP_DIR/current.tmp"
mv -Tf "$APP_DIR/current.tmp" "$APP_DIR/current"

# Sizes added to config/media.php are created for existing images.
"$PHP" artisan media:variants --no-interaction || true

# Keep the newest releases for quick rollback.
cd "$APP_DIR/releases"
ls -1t | tail -n +$((KEEP + 1)) | xargs -r rm -rf

echo "Release $RELEASE is live."
