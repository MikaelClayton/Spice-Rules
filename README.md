# Spice Rules

Laravel app with Blade, Tailwind CSS, DaisyUI, and MySQL in Docker. Login and signup are in place.

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+
- Docker

## Run locally

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
docker compose up -d
php artisan migrate
npm run build
php artisan serve
```

The app is at [http://localhost:8000](http://localhost:8000).

For live CSS/JS updates, run this in another terminal:

```bash
npm run dev
```

Or start PHP, the queue, logs, and Vite together:

```bash
composer run dev
```

## GeoGuessr sync

Pull profiles, weekly dailies, and streaks for active players that have an `_ncfa` cookie:

```bash
php artisan geoguessr:sync
```

If today's daily is already saved with rounds, that profile is skipped. Refresh anyway with:

```bash
php artisan geoguessr:sync --force
```

On the VPS this also runs every 30 minutes via the Laravel scheduler. The scheduler itself is triggered every minute:

```bash
* * * * * cd /var/www/spice-rules && php artisan schedule:run >> /dev/null 2>&1
```

## Database

Docker Compose exposes MySQL on `127.0.0.1:3306`.

| Setting  | Value         |
|----------|---------------|
| Database | `spice_rules` |
| User     | `spice`       |
| Password | `secret`      |

## Auth routes

- `/register` — create an account
- `/login` — sign in
- `/dashboard` — signed-in home
- `POST /logout` — sign out
