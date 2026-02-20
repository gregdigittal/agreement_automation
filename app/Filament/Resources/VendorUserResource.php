<?php
namespace App\Filament\Resources;

use App\Filament\Resources\VendorUserResource\Pages;
use App\Models\VendorUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorUserResource extends Resource
{
    protected static ?string $model = VendorUser::class;
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Vendor Users';
    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            Forms\Components\Select::make('counterparty_id')->label('Counterparty')->relationship('counterparty', 'legal_name')->searchable()->preload()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('email')->searchable(),
            Tables\Columns\TextColumn::make('counterparty.legal_name')->label('Counterparty')->searchable(),
            Tables\Columns\TextColumn::make('last_login_at')->label('Last Login')->since()->default('Never'),
            Tables\Columns\TextColumn::make('created_at')->since(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorUsers::route('/'),
            'create' => Pages\CreateVendorUser::route('/create'),
            'edit' => Pages\EditVendorUser::route('/{record}/edit'),
        ];
    }
}
