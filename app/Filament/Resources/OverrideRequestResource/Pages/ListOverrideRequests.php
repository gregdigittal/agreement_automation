<?php

namespace App\Filament\Resources\OverrideRequestResource\Pages;

use App\Filament\Resources\OverrideRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOverrideRequests extends ListRecords
{
    protected static string $resource = OverrideRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
