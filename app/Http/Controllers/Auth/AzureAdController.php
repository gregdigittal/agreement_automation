<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $email = $socialiteUser->getEmail();
        $name = $socialiteUser->getName();

        if (empty($email)) {
            Log::warning('Azure AD callback: user has no email', ['azure_id' => $socialiteUser->getId()]);
            return redirect('/admin/login')->withErrors([
                'auth' => 'Your Azure AD account does not have an email address configured. Contact your IT administrator.',
            ]);
        }

        $user = DB::transaction(function () use ($socialiteUser, $email, $name) {
            $user = User::lockForUpdate()->find($socialiteUser->getId());

            if ($user) {
                $user->update([
                    'email' => $email,
                    'name' => $name ?? $user->name,
                ]);
                return $user;
            }

            $user = new User([
                'email' => $email,
                'name' => $name ?? $email,
                'status' => 'pending',
            ]);
            $user->id = $socialiteUser->getId();
            $user->save();

            return $user;
        });

        if ($user->status === UserStatus::Pending) {
            return response()->view('auth.pending-approval', [], 403);
        }

        if ($user->status === UserStatus::Suspended) {
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
