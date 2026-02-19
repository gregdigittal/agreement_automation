<?php

namespace App\Filament\Resources\OverrideRequestResource\Pages;

use App\Filament\Resources\OverrideRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOverrideRequest extends EditRecord
{
    protected static string $resource = OverrideRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
