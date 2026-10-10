<?php

use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\Facades\Process;

/**
 * Step 6 — `php artisan backup:run` builds the right mysqldump
 * command and never exposes the password.
 *
 * The process is faked via the built-in `Process::fake()` so no
 * real binary runs. The test asserts:
 *   - The configured binary path is used
 *   - The DB password is not in any argument
 *   - The output path is inside the configured output directory
 *   - The extra flags from config are present
 */

beforeEach(function () {
    // Use a temp output dir and a fake binary path.
    $this->tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tsbackup-test-'.uniqid();
    @mkdir($this->tmpDir, 0775, true);

    config([
        'backup.binary' => '/usr/bin/mysqldump-test',
        'backup.output_dir' => $this->tmpDir,
        'backup.filename_prefix' => 'testbackup',
        'backup.extra_flags' => [
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--no-tablespaces',
        ],
        'database.connections.mysql.password' => 'SECRET-PASSWORD-DO-NOT-LOG',
    ]);
});

afterEach(function () {
    if (is_dir($this->tmpDir)) {
        foreach (glob($this->tmpDir.'/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
    }
});

test('backup:run uses the configured binary path, no password in args, writes into the configured output dir', function () {
    Process::fake();

    $exit = $this->artisan('backup:run')->run();

    expect($exit)->toBe(0);

    // Capture the command + env for assertions.
    $cmdRef = null;
    $envRef = null;

    Process::assertRan(function ($process, $result) use (&$cmdRef, &$envRef) {
        $cmdRef = is_array($process->command) ? $process->command : [$process->command];
        $envRef = $process->environment;

        return true;
    });

    expect($cmdRef)->not->toBeNull();

    // 1. Binary path is the configured one.
    expect($cmdRef[0])->toBe('/usr/bin/mysqldump-test');

    // 2. Password does not appear in any CLI argument.
    foreach ($cmdRef as $arg) {
        if (is_string($arg)) {
            expect($arg)->not->toContain('SECRET-PASSWORD-DO-NOT-LOG');
        }
    }

    // 3. Password is in the env via MYSQL_PWD (not on the command
    //    line — that's the security contract).
    expect($envRef)->toHaveKey('MYSQL_PWD');
    expect($envRef['MYSQL_PWD'])->toBe('SECRET-PASSWORD-DO-NOT-LOG');

    // 4. A --result-file=<path> argument points inside the output dir.
    $resultFile = null;
    foreach ($cmdRef as $arg) {
        if (is_string($arg) && str_starts_with($arg, '--result-file=')) {
            $resultFile = substr($arg, strlen('--result-file='));
        }
    }
    expect($resultFile)->not->toBeNull();
    expect(dirname($resultFile))->toBe($this->tmpDir);
    expect(basename($resultFile))->toStartWith('testbackup-');
    expect(basename($resultFile))->toEndWith('.sql');

    // 5. Extra flags from config are present.
    expect($cmdRef)->toContain('--single-transaction');
    expect($cmdRef)->toContain('--quick');
    expect($cmdRef)->toContain('--skip-lock-tables');
    expect($cmdRef)->toContain('--no-tablespaces');

    // 6. The connection settings are on the command line (no
    //    login-path file).
    expect($cmdRef)->toContain('--user=root');
    expect($cmdRef)->toContain('--host=127.0.0.1');
    expect($cmdRef)->toContain('--port=3306');
});
