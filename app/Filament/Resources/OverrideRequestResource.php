<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OverrideRequestResource\Pages;
use App\Models\OverrideRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OverrideRequestResource extends Resource
{
    protected static ?string $model = OverrideRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?int $navigationSort = 21;
    protected static ?string $navigationGroup = 'Counterparties';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('counterparty_id')
                ->relationship('counterparty', 'legal_name')
                ->required()
                ->searchable()
                ->disabled(fn (string $operation): bool => $operation === 'edit'),
            Forms\Components\TextInput::make('contract_title')->maxLength(255),
            Forms\Components\Textarea::make('reason')->required()->rows(4),
            Forms\Components\Select::make('status')
                ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                ->default('pending')
                ->required(),
            Forms\Components\TextInput::make('decided_by')->maxLength(255),
            Forms\Components\Textarea::make('comment')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('counterparty.legal_name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('contract_title')->searchable()->limit(30),
            Tables\Columns\BadgeColumn::make('status')->colors(['warning' => 'pending', 'success' => 'approved', 'danger' => 'rejected']),
            Tables\Columns\TextColumn::make('requested_by_email')->searchable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
        ])
        ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOverrideRequests::route('/'),
            'create' => Pages\CreateOverrideRequest::route('/create'),
            'edit' => Pages\EditOverrideRequest::route('/{record}/edit'),
        ];
    }
}
