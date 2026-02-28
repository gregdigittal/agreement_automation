<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Mail\UserInviteMail;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $data['status'] = 'active';

        $record = static::getModel()::create($data);
        $record->syncRoles($roles);

        Mail::to($record->email)
            ->queue(new UserInviteMail($record, $roles));

        return $record;
    }
}
