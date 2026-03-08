<?php

namespace App\Filament\Resources\EntityShareholdingResource\Pages;

use App\Filament\Resources\EntityShareholdingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEntityShareholding extends EditRecord
{
    protected static string $resource = EntityShareholdingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
