<?php

namespace App\Filament\Resources\SigningAuthorityResource\Pages;

use App\Filament\Resources\SigningAuthorityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSigningAuthority extends EditRecord
{
    protected static string $resource = SigningAuthorityResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        // If "All Projects" toggle is ON, clear the pivot to mean unrestricted
        if ($this->data['all_projects'] ?? false) {
            $this->record->projects()->detach();
        }
    }
}
