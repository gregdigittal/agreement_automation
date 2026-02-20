# Cursor Prompt — Laravel Migration Phase M: Final Phase 1 Gap Closure (Countersigning, Org Visualization, WCAG AA)

## Context

**Run this prompt in the `agreement_automation` repo on the `laravel-migration` branch** — the same branch where Phases A through L were executed.

This prompt closes the final Phase 1 gaps found by cross-referencing all 17 epics in the *CCRS Requirements v3 Board Edition* against Prompts A through L. Three requirements were identified as incomplete or missing:

1. **Countersigning Workflow for Third-Party Paper** (Epic 9, Section 4.5) — BoldSign integration supports standard signing but not the reverse countersign flow where the counterparty has already signed externally.
2. **Visual Organization and Flow Visualization** (Section 4.11) — the configurable org-hierarchy overlay with workflow stage mapping has not been built.
3. **WCAG 2.1 AA Accessibility Baseline** (NFR Section 5) — Filament 3 provides reasonable defaults, but explicit gaps remain around skip-nav, contrast, ARIA roles on custom components, and chart accessibility.

After this prompt, all 17 epics are covered by Phases A through M and the application is feature-complete for Phase 1.

---

## Task 1: Countersigning Workflow for Third-Party Paper (Epic 9, Section 4.5)

The requirements state: *"Countersigning workflow is available for third-party paper where the counterparty signs first and Digittal countersigns."*

Currently, `BoldsignService` supports sending contracts where Digittal's signers sign first (or in parallel). The countersigning flow is the reverse:

1. Commercial user uploads a third-party-paper contract already partially signed by the counterparty.
2. The contract's workflow enters a `countersign` stage (not `signing`).
3. The system verifies Digittal's signing authority is valid for this contract.
4. A BoldSign envelope is created with **only** the internal Digittal signers (counterparty has already signed on paper or externally).
5. On BoldSign completion, the countersigned copy replaces the uploaded document and the contract is marked as executed.

### 1.1 Migration: Add `is_countersign` to `boldsign_envelopes`

Create `database/migrations/XXXX_add_is_countersign_to_boldsign_envelopes.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boldsign_envelopes', function (Blueprint $table) {
            $table->boolean('is_countersign')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('boldsign_envelopes', function (Blueprint $table) {
            $table->dropColumn('is_countersign');
        });
    }
};
```

### 1.2 Update `BoldsignEnvelope` Model

In `app/Models/BoldsignEnvelope.php`, add `'is_countersign'` to `$fillable` and `$casts`:

```php
protected $fillable = [
    // ... existing fields ...
    'is_countersign',
];

protected $casts = [
    // ... existing casts ...
    'is_countersign' => 'boolean',
];
```

### 1.3 Allow `'countersign'` Stage Type in Workflow Templates

In `app/Services/WorkflowService.php`, locate the list of allowed stage types (set in Prompt B, extended in subsequent prompts). Add `'countersign'` to the allowed types:

```php
// In the stage type validation (e.g., inside createTemplate or validateStages):
$allowedTypes = ['review', 'approval', 'signing', 'countersign', 'parallel_approval'];
```

### 1.4 Enforce Signing Authority for Countersign Stages

In `WorkflowService::recordAction()`, the signing authority check (added in Prompt L, Task 3) currently fires only when stage type is `signing`. Extend it to also fire for `countersign`:

```php
// Find the existing check added in Prompt L:
if ($currentStageConfig && ($currentStageConfig['type'] ?? null) === 'signing') {
    $this->checkSigningAuthority($instance->contract, $actor, $stageName);
}

// Replace with:
if ($currentStageConfig && in_array($currentStageConfig['type'] ?? null, ['signing', 'countersign'])) {
    $this->checkSigningAuthority($instance->contract, $actor, $stageName);
}
```

### 1.5 Add `createCountersignEnvelope()` to `BoldsignService`

In `app/Services/BoldsignService.php`, add a new method:

```php
/**
 * Create a BoldSign envelope for countersigning — only internal Digittal signers.
 *
 * The uploaded document already has the counterparty's signature. This method
 * sends it to BoldSign with only the internal signers so Digittal can countersign.
 *
 * @param Contract $contract  The contract with its uploaded (partially signed) document.
 * @param array    $internalSigners  Array of ['user_id' => string, 'name' => string, 'email' => string, 'order' => int].
 * @return BoldsignEnvelope
 */
public function createCountersignEnvelope(Contract $contract, array $internalSigners): BoldsignEnvelope
{
    // 1. Download the uploaded (partially signed) document from S3
    $documentPath = $contract->document_path;
    if (!$documentPath) {
        throw new \RuntimeException("Contract {$contract->id} has no uploaded document to countersign.");
    }

    $documentContents = Storage::disk('s3')->get($documentPath);
    if (!$documentContents) {
        throw new \RuntimeException("Failed to download document from S3: {$documentPath}");
    }

    // 2. Build signers array — only internal Digittal signers
    $signers = collect($internalSigners)->map(fn (array $signer, int $index) => [
        'name' => $signer['name'],
        'emailAddress' => $signer['email'],
        'signerOrder' => $signer['order'] ?? ($index + 1),
        'signerType' => 'Signer',
    ])->values()->toArray();

    // 3. Create BoldSign envelope via API
    $response = Http::withToken(config('services.boldsign.api_key'))
        ->attach('Files', $documentContents, basename($documentPath))
        ->post(config('services.boldsign.base_url') . '/v1/document/send', [
            'title' => "Countersign: {$contract->title}",
            'signers' => $signers,
            'enableSigningOrder' => true,
            'message' => "Please countersign this contract on behalf of Digittal.",
        ]);

    if (!$response->successful()) {
        throw new \RuntimeException(
            "BoldSign API error creating countersign envelope: " . $response->body()
        );
    }

    $data = $response->json();

    // 4. Record in boldsign_envelopes
    return BoldsignEnvelope::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'contract_id' => $contract->id,
        'boldsign_document_id' => $data['documentId'] ?? $data['id'],
        'status' => 'sent',
        'is_countersign' => true,
        'signers' => $internalSigners,
        'sent_at' => now(),
        'created_by' => auth()->id(),
    ]);
}
```

### 1.6 Add "Send for Countersigning" Filament Action on ContractResource

In `app/Filament/Resources/ContractResource.php`, inside the table actions (or header actions on the view/edit page), add a new action:

```php
use App\Models\SigningAuthority;
use App\Services\BoldsignService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

Tables\Actions\Action::make('sendForCountersigning')
    ->label('Send for Countersigning')
    ->icon('heroicon-o-pen-tool')
    ->color('warning')
    ->visible(function (Contract $record): bool {
        // Only show when current workflow stage type is 'countersign'
        $instance = $record->activeWorkflowInstance;
        if (!$instance || !$instance->template) {
            return false;
        }
        $stages = collect($instance->template->stages);
        $currentStage = $stages->firstWhere('name', $instance->current_stage);
        return ($currentStage['type'] ?? null) === 'countersign';
    })
    ->form(function (Contract $record): array {
        // Pre-populate signers from signing_authority records matching entity/project
        $authorities = SigningAuthority::query()
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
            Repeater::make('signers')
                ->label('Internal Digittal Signers')
                ->schema([
                    Select::make('user_id')
                        ->label('User')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $user = \App\Models\User::find($state);
                                $set('name', $user?->name ?? '');
                                $set('email', $user?->email ?? '');
                            }
                        }),
                    TextInput::make('name')->required(),
                    TextInput::make('email')->email()->required(),
                    TextInput::make('order')
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
    ->modalDescription('This will create a BoldSign envelope with only the internal Digittal signers listed below. The counterparty has already signed this document externally.')
    ->action(function (Contract $record, array $data): void {
        $service = app(BoldsignService::class);
        $envelope = $service->createCountersignEnvelope($record, $data['signers']);

        Notification::make()
            ->title('Countersign envelope sent')
            ->body("BoldSign document ID: {$envelope->boldsign_document_id}")
            ->success()
            ->send();
    }),
```

**Note:** The existing BoldSign webhook handler for `DocumentCompleted` (Prompt L, Task 4) already marks the contract as executed and locks it. No changes are needed to the webhook — it handles countersign envelopes identically to standard envelopes because the `boldsign_envelopes` record links back to the same contract.

### 1.7 Feature Test

Create `tests/Feature/CountersignWorkflowTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\SigningAuthority;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTemplate;
use App\Services\BoldsignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CountersignWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_countersign_envelope_created_with_only_internal_signers(): void
    {
        Storage::fake('s3');
        Http::fake([
            '*/v1/document/send' => Http::response([
                'documentId' => 'boldsign-doc-123',
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        // Create contract with an uploaded document
        $contract = Contract::factory()->create([
            'document_path' => 'contracts/test-partially-signed.pdf',
            'workflow_state' => 'in_progress',
        ]);
        Storage::disk('s3')->put('contracts/test-partially-signed.pdf', 'fake-pdf-content');

        // Create a workflow template with a countersign stage
        $template = WorkflowTemplate::factory()->create([
            'stages' => [
                ['name' => 'legal_review', 'type' => 'review', 'owners' => ['legal']],
                ['name' => 'countersign', 'type' => 'countersign', 'owners' => ['legal']],
            ],
        ]);

        // Create workflow instance at the countersign stage
        $instance = WorkflowInstance::factory()->create([
            'contract_id' => $contract->id,
            'template_id' => $template->id,
            'current_stage' => 'countersign',
            'status' => 'active',
        ]);

        // Create signing authority for the user
        SigningAuthority::factory()->create([
            'user_id' => $user->id,
            'entity_id' => $contract->entity_id,
        ]);

        // Call the countersign service
        $service = app(BoldsignService::class);
        $envelope = $service->createCountersignEnvelope($contract, [
            ['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'order' => 1],
        ]);

        $this->assertDatabaseHas('boldsign_envelopes', [
            'contract_id' => $contract->id,
            'is_countersign' => true,
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/document/send');
        });
    }

    public function test_countersign_stage_requires_signing_authority(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contract = Contract::factory()->create([
            'workflow_state' => 'in_progress',
        ]);

        $template = WorkflowTemplate::factory()->create([
            'stages' => [
                ['name' => 'countersign', 'type' => 'countersign', 'owners' => ['legal']],
            ],
        ]);

        $instance = WorkflowInstance::factory()->create([
            'contract_id' => $contract->id,
            'template_id' => $template->id,
            'current_stage' => 'countersign',
            'status' => 'active',
        ]);

        // No signing authority exists — should throw
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No signing authority/');

        app(\App\Services\WorkflowService::class)->recordAction(
            $instance,
            $user,
            'countersign',
            'approve'
        );
    }
}
```

---

## Task 2: Visual Organization and Flow Visualization (Section 4.11)

The requirements state: *"Configurable visual organization structure that shows region/entity/project hierarchy. Overlay workflow stages to visualize contract flow, responsible roles, and signing authority at each stage. Counterparty(ies) and their signing participants included in the setup and view."*

### 2.1 Create the Filament Custom Page

Create `app/Filament/Pages/OrgVisualizationPage.php`:

```php
<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class OrgVisualizationPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Org Visualization';
    protected static ?string $title = 'Organization & Workflow Visualization';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 35;
    protected static string $view = 'filament.pages.org-visualization';
}
```

### 2.2 Create the Livewire Component

Create `app/Livewire/OrgHierarchyViewer.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Region;
use App\Models\Entity;
use App\Models\Project;
use App\Models\SigningAuthority;
use App\Models\WorkflowTemplate;
use Livewire\Component;

class OrgHierarchyViewer extends Component
{
    public ?string $selectedNodeType = null;
    public ?string $selectedNodeId = null;
    public ?string $counterpartyId = null;

    // Side panel data
    public array $signingAuthorities = [];
    public array $workflowTemplates = [];
    public ?string $selectedNodeLabel = null;

    public function mount(): void
    {
        $this->counterpartyId = request()->query('counterparty_id');
    }

    public function getTreeProperty(): array
    {
        $regions = Region::with([
            'entities' => fn ($q) => $q->withCount([
                'contracts as active_contracts_count' => fn ($cq) => $cq->whereNotIn('workflow_state', ['cancelled', 'expired']),
            ])->with([
                'projects' => fn ($q) => $q->withCount([
                    'contracts as active_contracts_count' => fn ($cq) => $cq->whereNotIn('workflow_state', ['cancelled', 'expired']),
                ]),
            ]),
        ])->withCount([
            'contracts as active_contracts_count' => fn ($cq) => $cq->whereNotIn('workflow_state', ['cancelled', 'expired']),
        ])->get();

        return $regions->map(fn (Region $region) => [
            'id' => $region->id,
            'type' => 'region',
            'name' => $region->name,
            'code' => $region->code ?? '',
            'active_contracts' => $region->active_contracts_count,
            'active_workflows' => $this->countActiveWorkflows('region', $region->id),
            'highlighted' => $this->isHighlighted('region', $region->id),
            'children' => $region->entities->map(fn (Entity $entity) => [
                'id' => $entity->id,
                'type' => 'entity',
                'name' => $entity->name,
                'code' => $entity->code ?? '',
                'active_contracts' => $entity->active_contracts_count,
                'active_workflows' => $this->countActiveWorkflows('entity', $entity->id),
                'highlighted' => $this->isHighlighted('entity', $entity->id),
                'children' => $entity->projects->map(fn (Project $project) => [
                    'id' => $project->id,
                    'type' => 'project',
                    'name' => $project->name,
                    'code' => $project->code ?? '',
                    'active_contracts' => $project->active_contracts_count,
                    'active_workflows' => $this->countActiveWorkflows('project', $project->id),
                    'highlighted' => $this->isHighlighted('project', $project->id),
                    'children' => [],
                ])->toArray(),
            ])->toArray(),
        ])->toArray();
    }

    /**
     * Select a node and load its signing authorities + workflow templates into the side panel.
     */
    public function selectNode(string $type, string $id): void
    {
        $this->selectedNodeType = $type;
        $this->selectedNodeId = $id;

        // Load signing authorities
        $authQuery = SigningAuthority::with('user');
        if ($type === 'entity') {
            $authQuery->where('entity_id', $id);
            $entity = Entity::find($id);
            $this->selectedNodeLabel = $entity?->name ?? $id;
        } elseif ($type === 'project') {
            $project = Project::find($id);
            $authQuery->where(function ($q) use ($project) {
                $q->where('project_id', $project?->id)
                  ->orWhere(function ($q2) use ($project) {
                      $q2->where('entity_id', $project?->entity_id)
                          ->whereNull('project_id');
                  });
            });
            $this->selectedNodeLabel = $project?->name ?? $id;
        } elseif ($type === 'region') {
            $region = Region::with('entities')->find($id);
            $entityIds = $region?->entities->pluck('id')->toArray() ?? [];
            $authQuery->whereIn('entity_id', $entityIds);
            $this->selectedNodeLabel = $region?->name ?? $id;
        }

        $this->signingAuthorities = $authQuery->get()->map(fn ($auth) => [
            'id' => $auth->id,
            'user_name' => $auth->user?->name ?? 'Unknown',
            'user_email' => $auth->user?->email ?? '',
            'role' => $auth->role ?? '',
            'contract_type_pattern' => $auth->contract_type_pattern ?? '*',
        ])->toArray();

        // Load workflow templates scoped to this entity/project
        $templateQuery = WorkflowTemplate::query();
        if ($type === 'entity') {
            $templateQuery->where(function ($q) use ($id) {
                $q->where('entity_id', $id)->orWhereNull('entity_id');
            });
        } elseif ($type === 'project') {
            $project = Project::find($id);
            $templateQuery->where(function ($q) use ($id, $project) {
                $q->where('project_id', $id)
                  ->orWhere('entity_id', $project?->entity_id)
                  ->orWhereNull('entity_id');
            });
        }
        // For region, show all templates for entities in that region
        elseif ($type === 'region') {
            $region = Region::with('entities')->find($id);
            $entityIds = $region?->entities->pluck('id')->toArray() ?? [];
            $templateQuery->where(function ($q) use ($entityIds) {
                $q->whereIn('entity_id', $entityIds)->orWhereNull('entity_id');
            });
        }

        $this->workflowTemplates = $templateQuery->get()->map(fn ($tpl) => [
            'id' => $tpl->id,
            'name' => $tpl->name,
            'contract_type' => $tpl->contract_type ?? 'Any',
            'stage_count' => is_array($tpl->stages) ? count($tpl->stages) : 0,
            'status' => $tpl->status ?? 'active',
            'stages' => collect($tpl->stages ?? [])->map(fn ($stage) => [
                'name' => $stage['name'] ?? 'Unnamed',
                'type' => $stage['type'] ?? 'review',
                'owners' => $stage['owners'] ?? [],
            ])->toArray(),
        ])->toArray();
    }

    private function countActiveWorkflows(string $nodeType, string $nodeId): int
    {
        return \App\Models\WorkflowInstance::where('status', 'active')
            ->whereHas('contract', function ($q) use ($nodeType, $nodeId) {
                if ($nodeType === 'region') {
                    $q->whereHas('entity', fn ($eq) => $eq->where('region_id', $nodeId));
                } elseif ($nodeType === 'entity') {
                    $q->where('entity_id', $nodeId);
                } elseif ($nodeType === 'project') {
                    $q->where('project_id', $nodeId);
                }
            })
            ->count();
    }

    private function isHighlighted(string $nodeType, string $nodeId): bool
    {
        if (!$this->counterpartyId) {
            return false;
        }

        return \App\Models\Contract::query()
            ->where('counterparty_id', $this->counterpartyId)
            ->whereNotIn('workflow_state', ['cancelled', 'expired'])
            ->when($nodeType === 'region', fn ($q) =>
                $q->whereHas('entity', fn ($eq) => $eq->where('region_id', $nodeId))
            )
            ->when($nodeType === 'entity', fn ($q) =>
                $q->where('entity_id', $nodeId)
            )
            ->when($nodeType === 'project', fn ($q) =>
                $q->where('project_id', $nodeId)
            )
            ->exists();
    }

    public function render()
    {
        return view('livewire.org-hierarchy-viewer', [
            'tree' => $this->tree,
        ]);
    }
}
```

### 2.3 Create Blade Views

**Create `resources/views/filament/pages/org-visualization.blade.php`:**

```blade
<x-filament-panels::page>
    <livewire:org-hierarchy-viewer />
</x-filament-panels::page>
```

**Create `resources/views/livewire/org-hierarchy-viewer.blade.php`:**

```blade
<div class="flex gap-6" x-data="{ expandedNodes: {} }">
    {{-- Left panel: Tree --}}
    <div class="w-2/3 bg-white dark:bg-gray-800 rounded-xl shadow p-4 overflow-auto" role="tree" aria-label="Organization hierarchy">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Region / Entity / Project Hierarchy</h3>

        @forelse ($tree as $region)
            <div class="mb-2" role="treeitem" aria-expanded="false" x-data="{ open: false }">
                {{-- Region node --}}
                <button
                    class="flex items-center gap-2 w-full text-left px-3 py-2 rounded-lg transition
                        {{ $region['highlighted'] ? 'bg-yellow-100 dark:bg-yellow-900 border border-yellow-400' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}
                        {{ $selectedNodeId === $region['id'] ? 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900' : '' }}"
                    x-on:click="open = !open"
                    wire:click="selectNode('region', '{{ $region['id'] }}')"
                    aria-label="Region: {{ $region['name'] }}"
                >
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <x-heroicon-o-globe-alt class="w-5 h-5 text-blue-600" />
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $region['name'] }}</span>
                    @if ($region['code'])
                        <span class="text-xs text-gray-500">({{ $region['code'] }})</span>
                    @endif
                    <span class="ml-auto text-xs text-gray-500">{{ $region['active_contracts'] }} contracts &middot; {{ $region['active_workflows'] }} workflows</span>
                </button>

                {{-- Entities --}}
                <div x-show="open" x-collapse class="ml-6 mt-1" role="group">
                    @foreach ($region['children'] as $entity)
                        <div class="mb-1" role="treeitem" aria-expanded="false" x-data="{ entityOpen: false }">
                            <button
                                class="flex items-center gap-2 w-full text-left px-3 py-1.5 rounded-lg transition
                                    {{ $entity['highlighted'] ? 'bg-yellow-100 dark:bg-yellow-900 border border-yellow-400' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}
                                    {{ $selectedNodeId === $entity['id'] ? 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900' : '' }}"
                                x-on:click="entityOpen = !entityOpen"
                                wire:click="selectNode('entity', '{{ $entity['id'] }}')"
                                aria-label="Entity: {{ $entity['name'] }}"
                            >
                                <svg class="w-3 h-3 transition-transform" :class="entityOpen ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <x-heroicon-o-building-office class="w-4 h-4 text-indigo-600" />
                                <span class="text-gray-800 dark:text-gray-200">{{ $entity['name'] }}</span>
                                @if ($entity['code'])
                                    <span class="text-xs text-gray-500">({{ $entity['code'] }})</span>
                                @endif
                                <span class="ml-auto text-xs text-gray-500">{{ $entity['active_contracts'] }} contracts &middot; {{ $entity['active_workflows'] }} workflows</span>
                            </button>

                            {{-- Projects --}}
                            <div x-show="entityOpen" x-collapse class="ml-6 mt-1" role="group">
                                @foreach ($entity['children'] as $project)
                                    <button
                                        class="flex items-center gap-2 w-full text-left px-3 py-1.5 rounded-lg transition
                                            {{ $project['highlighted'] ? 'bg-yellow-100 dark:bg-yellow-900 border border-yellow-400' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}
                                            {{ $selectedNodeId === $project['id'] ? 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900' : '' }}"
                                        wire:click="selectNode('project', '{{ $project['id'] }}')"
                                        role="treeitem"
                                        aria-label="Project: {{ $project['name'] }}"
                                    >
                                        <x-heroicon-o-folder class="w-4 h-4 text-green-600" />
                                        <span class="text-gray-700 dark:text-gray-300">{{ $project['name'] }}</span>
                                        @if ($project['code'])
                                            <span class="text-xs text-gray-500">({{ $project['code'] }})</span>
                                        @endif
                                        <span class="ml-auto text-xs text-gray-500">{{ $project['active_contracts'] }} contracts &middot; {{ $project['active_workflows'] }} workflows</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-gray-500 italic">No regions, entities, or projects found.</p>
        @endforelse
    </div>

    {{-- Right panel: Details --}}
    <div class="w-1/3 bg-white dark:bg-gray-800 rounded-xl shadow p-4 overflow-auto" aria-live="polite">
        @if ($selectedNodeId)
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">
                {{ $selectedNodeLabel }}
                <span class="text-xs font-normal text-gray-500 uppercase">{{ $selectedNodeType }}</span>
            </h3>

            {{-- Signing Authorities --}}
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Signing Authorities</h4>
                @if (count($signingAuthorities) > 0)
                    <table class="w-full text-sm" aria-label="Signing authorities for {{ $selectedNodeLabel }}">
                        <thead>
                            <tr class="text-left text-gray-500 border-b dark:border-gray-700">
                                <th class="py-1">Name</th>
                                <th class="py-1">Role</th>
                                <th class="py-1">Type Pattern</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($signingAuthorities as $auth)
                                <tr class="border-b dark:border-gray-700">
                                    <td class="py-1 text-gray-900 dark:text-gray-100">{{ $auth['user_name'] }}</td>
                                    <td class="py-1 text-gray-600 dark:text-gray-400">{{ $auth['role'] }}</td>
                                    <td class="py-1 text-gray-600 dark:text-gray-400">{{ $auth['contract_type_pattern'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-gray-500 text-sm italic">No signing authorities configured.</p>
                @endif
            </div>

            {{-- Workflow Templates --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Workflow Templates</h4>
                @if (count($workflowTemplates) > 0)
                    @foreach ($workflowTemplates as $tpl)
                        <div class="mb-3 border rounded-lg p-3 dark:border-gray-700" x-data="{ expanded: false }">
                            <button
                                class="flex items-center justify-between w-full text-left"
                                x-on:click="expanded = !expanded"
                                aria-expanded="false"
                                :aria-expanded="expanded.toString()"
                            >
                                <div>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $tpl['name'] }}</span>
                                    <span class="text-xs text-gray-500 ml-2">{{ $tpl['contract_type'] }} &middot; {{ $tpl['stage_count'] }} stages</span>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $tpl['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($tpl['status']) }}
                                </span>
                            </button>

                            <div x-show="expanded" x-collapse class="mt-2">
                                <table class="w-full text-xs" aria-label="Stages for {{ $tpl['name'] }}">
                                    <thead>
                                        <tr class="text-left text-gray-500 border-b dark:border-gray-700">
                                            <th class="py-1">Stage</th>
                                            <th class="py-1">Type</th>
                                            <th class="py-1">Owners</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($tpl['stages'] as $stage)
                                            <tr class="border-b dark:border-gray-700">
                                                <td class="py-1 text-gray-900 dark:text-gray-100">{{ $stage['name'] }}</td>
                                                <td class="py-1">
                                                    <span class="inline-flex px-1.5 py-0.5 rounded text-xs
                                                        {{ $stage['type'] === 'signing' || $stage['type'] === 'countersign' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                                        {{ $stage['type'] }}
                                                    </span>
                                                </td>
                                                <td class="py-1 text-gray-600 dark:text-gray-400">{{ implode(', ', $stage['owners']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                @else
                    <p class="text-gray-500 text-sm italic">No workflow templates scoped to this node.</p>
                @endif
            </div>
        @else
            <div class="flex flex-col items-center justify-center h-64 text-gray-400">
                <x-heroicon-o-cursor-arrow-rays class="w-12 h-12 mb-2" />
                <p>Select a node to view details</p>
            </div>
        @endif
    </div>
</div>
```

### 2.4 Register the Page and Component

The Filament page auto-discovers from `app/Filament/Pages/` (configured in `AdminPanelProvider.php` via `->discoverPages()`). No manual registration needed.

For the Livewire component, Laravel auto-discovers Livewire components in `app/Livewire/`. If you encounter a "component not found" error, register it explicitly in `app/Providers/AppServiceProvider.php`:

```php
use Livewire\Livewire;

public function boot(): void
{
    // ... existing boot code ...
    Livewire::component('org-hierarchy-viewer', \App\Livewire\OrgHierarchyViewer::class);
}
```

---

## Task 3: WCAG 2.1 AA Accessibility Baseline (NFR Section 5)

The requirements state: *"WCAG 2.1 AA compliance for the web application."*

Filament 3 has reasonable built-in accessibility, but some gaps need explicit attention.

### 3.1 Skip Navigation Link

Create or update the custom Filament layout. If `resources/views/filament/layout.blade.php` does not exist, create it. If a custom layout already exists, prepend the skip link to it.

**Create `resources/views/filament/layout.blade.php`:**

```blade
@extends('filament-panels::layouts.app')

@section('before-content')
    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:px-4 focus:py-2 focus:bg-white focus:text-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:rounded-md"
    >
        Skip to main content
    </a>
@endsection
```

If Filament does not support `@section('before-content')` in the layout extension, use a **render hook** instead. In `app/Providers/Filament/AdminPanelProvider.php`, inside the `panel()` method:

```php
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

->renderHook(
    PanelsRenderHook::BODY_START,
    fn (): string => '<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:px-4 focus:py-2 focus:bg-white focus:text-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:rounded-md">Skip to main content</a>'
)
->renderHook(
    PanelsRenderHook::CONTENT_START,
    fn (): string => '<div id="main-content"></div>'
)
```

Use whichever approach works — the render hook approach is more reliable for Filament 3.

### 3.2 Color Contrast Audit

In `app/Providers/Filament/AdminPanelProvider.php`, update the panel's color configuration to ensure WCAG AA contrast ratios. Add or update the `->colors()` call:

```php
use Filament\Support\Colors\Color;

->colors([
    'primary' => Color::Blue,
    'danger' => Color::Red,
    'gray' => Color::Zinc,
    'info' => Color::Sky,
    'success' => Color::Green,
    'warning' => Color::Amber,
])
```

Then, wherever custom badge colors are used (in `ContractResource`, `WorkflowInstanceResource`, etc.), verify contrast ratios:

- **Risk badges** — use high-contrast text colors:
  - `high` risk: `'danger'` (Filament red on light background, passes AA)
  - `medium` risk: `'warning'` (Filament amber on light background, passes AA)
  - `low` risk: `'success'` (Filament green on light background, passes AA)

- **Workflow state badges** — use Filament's built-in badge colors which pass AA:
  - `draft` → `'gray'`
  - `in_progress` / `in_review` → `'info'`
  - `executed` / `completed` → `'success'`
  - `cancelled` / `expired` → `'danger'`

Search all Resource files for `->badge()->color(` and verify each closure returns a Filament named color (not a raw Tailwind class like `'text-red-400'` which may fail contrast). Example of a compliant pattern:

```php
TextColumn::make('risk_rating')
    ->badge()
    ->color(fn (?string $state): string => match ($state) {
        'high' => 'danger',
        'medium' => 'warning',
        'low' => 'success',
        default => 'gray',
    }),
```

### 3.3 Form Accessibility — Helper Text and Placeholders

In the following Resources, add `->helperText()` to complex fields and `->placeholder()` to search/filter inputs:

**`ContractResource.php`:**
```php
TextInput::make('title')
    ->required()
    ->placeholder('e.g. Master Services Agreement — Acme Corp')
    ->helperText('A descriptive title for this contract.'),

Select::make('contract_type')
    ->required()
    ->placeholder('Select contract type')
    ->helperText('Determines which workflow template will be applied.'),

Select::make('counterparty_id')
    ->relationship('counterparty', 'name')
    ->searchable()
    ->preload()
    ->placeholder('Search for a counterparty...')
    ->helperText('The external party entering into this agreement.'),

Textarea::make('notes')
    ->placeholder('Internal notes about this contract...')
    ->helperText('Visible only to internal users. Not included in generated documents.'),
```

**`CounterpartyResource.php`:**
```php
TextInput::make('name')
    ->required()
    ->placeholder('e.g. Acme Corporation Ltd')
    ->helperText('Legal name of the counterparty as it appears on contracts.'),

TextInput::make('trade_license_number')
    ->placeholder('e.g. TL-2024-12345')
    ->helperText('TiTo-validated trade license number.'),
```

Apply the same pattern to all Resources — at minimum add `->placeholder()` to every `TextInput` and `->helperText()` to any field whose purpose is not immediately obvious from the label alone.

### 3.4 Table Accessibility — Column Descriptions

In Resource tables, add `->description()` to columns where the header alone may be ambiguous:

```php
TextColumn::make('workflow_state')
    ->badge()
    ->description('Current stage in the contract lifecycle'),

TextColumn::make('risk_rating')
    ->badge()
    ->description('AI-assessed risk level based on clause analysis'),

TextColumn::make('signing_status')
    ->badge()
    ->description('BoldSign e-signature status'),
```

### 3.5 Chart Accessibility

If any Dashboard widgets render charts (e.g., `ContractsByStatusChart`, `MonthlyContractsChart`), wrap the chart output in an accessible container and provide a data table fallback.

In each chart widget's Blade view (or in the widget class if using Filament's `ChartWidget`), add:

```php
protected function getExtraBodyAttributes(): array
{
    return [
        'role' => 'img',
        'aria-label' => $this->getAccessibleDescription(),
    ];
}

/**
 * Generate a text description of the chart data for screen readers.
 */
protected function getAccessibleDescription(): string
{
    $data = $this->getData();
    $labels = $data['labels'] ?? [];
    $values = $data['datasets'][0]['data'] ?? [];

    $parts = [];
    foreach ($labels as $i => $label) {
        $parts[] = "{$label}: " . ($values[$i] ?? 0);
    }

    return $this->getHeading() . '. ' . implode(', ', $parts) . '.';
}
```

For a full data table fallback, override the chart widget Blade view to include a visually hidden table below the chart:

```blade
{{-- In resources/views/filament/widgets/chart-with-table.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <div role="img" aria-label="{{ $this->getAccessibleDescription() }}">
            {!! $this->renderChart() !!}
        </div>

        {{-- Screen reader data table fallback --}}
        <div class="sr-only">
            <table aria-label="{{ $this->getHeading() }} data">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->getData()['labels'] ?? [] as $i => $label)
                        <tr>
                            <td>{{ $label }}</td>
                            <td>{{ $this->getData()['datasets'][0]['data'][$i] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

### 3.6 Focus Management and ARIA Roles on Custom Components

**WorkflowBuilder (Prompt F):** In the Blade view for the visual workflow builder component, add ARIA tree roles:

Find the workflow builder's Blade template (likely `resources/views/livewire/workflow-builder.blade.php` or similar) and add:

```html
{{-- On the outer container: --}}
<div role="tree" aria-label="Workflow stages">

{{-- On each stage node: --}}
<div role="treeitem" aria-selected="..." aria-expanded="...">
```

**OrgHierarchyViewer (Task 2):** Already includes `role="tree"` and `role="treeitem"` in the Blade template above.

**Filament modals and notifications:** Filament 3's built-in Action modals already trap focus correctly and notifications use `aria-live="polite"`. No changes needed — just verify by testing with keyboard navigation:

1. Press Tab through the interface — focus ring should be visible on every interactive element.
2. Open a modal (e.g., create contract) — focus should move into the modal, Tab should not leave it.
3. Press Escape — modal closes, focus returns to the trigger button.
4. When a notification appears — it should not steal focus but should be announced by screen readers.

---

## Verification Checklist

### Countersigning Workflow
1. **Create countersign template**: Create a workflow template with stages `[{name: "legal_review", type: "review"}, {name: "countersign", type: "countersign"}]`. Template saves successfully with `countersign` accepted as a valid stage type.
2. **Upload third-party document**: Create a contract, upload a PDF (simulating a partially-signed document from the counterparty). Advance the workflow past legal review to the countersign stage.
3. **Send for Countersigning**: The "Send for Countersigning" action button appears on the contract. Click it — modal shows internal signers pre-populated from `signing_authority`. Submit — BoldSign envelope created with `is_countersign = true`.
4. **Signing authority enforced**: Attempt to advance through the countersign stage as a user **without** a signing authority record — `RuntimeException` thrown.
5. **Countersign + immutability**: Mock BoldSign `DocumentCompleted` webhook for the countersign envelope — contract `workflow_state` set to `executed`, form fields locked.
6. **Feature test passes**: `php artisan test --filter=CountersignWorkflowTest` — all assertions pass.

### Organization Visualization
7. **Page accessible**: Navigate to `/admin/org-visualization` — page loads with the tree on the left and an empty detail panel on the right.
8. **Tree renders**: Tree shows all Regions with collapsible children (Entities, then Projects). Each node shows active contract count and active workflow count.
9. **Node selection**: Click an Entity node — side panel loads signing authorities and workflow templates scoped to that entity.
10. **Workflow template drill-down**: Click a workflow template in the side panel — it expands to show stages with type and owners.
11. **Counterparty filter**: Visit `/admin/org-visualization?counterparty_id={id}` — nodes where that counterparty has active contracts are highlighted with a yellow border.

### WCAG 2.1 AA Accessibility
12. **Skip link**: Press Tab on page load — "Skip to main content" link appears. Pressing Enter scrolls/focuses past the sidebar to the main content area.
13. **Color contrast**: Open the contract list page in Chrome DevTools → Lighthouse → Accessibility audit. No contrast failures on badge colors (risk, workflow state, signing status).
14. **Form accessibility**: Inspect contract create form — all fields have associated `<label>` elements (Filament default), complex fields have helper text visible below the input, required fields have `aria-required="true"`.
15. **Table accessibility**: Inspect contract list table — renders as `<table>` with `<th>` headers. Sortable columns have `aria-sort` attributes when sorted.
16. **Chart accessibility**: If dashboard has charts, inspect the HTML — chart wrapper has `role="img"` with an `aria-label` summarizing the data. A visually hidden `<table>` is present as a data fallback.
17. **Keyboard navigation**: Tab through the entire contract creation flow — every interactive element receives visible focus. Modals trap focus. Escape closes modals.
18. **Lighthouse score**: Run `pa11y http://localhost:8080/admin` or Chrome Lighthouse Accessibility audit on dashboard, contract list, and contract detail pages — no critical WCAG AA failures.
