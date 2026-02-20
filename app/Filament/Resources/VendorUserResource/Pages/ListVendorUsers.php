<?php
namespace App\Filament\Resources\VendorUserResource\Pages;
use App\Filament\Resources\VendorUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendorUsers extends ListRecords
{
    protected static string $resource = VendorUserResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
