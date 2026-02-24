<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractResource\Pages;
use App\Filament\Resources\ContractResource\RelationManagers;
use App\Helpers\Feature;
use App\Jobs\ProcessAiAnalysis;
use App\Models\Contract;
use App\Models\WikiContract;
use App\Services\ContractLinkService;
use App\Services\RedlineService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['counterparty', 'region', 'entity', 'project']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Linked From')
                ->visible(fn (?Contract $record) => $record && $record->parentLinks()->exists())
                ->schema([
                    Forms\Components\Placeholder::make('parent_link_info')
                        ->label('')
                        ->content(fn (Contract $record) => $record->parentLinks->first() ? 'Link type: ' . $record->parentLinks->first()->link_type . ' | Parent: ' . ($record->parentLinks->first()->parentContract?->title ?? $record->parentLinks->first()->parent_contract_id) : ''),
                ])
                ->columns(1),
                        Forms\Components\Select::make('region_id')->relationship('region', 'name')->required()->searchable()->live()
                ->placeholder('Select region...'),
            Forms\Components\Select::make('entity_id')->relationship('entity', 'name')->required()->searchable()->live()
                ->placeholder('Select entity...'),
            Forms\Components\Select::make('project_id')->relationship('project', 'name')->required()->searchable()
                ->placeholder('Select project...'),
            Forms\Components\Select::make('counterparty_id')->relationship('counterparty', 'legal_name')->required()->searchable()
                ->placeholder('Search for a counterparty...')
                ->helperText('The external party entering into this agreement.'),
            Forms\Components\Select::make('contract_type')->options(['Commercial' => 'Commercial', 'Merchant' => 'Merchant'])->required()
                ->placeholder('Select contract type')
                ->helperText('Determines which workflow template will be applied.'),
            Forms\Components\TextInput::make('title')->maxLength(255)
                ->placeholder('e.g. Master Services Agreement — Acme Corp')
                ->helperText('A descriptive title for this contract.'),
            Forms\Components\FileUpload::make('storage_path')->label('Contract File')->disk('s3')->directory('contracts')
                ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                ->afterStateUpdated(function ($state, Set $set) {
                    if ($state instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                        $set('file_name', $state->getClientOriginalName());
                    }
                }),
            Forms\Components\Hidden::make('file_name'),
            Forms\Components\Section::make('SharePoint Collaboration')
                ->description('Link the SharePoint document URL for collaborative review and track the version.')
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
        ])->disabled(fn (?Contract $record): bool =>
        $record !== null && in_array($record->workflow_state, ['executed', 'archived'])
    );
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(40),
            Tables\Columns\TextColumn::make('contract_type')->badge(),
            Tables\Columns\TextColumn::make('workflow_state')->badge()->description('Current lifecycle stage')->color(fn ($state) => match($state) { 'draft' => 'gray', 'review' => 'warning', 'approval' => 'info', 'signing' => 'primary', 'countersign' => 'warning', 'executed' => 'success', 'archived' => 'gray', default => 'gray' }),
            Tables\Columns\TextColumn::make('counterparty.legal_name')->sortable()->limit(30),
            Tables\Columns\TextColumn::make('region.name')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('languages_count')->badge()
                ->label('Languages')
                ->counts('languages')
                
                ->color('gray')
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\IconColumn::make('sharepoint_url')
                ->label('SP')
                ->boolean()
                ->trueIcon('heroicon-o-document-text')
                ->falseIcon('heroicon-o-minus')
                ->tooltip(fn (Contract $record) => $record->sharepoint_url)
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('contract_type')->options(['Commercial' => 'Commercial', 'Merchant' => 'Merchant']),
            Tables\Filters\SelectFilter::make('workflow_state')->options([
                'draft' => 'Draft', 'review' => 'Review', 'approval' => 'Approval',
                'signing' => 'Signing', 'countersign' => 'Countersign', 'executed' => 'Executed', 'archived' => 'Archived',
            ]),
            Tables\Filters\SelectFilter::make('region_id')->relationship('region', 'name'),
        ])
        ->actions([
            Tables\Actions\EditAction::make()
            ->visible(fn (Contract $record): bool => !in_array($record->workflow_state, ['executed', 'completed'])),
            Tables\Actions\Action::make('download')->icon('heroicon-o-arrow-down-tray')
                ->url(fn (Contract $record) => $record->storage_path ? app(\App\Services\ContractFileService::class)->getSignedUrl($record->storage_path) : null)
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
                ->label('Create Amendment')
                ->icon('heroicon-o-document-plus')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\TextInput::make('title')->required()->placeholder('e.g. Amendment No. 1'),
                    \Filament\Forms\Components\Textarea::make('notes')->rows(3)->nullable(),
                ])
                ->action(function (Contract $record, array $data) {
                    $child = app(ContractLinkService::class)->createLinkedContract($record, 'amendment', $data['title'], auth()->user(), ['notes' => $data['notes'] ?? null]);
                    if (! empty($data['notes'])) {
                        \App\Services\AuditService::log('contract.amendment_notes', 'contract', $child->id, ['notes' => $data['notes']], auth()->user());
                    }
                    \Filament\Notifications\Notification::make()->title('Amendment created')->success()->send();
                }),
            Tables\Actions\Action::make('create_renewal')
                ->label('Create Renewal')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\TextInput::make('title')->required()->placeholder('e.g. Renewal 2027-2029'),
                    \Filament\Forms\Components\Select::make('renewal_type')
                        ->options(['extension' => 'Extension', 'new_version' => 'New Version'])
                        ->required()
                        ->default('new_version'),
                    \Filament\Forms\Components\DatePicker::make('new_expiry_date')
                        ->label('New Expiry Date')
                        ->visible(fn ($get) => $get('renewal_type') === 'extension'),
                ])
                ->action(function (Contract $record, array $data) {
                    app(ContractLinkService::class)->createLinkedContract(
                        $record,
                        'renewal',
                        $data['title'],
                        auth()->user(),
                        [
                            'renewal_type' => $data['renewal_type'],
                            'new_expiry_date' => $data['new_expiry_date'] ?? null,
                        ],
                    );
                    \Filament\Notifications\Notification::make()->title('Renewal created')->success()->send();
                }),
            Tables\Actions\Action::make('add_side_letter')
                ->label('Add Side Letter')
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
            Tables\Actions\Action::make('sendForCountersigning')
                ->label('Send for Countersigning')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(function (Contract $record): bool {
                    $instance = $record->activeWorkflowInstance;
                    if (!$instance || !$instance->template) {
                        return false;
                    }
                    $stages = collect($instance->template->stages);
                    $currentStage = $stages->firstWhere('name', $instance->current_stage);
                    return ($currentStage['type'] ?? null) === 'countersign';
                })
                ->form(function (Contract $record): array {
                    $authorities = \App\Models\SigningAuthority::query()
                        ->where('entity_id', $record->entity_id)
                        ->where(function ($q) use ($record) {
                            $q->whereNull('project_id')
                              ->orWhere('project_id', $record->project_id);
                        })
                        ->with('user')
                        ->get();

                    $defaultSigners = $authorities->map(fn ($auth, $index) => [
                        'user_id' => $auth->user_id,
                        'name' => $auth->user->name ?? '',
                        'email' => $auth->user->email ?? '',
                        'order' => $index + 1,
                    ])->toArray();

                    return [
                        \Filament\Forms\Components\Repeater::make('signers')
                            ->label('Internal Digittal Signers')
                            ->schema([
                                \Filament\Forms\Components\Select::make('user_id')
                                    ->label('User')
                                    ->options(\App\Models\User::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                        if ($state) {
                                            $user = \App\Models\User::find($state);
                                            $set('name', $user?->name ?? '');
                                            $set('email', $user?->email ?? '');
                                        }
                                    }),
                                \Filament\Forms\Components\TextInput::make('name')->required(),
                                \Filament\Forms\Components\TextInput::make('email')->email()->required(),
                                \Filament\Forms\Components\TextInput::make('order')
                                    ->label('Signing Order')
                                    ->numeric()
                                    ->default(1)
                                    ->required(),
                            ])
                            ->default($defaultSigners)
                            ->minItems(1)
                            ->columns(4),
                    ];
                })
                ->requiresConfirmation()
                ->modalHeading('Send for Countersigning')
                ->modalDescription('This will create a BoldSign envelope with only the internal Digittal signers. The counterparty has already signed this document externally.')
                ->action(function (Contract $record, array $data): void {
                    $service = app(\App\Services\BoldsignService::class);
                    $envelope = $service->createCountersignEnvelope($record, $data['signers']);
                    Notification::make()
                        ->title('Countersign envelope sent')
                        ->body("BoldSign document ID: {$envelope->boldsign_document_id}")
                        ->success()
                        ->send();
                }),
            Tables\Actions\Action::make('startRedlineReview')
                ->label('Start Redline Review')
                ->icon('heroicon-o-scale')
                ->color('info')
                ->visible(function (Contract $record): bool {
                    return Feature::enabled('redlining')
                        && !empty($record->storage_path);
                })
                ->form(function (Contract $record) {
                    return [
                        Forms\Components\Select::make('wiki_contract_id')
                            ->label('WikiContract Template')
                            ->options(function () use ($record) {
                                return WikiContract::where('status', 'published')
                                    ->where('region_id', $record->region_id)
                                    ->orderByDesc('version')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->placeholder('Auto-select (latest for this region)')
                            ->helperText('Choose a template to compare against, or leave blank to auto-select the latest published template for this contract\'s region.')
                            ->searchable(),
                    ];
                })
                ->requiresConfirmation()
                ->modalHeading('Start Redline Review')
                ->modalDescription('This will send the contract to the AI engine for clause-by-clause comparison against the selected WikiContract template. The analysis may take a few minutes for long contracts.')
                ->action(function (Contract $record, array $data): void {
                    $template = null;
                    if (!empty($data['wiki_contract_id'])) {
                        $template = WikiContract::find($data['wiki_contract_id']);
                    }

                    $session = app(RedlineService::class)->startSession(
                        $record,
                        $template,
                        auth()->user(),
                    );

                    Notification::make()
                        ->title('Redline review started')
                        ->body('AI analysis is processing. You will be redirected to the review page.')
                        ->success()
                        ->send();

                    redirect(ContractResource::getUrl('redline-session', [
                        'record' => $record->id,
                        'session' => $session->id,
                    ]));
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
            RelationManagers\RedlineSessionsRelationManager::class,
            RelationManagers\ComplianceFindingsRelationManager::class,
            RelationManagers\KycPackRelationManager::class,
            RelationManagers\SigningSessionsRelationManager::class,
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

    public static function canView(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial', 'finance', 'audit']) ?? false;
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
            'view' => Pages\ViewContract::route('/{record}'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
            'redline-session' => Pages\RedlineSessionPage::route('/{record}/redline/{session}'),
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
