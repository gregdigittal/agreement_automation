<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CounterpartyResource\Pages;
use App\Models\Counterparty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CounterpartyResource extends Resource
{
    protected static ?string $model = Counterparty::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Counterparties';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('legal_name')->required()->maxLength(255),
            Forms\Components\TextInput::make('registration_number')->maxLength(255),
            Forms\Components\Textarea::make('address'),
            Forms\Components\TextInput::make('jurisdiction')->maxLength(255),
            Forms\Components\Select::make('status')->options(['Active' => 'Active', 'Suspended' => 'Suspended', 'Blacklisted' => 'Blacklisted'])->required(),
            Forms\Components\Textarea::make('status_reason'),
            Forms\Components\Select::make('preferred_language')->options(['en' => 'English', 'fr' => 'French', 'es' => 'Spanish', 'de' => 'German'])->default('en'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('legal_name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('registration_number')->searchable(),
            Tables\Columns\BadgeColumn::make('status')->colors(['success' => 'Active', 'warning' => 'Suspended', 'danger' => 'Blacklisted']),
            Tables\Columns\TextColumn::make('jurisdiction'),
            Tables\Columns\TextColumn::make('contracts_count')->counts('contracts')->label('Contracts'),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCounterparties::route('/'),
            'create' => Pages\CreateCounterparty::route('/create'),
            'edit' => Pages\EditCounterparty::route('/{record}/edit'),
        ];
    }
}
