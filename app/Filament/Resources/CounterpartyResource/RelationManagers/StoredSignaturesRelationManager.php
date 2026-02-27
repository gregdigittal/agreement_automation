<?php

namespace App\Filament\Resources\CounterpartyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StoredSignaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'storedSignatures';
    protected static ?string $title = 'Stored Signatures';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('label')
                ->maxLength(100)
                ->placeholder('e.g. Company signature')
                ->helperText('A descriptive name for this signature (e.g. CEO Signature).'),

            Forms\Components\TextInput::make('signer_email')
                ->email()
                ->maxLength(255)
                ->placeholder('contact@company.com')
                ->helperText('Email of the person this signature belongs to.'),

            Forms\Components\Select::make('type')
                ->options(['signature' => 'Signature', 'initials' => 'Initials'])
                ->default('signature')
                ->required()
                ->helperText('Signature appearance style (drawn, typed, or uploaded image).'),

            Forms\Components\Select::make('capture_method')
                ->options(['draw' => 'Draw', 'type' => 'Type', 'upload' => 'Upload', 'webcam' => 'Camera'])
                ->default('upload')
                ->required()
                ->helperText('How this signature was originally captured.'),

            Forms\Components\Toggle::make('is_default')
                ->label('Default')
                ->helperText('Use this signature by default when signing contracts.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('label')->searchable(),
            Tables\Columns\TextColumn::make('signer_email'),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('capture_method'),
            Tables\Columns\IconColumn::make('is_default')->boolean()->label('Default'),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->headerActions([
            Tables\Actions\CreateAction::make(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }
}
