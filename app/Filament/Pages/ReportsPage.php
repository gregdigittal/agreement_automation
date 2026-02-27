<?php

namespace App\Filament\Pages;

use App\Models\Contract;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ReportsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $title = 'Reports';
    protected static string $view = 'filament.pages.reports-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'finance', 'audit']) ?? false;
    }

    public function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->url(fn () => route('reports.export.contracts.excel', request()->query()))
                ->openUrlInNewTab(),
            \Filament\Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->url(fn () => route('reports.export.contracts.pdf', request()->query()))
                ->openUrlInNewTab(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Contract::query()
                    ->with(['counterparty', 'region', 'entity'])
                    ->latest('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('contract_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('counterparty.legal_name')
                    ->label('Counterparty')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('region.name')
                    ->label('Region'),
                Tables\Columns\TextColumn::make('entity.name')
                    ->label('Entity'),
                Tables\Columns\TextColumn::make('workflow_state')
                    ->label('State')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'gray',
                        'review' => 'info',
                        'approval' => 'warning',
                        'signing' => 'primary',
                        'countersign' => 'purple',
                        'executed' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('workflow_state')
                    ->label('State')
                    ->options([
                        'draft' => 'Draft',
                        'review' => 'Review',
                        'approval' => 'Approval',
                        'signing' => 'Signing',
                        'countersign' => 'Countersign',
                        'executed' => 'Executed',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\SelectFilter::make('contract_type')
                    ->label('Type')
                    ->options(
                        Contract::distinct()->pluck('contract_type', 'contract_type')->toArray()
                    ),
                Tables\Filters\SelectFilter::make('region_id')
                    ->label('Region')
                    ->relationship('region', 'name'),
                Tables\Filters\SelectFilter::make('entity_id')
                    ->label('Entity')
                    ->relationship('entity', 'name'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }
}
