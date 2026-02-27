<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Models\KycPackItem;
use App\Services\KycService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KycPackRelationManager extends RelationManager
{
    protected static string $relationship = 'kycPack';
    protected static ?string $title = 'KYC Pack';

    /**
     * Since kycPack is a hasOne, we display the pack's ITEMS, not packs.
     * We override the table query to fetch items from the pack.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $contract = $this->getOwnerRecord();
                $pack = $contract->kycPack;

                if ($pack) {
                    return KycPackItem::query()->where('kyc_pack_id', $pack->id);
                }

                // Return an empty query if no pack exists
                return KycPackItem::query()->whereRaw('1 = 0');
            })
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->wrap()
                    ->limit(60),
                Tables\Columns\TextColumn::make('field_type')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_required')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'not_applicable' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->placeholder('-'),
            ])
            ->actions([
                Tables\Actions\Action::make('fill')
                    ->label('Fill')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (KycPackItem $record): bool => $record->status === 'pending')
                    ->form(function (KycPackItem $record): array {
                        return match ($record->field_type) {
                            'file_upload' => [
                                Forms\Components\FileUpload::make('file_path')
                                    ->label($record->label)
                                    ->required()
                                    ->disk(config('ccrs.contracts_disk'))
                                    ->directory('kyc')
                                    ->helperText('Upload the required KYC document.'),
                            ],
                            'text' => [
                                Forms\Components\TextInput::make('value')
                                    ->label($record->label)
                                    ->required()
                                    ->helperText('Enter the requested information as text.'),
                            ],
                            'textarea' => [
                                Forms\Components\Textarea::make('value')
                                    ->label($record->label)
                                    ->required()
                                    ->rows(4)
                                    ->helperText('Provide a detailed response for this checklist item.'),
                            ],
                            'number' => [
                                Forms\Components\TextInput::make('value')
                                    ->label($record->label)
                                    ->required()
                                    ->numeric()
                                    ->helperText('Enter a numeric value.'),
                            ],
                            'date' => [
                                Forms\Components\DatePicker::make('value')
                                    ->label($record->label)
                                    ->required()
                                    ->helperText('Select the relevant date for this item.'),
                            ],
                            'yes_no' => [
                                Forms\Components\Toggle::make('value')
                                    ->label($record->label)
                                    ->required()
                                    ->helperText('Toggle on for Yes, off for No.'),
                            ],
                            'select' => [
                                Forms\Components\Select::make('value')
                                    ->label($record->label)
                                    ->options($record->options ?? [])
                                    ->required()
                                    ->helperText('Choose the appropriate option from the list.'),
                            ],
                            'attestation' => [
                                Forms\Components\Checkbox::make('attested')
                                    ->label('I attest to: ' . $record->label)
                                    ->required()
                                    ->accepted()
                                    ->helperText('Check to confirm your attestation of this statement.'),
                            ],
                            default => [
                                Forms\Components\TextInput::make('value')
                                    ->label($record->label)
                                    ->required()
                                    ->helperText('Enter the requested information.'),
                            ],
                        };
                    })
                    ->action(function (KycPackItem $record, array $data): void {
                        $service = app(KycService::class);

                        $value = $data['value'] ?? null;
                        $filePath = $data['file_path'] ?? null;

                        // Convert boolean toggle values to string
                        if (is_bool($value)) {
                            $value = $value ? 'Yes' : 'No';
                        }

                        $service->completeItem($record, value: $value, filePath: $filePath);

                        Notification::make()
                            ->title('Item completed')
                            ->body("'{$record->label}' has been filled.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('mark_na')
                    ->label('N/A')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn ($record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->update([
                            'status' => 'not_applicable',
                            'completed_at' => now(),
                            'completed_by' => auth()->id(),
                        ]);
                        $pack = $record->pack;
                        if ($pack->isComplete()) {
                            $pack->update(['status' => 'complete', 'completed_at' => now()]);
                        }
                        Notification::make()->title('Marked as N/A')->success()->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('initialize_kyc_pack')
                    ->label('Initialize KYC Pack')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->visible(fn (): bool => $this->getOwnerRecord()->kycPack === null)
                    ->requiresConfirmation()
                    ->modalHeading('Initialize KYC Pack')
                    ->modalDescription('This will create a KYC checklist for this contract based on the best-matching template.')
                    ->action(function (): void {
                        $contract = $this->getOwnerRecord();
                        $service = app(KycService::class);
                        $pack = $service->createPackForContract($contract);

                        if ($pack) {
                            Notification::make()
                                ->title('KYC Pack initialized')
                                ->body("Created pack with {$pack->items->count()} checklist items.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('No matching template')
                                ->body('No active KYC template matches this contract. Create a template first.')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }
}
