<?php

namespace App\Helpers;

class CountryCodes
{
    /**
     * ISO 3166-1 alpha-2 country codes for dropdown selections.
     * Covers GCC, major markets, and commonly referenced jurisdictions.
     */
    public static function options(): array
    {
        return [
            'AE' => 'AE - United Arab Emirates',
            'SA' => 'SA - Saudi Arabia',
            'QA' => 'QA - Qatar',
            'BH' => 'BH - Bahrain',
            'KW' => 'KW - Kuwait',
            'OM' => 'OM - Oman',
            'EG' => 'EG - Egypt',
            'JO' => 'JO - Jordan',
            'LB' => 'LB - Lebanon',
            'GB' => 'GB - United Kingdom',
            'US' => 'US - United States',
            'DE' => 'DE - Germany',
            'FR' => 'FR - France',
            'NL' => 'NL - Netherlands',
            'CH' => 'CH - Switzerland',
            'IE' => 'IE - Ireland',
            'SG' => 'SG - Singapore',
            'HK' => 'HK - Hong Kong',
            'JP' => 'JP - Japan',
            'CN' => 'CN - China',
            'AU' => 'AU - Australia',
            'NZ' => 'NZ - New Zealand',
            'CA' => 'CA - Canada',
            'IN' => 'IN - India',
            'PK' => 'PK - Pakistan',
            'ZA' => 'ZA - South Africa',
            'NG' => 'NG - Nigeria',
            'KE' => 'KE - Kenya',
            'BR' => 'BR - Brazil',
            'MX' => 'MX - Mexico',
            'MY' => 'MY - Malaysia',
            'ID' => 'ID - Indonesia',
            'TH' => 'TH - Thailand',
            'PH' => 'PH - Philippines',
            'KR' => 'KR - South Korea',
            'TW' => 'TW - Taiwan',
            'IT' => 'IT - Italy',
            'ES' => 'ES - Spain',
            'SE' => 'SE - Sweden',
            'NO' => 'NO - Norway',
            'DK' => 'DK - Denmark',
            'FI' => 'FI - Finland',
            'PL' => 'PL - Poland',
            'AT' => 'AT - Austria',
            'BE' => 'BE - Belgium',
            'PT' => 'PT - Portugal',
            'CZ' => 'CZ - Czech Republic',
            'RO' => 'RO - Romania',
            'TR' => 'TR - Turkey',
            'IL' => 'IL - Israel',
            'GLOBAL' => 'GLOBAL - Multi-jurisdictional',
        ];
    }
}
