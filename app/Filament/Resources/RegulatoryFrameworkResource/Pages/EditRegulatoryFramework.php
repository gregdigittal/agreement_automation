<?php

namespace App\Filament\Resources\RegulatoryFrameworkResource\Pages;

use App\Filament\Resources\RegulatoryFrameworkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRegulatoryFramework extends EditRecord
{
    protected static string $resource = RegulatoryFrameworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
