<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';
    protected static ?string $title = 'Reminders';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('reminder_type')->options([
                'expiry' => 'Expiry', 'renewal_notice' => 'Renewal Notice', 'payment' => 'Payment',
                'sla' => 'SLA', 'obligation' => 'Obligation', 'custom' => 'Custom',
            ])->required()
                ->helperText('What triggers this reminder (e.g. key date, obligation, custom).'),
            Forms\Components\TextInput::make('lead_days')->numeric()->required()
                ->helperText('Number of days before the event to send the reminder.'),
            Forms\Components\Select::make('channel')->options(['email' => 'Email', 'teams' => 'Teams', 'calendar' => 'Calendar Invite (.ics)'])->default('email')
                ->helperText('How the reminder will be delivered (email, Teams, etc.).'),
            Forms\Components\TextInput::make('recipient_email')
                ->helperText('Email address to send the reminder to.'),
            Forms\Components\Toggle::make('is_active')->default(true)
                ->helperText('Whether this reminder is currently active.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('reminder_type'),
            Tables\Columns\TextColumn::make('lead_days'),
            Tables\Columns\TextColumn::make('channel'),
            Tables\Columns\TextColumn::make('next_due_at')->dateTime(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->headerActions([Tables\Actions\CreateAction::make()]);
    }
}
