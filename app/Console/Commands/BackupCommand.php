<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Dump basis data ke disk lokal.
 *
 * Dijadwalkan tiap 15 menit selama pelaksanaan (routes/console.php). Kalau
 * server jatuh di tengah sesi, yang hilang paling banyak seperempat jam
 * pengerjaan — bukan seluruh hari pengambilan data yang tidak bisa diulang
 * karena siswa sudah pulang.
 */
class BackupCommand extends Command
{
    protected $signature = 'cat:backup {--keep=48 : Berapa dump terakhir yang disimpan}';

    protected $description = 'Menyimpan dump basis data ke storage/app/backups';

    public function handle(): int
    {
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/ceco-'.now()->format('Ymd-His').'.sql.gz';

        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        $process = Process::fromShellCommandline(
            'mysqldump --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" '
            .'--password="$DB_PASS" --single-transaction --quick --routines '
            .'"$DB_NAME" | gzip > "$DB_OUT"',
            timeout: 600,
            env: [
                'DB_HOST' => $db['host'],
                'DB_PORT' => (string) $db['port'],
                'DB_USER' => $db['username'],
                'DB_PASS' => $db['password'],
                'DB_NAME' => $db['database'],
                'DB_OUT' => $path,
            ],
        );

        $process->run();

        if (! $process->isSuccessful() || ! File::exists($path) || File::size($path) === 0) {
            File::delete($path);
            $this->error('Dump gagal: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $this->prune($directory, (int) $this->option('keep'));

        $this->info(sprintf('Dump tersimpan: %s (%s KB)', $path, number_format(File::size($path) / 1024, 1)));

        return self::SUCCESS;
    }

    private function prune(string $directory, int $keep): void
    {
        $dumps = collect(File::glob($directory.'/ceco-*.sql.gz'))->sort()->values();

        $dumps->slice(0, max(0, $dumps->count() - $keep))
            ->each(static fn (string $file) => File::delete($file));
    }
}
