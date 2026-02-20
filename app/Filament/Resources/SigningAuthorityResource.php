<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SigningAuthorityResource\Pages;
use App\Models\SigningAuthority;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SigningAuthorityResource extends Resource
{
    protected static ?string $model = SigningAuthority::class;
    protected static ?string $navigationIcon = 'heroicon-o-pen-nib';
    protected static ?string $navigationGroup = 'Org Structure';
    protected static ?int $navigationSort = 13;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('entity_id')->relationship('entity', 'name')->required()->searchable(),
            Forms\Components\Select::make('project_id')->relationship('project', 'name')->searchable(),
            Forms\Components\TextInput::make('user_id')->maxLength(255),
            Forms\Components\TextInput::make('user_email')->email()->required()->maxLength(255),
            Forms\Components\TextInput::make('role_or_name')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('entity.name')->sortable(),
            Tables\Columns\TextColumn::make('user_email')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('role_or_name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('project.name')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSigningAuthorities::route('/'),
            'create' => Pages\CreateSigningAuthority::route('/create'),
            'edit' => Pages\EditSigningAuthority::route('/{record}/edit'),
        ];
    }
}
