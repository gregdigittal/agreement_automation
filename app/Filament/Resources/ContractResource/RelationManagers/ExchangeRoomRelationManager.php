<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Helpers\Feature;
use App\Helpers\StorageHelper;
use App\Models\ExchangeRoom;
use App\Models\ExchangeRoomPost;
use App\Services\ExchangeRoomService;
use App\Services\VendorNotificationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExchangeRoomRelationManager extends RelationManager
{
    protected static string $relationship = 'exchangeRoom';
    protected static ?string $title = 'Document Exchange Room';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $contract = $this->getOwnerRecord();
                $room = $contract->exchangeRoom;

                if ($room) {
                    return ExchangeRoomPost::query()->where('room_id', $room->id)->orderByDesc('created_at');
                }

                return ExchangeRoomPost::query()->whereRaw('1 = 0');
            })
            ->columns([
                Tables\Columns\TextColumn::make('actor_side')
                    ->label('Side')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'internal' => 'primary',
                        'vendor' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('author_name')
                    ->label('Author'),
                Tables\Columns\TextColumn::make('version_number')
                    ->label('Version')
                    ->badge()
                    ->color('info')
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state) => $state ? "v{$state}" : null),
                Tables\Columns\TextColumn::make('file_name')
                    ->label('File')
                    ->limit(40)
                    ->placeholder('— message only —'),
                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->limit(60)
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Posted')
                    ->since(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('open_room')
                    ->label('Open Exchange Room')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->visible(fn (): bool => $this->getOwnerRecord()->exchangeRoom === null)
                    ->requiresConfirmation()
                    ->modalHeading('Open Document Exchange Room')
                    ->modalDescription('This will create a shared document exchange workspace for this contract. Both internal users and the counterparty vendor can upload document versions and leave comments.')
                    ->action(function (): void {
                        $service = app(ExchangeRoomService::class);
                        $service->getOrCreate($this->getOwnerRecord(), auth()->id());

                        Notification::make()
                            ->title('Exchange Room opened')
                            ->body('The document exchange room is now active.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('upload_version')
                    ->label('Upload Version / Post')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->visible(fn (): bool => $this->getOwnerRecord()->exchangeRoom?->isOpen() === true)
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('Document (PDF / DOCX)')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->disk(config('ccrs.contracts_disk'))
                            ->visibility('private')
                            ->directory('exchange_room_uploads')
                            ->maxSize(51200)
                            ->helperText('Optional. Upload a new document version (max 50 MB).'),
                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->rows(3)
                            ->helperText('Optional. Leave a comment or note.'),
                    ])
                    ->action(function (array $data): void {
                        $contract = $this->getOwnerRecord();
                        $room = $contract->exchangeRoom;
                        $service = app(ExchangeRoomService::class);

                        $uploadedFile = null;
                        if (! empty($data['file'])) {
                            $disk = config('ccrs.contracts_disk', 'database');
                            $filePath = $data['file'];
                            // Read from storage disk (works with database disk where files are in MySQL)
                            $content = \Illuminate\Support\Facades\Storage::disk($disk)->get($filePath);
                            $tmpPath = tempnam(sys_get_temp_dir(), 'exchange_');
                            file_put_contents($tmpPath, $content);
                            $uploadedFile = new \Illuminate\Http\UploadedFile(
                                $tmpPath,
                                basename($filePath),
                            );
                        }

                        $post = $service->post(
                            $room,
                            auth()->user(),
                            'internal',
                            $data['message'] ?? null,
                            $uploadedFile,
                        );

                        // Notify vendor users
                        if ($contract->counterparty_id && $post->hasFile()) {
                            app(VendorNotificationService::class)->notifyVendors(
                                $contract->counterparty,
                                "New document version: {$contract->title}",
                                "A new document version (v{$post->version_number}) has been uploaded to the exchange room for \"{$contract->title}\".",
                                'exchange_room',
                                $room->id,
                            );
                        }

                        Notification::make()
                            ->title($post->hasFile() ? "Version v{$post->version_number} uploaded" : 'Message posted')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('advance_stage')
                    ->label('Advance Stage')
                    ->icon('heroicon-o-forward')
                    ->color('warning')
                    ->visible(fn (): bool => $this->getOwnerRecord()->exchangeRoom?->isOpen() === true
                        && $this->getOwnerRecord()->exchangeRoom?->negotiation_stage !== 'final')
                    ->form([
                        Forms\Components\Select::make('new_stage')
                            ->label('Advance to')
                            ->options(function (): array {
                                $room = $this->getOwnerRecord()->exchangeRoom;
                                $stages = ['draft_round', 'vendor_review', 'revised', 'final'];
                                $currentIdx = array_search($room->negotiation_stage, $stages);
                                $options = [];
                                foreach ($stages as $idx => $stage) {
                                    if ($idx > $currentIdx) {
                                        $options[$stage] = ucwords(str_replace('_', ' ', $stage));
                                    }
                                }
                                return $options;
                            })
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $service = app(ExchangeRoomService::class);
                        $service->advanceStage($this->getOwnerRecord()->exchangeRoom, $data['new_stage']);

                        Notification::make()
                            ->title('Stage advanced')
                            ->body('Negotiation stage updated to: ' . ucwords(str_replace('_', ' ', $data['new_stage'])))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('close_room')
                    ->label('Close Room')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (): bool => $this->getOwnerRecord()->exchangeRoom?->isOpen() === true)
                    ->requiresConfirmation()
                    ->modalHeading('Close Exchange Room')
                    ->modalDescription('No more posts or uploads will be allowed after closing. This action cannot be undone.')
                    ->action(function (): void {
                        $service = app(ExchangeRoomService::class);
                        $service->close($this->getOwnerRecord()->exchangeRoom);

                        Notification::make()
                            ->title('Exchange Room closed')
                            ->body('The room has been closed. No further posts are allowed.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (ExchangeRoomPost $record): bool => $record->hasFile())
                    ->url(fn (ExchangeRoomPost $record) => StorageHelper::temporaryUrl($record->storage_path, 'download'))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Feature::exchangeRoom() && $ownerRecord->exchange_room_enabled;
    }
}
