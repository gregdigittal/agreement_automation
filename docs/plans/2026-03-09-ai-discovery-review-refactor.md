# AI Discovery Review Refactoring — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Refactor the AI Discovery Review page to group discoveries by contract with collapsible sections, add deduplication logic to prevent duplicate drafts, and detect duplicate contract uploads via SHA-256 hash + filename matching.

**Architecture:** Four independent changes that build on the existing AiDiscoveryService/AiDiscoveryDraft infrastructure: (1) a migration adding `file_hash` to contracts, (2) dedup logic in the discovery service, (3) duplicate detection in the contract upload flow, and (4) a rewritten review page with grouped/filterable layout.

**Tech Stack:** Laravel 12, Filament 3, PHP 8.4, MySQL 8.0 (SQLite in tests), Pest test framework, UUID primary keys.

**Design Document:** `docs/plans/2026-03-09-ai-discovery-review-refactor-design.md`

---

## Task 1: Migration — Add `file_hash` column to contracts

**Files:**
- Create: `database/migrations/2026_03_09_000001_add_file_hash_to_contracts_table.php`
- Modify: `app/Models/Contract.php` (add `file_hash` to `$fillable`)

**Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('file_hash', 64)->nullable()->after('file_version');
            $table->index('file_hash');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['file_hash']);
            $table->dropColumn('file_hash');
        });
    }
};
```

**Step 2: Add `file_hash` to Contract model `$fillable`**

In `app/Models/Contract.php`, add `'file_hash'` to the `$fillable` array, after `'file_version'`.

**Step 3: Run migration locally to verify**

```bash
php artisan migrate
```

Expected: Migration runs without errors. Column appears in `contracts` table.

**Step 4: Commit**

```bash
git add database/migrations/2026_03_09_000001_add_file_hash_to_contracts_table.php app/Models/Contract.php
git commit -m "feat: add file_hash column to contracts table for duplicate detection"
```

---

## Task 2: Create AiDiscoveryDraftFactory

**Files:**
- Create: `database/factories/AiDiscoveryDraftFactory.php`

No factory exists for `AiDiscoveryDraft`. We need one for testing dedup and the review page.

**Step 1: Create the factory**

```php
<?php

namespace Database\Factories;

use App\Models\AiDiscoveryDraft;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AiDiscoveryDraftFactory extends Factory
{
    protected $model = AiDiscoveryDraft::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'contract_id' => Contract::factory(),
            'analysis_id' => Str::uuid()->toString(),
            'draft_type' => fake()->randomElement(['counterparty', 'entity', 'jurisdiction', 'governing_law']),
            'extracted_data' => ['name' => fake()->company()],
            'matched_record_id' => null,
            'matched_record_type' => null,
            'confidence' => fake()->randomFloat(2, 0.3, 0.99),
            'status' => 'pending',
        ];
    }

    public function counterparty(array $data = []): static
    {
        return $this->state(fn () => [
            'draft_type' => 'counterparty',
            'extracted_data' => array_merge([
                'legal_name' => fake()->company(),
                'registration_number' => fake()->numerify('REG-####'),
            ], $data),
        ]);
    }

    public function entity(array $data = []): static
    {
        return $this->state(fn () => [
            'draft_type' => 'entity',
            'extracted_data' => array_merge([
                'name' => fake()->company() . ' LLC',
                'registration_number' => fake()->numerify('ENT-####'),
            ], $data),
        ]);
    }

    public function jurisdiction(array $data = []): static
    {
        return $this->state(fn () => [
            'draft_type' => 'jurisdiction',
            'extracted_data' => array_merge([
                'name' => fake()->city() . ' International Financial Centre',
            ], $data),
        ]);
    }

    public function governingLaw(array $data = []): static
    {
        return $this->state(fn () => [
            'draft_type' => 'governing_law',
            'extracted_data' => array_merge([
                'name' => 'Laws of ' . fake()->country(),
            ], $data),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'reviewed_at' => now(),
        ]);
    }
}
```

**Step 2: Add `HasFactory` trait to AiDiscoveryDraft model if missing**

In `app/Models/AiDiscoveryDraft.php`, ensure:
```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AiDiscoveryDraft extends Model
{
    use HasFactory, HasUuidPrimaryKey;
    // ...
}
```

**Step 3: Commit**

```bash
git add database/factories/AiDiscoveryDraftFactory.php app/Models/AiDiscoveryDraft.php
git commit -m "feat: add AiDiscoveryDraftFactory for test support"
```

---

## Task 3: Discovery Deduplication — Tests

**Files:**
- Modify: `tests/Feature/AiDiscoveryTest.php`

**Step 1: Add dedup tests to AiDiscoveryTest**

Add these test methods to the existing `AiDiscoveryTest` class:

```php
public function test_duplicate_counterparty_discovery_is_not_created(): void
{
    $contract = $this->makeContract();
    $service = new AiDiscoveryService();

    // First discovery run
    $service->processDiscoveryResults($contract, 'analysis-1', [
        [
            'type' => 'counterparty',
            'confidence' => 0.9,
            'data' => ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123'],
        ],
    ]);

    $this->assertDatabaseCount('ai_discovery_drafts', 1);

    // Second discovery run with identical counterparty
    $service->processDiscoveryResults($contract, 'analysis-2', [
        [
            'type' => 'counterparty',
            'confidence' => 0.95,
            'data' => ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123'],
        ],
    ]);

    // Should still be 1 — duplicate was skipped
    $this->assertDatabaseCount('ai_discovery_drafts', 1);
}

public function test_different_counterparty_discovery_is_created(): void
{
    $contract = $this->makeContract();
    $service = new AiDiscoveryService();

    $service->processDiscoveryResults($contract, 'analysis-1', [
        [
            'type' => 'counterparty',
            'confidence' => 0.9,
            'data' => ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123'],
        ],
    ]);

    // Different counterparty
    $service->processDiscoveryResults($contract, 'analysis-2', [
        [
            'type' => 'counterparty',
            'confidence' => 0.85,
            'data' => ['legal_name' => 'Beta LLC', 'registration_number' => 'REG-999'],
        ],
    ]);

    $this->assertDatabaseCount('ai_discovery_drafts', 2);
}

public function test_duplicate_jurisdiction_discovery_is_not_created(): void
{
    $contract = $this->makeContract();
    $service = new AiDiscoveryService();

    $service->processDiscoveryResults($contract, 'analysis-1', [
        ['type' => 'jurisdiction', 'confidence' => 0.8, 'data' => ['name' => 'DIFC']],
    ]);

    $service->processDiscoveryResults($contract, 'analysis-2', [
        ['type' => 'jurisdiction', 'confidence' => 0.9, 'data' => ['name' => 'DIFC']],
    ]);

    $this->assertDatabaseCount('ai_discovery_drafts', 1);
}

public function test_approved_draft_does_not_block_new_discovery(): void
{
    $contract = $this->makeContract();
    $service = new AiDiscoveryService();

    // First run
    $service->processDiscoveryResults($contract, 'analysis-1', [
        [
            'type' => 'counterparty',
            'confidence' => 0.9,
            'data' => ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123'],
        ],
    ]);

    // Approve the draft
    $draft = AiDiscoveryDraft::first();
    $admin = \App\Models\User::factory()->create();
    $service->approveDraft($draft, $admin);

    // Second run with same data — should create new draft since first is approved
    $service->processDiscoveryResults($contract, 'analysis-2', [
        [
            'type' => 'counterparty',
            'confidence' => 0.95,
            'data' => ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123'],
        ],
    ]);

    $this->assertDatabaseCount('ai_discovery_drafts', 2);
}

public function test_duplicate_across_different_contracts_is_allowed(): void
{
    $contract1 = $this->makeContract();
    $contract2 = $this->makeContract();
    $service = new AiDiscoveryService();

    $discovery = [
        ['type' => 'counterparty', 'confidence' => 0.9, 'data' => ['legal_name' => 'Acme Corp']],
    ];

    $service->processDiscoveryResults($contract1, 'analysis-1', $discovery);
    $service->processDiscoveryResults($contract2, 'analysis-2', $discovery);

    // Different contracts — both should be created
    $this->assertDatabaseCount('ai_discovery_drafts', 2);
}
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/AiDiscoveryTest.php --filter="duplicate"
```

Expected: All 5 new tests FAIL — current `processDiscoveryResults` has no dedup logic.

**Step 3: Commit failing tests**

```bash
git add tests/Feature/AiDiscoveryTest.php
git commit -m "test: add failing dedup tests for AI discovery drafts"
```

---

## Task 4: Discovery Deduplication — Implementation

**Files:**
- Modify: `app/Services/AiDiscoveryService.php`

**Step 1: Add `isDuplicatePending()` method and modify `processDiscoveryResults()`**

Replace the `processDiscoveryResults` method and add helper:

```php
public function processDiscoveryResults(Contract $contract, string $analysisId, array $discoveries): void
{
    $created = 0;
    $skipped = 0;

    foreach ($discoveries as $discovery) {
        $type = $discovery['type'] ?? null;
        $data = $discovery['data'] ?? [];
        $confidence = $discovery['confidence'] ?? 0.0;

        if (! $type || empty($data)) {
            continue;
        }

        // Dedup: skip if an identical pending draft already exists for this contract
        if ($this->isDuplicatePending($contract->id, $type, $data)) {
            $skipped++;
            continue;
        }

        [$matchedId, $matchedType] = $this->findMatch($type, $data);

        AiDiscoveryDraft::create([
            'contract_id' => $contract->id,
            'analysis_id' => $analysisId,
            'draft_type' => $type,
            'extracted_data' => $data,
            'matched_record_id' => $matchedId,
            'matched_record_type' => $matchedType,
            'confidence' => $confidence,
            'status' => 'pending',
        ]);

        $created++;
    }

    Log::info("AI discovery: created {$created}, skipped {$skipped} duplicates for contract {$contract->id}");
}

/**
 * Check if a pending draft with the same identity fields already exists.
 */
private function isDuplicatePending(string $contractId, string $type, array $data): bool
{
    $identityField = match ($type) {
        'counterparty', 'entity' => 'name',
        'jurisdiction', 'governing_law' => 'name',
        default => null,
    };

    // Determine the primary key to match on
    $nameValue = match ($type) {
        'counterparty' => $data['legal_name'] ?? null,
        'entity' => $data['name'] ?? null,
        'jurisdiction' => $data['name'] ?? null,
        'governing_law' => $data['name'] ?? null,
        default => null,
    };

    if (! $nameValue) {
        return false;
    }

    $query = AiDiscoveryDraft::where('contract_id', $contractId)
        ->where('draft_type', $type)
        ->where('status', 'pending');

    // Match on the identity field within JSON extracted_data
    $jsonKey = match ($type) {
        'counterparty' => 'legal_name',
        default => 'name',
    };

    return $query->where("extracted_data->{$jsonKey}", $nameValue)->exists();
}
```

**Step 2: Run the dedup tests**

```bash
php artisan test tests/Feature/AiDiscoveryTest.php --filter="duplicate"
```

Expected: All 5 dedup tests PASS.

**Step 3: Run full AI discovery test suite to ensure no regressions**

```bash
php artisan test tests/Feature/AiDiscoveryTest.php
```

Expected: All tests PASS (existing + new).

**Step 4: Commit**

```bash
git add app/Services/AiDiscoveryService.php
git commit -m "feat: add dedup logic to prevent duplicate AI discovery drafts"
```

---

## Task 5: AI Analysis Pre-Flight Warning

**Files:**
- Modify: `app/Filament/Resources/ContractResource.php` (lines ~365-405, the `trigger_ai_analysis` action)

**Step 1: Add pending-draft check before dispatching discovery jobs**

Inside the `trigger_ai_analysis` action's `->action()` closure, add a check before the dispatch loop. After the `$types = $data['analysis_types'] ?? [];` line, add:

```php
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
```

**Step 2: Verify manually (or via Livewire test)**

Dispatch AI analysis on a contract that already has pending drafts — a warning notification should appear.

**Step 3: Commit**

```bash
git add app/Filament/Resources/ContractResource.php
git commit -m "feat: warn user about existing pending discoveries before AI analysis"
```

---

## Task 6: Duplicate Contract Detection — Tests

**Files:**
- Create: `tests/Feature/Contracts/ContractDuplicateDetectionTest.php`

**Step 1: Write tests for duplicate detection service logic**

Since the actual duplicate detection warning is a Livewire/UI concern (difficult to test end-to-end), we test the detection query logic as a feature test:

```php
<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractDuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_detected_by_file_hash(): void
    {
        $contract = Contract::factory()->create([
            'file_hash' => hash('sha256', 'test-content'),
            'file_name' => 'contract-a.pdf',
        ]);

        $duplicates = Contract::where('file_hash', hash('sha256', 'test-content'))
            ->where('id', '!=', $contract->id)
            ->get();

        // No other contract with same hash yet
        $this->assertCount(0, $duplicates);

        // Create a second contract with same hash
        $contract2 = Contract::factory()->create([
            'file_hash' => hash('sha256', 'test-content'),
            'file_name' => 'contract-b.pdf',
        ]);

        $duplicates = Contract::where(function ($q) use ($contract2) {
            $q->where('file_hash', $contract2->file_hash)
              ->orWhere('file_name', $contract2->file_name);
        })
        ->where('id', '!=', $contract2->id)
        ->get();

        $this->assertCount(1, $duplicates);
        $this->assertEquals($contract->id, $duplicates->first()->id);
    }

    public function test_duplicate_detected_by_file_name(): void
    {
        $contract = Contract::factory()->create([
            'file_hash' => hash('sha256', 'content-a'),
            'file_name' => 'master-services-agreement.pdf',
        ]);

        $contract2 = Contract::factory()->create([
            'file_hash' => hash('sha256', 'content-b'),
            'file_name' => 'master-services-agreement.pdf',
        ]);

        $duplicates = Contract::where(function ($q) use ($contract2) {
            $q->where('file_hash', $contract2->file_hash)
              ->orWhere('file_name', $contract2->file_name);
        })
        ->where('id', '!=', $contract2->id)
        ->get();

        $this->assertCount(1, $duplicates);
    }

    public function test_no_duplicate_when_different_hash_and_name(): void
    {
        Contract::factory()->create([
            'file_hash' => hash('sha256', 'content-a'),
            'file_name' => 'contract-a.pdf',
        ]);

        $contract2 = Contract::factory()->create([
            'file_hash' => hash('sha256', 'content-b'),
            'file_name' => 'contract-b.pdf',
        ]);

        $duplicates = Contract::where(function ($q) use ($contract2) {
            $q->where('file_hash', $contract2->file_hash)
              ->orWhere('file_name', $contract2->file_name);
        })
        ->where('id', '!=', $contract2->id)
        ->get();

        $this->assertCount(0, $duplicates);
    }
}
```

**Step 2: Run tests**

```bash
php artisan test tests/Feature/Contracts/ContractDuplicateDetectionTest.php
```

Expected: Tests PASS (they test the query logic, not the UI).

**Step 3: Commit**

```bash
git add tests/Feature/Contracts/ContractDuplicateDetectionTest.php
git commit -m "test: add duplicate contract detection tests"
```

---

## Task 7: Duplicate Contract Detection — Implementation

**Files:**
- Modify: `app/Filament/Resources/ContractResource.php` (FileUpload `afterStateUpdated` hook)
- Modify: `app/Filament/Resources/ContractResource/Pages/CreateContract.php`

**Step 1: Add file hash computation to FileUpload in ContractResource form**

In `ContractResource.php`, find the existing `FileUpload::make('storage_path')` component (around line 204). Modify its `afterStateUpdated` closure to also compute the SHA-256 hash and check for duplicates:

```php
Forms\Components\FileUpload::make('storage_path')
    ->label('Contract File')
    ->disk(config('ccrs.contracts_disk'))
    ->directory('contracts')
    ->columnSpanFull()
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

            $duplicates = $query->limit(5)->get(['id', 'title', 'contract_ref', 'file_name', 'created_at', 'created_by']);

            if ($duplicates->isNotEmpty()) {
                $details = $duplicates->map(fn ($c) => "- {$c->contract_ref}: {$c->title} ({$c->file_name})")->join("\n");
                \Filament\Notifications\Notification::make()
                    ->title('Duplicate contract suspected')
                    ->body("The uploaded file matches " . $duplicates->count() . " existing contract(s):\n{$details}\n\nAre you sure you wish to proceed?")
                    ->warning()
                    ->persistent()
                    ->send();
            }
        }
    }),
```

Also add a hidden field for file_hash in the form schema (inside the same section as storage_path):

```php
Forms\Components\Hidden::make('file_hash'),
```

**Step 2: Ensure `file_hash` is persisted in CreateContract**

In `app/Filament/Resources/ContractResource/Pages/CreateContract.php`, the existing `mutateFormDataBeforeCreate` already passes through all form data. Since `file_hash` is in the form as a hidden field and `file_hash` is in the model's `$fillable`, it will be automatically saved. No additional code needed here.

**Step 3: Commit**

```bash
git add app/Filament/Resources/ContractResource.php
git commit -m "feat: add duplicate contract detection on file upload with SHA-256 hash"
```

---

## Task 8: Grouped Review Page — Tests

**Files:**
- Create: `tests/Feature/AiDiscoveryReviewPageTest.php`

**Step 1: Write tests for the review page grouped display and filters**

```php
<?php

namespace Tests\Feature;

use App\Filament\Pages\AiDiscoveryReviewPage;
use App\Models\AiDiscoveryDraft;
use App\Models\Contract;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiDiscoveryReviewPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_page_renders_with_pending_drafts(): void
    {
        $contract = Contract::factory()->create(['title' => 'Alpha Contract']);
        AiDiscoveryDraft::factory()->counterparty()->create(['contract_id' => $contract->id]);

        Livewire::test(AiDiscoveryReviewPage::class)
            ->assertSuccessful();
    }

    public function test_drafts_from_multiple_contracts_are_visible(): void
    {
        $contract1 = Contract::factory()->create(['title' => 'Contract Alpha']);
        $contract2 = Contract::factory()->create(['title' => 'Contract Beta']);

        AiDiscoveryDraft::factory()->counterparty()->create(['contract_id' => $contract1->id]);
        AiDiscoveryDraft::factory()->jurisdiction()->create(['contract_id' => $contract2->id]);

        Livewire::test(AiDiscoveryReviewPage::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(AiDiscoveryDraft::where('status', 'pending')->get());
    }

    public function test_filter_by_contract_shows_only_that_contracts_drafts(): void
    {
        $contract1 = Contract::factory()->create(['title' => 'Contract Alpha']);
        $contract2 = Contract::factory()->create(['title' => 'Contract Beta']);

        $draft1 = AiDiscoveryDraft::factory()->counterparty()->create(['contract_id' => $contract1->id]);
        $draft2 = AiDiscoveryDraft::factory()->jurisdiction()->create(['contract_id' => $contract2->id]);

        Livewire::test(AiDiscoveryReviewPage::class)
            ->filterTable('contract_id', $contract1->id)
            ->assertCanSeeTableRecords([$draft1])
            ->assertCanNotSeeTableRecords([$draft2]);
    }

    public function test_approved_drafts_not_shown(): void
    {
        $contract = Contract::factory()->create();
        AiDiscoveryDraft::factory()->counterparty()->approved()->create(['contract_id' => $contract->id]);

        Livewire::test(AiDiscoveryReviewPage::class)
            ->assertCanNotSeeTableRecords(AiDiscoveryDraft::where('status', 'approved')->get());
    }

    public function test_navigation_badge_shows_pending_count(): void
    {
        $contract = Contract::factory()->create();
        AiDiscoveryDraft::factory()->count(3)->create(['contract_id' => $contract->id, 'status' => 'pending']);
        AiDiscoveryDraft::factory()->approved()->create(['contract_id' => $contract->id]);

        $badge = AiDiscoveryReviewPage::getNavigationBadge();
        $this->assertEquals('3', $badge);
    }
}
```

**Step 2: Run tests to verify they fail (grouped features don't exist yet)**

```bash
php artisan test tests/Feature/AiDiscoveryReviewPageTest.php
```

Expected: Some tests may pass (basic rendering), filter test will fail.

**Step 3: Commit**

```bash
git add tests/Feature/AiDiscoveryReviewPageTest.php
git commit -m "test: add review page tests for grouped display and contract filter"
```

---

## Task 9: Grouped Review Page — Implementation

**Files:**
- Modify: `app/Filament/Pages/AiDiscoveryReviewPage.php`

**Step 1: Rewrite the table method with grouping and filters**

Replace the entire `table()` method:

```php
public function table(Table $table): Table
{
    return $table
        ->query(
            AiDiscoveryDraft::query()
                ->where('status', 'pending')
                ->with('contract')
        )
        ->columns([
            Tables\Columns\TextColumn::make('contract.contract_ref')
                ->label('Contract Ref')
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('contract.title')
                ->label('Contract Title')
                ->limit(30)
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('draft_type')
                ->badge()
                ->color(fn ($state) => match ($state) {
                    'counterparty' => 'warning',
                    'entity' => 'info',
                    'jurisdiction' => 'success',
                    'governing_law' => 'primary',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('extracted_data')
                ->label('Extracted')
                ->formatStateUsing(function ($state) {
                    if (! is_array($state)) {
                        return (string) $state;
                    }

                    return collect($state)
                        ->map(fn ($v, $k) => "{$k}: " . (is_array($v) ? json_encode($v) : (string) $v))
                        ->take(3)
                        ->join(', ');
                })
                ->limit(60),
            Tables\Columns\TextColumn::make('confidence')
                ->badge()
                ->color(fn ($state) => $state >= 0.8 ? 'success' : ($state >= 0.5 ? 'warning' : 'danger'))
                ->formatStateUsing(fn ($state) => round(($state ?? 0) * 100) . '%'),
            Tables\Columns\TextColumn::make('matched_record_id')
                ->label('Match')
                ->formatStateUsing(fn ($state) => $state ? 'Existing record' : 'New record')
                ->badge()
                ->color(fn ($state) => $state ? 'success' : 'warning'),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
        ])
        ->groups([
            Tables\Grouping\Group::make('contract.contract_ref')
                ->label('Contract')
                ->collapsible()
                ->getTitleFromRecordUsing(
                    fn (AiDiscoveryDraft $record): string =>
                        ($record->contract?->contract_ref ?? 'N/A') . ' — ' . ($record->contract?->title ?? 'Untitled')
                ),
        ])
        ->defaultGroup('contract.contract_ref')
        ->filters([
            Tables\Filters\SelectFilter::make('contract_id')
                ->label('Contract')
                ->options(fn () => Contract::whereHas('aiDiscoveryDrafts', fn ($q) => $q->where('status', 'pending'))
                    ->get()
                    ->mapWithKeys(fn (Contract $c) => [$c->id => ($c->contract_ref ?? 'N/A') . ' — ' . $c->title])
                    ->toArray()
                )
                ->searchable(),
            Tables\Filters\SelectFilter::make('draft_type')
                ->options([
                    'counterparty' => 'Counterparty',
                    'entity' => 'Entity',
                    'jurisdiction' => 'Jurisdiction',
                    'governing_law' => 'Governing Law',
                ]),
        ])
        ->actions([
            Tables\Actions\Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (AiDiscoveryDraft $record) {
                    try {
                        app(AiDiscoveryService::class)->approveDraft($record, auth()->user());
                        Notification::make()->title('Draft approved')->success()->send();
                    } catch (\Exception $e) {
                        Log::error('AI Discovery approve failed', [
                            'draft_id' => $record->id,
                            'error' => $e->getMessage(),
                        ]);
                        Notification::make()
                            ->title('Approval failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Tables\Actions\Action::make('reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (AiDiscoveryDraft $record) {
                    try {
                        app(AiDiscoveryService::class)->rejectDraft($record, auth()->user());
                        Notification::make()->title('Draft rejected')->warning()->send();
                    } catch (\Exception $e) {
                        Log::error('AI Discovery reject failed', [
                            'draft_id' => $record->id,
                            'error' => $e->getMessage(),
                        ]);
                        Notification::make()
                            ->title('Rejection failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ])
        ->defaultSort('created_at', 'desc')
        ->poll('30s');
}
```

Also add the required import at the top of the file:

```php
use App\Models\Contract;
```

**Step 2: Add `aiDiscoveryDrafts` relationship to Contract model if missing**

In `app/Models/Contract.php`, ensure this relationship exists:

```php
public function aiDiscoveryDrafts(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\AiDiscoveryDraft::class);
}
```

**Step 3: Run the review page tests**

```bash
php artisan test tests/Feature/AiDiscoveryReviewPageTest.php
```

Expected: All tests PASS.

**Step 4: Run full test suite**

```bash
php artisan test
```

Expected: No regressions.

**Step 5: Commit**

```bash
git add app/Filament/Pages/AiDiscoveryReviewPage.php app/Models/Contract.php
git commit -m "feat: rewrite AI Discovery Review with contract grouping, collapsible sections, and filters"
```

---

## Task 10: Final Integration — Run All Tests & Push

**Step 1: Run full test suite**

```bash
php artisan test
```

Expected: All tests PASS.

**Step 2: Sync branches**

```bash
git push origin laravel-migration
git checkout main && git merge laravel-migration && git push origin main
git checkout sandbox && git merge main && git push ccrs sandbox
git checkout laravel-migration
```

**Step 3: Verify**

```bash
git log --oneline -8
```

Expected: All commits from this refactoring visible on all branches.
