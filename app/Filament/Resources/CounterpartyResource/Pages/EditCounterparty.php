<?php

namespace App\Filament\Resources\CounterpartyResource\Pages;

use App\Filament\Resources\CounterpartyResource;
use App\Services\CounterpartyService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCounterparty extends EditRecord
{
    protected static string $resource = CounterpartyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $duplicates = app(CounterpartyService::class)->findDuplicates(
            $data['legal_name'] ?? '',
            $data['registration_number'] ?? '',
            $this->record->id,
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
