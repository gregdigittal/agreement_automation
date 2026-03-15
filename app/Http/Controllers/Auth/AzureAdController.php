<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

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

        // Sync Spatie roles from Azure AD group membership, if the group map is configured
        // and the token contains group claims. Skipped gracefully if groups are absent.
        $this->syncRolesFromGroupMap($user, $socialiteUser);

        if ($user->status === UserStatus::Pending) {
            return response()->view('auth.pending-approval', [], 403);
        }

        if ($user->status === UserStatus::Suspended) {
            return redirect('/admin/login')->withErrors(['auth' => 'Your account has been suspended. Contact your administrator.']);
        }

        // Active user with roles — log in
        if (! $user->roles()->exists()) {
            return redirect('/admin/login')->withErrors(['auth' => 'You do not have access to CCRS. Contact your administrator.']);
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/admin');
    }

    /**
     * Sync the user's Spatie roles based on their Azure AD group memberships.
     *
     * Requires:
     *   1. AZURE_AD_GROUP_* env vars configured (see config/ccrs.php azure_ad.group_map).
     *   2. Azure AD app manifest configured with groupMembershipClaims (CTO task).
     *      Without this, $groups will be empty and the sync is a no-op.
     *
     * When groups ARE present and map to CCRS roles:
     *   - Replaces the user's Spatie roles with the mapped set.
     *   - Auto-activates pending users that have at least one mapped role.
     */
    private function syncRolesFromGroupMap(User $user, SocialiteUser $socialiteUser): void
    {
        $groupMap = config('ccrs.azure_ad.group_map', []);

        if (empty($groupMap)) {
            return;
        }

        $groups = $this->extractGroupsFromToken($socialiteUser);

        if (empty($groups)) {
            return;
        }

        $mappedRoles = array_values(array_filter(
            array_map(fn (string $groupId) => $groupMap[$groupId] ?? null, $groups)
        ));

        if (empty($mappedRoles)) {
            Log::info('Azure AD group map: user has groups but none map to a CCRS role', [
                'user_id' => $user->id,
                'group_count' => count($groups),
            ]);

            return;
        }

        $user->syncRoles($mappedRoles);

        Log::info('Azure AD group map: roles synced', [
            'user_id' => $user->id,
            'roles' => $mappedRoles,
        ]);

        // Auto-activate pending users whose Azure AD groups grant them CCRS access.
        if ($user->status === UserStatus::Pending) {
            $user->update(['status' => 'active']);

            Log::info('Azure AD group map: pending user auto-activated via group membership', [
                'user_id' => $user->id,
                'roles' => $mappedRoles,
            ]);
        }
    }

    /**
     * Extract Azure AD group UUIDs from the socialite user's raw token claims.
     *
     * Returns an empty array when:
     *   - The groups claim is absent (groupMembershipClaims not configured in Azure AD).
     *   - The token has a groups overage (user has >200 groups — requires Graph API call).
     */
    private function extractGroupsFromToken(SocialiteUser $socialiteUser): array
    {
        /** @var array<string, mixed> $raw */
        $raw = property_exists($socialiteUser, 'user') ? ($socialiteUser->user ?? []) : [];

        if (! is_array($raw)) {
            return [];
        }

        if (! empty($raw['groups']) && is_array($raw['groups'])) {
            return $raw['groups'];
        }

        // Groups overage: token contains _claim_names.groups or hasgroups=true instead of
        // a direct groups array. A Microsoft Graph API call is required to retrieve them.
        // TODO(CTO): configure groupMembershipClaims in Azure AD app manifest to avoid overage.
        if (isset($raw['_claim_names']['groups']) || ! empty($raw['hasgroups'])) {
            Log::warning('Azure AD group map: groups overage detected — groups claim not included in token. Configure groupMembershipClaims in Azure AD app manifest to enable automatic role sync.', [
                'user_id' => $socialiteUser->getId(),
            ]);
        }

        return [];
    }
}
