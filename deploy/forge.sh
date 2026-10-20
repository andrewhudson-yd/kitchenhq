#!/usr/bin/env bash
set -euo pipefail
: "${SITE_DIRECTORY:?Set SITE_DIRECTORY to the Forge site directory}"
cd "$SITE_DIRECTORY"
git pull origin "${FORGE_SITE_BRANCH:-main}"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
"${FORGE_PHP:-php}" artisan migrate --force
"${FORGE_PHP:-php}" artisan optimize
