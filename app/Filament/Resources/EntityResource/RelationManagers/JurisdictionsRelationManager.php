<?php

namespace App\Filament\Resources\EntityResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class JurisdictionsRelationManager extends RelationManager
{
    protected static string $relationship = 'jurisdictions';
    protected static ?string $title = 'Jurisdictions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Jurisdiction'),
                Tables\Columns\TextColumn::make('pivot.license_number')->label('License #'),
                Tables\Columns\TextColumn::make('pivot.license_expiry')->date()->label('Expiry'),
                Tables\Columns\IconColumn::make('pivot.is_primary')->boolean()->label('Primary'),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('license_number')->maxLength(100)
                            ->helperText('Trade license or regulatory license number for this jurisdiction.'),
                        Forms\Components\DatePicker::make('license_expiry')
                            ->helperText('Date when this license expires and needs renewal.'),
                        Forms\Components\Toggle::make('is_primary')->default(false)
                            ->helperText('Whether this is the entity\'s primary operating jurisdiction.'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('license_number')->maxLength(100)
                            ->helperText('Trade license or regulatory license number for this jurisdiction.'),
                        Forms\Components\DatePicker::make('license_expiry')
                            ->helperText('Date when this license expires and needs renewal.'),
                        Forms\Components\Toggle::make('is_primary')->default(false)
                            ->helperText('Whether this is the entity\'s primary operating jurisdiction.'),
                    ]),
                Tables\Actions\DetachAction::make(),
            ]);
    }
}
