# Installation & updates

## Requirements
- PHP **8.4** (8.4.1 or newer) with extensions: pdo_mysql, mbstring, openssl, curl, xml, zip, gd, intl, bcmath, fileinfo, ctype, tokenizer
- MySQL 8+ or MariaDB 10.6+
- A cron job that runs every minute
- Outbound access to your SMTP server / sending APIs / SMS gateways

The web installer checks all of this for you.

## A. Shared hosting (cPanel and similar)

Shared hosting works out of the box: no terminal, Redis or background worker is needed.
The one-minute cron job runs the scheduler **and** sends the queued emails (`QUEUE_CRON_WORKER=true`).

### 1. Build the package (on the development machine)
```bash
npm run build
php artisan app:package
```
This creates `storage/app/packages/sciat-mail-<version>.zip` containing the code, production
dependencies, built assets, a `.env.install` template (new APP_KEY, `APP_INSTALLER=true`) and a safe
root `.htaccess`. On the first visit `.env.install` becomes `.env` and the installer opens; the same
zip is used for updates later.

### 2. Prepare the hosting
1. **Select PHP Version** → **8.4**; tick the extensions above (they are saved per PHP version — re-tick them if you ever switch version).
2. **MySQL Databases** → create a database and user; give the user ALL PRIVILEGES.

### 3. Upload
- **Recommended:** upload the zip next to `public_html`, extract it (folder `sciat-mail`), create a
  subdomain such as `mail.yourcompany.com` and set its **document root** to `sciat-mail/public`.
- **If the document root can't be changed:** turn on *Show Hidden Files* in File Manager, extract the
  zip and move everything inside `sciat-mail/` into `public_html`. The included `.htaccess` sends
  requests to `public/` and blocks `.env`, logs and backups.

### 4. Run the web installer
Open the site. It goes to `/install`:
1. **Server check** — fix anything red, then *Check again*.
2. **Database** — host (usually `localhost`), port 3306, name, user, password. Tables, starter
   templates and warmup strategies are created.
3. **Administrator** — platform name, address and your login.

The installer switches itself off (`APP_INSTALLER=false`) and shows your cron command.

### 5. Cron
cPanel → Cron Jobs → *Once Per Minute*, command:
```
/usr/local/bin/php /home/ACCOUNT/sciat-mail/artisan schedule:run >> /dev/null 2>&1
```
Use your host's PHP path if different (e.g. `/usr/local/bin/ea-php84`). Confirm under
**Settings → Cron & queue** that the scheduler shows *Running*.

### 6. First steps inside the platform
1. Sending → Sending servers → add your SMTP server → **Test**.
2. Settings → System mail → same SMTP account (password resets, notifications).
3. Sending → Sending domains → add the domain, publish SPF/DKIM/DMARC, **Verify**.
4. Settings → Maintenance → **Back up now**.
5. Create a client, import a few addresses, send a test campaign.

### Troubleshooting
| Symptom | Fix |
|---|---|
| 500 error right after upload | PHP version too old, or `storage/` and `bootstrap/cache/` not writable (755/775) |
| SMTP test times out | The host blocks outgoing mail ports — ask support to allow your SMTP server |
| Images missing | Nothing to do: uploads are served by the app when symlinks are not allowed |
| Scheduler "Not running" | Cron missing or wrong PHP path |

## B. VPS (Ubuntu, later)
```bash
apt install nginx mariadb-server redis-server php8.4-fpm php8.4-{mysql,mbstring,xml,curl,zip,gd,intl,bcmath,redis}
git clone <repo> /var/www/sciat-mail && cd /var/www/sciat-mail
composer install --no-dev --optimize-autoloader && npm ci && npm run build
cp .env.example .env && php artisan key:generate   # set DB_*, APP_URL, QUEUE_CONNECTION=redis, CACHE_STORE=redis
php artisan migrate --force && php artisan db:seed --force && php artisan storage:link
```
- Nginx root: `/var/www/sciat-mail/public`; HTTPS via certbot.
- Cron: `* * * * * www-data php /var/www/sciat-mail/artisan schedule:run`
- Supervisor: `php artisan queue:work --tries=3 --timeout=900` (one or more processes); keep `QUEUE_CRON_WORKER=false`.
- First admin: set `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` before `db:seed`, or use the web installer.

## Updating
1. On the development machine: commit, `npm run build`, `php artisan app:package`.
2. In the platform: **Settings → Maintenance → Back up now** and download the backup.
3. Upload the same `sciat-mail-<version>.zip` and extract it over the existing folder. It contains no
   `.env` (only a `.env.install` template used on new installs), so your settings and uploads are kept.
4. A banner appears for administrators: **Settings → Maintenance → Apply update** (runs the
   database migrations and clears caches).

## Backups
- Automatic daily database backup at 02:30 (gzip SQL, newest 7 kept — configurable).
- Manual backups, download and delete under **Settings → Maintenance**.
- Restore: import the `.sql.gz` with phpMyAdmin (or `gunzip -c file | mysql db`).
- Keep copies off the server, and back up `storage/app/public` (uploaded images) with your host's tools.

## Local development
```bash
composer install && npm install && npm run build
php artisan migrate --seed
php artisan serve            # app
php artisan queue:work       # worker
php artisan schedule:work    # scheduler
php artisan test             # 230+ tests (MariaDB database mailplatform_test)
```
