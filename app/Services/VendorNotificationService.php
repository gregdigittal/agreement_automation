<?php
namespace App\Services;

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\VendorNotification;
use App\Models\VendorUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class VendorNotificationService
{
    public function notifyVendors(Counterparty $counterparty, string $subject, string $body, ?string $resourceType = null, ?string $resourceId = null): void
    {
        $vendors = VendorUser::where('counterparty_id', $counterparty->id)->get();
        foreach ($vendors as $vendor) {
            VendorNotification::create([
                'id' => Str::uuid()->toString(), 'vendor_user_id' => $vendor->id,
                'subject' => $subject, 'body' => $body,
                'related_resource_type' => $resourceType, 'related_resource_id' => $resourceId,
            ]);
            Mail::to($vendor->email)->queue(new \App\Mail\VendorNotificationMail($vendor, $subject, $body));
        }
    }

    public function notifyContractStatusChange(Contract $contract, string $newState): void
    {
        if (!$contract->counterparty_id) return;
        $this->notifyVendors(
            $contract->counterparty,
            "Agreement Status Update: {$contract->title}",
            "Your agreement \"{$contract->title}\" has moved to status: **{$newState}**.",
            'contract', $contract->id,
        );
    }
}
