<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\SessionEvent;
use App\Models\TestSession;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Angka yang perlu dilihat pengawas selama pelaksanaan.
 */
class SessionOverview extends StatsOverviewWidget
{
    /** Menit tanpa kabar sebelum sebuah sesi dianggap tersendat. */
    public const STUCK_AFTER_MINUTES = 5;

    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $stuck = $this->stuckCount();
        $duplicates = SessionEvent::query()->where('type', 'duplicate_submit')->count();

        return [
            Stat::make('Sedang mengerjakan', (string) TestSession::query()->where('status', 'in_progress')->count())
                ->description('Sesi berstatus in_progress')
                ->color('info'),

            Stat::make('Selesai', (string) TestSession::query()->where('status', 'completed')->count())
                ->description('Sesi berstatus completed')
                ->color('success'),

            Stat::make('Belum mulai', (string) TestSession::query()->where('status', 'pending')->count())
                ->description('Token terbit, belum dibuka')
                ->color('gray'),

            Stat::make('Tersendat', (string) $stuck)
                ->description('Tanpa kabar > '.self::STUCK_AFTER_MINUTES.' menit')
                // Ini satu-satunya angka yang menuntut pengawas berjalan ke
                // meja siswa. Warnanya berubah supaya tidak tenggelam.
                ->color($stuck > 0 ? 'danger' : 'gray'),

            Stat::make('Rata-rata butir', $this->averageItems())
                ->description('Pada sesi yang sudah selesai')
                ->color('gray'),

            Stat::make('Kiriman ulang', (string) $duplicates)
                ->description('Jawaban dikirim ulang oleh outbox')
                ->color($duplicates > 0 ? 'warning' : 'gray'),
        ];
    }

    private function stuckCount(): int
    {
        return TestSession::query()
            ->where('status', 'in_progress')
            ->where(function ($query): void {
                $threshold = Carbon::now()->subMinutes(self::STUCK_AFTER_MINUTES);

                $query->where('last_seen_at', '<', $threshold)->orWhereNull('last_seen_at');
            })
            ->count();
    }

    private function averageItems(): string
    {
        $average = TestSession::query()->where('status', 'completed')->avg('items_administered');

        return $average === null ? '—' : number_format((float) $average, 1);
    }
}
