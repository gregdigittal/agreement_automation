<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    \SocialiteProviders\Manager\ServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\VendorPanelProvider::class,
    App\Providers\Filament\PlatformPanelProvider::class,
];
