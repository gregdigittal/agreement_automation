<?php

namespace App\Helpers;

class Feature
{
    public static function enabled(string $feature): bool
    {
        return (bool) config("features.{$feature}", false);
    }

    public static function disabled(string $feature): bool
    {
        return ! static::enabled($feature);
    }

    public static function inHouseSigning(): bool
    {
        return (bool) config('ccrs.in_house_signing');
    }
}
