<?php

namespace App\Filament\Resources\MerchantAgreementRequestResource\Pages;

use App\Filament\Resources\MerchantAgreementRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchantAgreementRequest extends EditRecord
{
    protected static string $resource = MerchantAgreementRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
