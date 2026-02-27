<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class KeyDatesRelationManager extends RelationManager
{
    protected static string $relationship = 'keyDates';
    protected static ?string $title = 'Key Dates';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('date_type')->required()->maxLength(100)
                ->helperText('Type of date milestone (e.g. execution, expiry, renewal).'),
            Forms\Components\DatePicker::make('date_value')->required()
                ->helperText('The actual date for this milestone.'),
            Forms\Components\Textarea::make('description')
                ->helperText('Additional notes about this key date.'),
            Forms\Components\TagsInput::make('reminder_days')->placeholder('Add days')
                ->helperText('Days before the date to send reminders (e.g. 30, 14, 7).'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('date_type'),
            Tables\Columns\TextColumn::make('date_value')->date(),
            Tables\Columns\IconColumn::make('is_verified')->boolean(),
        ])->headerActions([Tables\Actions\CreateAction::make()]);
    }
}
