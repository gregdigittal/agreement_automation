<?php
namespace App\Filament\Vendor\Resources\VendorDocumentResource\Pages;
use App\Filament\Vendor\Resources\VendorDocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorDocument extends CreateRecord
{
    protected static string $resource = VendorDocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['filename']) && !empty($data['storage_path'])) {
            $data['filename'] = basename($data['storage_path']);
        }
        return $data;
    }
}
