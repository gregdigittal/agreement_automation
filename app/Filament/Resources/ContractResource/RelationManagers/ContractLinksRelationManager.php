<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Models\ContractLink;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContractLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'parentLinks';
    protected static ?string $title = 'Amendments, Renewals & Side Letters';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('link_type')
                ->options([
                    'amendment' => 'Amendment',
                    'renewal' => 'Renewal',
                    'side_letter' => 'Side Letter',
                    'addendum' => 'Addendum',
                ])
                ->required(),
            Forms\Components\Select::make('child_contract_id')
                ->label('Linked Contract')
                ->relationship('childContract', 'title')
                ->searchable()
                ->preload()
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('link_type')->badge()
                    ->color(fn ($state) => match($state) { 'amendment' => 'warning', 'renewal' => 'info', 'side_letter' => 'success', 'addendum' => 'gray', default => 'gray' }),
                Tables\Columns\TextColumn::make('childContract.title')
                    ->label('Contract')
                    ->searchable(),
                Tables\Columns\TextColumn::make('childContract.workflow_state')
                    ->label('State')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Link Contract'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (ContractLink $record) =>
                        \App\Filament\Resources\ContractResource::getUrl('edit', [
                            'record' => $record->child_contract_id,
                        ])
                    )
                    ->icon('heroicon-o-arrow-top-right-on-square'),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
