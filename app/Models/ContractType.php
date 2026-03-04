<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ContractType extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Filament-ready options: ['Commercial' => 'Commercial', ...].
     * Cached 5 minutes, invalidated on save/delete.
     */
    public static function options(): array
    {
        return Cache::remember('contract_types.options', now()->addMinutes(5), function () {
            return static::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name', 'name')
                ->toArray();
        });
    }

    /**
     * Slug-keyed options: ['commercial' => 'Commercial', ...].
     */
    public static function slugOptions(): array
    {
        return Cache::remember('contract_types.slug_options', now()->addMinutes(5), function () {
            return static::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name', 'slug')
                ->toArray();
        });
    }

    /**
     * Flush caches when a type is created, updated, or deleted.
     */
    protected static function booted(): void
    {
        $flush = function () {
            Cache::forget('contract_types.options');
            Cache::forget('contract_types.slug_options');
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
