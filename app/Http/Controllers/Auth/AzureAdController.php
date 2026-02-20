<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AzureAdController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('azure')
            ->scopes(['openid', 'profile', 'email', 'User.Read', 'GroupMember.Read.All'])
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

        $role = $this->resolveRole($socialiteUser->token);

        $user = User::updateOrCreate(
            ['id' => $socialiteUser->getId()],
            [
                'email' => $socialiteUser->getEmail(),
                'name' => $socialiteUser->getName(),
            ]
        );

        if ($role) {
            $user->syncRoles([$role]);
        } else {
            Log::warning('Azure AD user has no CCRS group membership', ['email' => $user->email]);
            return redirect('/admin/login')->withErrors(['auth' => 'You do not have access to CCRS. Contact your administrator.']);
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/admin');
    }

    private function resolveRole(?string $accessToken): ?string
    {
        if (!$accessToken) {
            return null;
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get('https://graph.microsoft.com/v1.0/me/memberOf', [
                    '$select' => 'id,displayName',
                ]);

            if (!$response->successful()) {
                Log::warning('Microsoft Graph memberOf call failed', ['status' => $response->status()]);
                return null;
            }

            $groups = $response->json('value', []);
            $groupIds = array_column($groups, 'id');
            $groupMap = array_filter(config('ccrs.azure_ad.group_map', []));

            $priorityOrder = ['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'];
            $userRoles = [];
            foreach ($groupMap as $groupId => $roleName) {
                if (in_array($groupId, $groupIds, true)) {
                    $userRoles[] = $roleName;
                }
            }

            foreach ($priorityOrder as $role) {
                if (in_array($role, $userRoles)) {
                    return $role;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to resolve Azure AD role', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
