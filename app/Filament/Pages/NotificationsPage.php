<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;

class NotificationsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';
    protected static ?string $navigationGroup = 'Admin';
    protected static ?string $title = 'Notifications';
    protected static string $view = 'filament.pages.notifications-page';
}
