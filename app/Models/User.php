<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasUuidPrimaryKey, HasRoles;

    protected $keyType = 'string';
    public $incrementing = false;
    protected string $guard_name = 'web';

    protected $fillable = ['id', 'email', 'name'];
    protected $casts = ['roles' => 'array'];
}
