<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class HelpGuidePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static string $view = 'filament.pages.help-guide';
    protected static ?int $navigationSort = 100;
    protected static ?string $title = 'Help & Guide';
    protected static ?string $navigationLabel = 'Help & Guide';
}
