# Spice Rules

Laravel app with Blade, Tailwind CSS, DaisyUI, and MySQL in Docker. Login and signup are in place.

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+
- Docker

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Start MySQL:

```bash
docker compose up -d
```

Run migrations and build assets:

```bash
php artisan migrate
npm run build
```

Start the app:

```bash
php artisan serve
```

For live CSS/JS updates, also run `npm run dev` in another terminal.

## Database

Docker Compose exposes MySQL on `127.0.0.1:3306`.

| Setting  | Value        |
|----------|--------------|
| Database | `spice_rules` |
| User     | `spice`       |
| Password | `secret`      |

## GeoGuessr sync

Every 5 minutes Laravel will pull profiles, weekly dailies, and streaks for active GeoGuessr rows that have an `_ncfa`.

On the VPS, run the scheduler every minute:

```bash
* * * * * cd /path/to/Spice-Rules && php artisan schedule:run >> /dev/null 2>&1
```

## Auth routes

- `/register` — create an account
- `/login` — sign in
- `/dashboard` — signed-in home
- `POST /logout` — sign out
