<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamGroups\Pages;

use App\Filament\Resources\ExamGroups\ExamGroupResource;
use App\Models\ExamGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExamGroups extends ListRecords
{
    protected static string $resource = ExamGroupResource::class;

    public function getTitle(): string
    {
        return 'Jadwal ujian';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Jadwal baru'),
        ];
    }

    public function getSubheading(): ?string
    {
        $groups = ExamGroup::query();
        $user = auth()->user();

        if ($user?->isPengawas()) {
            $groups->where('supervisor_id', $user->id);
        } else {
            $groups->whereNull('exam_simulation_id');
        }

        $rows = $groups->withCount([
            'testSessions as opened_count' => fn ($q) => $q->whereNotNull('opened_at'),
            'testSessions as claimed_count' => fn ($q) => $q->whereNotNull('claimed_at'),
        ])->get();

        $seats = $rows->sum('capacity');
        $opened = $rows->sum('opened_count');
        $claimed = $rows->sum('claimed_count');

        return "{$rows->count()} jadwal · {$seats} kursi · {$opened} sudah memuat QR · {$claimed} sudah isi identitas. Target 300 siswa serentak di kelas masing-masing.";
    }
}
