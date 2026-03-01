<?php

namespace App\Helpers;

class CountryCodes
{
    /**
     * ISO 3166-1 alpha-2 country codes for dropdown selections.
     *
     * @deprecated Use \App\Models\Country::dropdownOptions() instead.
     *             This method now delegates to the countries database table.
     */
    public static function options(): array
    {
        try {
            return \App\Models\Country::dropdownOptions();
        } catch (\Exception $e) {
            // Fallback for when the countries table hasn't been migrated yet
            return [
                'AE' => 'AE - United Arab Emirates',
                'SA' => 'SA - Saudi Arabia',
                'GB' => 'GB - United Kingdom',
                'US' => 'US - United States',
            ];
        }
    }
}
