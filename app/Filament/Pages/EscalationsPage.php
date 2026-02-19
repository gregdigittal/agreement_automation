<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;

class EscalationsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Workflows';
    protected static ?string $title = 'Escalations';
    protected static string $view = 'filament.pages.escalations-page';
}
