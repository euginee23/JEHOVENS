# Jehoven's Garden Resort

Reservation website for Jehoven's Garden Resort — function halls, room stays, catering,
and pool access, all bookable with a 50% down payment.

Built on Laravel 13 with Livewire 4, Flux UI, and Tailwind CSS v4.

---

## Requirements

| Tool | Version | Notes |
| --- | --- | --- |
| PHP | 8.4.1 or newer | With the `pdo_mysql` extension enabled |
| MySQL | 8.0 or newer | MariaDB 10.6+ also works |
| Composer | 2.x | |
| Node.js | 22.12+ (or 20.19+) | Required by Vite 8 |
| npm | 10+ | Ships with Node |

The app runs on MySQL. The test suite runs on in-memory SQLite (configured in
`phpunit.xml`), so tests need no database server of their own.

Check what you have:

```bash
php -v && composer -V && node -v
php -m | grep pdo_mysql   # should print pdo_mysql
mysql --version
```

---

## Quick start

```bash
git clone <repository-url> jehovens
cd jehovens

# Create the database first — composer setup migrates into it.
mysql -u root -p -e "CREATE DATABASE jehovens CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

composer setup
composer dev
```

If your MySQL credentials differ from the defaults in `.env.example` (`root` with no
password on `127.0.0.1:3306`), edit `.env` after the first `composer setup` run and then
run `php artisan migrate`.

Then open **http://localhost:8000**.

That's it. `composer setup` is a single command that does the whole install — see below
for what it runs and how to do it by hand.

---

## What `composer setup` does

It runs these six steps in order:

1. `composer install` — installs PHP dependencies into `vendor/`
2. Copies `.env.example` to `.env` (skipped if `.env` already exists)
3. `php artisan key:generate` — writes a fresh `APP_KEY`
4. `php artisan migrate --force` — creates the tables in your `jehovens` database
5. `npm install` — installs JS dependencies into `node_modules/`
6. `npm run build` — compiles CSS/JS into `public/build/`

Steps 4–6 are the ones that matter after pulling changes; see
[Pulling updates](#pulling-updates).

### Doing it manually

If you'd rather run the steps yourself, or one of them failed and you want to retry just
that part:

```bash
composer install
cp .env.example .env
php artisan key:generate
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS jehovens CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
npm install
npm run build
```

`.env` is gitignored, so every machine gets its own database credentials.

---

## Running the app

```bash
composer dev
```

This starts four processes together and streams their output into one terminal:

| Process | Command | Purpose |
| --- | --- | --- |
| `server` | `php artisan serve` | The app at http://localhost:8000 |
| `vite` | `npm run dev` | Hot-reloads CSS and Blade changes |
| `queue` | `php artisan queue:listen` | Background jobs |
| `logs` | `php artisan pail` | Live application log |

Press <kbd>Ctrl</kbd>+<kbd>C</kbd> to stop all four.

**Prefer to run just the web server?** Use `php artisan serve` on its own — but then run
`npm run build` after any CSS or Blade change, or the browser will keep serving the last
compiled stylesheet.

Run `php artisan dev:list` to see the configured processes.

---

## Pulling updates

After `git pull`, run whichever of these apply:

```bash
composer install       # if composer.lock changed
npm install            # if package-lock.json changed
php artisan migrate    # if anything in database/migrations/ changed
npm run build          # if any CSS or Blade file changed (not needed while `composer dev` is running)
php artisan config:clear
```

`composer setup` can be re-run and will not overwrite your `.env` or drop your database —
but it *does* regenerate `APP_KEY`, which logs out every existing session. On a machine
that's already set up, prefer the individual commands above.

---

## Common tasks

| Task | Command |
| --- | --- |
| Start everything | `composer dev` |
| Build assets for production | `npm run build` |
| Run the test suite | `php artisan test --compact` |
| Run one test file | `php artisan test --compact --filter=LandingPage` |
| Format PHP code | `vendor/bin/pint` |
| Check formatting without changing files | `composer lint:check` |
| Reset the database | `php artisan migrate:fresh` |
| List all routes | `php artisan route:list --except-vendor` |
| Clear cached config | `php artisan config:clear` |

---

## Project layout

The parts you're most likely to touch:

```
resources/
├── css/app.css                       Tailwind theme — brand colours live here
└── views/
    ├── layouts/marketing.blade.php   Public site shell (nav + footer)
    ├── layouts/admin.blade.php       Staff shell (in-page topbar)
    ├── layouts/auth.blade.php        Sign-in shell
    ├── pages/marketing/home.blade.php  The landing page
    ├── pages/booking/                Function hall, rooms, catering
    ├── pages/admin/                  Dashboard
    ├── pages/auth/                   Login, password reset
    ├── pages/settings/               Profile, security
    ├── components/marketing/         nav, footer, cards, section headings
    └── partials/marketing-head.blade.php  <head> tags for public pages

public/images/resort/                 Resort photography used by the landing page
routes/web.php                        Route definitions
tests/Feature/                        Pest tests
```

### Brand colours

The teal and coral palettes are defined as CSS custom properties in the `@theme` block of
`resources/css/app.css` (`--color-brand-*` and `--color-coral-*`). Change a value there and
every `bg-brand-600`, `text-brand-700`, etc. across the site updates on the next build.
There is no `tailwind.config.js` — Tailwind v4 is configured entirely in CSS.

---

## Database configuration

The defaults in `.env.example`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jehovens
DB_USERNAME=root
DB_PASSWORD=
```

Change these in `.env` to match your local server, then run
`php artisan config:clear && php artisan migrate`.

Seed the halls, rooms, and catering packages with `php artisan db:seed`.

**Tests do not use this database.** `phpunit.xml` pins the suite to in-memory SQLite so it
runs fast and never touches your data. CI does the same — it stands up a MySQL service
only for the migration step, and runs the suite on SQLite.

---

## Admin area

The public site needs no accounts — guests book halls, rooms, and catering without signing
in. The only part of the site behind a login is the admin area at **`/admin`**, which
redirects to `/admin/login` until you sign in.

**There is no public sign-up.** Fortify's registration feature is switched off and
`/register` returns 404. There are two ways to create an administrator.

**Seeder** — creates the default account, and runs as part of `php artisan db:seed`:

```bash
php artisan db:seed --class=AdminSeeder
```

| | |
| --- | --- |
| Email | `admin@admin.com` |
| Password | `password` |

Override these with `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` in `.env` before
seeding anywhere public. The seeder is idempotent — re-running it updates the same account
rather than creating a second one.

> **Change the default password before the site is reachable from the internet.**
> `admin@admin.com` / `password` is a guess away from full access to the admin area.

**Console** — for any account after the first, or to avoid the default credentials
entirely:

```bash
php artisan resort:make-admin
```

It prompts for name, email, and password, or takes them as options:

```bash
php artisan resort:make-admin --name="Jane" --email="jane@example.com" --password="..."
```

Accounts created this way are marked as verified, since nobody is there to click a
verification link.

| URL | What it is |
| --- | --- |
| `/admin` | Dashboard — live reservation figures (redirects to the login when signed out) |
| `/admin/login` | Staff sign-in |
| `/admin/forgot-password` | Password reset request |
| `/admin/settings/profile` | Name and email |
| `/admin/settings/security` | Password |

`/dashboard` and `/settings` redirect into `/admin` for anyone with an old bookmark.

The dashboard is read-only: it shows counts of reservations awaiting payment, bookings made
this week, confirmed revenue for the month, and what is coming up in the next seven days,
plus the newest reservations across all three types. Confirming a downpayment still means
updating the row's `status` in the database.

---

## Troubleshooting

**`Unable to locate file in Vite manifest`**
Assets haven't been compiled. Run `npm run build`, or start `composer dev` and leave it
running.

**The page loads but has no styling**
Same cause as above — `public/build/` is gitignored, so a fresh clone always needs
`npm run build` at least once.

**`Failed to listen on 127.0.0.1:8000 (reason: Address already in use)`**
Something else is on that port. Use another one: `php artisan serve --port=8001`.

**`SQLSTATE[HY000] [2002] Connection refused`**
MySQL isn't running, or `.env` points at the wrong host or port. Start it
(`sudo service mysql start`) and check `DB_HOST` / `DB_PORT`, then `php artisan config:clear`.

**`SQLSTATE[HY000] [1049] Unknown database 'jehovens'`**
The database doesn't exist yet. Create it:
`mysql -u root -p -e "CREATE DATABASE jehovens CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"`

**`No application encryption key has been specified`**
Run `php artisan key:generate`.

**Config changes aren't taking effect**
Run `php artisan config:clear`. Editing `.env` requires this whenever config has been
cached.

**`Your lock file does not contain a compatible set of packages`**
Your PHP is older than the dependencies require. The locked Symfony packages need PHP
8.4.1+; check with `php -v` and upgrade. Do **not** run `composer update` to work around
this — it would silently downgrade packages across the whole project.
