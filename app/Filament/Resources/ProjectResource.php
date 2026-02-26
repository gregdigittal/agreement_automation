<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;
    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Org Structure';
    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('entity_id')
                ->relationship('entity', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->helperText('The entity this project belongs to.'),
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g. TiTo Platform v2')
                ->helperText('A descriptive name for this project.'),
            Forms\Components\TextInput::make('code')
                ->maxLength(50)
                ->placeholder('e.g. PRJ-001')
                ->helperText('Short project code used in CSV uploads and contract filters.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('code')->sortable(),
            Tables\Columns\TextColumn::make('entity.name')->sortable(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
