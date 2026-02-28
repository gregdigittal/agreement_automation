<?php
namespace App\Models;

use App\Enums\UserStatus;
use App\Traits\HasUuidPrimaryKey;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasUuidPrimaryKey, HasRoles, HasFactory, Notifiable;

    protected string $guard_name = 'web';

    protected $fillable = ['email', 'name', 'notification_preferences', 'status'];

    protected $casts = [
        'notification_preferences' => 'array',
        'status' => UserStatus::class,
    ];

    public function storedSignatures(): HasMany
    {
        return $this->hasMany(StoredSignature::class);
    }

    public function accessibleContracts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Contract::class, 'contract_user_access')
            ->withPivot('access_level', 'granted_by')
            ->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === UserStatus::Active && $this->roles()->exists();
    }

    /**
     * Check if user wants notifications on a given channel for a given category.
     */
    public function wantsNotification(string $category, string $channel): bool
    {
        $prefs = $this->notification_preferences ?? [];

        if (empty($prefs)) {
            return true;
        }

        if (isset($prefs[$channel]) && $prefs[$channel] === false) {
            return false;
        }

        $categoryChannels = $prefs['channels'][$category] ?? [$channel];
        return in_array($channel, $categoryChannels);
    }
}
