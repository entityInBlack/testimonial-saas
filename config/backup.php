<?php

/*
 * Nightly mysqldump-style backup. The actual binary is configurable
 * (it is not on PATH on every box) and the DB password is NEVER
 * passed on the command line — see BackupRun command for the
 * implementation.
 *
 * Output directory is gitignored (see /storage/app/backups in
 * .gitignore).
 */

return [
    /*
     * Path to the mysqldump binary. Default is the bare command name,
     * assuming mysqldump is on PATH. Override in .env with an absolute
     * path like /usr/local/mysql/bin/mysqldump or
     * C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe.
     */
    'binary' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

    /*
     * Path to the mysql CLIENT binary used by the restore
     * rehearsal runbook (docs/backup-restore.md). The restore is
     * a manual, operator-only process — the application does not
     * invoke the client — so this key is here only so the runbook
     * commands can be templated against a known, env-driven value.
     * The default is the bare "mysql" command name (assumes the
     * client is on PATH); override in .env with the absolute path
     * on hosts where the client is not on PATH (e.g. Windows
     * installers drop it under "C:\Program Files\MySQL\MySQL
     * Server 8.0\bin\mysql.exe").
     */
    'mysql_client' => env('BACKUP_MYSQL_PATH', 'mysql'),

    /*
     * Directory where the .sql dump file is written. Must exist and
     * be writable by the web user / scheduler user. The path is
     * created if it does not exist on the first backup:run.
     */
    'output_dir' => env('BACKUP_OUTPUT_DIR', storage_path('app/backups')),

    /*
     * Filename prefix. The timestamp suffix is added by BackupRun.
     */
    'filename_prefix' => env('BACKUP_FILENAME_PREFIX', 'testimonial_saas'),

    /*
     * Extra mysqldump flags. Default is single-transaction +
     * quick + skip-lock-tables + no-tablespaces. These are the
     * "safe online dump" flags the MySQL docs recommend.
     */
    'extra_flags' => [
        '--single-transaction',
        '--quick',
        '--skip-lock-tables',
        '--no-tablespaces',
    ],
];