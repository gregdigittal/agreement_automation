<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class OrganisationStructurePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-share';
    protected static ?string $navigationLabel = 'Organisation Structure';
    protected static ?string $title = 'Interactive Organisation Structure';
    protected static ?string $navigationGroup = 'Organization';
    protected static ?int $navigationSort = 36;
    protected static string $view = 'filament.pages.organisation-structure';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial']) ?? false;
    }
}
