#!/usr/bin/env bash
set -euo pipefail

# Spice Rules production deploy. Source of truth for "please deploy".
# Host: spice-rules.co.za  Path: /var/www/spice-rules
# Never overwrite .env, vendor, or uploaded files. Never use rsync --delete.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/id_rsa}"
SSH_HOST="${SSH_HOST:-root@102.214.9.187}"
REMOTE_PATH="${REMOTE_PATH:-/var/www/spice-rules}"
SSH=(ssh -o BatchMode=yes -o IdentitiesOnly=yes -i "$SSH_KEY")

cd "$ROOT"

if [[ "${SKIP_BUILD:-}" != "1" ]]; then
    npm run build
fi

rsync -az \
    --exclude '.env' \
    --exclude '.env.*' \
    --exclude 'vendor' \
    --exclude 'node_modules' \
    --exclude '.git' \
    --exclude '.idea' \
    --exclude '.claude' \
    --exclude '.junie' \
    --exclude '.ai' \
    --exclude '.cursor' \
    --exclude 'bootstrap/cache' \
    --exclude 'storage/logs' \
    --exclude 'storage/framework/cache' \
    --exclude 'storage/framework/sessions' \
    --exclude 'storage/framework/views' \
    --exclude 'storage/framework/testing' \
    --exclude 'storage/app/public' \
    --exclude 'storage/app/private' \
    --exclude 'public/storage' \
    --exclude 'public/hot' \
    --exclude 'test.json' \
    --exclude 'fit-ish-userid-charles.txt' \
    --exclude '*firebase-adminsdk*.json' \
    -e "ssh -o BatchMode=yes -o IdentitiesOnly=yes -i ${SSH_KEY}" \
    "$ROOT/" \
    "${SSH_HOST}:${REMOTE_PATH}/"

"${SSH[@]}" "$SSH_HOST" "bash -s" <<'REMOTE'
set -euo pipefail
cd /var/www/spice-rules

if [ -L public/storage ]; then
    case "$(readlink public/storage)" in
        /Users/*)
            rm public/storage
            ;;
    esac
fi

rm -f public/hot
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    bootstrap/cache/config.php bootstrap/cache/routes-v7.php \
    bootstrap/cache/events.php

export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force --no-interaction
php artisan spirdle:import-words --no-interaction
php artisan spirdle:open-daily --no-interaction || true
php artisan storage:link --no-interaction || true

chown -R www-data:www-data /var/www/spice-rules
chmod -R ug+rwx storage bootstrap/cache

php artisan optimize --no-interaction

php artisan tinker --execute '
$disk = Illuminate\Support\Facades\Storage::disk("public");
$store = app(App\Services\FitIsh\StoreFitIshWorkoutLogo::class);
foreach (App\Models\FitIshWorkout::query()->whereNotNull("logo_url")->orderBy("id")->get() as $workout) {
    if (filled($workout->logo_path) && $disk->exists($workout->logo_path)) {
        continue;
    }
    $store->handle($workout, $workout->logo_url);
}
'

systemctl reload php8.5-fpm
systemctl reload nginx
REMOTE

curl -fsS -o /dev/null -w "site %{http_code}\n" https://spice-rules.co.za/
echo "deployed ${SSH_HOST}:${REMOTE_PATH}"
