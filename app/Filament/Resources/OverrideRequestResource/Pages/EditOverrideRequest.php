<?php

namespace App\Filament\Resources\OverrideRequestResource\Pages;

use App\Filament\Resources\OverrideRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOverrideRequest extends EditRecord
{
    protected static string $resource = OverrideRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['status']) && $data['status'] !== 'pending') {
            $data['decided_by'] = auth()->user()?->email;
            $data['decided_at'] = now();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
