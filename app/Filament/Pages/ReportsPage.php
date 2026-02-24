<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ReportsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $title = 'Reports';
    protected static string $view = 'filament.pages.reports-page';
    protected static bool $shouldRegisterNavigation = false;

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
}
