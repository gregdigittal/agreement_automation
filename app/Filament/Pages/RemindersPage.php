<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;

class RemindersPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?string $title = 'Reminders';
    protected static string $view = 'filament.pages.reminders-page';
    protected static bool $shouldRegisterNavigation = false;
}
