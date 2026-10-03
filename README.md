<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

> **Custograde project documentation lives at [`../SCOPE.md`](../SCOPE.md).**
> That is the authoritative record of what we are building, the build order,
> the non-negotiable product rules, and the open risks. The requirements
> baseline is `docs/requirements/requirements.md` (269 requirement IDs).
> Architecture decisions are in `docs/decisions.md`, entity notes in
> `docs/entities.md`.

## Running the Custograde stack locally

Marking is not synchronous. A scan is uploaded, a job transcribes it, the AI
service reads it, and suggestions come back. That means **three processes** are
required, and a missing one fails quietly rather than loudly.

```bash
# 1. Laravel
php artisan serve --host=127.0.0.1 --port=8000

# 2. The queue worker. Without this, nothing is ever transcribed.
php artisan queue:work --tries=1 --timeout=600 --sleep=1

# 3. The Python AI service, in ../AI_Service
.venv/Scripts/python.exe -m uvicorn app.api.server:app --host 127.0.0.1 --port 8100
```

### Restart the worker after installing a Composer package

`php artisan queue:work` is a long-running process, so it holds a snapshot of the
autoloader from when it started. Install a package and the worker keeps using the
old map until it is restarted.

The symptom is confusing because the web request succeeds and only the queued
work fails. Answer sheet batch printing showed it clearly: `POST /sheets/batch`
returned 202, the batch row appeared, and then every candidate failed inside the
worker with `Class "..." not found`, while the identical code worked perfectly in
tests and in `artisan tinker`. Both were the same stale worker.

So after `composer require` or `composer dump-autoload`:

```bash
# Ctrl-C the running worker, then
php artisan queue:work --tries=1 --timeout=600 --sleep=1
```

If a queued job fails with a "class not found" that you can load fine in tinker,
this is the cause before anything else.

### Why the queue worker is called out explicitly

The end-to-end check hit this and it is worth knowing about. With Laravel running
but no worker, `POST /exams/{id}/scripts` returns **201** and the script appears
in the queue as `uploaded`. It then sits there forever. Nothing errors, no log
line mentions a worker, and the upload looks successful.

The symptom to recognise: an upload that returns success but never leaves
`uploaded` state. Check the worker is running before investigating anything else.

The `--timeout=600` is deliberate. A page with several questions takes the model
around 13 seconds per page, and the default 60 second worker timeout kills long
jobs mid-flight.

### Confirming the stack is healthy

```bash
curl http://127.0.0.1:8000/api/v1/ai/health
```

Reports whether the AI service is reachable and whether a provider key is
configured. A `false` here means uploads will queue and never complete, which is
why the frontend states AI availability in the sidebar rather than leaving a
marker to discover it as a blank panel.

### Verifying end to end

From the repository root, with all three processes running:

```bash
python ../e2e_check.py       # real scan in, released result out
python ../contract_check.py  # API field names and types match the frontend
```

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

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

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
