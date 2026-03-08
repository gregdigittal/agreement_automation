<?php

namespace App\Filament\Resources\EntityShareholdingResource\Pages;

use App\Filament\Resources\EntityShareholdingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEntityShareholdings extends ListRecords
{
    protected static string $resource = EntityShareholdingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
