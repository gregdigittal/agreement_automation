<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Jobs\ProcessSmartUpload;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    /**
     * When true, the next create will produce a staging contract
     * and dispatch AI discovery instead of the normal create flow.
     */
    public bool $saveAsStaging = false;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save_and_discover')
                ->label('Save & Run AI Discovery')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->action(function () {
                    $this->saveAsStaging = true;
                    $this->create();
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        if ($this->saveAsStaging) {
            $data['workflow_state'] = 'staging';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->saveAsStaging && $this->record->storage_path) {
            ProcessSmartUpload::dispatch(
                contractId: $this->record->id,
                actorId: auth()->id(),
            )->onQueue('default');

            Notification::make()
                ->title('AI Discovery started')
                ->body('The contract has been saved in staging. AI is extracting metadata in the background.')
                ->success()
                ->send();
        }
    }

    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Create Agreement');
    }
}
