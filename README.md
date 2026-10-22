# SCIAT Mail

Self-hosted email and SMS marketing platform for running client campaigns: lists, signup forms,
websites, email campaigns with a drag-and-drop builder, automations, bulk and two-way SMS,
deliverability tools, reports, a REST API and a read-only client portal.

**Version 1.0.0** — see [docs/CHANGELOG.md](docs/CHANGELOG.md).

## Documentation
Start at **[docs/README.md](docs/README.md)**:
[installation](docs/installation.md) · [user guide](docs/user-guide.md) ·
[sending & deliverability](docs/sending-and-deliverability.md) · [SMS](docs/sms.md) ·
[administration](docs/administration.md) · [API](docs/api.md) · [developer guide](docs/developer-guide.md)

## Quick start (development)
```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate   # set DB_* and ADMIN_*
php artisan migrate --seed
php artisan serve & php artisan queue:work & php artisan schedule:work
```

## Deploying to shared hosting
```bash
php artisan app:package            # upload the zip, open the site, follow the installer
php artisan app:package --update   # later updates (keeps .env and storage)
```

Built with Laravel 13. Original code; functional reference: Acelle Mail feature set.
