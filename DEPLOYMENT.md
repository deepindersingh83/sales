# Deployment (CloudPanel / LEMP, PHP 8.4)

The app is a standard Laravel 13 project. Production targets MySQL 8 and
**PHP 8.4+**.

## 1. Get the code + dependencies

```bash
cd /home/<site-user>/htdocs/<domain>
git clone <repo> .            # or pull
composer install --no-dev --optimize-autoloader
npm install && npm run build  # compiles Tailwind/Alpine into public/build
```

## 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```
APP_ENV=production
APP_DEBUG=false            # IMPORTANT: keep false in production
APP_URL=https://<domain>

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<db>
DB_USERNAME=<user>
DB_PASSWORD=<pass>

QUEUE_CONNECTION=database
```

## 3. Writable directories (the #1 cause of a fresh-deploy 500)

Laravel compiles Blade views into `storage/framework/views` at runtime. If the
web/PHP-FPM user cannot write there, PHP 8.4 emits
`tempnam(): file created in the system's temporary directory` and — with
`APP_DEBUG=true` — that surfaces as an HTTP 500.

On CloudPanel the site runs as a per-site system user. Make that user own the
writable paths:

```bash
chown -R <site-user>:<site-user> storage bootstrap/cache
find storage -type d -exec chmod 775 {} \;
find storage -type f -exec chmod 664 {} \;
chmod -R 775 bootstrap/cache
```

## 4. Migrate + cache

```bash
php artisan migrate --force
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache        # requires storage/framework/views to be writable
```

If you ever see the compiled-view / tempnam error, re-check step 3 and run
`php artisan view:clear`.

## 5. Queue worker (required for calculations)

Calculation runs are queued jobs. Run a worker as a supervised process:

```bash
php artisan queue:work --sleep=3 --tries=3
```

Set this up under CloudPanel's Supervisor (or systemd) so it stays running.
Without a worker, calc runs stay in the `queued` state and never complete.

## 6. Web root

Point the site's document root at `public/` (CloudPanel: site's "Root
Directory" → `.../htdocs/<domain>/public`).
