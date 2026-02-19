<?php

namespace App\Filament\Resources\MerchantAgreementResource\Pages;

use App\Filament\Resources\MerchantAgreementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantAgreements extends ListRecords
{
    protected static string $resource = MerchantAgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
