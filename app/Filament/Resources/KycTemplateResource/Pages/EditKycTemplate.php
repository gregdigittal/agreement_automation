<?php

namespace App\Filament\Resources\KycTemplateResource\Pages;

use App\Filament\Resources\KycTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKycTemplate extends EditRecord
{
    protected static string $resource = KycTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
