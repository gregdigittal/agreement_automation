<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractResource\Pages;
use App\Filament\Resources\ContractResource\RelationManagers;
use App\Helpers\Feature;
use App\Jobs\ProcessAiAnalysis;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\WikiContract;
use App\Services\ContractLinkService;
use App\Services\RedlineService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('workflow_state', ['review', 'approval', 'staging'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['counterparty', 'region', 'entity', 'secondEntity', 'project']);

        $user = auth()->user();
        if ($user && !$user->hasRole('system_admin')) {
            $userId = $user->id;
            $query->where(function (Builder $q) use ($userId) {
                $q->where('is_restricted', false)
                  ->orWhereHas('authorizedUsers', fn (Builder $sub) => $sub->where('users.id', $userId));
            });
        }

        return $query;
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
            Forms\Components\Section::make('Contract Details')
                ->icon('heroicon-o-document-text')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('region_id')->relationship('region', 'name')->required(fn (?Contract $record): bool => ! $record || $record->workflow_state !== 'staging')->searchable()->preload()->live()
                        ->placeholder('Select region...')
                        ->helperText('The organisational region this contract falls under.')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. MENA'),
                            Forms\Components\Select::make('code')
                                ->options(\App\Models\Country::dropdownOptions())
                                ->required()
                                ->searchable()
                                ->placeholder('Select country code'),
                        ]),
                    Forms\Components\Select::make('entity_id')->relationship('entity', 'name')->required(fn (?Contract $record): bool => ! $record || $record->workflow_state !== 'staging')->searchable()->preload()->live()
                        ->placeholder('Select entity...')
                        ->helperText('The legal entity entering into this contract.')
                        ->createOptionForm([
                            Forms\Components\Select::make('region_id')
                                ->relationship('region', 'name')
                                ->required()
                                ->searchable()
                                ->preload(),
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. Digittal UAE'),
                            Forms\Components\TextInput::make('code')
                                ->maxLength(50)
                                ->placeholder('e.g. DGT-AE'),
                        ]),
                    Forms\Components\Select::make('project_id')->relationship('project', 'name')->required(fn (?Contract $record): bool => ! $record || $record->workflow_state !== 'staging')->searchable()->preload()
                        ->placeholder('Select project...')
                        ->helperText('The project or business unit this contract relates to.')
                        ->createOptionForm([
                            Forms\Components\Select::make('entity_id')
                                ->relationship('entity', 'name')
                                ->required()
                                ->searchable()
                                ->preload(),
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('code')
                                ->maxLength(50),
                        ]),
                    Forms\Components\Select::make('counterparty_id')->relationship('counterparty', 'legal_name')->searchable()->preload()
                        ->placeholder('Search for a counterparty...')
                        ->helperText('The external party entering into this agreement.')
                        ->visible(fn (Forms\Get $get): bool => $get('contract_type') !== 'Inter-Company')
                        ->required(fn (Forms\Get $get): bool => $get('contract_type') !== 'Inter-Company')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('legal_name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. Acme Corporation Ltd'),
                            Forms\Components\TextInput::make('registration_number')
                                ->maxLength(255)
                                ->placeholder('e.g. TL-2024-12345'),
                            Forms\Components\Select::make('jurisdiction')
                                ->options(\App\Models\Country::dropdownOptions())
                                ->searchable(),
                            Forms\Components\Select::make('status')
                                ->options(['Active' => 'Active'])
                                ->default('Active')
                                ->required(),
                        ])
                        ->rules([
                            fn () => function (string $attribute, $value, \Closure $fail) {
                                $cp = \App\Models\Counterparty::find($value);
                                if ($cp && in_array($cp->status, ['Suspended', 'Blacklisted'])) {
                                    $fail("Cannot create contract with a {$cp->status} counterparty.");
                                }
                            },
                        ]),
                    Forms\Components\Select::make('second_entity_id')
                        ->label('Second Group Entity')
                        ->relationship('secondEntity', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder('Select the other group entity...')
                        ->helperText('The other group company in this inter-company agreement.')
                        ->visible(fn (Forms\Get $get): bool => $get('contract_type') === 'Inter-Company')
                        ->required(fn (Forms\Get $get): bool => $get('contract_type') === 'Inter-Company')
                        ->different('entity_id')
                        ->createOptionForm([
                            Forms\Components\Select::make('region_id')
                                ->relationship('region', 'name')
                                ->required()
                                ->searchable()
                                ->preload(),
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. Digittal UK'),
                            Forms\Components\TextInput::make('code')
                                ->maxLength(50)
                                ->placeholder('e.g. DGT-UK'),
                        ]),
                    Forms\Components\Select::make('governing_law_id')
                        ->relationship('governingLaw', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder('Select governing law...')
                        ->helperText('The governing law for this contract (may differ from the jurisdiction where it is signed).')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. England and Wales'),
                            Forms\Components\Select::make('country_code')
                                ->label('Country')
                                ->options(\App\Models\Country::dropdownOptions())
                                ->searchable(),
                            Forms\Components\Select::make('legal_system')
                                ->options([
                                    'Common Law' => 'Common Law',
                                    'Civil Law' => 'Civil Law',
                                    'Sharia / Civil Law' => 'Sharia / Civil Law',
                                    'Mixed' => 'Mixed',
                                ])
                                ->searchable(),
                        ]),
                    Forms\Components\Select::make('contract_type')->options(ContractType::options())->required(fn (?Contract $record): bool => ! $record || $record->workflow_state !== 'staging')->live()
                        ->placeholder('Select contract type')
                        ->helperText('Determines which workflow template will be applied. Inter-Company is for agreements between two group entities.'),
                    Forms\Components\TextInput::make('title')->maxLength(255)->columnSpanFull()
                        ->placeholder('e.g. Master Services Agreement — Acme Corp')
                        ->helperText('A descriptive title for this contract.'),
                    Forms\Components\FileUpload::make('storage_path')->label('Contract File')->disk(config('ccrs.contracts_disk'))->directory('contracts')->columnSpanFull()
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->maxSize(51200)
                        ->helperText('Upload the contract document (max 50 MB). Accepted formats: PDF, DOCX.')
                        ->afterStateUpdated(function ($state, Set $set, $livewire) {
                            if ($state instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                $fileName = $state->getClientOriginalName();
                                $set('file_name', $fileName);

                                // Compute SHA-256 hash
                                $hash = hash_file('sha256', $state->getRealPath());
                                $set('file_hash', $hash);

                                // Check for duplicate contracts
                                $query = Contract::query();
                                $query->where(function ($q) use ($hash, $fileName) {
                                    $q->where('file_hash', $hash)
                                      ->orWhere('file_name', $fileName);
                                });

                                // Exclude current record when editing
                                if (isset($livewire->record) && $livewire->record?->id) {
                                    $query->where('id', '!=', $livewire->record->id);
                                }

                                $duplicates = $query->limit(5)->get(['id', 'title', 'contract_ref', 'file_name', 'created_at']);

                                if ($duplicates->isNotEmpty()) {
                                    $details = $duplicates->map(fn ($c) => "• {$c->contract_ref}: {$c->title} ({$c->file_name})")->join("\n");
                                    Notification::make()
                                        ->title('Duplicate contract suspected')
                                        ->body("The uploaded file matches {$duplicates->count()} existing contract(s):\n{$details}\n\nAre you sure you wish to proceed?")
                                        ->warning()
                                        ->persistent()
                                        ->send();
                                }
                            }
                        }),
                    Forms\Components\Hidden::make('file_name'),
                    Forms\Components\Hidden::make('file_hash'),
                ]),
            Forms\Components\Section::make('Collaboration')
                ->description('Enable collaboration tools for document negotiation with counterparties.')
                ->collapsed()
                ->schema([
                    Forms\Components\Toggle::make('exchange_room_enabled')
                        ->label('Enable Document Exchange Room')
                        ->helperText('Shared workspace for Digittal and counterparty to exchange document versions and comments.')
                        ->visible(fn () => Feature::exchangeRoom()),
                    Forms\Components\Toggle::make('sharepoint_enabled')
                        ->label('Enable SharePoint Integration')
                        ->helperText('Link a SharePoint folder for document collaboration via Microsoft 365.')
                        ->visible(fn () => Feature::sharePoint()),
                    Forms\Components\TextInput::make('sharepoint_url')
                        ->label('SharePoint URL')
                        ->url()
                        ->maxLength(2048)
                        ->placeholder('https://digittalgroup.sharepoint.com/sites/legal/...')
                        ->helperText('Link to the document on SharePoint for collaborative editing.')
                        ->visible(fn (Get $get) => $get('sharepoint_enabled') || ! Feature::sharePoint()),
                    Forms\Components\TextInput::make('sharepoint_version')
                        ->label('SharePoint Version')
                        ->maxLength(50)
                        ->placeholder('e.g. 2.3')
                        ->helperText('Track the current SharePoint document version number.')
                        ->visible(fn () => ! Feature::sharePoint()),
                ])
                ->columns(2),
            Forms\Components\Section::make('Access Control')
                ->description('Restrict this contract to specific users. When restricted, only authorized users and system admins can see it.')
                ->collapsed()
                ->visible(fn (): bool => auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false)
                ->schema([
                    Forms\Components\Toggle::make('is_restricted')
                        ->label('Restricted Access')
                        ->helperText('When enabled, only authorized users listed below (and system admins) can view or edit this contract.')
                        ->live()
                        ->afterStateUpdated(function ($state, ?Contract $record) {
                            if ($record) {
                                \App\Services\AuditService::log(
                                    $state ? 'access_restricted' : 'access_unrestricted',
                                    'contract',
                                    $record->id,
                                );
                            }
                        }),
                    Forms\Components\Select::make('authorizedUsers')
                        ->label('Authorized Users')
                        ->relationship('authorizedUsers', 'name')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('is_restricted'))
                        ->helperText('Select users who should have access to this restricted contract.'),
                ]),
        ])->disabled(fn (?Contract $record): bool =>
        $record !== null && in_array($record->workflow_state, ['executed', 'archived'])
    );
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('contract_ref')
                ->label('Ref')
                ->searchable()
                ->sortable()
                ->copyable()
                ->tooltip('Click to copy reference')
                ->weight('bold')
                ->color('primary'),
            Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(40),
            Tables\Columns\TextColumn::make('contract_type')->badge(),
            Tables\Columns\TextColumn::make('workflow_state')->badge()->description('Current lifecycle stage')->color(fn ($state) => match($state) { 'staging' => 'purple', 'draft' => 'gray', 'review' => 'warning', 'approval' => 'info', 'signing' => 'primary', 'countersign' => 'warning', 'executed' => 'success', 'archived' => 'gray', default => 'gray' }),
            Tables\Columns\TextColumn::make('counterparty.legal_name')->sortable()->limit(30)->placeholder('—')->toggleable(),
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
            Tables\Columns\IconColumn::make('is_restricted')
                ->label('Locked')
                ->boolean()
                ->trueIcon('heroicon-o-lock-closed')
                ->falseIcon('heroicon-o-lock-open')
                ->trueColor('danger')
                ->falseColor('gray')
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('contract')
                ->label('Contract')
                ->options(fn () => Contract::query()
                    ->orderByDesc('created_at')
                    ->limit(200)
                    ->get()
                    ->mapWithKeys(fn (Contract $c) => [
                        $c->id => ($c->contract_ref ? "{$c->contract_ref} — " : '') . ($c->title ?? 'Untitled'),
                    ])
                    ->toArray()
                )
                ->searchable()
                ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $q, $id) => $q->where('contracts.id', $id)))
                ->placeholder('Filter by contract...'),
            Tables\Filters\SelectFilter::make('contract_type')->options(ContractType::options()),
            Tables\Filters\SelectFilter::make('workflow_state')->options([
                'staging' => 'Staging', 'draft' => 'Draft', 'review' => 'Review', 'approval' => 'Approval',
                'signing' => 'Signing', 'countersign' => 'Countersign', 'executed' => 'Executed', 'archived' => 'Archived',
            ]),
            Tables\Filters\SelectFilter::make('region_id')->relationship('region', 'name'),
        ])
        ->actions([
            Tables\Actions\EditAction::make()
                ->visible(fn (Contract $record): bool => ! in_array($record->workflow_state, ['executed', 'completed'])),
            Tables\Actions\Action::make('download')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (Contract $record) => $record->storage_path ? app(\App\Services\ContractFileService::class)->getSignedUrl($record->storage_path) : null)
                ->openUrlInNewTab()
                ->visible(fn (Contract $record) => (bool) $record->storage_path),
            Tables\Actions\Action::make('trigger_ai_analysis')
                ->label('AI Analysis')
                ->icon('heroicon-o-cpu-chip')
                ->color('info')
                ->modalHeading('Run AI Analysis')
                ->modalDescription('Select one or more analysis types to run. Results appear in the AI tabs and Discovery Review page.')
                ->form([
                    Forms\Components\CheckboxList::make('analysis_types')
                        ->label('Analysis Types')
                        ->options([
                            'summary' => 'Summary — overall contract synopsis',
                            'extraction' => 'Field Extraction — key terms, dates & values',
                            'risk' => 'Risk Assessment — risk scores & red flags',
                            'deviation' => 'Template Deviation — compare against standard templates',
                            'obligations' => 'Obligations Register — deadlines & responsibilities',
                            'discovery' => 'Auto-Discovery — extract counterparties, jurisdictions & governing law',
                        ])
                        ->required()
                        ->columns(1)
                        ->bulkToggleable(),
                ])
                ->action(function (Contract $record, array $data) {
                    if (! $record->storage_path) {
                        Notification::make()
                            ->title('No file uploaded')
                            ->body('Upload a contract file before running AI analysis.')
                            ->danger()
                            ->send();
                        return;
                    }
                    $types = $data['analysis_types'] ?? [];

                    // Warn if pending discoveries already exist
                    if (in_array('discovery', $types)) {
                        $pendingCount = \App\Models\AiDiscoveryDraft::where('contract_id', $record->id)
                            ->where('status', 'pending')
                            ->count();

                        if ($pendingCount > 0) {
                            Notification::make()
                                ->title('Existing discoveries detected')
                                ->body("This contract already has {$pendingCount} pending discovery draft(s) in the review queue. Re-running will not create duplicates.")
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    }

                    \Illuminate\Support\Facades\Log::info('AI Analysis dispatch: starting', [
                        'contract_id' => $record->id,
                        'types' => $types,
                        'storage_path' => $record->storage_path,
                        'queue_connection' => config('queue.default'),
                        'actor' => auth()->user()?->email,
                    ]);

                    foreach ($types as $type) {
                        ProcessAiAnalysis::dispatch(
                            $record->id,
                            $type,
                            auth()->id(),
                            auth()->user()?->email,
                        );
                        \Illuminate\Support\Facades\Log::info("AI Analysis dispatch: dispatched {$type}", [
                            'contract_id' => $record->id,
                            'analysis_type' => $type,
                        ]);
                    }
                    $labels = collect($types)->join(', ');
                    $discoveryNote = in_array('discovery', $types)
                        ? ' Discovery results will appear on the AI Discovery Review page.'
                        : '';
                    Notification::make()
                        ->title(count($types) . ' analysis job(s) queued')
                        ->body("Running: {$labels}.{$discoveryNote}")
                        ->success()
                        ->send();
                })
                ->visible(fn (Contract $record) => $record->storage_path !== null),
            Tables\Actions\ActionGroup::make([
            Tables\Actions\Action::make('complete_setup')
                ->label('Complete Setup')
                ->icon('heroicon-o-check-circle')
                ->color('purple')
                ->visible(fn (Contract $record): bool => $record->workflow_state === 'staging')
                ->form(fn (Contract $record) => [
                    Forms\Components\Select::make('region_id')
                        ->relationship('region', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->default($record->region_id),
                    Forms\Components\Select::make('entity_id')
                        ->relationship('entity', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->default($record->entity_id),
                    Forms\Components\Select::make('project_id')
                        ->relationship('project', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->default($record->project_id),
                    Forms\Components\Select::make('contract_type')
                        ->options(ContractType::options())
                        ->required()
                        ->default($record->contract_type),
                    Forms\Components\Select::make('counterparty_id')
                        ->relationship('counterparty', 'legal_name')
                        ->searchable()
                        ->preload()
                        ->default($record->counterparty_id),
                    Forms\Components\TextInput::make('title')
                        ->maxLength(255)
                        ->default($record->title),
                ])
                ->modalHeading('Complete Contract Setup')
                ->modalDescription('Fill in the required metadata to promote this staged contract to draft status.')
                ->action(function (Contract $record, array $data): void {
                    $record->update(array_merge($data, ['workflow_state' => 'draft']));
                    Notification::make()
                        ->title('Contract promoted to Draft')
                        ->body('Metadata saved. The contract is now in draft status.')
                        ->success()
                        ->send();
                }),
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
                        ->maxSize(51200)
                        ->disk(config('ccrs.contracts_disk'))
                        ->visibility('private')
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
                    // Only visible when NOT using in-house signing (BoldSign legacy path)
                    if (Feature::inHouseSigning()) {
                        return false;
                    }
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
                            // "All Projects" authorities (no pivot rows) OR scoped to this project
                            $q->whereDoesntHave('projects')
                              ->orWhereHas('projects', fn ($sub) => $sub->where('projects.id', $record->project_id));
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
            ])
                ->label('More')
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('More actions'),
        ])
        ->bulkActions([
            Tables\Actions\BulkAction::make('export')
                ->label('Export Selected')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                    $csv = "Title,Type,State,Counterparty,Region,Created\n";
                    foreach ($records as $record) {
                        $csv .= '"' . str_replace('"', '""', $record->title) . '","' . $record->contract_type . '","' . $record->workflow_state . '","' . str_replace('"', '""', $record->counterparty?->legal_name ?? '') . '","' . ($record->region?->name ?? '') . '","' . $record->created_at->format('Y-m-d') . "\"\n";
                    }
                    return response()->streamDownload(fn () => print($csv), 'contracts_export.csv', ['Content-Type' => 'text/csv']);
                }),
            Tables\Actions\DeleteBulkAction::make(),
        ])
        ->emptyStateHeading('No contracts yet')
        ->emptyStateDescription('Create your first contract to get started.')
        ->emptyStateIcon('heroicon-o-document-text')
        ->emptyStateActions([
            Tables\Actions\CreateAction::make()
                ->label('Create Contract'),
        ]);
    }

    public static function getRelationManagers(): array
    {
        $relations = [
            RelationManagers\KeyDatesRelationManager::class,
            RelationManagers\RemindersRelationManager::class,
            RelationManagers\ObligationsRelationManager::class,
            RelationManagers\ContractLanguagesRelationManager::class,
            RelationManagers\ContractLinksRelationManager::class,
            RelationManagers\AiAnalysisRelationManager::class,
            RelationManagers\RedlineSessionsRelationManager::class,
            RelationManagers\ComplianceFindingsRelationManager::class,
            RelationManagers\KycPackRelationManager::class,
        ];

        if (Feature::inHouseSigning()) {
            $relations[] = RelationManagers\SigningSessionsRelationManager::class;
        } else {
            // BoldSign legacy path — only add if the class still exists
            if (class_exists(RelationManagers\BoldsignEnvelopesRelationManager::class)) {
                $relations[] = RelationManagers\BoldsignEnvelopesRelationManager::class;
            }
        }

        if (Feature::exchangeRoom()) {
            $relations[] = RelationManagers\ExchangeRoomRelationManager::class;
        }

        if (Feature::sharePoint()) {
            $relations[] = RelationManagers\SharePointRelationManager::class;
        }

        return $relations;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit']) ?? false;
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
        $ref = $record->contract_ref ? "[{$record->contract_ref}] " : '';

        return $ref . ($record->title ?? 'Untitled');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Ref' => $record->contract_ref,
            'Type' => $record->contract_type,
            'State' => $record->workflow_state,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'contract_ref', 'contract_type'];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return ContractResource::getUrl('edit', ['record' => $record]);
    }
}
