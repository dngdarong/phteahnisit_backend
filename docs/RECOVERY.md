# Backup & Disaster Recovery

## What needs backing up

1. **Database** — all application data (users, rooms, bookings,
   conversations/messages, favorites, audit logs).
2. **Uploaded files** — `storage/app/public/rooms/` (room images,
   written via `RoomService::attachImages()` to the `public` disk).
   The `storage/app/public` symlink itself (`php artisan storage:link`)
   is not data and does not need backing up — just recreate it after a
   restore.

`APP_KEY` (in `.env`, never committed) must also be preserved
separately from both — losing it invalidates encrypted session/cookie
data and any encrypted columns. Store it in your secrets manager
alongside DB credentials, not in the backup archive.

## Database backup

Adjust connection details to match your production `.env` (`DB_HOST`,
`DB_DATABASE`, `DB_USERNAME`):

```bash
mysqldump -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  --single-transaction --routines --triggers \
  > phteahnisit_$(date +%Y%m%d_%H%M%S).sql
```

`--single-transaction` avoids locking tables during the dump on InnoDB
(all of this app's tables are InnoDB via Laravel's default migrations).

## Uploaded files backup

```bash
tar -czf storage_$(date +%Y%m%d_%H%M%S).tar.gz storage/app/public/rooms
```

Run both backups on the same schedule so a restore point is consistent
between the DB (which references image paths) and the files themselves.

## Restore process

```bash
# 1. Restore the database
mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < phteahnisit_YYYYMMDD_HHMMSS.sql

# 2. Restore uploaded files
tar -xzf storage_YYYYMMDD_HHMMSS.tar.gz -C /path/to/deployed/app/

# 3. Recreate the public storage symlink (not part of the backup — it's just a symlink)
php artisan storage:link

# 4. Run any migrations that postdate this backup, if restoring onto a newer codebase
php artisan migrate --force

# 5. Clear and rebuild caches so the app reflects the restored state
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Verify
curl -f https://your-domain/up   # expect 200
```

## Migration rollback (code-level revert, not a data restore)

For rolling back a bad schema migration specifically (not a full
disaster recovery):

```bash
php artisan migrate:rollback           # reverts the most recent migration batch
php artisan migrate:rollback --step=1  # reverts just the last migration
```

Every migration in this codebase has a verified, non-empty `down()` as
of this release (checked during the Phase 8 review). `migrate:rollback`
reverts schema only — it does not restore data that existed before the
migration ran if that migration also transformed/dropped data. For any
migration that drops a column or table, take a database backup
immediately before rolling forward in production, since rollback alone
cannot recover data loss from a destructive `up()`.

## Disaster recovery runbook (full loss scenario)

1. Provision a fresh server/container with the required PHP version
   and extensions (see `docs/DEPLOYMENT.md` §1).
2. Deploy the application code at the last known-good release tag.
3. Restore the database from the most recent backup (§ Restore process,
   step 1).
4. Restore uploaded files from the most recent backup (step 2).
5. Recreate `.env` from your secrets manager (including `APP_KEY` —
   this must match the key used when the backed-up data was encrypted,
   or session/encrypted data becomes unreadable).
6. Run `storage:link`, any pending migrations, and rebuild caches
   (steps 3–5 of the restore process above).
7. Verify via `GET /up` and a manual smoke test of login + room search
   before pointing production traffic at the restored instance.
