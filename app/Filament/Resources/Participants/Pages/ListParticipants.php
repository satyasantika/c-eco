<?php

declare(strict_types=1);

namespace App\Filament\Resources\Participants\Pages;

use App\Filament\Actions\ImportRosterAction;
use App\Filament\Resources\Participants\ParticipantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListParticipants extends ListRecords
{
    protected static string $resource = ParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportRosterAction::participants(),
            CreateAction::make()->label('Peserta baru'),
        ];
    }
}
