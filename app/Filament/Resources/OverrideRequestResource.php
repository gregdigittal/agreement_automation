<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OverrideRequestResource\Pages;
use App\Models\OverrideRequest;
use App\Services\AuditService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OverrideRequestResource extends Resource
{
    protected static ?string $model = OverrideRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?int $navigationSort = 21;
    protected static ?string $navigationGroup = 'Counterparties';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('counterparty_id')
                ->relationship('counterparty', 'legal_name')
                ->required()
                ->searchable()
                ->disabled(fn (string $operation): bool => $operation === 'edit'),
            Forms\Components\TextInput::make('contract_title')->maxLength(255),
            Forms\Components\Textarea::make('reason')->required()->rows(4),
            Forms\Components\Select::make('status')
                ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                ->default('pending')
                ->required()
                ->disabled(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(),
            Forms\Components\TextInput::make('decided_by')->maxLength(255)
                ->disabled()
                ->dehydrated(false),
            Forms\Components\Textarea::make('comment')->rows(3)
                ->disabled(fn (string $operation): bool => $operation === 'create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('counterparty.legal_name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('contract_title')->searchable()->limit(30),
            Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match($state) { 'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', default => 'gray' }),
            Tables\Columns\TextColumn::make('requested_by_email')->searchable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
        ])
        ->actions([
            Tables\Actions\Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Override Request')
                ->modalDescription('This will allow the counterparty override to proceed.')
                ->form([
                    Forms\Components\Textarea::make('comment')->label('Comment (optional)')->rows(3),
                ])
                ->action(function (OverrideRequest $record, array $data) {
                    $record->update([
                        'status' => 'approved',
                        'decided_by' => auth()->user()->email,
                        'decided_at' => now(),
                        'comment' => $data['comment'] ?? null,
                    ]);
                    AuditService::log(
                        action: 'override_approved',
                        resourceType: 'override_request',
                        resourceId: $record->id,
                        details: ['counterparty_id' => $record->counterparty_id],
                    );
                    Notification::make()->title('Override request approved')->success()->send();
                })
                ->visible(fn (OverrideRequest $record) => $record->status === 'pending' && auth()->user()?->hasAnyRole(['system_admin', 'legal'])),
            Tables\Actions\Action::make('reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reject Override Request')
                ->modalDescription('Please provide a reason for rejection.')
                ->form([
                    Forms\Components\Textarea::make('comment')->label('Reason for rejection')->required()->rows(3),
                ])
                ->action(function (OverrideRequest $record, array $data) {
                    $record->update([
                        'status' => 'rejected',
                        'decided_by' => auth()->user()->email,
                        'decided_at' => now(),
                        'comment' => $data['comment'],
                    ]);
                    AuditService::log(
                        action: 'override_rejected',
                        resourceType: 'override_request',
                        resourceId: $record->id,
                        details: ['counterparty_id' => $record->counterparty_id, 'reason' => $data['comment']],
                    );
                    Notification::make()->title('Override request rejected')->danger()->send();
                })
                ->visible(fn (OverrideRequest $record) => $record->status === 'pending' && auth()->user()?->hasAnyRole(['system_admin', 'legal'])),
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOverrideRequests::route('/'),
            'create' => Pages\CreateOverrideRequest::route('/create'),
            'edit' => Pages\EditOverrideRequest::route('/{record}/edit'),
        ];
    }
}
