<?php

namespace App\Filament\Pages;

use App\Models\EscalationEvent;
use App\Services\EscalationService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EscalationsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-fire';
    protected static ?string $navigationGroup = 'Workflows';
    protected static ?string $title = 'Escalations';
    protected static string $view = 'filament.pages.escalations-page';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EscalationEvent::query()
                    ->with(['contract', 'workflowInstance', 'rule'])
                    ->latest('escalated_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('contract.title')
                    ->label('Contract')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('stage_name')
                    ->label('Stage')
                    ->badge(),
                Tables\Columns\TextColumn::make('tier')
                    ->label('Tier')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 3 => 'danger',
                        $state === 2 => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('escalated_at')
                    ->label('Escalated')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Resolved')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Unresolved')
                    ->sortable(),
                Tables\Columns\TextColumn::make('resolved_by')
                    ->label('Resolved By')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\Filter::make('unresolved')
                    ->label('Unresolved Only')
                    ->query(fn (Builder $query) => $query->whereNull('resolved_at'))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Resolve Escalation')
                    ->modalDescription('Mark this escalation as resolved?')
                    ->visible(fn (EscalationEvent $record) => $record->resolved_at === null)
                    ->action(function (EscalationEvent $record) {
                        app(EscalationService::class)->resolveEscalation($record->id, auth()->user());
                    }),
            ])
            ->defaultSort('escalated_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
