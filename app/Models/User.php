<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasUuidPrimaryKey, HasRoles, HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;
    protected string $guard_name = 'web';

    protected $fillable = ['id', 'email', 'name', 'notification_preferences'];

    protected $casts = [
        'notification_preferences' => 'array',
    ];

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
