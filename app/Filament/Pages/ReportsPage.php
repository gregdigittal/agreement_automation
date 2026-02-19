<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;

class ReportsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $title = 'Reports';
    protected static string $view = 'filament.pages.reports-page';
}
