<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JurisdictionResource\Pages;
use App\Models\Jurisdiction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class JurisdictionResource extends Resource
{
    protected static ?string $model = Jurisdiction::class;
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationGroup = 'Organization';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Jurisdiction Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->placeholder('UAE - DIFC'),
                Forms\Components\TextInput::make('country_code')
                    ->required()
                    ->maxLength(2)
                    ->placeholder('AE')
                    ->helperText('ISO 3166-1 alpha-2 country code'),
                Forms\Components\TextInput::make('regulatory_body')
                    ->maxLength(255)
                    ->placeholder('DIFC Authority'),
                Forms\Components\Textarea::make('notes')
                    ->rows(3),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('country_code')->badge()->sortable(),
                Tables\Columns\TextColumn::make('regulatory_body')->limit(40),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('entities_count')
                    ->counts('entities')
                    ->label('Entities')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
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
            'index' => Pages\ListJurisdictions::route('/'),
            'create' => Pages\CreateJurisdiction::route('/create'),
            'edit' => Pages\EditJurisdiction::route('/{record}/edit'),
        ];
    }
}
