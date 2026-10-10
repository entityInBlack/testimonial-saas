# Backup and restore

v1 ships a nightly mysqldump-style backup and a tested restore
rehearsal. This document is the runbook — exact commands for
Windows PowerShell, and the rehearsal steps the maintainer can
follow to prove the restore works without touching the real
database.

The credentials live in `.env` and are read at runtime by
Laravel's `config/database.php`. They are NOT in this file and
NOT in any command-line argument. The `backup:run` artisan
command writes a temporary mysqldump "defaults file" with the
credentials, runs mysqldump reading from it, and deletes the
defaults file in a `finally` block.

## What is backed up

The full `mysql` connection's database (the one Laravel uses
for the app). The dump is single-transaction, quick, and
skips lock-tables, so a backup can run on a live database
without locking tables for long.

Default flags (override via `config/backup.php`):

- `--single-transaction`
- `--quick`
- `--skip-lock-tables`
- `--no-tablespaces`

## Where the dump goes

Default: `storage/app/backups/testimonial_saas-YYYYMMDD-HHMMSS.sql`.

The directory is created on first run if missing. It is
gitignored (`/storage/app/backups` in `.gitignore`).

## Where the binary lives

Default: the bare name `mysqldump`, assuming the binary is on
the PATH of the scheduler user. On most boxes it is
**not** on the PATH of the web user; set
`BACKUP_MYSQLDUMP_PATH` in `.env` to the absolute path of the
binary.

Examples:

- Windows (MySQL 8 installer): `C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe`
- Ubuntu (apt): `/usr/bin/mysqldump`
- macOS (Homebrew): `/opt/homebrew/opt/mysql-client/bin/mysqldump` (Apple Silicon) or `/usr/local/opt/mysql-client/bin/mysqldump` (Intel)

## Run a backup manually

```powershell
cd C:\11111\testimonial-saas
php artisan backup:run
```

The command prints the dump path and the file size. Audit
entries are written to the `purge` log channel
(`storage/logs/purge-YYYY-MM-DD.log`); the entries contain only
the output path and size — never the password, never the data.

## Run the scheduler

Laravel 11: one cron line on the host, then the scheduler
fires the rest.

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Windows: use Task Scheduler. Create a task that runs every
minute:

- Program: `php`
- Arguments: `artisan schedule:run`
- Start in: `C:\11111\testimonial-saas`

The schedule is in `routes/console.php`:

- `purge:run` at 03:30 UTC, with `withoutOverlapping`.
- `backup:run` at 04:00 UTC, with `withoutOverlapping`.

The order matters: the backup always runs an hour after the
purge, so a backup is always a copy of the post-purge state.

## Verify the schedule is wired

```powershell
php artisan schedule:list
```

Expected output (times are server local; both jobs are
`withoutOverlapping`):

```
30 3 * * *  php artisan purge:run
0  4 * * *  php artisan backup:run
```

## Restore rehearsal

A real restore is rehearsed into a SCRATCH database called
`testimonial_saas_restore_check`. Never restore into the live
`testimonial_saas` or `testimonial_saas_test` databases by
accident. The rehearsal compares row counts per table between
the source and scratch, prints both, then drops the scratch.

The point of the rehearsal is to prove the dump file is
non-empty, that every v1 table is present, and that the row
counts match the source. It does NOT verify row-level
correctness — for that, run a full E2E pass after the
rehearsal.

### Locating the mysql client

The mysql CLIENT binary is configurable the same way
`BACKUP_MYSQLDUMP_PATH` is, via `BACKUP_MYSQL_PATH` in `.env`
(read by `config('backup.mysql_client')`). Default is the bare
name `mysql` (assumes the client is on PATH). On Windows
installers the client is typically
`C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe`, which
is **not** on PATH by default — set `BACKUP_MYSQL_PATH` to the
absolute path, or use the `$MYSQL` shell variable below.

### Rehearsal commands (Windows PowerShell)

The commands below read the client from `$MYSQL`. Set it once
per shell to the absolute mysql path, or to `mysql` if the
client is on PATH:

```powershell
$MYSQL = "$env:BACKUP_MYSQL_PATH"
if (-not $MYSQL) { $MYSQL = 'mysql' }
```

```powershell
# 1. Find the latest dump file.
Get-ChildItem -Path storage\app\backups\testimonial_saas-*.sql |
    Sort-Object LastWriteTime -Descending |
    Select-Object -First 1

# 2. Create the scratch database.
& $MYSQL -u root -p -e "CREATE DATABASE IF NOT EXISTS testimonial_saas_restore_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Restore the dump into the scratch database. Substitute the
#    path from step 1.
& $MYSQL -u root -p testimonial_saas_restore_check < storage\app\backups\testimonial_saas-YYYYMMDD-HHMMSS.sql

# 4. Compare row counts. Spaces, testimonials, embed_configurations,
#    deletion_requests, users — the five v1 tables.
$tables = 'spaces','testimonials','embed_configurations','deletion_requests','users'
foreach ($t in $tables) {
    $src  = (& $MYSQL -u root -p -N -B -e "SELECT COUNT(*) FROM testimonial_saas.$t")
    $dest = (& $MYSQL -u root -p -N -B -e "SELECT COUNT(*) FROM testimonial_saas_restore_check.$t")
    "{0,-22} source={1,-8} scratch={2,-8}" -f $t,$src,$dest
}

# 5. Drop the scratch database.
& $MYSQL -u root -p -e "DROP DATABASE testimonial_saas_restore_check;"

# 6. Confirm the scratch database is gone.
& $MYSQL -u root -p -e "SHOW DATABASES LIKE 'testimonial_saas_restore_check';"
# Expect: empty result.
```

Expected output of step 4 (counts will match the live data):

```
spaces                source=12      scratch=12
testimonials          source=345     scratch=345
embed_configurations  source=12      scratch=12
deletion_requests     source=1       scratch=1
users                 source=4       scratch=4
```

The "deletion_requests=1" line is the seeded open Sara request
(see the data fixture; the actual count depends on what is
seeded in the dev DB).

## What is NOT in v1

- **No rotation.** The backup directory grows by one file per
  night. v1 does not prune old files. Cron a `find ... -mtime
  +N -delete` separately if needed.
- **No upload.** The backup is local-only. v1 does not push to
  S3, R2, or any other remote.
- **No point-in-time recovery.** A restore lands on the state
  of the dump file. The nightly gap is up to 24 hours.
- **No encryption at rest.** The .sql file is plaintext on
  disk. The dev box is assumed to be inside a trusted
  environment.
