<?php

namespace App\Filament\Vendor\Pages;

use App\Helpers\Feature;
use App\Helpers\StorageHelper;
use App\Mail\ExchangeRoomNewVersionMail;
use App\Models\Contract;
use App\Models\ExchangeRoomPost;
use App\Models\User;
use App\Services\ExchangeRoomService;
use App\Services\TeamsNotificationService;
use Illuminate\Support\Facades\Mail;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class VendorExchangeRoomPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static string $view = 'filament.vendor.pages.exchange-room';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'exchange-room/{contractId}';

    public ?Contract $contract = null;

    public function mount(string $contractId): void
    {
        $vendorUser = auth('vendor')->user();

        $this->contract = Contract::where('id', $contractId)
            ->where('counterparty_id', $vendorUser->counterparty_id)
            ->firstOrFail();

        abort_unless(
            Feature::exchangeRoom() && $this->contract->exchange_room_enabled && $this->contract->exchangeRoom,
            404,
        );
    }

    public function getTitle(): string|Htmlable
    {
        return 'Document Exchange: ' . ($this->contract?->title ?? '');
    }

    public function table(Table $table): Table
    {
        $room = $this->contract->exchangeRoom;

        return $table
            ->query(
                $room
                    ? ExchangeRoomPost::query()->where('room_id', $room->id)->orderByDesc('created_at')
                    : ExchangeRoomPost::query()->whereRaw('1 = 0')
            )
            ->columns([
                Tables\Columns\TextColumn::make('actor_side')
                    ->label('Side')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'internal' => 'primary',
                        'vendor' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => $state === 'internal' ? 'Digittal' : 'Vendor'),
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
                Tables\Actions\Action::make('upload_version')
                    ->label('Upload Revised Document / Post Reply')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->visible(fn (): bool => $this->contract->exchangeRoom?->isOpen() === true)
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('Document (PDF / DOCX)')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->disk(config('ccrs.contracts_disk'))
                            ->directory('exchange_room_uploads')
                            ->maxSize(51200)
                            ->helperText('Optional. Upload a revised document version (max 50 MB).'),
                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->rows(3)
                            ->helperText('Optional. Leave a comment or note.'),
                    ])
                    ->action(function (array $data): void {
                        $room = $this->contract->exchangeRoom;
                        $vendorUser = auth('vendor')->user();
                        $service = app(ExchangeRoomService::class);

                        $uploadedFile = null;
                        if (! empty($data['file'])) {
                            $disk = config('ccrs.contracts_disk', 'local');
                            $filePath = $data['file'];
                            $uploadedFile = new \Illuminate\Http\UploadedFile(
                                \Illuminate\Support\Facades\Storage::disk($disk)->path($filePath),
                                basename($filePath),
                            );
                        }

                        $post = $service->post(
                            $room,
                            $vendorUser,
                            'vendor',
                            $data['message'] ?? null,
                            $uploadedFile,
                        );

                        // Notify internal team via Teams (non-fatal)
                        try {
                            $teamsService = app(TeamsNotificationService::class);
                            if ($teamsService->isConfigured()) {
                                $teamsService->sendToChannel(
                                    "Vendor document exchange: {$this->contract->title}",
                                    "**{$vendorUser->name}** (vendor) posted " .
                                    ($post->hasFile() ? "version v{$post->version_number}" : 'a message') .
                                    " in the exchange room for \"{$this->contract->title}\".",
                                );
                            }
                        } catch (\Throwable) {
                            // Teams notification is non-fatal
                        }

                        // Email contract creator when vendor uploads a file version
                        if ($post->hasFile() && $this->contract->created_by) {
                            try {
                                $creator = User::find($this->contract->created_by);
                                if ($creator?->email) {
                                    Mail::to($creator->email)
                                        ->queue(new ExchangeRoomNewVersionMail($post, $this->contract->title));
                                }
                            } catch (\Throwable) {
                                // Email notification is non-fatal
                            }
                        }

                        Notification::make()
                            ->title($post->hasFile() ? "Version v{$post->version_number} uploaded" : 'Message posted')
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

    public function getRoomStatus(): string
    {
        return $this->contract->exchangeRoom?->status ?? 'unknown';
    }

    public function getNegotiationStage(): string
    {
        $stage = $this->contract->exchangeRoom?->negotiation_stage ?? 'unknown';

        return ucwords(str_replace('_', ' ', $stage));
    }
}
