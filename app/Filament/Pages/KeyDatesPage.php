<?php

namespace App\Filament\Pages;

use App\Models\ContractKeyDate;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class KeyDatesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?string $title = 'Key Dates';
    protected static string $view = 'filament.pages.key-dates-page';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ContractKeyDate::query()
                    ->with('contract')
                    ->orderBy('date_value')
            )
            ->columns([
                Tables\Columns\TextColumn::make('contract.title')
                    ->label('Contract')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('date_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('date_value')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (ContractKeyDate $record) => $record->date_value?->isPast() ? 'danger' : null),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(60),
                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean(),
                Tables\Columns\TextColumn::make('verified_by')
                    ->label('Verified By')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('date_type')
                    ->label('Date Type')
                    ->options([
                        'effective_date' => 'Effective Date',
                        'expiry_date' => 'Expiry Date',
                        'renewal_date' => 'Renewal Date',
                        'termination_date' => 'Termination Date',
                        'review_date' => 'Review Date',
                    ]),
                Tables\Filters\Filter::make('upcoming')
                    ->label('Upcoming (next 90 days)')
                    ->query(fn ($query) => $query->whereBetween('date_value', [now(), now()->addDays(90)]))
                    ->default(),
            ])
            ->defaultSort('date_value')
            ->paginated([10, 25, 50]);
    }
}
