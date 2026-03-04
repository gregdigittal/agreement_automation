<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Helpers\Feature;
use App\Services\SharePointService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class SharePointRelationManager extends RelationManager
{
    protected static string $relationship = 'exchangeRoom';
    protected static ?string $title = 'SharePoint Documents';

    public function table(Table $table): Table
    {
        $contract = $this->getOwnerRecord();
        $service = app(SharePointService::class);
        $items = [];

        if ($contract->sharepoint_drive_id && $contract->sharepoint_folder_id) {
            try {
                $items = $service->listFolderContents($contract);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SharePoint listing failed', [
                    'contract_id' => $contract->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Use a simple array-based table since these aren't Eloquent records
        return $table
            ->query(fn () => \App\Models\ExchangeRoomPost::query()->whereRaw('1 = 0'))
            ->emptyStateHeading(
                $contract->sharepoint_folder_id
                    ? (empty($items) ? 'No files in SharePoint folder' : 'SharePoint files loaded')
                    : 'No SharePoint folder linked'
            )
            ->emptyStateDescription(
                $contract->sharepoint_folder_id
                    ? 'Check the SharePoint folder or try refreshing.'
                    : 'Use the "Link SharePoint Folder" action to connect a folder.'
            )
            ->columns([])
            ->headerActions([
                Tables\Actions\Action::make('link_folder')
                    ->label('Link SharePoint Folder')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn (): bool => ! $contract->sharepoint_folder_id)
                    ->form([
                        Forms\Components\TextInput::make('share_url')
                            ->label('SharePoint Sharing URL')
                            ->url()
                            ->required()
                            ->placeholder('https://digittalgroup.sharepoint.com/sites/...')
                            ->helperText('Paste the sharing link from SharePoint. The folder will be resolved via Microsoft Graph API.'),
                    ])
                    ->action(function (array $data) use ($service, $contract): void {
                        try {
                            $service->linkFolder($contract, $data['share_url']);
                            Notification::make()
                                ->title('SharePoint folder linked')
                                ->body('The folder has been connected. Refresh to see the files.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Failed to link folder')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('refresh')
                    ->label('Refresh')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (): bool => (bool) $contract->sharepoint_folder_id)
                    ->action(fn () => null), // Just triggers a re-render

                Tables\Actions\Action::make('unlink')
                    ->label('Unlink Folder')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (): bool => (bool) $contract->sharepoint_folder_id)
                    ->requiresConfirmation()
                    ->action(function () use ($contract): void {
                        $contract->update([
                            'sharepoint_folder_id' => null,
                            'sharepoint_drive_id' => null,
                            'sharepoint_site_id' => null,
                        ]);
                        Notification::make()->title('SharePoint folder unlinked')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->contentFooter(
                $contract->sharepoint_folder_id && ! empty($items)
                    ? view('filament.resources.contract-resource.sharepoint-file-list', ['items' => $items])
                    : null
            );
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Feature::sharePoint() && $ownerRecord->sharepoint_enabled;
    }
}
