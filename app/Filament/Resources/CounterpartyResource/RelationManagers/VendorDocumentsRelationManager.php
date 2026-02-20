<?php

namespace App\Filament\Resources\CounterpartyResource\RelationManagers;

use App\Models\VendorDocument;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class VendorDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'vendorDocuments';
    protected static ?string $title = 'Documents Uploaded by Vendor';

    public function isReadOnly(): bool { return true; }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('filename'),
            Tables\Columns\BadgeColumn::make('document_type'),
            Tables\Columns\TextColumn::make('contract.title')->label('Agreement')->limit(30)->default('—'),
            Tables\Columns\TextColumn::make('uploadedBy.name')->label('Uploaded By'),
            Tables\Columns\TextColumn::make('created_at')->since(),
        ])->actions([
            Tables\Actions\Action::make('download')->icon('heroicon-o-arrow-down-tray')
                ->url(fn (VendorDocument $record) => Storage::disk('s3')->temporaryUrl($record->storage_path, now()->addMinutes(10)))->openUrlInNewTab(),
        ]);
    }
}
