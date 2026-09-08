<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Pay-in & Payout Module

A backend payment-processing module built on Laravel 13 (PHP ^8.3) and administered through
Laravel Backpack. Merchants initiate pay-in and payout transactions via a JSON API; each
transaction is created in a `PENDING` state and resolved asynchronously by a scheduled
Artisan command that adjusts the owning merchant's wallet balance exactly once.

- JSON API: `POST /api/pay-in` and `POST /api/payout`
- Admin panel (Laravel Backpack): `/admin`
- Scheduled processor: the `payments:process` Artisan command

For the full API reference (sample requests, success responses, and error responses), see
[docs/API.md](docs/API.md).

## Setup / Getting Started

### Prerequisites

- PHP `^8.3` with the `php8.3-sqlite3` extension enabled (the project uses SQLite by default).
  On Debian/Ubuntu: `sudo apt-get install php8.3-sqlite3`
- [Composer](https://getcomposer.org/)

Verify PHP and Composer are available:

```bash
php -v
composer -V
```

Expected outcome: each command prints its installed version (PHP 8.3.x and Composer 2.x). If
either command is not found, install the missing tool before continuing.

### 1. Install dependencies

```bash
composer install
```

Expected outcome: Composer downloads the project's dependencies into `vendor/` and finishes
with a line such as `Generating optimized autoload files` followed by
`Package manifest generated successfully.` with no errors.

### 2. Environment configuration

Copy the example environment file:

```bash
cp .env.example .env
```

Expected outcome: a new `.env` file exists at the repository root (no output is printed on
success).

Generate the application encryption key:

```bash
php artisan key:generate
```

Expected outcome: the command prints `Application key set successfully.` and populates the
`APP_KEY` value in `.env`.

The project ships with `DB_CONNECTION=sqlite` in `.env.example`, so no database server is
required. Create the SQLite database file that migrations will run against:

```bash
touch database/database.sqlite
```

Expected outcome: an empty file `database/database.sqlite` exists at the repository root (no
output is printed on success). The automated test suite uses an in-memory SQLite database
(`:memory:`) and does not need this file.

### 3. Run migrations

```bash
php artisan migrate
```

Expected outcome: the command reports each migration running and finishes with `DONE`,
creating the `merchants`, `wallets`, `pay_ins`, `payouts`, and `transaction_logs` tables (plus
Laravel's default framework tables). Example output lines look like
`... create_merchants_table ... DONE`.

### 4. Seed sample data

```bash
php artisan db:seed
```

Expected outcome: the command prints `Seeding: Database\Seeders\MerchantSeeder` followed by
`Database seeding completed successfully.` It creates 3 sample merchants (Acme Payments Ltd,
Globex Commerce Inc, Initech Digital LLC), each associated with exactly one wallet initialized
with a non-negative balance. The seeder is idempotent, so it is safe to re-run.

### 5. Run the application

Start the local development server:

```bash
php artisan serve
```

Expected outcome: the command prints `Server running on [http://127.0.0.1:8000]`. The API is
then reachable at `http://127.0.0.1:8000/api/pay-in` and `http://127.0.0.1:8000/api/payout`,
and the Backpack admin panel at `http://127.0.0.1:8000/admin`.

### 6. Process pending payments

Pending transactions are resolved by the `payments:process` command. Run it once manually:

```bash
php artisan payments:process
```

Expected outcome: the command evaluates all `PENDING` pay-in and payout transactions, assigns
each a random outcome (`success`, `failed`, or `pending`), applies successful transactions to
the owning merchant's wallet, and prints a processing summary. In production the command is
driven automatically by the Laravel scheduler (registered every minute with
`withoutOverlapping()`), which runs via:

```bash
php artisan schedule:work
```

Expected outcome: the scheduler stays running and invokes `payments:process` every minute.

### Running the tests

```bash
./vendor/bin/phpunit
```

Expected outcome: PHPUnit runs the unit, feature, and property-based test suites against an
in-memory SQLite database and reports `OK` with all assertions passing.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
