# Cursor Prompt — Laravel Migration Phase J: Vendor Portal Full Implementation

## Context

**Run this prompt in the `agreement_automation` repo on the `laravel-migration` branch** — the same branch where Phases A through I were executed.

Phase G scaffolded the Vendor Self-Service Portal with:
- A separate Filament panel at `/vendor` with emerald colour scheme
- `VendorUser` model + `vendor_users` table + magic-link auth
- `VendorContractResource` (read-only, scoped to counterparty)
- `vendor_documents` table migration
- Signed S3 download route

This phase fully implements the vendor portal:

1. **Vendor Document Upload** — vendors upload supporting documents tied to their contracts
2. **Vendor Notifications** — in-app and email notifications delivered into the vendor portal inbox
3. **Portal UI Polish** — dashboard, branding, profile page, empty states
4. **Admin Vendor Management** — internal admin creates vendor user accounts and views their portal activity

---

## Task 1: Vendor Document Upload

### 1.1 Create `VendorDocument` Model

Ensure `app/Models/VendorDocument.php` exists (was scaffolded in Phase G). If not, create it:

```php
<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDocument extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'vendor_documents';

    protected $fillable = [
        'id', 'counterparty_id', 'contract_id', 'filename', 'storage_path',
        'document_type', 'uploaded_by_vendor_user_id',
    ];

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(VendorUser::class, 'uploaded_by_vendor_user_id');
    }
}
```

### 1.2 Create `VendorDocumentResource` in the Vendor Panel

Create `app/Filament/Vendor/Resources/VendorDocumentResource.php`:

```php
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VendorDocumentResource extends Resource
{
    protected static ?string $model = VendorDocument::class;
    protected static ?string $navigationGroup = 'Documents';
    protected static ?string $navigationLabel = 'My Documents';
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';

    public static function getEloquentQuery(): Builder
    {
        $counterpartyId = auth('vendor')->user()?->counterparty_id;
        return parent::getEloquentQuery()
            ->where('counterparty_id', $counterpartyId)
            ->with(['contract', 'uploadedBy']);
    }

    public static function form(Form $form): Form
    {
        $counterpartyId = auth('vendor')->user()?->counterparty_id;

        return $form
            ->schema([
                Forms\Components\Select::make('contract_id')
                    ->label('Related Agreement (Optional)')
                    ->options(
                        Contract::where('counterparty_id', $counterpartyId)
                            ->pluck('title', 'id')
                    )
                    ->searchable()
                    ->nullable(),

                Forms\Components\Select::make('document_type')
                    ->label('Document Type')
                    ->options([
                        'supporting'        => 'Supporting Document',
                        'certificate'       => 'Certificate',
                        'insurance'         => 'Insurance Certificate',
                        'compliance'        => 'Compliance Document',
                        'registration'      => 'Company Registration',
                        'id'                => 'Director ID / Passport',
                        'financial'         => 'Financial Statement',
                        'other'             => 'Other',
                    ])
                    ->required()
                    ->default('supporting'),

                Forms\Components\FileUpload::make('storage_path')
                    ->label('Document File')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'image/jpeg',
                        'image/png',
                    ])
                    ->disk('s3')
                    ->directory(fn () => 'vendor_documents/' . $counterpartyId)
                    ->maxSize(20480) // 20MB
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, callable $set) =>
                        $set('filename', $state ? basename($state) : null)
                    ),

                Forms\Components\Hidden::make('filename'),
                Forms\Components\Hidden::make('counterparty_id')
                    ->default($counterpartyId),
                Forms\Components\Hidden::make('uploaded_by_vendor_user_id')
                    ->default(fn () => auth('vendor')->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('filename')->searchable(),
                Tables\Columns\BadgeColumn::make('document_type'),
                Tables\Columns\TextColumn::make('contract.title')
                    ->label('Agreement')
                    ->limit(35)
                    ->default('—'),
                Tables\Columns\TextColumn::make('created_at')->since()->label('Uploaded'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (VendorDocument $record) =>
                        Storage::disk('s3')->temporaryUrl($record->storage_path, now()->addMinutes(10))
                    )
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => \App\Filament\Vendor\Resources\VendorDocumentResource\Pages\ListVendorDocuments::route('/'),
            'create' => \App\Filament\Vendor\Resources\VendorDocumentResource\Pages\CreateVendorDocument::route('/create'),
        ];
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false; // Documents are immutable once uploaded
    }
}
```

Create the `Pages/` classes:
- `ListVendorDocuments.php` extending `Filament\Resources\Pages\ListRecords`
- `CreateVendorDocument.php` extending `Filament\Resources\Pages\CreateRecord`, with `mutateFormDataBeforeCreate()` setting `filename` from `storage_path` if not already set

### 1.3 Add `VendorDocumentsRelationManager` to Internal `CounterpartyResource`

In `app/Filament/Resources/CounterpartyResource.php`, add a read-only relation manager so internal users can see documents uploaded by vendors:

Create `app/Filament/Resources/CounterpartyResource/RelationManagers/VendorDocumentsRelationManager.php`:

```php
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
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('filename'),
                Tables\Columns\BadgeColumn::make('document_type'),
                Tables\Columns\TextColumn::make('contract.title')->label('Agreement')->limit(30)->default('—'),
                Tables\Columns\TextColumn::make('uploadedBy.name')->label('Uploaded By'),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (VendorDocument $record) =>
                        Storage::disk('s3')->temporaryUrl($record->storage_path, now()->addMinutes(10))
                    )
                    ->openUrlInNewTab(),
            ]);
    }
}
```

Add relationship to `Counterparty` model:
```php
public function vendorDocuments(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\VendorDocument::class);
}
```

---

## Task 2: Vendor Notifications

### 2.1 Create `vendor_notifications` Table Migration

```bash
php artisan make:migration create_vendor_notifications_table
```

Schema:
```php
Schema::create('vendor_notifications', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('vendor_user_id', 36)->index();
    $table->string('subject');
    $table->text('body');
    $table->string('related_resource_type', 50)->nullable();
    $table->char('related_resource_id', 36)->nullable();
    $table->timestamp('read_at')->nullable();
    $table->timestamps();

    $table->foreign('vendor_user_id')->references('id')->on('vendor_users')->onDelete('cascade');
});
```

### 2.2 Create `VendorNotification` Model

Create `app/Models/VendorNotification.php`:

```php
<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class VendorNotification extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'vendor_notifications';

    protected $fillable = [
        'id', 'vendor_user_id', 'subject', 'body',
        'related_resource_type', 'related_resource_id', 'read_at',
    ];

    protected $casts = ['read_at' => 'datetime'];

    public function isRead(): bool { return $this->read_at !== null; }
    public function markRead(): void { $this->update(['read_at' => now()]); }
}
```

### 2.3 Create `VendorNotificationService`

Create `app/Services/VendorNotificationService.php`:

```php
<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\VendorNotification;
use App\Models\VendorUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class VendorNotificationService
{
    /**
     * Send a notification to all vendor users associated with a counterparty.
     * Creates an in-portal notification record AND sends an email.
     */
    public function notifyVendors(
        Counterparty $counterparty,
        string $subject,
        string $body,
        ?string $resourceType = null,
        ?string $resourceId = null,
    ): void {
        $vendors = VendorUser::where('counterparty_id', $counterparty->id)->get();

        foreach ($vendors as $vendor) {
            // Create in-portal notification
            VendorNotification::create([
                'id'                    => Str::uuid()->toString(),
                'vendor_user_id'        => $vendor->id,
                'subject'               => $subject,
                'body'                  => $body,
                'related_resource_type' => $resourceType,
                'related_resource_id'   => $resourceId,
            ]);

            // Send email
            Mail::to($vendor->email)->queue(new \App\Mail\VendorNotificationMail($vendor, $subject, $body));
        }
    }

    /**
     * Notify vendors when a contract status changes.
     * Called from ContractResource action hooks or WorkflowService.
     */
    public function notifyContractStatusChange(Contract $contract, string $newState): void
    {
        if (! $contract->counterparty_id) return;

        $this->notifyVendors(
            counterparty: $contract->counterparty,
            subject: "Agreement Status Update: {$contract->title}",
            body: "Your agreement \"{$contract->title}\" has moved to status: **{$newState}**.",
            resourceType: 'contract',
            resourceId: $contract->id,
        );
    }
}
```

### 2.4 Create `VendorNotificationMail`

Create `app/Mail/VendorNotificationMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\VendorUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendorNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly VendorUser $vendor,
        public readonly string $subject,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.vendor-notification',
            with: [
                'vendorName' => $this->vendor->name,
                'body'       => $this->body,
                'portalUrl'  => config('app.url') . '/vendor',
            ],
        );
    }
}
```

Create `resources/views/mail/vendor-notification.blade.php`:

```blade
# {{ $subject }}

Dear {{ $vendorName }},

{{ $body }}

---
[Visit your Vendor Portal]({{ $portalUrl }}) to view your agreements and documents.

*CCRS — Contract & Merchant Agreement Repository System*
*Digittal Group*
```

### 2.5 Create Vendor Notifications Page

Create `app/Filament/Vendor/Pages/VendorNotificationsPage.php`:

```php
<?php

namespace App\Filament\Vendor\Pages;

use App\Models\VendorNotification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorNotificationsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notifications';
    protected static string $view = 'filament.vendor.pages.notifications';
    protected static ?int $navigationSort = 10;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VendorNotification::where('vendor_user_id', auth('vendor')->id())
                    ->latest()
            )
            ->columns([
                Tables\Columns\IconColumn::make('read_at')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-ellipsis-horizontal-circle')
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\TextColumn::make('subject')
                    ->weight(fn ($record) => $record->isRead() ? 'normal' : 'bold'),
                Tables\Columns\TextColumn::make('body')->limit(60)->wrap(),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_read')
                    ->label('Mark Read')
                    ->icon('heroicon-o-check')
                    ->visible(fn (VendorNotification $record) => ! $record->isRead())
                    ->action(fn (VendorNotification $record) => $record->markRead()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('mark_all_read')
                    ->label('Mark All Read')
                    ->action(function () {
                        VendorNotification::where('vendor_user_id', auth('vendor')->id())
                            ->whereNull('read_at')
                            ->update(['read_at' => now()]);
                    }),
            ]);
    }

    public function getUnreadCount(): int
    {
        return VendorNotification::where('vendor_user_id', auth('vendor')->id())
            ->whereNull('read_at')
            ->count();
    }
}
```

Create `resources/views/filament/vendor/pages/notifications.blade.php`:

```blade
<x-filament-panels::page>
    @if ($this->getUnreadCount() > 0)
        <x-filament::section>
            <p class="text-sm text-amber-700 dark:text-amber-300">
                You have {{ $this->getUnreadCount() }} unread notification(s).
            </p>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
```

Register `VendorNotificationsPage::class` in `VendorPanelProvider`.

---

## Task 3: Vendor Dashboard + Profile

### 3.1 Create Vendor Dashboard Page

Create `app/Filament/Vendor/Pages/VendorDashboard.php`:

```php
<?php

namespace App\Filament\Vendor\Pages;

use App\Models\Contract;
use App\Models\VendorDocument;
use App\Models\VendorNotification;
use Filament\Pages\Dashboard;

class VendorDashboard extends Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = 'Dashboard';

    public function getWidgets(): array
    {
        return []; // Widgets defined below
    }

    public function getDashboardStats(): array
    {
        $counterpartyId = auth('vendor')->user()?->counterparty_id;

        return [
            'active_agreements' => Contract::where('counterparty_id', $counterpartyId)
                ->where('workflow_state', 'active')->count(),
            'pending_signing'   => Contract::where('counterparty_id', $counterpartyId)
                ->whereHas('boldsignEnvelopes', fn ($q) => $q->where('status', 'pending'))
                ->count(),
            'documents_uploaded'=> VendorDocument::where('counterparty_id', $counterpartyId)->count(),
            'unread_notifications' => VendorNotification::where('vendor_user_id', auth('vendor')->id())
                ->whereNull('read_at')->count(),
        ];
    }
}
```

Create `resources/views/filament/vendor/pages/dashboard.blade.php`:

```blade
<x-filament-panels::page>
    @php $stats = $this->getDashboardStats() @endphp

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        @foreach ([
            ['Active Agreements', $stats['active_agreements'], 'heroicon-o-document-check', 'text-emerald-600'],
            ['Pending Signing',   $stats['pending_signing'],   'heroicon-o-pencil',          'text-amber-600'],
            ['Documents Uploaded',$stats['documents_uploaded'],'heroicon-o-folder',           'text-blue-600'],
            ['Unread Notifications',$stats['unread_notifications'],'heroicon-o-bell',         'text-red-500'],
        ] as [$label, $value, $icon, $color])
            <div class="rounded-xl border bg-white p-5 shadow-sm dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <x-filament::icon :icon="$icon" class="h-6 w-6 {{ $color }}" />
                    <div>
                        <p class="text-xs text-gray-500">{{ $label }}</p>
                        <p class="text-2xl font-bold {{ $color }}">{{ $value }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-filament::section heading="Quick Actions">
        <div class="flex flex-wrap gap-3">
            <x-filament::button
                href="{{ \App\Filament\Vendor\Resources\VendorDocumentResource::getUrl('create') }}"
                tag="a"
                color="primary"
                icon="heroicon-o-arrow-up-tray"
            >
                Upload Document
            </x-filament::button>
            <x-filament::button
                href="{{ \App\Filament\Vendor\Resources\VendorContractResource::getUrl('index') }}"
                tag="a"
                color="gray"
                icon="heroicon-o-document-text"
            >
                View Agreements
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
```

Set `VendorDashboard` as the default page in `VendorPanelProvider`:
```php
->pages([VendorDashboard::class])
```

### 3.2 Vendor Profile Page

Create `app/Filament/Vendor/Pages/VendorProfilePage.php` extending `Filament\Pages\Page`:

```php
<?php

namespace App\Filament\Vendor\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class VendorProfilePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'My Profile';
    protected static string $view = 'filament.vendor.pages.profile';
    protected static ?int $navigationSort = 90;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'name'  => auth('vendor')->user()?->name,
            'email' => auth('vendor')->user()?->email,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->disabled()
                    ->helperText('Email address cannot be changed. Contact your account manager.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        auth('vendor')->user()->update(['name' => $data['name']]);
        Notification::make()->title('Profile updated')->success()->send();
    }
}
```

Create `resources/views/filament/vendor/pages/profile.blade.php`:

```blade
<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}
            <x-filament::button type="submit" color="primary">Save Changes</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
```

---

## Task 4: Admin Vendor User Management

### 4.1 Create `VendorUserResource` in Admin Panel

Create `app/Filament/Resources/VendorUserResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Models\VendorUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorUserResource extends Resource
{
    protected static ?string $model = VendorUser::class;
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Vendor Users';
    protected static ?int $navigationSort = 60;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('system_admin');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            Forms\Components\Select::make('counterparty_id')
                ->label('Counterparty')
                ->relationship('counterparty', 'legal_name')
                ->searchable()
                ->preload()
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('counterparty.legal_name')
                    ->label('Counterparty')
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->since()
                    ->default('Never'),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('send_invite')
                    ->label('Send Login Link')
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (VendorUser $record) {
                        $token = \Illuminate\Support\Str::random(64);
                        $record->update([
                            'login_token'            => hash('sha256', $token),
                            'login_token_expires_at' => now()->addHours(48),
                        ]);
                        $link = route('vendor.magic-link.verify', ['token' => $token]);
                        \Illuminate\Support\Facades\Mail::to($record->email)
                            ->send(new \App\Mail\VendorMagicLink($record, $link));
                        \Filament\Notifications\Notification::make()
                            ->title('Login link sent to ' . $record->email)
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVendorUsers::route('/'),
            'create' => Pages\CreateVendorUser::route('/create'),
            'edit'   => Pages\EditVendorUser::route('/{record}/edit'),
        ];
    }
}
```

Create stub `Pages/` classes (ListVendorUsers, CreateVendorUser, EditVendorUser).

Register `VendorUserResource::class` in `AdminPanelProvider`.

### 4.2 Wire `NotifyVendors` into Contract Workflow State Changes

In `app/Services/WorkflowService.php`, after advancing a workflow stage to `'active'` or `'executed'`, notify vendors:

```php
use App\Services\VendorNotificationService;

// After contract workflow_state is updated to 'active' or 'executed':
if (in_array($newState, ['active', 'executed'])) {
    app(VendorNotificationService::class)
        ->notifyContractStatusChange($contract, $newState);
}
```

---

## Verification Checklist

1. **Document Upload:**
   - Log in as a vendor at `/vendor`.
   - Navigate to Documents → Create → upload a PDF.
   - Document appears in the list with a working Download action (signed S3 URL).
   - Internal admin navigating to the Counterparty's "Documents by Vendor" relation manager tab sees the same file.

2. **Notifications:**
   - Trigger a contract state change to `'active'` from the admin panel.
   - The vendor sees a new notification in their portal inbox.
   - The vendor receives an email notification.
   - Clicking "Mark Read" clears the unread indicator.
   - "Mark All Read" clears all unread badges.

3. **Dashboard:**
   - Vendor portal dashboard shows correct counts for active agreements, pending signing, documents, unread notifications.
   - "Upload Document" and "View Agreements" quick action buttons navigate correctly.

4. **Profile:**
   - Vendor updates their name → success notification → name change persists after refresh.
   - Email field is disabled and cannot be changed.

5. **Admin Vendor Management:**
   - System Admin navigates to Administration → Vendor Users → creates a new vendor user linked to a counterparty.
   - Clicking "Send Login Link" sends an email and shows a success notification.
   - Token expires after 48 hours.

6. **Tests:**
   ```bash
   php artisan test --filter=VendorPortalTest
   ```
   Create `tests/Feature/VendorPortalTest.php` covering:
   - Magic link login flow
   - Document upload scoped to counterparty
   - Vendor cannot see another counterparty's contracts
   - Notifications created on contract status change
