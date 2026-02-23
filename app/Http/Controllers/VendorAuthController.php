<?php

namespace App\Http\Controllers;

use App\Models\VendorLoginToken;
use App\Models\VendorUser;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class VendorAuthController extends Controller
{
    public function requestLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $vendor = VendorUser::where('email', $request->email)->first();
        if (!$vendor) {
            return back()->with('status', 'If an account exists, a login link has been sent.');
        }

        $rawToken = Str::random(64);
        VendorLoginToken::create([
            'vendor_user_id' => $vendor->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
        ]);

        $loginUrl = route('vendor.auth.verify', ['token' => $rawToken]);

        app(NotificationService::class)->create([
            'recipient_email' => $vendor->email,
            'subject' => 'CCRS Vendor Portal Login',
            'body' => "Click this link to log in (expires in 15 minutes): {$loginUrl}",
            'channel' => 'email',
            'related_resource_type' => 'vendor_login',
            'related_resource_id' => $vendor->id,
        ]);

        return back()->with('status', 'If an account exists, a login link has been sent.');
    }

    public function verify(string $token)
    {
        $hash = hash('sha256', $token);
        $loginToken = VendorLoginToken::where('token_hash', $hash)->first();

        if (!$loginToken || !$loginToken->isValid()) {
            return redirect()->route('vendor.login')->withErrors(['token' => 'Invalid or expired link.']);
        }

        $loginToken->update(['used_at' => now()]);
        $loginToken->vendorUser->update(['last_login_at' => now()]);

        Auth::guard('vendor')->login($loginToken->vendorUser);

        return redirect('/vendor');
    }

    public function logout()
    {
        Auth::guard('vendor')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('vendor.login');
    }
}
