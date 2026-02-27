<?php

namespace App\Filament\Resources\CounterpartyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';
    protected static ?string $title = 'Contacts';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('Full name of the contact person.'),
            Forms\Components\TextInput::make('email')->email()->required()
                ->helperText('Contact email address.'),
            Forms\Components\TextInput::make('role')->maxLength(100)
                ->helperText('Job title or role within the counterparty organisation.'),
            Forms\Components\Toggle::make('is_signer')->label('Is Signer')->default(false)
                ->helperText('Whether this contact is authorised to sign contracts.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('email'),
            Tables\Columns\TextColumn::make('role'),
            Tables\Columns\IconColumn::make('is_signer')->boolean()->label('Signer'),
        ])->headerActions([Tables\Actions\CreateAction::make()]);
    }
}
