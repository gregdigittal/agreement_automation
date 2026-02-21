<?php

namespace App\Filament\Resources\CounterpartyResource\Pages;

use App\Filament\Resources\CounterpartyResource;
use App\Services\CounterpartyService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCounterparty extends CreateRecord
{
    protected static string $resource = CounterpartyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $duplicates = app(CounterpartyService::class)->findDuplicates(
            $data['legal_name'] ?? '',
            $data['registration_number'] ?? '',
        );

        if ($duplicates->isNotEmpty() && ! ($data['duplicate_acknowledged'] ?? false)) {
            Notification::make()
                ->title('Duplicate check required')
                ->body('Please use the "Check for Duplicates" button and acknowledge before saving.')
                ->warning()
                ->send();

            $this->halt();
        }

        unset($data['duplicate_acknowledged']);
        return $data;
    }
}
