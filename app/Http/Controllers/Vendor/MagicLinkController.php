<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    public function request(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $vendor = VendorUser::where('email', $request->email)->first();

        if ($vendor) {
            $token = Str::random(64);
            $vendor->update([
                'login_token'            => hash('sha256', $token),
                'login_token_expires_at' => now()->addMinutes(15),
            ]);

            $link = route('vendor.magic-link.verify', ['token' => $token]);

            Mail::to($vendor->email)->send(
                new \App\Mail\VendorMagicLink($vendor, $link)
            );
        }

        return back()->with('status', 'If an account exists for that email, a login link has been sent.');
    }

    public function verify(Request $request, string $token)
    {
        $hashed = hash('sha256', $token);

        $vendor = VendorUser::where('login_token', $hashed)
            ->where('login_token_expires_at', '>', now())
            ->first();

        if (! $vendor) {
            return redirect('/vendor/login')->withErrors(['token' => 'This login link is invalid or has expired.']);
        }

        $vendor->update([
            'login_token'            => null,
            'login_token_expires_at' => null,
            'last_login_at'          => now(),
        ]);

        Auth::guard('vendor')->login($vendor, remember: true);

        return redirect('/vendor');
    }
}
