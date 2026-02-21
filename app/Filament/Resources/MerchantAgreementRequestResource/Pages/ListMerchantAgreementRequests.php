<?php

namespace App\Filament\Resources\MerchantAgreementRequestResource\Pages;

use App\Filament\Resources\MerchantAgreementRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantAgreementRequests extends ListRecords
{
    protected static string $resource = MerchantAgreementRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
