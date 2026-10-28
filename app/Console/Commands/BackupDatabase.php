<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Nightly copy of the shop's database. The shop server is the only copy of offline sales
 * until they sync, so a disk failure without a backup loses money.
 */
class BackupDatabase extends Command
{
    protected $signature = 'pos:backup {--keep=14 : How many backups to keep}';

    protected $description = 'Back up the POS database to storage/backups (or POS_BACKUP_PATH)';

    public function handle(): int
    {
        $db = config('database.connections.' . config('database.default'));

        if (($db['driver'] ?? null) !== 'mysql' && ($db['driver'] ?? null) !== 'mariadb') {
            $this->components->error('Backups are only set up for MySQL / MariaDB.');

            return self::FAILURE;
        }

        $dir = rtrim((string) (config('pos.backup_path') ?: storage_path('backups')), '/\\');
        File::ensureDirectoryExists($dir);

        $file = $dir . DIRECTORY_SEPARATOR . $db['database'] . '-' . now()->format('Y-m-d-His') . '.sql';
        $dump = config('pos.mysqldump');

        $process = new Process(array_values(array_filter([
            $dump,
            '--host=' . $db['host'],
            '--port=' . $db['port'],
            '--user=' . $db['username'],
            $db['password'] !== '' && $db['password'] !== null ? '--password=' . $db['password'] : null,
            '--single-transaction',
            '--routines',
            '--default-character-set=utf8mb4',
            '--result-file=' . $file,
            $db['database'],
        ])));
        $process->setTimeout(600)->run();

        if (! $process->isSuccessful() || ! is_file($file) || filesize($file) === 0) {
            @unlink($file);
            $this->components->error('Backup failed: ' . trim($process->getErrorOutput() ?: 'mysqldump not found. Set MYSQLDUMP_PATH in .env.'));

            return self::FAILURE;
        }

        // Keep the newest N
        collect(File::glob($dir . DIRECTORY_SEPARATOR . $db['database'] . '-*.sql'))
            ->sortDesc()->slice(max(1, (int) $this->option('keep')))
            ->each(fn ($old) => File::delete($old));

        $this->components->info('Backup saved: ' . $file . ' (' . round(filesize($file) / 1024 / 1024, 1) . ' MB)');

        return self::SUCCESS;
    }
}
