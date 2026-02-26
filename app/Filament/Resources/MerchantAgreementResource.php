<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MerchantAgreementResource\Pages;
use App\Models\Contract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MerchantAgreementResource extends Resource
{
    protected static ?string $model = Contract::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Merchant Agreements';
    protected static ?string $modelLabel = 'Merchant Agreement';
    protected static ?string $pluralModelLabel = 'Merchant Agreements';
    protected static ?string $slug = 'merchant-agreements';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('contract_type', 'Merchant');

        $user = auth()->user();
        if ($user && !$user->hasRole('system_admin')) {
            $userId = $user->id;
            $query->where(function (Builder $q) use ($userId) {
                $q->where('is_restricted', false)
                  ->orWhereHas('authorizedUsers', fn (Builder $sub) => $sub->where('users.id', $userId));
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('region_id')->relationship('region', 'name')->required()->searchable()->live(),
            Forms\Components\Select::make('entity_id')->relationship('entity', 'name')->required()->searchable()->live(),
            Forms\Components\Select::make('project_id')->relationship('project', 'name')->required()->searchable(),
            Forms\Components\Select::make('counterparty_id')->relationship('counterparty', 'legal_name')->required()->searchable(),
            Forms\Components\Hidden::make('contract_type')->default('Merchant'),
            Forms\Components\TextInput::make('title')->maxLength(255),
            Forms\Components\FileUpload::make('storage_path')->label('Agreement File')->disk('s3')->directory('contracts')
                ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                ->afterStateUpdated(function ($state, Set $set) {
                    if ($state instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                        $set('file_name', $state->getClientOriginalName());
                    }
                }),
            Forms\Components\Hidden::make('file_name'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(40),
            Tables\Columns\TextColumn::make('workflow_state')->badge()->color(fn ($state) => match($state) { 'draft' => 'gray', 'review' => 'warning', 'approval' => 'info', 'signing' => 'primary', 'executed' => 'success', 'archived' => 'gray', default => 'gray' }),
            Tables\Columns\TextColumn::make('counterparty.legal_name')->sortable()->limit(30),
            Tables\Columns\TextColumn::make('region.name')->sortable(),
            Tables\Columns\TextColumn::make('entity.name')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('workflow_state')->options([
                'draft' => 'Draft', 'review' => 'Review', 'approval' => 'Approval',
                'signing' => 'Signing', 'executed' => 'Executed', 'archived' => 'Archived',
            ]),
            Tables\Filters\SelectFilter::make('region_id')->relationship('region', 'name'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('download')->icon('heroicon-o-arrow-down-tray')
                ->url(fn (Contract $record) => $record->storage_path ? route('contract.download', $record) : null)
                ->visible(fn (Contract $record) => (bool) $record->storage_path),
            Tables\Actions\Action::make('generate_docx')
                ->label('Generate DOCX')
                ->icon('heroicon-o-document-arrow-down')
                ->visible(fn (Contract $record) => !$record->storage_path)
                ->requiresConfirmation()
                ->action(function (Contract $record) {
                    $input = $record->merchantAgreementInputs()->first();
                    if (!$input || !$input->template_id) {
                        \Filament\Notifications\Notification::make()->title('No template linked')->danger()->send();
                        return;
                    }
                    $actor = auth()->user();
                    if (!$actor) {
                        \Filament\Notifications\Notification::make()->title('Not authenticated')->danger()->send();
                        return;
                    }
                    app(\App\Services\MerchantAgreementService::class)->generate($record, [
                        'template_id' => $input->template_id,
                        'vendor_name' => $input->vendor_name,
                        'merchant_fee' => $input->merchant_fee,
                        'region_terms' => $input->region_terms,
                    ], $actor);
                    \Filament\Notifications\Notification::make()->title('DOCX generated')->success()->send();
                }),
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial']) ?? false;
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
            'index' => Pages\ListMerchantAgreements::route('/'),
            'create' => Pages\CreateMerchantAgreement::route('/create'),
            'edit' => Pages\EditMerchantAgreement::route('/{record}/edit'),
        ];
    }
}
