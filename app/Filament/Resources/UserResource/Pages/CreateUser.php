<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Mail\UserInviteMail;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'active';

        return $data;
    }

    protected function afterCreate(): void
    {
        // Clear Spatie's permission cache after relationship sync
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = $this->record->roles->pluck('name')->toArray();

        if (! empty($roles)) {
            Mail::to($this->record->email)
                ->queue(new UserInviteMail($this->record, $roles));
        }
    }
}
