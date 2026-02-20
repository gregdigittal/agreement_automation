<?php
namespace App\Filament\Vendor\Pages;

use App\Models\Contract;
use App\Models\VendorDocument;
use App\Models\VendorNotification;
use Filament\Pages\Dashboard;

class VendorDashboard extends Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = 'Dashboard';
    protected static string $view = 'filament.vendor.pages.dashboard';

    public function getWidgets(): array { return []; }

    public function getDashboardStats(): array
    {
        $cpId = auth('vendor')->user()?->counterparty_id;
        return [
            'active_agreements' => Contract::where('counterparty_id', $cpId)->where('workflow_state', 'executed')->count(),
            'pending_signing' => Contract::where('counterparty_id', $cpId)->where('signing_status', 'sent')->count(),
            'documents_uploaded' => VendorDocument::where('counterparty_id', $cpId)->count(),
            'unread_notifications' => VendorNotification::where('vendor_user_id', auth('vendor')->id())->whereNull('read_at')->count(),
        ];
    }
}
