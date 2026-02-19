# Cursor Prompt — Laravel Migration Phase B: Filament Resources + Business Logic

## Context

Phase A has been completed. The following now exist:
- All 25 Laravel migrations (27 MySQL tables)
- All 27 Eloquent models in `app/Models/`
- Filament AdminPanelProvider with stub Resource registrations
- Docker Compose with 4 services running

This phase implements **all Filament Resources, Custom Pages, Widgets, Service classes, scheduled Jobs, and middleware.** This is the largest phase — it contains all the business logic.

**Reference the existing FastAPI implementation in `apps/api/app/` for business logic.** The goal is to port the functionality, not preserve the exact code structure.

---

## Task 1: Implement All Service Classes

Create the following in `app/Services/`:

### `AuditService.php`
Provides a static `log()` method called after every meaningful action.
```php
// Port of apps/api/app/audit/service.py::audit_log()
public static function log(
    string $action,
    string $resourceType,
    ?string $resourceId,
    array $details = [],
    ?User $actor = null
): void {
    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'at' => now(),
        'actor_id' => $actor?->id,
        'actor_email' => $actor?->email,
        'action' => $action,
        'resource_type' => $resourceType,
        'resource_id' => $resourceId,
        'details' => $details ?: null,
        'ip_address' => request()->ip(),
    ]);
}
```
Note: `AuditLog` model has `boot()` that throws on update/delete — never attempt to modify audit log rows.

### `WorkflowService.php`
Port of `apps/api/app/workflows/service.py`. Key methods:

**`startWorkflow(string $contractId, string $templateId, User $actor): WorkflowInstance`**
- Validate template exists and is published
- Use `DB::transaction(fn() => ...)` with `lockForUpdate()` to check for existing active instance:
  ```php
  $existing = WorkflowInstance::where('contract_id', $contractId)
      ->where('state', 'active')
      ->lockForUpdate()
      ->first();
  if ($existing) throw new \RuntimeException('Active workflow already exists for this contract');
  ```
- Create `WorkflowInstance` with first stage, state=active
- Update `contracts.workflow_state` to first stage
- Call `AuditService::log('workflow_instance.start', 'workflow_instance', $instance->id, [], $actor)`
- Return instance

**`recordAction(WorkflowInstance $instance, string $stageName, string $action, User $actor, ?string $comment = null, ?array $artifacts = null): WorkflowStageAction`**
- Validate `$instance->current_stage === $stageName`
- Load template stages from `$instance->template->stages`
- Check actor authorization (role/signing authority — see state machine logic in `apps/api/app/workflows/state_machine.py`)
- Determine next stage; if null + action=approve → complete the workflow:
  - Update instance state=completed, completed_at=now()
  - Update contract.workflow_state='completed'
- Else → advance to next stage, update both instance and contract
- Create `WorkflowStageAction`
- Audit log
- Return action

**`getActiveInstance(string $contractId): ?WorkflowInstance`** — simple query

**`getHistory(string $instanceId): Collection`** — ordered stage actions

### `ContractFileService.php`
Handles S3 uploads for contracts and wiki-contracts.

**`upload(UploadedFile $file, string $contractId, string $disk = 'contracts'): array`**
- Validate file is PDF or DOCX: `$file->getMimeType()` must be `application/pdf` or `application/vnd.openxmlformats-officedocument.wordprocessingml.document`
- Generate path: `"{$contractId}/{$file->hashName()}"`
- Store: `$path = Storage::disk('s3')->putFileAs("contracts/{$contractId}", $file, $file->getClientOriginalName())`
- Return `['storage_path' => $path, 'file_name' => $file->getClientOriginalName()]`

**`getSignedUrl(string $storagePath, int $minutes = 60): string`**
- Return `Storage::disk('s3')->temporaryUrl($storagePath, now()->addMinutes($minutes))`

**`download(string $storagePath): string`** (returns file bytes as string)
- Return `Storage::disk('s3')->get($storagePath)`

### `BoldsignService.php`
Port of `apps/api/app/boldsign/service.py`.

**`sendToSign(Contract $contract, array $signers, string $signingOrder = 'sequential'): BoldsignEnvelope`**
- POST to `{BOLDSIGN_API_URL}/v1/document/send` with Guzzle
- Create `BoldsignEnvelope` record
- Update `contract->signing_status = 'sent'`
- Audit log

**`getSigningStatus(string $documentId): array`**
- GET `{BOLDSIGN_API_URL}/v1/document/properties?documentId={id}`
- Return status data

**`handleWebhook(array $payload): void`**
- Find envelope by `boldsign_document_id`
- Update envelope status
- If completed → update `contract->signing_status = 'completed'`
- Audit log

**`verifyWebhookSignature(string $rawBody, string $signature, string $secret): bool`**
- `hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)`

### `MerchantAgreementService.php`
Port of `apps/api/app/merchant_agreements/service.py`.

**`generate(Contract $contract, array $inputs, User $actor): array`**
- Load wiki template from `$inputs['template_id']`
- Get template file from S3 via `ContractFileService::download()`
- Use `PhpOffice\PhpWord` (via `barryvdh/laravel-dompdf`) to populate template variables
- Upload generated document to S3 as a new contract version
- Create `MerchantAgreementInput` record
- Audit log
- Return `['contract_id' => ..., 'generated_at' => ...]`

### `NotificationService.php`
Port of `apps/api/app/notifications/service.py`.

**`listNotifications(User $user, bool $unreadOnly = false): Collection`**
- Query notifications where `recipient_user_id = $user->id` OR `recipient_email = $user->email`
- Apply `whereNull('read_at')` if `$unreadOnly`

**`markRead(string $notificationId, User $user): void`**
- Update `read_at = now()` where id matches and recipient matches user

**`markAllRead(User $user): int`**
- Update all unread notifications for this user, return count

**`create(array $data): Notification`**
- Insert notification, return model

### `ReminderService.php`
Port of `apps/api/app/reminders/service.py`.

**`processReminders(): int`**
- Query active reminders where `next_due_at <= now()` AND (`last_sent_at IS NULL` OR `last_sent_at < next_due_at`)
- For each: create `Notification`, advance `next_due_at` by `lead_days` days
- Return count of sent reminders

### `EscalationService.php`
Port of `apps/api/app/escalation/service.py`.

**`checkSlaBreaches(): int`**
- Load active workflow instances with their stage start times
- For each active stage, check if hours since stage entered > SLA breach hours in escalation_rules
- Create `EscalationEvent` if not already escalated
- Send notification to `escalate_to_role` user group (look up by role in users table)
- Return count

**`resolveEscalation(string $eventId, User $actor): EscalationEvent`**
- Update event: `resolved_at = now()`, `resolved_by = $actor->email`
- Audit log

---

## Task 2: Implement All Scheduled Jobs

Create in `app/Jobs/`:

### `SendReminders.php`
```php
// Called: daily at 08:00
public function handle(ReminderService $service): void {
    $count = $service->processReminders();
    Log::info("Sent {$count} reminders");
}
```

### `CheckSlaBreaches.php`
```php
// Called: hourly
public function handle(EscalationService $service): void {
    $count = $service->checkSlaBreaches();
    Log::info("Checked SLA breaches, escalated {$count}");
}
```

### `SendPendingNotifications.php`
```php
// Called: every 5 minutes
// Fetch pending notifications, send via Laravel Mail (SendGrid), update status to 'sent' or 'failed'
public function handle(): void {
    $notifications = Notification::where('status', 'pending')
        ->whereNotNull('recipient_email')
        ->limit(50)
        ->get();
    foreach ($notifications as $notification) {
        try {
            Mail::to($notification->recipient_email)
                ->send(new \App\Mail\NotificationMail($notification));
            $notification->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Exception $e) {
            $notification->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }
    }
}
```

### `ProcessAiAnalysis.php`
Placeholder for Phase C — create the class but leave `handle()` body empty with a `// TODO: implement in Phase C` comment. The class must accept `string $contractId` and `string $analysisType` constructor arguments and be `Queueable`.

### Register in `routes/console.php` (Laravel 11 style):
```php
use Illuminate\Support\Facades\Schedule;

Schedule::job(new \App\Jobs\SendReminders)->dailyAt('08:00');
Schedule::job(new \App\Jobs\CheckSlaBreaches)->hourly();
Schedule::job(new \App\Jobs\SendPendingNotifications)->everyFiveMinutes();
```

---

## Task 3: Create Mail Template

Create `app/Mail/NotificationMail.php` using `Mailable`:
- Subject from `$notification->subject`
- Body from `$notification->body`
- View: `resources/views/emails/notification.blade.php` (simple HTML template)

---

## Task 4: Implement Middleware

### `app/Http/Middleware/AuditMiddleware.php`
Logs HTTP actions for Filament form submissions (POST/PUT/PATCH/DELETE to `/admin/*`):
```php
public function handle(Request $request, Closure $next): Response {
    $response = $next($request);
    if ($request->isMethod('GET') || !str_starts_with($request->path(), 'admin')) {
        return $response;
    }
    // Determine resource type from URL pattern
    $path = $request->path(); // e.g. 'admin/contracts/create'
    $parts = explode('/', $path);
    $resourceType = $parts[1] ?? 'unknown';
    AuditService::log(
        action: $request->method() . ':' . $path,
        resourceType: $resourceType,
        resourceId: $request->route('record'),
        actor: auth()->user(),
    );
    return $response;
}
```
Register in `bootstrap/app.php` for web middleware group.

---

## Task 5: Implement BoldSign Webhook Controller

Create `app/Http/Controllers/Webhooks/BoldsignWebhookController.php`:
```php
public function handle(Request $request): JsonResponse {
    $secret = config('ccrs.boldsign_webhook_secret');
    $signature = $request->header('X-BoldSign-Signature', '');
    if (!app(BoldsignService::class)->verifyWebhookSignature($request->getContent(), $signature, $secret)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }
    app(BoldsignService::class)->handleWebhook($request->all());
    return response()->json(['ok' => true]);
}
```

Register in `routes/api.php`:
```php
Route::post('/webhooks/boldsign', [\App\Http\Controllers\Webhooks\BoldsignWebhookController::class, 'handle']);
```

---

## Task 6: Implement All Filament Resources

### 6.1 `ContractResource.php`

**Table columns:**
```php
Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
Tables\Columns\BadgeColumn::make('contract_type')
    ->colors(['warning' => 'Merchant', 'primary' => 'Commercial']),
Tables\Columns\TextColumn::make('counterparty.legal_name')->label('Counterparty')->searchable(),
Tables\Columns\BadgeColumn::make('workflow_state')
    ->colors([
        'gray' => 'draft',
        'warning' => fn($state) => in_array($state, ['review', 'legal_review']),
        'success' => 'completed',
        'danger' => 'cancelled',
    ]),
Tables\Columns\BadgeColumn::make('signing_status')
    ->colors(['success' => 'completed', 'warning' => 'sent', 'gray' => null]),
Tables\Columns\TextColumn::make('entity.name')->label('Entity'),
Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
```

**Form fields:**
```php
Forms\Components\Select::make('region_id')
    ->relationship('region', 'name')->required()->reactive(),
Forms\Components\Select::make('entity_id')
    ->relationship('entity', 'name')
    ->options(fn(Get $get) => \App\Models\Entity::where('region_id', $get('region_id'))->pluck('name', 'id'))
    ->required()->reactive(),
Forms\Components\Select::make('project_id')
    ->relationship('project', 'name')
    ->options(fn(Get $get) => \App\Models\Project::where('entity_id', $get('entity_id'))->pluck('name', 'id'))
    ->required(),
Forms\Components\Select::make('counterparty_id')
    ->relationship('counterparty', 'legal_name')->searchable()->required(),
Forms\Components\Select::make('contract_type')
    ->options(['Commercial' => 'Commercial', 'Merchant' => 'Merchant'])->required(),
Forms\Components\TextInput::make('title'),
Forms\Components\FileUpload::make('storage_path')
    ->label('Contract File')
    ->disk('s3')->directory('contracts')
    ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
    ->afterStateUpdated(function ($state, Set $set) {
        if ($state instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $set('file_name', $state->getClientOriginalName());
        }
    }),
```

**Table actions (per row):**
- `Action::make('download')` → generate signed URL and redirect
- `Action::make('trigger_ai_analysis')` → opens modal with `analysis_type` Select, dispatches `ProcessAiAnalysis::dispatch($record->id, $analysisType)` to queue
- `Action::make('send_to_sign')` → opens modal (System Admin/Legal only), calls `BoldsignService::sendToSign()`
- `DeleteAction` — System Admin only; blocked if workflow_state is 'completed' or 'executed'

**Relation managers (create as separate `RelationManager` classes):**
- `KeyDatesRelationManager` — columns: date_type, date_value, is_verified; form: date_type, date_value, description, reminder_days (TagsInput)
- `RemindersRelationManager` — columns: reminder_type, lead_days, channel, next_due_at, is_active; form: all fields
- `ObligationsRelationManager` — columns: obligation_type, description (truncated), due_date, status; form: all fields
- `ContractLanguagesRelationManager` — columns: language_code, is_primary; form: language_code, is_primary, file upload
- `ContractLinksRelationManager` — columns: child contract title, link_type; form: child_contract_id Select, link_type Select
- `AiAnalysisRelationManager` (read-only) — columns: analysis_type, status (badge), confidence_score, cost_usd, created_at; no create/edit
- `BoldsignEnvelopesRelationManager` (read-only) — columns: boldsign_document_id, status, sent_at, completed_at; no create/edit

**Authorization:**
```php
public static function canCreate(): bool {
    return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial']) ?? false;
}
public static function canEdit(Model $record): bool {
    return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
}
public static function canDelete(Model $record): bool {
    return auth()->user()?->hasRole('system_admin') ?? false;
}
```

### 6.2 `CounterpartyResource.php`

**Table columns:** legal_name (searchable), registration_number, jurisdiction, status (badge: Active=success, Suspended=warning, Blacklisted=danger), preferred_language

**Table actions:**
- `Action::make('override_request')` — opens modal with `reason` textarea, creates `OverrideRequest` record for system_admin/legal review. Available to commercial role.
- `Action::make('merge')` — System Admin only; opens modal with target counterparty select; calls merge logic (reassign all FK references from source to target, create `CounterpartyMerge` record)

**Form fields:** legal_name, registration_number, address (Textarea), jurisdiction, status (Select), status_reason (visible only when status != Active), preferred_language (Select with language codes: en, fr, ar, es, zh, pt, de)

**Filters:** SelectFilter by status, TextFilter by jurisdiction

**Relation managers:** `ContactsRelationManager` — columns: name, email, role, is_signer (icon); form: name, email, role, is_signer (Toggle)

### 6.3 `WorkflowTemplateResource.php`

**Form fields:**
```php
Forms\Components\TextInput::make('name')->required(),
Forms\Components\Select::make('contract_type')
    ->options(['Commercial' => 'Commercial', 'Merchant' => 'Merchant'])->required(),
Forms\Components\Select::make('region_id')->relationship('region', 'name')->nullable(),
Forms\Components\Select::make('entity_id')->relationship('entity', 'name')->nullable(),
Forms\Components\Repeater::make('stages')
    ->schema([
        Forms\Components\TextInput::make('name')->required(),
        Forms\Components\TextInput::make('order')->numeric()->required(),
        Forms\Components\Select::make('approver_role')
            ->options(['system_admin'=>'System Admin','legal'=>'Legal','commercial'=>'Commercial','finance'=>'Finance','operations'=>'Operations']),
        Forms\Components\TextInput::make('sla_hours')->numeric()->default(24),
        Forms\Components\Toggle::make('required')->default(true),
    ])
    ->orderColumn('order')
    ->required(),
```

**Actions:**
- `Action::make('publish')` — System Admin only; validates stages not empty, sets status=published, bumps version. Shows validation errors in modal if stages are invalid.
- `Action::make('generate_ai')` — opens modal with `description` textarea; dispatches HTTP call to `ai-worker /generate-workflow` (via `AiWorkerClient::generateWorkflow()`); fills the stages Repeater with the result.

**Relation manager:** `EscalationRulesRelationManager` — columns: stage_name, sla_breach_hours, tier, escalate_to_role; form: all fields

### 6.4 `WikiContractResource.php`

**Form fields:** name, category, region_id (Select), description (Textarea), status (Select), file upload (S3, wiki-contracts prefix)

**Actions:**
- `Action::make('publish')` — System Admin only; sets status=published, published_at=now()
- `Action::make('download')` — signed URL redirect

### 6.5 `RegionResource.php`, `EntityResource.php`, `ProjectResource.php`

Simple CRUD. All restricted to System Admin.

**Region:** form fields: name, code (unique)
**Entity:** form fields: name, code, region_id (Select). Table: name, code, region.name
**Project:** form fields: name, code, entity_id (Select). Table: name, code, entity.name, region (via entity)

### 6.6 `SigningAuthorityResource.php`

System Admin only. Table: entity.name, project.name, user_email, role_or_name, contract_type_pattern.
Form: entity_id, project_id (nullable), user_id, user_email, role_or_name, contract_type_pattern.

### 6.7 `OverrideRequestResource.php`

System Admin and Legal only. Read-only table with action modals.

**Table columns:** counterparty.legal_name, contract_title, requested_by_email, status (badge), created_at

**Actions:**
- `Action::make('approve')` — visible only when status=pending; opens modal with optional `comment`; updates status=approved, decided_by=auth()->user()->email, decided_at=now(); calls `AuditService::log()`
- `Action::make('reject')` — same but status=rejected, comment required

### 6.8 `AuditLogResource.php`

Read-only (no create/edit/delete).

**Table columns:** at (sortable, default sort), actor_email, action, resource_type, resource_id, ip_address

**Filters:**
- SelectFilter by resource_type (contract, counterparty, workflow_template, workflow_instance, etc.)
- TextFilter by actor_email
- DateRangeFilter by at

**Header action:**
```php
Actions\Action::make('export_csv')
    ->label('Export CSV')
    ->action(function () {
        return Excel::download(new AuditLogExport(request()->all()), 'audit-log.csv', \Maatwebsite\Excel\Excel::CSV);
    })
```

**Roles:** `System Admin`, `Legal`, `Audit` only:
```php
public static function canViewAny(): bool {
    return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'audit']) ?? false;
}
```

Create `app/Exports/AuditLogExport.php` using `Maatwebsite\Excel\Concerns\FromQuery` returning filtered `AuditLog` query.

### 6.9 `NotificationResource.php`

Scoped to current user. **Override `getEloquentQuery()`:**
```php
public static function getEloquentQuery(): Builder {
    $user = auth()->user();
    return parent::getEloquentQuery()
        ->where(function($q) use ($user) {
            $q->where('recipient_user_id', $user->id)
              ->orWhere('recipient_email', $user->email);
        });
}
```

**Table columns:** subject, channel (badge), status (badge), read_at (null = unread icon), sent_at

**Actions:**
- `Action::make('mark_read')` — sets read_at=now() for single row
- `BulkAction::make('mark_all_read')` — bulk action to mark selected as read

### 6.10 `MerchantAgreementResource.php`

Table columns: contract.title, vendor_name, merchant_fee, contract.region.name, generated_at

Form: Wizard with two steps:
1. **Contract Details**: region_id, entity_id, project_id, counterparty_id, contract_type (fixed=Merchant)
2. **Agreement Details**: template_id (Select from published WikiContracts), vendor_name, merchant_fee, region_terms (KeyValue component)

On save: calls `MerchantAgreementService::generate()` which creates the Contract and MerchantAgreementInput records.

---

## Task 7: Implement All Custom Pages and Widgets

### Dashboard Page (`app/Filament/Pages/Dashboard.php`)

Override the default Filament Dashboard. Register 5 widgets.

### Widget: `ContractStatusWidget.php`

Extends `Filament\Widgets\ChartWidget`. Type = bar chart.

```php
protected function getData(): array {
    $data = Contract::selectRaw('workflow_state, COUNT(*) as count')
        ->groupBy('workflow_state')
        ->pluck('count', 'workflow_state')
        ->toArray();
    return [
        'labels' => array_keys($data),
        'datasets' => [['data' => array_values($data), 'backgroundColor' => '#6366f1']],
    ];
}
protected static ?string $heading = 'Contracts by Status';
```

### Widget: `ExpiryHorizonWidget.php`

Extends `Filament\Widgets\StatsOverviewWidget`. Four stats: 0-30 days, 31-60, 61-90, 90+ (counts of upcoming contract expiry dates). Query port from `apps/api/app/reports/service.py::expiry_horizon()`.

### Widget: `AiCostWidget.php`

Extends `Filament\Widgets\StatsOverviewWidget`. Three stats: total cost (30 days), total analyses (30 days), avg cost per analysis. Query port from `apps/api/app/reports/service.py::ai_cost_summary()`.

### Widget: `PendingWorkflowsWidget.php`

Shows count of active `workflow_instances` where current user's role is the approver of the current stage.

```php
protected function getStats(): array {
    $userRole = auth()->user()->roles->first()?->name;
    $count = WorkflowInstance::where('state', 'active')
        ->with('template')
        ->get()
        ->filter(function ($instance) use ($userRole) {
            $stages = $instance->template->stages ?? [];
            foreach ($stages as $stage) {
                if (($stage['name'] ?? '') === $instance->current_stage) {
                    $approverRole = $stage['approver_role'] ?? null;
                    return $approverRole !== null && $approverRole === $userRole;
                }
            }
            return false;
        })
        ->count();

    return [Stat::make('Pending Your Approval', $count)->color('warning')];
}
```

### Widget: `ActiveEscalationsWidget.php`

Count of unresolved `escalation_events` (resolved_at IS NULL).

### `ReportsPage.php`

Extends `Filament\Pages\Page`. View: `filament/pages/reports.blade.php`.

Livewire properties: `$regionId`, `$entityId`, `$periodDays = 30`.

Four computed methods mirroring `apps/api/app/reports/service.py`:
- `contractStatusSummary()` → Contract counts by workflow_state and contract_type
- `expiryHorizon()` → 4 date buckets (0-30, 31-60, 61-90, 90+)
- `signingStatusSummary()` → BoldsignEnvelope counts by status
- `aiCostSummary()` → 30-day AI analysis costs from ai_analysis_results

The Blade view renders these as stat cards with filter controls (Select for region, entity, period).

### `EscalationsPage.php`

Extends `Filament\Pages\Page`. Roles: System Admin, Legal.

Livewire-powered table of unresolved `EscalationEvent` records with columns: contract.title, stage_name, tier, escalated_at.

Row action: `Action::make('resolve')` — calls `EscalationService::resolveEscalation($event->id, auth()->user())`.

### `KeyDatesPage.php`

Table of upcoming `ContractKeyDate` records (next 90 days). Columns: contract.title, date_type, date_value (with days_until computed), is_verified. Filters: contract_id, date_type, date range.

### `RemindersPage.php`

Table of all `Reminder` records across contracts. Columns: contract.title, reminder_type, lead_days, channel, next_due_at, is_active (toggle). Toggle is_active inline.

### `NotificationsPage.php`

Full inbox view. Columns: subject, channel, status, sent_at, read_at. Header bulk action: Mark All Read.

---

## Task 8: Configure Filament Navigation and RBAC

In `AdminPanelProvider.php`, add navigation groups:

```php
->navigationGroups([
    NavigationGroup::make('Agreements')->icon('heroicon-o-document-text'),
    NavigationGroup::make('Organization')->icon('heroicon-o-building-office'),
    NavigationGroup::make('Workflow & Compliance')->icon('heroicon-o-check-circle'),
    NavigationGroup::make('Administration')->icon('heroicon-o-cog-6-tooth'),
])
```

Assign resources to navigation groups via `protected static ?string $navigationGroup = 'Agreements';` in each Resource:
- Agreements: Contract, MerchantAgreement, Counterparty, WikiContract
- Organization: Region, Entity, Project, SigningAuthority
- Workflow & Compliance: WorkflowTemplate, OverrideRequest, Escalations (page), KeyDates (page), Reminders (page)
- Administration: AuditLog, Notifications, Reports, Shield

---

## Task 9: Implement All Policy Classes

Create in `app/Policies/`:

### `ContractPolicy.php`
```php
public function viewAny(User $user): bool { return true; } // all authenticated
public function create(User $user): bool { return $user->hasAnyRole(['system_admin','legal','commercial']); }
public function update(User $user, Contract $contract): bool {
    if (in_array($contract->workflow_state, ['executed','archived'])) return false;
    return $user->hasAnyRole(['system_admin','legal']);
}
public function delete(User $user, Contract $contract): bool { return $user->hasRole('system_admin'); }
```

### `WorkflowTemplatePolicy.php`
All operations: System Admin only. `create/update/delete/publish`: `$user->hasRole('system_admin')`.

### `CounterpartyPolicy.php`
- viewAny: all
- create/update: system_admin, legal, commercial
- delete: system_admin
- status changes (via update): system_admin, legal

### `AuditLogPolicy.php`
- viewAny: system_admin, legal, audit
- create/update/delete: never (return false always)

### `WikiContractPolicy.php`
- viewAny: all
- create/update: system_admin, legal
- publish: system_admin

Register all policies in `app/Providers/AuthServiceProvider.php`:
```php
protected $policies = [
    Contract::class => ContractPolicy::class,
    WorkflowTemplate::class => WorkflowTemplatePolicy::class,
    Counterparty::class => CounterpartyPolicy::class,
    AuditLog::class => AuditLogPolicy::class,
    WikiContract::class => WikiContractPolicy::class,
];
```

---

## Task 10: Configure Laravel Horizon

Publish Horizon config:
```bash
php artisan horizon:install
```

In `config/horizon.php`, add environment config:
```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'simple',
            'processes' => 3,
            'tries' => 3,
        ],
    ],
    'local' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'simple',
            'processes' => 2,
            'tries' => 3,
        ],
    ],
],
```

Restrict Horizon dashboard to System Admin in `app/Providers/HorizonServiceProvider.php`:
```php
protected function gate(): void {
    Gate::define('viewHorizon', function (User $user) {
        return $user->hasRole('system_admin');
    });
}
```

---

## Task 11: Feature Tests

Create the following test files in `tests/Feature/`:

### `ContractTest.php`
- Test create contract via Filament form (authenticated as system_admin)
- Test unauthorized commercial user cannot delete contract
- Test contract with workflow_state='completed' cannot be edited

### `WorkflowTest.php`
- Test `WorkflowService::startWorkflow()` creates instance and updates contract.workflow_state
- Test starting second active workflow on same contract throws RuntimeException
- Test `WorkflowService::recordAction()` advances stage correctly

### `CounterpartyTest.php`
- Test counterparty create, edit, status change with role guards

### `AuditTest.php`
- Test AuditLog is immutable (attempt update throws)
- Test audit log is created after contract action

---

## Verification Checklist

After completing all tasks, verify:

1. `docker compose exec app php artisan test` — all Feature tests pass
2. `docker compose exec app php artisan schedule:run` — no errors
3. Open `http://localhost:8000/admin` and log in (use a test user created via tinker)
4. Verify all 12 Resources appear in sidebar with correct navigation groups
5. Create: Region → Entity → Project → Counterparty → Contract (with PDF file upload)
6. Verify audit log entry appears after creating a contract
7. Create a WorkflowTemplate, add stages, click Publish → verify status changes
8. Trigger AI Analysis action on a Contract → verify job dispatched to Redis queue (`docker compose exec app php artisan queue:work --once`)
9. `docker compose exec app php artisan route:list | grep webhook` shows BoldSign webhook route
