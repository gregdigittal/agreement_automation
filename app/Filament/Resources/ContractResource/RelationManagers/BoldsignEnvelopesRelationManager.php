<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BoldsignEnvelopesRelationManager extends RelationManager
{
    protected static string $relationship = 'boldsignEnvelopes';
    protected static ?string $title = 'Boldsign Envelopes';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('boldsign_document_id')->limit(30),
            Tables\Columns\BadgeColumn::make('status'),
            Tables\Columns\TextColumn::make('sent_at')->dateTime(),
            Tables\Columns\TextColumn::make('completed_at')->dateTime(),
        ])->headerActions([]);
    }
}
