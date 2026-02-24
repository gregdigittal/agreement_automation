<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class OrgVisualizationPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Org Visualization';
    protected static ?string $title = 'Organization & Workflow Visualization';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 35;
    protected static string $view = 'filament.pages.org-visualization';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
    }
}
