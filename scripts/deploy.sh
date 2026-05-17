#!/usr/bin/env bash
#
# Server-side deploy script (runs ON the cPanel server, from the app root).
# The GitHub Actions workflow performs `git fetch && git reset --hard origin/main`
# and then invokes this script via `bash scripts/deploy.sh`.
#
# Requires on the server: php 8.3+, composer, node 18+/npm, a writable
# storage/ and bootstrap/cache, and a production .env (never committed).
set -euo pipefail

PHP_BIN="${PHP_BIN:-php}"

echo "→ Deploy started: $(date)"

export COMPOSER_MEMORY_LIMIT=-1

# 1. Backend + frontend build happen WITHOUT maintenance mode so the brief
#    503 window (Kick webhooks must keep getting 200) stays a few seconds.
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build   # vite + wayfinder plugin regenerates resources/js/{actions,routes,wayfinder}

# 2. Short maintenance window only around migrate + cache rebuild.
"$PHP_BIN" artisan down --render="errors::503" --retry=15 || true
trap '"$PHP_BIN" artisan up || true' EXIT

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan storage:link || true
"$PHP_BIN" artisan optimize          # config + route + view + event cache
"$PHP_BIN" artisan queue:restart     # cron-spawned workers pick up new code

"$PHP_BIN" artisan up
trap - EXIT

echo "→ Deploy finished: $(date)"