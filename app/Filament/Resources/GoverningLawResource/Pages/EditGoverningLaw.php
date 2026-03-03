<?php

namespace App\Filament\Resources\GoverningLawResource\Pages;

use App\Filament\Resources\GoverningLawResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoverningLaw extends EditRecord
{
    protected static string $resource = GoverningLawResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
