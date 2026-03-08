<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EntityShareholdingResource\Pages;
use App\Models\EntityShareholding;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EntityShareholdingResource extends Resource
{
    protected static ?string $model = EntityShareholding::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationGroup = 'Organization';
    protected static ?int $navigationSort = 14;
    protected static ?string $navigationLabel = 'Shareholdings';
    protected static ?string $modelLabel = 'Shareholding';
    protected static ?string $pluralModelLabel = 'Shareholdings';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('owner_entity_id')
                ->relationship('owner', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText('The parent entity that holds the ownership stake.'),
            Forms\Components\Select::make('owned_entity_id')
                ->relationship('owned', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText('The subsidiary or investee entity that is owned.')
                ->rules([
                    fn (Forms\Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                        if ($value && $value === $get('owner_entity_id')) {
                            $fail('An entity cannot own itself.');
                        }
                    },
                ]),
            Forms\Components\TextInput::make('percentage')
                ->required()
                ->numeric()
                ->minValue(0.01)
                ->maxValue(100)
                ->step(0.01)
                ->suffix('%')
                ->helperText('Shareholding percentage (0.01–100). Total ownership of an entity cannot exceed 100%.')
                ->rules([
                    fn (Forms\Get $get, ?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                        $ownedEntityId = $get('owned_entity_id');
                        if (! $ownedEntityId) {
                            return;
                        }

                        $query = EntityShareholding::where('owned_entity_id', $ownedEntityId);

                        if ($record) {
                            $query->where('id', '!=', $record->id);
                        }

                        $existingSum = $query->sum('percentage');

                        if (($existingSum + (float) $value) > 100) {
                            $remaining = round(100 - $existingSum, 2);
                            $fail("Total shareholding would exceed 100%. Remaining capacity: {$remaining}%.");
                        }
                    },
                ]),
            Forms\Components\Select::make('ownership_type')
                ->options([
                    'direct' => 'Direct',
                    'indirect' => 'Indirect',
                    'beneficial' => 'Beneficial',
                    'nominee' => 'Nominee',
                ])
                ->default('direct')
                ->required()
                ->helperText('The nature of the ownership relationship.'),
            Forms\Components\DatePicker::make('effective_date')
                ->helperText('Date from which this shareholding is effective. Leave blank if unknown.'),
            Forms\Components\Textarea::make('notes')
                ->maxLength(1000)
                ->columnSpanFull()
                ->helperText('Optional notes about this shareholding arrangement.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner Entity')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owned.name')
                    ->label('Owned Entity')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('percentage')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ownership_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('effective_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntityShareholdings::route('/'),
            'create' => Pages\CreateEntityShareholding::route('/create'),
            'edit' => Pages\EditEntityShareholding::route('/{record}/edit'),
        ];
    }
}
