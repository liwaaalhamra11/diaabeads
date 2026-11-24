# Diaa's Beads

Shop website and admin panel for Diaa's Beads — Laravel 13, PHP 8.5, Filament 5, MySQL.

## Setup

```bash
composer setup          # install, .env, key, migrate, storage link, seed admin + categories
php artisan serve       # http://127.0.0.1:8000
```

Database settings are in `.env` (MAMP MySQL: port `8889`, database `diaa_beads_db`).
Set `APP_TIMEZONE` (default `Asia/Beirut` here) and `SHOP_CURRENCY` / `SHOP_LOW_STOCK` if needed.

## Admin panel

`/admin` — seeded login `admin@admin.com` / `P@ssw0rd` (**change it after the first login**).

| Section | What it does |
|---|---|
| Shop → Products | Products with SKU, prices, stock, publish, many photos, view page with sales history |
| Shop → Categories / Subcategories | Name + publish; subcategories belong to a category |
| Finance → Bills | In (sales, take stock) and Out (expenses); paid / pending; PDF per bill |
| Finance → Reports | Daily / monthly financial report, export to Excel and PDF |
| Access Control | Admins, roles and permissions (`app/Support/AdminPermissions.php`) |

## Useful commands

```bash
php artisan test                                      # run the test suite
php artisan db:seed --class=ShopSeeder                # categories + subcategories (safe to re-run)
php artisan db:seed --class=AdminPermissionSeeder     # sync permissions after adding new ones
vendor/bin/pint                                       # code style
```

## Where things are

- `app/Reports/` — financial report calculations and Excel / PDF exports
- `app/Documents/BillPdf.php` + `resources/views/bills/pdf.blade.php` — bill PDF
- `resources/views/welcome.blade.php` — landing page
- `resources/brand/` — original logo and notes on the image files in `public/images/`
