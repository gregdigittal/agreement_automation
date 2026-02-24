<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AgreementRepositoryPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationLabel = 'Agreement Repository';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.agreement-repository';
    protected static ?string $title = 'Agreement Repository';
    protected static ?string $slug = 'agreement-repository';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            'system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit',
        ]) ?? false;
    }
}
