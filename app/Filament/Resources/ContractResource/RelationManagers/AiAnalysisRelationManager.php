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
            Tables\Columns\TextColumn::make('analysis_type')
                ->badge()
                ->color(fn ($state) => match ($state) {
                    'summary' => 'gray',
                    'extraction' => 'info',
                    'risk' => 'warning',
                    'deviation' => 'purple',
                    'obligations' => 'success',
                    'discovery' => 'primary',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('status')
                ->badge()
                ->color(fn ($state) => match ($state) {
                    'pending' => 'gray',
                    'processing' => 'warning',
                    'completed' => 'success',
                    'failed' => 'danger',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('error_message')
                ->label('Error')
                ->limit(80)
                ->tooltip(fn ($record) => $record->error_message)
                ->color('danger')
                ->placeholder('—'),
            Tables\Columns\TextColumn::make('model_used')
                ->label('Model')
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('confidence_score')
                ->numeric(decimalPlaces: 2)
                ->toggleable(),
            Tables\Columns\TextColumn::make('cost_usd')
                ->money('USD')
                ->toggleable(),
            Tables\Columns\TextColumn::make('processing_time_ms')
                ->label('Time')
                ->formatStateUsing(fn ($state) => $state ? round($state / 1000, 1) . 's' : '—')
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
        ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('view_result')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn ($record) => ucfirst($record->analysis_type) . ' Analysis')
                    ->modalContent(fn ($record) => view('filament.modals.ai-analysis-detail', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->visible(fn ($record) => in_array($record->status, ['completed', 'failed'])),
            ])
            ->poll('5s');
    }
}
