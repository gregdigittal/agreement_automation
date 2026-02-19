<?php

namespace App\Filament\Resources\WikiContractResource\Pages;

use App\Filament\Resources\WikiContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWikiContracts extends ListRecords
{
    protected static string $resource = WikiContractResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
