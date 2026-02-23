<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractResource\Pages;
use App\Filament\Resources\ContractResource\RelationManagers;
use App\Jobs\ProcessAiAnalysis;
use App\Models\Contract;
use App\Services\ContractLinkService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('region_id')->relationship('region', 'name')->required()->searchable()->reactive(),
            Forms\Components\Select::make('entity_id')->relationship('entity', 'name')->required()->searchable()->reactive(),
            Forms\Components\Select::make('project_id')->relationship('project', 'name')->required()->searchable(),
            Forms\Components\Select::make('counterparty_id')->relationship('counterparty', 'legal_name')->required()->searchable(),
            Forms\Components\Select::make('contract_type')->options(['Commercial' => 'Commercial', 'Merchant' => 'Merchant'])->required(),
            Forms\Components\TextInput::make('title')->maxLength(255),
            Forms\Components\FileUpload::make('storage_path')->label('Contract File')->disk('s3')->directory('contracts')
                ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                ->afterStateUpdated(function ($state, Set $set) {
                    if ($state instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                        $set('file_name', $state->getClientOriginalName());
                    }
                }),
            Forms\Components\Hidden::make('file_name'),
            Forms\Components\Section::make('SharePoint Collaboration')
                ->description('Link the SharePoint document URL for collaborative review.')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('sharepoint_url')
                        ->label('SharePoint URL')
                        ->url()
                        ->maxLength(2048)
                        ->placeholder('https://digittalgroup.sharepoint.com/sites/legal/...'),
                    Forms\Components\TextInput::make('sharepoint_version')
                        ->label('SharePoint Version')
                        ->maxLength(50)
                        ->placeholder('e.g. 2.3'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(40),
            Tables\Columns\BadgeColumn::make('contract_type'),
            Tables\Columns\BadgeColumn::make('workflow_state')->colors([
                'gray' => 'draft', 'warning' => 'review', 'info' => 'approval',
                'primary' => 'signing', 'success' => 'executed', 'secondary' => 'archived',
            ]),
            Tables\Columns\TextColumn::make('counterparty.legal_name')->sortable()->limit(30),
            Tables\Columns\TextColumn::make('region.name')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('languages_count')
                ->label('Languages')
                ->counts('languages')
                ->badge()
                ->color('gray')
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\IconColumn::make('sharepoint_url')
                ->label('SP')
                ->boolean()
                ->trueIcon('heroicon-o-document-text')
                ->falseIcon('heroicon-o-minus')
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('contract_type')->options(['Commercial' => 'Commercial', 'Merchant' => 'Merchant']),
            Tables\Filters\SelectFilter::make('workflow_state')->options([
                'draft' => 'Draft', 'review' => 'Review', 'approval' => 'Approval',
                'signing' => 'Signing', 'executed' => 'Executed', 'archived' => 'Archived',
            ]),
            Tables\Filters\SelectFilter::make('region_id')->relationship('region', 'name'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('download')->icon('heroicon-o-arrow-down-tray')
                ->url(fn (Contract $record) => $record->storage_path ? app(\App\Services\ContractFileService::class)->getSignedUrl($record->storage_path, 60) : null)
                ->openUrlInNewTab()
                ->visible(fn (Contract $record) => (bool) $record->storage_path),
            Tables\Actions\Action::make('trigger_ai_analysis')
                ->label('AI Analysis')
                ->icon('heroicon-o-cpu-chip')
                ->form([
                    Forms\Components\Select::make('analysis_type')
                        ->options([
                            'summary' => 'Summary',
                            'extraction' => 'Field Extraction',
                            'risk' => 'Risk Assessment',
                            'deviation' => 'Template Deviation',
                            'obligations' => 'Obligations Register',
                        ])
                        ->required(),
                ])
                ->action(function (Contract $record, array $data) {
                    if (!$record->storage_path) {
                        \Filament\Notifications\Notification::make()
                            ->title('No file uploaded')
                            ->body('Upload a contract file before running AI analysis.')
                            ->danger()
                            ->send();
                        return;
                    }
                    ProcessAiAnalysis::dispatch(
                        $record->id,
                        $data['analysis_type'],
                        auth()->id(),
                        auth()->user()?->email,
                    );
                    \Filament\Notifications\Notification::make()
                        ->title('AI Analysis queued')
                        ->body('Analysis will complete in the background.')
                        ->success()
                        ->send();
                })
                ->visible(fn (Contract $record) => $record->storage_path !== null),
            Tables\Actions\Action::make('create_amendment')
                ->label('Amendment')
                ->icon('heroicon-o-document-plus')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\TextInput::make('title')->required()->placeholder('e.g. Amendment No. 1'),
                ])
                ->action(function (Contract $record, array $data) {
                    app(ContractLinkService::class)->createLinkedContract($record, 'amendment', $data['title'], auth()->user());
                    \Filament\Notifications\Notification::make()->title('Amendment created')->success()->send();
                }),
            Tables\Actions\Action::make('create_renewal')
                ->label('Renewal')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\TextInput::make('title')->required()->placeholder('e.g. Renewal 2027-2029'),
                    \Filament\Forms\Components\Select::make('renewal_type')
                        ->options(['extension' => 'Extension', 'new_version' => 'New Version'])
                        ->required()
                        ->default('new_version'),
                ])
                ->action(function (Contract $record, array $data) {
                    app(ContractLinkService::class)->createLinkedContract($record, 'renewal', $data['title'], auth()->user(), ['renewal_type' => $data['renewal_type']]);
                    \Filament\Notifications\Notification::make()->title('Renewal created')->success()->send();
                }),
            Tables\Actions\Action::make('add_side_letter')
                ->label('Side Letter')
                ->icon('heroicon-o-paper-clip')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\TextInput::make('title')->required()->placeholder('e.g. Side Letter - Data Sharing'),
                    \Filament\Forms\Components\FileUpload::make('file')
                        ->label('File (PDF/DOCX)')
                        ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->disk('s3')
                        ->directory('side_letters'),
                ])
                ->action(function (Contract $record, array $data) {
                    app(ContractLinkService::class)->createLinkedContract($record, 'side_letter', $data['title'], auth()->user(), ['storage_path' => $data['file'] ?? null]);
                    \Filament\Notifications\Notification::make()->title('Side letter linked')->success()->send();
                }),
        ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\KeyDatesRelationManager::class,
            RelationManagers\RemindersRelationManager::class,
            RelationManagers\ObligationsRelationManager::class,
            RelationManagers\ContractLanguagesRelationManager::class,
            RelationManagers\ContractLinksRelationManager::class,
            RelationManagers\AiAnalysisRelationManager::class,
            RelationManagers\BoldsignEnvelopesRelationManager::class,
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        if (in_array($record->workflow_state, ['executed', 'archived'])) {
            return false;
        }
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->title ?? $record->id;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Type' => $record->contract_type,
            'State' => $record->workflow_state,
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return ContractResource::getUrl('edit', ['record' => $record]);
    }
}
