<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoverningLawResource\Pages;
use App\Models\GoverningLaw;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class GoverningLawResource extends Resource
{
    protected static ?string $model = GoverningLaw::class;
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Governing Law Details')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->placeholder('e.g. England and Wales'),
                    Forms\Components\Select::make('country_code')
                        ->label('Country')
                        ->options(\App\Models\Country::dropdownOptions())
                        ->searchable()
                        ->placeholder('Select country'),
                    Forms\Components\Select::make('legal_system')
                        ->options([
                            'Common Law' => 'Common Law',
                            'Civil Law' => 'Civil Law',
                            'Sharia / Civil Law' => 'Sharia / Civil Law',
                            'Mixed' => 'Mixed',
                        ])
                        ->searchable()
                        ->placeholder('Select legal system'),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull()
                        ->placeholder('Optional notes about this governing law.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('country_code')->label('Country')->sortable(),
            Tables\Columns\TextColumn::make('legal_system')->sortable(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('contracts_count')
                ->counts('contracts')
                ->label('Contracts')
                ->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('legal_system')
                ->options([
                    'Common Law' => 'Common Law',
                    'Civil Law' => 'Civil Law',
                    'Sharia / Civil Law' => 'Sharia / Civil Law',
                    'Mixed' => 'Mixed',
                ]),
            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Active')
                ->trueLabel('Active only')
                ->falseLabel('Inactive only'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoverningLaws::route('/'),
            'create' => Pages\CreateGoverningLaw::route('/create'),
            'edit' => Pages\EditGoverningLaw::route('/{record}/edit'),
        ];
    }
}
