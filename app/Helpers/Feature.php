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
}
