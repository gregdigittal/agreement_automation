<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Models\Contract;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;


    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make("Linked From")
                    ->visible(fn (Contract $record) => $record->parentContract !== null)
                    ->schema([
                        Components\TextEntry::make("parentContract.link_type")
                            ->label("Link Type")
                            ->badge(),
                        Components\TextEntry::make("parentContract.parentContract.title")
                            ->label("Parent Contract")
                            ->url(fn (Contract $record) => ContractResource::getUrl("edit", ["record" => $record->parentContract->parentContract])),
                    ])
                    ->columns(2),
                Components\Section::make("SharePoint")
                    ->visible(fn (Contract $record) => ! empty($record->sharepoint_url))
                    ->schema([
                        Components\TextEntry::make("sharepoint_url")
                            ->label("Document URL")
                            ->url()
                            ->openUrlInNewTab(),
                        Components\TextEntry::make("sharepoint_version")
                            ->label("Version"),
                    ])
                    ->columns(2),
            ]);
    }
}
