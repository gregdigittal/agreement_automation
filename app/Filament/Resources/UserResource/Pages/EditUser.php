<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function afterSave(): void
    {
        // Clear Spatie's permission cache after relationship sync
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->is(auth()->user())),
        ];
    }
}
