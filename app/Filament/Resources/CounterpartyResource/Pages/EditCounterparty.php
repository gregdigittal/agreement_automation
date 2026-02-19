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

    protected function beforeSave(): void
    {
        $service = app(CounterpartyService::class);
        $duplicates = $service->findDuplicates(
            $this->data['legal_name'] ?? '',
            $this->data['registration_number'] ?? null,
            $this->record->id,
        );

        if ($duplicates->isNotEmpty() && !($this->data['_duplicate_acknowledged'] ?? false)) {
            $names = $duplicates->pluck('legal_name')->implode(', ');
            Notification::make()
                ->title('Possible duplicates found')
                ->body("Similar counterparties exist: {$names}. Save again to confirm.")
                ->warning()
                ->persistent()
                ->send();

            $this->data['_duplicate_acknowledged'] = true;
            $this->halt();
        }

        unset($this->data['_duplicate_acknowledged']);
    }
}
