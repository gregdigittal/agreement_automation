<?php

namespace App\Filament\Resources\JurisdictionResource\Pages;

use App\Filament\Resources\JurisdictionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJurisdiction extends EditRecord
{
    protected static string $resource = JurisdictionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
