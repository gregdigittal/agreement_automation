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
            Forms\Components\Select::make('region_id')->relationship('region', 'name')->required()->searchable(),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('code')->maxLength(50),

            Forms\Components\Section::make('Legal Details')->schema([
                Forms\Components\TextInput::make('legal_name')
                    ->maxLength(500)
                    ->placeholder('Full legal entity name'),
                Forms\Components\TextInput::make('registration_number')
                    ->maxLength(100),
                Forms\Components\Textarea::make('registered_address')
                    ->rows(2),
                Forms\Components\Select::make('parent_entity_id')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('None (top-level entity)'),
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
