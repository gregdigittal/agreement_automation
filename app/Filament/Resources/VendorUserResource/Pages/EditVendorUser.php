<?php
namespace App\Filament\Resources\VendorUserResource\Pages;
use App\Filament\Resources\VendorUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVendorUser extends EditRecord
{
    protected static string $resource = VendorUserResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
