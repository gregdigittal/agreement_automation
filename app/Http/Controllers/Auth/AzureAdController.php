<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;

class AzureAdController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('azure')
            ->scopes(['openid', 'profile', 'email', 'User.Read'])
            ->redirect();
    }

    public function callback()
    {
        try {
            $socialiteUser = Socialite::driver('azure')->user();
        } catch (\Exception $e) {
            Log::error('Azure AD callback failed', ['error' => $e->getMessage()]);
            return redirect('/admin/login')->withErrors(['auth' => 'Azure AD authentication failed. Please try again.']);
        }

        $user = User::find($socialiteUser->getId());

        if ($user) {
            // Existing user — update name/email
            $user->update([
                'email' => $socialiteUser->getEmail(),
                'name' => $socialiteUser->getName(),
            ]);
        } else {
            // First-time SSO user — create as pending
            $user = new User([
                'email' => $socialiteUser->getEmail(),
                'name' => $socialiteUser->getName(),
                'status' => 'pending',
            ]);
            $user->id = $socialiteUser->getId();
            $user->save();

            return response()->view('auth.pending-approval', [], 200);
        }

        // Check status
        if ($user->status === 'pending') {
            return response()->view('auth.pending-approval', [], 200);
        }

        if ($user->status === 'suspended') {
            return redirect('/admin/login')->withErrors(['auth' => 'Your account has been suspended. Contact your administrator.']);
        }

        // Active user with roles — log in
        if (!$user->roles()->exists()) {
            return redirect('/admin/login')->withErrors(['auth' => 'You do not have access to CCRS. Contact your administrator.']);
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/admin');
    }
}
