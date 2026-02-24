<?php

namespace App\Filament\Resources\RegulatoryFrameworkResource\Pages;

use App\Filament\Resources\RegulatoryFrameworkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegulatoryFrameworks extends ListRecords
{
    protected static string $resource = RegulatoryFrameworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
