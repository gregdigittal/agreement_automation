<?php

namespace App\Filament\Vendor\Pages;

use App\Models\Contract;
use App\Models\KycPack;
use App\Models\VendorDocument;
use App\Models\VendorNotification;
use Filament\Pages\Dashboard;

class VendorDashboard extends Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'Dashboard';

    protected static string $view = 'filament.vendor.pages.dashboard';

    public function getWidgets(): array
    {
        return [];
    }

    public function getDashboardStats(): array
    {
        $cpId = auth('vendor')->user()?->counterparty_id;
        $vendorUserId = auth('vendor')->id();

        return [
            'active_agreements' => Contract::where('counterparty_id', $cpId)->where('workflow_state', 'executed')->count(),
            'pending_signing' => Contract::where('counterparty_id', $cpId)->where('signing_status', 'sent')->count(),
            'pending_kyc' => KycPack::whereHas('contract', fn ($q) => $q->where('counterparty_id', $cpId))
                ->where('status', 'incomplete')
                ->count(),
            'documents_uploaded' => VendorDocument::where('counterparty_id', $cpId)->count(),
            'unread_notifications' => VendorNotification::where('vendor_user_id', $vendorUserId)->whereNull('read_at')->count(),
        ];
    }

    public function getRecentActivity(): array
    {
        $cpId = auth('vendor')->user()?->counterparty_id;
        $vendorUserId = auth('vendor')->id();

        $contracts = Contract::where('counterparty_id', $cpId)
            ->latest('updated_at')
            ->limit(3)
            ->get(['id', 'title', 'workflow_state', 'updated_at'])
            ->map(fn ($c) => [
                'icon' => 'heroicon-o-document-text',
                'color' => 'text-blue-500',
                'description' => "Contract \"{$c->title}\" is now {$c->workflow_state}",
                'time' => $c->updated_at->diffForHumans(),
            ]);

        $documents = VendorDocument::where('counterparty_id', $cpId)
            ->latest('created_at')
            ->limit(2)
            ->get(['id', 'document_type', 'created_at'])
            ->map(fn ($d) => [
                'icon' => 'heroicon-o-arrow-up-tray',
                'color' => 'text-emerald-500',
                'description' => ucwords(str_replace('_', ' ', $d->document_type)).' document uploaded',
                'time' => $d->created_at->diffForHumans(),
            ]);

        $notifications = VendorNotification::where('vendor_user_id', $vendorUserId)
            ->latest('created_at')
            ->limit(3)
            ->get(['id', 'subject', 'created_at', 'read_at'])
            ->map(fn ($n) => [
                'icon' => 'heroicon-o-bell',
                'color' => $n->read_at ? 'text-gray-400' : 'text-red-500',
                'description' => $n->subject,
                'time' => $n->created_at->diffForHumans(),
            ]);

        return collect()
            ->concat($contracts)
            ->concat($documents)
            ->concat($notifications)
            ->sortByDesc(fn ($item) => $item['time'])
            ->take(5)
            ->values()
            ->toArray();
    }
}
