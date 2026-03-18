<?php

declare(strict_types=1);

namespace App\Filament\Platform\Pages;

use App\Models\Tenant;
use Filament\Pages\Page;

class PlatformDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.platform.pages.platform-dashboard';

    public int $totalTenants = 0;

    public int $activeTenants = 0;

    public int $suspendedTenants = 0;

    public int $provisioningTenants = 0;

    public function mount(): void
    {
        $this->totalTenants = Tenant::count();
        $this->activeTenants = Tenant::where('status', Tenant::STATUS_ACTIVE)->count();
        $this->suspendedTenants = Tenant::where('status', Tenant::STATUS_SUSPENDED)->count();
        $this->provisioningTenants = Tenant::where('status', Tenant::STATUS_PROVISIONING)->count();
    }
}
