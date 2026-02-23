<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use App\Models\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?int $navigationSort = 32;
    protected static ?string $navigationGroup = 'Admin';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('recipient_email')->email()->required()->maxLength(255),
            Forms\Components\TextInput::make('subject')->required()->maxLength(255),
            Forms\Components\Textarea::make('body')->required()->rows(5),
            Forms\Components\Select::make('channel')
                ->options(['email' => 'Email', 'in_app' => 'In-App', 'teams' => 'Teams'])
                ->default('email')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('subject')->searchable()->sortable()->limit(40),
            Tables\Columns\TextColumn::make('recipient_email')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('channel')->sortable(),
            Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match($state) { 'pending' => 'warning', 'sent' => 'success', 'failed' => 'danger', default => 'gray' }),
            Tables\Columns\TextColumn::make('sent_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('channel')->options(['email' => 'Email', 'in_app' => 'In-App', 'teams' => 'Teams']),
            Tables\Filters\SelectFilter::make('status')->options(['pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed']),
        ])
        ->defaultSort('created_at', 'desc')
        ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
            'create' => Pages\CreateNotification::route('/create'),
            'edit' => Pages\EditNotification::route('/{record}/edit'),
        ];
    }
}
