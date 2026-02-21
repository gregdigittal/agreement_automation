<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CounterpartyResource\Pages;
use App\Filament\Resources\CounterpartyResource\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\CounterpartyResource\RelationManagers\VendorDocumentsRelationManager;
use App\Models\Counterparty;
use App\Models\CounterpartyMerge;
use App\Models\OverrideRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CounterpartyResource extends Resource
{
    protected static ?string $model = Counterparty::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationGroup = 'Counterparties';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('legal_name')->required()->maxLength(255),
            Forms\Components\TextInput::make('registration_number')->maxLength(255),
            Forms\Components\Textarea::make('address'),
            Forms\Components\TextInput::make('jurisdiction')->maxLength(255),
            Forms\Components\Select::make('status')
                ->options(['Active' => 'Active', 'Suspended' => 'Suspended', 'Blacklisted' => 'Blacklisted'])
                ->required()
                ->live(),
            Forms\Components\Textarea::make('status_reason')
                ->visible(fn (\Filament\Forms\Get $get) => $get('status') !== 'Active'),
            Forms\Components\Select::make('preferred_language')
                ->options([
                    'en' => 'English', 'fr' => 'French', 'ar' => 'Arabic', 'es' => 'Spanish',
                    'zh' => 'Chinese', 'pt' => 'Portuguese', 'de' => 'German',
                ])
                ->default('en'),
            Forms\Components\Hidden::make('duplicate_acknowledged')->default(false),
            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('check_duplicates')->label('Check for Duplicates')->color('warning')->icon('heroicon-o-magnifying-glass')->action(function (array $data, \Filament\Forms\Set $set) {
                    $duplicates = app(\App\Services\CounterpartyService::class)->findDuplicates($data['legal_name'] ?? '', $data['registration_number'] ?? '', $data['id'] ?? null);
                    if ($duplicates->isEmpty()) { \Filament\Notifications\Notification::make()->title('No duplicates found')->success()->send(); $set('duplicate_acknowledged', true); return; }
                    $list = $duplicates->map(fn ($d) => "- " . $d->legal_name . " (" . $d->registration_number . ") - " . $d->status)->implode("\n");
                    \Filament\Notifications\Notification::make()->title('Possible duplicates found')->body("The following may be duplicates:\n\n" . $list)->warning()->persistent()->actions([\Filament\Notifications\Actions\Action::make('acknowledge')->label('Proceed anyway')->button()->close()->action(fn () => $set('duplicate_acknowledged', true))])->send();
                }),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('legal_name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('registration_number')->searchable(),
            Tables\Columns\TextColumn::make('jurisdiction')->searchable(),
            Tables\Columns\BadgeColumn::make('status')->colors(['success' => 'Active', 'warning' => 'Suspended', 'danger' => 'Blacklisted']),
            Tables\Columns\TextColumn::make('preferred_language'),
            Tables\Columns\TextColumn::make('contracts_count')->counts('contracts')->label('Contracts')->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('status')->options(['Active' => 'Active', 'Suspended' => 'Suspended', 'Blacklisted' => 'Blacklisted']),
            Tables\Filters\Filter::make('jurisdiction')->form([Forms\Components\TextInput::make('jurisdiction')->placeholder('Jurisdiction')])
                ->query(fn (Builder $q, array $data) => !empty($data['jurisdiction']) ? $q->where('jurisdiction', 'like', '%'.$data['jurisdiction'].'%') : $q),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('override_request')
                ->label('Override Request')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->hasRole('commercial') ?? false)
                ->form([
                    Forms\Components\Textarea::make('reason')->required()->rows(3),
                    Forms\Components\TextInput::make('contract_title')->placeholder('Contract title (optional)'),
                ])
                ->action(function (Counterparty $record, array $data): void {
                    OverrideRequest::create([
                        'counterparty_id' => $record->id,
                        'contract_title' => $data['contract_title'] ?? null,
                        'requested_by_email' => auth()->user()?->email,
                        'reason' => $data['reason'],
                        'status' => 'pending',
                    ]);
                    \Filament\Notifications\Notification::make()->title('Override request submitted')->success()->send();
                }),
            Tables\Actions\Action::make('merge')
                ->label('Merge')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('danger')
                ->visible(fn (): bool => auth()->user()?->hasRole('system_admin') ?? false)
                ->form(fn (Counterparty $record) => [
                    Forms\Components\Select::make('target_counterparty_id')
                        ->label('Target counterparty')
                        ->options(Counterparty::where('id', '!=', $record->id)->pluck('legal_name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (Counterparty $record, array $data): void {
                    $targetId = $data['target_counterparty_id'];
                    \DB::transaction(function () use ($record, $targetId) {
                        \App\Models\Contract::where('counterparty_id', $record->id)->update(['counterparty_id' => $targetId]);
                        CounterpartyMerge::create([
                            'source_counterparty_id' => $record->id,
                            'target_counterparty_id' => $targetId,
                            'merged_by' => auth()->id(),
                            'merged_by_email' => auth()->user()?->email,
                            'created_at' => now(),
                        ]);
                    });
                    \Filament\Notifications\Notification::make()->title('Counterparties merged')->success()->send();
                }),
        ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            ContactsRelationManager::class,
            VendorDocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCounterparties::route('/'),
            'create' => Pages\CreateCounterparty::route('/create'),
            'edit' => Pages\EditCounterparty::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->legal_name ?? $record->id;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): ?array
    {
        return [
            'Registration' => $record->registration_number,
            'Status' => $record->status,
        ];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return \App\Filament\Resources\CounterpartyResource::getUrl('edit', ['record' => $record]);
    }

}
