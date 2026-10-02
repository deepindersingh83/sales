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

## 6a. Email (required — accounts must verify their address)

Every account must verify its email address before using the app; this stops
someone pre-registering a colleague's address and being granted access when an
admin adds that address. Configure a real mailer in `.env` (`MAIL_MAILER`,
`MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`) — the
default `log` mailer only writes emails to the log.

**Upgrading an existing install:** accounts created before verification was
switched on are unverified, so on their next visit users are asked to click a
verification link (a password reset also verifies). To verify accounts by hand:

```bash
php artisan users:verify someone@company.com   # one account
php artisan users:verify --all                 # every account you trust
```

Only use `--all` if you trust every existing account: it also verifies any
account registered with an address its owner does not control.

## Troubleshooting

### `tempnam(): file created in the system's temporary directory` (HTTP 500)
Blade can't write compiled views because `storage/framework/views` isn't
writable by the PHP-FPM user. Fix step 3 (writable directories), then
`php artisan view:clear`.

### `attempt to write a readonly database` (SQLite, HTTP 500)
Two possibilities:

1. **You're on SQLite in production but it isn't writable.** SQLite needs write
   access to **both** the file and its parent directory (it writes `-wal` /
   `-journal` files alongside):
   ```bash
   chown <site-user>:<site-user> database database/database.sqlite
   chmod 775 database
   chmod 664 database/database.sqlite
   php artisan config:clear
   ```
2. **You should be on MySQL.** Production targets MySQL 8 — set the `DB_*`
   values (step 2), then `php artisan config:clear && php artisan migrate --force`.

Sessions default to the `database` driver, so every request writes to the DB —
a read-only DB fails immediately. (Alternatively set `SESSION_DRIVER=file`, but
the app still needs a writable database for its own data.)

### Changed `.env` but nothing changed
Config may be cached. Run `php artisan config:clear` (or re-run
`php artisan config:cache`).
