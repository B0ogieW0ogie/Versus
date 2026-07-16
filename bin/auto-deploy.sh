#!/usr/bin/env bash
set -euo pipefail

REPO=/home/user/versus
LOG="$REPO/storage/logs/deploy.log"

cd "$REPO"

BEFORE=$(git rev-parse HEAD)
git fetch --quiet origin main
AFTER=$(git rev-parse origin/main)

if [ "$BEFORE" = "$AFTER" ]; then
    exit 0
fi

{
    echo "=== $(date -Is) pull $BEFORE → $AFTER ==="
    git merge --ff-only origin/main

    CHANGED=$(git diff --name-only "$BEFORE" "$AFTER")

    if echo "$CHANGED" | grep -qx "composer.lock"; then
        echo "→ composer install"
        docker compose run --rm -T workspace composer install --no-dev --optimize-autoloader --no-interaction
    fi

    if echo "$CHANGED" | grep -qE "^(resources|package-lock\.json|vite\.config\.js|tailwind\.config\.js|postcss\.config\.js)"; then
        echo "→ npm run build"
        docker compose run --rm -T workspace npm run build
    fi

    if echo "$CHANGED" | grep -q "^database/migrations/"; then
        echo "→ migrate"
        docker compose exec -T php php artisan migrate --force --no-interaction
    fi

    if echo "$CHANGED" | grep -qE "^(config|routes|app)/"; then
        echo "→ optimize:clear"
        docker compose exec -T php php artisan optimize:clear >/dev/null
        echo "→ queue:restart"
        docker compose exec -T php php artisan queue:restart >/dev/null
    fi

    if echo "$CHANGED" | grep -qx "docker-compose.yml"; then
        echo "→ compose up -d"
        docker compose up -d --remove-orphans
    fi

    echo "=== done ==="
} >> "$LOG" 2>&1
