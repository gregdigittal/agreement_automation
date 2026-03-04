<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractTypeResource\Pages;
use App\Models\ContractType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractTypeResource extends Resource
{
    protected static ?string $model = ContractType::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Contract Type Details')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->placeholder('e.g. Framework Agreement')
                        ->helperText('Display name used in dropdowns and reports.')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $state, Set $set, ?Model $record) {
                            if (! $record?->exists) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->placeholder('e.g. framework-agreement')
                        ->helperText('URL-safe identifier (auto-generated from name).'),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear first in dropdowns.'),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->helperText('Inactive types are hidden from all dropdowns but existing contracts retain the value.'),
                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull()
                        ->placeholder('Optional description of when to use this contract type.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('sort_order')
                ->label('#')
                ->sortable()
                ->width('60px'),
            Tables\Columns\TextColumn::make('name')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('slug')
                ->badge()
                ->color('gray'),
            Tables\Columns\TextColumn::make('description')
                ->limit(50)
                ->toggleable(),
            Tables\Columns\IconColumn::make('is_active')
                ->boolean()
                ->sortable(),
            Tables\Columns\TextColumn::make('updated_at')
                ->since()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->defaultSort('sort_order')
        ->reorderable('sort_order')
        ->actions([
            Tables\Actions\EditAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
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
            'index' => Pages\ListContractTypes::route('/'),
            'create' => Pages\CreateContractType::route('/create'),
            'edit' => Pages\EditContractType::route('/{record}/edit'),
        ];
    }
}
