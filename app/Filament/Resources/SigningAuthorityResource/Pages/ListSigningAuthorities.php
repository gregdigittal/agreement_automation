<?php

namespace App\Filament\Resources\SigningAuthorityResource\Pages;

use App\Filament\Resources\SigningAuthorityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSigningAuthorities extends ListRecords
{
    protected static string $resource = SigningAuthorityResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
