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

    public static function exchangeRoom(): bool
    {
        return (bool) config('ccrs.exchange_room.enabled', true);
    }

    public static function sharePoint(): bool
    {
        return (bool) config('ccrs.sharepoint.enabled', false)
            && config('services.azure.client_id')
            && config('services.azure.client_secret');
    }
}
