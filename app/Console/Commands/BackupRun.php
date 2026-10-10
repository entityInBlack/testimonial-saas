<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\Facades\Log;

/**
 * Step 6 — `php artisan backup:run`.
 *
 * Runs a mysqldump of the configured DB connection and writes a
 * .sql file to `config('backup.output_dir')`. Schedulable, exit
 * code 0 on success.
 *
 * SECURITY: the DB password is NEVER passed on the command line
 * and never appears in any log or output. We pass the password to
 * the child process via the `MYSQL_PWD` environment variable, which
 * mysqldump reads on every platform. The env var exists only for
 * the lifetime of the child process and is not logged, persisted,
 * or echoed back.
 *
 * We also set `MYSQL_TEST_LOGIN_FILE` to an empty string so that a
 * user-configured login-path file cannot silently override the
 * connection settings we just passed on the command line (defence
 * in depth — the `MYSQL_PWD` env var already wins).
 *
 * No backup rotation or upload — out of scope for v1.
 */
class BackupRun extends Command
{
    protected $signature = 'backup:run {--connection=mysql : DB connection name to dump}';

    protected $description = 'Run a mysqldump of the configured DB into config(backup.output_dir).';

    public function handle(ProcessFactory $processes): int
    {
        $connection = (string) $this->option('connection');
        $cfg = config("database.connections.$connection");

        if (! $cfg || ($cfg['driver'] ?? null) !== 'mysql') {
            $this->error("backup:run: connection '$connection' is not a mysql connection.");

            return self::FAILURE;
        }

        $binary = (string) config('backup.binary', 'mysqldump');
        $outputDir = (string) config('backup.output_dir', storage_path('app/backups'));
        $prefix = (string) config('backup.filename_prefix', 'testimonial_saas');
        $extra = (array) config('backup.extra_flags', []);

        // Ensure the output dir exists. The dir is gitignored; see
        // .gitignore (/storage/app/backups).
        if (! is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }

        $stamp = now()->format('Ymd-His');
        $filename = sprintf('%s-%s.sql', $prefix, $stamp);
        $outputPath = rtrim($outputDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;

        $password = (string) ($cfg['password'] ?? '');

        $cmd = array_merge(
            [$binary],
            $extra,
            [
                '--user='.(string) $cfg['username'],
                '--host='.(string) $cfg['host'],
                '--port='.(string) ($cfg['port'] ?? 3306),
                '--default-character-set=utf8mb4',
                '--result-file='.$outputPath,
                (string) $cfg['database'],
            ],
        );

        $process = $processes
            ->command($cmd)
            ->timeout(300);

        // SECURITY: the DB password is NEVER passed on the command
        // line. We pass it via the MYSQL_PWD environment variable,
        // which mysqldump reads on every platform. The env var
        // exists only for the duration of the child process and
        // is not logged, persisted, or echoed back.
        //
        // We also set MYSQL_TEST_LOGIN_FILE to an empty value so
        // mysqldump ignores any login-path file the user might
        // have configured (defence in depth — the env var above
        // already wins, but if a future operator adds login-path
        // support they will not silently override the connection
        // settings we just passed).
        if ($password !== '') {
            $process = $process
                ->env(['MYSQL_PWD' => $password, 'MYSQL_TEST_LOGIN_FILE' => '']);
        } else {
            $process = $process
                ->env(['MYSQL_TEST_LOGIN_FILE' => '']);
        }

        $result = $process->run();

        $stderr = (string) $result->errorOutput();
        $exit = $result->exitCode();

        if ($exit !== 0) {
            $scrubbed = $this->scrubPassword($stderr, $password);

            $this->error('backup:run: mysqldump exited '.$exit.'.');
            $this->line($scrubbed);

            Log::channel('purge')->error('backup:run mysqldump failed', [
                'exit_code' => $exit,
                'stderr_excerpt' => substr($scrubbed, 0, 500),
            ]);

            return self::FAILURE;
        }

        $size = file_exists($outputPath) ? filesize($outputPath) : 0;

        $this->line(sprintf(
            '[%s] backup:run wrote %s (%d bytes)',
            now()->toDateTimeString(),
            $outputPath,
            (int) $size,
        ));

        Log::channel('purge')->info('backup:run complete', [
            'output_path' => $outputPath,
            'output_size' => (int) $size,
        ]);

        return self::SUCCESS;
    }

    /**
     * Defensive scrub: if a password happens to appear in an error
     * message (it should not, but we belt-and-brace), replace it
     * before logging. The password value is read from config at
     * runtime — we never print it elsewhere.
     */
    protected function scrubPassword(string $haystack, string $password): string
    {
        if ($password === '') {
            return $haystack;
        }

        return str_replace($password, '***', $haystack);
    }
}
