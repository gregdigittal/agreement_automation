<?php

namespace App\Filament\Pages;

use App\Models\Reminder;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class RemindersPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?string $title = 'Reminders';
    protected static string $view = 'filament.pages.reminders-page';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Reminder::query()
                    ->with(['contract', 'keyDate'])
                    ->orderBy('next_due_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('contract.title')
                    ->label('Contract')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('reminder_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'email' => 'info',
                        'calendar' => 'success',
                        'teams' => 'purple',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('lead_days')
                    ->label('Lead Days')
                    ->suffix(' days'),
                Tables\Columns\TextColumn::make('next_due_at')
                    ->label('Next Due')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->color(fn (Reminder $record) => $record->next_due_at?->isPast() ? 'danger' : null),
                Tables\Columns\TextColumn::make('last_sent_at')
                    ->label('Last Sent')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Never'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->label('Active Only')
                    ->query(fn ($query) => $query->where('is_active', true))
                    ->default(),
                Tables\Filters\SelectFilter::make('channel')
                    ->options([
                        'email' => 'Email',
                        'calendar' => 'Calendar',
                        'teams' => 'Teams',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (Reminder $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Reminder $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Reminder $record) => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Reminder $record) => $record->update(['is_active' => !$record->is_active])),
            ])
            ->defaultSort('next_due_at')
            ->paginated([10, 25, 50]);
    }
}
