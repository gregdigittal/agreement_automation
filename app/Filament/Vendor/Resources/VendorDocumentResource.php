<?php

namespace App\Filament\Vendor\Resources;

use App\Models\Contract;
use App\Models\VendorDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Helpers\StorageHelper;

class VendorDocumentResource extends Resource
{
    protected static ?string $model = VendorDocument::class;
    protected static ?string $navigationGroup = 'Documents';
    protected static ?string $navigationLabel = 'My Documents';
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';

    public static function getEloquentQuery(): Builder
    {
        $counterpartyId = auth('vendor')->user()?->counterparty_id;
        return parent::getEloquentQuery()->where('counterparty_id', $counterpartyId)->with(['contract', 'uploadedBy']);
    }

    public static function form(Form $form): Form
    {
        $counterpartyId = auth('vendor')->user()?->counterparty_id;
        return $form->schema([
            Forms\Components\Select::make('contract_id')->label('Related Agreement (Optional)')
                ->options(Contract::where('counterparty_id', $counterpartyId)->pluck('title', 'id'))->searchable()->nullable(),
            Forms\Components\Select::make('document_type')->label('Document Type')
                ->options(['supporting' => 'Supporting Document', 'certificate' => 'Certificate', 'insurance' => 'Insurance Certificate', 'compliance' => 'Compliance Document', 'registration' => 'Company Registration', 'id' => 'Director ID / Passport', 'financial' => 'Financial Statement', 'other' => 'Other'])
                ->required()->default('supporting'),
            Forms\Components\FileUpload::make('storage_path')->label('Document File')
                ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'])
                ->disk(config('ccrs.contracts_disk'))->directory(fn () => 'vendor_documents/' . $counterpartyId)->maxSize(20480)->required()->live()
                ->afterStateUpdated(fn ($state, callable $set) => $set('filename', $state ? basename($state) : null)),
            Forms\Components\Hidden::make('filename'),
            Forms\Components\Hidden::make('counterparty_id')->default($counterpartyId),
            Forms\Components\Hidden::make('uploaded_by_vendor_user_id')->default(fn () => auth('vendor')->id()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('filename')->searchable(),
            Tables\Columns\TextColumn::make('document_type')->badge(),
            Tables\Columns\TextColumn::make('contract.title')->label('Agreement')->limit(35)->default('—'),
            Tables\Columns\TextColumn::make('created_at')->since()->label('Uploaded'),
        ])->defaultSort('created_at', 'desc')
        ->actions([
            Tables\Actions\Action::make('download')->icon('heroicon-o-arrow-down-tray')
                ->url(fn (VendorDocument $record) => StorageHelper::temporaryUrl($record->storage_path, 'download'))->openUrlInNewTab(),
            Tables\Actions\DeleteAction::make()->requiresConfirmation(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Vendor\Resources\VendorDocumentResource\Pages\ListVendorDocuments::route('/'),
            'create' => \App\Filament\Vendor\Resources\VendorDocumentResource\Pages\CreateVendorDocument::route('/create'),
        ];
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
}
