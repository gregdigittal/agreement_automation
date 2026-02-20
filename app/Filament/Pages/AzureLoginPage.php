<?php

namespace App\Filament\Pages;

use Filament\Pages\Auth\Login;

class AzureLoginPage extends Login
{
    public function mount(): void
    {
        if (auth()->check()) {
            $this->redirect(filament()->getHomeUrl());
            return;
        }
        $this->redirect(route('azure.redirect'));
    }
}
