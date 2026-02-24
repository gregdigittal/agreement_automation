<?php

namespace App\Filament\Resources\JurisdictionResource\Pages;

use App\Filament\Resources\JurisdictionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJurisdictions extends ListRecords
{
    protected static string $resource = JurisdictionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
