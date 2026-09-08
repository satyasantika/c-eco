<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\BackupCommand;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupTest extends TestCase
{
    public function test_the_dump_is_not_scheduled_unless_the_flag_is_on(): void
    {
        $this->assertNull($this->backupEvent(enabled: false));
    }

    public function test_the_flag_schedules_a_dump_every_fifteen_minutes(): void
    {
        $event = $this->backupEvent(enabled: true);

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
    }

    /**
     * Dump menumpuk sendiri kalau tidak dipangkas: 15 menit sekali berarti 96
     * berkas sehari, masing-masing ratusan kilobita.
     */
    public function test_pruning_keeps_only_the_newest_dumps(): void
    {
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        foreach (['20260101-000000', '20260101-001500', '20260101-003000'] as $stamp) {
            File::put($directory."/ceco-{$stamp}.sql.gz", 'x');
        }

        $command = new BackupCommand;
        (new \ReflectionMethod($command, 'prune'))->invoke($command, $directory, 2);

        $kept = array_map('basename', File::glob($directory.'/ceco-*.sql.gz'));
        sort($kept);

        $this->assertSame(['ceco-20260101-001500.sql.gz', 'ceco-20260101-003000.sql.gz'], $kept);

        File::deleteDirectory($directory);
    }

    private function backupEvent(bool $enabled): ?Event
    {
        config(['cat.backup.enabled' => $enabled, 'cat.backup.keep' => 48]);

        // Jadwal dibangun ulang supaya membaca config yang baru saja diubah.
        $schedule = new Schedule;
        $this->app->instance(Schedule::class, $schedule);
        require base_path('routes/console.php');

        foreach ($schedule->events() as $event) {
            if (str_contains($event->command ?? '', 'cat:backup')) {
                return $event;
            }
        }

        return null;
    }
}
