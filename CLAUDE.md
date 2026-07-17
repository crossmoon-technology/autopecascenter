# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`autopecascenter` — a bare Laravel base/starter project. It's a monolithic PHP/Laravel app with only the framework skeleton plus a custom authentication flow (login, registration, password reset). No admin panel, no business domain models beyond `users`. Portuguese (pt_BR) is the primary language for UI copy, labels, and validation messages; code (identifiers, comments) is in English.

## Commands

Run everything through the `workspace` Laradock container (`docker-compose.yml`, service `workspace` / `${COMPOSE_PROJECT_NAME}-workspace`), or directly if running outside Docker.

- Install & bootstrap: `composer setup` (installs deps, copies `.env`, generates key, migrates, builds frontend)
- Local dev (server + queue listener + log tail + vite, concurrently): `composer dev`
- Run all tests: `composer test` (clears config, then `php artisan test`)
- Run a single test: `php artisan test --filter=test_the_application_returns_a_successful_response` or `php artisan test tests/Feature/ExampleTest.php`
- Lint/format PHP: `vendor/bin/pint` (Laravel Pint)
- Frontend build: `npm run build` — dev/watch: `npm run dev` (Vite + Tailwind v4 + Sass)
- Migrations: `php artisan migrate` (Postgres only — see below)

Tests use plain PHPUnit (no Pest), SQLite `:memory:` DB, `sync` queue, `array` session/cache/mail during testing (see `phpunit.xml`), independent of the app's normal `pgsql`/`database` driver config.

## Architecture

### Auth flow is custom, not Breeze/Jetstream/Fortify

`AuthController` (`app/Http/Controllers/AuthController.php`) delegates business logic to `AuthService` (`app/Services/Auth/AuthService.php`) and throws custom exceptions (`app/Http/Controllers/AuthController/Exceptions/*`, each extending `ValidationException` with a static `::make()`) instead of returning validation errors inline. Form validation lives in `app/Http/Requests/Auth/*Request`. Password reset uses Laravel's built-in `Password` broker but sends a custom Mailable (`App\Mail\Auth\ResetPasswordMail`) via `User::sendPasswordResetNotification()`.

`User` has a `role` column (`App\Enums\Role`: `SuperAdmin=1`, `Admin=2`, `Client=3`) but nothing in the base app currently branches on it — `bootstrap/app.php`'s post-login redirect just sends everyone to `route('home')`. Add role-based authorization/redirects here as real features are built on top of this base.

### Database

PostgreSQL only (`config/database.php` default `pgsql`; host is `${APP_SLUG}-postgresql` per Laradock networking). Session, cache, and queue all use the `database` driver in normal (non-testing) environments — there is no Redis in this stack. See `README.md` for the documented base schema (`users`, `password_reset_tokens`, `sessions`) — keep it updated as tables are added.

### PHP 8.3 attribute-based Eloquent config

Models use `#[Fillable([...])]` and `#[Hidden([...])]` PHP attributes (`Illuminate\Database\Eloquent\Attributes\*`) instead of the classic `protected $fillable` / `protected $hidden` properties. Follow this convention for new models.

## Environment / Docker

Laradock-based `docker-compose.yml` with containers named `${COMPOSE_PROJECT_NAME}-*` (workspace, php-fpm as `-php`, php-worker as `-worker`, nginx, postgres, mailhog), where `COMPOSE_PROJECT_NAME=APP_SLUG=autopecascenter`. App is served at `https://autopecascenter.local.com.br` (`APP_URL`). Mail is caught by Mailhog in dev (`MAIL_MAILER=log` by default in `.env.example`, Mailhog available on the network).

**Queue worker**: `QUEUE_CONNECTION=database`. If a queued job is added, a `queue:work` process must actually be running for it to execute — otherwise dispatched jobs just sit in the `jobs` table forever. Supervisor inside the `php-worker` container runs it via `.docker/php-worker/supervisord.d/laravel-worker.conf` (gitignored by that folder's own `.gitignore`, like the rest of `*.conf` there — copy `laravel-worker.conf.example` again if it ever goes missing on a fresh clone), `autostart`/`autorestart` on, `--max-time=3600` so it self-restarts hourly. **Gotcha**: `queue:work` boots the framework once and keeps running — it does *not* pick up PHP file changes made after it started (unlike `php-fpm`, which revalidates opcache per-request). After editing any job/service code reachable from a queued job, restart the worker (`docker restart ${COMPOSE_PROJECT_NAME}-worker`, or `docker exec ${COMPOSE_PROJECT_NAME}-worker supervisorctl restart laravel-worker`) to avoid it silently executing stale, in-memory class definitions.

**File ownership across containers, already caused a real 500** (`touch(): Utime failed: Operation not permitted` from `BladeCompiler`, and would equally break `bootstrap/cache/*`): `php-fpm` actually serves requests as `www-data`, and `php-worker` as `laradock` — both UID 1000, so they're mutually fine — but running `artisan`/`composer`/`pint`/`test` via `docker exec` on the `workspace` container defaults to **`root`** (UID 0) unless you pass `-u laradock`/`-u www-data`, and root-owned files under `storage/` or `bootstrap/cache/` are then unwritable (can't even have their mtime touched) by the UID-1000 processes that actually serve traffic. If a page throws a permissions-flavored `ErrorException`/`touch()` failure, run `find /var/www/storage /var/www/bootstrap/cache -not -user 1000` (from the `workspace` container) to find the offending files and `chown -R 1000:1000` those two directories to fix it. To avoid causing this: prefer `docker exec -u www-data -w /var/www ${COMPOSE_PROJECT_NAME}-php ...` (the real serving user) when running `artisan` commands that touch `storage/`/`bootstrap/cache`.
