<?php

namespace App\Filament\Resources\WikiContractResource\Pages;

use App\Filament\Resources\WikiContractResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWikiContract extends EditRecord
{
    protected static string $resource = WikiContractResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
