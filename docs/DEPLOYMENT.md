# Backend Deployment Guide (Production)

Generic PHP-FPM + web server (Nginx/Apache) + MySQL deployment — no
cloud-specific tooling exists in this codebase, so nothing cloud-specific
is assumed here. Adapt paths/commands to your actual host.

## 1. Prerequisites

- PHP matching `composer.json`'s `require.php` constraint, with the
  extensions Laravel needs (pdo_mysql, mbstring, openssl, tokenizer,
  xml, ctype, json, bcmath).
- MySQL (or another Laravel-supported RDBMS) — the `.env.example`
  DB_CONNECTION defaults to `sqlite` for local dev only; production
  must set `DB_CONNECTION=mysql` (or equivalent) with real credentials.
- Composer, for `composer install`.

## 2. Deployment steps

```bash
# 1. Get the code onto the server (git pull / artifact deploy / etc.)

# 2. Install production dependencies only, with an optimized autoloader
composer install --no-dev --optimize-autoloader

# 3. Environment file — copy from .env.example and fill in every
#    production-specific value documented inline in that file
#    (APP_ENV, APP_DEBUG, APP_URL, DB_*, SESSION_SECURE_COOKIE,
#    SANCTUM_STATEFUL_DOMAINS, FRONTEND_URLS, LOG_STACK, LOG_LEVEL —
#    see .env.example comments for what each must be in production).
cp .env.example .env   # only if .env doesn't already exist — never overwrite a live one
php artisan key:generate   # only on first deploy; never re-run against a live .env, it invalidates encrypted data/sessions

# 4. Database migrations (review pending migrations before running against
#    a production database with real data)
php artisan migrate --force

# 5. Public storage symlink (serves uploaded room images)
php artisan storage:link

# 6. Framework caches — safe to run every deploy, all three verified
#    clean against this codebase (no closure routes, no stray env()
#    calls outside config/)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Restart PHP-FPM (or equivalent) so opcache/autoloader changes take effect
```

## 3. Rollback safety

- Every migration in `database/migrations/` has a working, non-empty
  `down()` — verified for this release. `php artisan migrate:rollback`
  is safe to use if a deploy needs to be reverted, but always confirm
  the specific migration's `down()` doesn't discard data you need
  first (rollback undoes the schema change, not any data written under
  the new schema).
- Before re-deploying old code after a rollback, also run
  `php artisan optimize:clear` so stale cached config/routes from the
  newer code aren't left active.

## 4. Post-deploy verification

- Hit the built-in health endpoint: `GET /up` (configured in
  `bootstrap/app.php`) — returns 200 if the app booted successfully.
- Confirm `APP_DEBUG=false` in the deployed `.env` (a stray `true` here
  leaks stack traces on non-`api/*` routes).
- Run `php artisan test` in a staging environment before promoting to
  production if the deploy included code changes (147/147 expected as
  of this release).

## 5. Required production environment variables

See `.env.example` — every variable that needs a production-specific
value now has an inline comment explaining why. Notably:

| Variable | Why it matters in production |
|---|---|
| `APP_ENV` | Must be `production` — gates HSTS header emission in `AddSecurityHeaders`, and several Laravel internals (error verbosity, etc.) |
| `APP_DEBUG` | Must be `false` — stack traces otherwise leak on non-API routes |
| `APP_URL` | Must be the real HTTPS domain |
| `DB_*` | Must point at a real MySQL/Postgres instance, not sqlite |
| `SESSION_SECURE_COOKIE` | Must be `true` — otherwise session cookies can be sent over plain HTTP |
| `SANCTUM_STATEFUL_DOMAINS` | Must include the production frontend's host |
| `FRONTEND_URLS` | Must be the production frontend's origin(s) — drives CORS `allowed_origins` |
| `LOG_STACK` | Should be `daily`, not `single`, for log rotation |
| `LOG_LEVEL` | Should be `warning` or `error`, not `debug` |
| `SEED_DEMO_DATA` | Should be `false`, or `DEMO_DEFAULT_PASSWORD` must be changed per-deployment |

## 6. Recommended, not implemented (needs infra-specific decisions)

These were identified during the Phase 8 production-readiness review but
were **not** implemented because the correct value depends on the actual
hosting setup, which this codebase has no evidence of:

- **Trusted proxies**: `bootstrap/app.php` does not currently configure
  `TrustProxies`. If this app runs behind a reverse proxy or load
  balancer that terminates TLS (the common case — Nginx/Cloudflare/a
  cloud LB in front of PHP-FPM), Laravel needs to trust that proxy's
  `X-Forwarded-*` headers to correctly detect HTTPS. Without this,
  `$request->isSecure()` (which the HSTS header in `AddSecurityHeaders`
  depends on) can incorrectly return `false` even when the end user is
  on HTTPS, silently disabling the HSTS header. Add to
  `bootstrap/app.php`'s `->withMiddleware()`:
  ```php
  $middleware->trustProxies(at: '*'); // or a specific proxy IP/CIDR — depends on your infra
  ```
  Using `'*'` is only safe if the app is not directly reachable except
  through your proxy (typical for most PaaS/reverse-proxy setups) —
  verify that's true for your deployment before using it.
- **Queue worker**: `QUEUE_CONNECTION=database` is configured, but no
  `ShouldQueue` job exists anywhere in the codebase today, so no queue
  worker process is currently required. If queued jobs are added in a
  future phase, a `php artisan queue:work` supervisor process (e.g.
  systemd or Supervisor) will need to be provisioned then — not now.
- **Outbound mail**: `MAIL_MAILER=log` (writes to the log instead of
  sending). No `Mail::` usage exists anywhere in the codebase, so this
  is not a current gap — only relevant if a future feature sends real
  email.
- **Object storage for uploads**: `FILESYSTEM_DISK=local` stores room
  images on local disk. Fine for a single-server deployment; if this
  ever runs on multiple app servers behind a load balancer, uploads
  need to move to a shared disk (`AWS_*` S3 config already exists as
  placeholders in `.env.example` for this — just needs real
  credentials and `FILESYSTEM_DISK=s3`). Not implemented since there's
  no evidence this app runs on more than one server.
