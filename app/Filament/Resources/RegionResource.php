<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegionResource\Pages;
use App\Models\Region;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Org Structure';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g. Middle East & North Africa')
                ->helperText('A descriptive name for this organisational region.'),
            Forms\Components\Select::make('code')
                ->options(self::getRegionCodeOptions())
                ->searchable()
                ->helperText('ISO 3166-1 alpha-2 country code. Used in CSV uploads and reports.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->sortable(),
                Tables\Columns\TextColumn::make('entities_count')->counts('entities')->label('Entities'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegions::route('/'),
            'create' => Pages\CreateRegion::route('/create'),
            'edit' => Pages\EditRegion::route('/{record}/edit'),
        ];
    }

    public static function getRegionCodeOptions(): array
    {
        return [
            'AF' => 'AF — Afghanistan',
            'AL' => 'AL — Albania',
            'DZ' => 'DZ — Algeria',
            'AS' => 'AS — American Samoa',
            'AD' => 'AD — Andorra',
            'AO' => 'AO — Angola',
            'AG' => 'AG — Antigua and Barbuda',
            'AR' => 'AR — Argentina',
            'AM' => 'AM — Armenia',
            'AU' => 'AU — Australia',
            'AT' => 'AT — Austria',
            'AZ' => 'AZ — Azerbaijan',
            'BS' => 'BS — Bahamas',
            'BH' => 'BH — Bahrain',
            'BD' => 'BD — Bangladesh',
            'BB' => 'BB — Barbados',
            'BY' => 'BY — Belarus',
            'BE' => 'BE — Belgium',
            'BZ' => 'BZ — Belize',
            'BJ' => 'BJ — Benin',
            'BT' => 'BT — Bhutan',
            'BO' => 'BO — Bolivia',
            'BA' => 'BA — Bosnia and Herzegovina',
            'BW' => 'BW — Botswana',
            'BR' => 'BR — Brazil',
            'BN' => 'BN — Brunei',
            'BG' => 'BG — Bulgaria',
            'BF' => 'BF — Burkina Faso',
            'BI' => 'BI — Burundi',
            'CV' => 'CV — Cabo Verde',
            'KH' => 'KH — Cambodia',
            'CM' => 'CM — Cameroon',
            'CA' => 'CA — Canada',
            'CF' => 'CF — Central African Republic',
            'TD' => 'TD — Chad',
            'CL' => 'CL — Chile',
            'CN' => 'CN — China',
            'CO' => 'CO — Colombia',
            'KM' => 'KM — Comoros',
            'CG' => 'CG — Congo',
            'CD' => 'CD — Congo (DRC)',
            'CR' => 'CR — Costa Rica',
            'CI' => 'CI — Cote d\'Ivoire',
            'HR' => 'HR — Croatia',
            'CU' => 'CU — Cuba',
            'CY' => 'CY — Cyprus',
            'CZ' => 'CZ — Czechia',
            'DK' => 'DK — Denmark',
            'DJ' => 'DJ — Djibouti',
            'DM' => 'DM — Dominica',
            'DO' => 'DO — Dominican Republic',
            'EC' => 'EC — Ecuador',
            'EG' => 'EG — Egypt',
            'SV' => 'SV — El Salvador',
            'GQ' => 'GQ — Equatorial Guinea',
            'ER' => 'ER — Eritrea',
            'EE' => 'EE — Estonia',
            'SZ' => 'SZ — Eswatini',
            'ET' => 'ET — Ethiopia',
            'FJ' => 'FJ — Fiji',
            'FI' => 'FI — Finland',
            'FR' => 'FR — France',
            'GA' => 'GA — Gabon',
            'GM' => 'GM — Gambia',
            'GE' => 'GE — Georgia',
            'DE' => 'DE — Germany',
            'GH' => 'GH — Ghana',
            'GR' => 'GR — Greece',
            'GD' => 'GD — Grenada',
            'GT' => 'GT — Guatemala',
            'GN' => 'GN — Guinea',
            'GW' => 'GW — Guinea-Bissau',
            'GY' => 'GY — Guyana',
            'HT' => 'HT — Haiti',
            'HN' => 'HN — Honduras',
            'HK' => 'HK — Hong Kong',
            'HU' => 'HU — Hungary',
            'IS' => 'IS — Iceland',
            'IN' => 'IN — India',
            'ID' => 'ID — Indonesia',
            'IR' => 'IR — Iran',
            'IQ' => 'IQ — Iraq',
            'IE' => 'IE — Ireland',
            'IL' => 'IL — Israel',
            'IT' => 'IT — Italy',
            'JM' => 'JM — Jamaica',
            'JP' => 'JP — Japan',
            'JO' => 'JO — Jordan',
            'KZ' => 'KZ — Kazakhstan',
            'KE' => 'KE — Kenya',
            'KI' => 'KI — Kiribati',
            'KP' => 'KP — Korea (North)',
            'KR' => 'KR — Korea (South)',
            'KW' => 'KW — Kuwait',
            'KG' => 'KG — Kyrgyzstan',
            'LA' => 'LA — Laos',
            'LV' => 'LV — Latvia',
            'LB' => 'LB — Lebanon',
            'LS' => 'LS — Lesotho',
            'LR' => 'LR — Liberia',
            'LY' => 'LY — Libya',
            'LI' => 'LI — Liechtenstein',
            'LT' => 'LT — Lithuania',
            'LU' => 'LU — Luxembourg',
            'MO' => 'MO — Macao',
            'MG' => 'MG — Madagascar',
            'MW' => 'MW — Malawi',
            'MY' => 'MY — Malaysia',
            'MV' => 'MV — Maldives',
            'ML' => 'ML — Mali',
            'MT' => 'MT — Malta',
            'MH' => 'MH — Marshall Islands',
            'MR' => 'MR — Mauritania',
            'MU' => 'MU — Mauritius',
            'MX' => 'MX — Mexico',
            'FM' => 'FM — Micronesia',
            'MD' => 'MD — Moldova',
            'MC' => 'MC — Monaco',
            'MN' => 'MN — Mongolia',
            'ME' => 'ME — Montenegro',
            'MA' => 'MA — Morocco',
            'MZ' => 'MZ — Mozambique',
            'MM' => 'MM — Myanmar',
            'NA' => 'NA — Namibia',
            'NR' => 'NR — Nauru',
            'NP' => 'NP — Nepal',
            'NL' => 'NL — Netherlands',
            'NZ' => 'NZ — New Zealand',
            'NI' => 'NI — Nicaragua',
            'NE' => 'NE — Niger',
            'NG' => 'NG — Nigeria',
            'MK' => 'MK — North Macedonia',
            'NO' => 'NO — Norway',
            'OM' => 'OM — Oman',
            'PK' => 'PK — Pakistan',
            'PW' => 'PW — Palau',
            'PS' => 'PS — Palestine',
            'PA' => 'PA — Panama',
            'PG' => 'PG — Papua New Guinea',
            'PY' => 'PY — Paraguay',
            'PE' => 'PE — Peru',
            'PH' => 'PH — Philippines',
            'PL' => 'PL — Poland',
            'PT' => 'PT — Portugal',
            'PR' => 'PR — Puerto Rico',
            'QA' => 'QA — Qatar',
            'RO' => 'RO — Romania',
            'RU' => 'RU — Russia',
            'RW' => 'RW — Rwanda',
            'KN' => 'KN — Saint Kitts and Nevis',
            'LC' => 'LC — Saint Lucia',
            'VC' => 'VC — Saint Vincent and the Grenadines',
            'WS' => 'WS — Samoa',
            'SM' => 'SM — San Marino',
            'ST' => 'ST — Sao Tome and Principe',
            'SA' => 'SA — Saudi Arabia',
            'SN' => 'SN — Senegal',
            'RS' => 'RS — Serbia',
            'SC' => 'SC — Seychelles',
            'SL' => 'SL — Sierra Leone',
            'SG' => 'SG — Singapore',
            'SK' => 'SK — Slovakia',
            'SI' => 'SI — Slovenia',
            'SB' => 'SB — Solomon Islands',
            'SO' => 'SO — Somalia',
            'ZA' => 'ZA — South Africa',
            'SS' => 'SS — South Sudan',
            'ES' => 'ES — Spain',
            'LK' => 'LK — Sri Lanka',
            'SD' => 'SD — Sudan',
            'SR' => 'SR — Suriname',
            'SE' => 'SE — Sweden',
            'CH' => 'CH — Switzerland',
            'SY' => 'SY — Syria',
            'TW' => 'TW — Taiwan',
            'TJ' => 'TJ — Tajikistan',
            'TZ' => 'TZ — Tanzania',
            'TH' => 'TH — Thailand',
            'TL' => 'TL — Timor-Leste',
            'TG' => 'TG — Togo',
            'TO' => 'TO — Tonga',
            'TT' => 'TT — Trinidad and Tobago',
            'TN' => 'TN — Tunisia',
            'TR' => 'TR — Turkey',
            'TM' => 'TM — Turkmenistan',
            'TV' => 'TV — Tuvalu',
            'UG' => 'UG — Uganda',
            'UA' => 'UA — Ukraine',
            'AE' => 'AE — United Arab Emirates',
            'GB' => 'GB — United Kingdom',
            'US' => 'US — United States',
            'UY' => 'UY — Uruguay',
            'UZ' => 'UZ — Uzbekistan',
            'VU' => 'VU — Vanuatu',
            'VA' => 'VA — Vatican City',
            'VE' => 'VE — Venezuela',
            'VN' => 'VN — Vietnam',
            'YE' => 'YE — Yemen',
            'ZM' => 'ZM — Zambia',
            'ZW' => 'ZW — Zimbabwe',
        ];
    }
}
