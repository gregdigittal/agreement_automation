<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;

class KeyDatesPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?string $title = 'Key Dates';
    protected static string $view = 'filament.pages.key-dates-page';
}
