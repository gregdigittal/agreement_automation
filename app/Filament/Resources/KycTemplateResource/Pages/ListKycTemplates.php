<?php

namespace App\Filament\Resources\KycTemplateResource\Pages;

use App\Filament\Resources\KycTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKycTemplates extends ListRecords
{
    protected static string $resource = KycTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
