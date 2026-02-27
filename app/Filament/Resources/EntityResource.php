<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EntityResource\Pages;
use App\Filament\Resources\EntityResource\RelationManagers;
use App\Models\Entity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EntityResource extends Resource
{
    protected static ?string $model = Entity::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Org Structure';
    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('region_id')
                ->relationship('region', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->helperText('The region this entity belongs to.'),
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g. Digittal UAE')
                ->helperText('The display name for this entity.'),
            Forms\Components\TextInput::make('code')
                ->maxLength(50)
                ->placeholder('e.g. DGT-AE, DGT-UK')
                ->helperText('Short internal identifier used in bulk uploads and reports.'),

            Forms\Components\Section::make('Legal Details')->schema([
                Forms\Components\TextInput::make('legal_name')
                    ->maxLength(500)
                    ->placeholder('Full legal entity name')
                    ->helperText('The official legal name as registered with authorities.'),
                Forms\Components\TextInput::make('registration_number')
                    ->maxLength(100)
                    ->placeholder('e.g. 12345678')
                    ->helperText('Trade license or company registration number.'),
                Forms\Components\Textarea::make('registered_address')
                    ->rows(2)
                    ->helperText('The official registered address of this entity.'),
                Forms\Components\Select::make('parent_entity_id')
                    ->relationship(
                        'parent',
                        'name',
                        fn (\Illuminate\Database\Eloquent\Builder $query, ?Model $record) =>
                            $record ? $query->where('id', '!=', $record->id) : $query
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('None (top-level entity)')
                    ->helperText('Select a parent entity to build the organisational hierarchy.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('code')->sortable(),
            Tables\Columns\TextColumn::make('region.name')->sortable(),
            Tables\Columns\TextColumn::make('projects_count')->counts('projects')->label('Projects'),
        ])->actions([Tables\Actions\EditAction::make()]);
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\JurisdictionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntities::route('/'),
            'create' => Pages\CreateEntity::route('/create'),
            'edit' => Pages\EditEntity::route('/{record}/edit'),
        ];
    }
}
