# Jehoven's Garden Resort

Reservation website for Jehoven's Garden Resort — function halls, room stays, catering,
and pool access, all bookable with a 50% down payment.

Built on Laravel 13 with Livewire 4, Flux UI, and Tailwind CSS v4.

---

## Requirements

| Tool | Version | Notes |
| --- | --- | --- |
| PHP | 8.3 or newer | With the `pdo_sqlite` and `sqlite3` extensions enabled |
| Composer | 2.x | |
| Node.js | 22.12+ (or 20.19+) | Required by Vite 8 |
| npm | 10+ | Ships with Node |

No database server is needed — the app uses SQLite by default and creates the database
file for you.

Check what you have:

```bash
php -v && composer -V && node -v
php -m | grep sqlite   # should list pdo_sqlite and sqlite3
```

---

## Quick start

```bash
git clone <repository-url> jehovens
cd jehovens
composer setup
composer dev
```

Then open **http://localhost:8000**.

That's it. `composer setup` is a single command that does the whole install — see below
for what it runs and how to do it by hand.

---

## What `composer setup` does

It runs these six steps in order:

1. `composer install` — installs PHP dependencies into `vendor/`
2. Copies `.env.example` to `.env` (skipped if `.env` already exists)
3. `php artisan key:generate` — writes a fresh `APP_KEY`
4. `php artisan migrate --force` — creates `database/database.sqlite` and its tables
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
touch database/database.sqlite
php artisan migrate
npm install
npm run build
```

`.env` and `database/database.sqlite` are both gitignored, so every machine gets its own.

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
    ├── pages/marketing/home.blade.php  The landing page
    ├── pages/auth/                   Login, register, password reset
    ├── pages/settings/               Profile, security, appearance
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

## Using MySQL instead of SQLite

SQLite is the default and needs no setup. To switch to MySQL, create the database, then
edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jehovens
DB_USERNAME=root
DB_PASSWORD=
```

Then run `php artisan config:clear && php artisan migrate`.

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

**`SQLSTATE[HY000]: unable to open database file`**
The SQLite file is missing. Run `touch database/database.sqlite && php artisan migrate`.

**`No application encryption key has been specified`**
Run `php artisan key:generate`.

**Config changes aren't taking effect**
Run `php artisan config:clear`. Editing `.env` requires this whenever config has been
cached.

---

## Known issues

`composer test` and `composer ci:check` currently fail at the PHPStan step on a stub method
left over from the Laravel starter kit:

```
database/factories/UserFactory.php:49
Method Database\Factories\UserFactory::withTwoFactor() should return
static(Database\Factories\UserFactory) but return statement is missing.
```

This is unrelated to the site itself and does not affect the app at runtime. Use
`php artisan test --compact` to run the test suite until the factory method is implemented.
