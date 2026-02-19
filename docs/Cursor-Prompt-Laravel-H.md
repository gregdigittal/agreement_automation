# Cursor Prompt — Laravel Migration Phase H: Amendments, Renewals & Side Letters + SharePoint

## Context

**Run this prompt in the `agreement_automation` repo on the `laravel-migration` branch** — the same branch where Phases A through G were executed.

This phase implements two remaining Phase 1b items:

1. **Epic 15 — Amendments, Renewals & Side Letters**: Linked contract records with their own workflow instances. An amendment inherits classification from the parent contract; a renewal creates a new contract version linked to its predecessor; a side letter is an independent supplementary agreement linked to a master.
2. **Epic 9.1 — SharePoint Link Storage**: The `contracts` table already has `sharepoint_url` and `sharepoint_version` columns (added in Phase 1a schema, deferred from earlier prompts). This phase exposes them in the Filament contract form and makes them searchable.

---

## Task 1: Amendments, Renewals & Side Letters (Epic 15)

The `contract_links` table was created in the Phase A migration. The `Contract` model needs the relationship wired and the Filament `ContractResource` needs three actions.

### 1.1 Verify `contract_links` Table and Add Model

Ensure `database/migrations/` contains a migration for `contract_links` with columns:
```sql
id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
parent_contract_id CHAR(36) NOT NULL,
child_contract_id CHAR(36) NOT NULL,
link_type ENUM('amendment','renewal','side_letter','addendum') NOT NULL,
created_at TIMESTAMP,
updated_at TIMESTAMP,
FOREIGN KEY (parent_contract_id) REFERENCES contracts(id),
FOREIGN KEY (child_contract_id) REFERENCES contracts(id)
```

If the migration does not exist, create it via `php artisan make:migration create_contract_links_table`.

Create `app/Models/ContractLink.php`:

```php
<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractLink extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'id', 'parent_contract_id', 'child_contract_id', 'link_type',
    ];

    public function parentContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'parent_contract_id');
    }

    public function childContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'child_contract_id');
    }
}
```

### 1.2 Add Relationships to `Contract` Model

In `app/Models/Contract.php`, add:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Outbound links — this contract is the parent */
public function childLinks(): HasMany
{
    return $this->hasMany(ContractLink::class, 'parent_contract_id');
}

/** Inbound links — this contract is a child (amendment/renewal/side letter) */
public function parentLinks(): HasMany
{
    return $this->hasMany(ContractLink::class, 'child_contract_id');
}

/** Convenience: direct children by link type */
public function amendments()
{
    return $this->childLinks()->where('link_type', 'amendment')
        ->with('childContract');
}

public function renewals()
{
    return $this->childLinks()->where('link_type', 'renewal')
        ->with('childContract');
}

public function sideLetters()
{
    return $this->childLinks()->where('link_type', 'side_letter')
        ->with('childContract');
}

/** Parent contract (if this contract IS a child) */
public function parentContract(): \Illuminate\Database\Eloquent\Relations\HasOneThrough|\Illuminate\Database\Eloquent\Relations\HasOne|null
{
    return $this->hasOne(ContractLink::class, 'child_contract_id')
        ->with('parentContract');
}
```

### 1.3 Create `ContractLinksRelationManager`

Create `app/Filament/Resources/ContractResource/RelationManagers/ContractLinksRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Models\ContractLink;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContractLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'childLinks';
    protected static ?string $title = 'Amendments, Renewals & Side Letters';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('link_type')
                ->options([
                    'amendment'   => 'Amendment',
                    'renewal'     => 'Renewal',
                    'side_letter' => 'Side Letter',
                    'addendum'    => 'Addendum',
                ])
                ->required(),
            Forms\Components\Select::make('child_contract_id')
                ->label('Linked Contract')
                ->relationship('childContract', 'title')
                ->searchable()
                ->preload()
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('link_type')
                    ->colors([
                        'warning' => 'amendment',
                        'info'    => 'renewal',
                        'success' => 'side_letter',
                        'gray'    => 'addendum',
                    ]),
                Tables\Columns\TextColumn::make('childContract.title')
                    ->label('Contract')
                    ->searchable(),
                Tables\Columns\TextColumn::make('childContract.workflow_state')
                    ->label('State')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Link Contract'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (ContractLink $record) =>
                        \App\Filament\Resources\ContractResource::getUrl('edit', [
                            'record' => $record->child_contract_id
                        ])
                    )
                    ->icon('heroicon-o-arrow-top-right-on-square'),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
```

Register this relation manager in `ContractResource::getRelationManagers()`:

```php
public static function getRelationManagers(): array
{
    return [
        // ... existing relation managers ...
        RelationManagers\ContractLinksRelationManager::class,
    ];
}
```

### 1.4 Add "Create Amendment / Renewal / Side Letter" Actions to `ContractResource`

In `ContractResource`'s table, add three row actions. Each action opens a modal to configure and create a new linked contract that inherits classification from the parent.

```php
use App\Services\ContractLinkService;

Tables\Actions\Action::make('create_amendment')
    ->label('Create Amendment')
    ->icon('heroicon-o-document-plus')
    ->color('warning')
    ->form([
        \Filament\Forms\Components\TextInput::make('title')
            ->label('Amendment Title')
            ->required()
            ->placeholder('e.g. Amendment No. 1 — Fee Adjustment'),
        \Filament\Forms\Components\Textarea::make('notes')
            ->label('Notes / Reason')
            ->rows(3),
    ])
    ->action(function (Contract $record, array $data) {
        $child = app(ContractLinkService::class)->createLinkedContract(
            parent: $record,
            linkType: 'amendment',
            title: $data['title'],
            actor: auth()->user(),
        );
        \Filament\Notifications\Notification::make()
            ->title('Amendment created')
            ->body("Contract: {$child->id}")
            ->success()
            ->send();
    }),

Tables\Actions\Action::make('create_renewal')
    ->label('Create Renewal')
    ->icon('heroicon-o-arrow-path')
    ->color('info')
    ->form([
        \Filament\Forms\Components\TextInput::make('title')
            ->required()
            ->placeholder('e.g. Renewal 2027–2029'),
        \Filament\Forms\Components\Select::make('renewal_type')
            ->options([
                'extension'   => 'Extension (update dates on existing)',
                'new_version' => 'New Version (new contract record)',
            ])
            ->required()
            ->default('new_version'),
        \Filament\Forms\Components\DatePicker::make('new_expiry_date')
            ->label('New Expiry Date')
            ->visible(fn ($get) => $get('renewal_type') === 'extension'),
    ])
    ->action(function (Contract $record, array $data) {
        $child = app(ContractLinkService::class)->createLinkedContract(
            parent: $record,
            linkType: 'renewal',
            title: $data['title'],
            actor: auth()->user(),
            extra: ['renewal_type' => $data['renewal_type'], 'new_expiry_date' => $data['new_expiry_date'] ?? null],
        );
        \Filament\Notifications\Notification::make()
            ->title('Renewal created')->success()->send();
    }),

Tables\Actions\Action::make('add_side_letter')
    ->label('Add Side Letter')
    ->icon('heroicon-o-paper-clip')
    ->color('success')
    ->form([
        \Filament\Forms\Components\TextInput::make('title')
            ->required()
            ->placeholder('e.g. Side Letter — Data Sharing'),
        \Filament\Forms\Components\FileUpload::make('file')
            ->label('Side Letter File (PDF/DOCX)')
            ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
            ->disk('s3')
            ->directory('side_letters'),
    ])
    ->action(function (Contract $record, array $data) {
        $child = app(ContractLinkService::class)->createLinkedContract(
            parent: $record,
            linkType: 'side_letter',
            title: $data['title'],
            actor: auth()->user(),
            extra: ['storage_path' => $data['file'] ?? null],
        );
        \Filament\Notifications\Notification::make()
            ->title('Side letter linked')->success()->send();
    }),
```

### 1.5 Create `ContractLinkService`

Create `app/Services/ContractLinkService.php`:

```php
<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContractLinkService
{
    /**
     * Create a new contract linked to a parent contract.
     * The child inherits region, entity, project, counterparty, and contract_type from parent.
     * For renewals of type 'extension', updates the parent contract's expiry_date in-place (no new record).
     *
     * @param  array  $extra  Optional: ['renewal_type' => 'extension'|'new_version', 'new_expiry_date' => ?, 'storage_path' => ?]
     */
    public function createLinkedContract(
        Contract $parent,
        string $linkType,
        string $title,
        User $actor,
        array $extra = [],
    ): Contract {
        return DB::transaction(function () use ($parent, $linkType, $title, $actor, $extra) {
            // For extension renewals: update parent in-place, still create a link record
            if ($linkType === 'renewal' && ($extra['renewal_type'] ?? null) === 'extension') {
                if (! empty($extra['new_expiry_date'])) {
                    $parent->update(['expiry_date' => $extra['new_expiry_date']]);
                }
                // Create a placeholder child to represent the extension event
                $title .= ' (Extension)';
            }

            // Create the child contract inheriting classification
            $child = Contract::create([
                'id'              => Str::uuid()->toString(),
                'title'           => $title,
                'contract_type'   => $parent->contract_type,
                'counterparty_id' => $parent->counterparty_id,
                'region_id'       => $parent->region_id,
                'entity_id'       => $parent->entity_id,
                'project_id'      => $parent->project_id,
                'workflow_state'  => 'draft',
                'storage_path'    => $extra['storage_path'] ?? null,
                'created_by'      => $actor->id,
            ]);

            // Link parent → child
            ContractLink::create([
                'id'                  => Str::uuid()->toString(),
                'parent_contract_id'  => $parent->id,
                'child_contract_id'   => $child->id,
                'link_type'           => $linkType,
            ]);

            AuditService::log(
                action: "contract.{$linkType}_created",
                resourceType: 'contract',
                resourceId: $child->id,
                details: ['parent_contract_id' => $parent->id, 'link_type' => $linkType],
                actor: $actor,
            );

            return $child;
        });
    }
}
```

### 1.6 Show Parent Contract Info on Child Contract Detail

In `ContractResource`'s form (or view page), add an `InfoList` section that shows the parent contract if this contract is a child:

```php
// In ContractResource infolist or view page:
\Filament\Infolists\Components\Section::make('Linked From')
    ->visible(fn (Contract $record) =>
        $record->parentLinks()->exists()
    )
    ->schema([
        \Filament\Infolists\Components\TextEntry::make('parentLinks.0.link_type')
            ->label('Link Type')
            ->badge(),
        \Filament\Infolists\Components\TextEntry::make('parentLinks.0.parentContract.title')
            ->label('Parent Contract')
            ->url(fn (Contract $record) =>
                $record->parentLinks->first()?->parent_contract_id
                    ? \App\Filament\Resources\ContractResource::getUrl('edit', [
                        'record' => $record->parentLinks->first()->parent_contract_id
                      ])
                    : null
            ),
    ]),
```

### 1.7 Write Feature Test

Create `tests/Feature/ContractLinksTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use App\Services\ContractLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_amendment_linked_to_parent(): void
    {
        $user = User::factory()->create();
        $parent = Contract::factory()->create(['contract_type' => 'commercial']);

        $child = app(ContractLinkService::class)->createLinkedContract(
            parent: $parent,
            linkType: 'amendment',
            title: 'Amendment No. 1',
            actor: $user,
        );

        $this->assertEquals('commercial', $child->contract_type);
        $this->assertEquals($parent->counterparty_id, $child->counterparty_id);
        $this->assertEquals($parent->region_id, $child->region_id);
        $this->assertDatabaseHas('contract_links', [
            'parent_contract_id' => $parent->id,
            'child_contract_id'  => $child->id,
            'link_type'          => 'amendment',
        ]);
    }

    public function test_parent_contract_shows_amendments(): void
    {
        $user = User::factory()->create();
        $parent = Contract::factory()->create();

        app(ContractLinkService::class)->createLinkedContract($parent, 'amendment', 'Amend 1', $user);
        app(ContractLinkService::class)->createLinkedContract($parent, 'side_letter', 'Side Letter A', $user);

        $parent->refresh();
        $this->assertCount(1, $parent->amendments);
        $this->assertCount(1, $parent->sideLetters);
    }

    public function test_renewal_extension_updates_parent_expiry(): void
    {
        $user = User::factory()->create();
        $parent = Contract::factory()->create(['expiry_date' => now()->addYear()]);

        $newExpiry = now()->addYears(3)->format('Y-m-d');
        app(ContractLinkService::class)->createLinkedContract(
            parent: $parent,
            linkType: 'renewal',
            title: 'Renewal 2029',
            actor: $user,
            extra: ['renewal_type' => 'extension', 'new_expiry_date' => $newExpiry],
        );

        $parent->refresh();
        $this->assertEquals($newExpiry, $parent->expiry_date->format('Y-m-d'));
    }
}
```

---

## Task 2: SharePoint Link Storage (Epic 9.1)

The `contracts` table has `sharepoint_url TEXT NULL` and `sharepoint_version VARCHAR(50) NULL` columns (added in the Phase A migration). This task exposes them in the Filament form and adds a display section.

### 2.1 Add Fields to `ContractResource` Form

In `app/Filament/Resources/ContractResource.php`, in the `form()` method, add a `Section` for collaboration:

```php
\Filament\Forms\Components\Section::make('SharePoint Collaboration')
    ->description('Link the SharePoint document URL for collaborative review and track the version.')
    ->collapsed()
    ->schema([
        \Filament\Forms\Components\TextInput::make('sharepoint_url')
            ->label('SharePoint URL')
            ->url()
            ->maxLength(2048)
            ->placeholder('https://digittalgroup.sharepoint.com/sites/legal/...'),

        \Filament\Forms\Components\TextInput::make('sharepoint_version')
            ->label('SharePoint Version')
            ->maxLength(50)
            ->placeholder('e.g. 2.3'),
    ])
    ->columns(2),
```

### 2.2 Add `sharepoint_url` and `sharepoint_version` to `Contract::$fillable`

In `app/Models/Contract.php`, ensure both fields are in `$fillable`:

```php
protected $fillable = [
    // ... existing fields ...
    'sharepoint_url',
    'sharepoint_version',
];
```

### 2.3 Show SharePoint Link on Contract View Page

In the `ContractResource` infolist (view page), add a section:

```php
\Filament\Infolists\Components\Section::make('SharePoint')
    ->visible(fn (Contract $record) => ! empty($record->sharepoint_url))
    ->schema([
        \Filament\Infolists\Components\TextEntry::make('sharepoint_url')
            ->label('Document URL')
            ->url()
            ->openUrlInNewTab(),
        \Filament\Infolists\Components\TextEntry::make('sharepoint_version')
            ->label('Version'),
    ])
    ->columns(2),
```

### 2.4 Add SharePoint Column to Contract Table (Optional)

In `ContractResource`'s `table()`, add a toggleable SharePoint column:

```php
Tables\Columns\IconColumn::make('sharepoint_url')
    ->label('SharePoint')
    ->boolean()
    ->trueIcon('heroicon-o-document-text')
    ->falseIcon('heroicon-o-minus')
    ->tooltip(fn (Contract $record) => $record->sharepoint_url)
    ->toggleable(isToggledHiddenByDefault: true),
```

### 2.5 Write Feature Test

Create `tests/Feature/SharePointIntegrationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;
use App\Filament\Resources\ContractResource\Pages\EditContract;

class SharePointIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_stores_sharepoint_url_and_version(): void
    {
        $contract = Contract::factory()->create();

        $contract->update([
            'sharepoint_url'     => 'https://digittalgroup.sharepoint.com/sites/legal/document.docx',
            'sharepoint_version' => '3.1',
        ]);

        $contract->refresh();
        $this->assertStringContainsString('sharepoint.com', $contract->sharepoint_url);
        $this->assertEquals('3.1', $contract->sharepoint_version);
    }
}
```

---

## Verification Checklist

After completing all tasks, verify the following:

1. **Amendments & Renewals:**
   - Navigate to Contracts → select any contract → click "Create Amendment".
   - A new contract record is created with the same region/entity/project/counterparty as the parent.
   - The parent contract's "Amendments, Renewals & Side Letters" relation manager tab shows the new child.
   - Clicking the child contract shows a "Linked From" banner with the parent contract title.
   - "Add Side Letter" with a PDF upload creates a linked child with the uploaded file as `storage_path`.
   - "Create Renewal → Extension" with a new expiry date updates the parent's `expiry_date` column.
   - `php artisan test --filter=ContractLinksTest` — all tests pass.

2. **SharePoint:**
   - Edit a contract → expand "SharePoint Collaboration" section → enter a SharePoint URL and version → save.
   - View the contract — the "SharePoint" infolist section shows a clickable link.
   - The contracts table shows a SharePoint icon column (when enabled via column toggle).
   - `php artisan test --filter=SharePointIntegrationTest` — passes.
