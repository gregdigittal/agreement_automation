<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get a dropdown-ready options array: 'code' => 'CODE - Name'.
     */
    public static function dropdownOptions(): array
    {
        return static::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (self $c) => [$c->code => "{$c->code} - {$c->name}"])
            ->toArray();
    }

    /**
     * Get a simple name-keyed options array: 'code' => 'Name'.
     */
    public static function nameOptions(): array
    {
        return static::where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'code')
            ->toArray();
    }
}
