<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Filament\Resources\ContractResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RedlineSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'redlineSessions';
    protected static ?string $title = 'Redline Sessions';
    protected static ?string $recordTitleAttribute = 'id';

    public function isVisible(): bool
    {
        return \App\Helpers\Feature::enabled('redlining');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('wikiContract.name')
                    ->label('Template')
                    ->default('N/A'),

                Tables\Columns\TextColumn::make('total_clauses')
                    ->label('Total'),

                Tables\Columns\TextColumn::make('reviewed_clauses')
                    ->label('Reviewed'),

                Tables\Columns\TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(fn ($record) => $record->progress_percentage . '%')
                    ->badge()
                    ->color(fn ($record) => $record->isFullyReviewed() ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Started By'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open Review')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => ContractResource::getUrl('redline-session', [
                        'record' => $record->contract_id,
                        'session' => $record->id,
                    ]))
                    ->visible(fn ($record) => $record->status === 'completed'),
            ]);
    }
}
