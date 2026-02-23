<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AiAnalysisRelationManager extends RelationManager
{
    protected static string $relationship = 'aiAnalyses';
    protected static ?string $title = 'AI Analysis';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('analysis_type'),
            Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match($state) { 'pending' => 'gray', 'processing' => 'warning', 'completed' => 'success', 'failed' => 'danger', default => 'gray' }),
            Tables\Columns\TextColumn::make('confidence_score')->numeric(decimalPlaces: 2),
            Tables\Columns\TextColumn::make('cost_usd')->money('USD'),
            Tables\Columns\TextColumn::make('created_at')->dateTime(),
        ])->headerActions([]);
    }
}
