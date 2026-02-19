# Cursor Prompt — Laravel Migration Phase F: Visual Workflow Builder + Bulk Upload + Duplicate Detection

## Context

**Run this prompt in the `agreement_automation` repo on the `laravel-migration` branch** — the same branch where Phases A through E were executed.

Phases A–E are complete. This phase implements three UX-focused features:

1. **R4 — Visual Workflow Builder**: Replace the plain Filament Repeater in `WorkflowTemplateResource` with an interactive Livewire component that renders workflow stages as connected cards with drag-and-drop reordering.
2. **R5 — Bulk Contract Upload Pipeline**: A Filament page where an administrator uploads a CSV manifest + a ZIP of contract files, which are queued for background processing (one job per row) with a progress tracking page.
3. **R7 — Counterparty Duplicate Detection**: A server-side check fired when creating or editing a Counterparty — if fuzzy matches exist, a Filament modal presents them before saving, allowing the operator to proceed or link to an existing record.

---

## Task 1: Visual Workflow Builder (R4)

The goal is a drag-and-drop stage editor embedded in `WorkflowTemplateResource`'s form. Stages are stored as a JSON array in `workflow_templates.stages`. Each stage object: `{id, name, role, duration_days, is_approval, order}`.

### 1.1 Create the Livewire Component

Create `app/Livewire/WorkflowBuilder.php`:

```php
<?php

namespace App\Livewire;

use Livewire\Component;

class WorkflowBuilder extends Component
{
    /** @var array<int, array{id: string, name: string, role: string, duration_days: int, is_approval: bool, order: int}> */
    public array $stages = [];

    public string $statePath = '';

    public function mount(array $state = [], string $statePath = ''): void
    {
        $this->stages    = array_values($state ?: []);
        $this->statePath = $statePath;
    }

    public function addStage(): void
    {
        $this->stages[] = [
            'id'            => (string) \Illuminate\Support\Str::uuid(),
            'name'          => 'New Stage',
            'role'          => 'legal',
            'duration_days' => 5,
            'is_approval'   => false,
            'order'         => count($this->stages),
        ];
        $this->syncState();
    }

    public function removeStage(int $index): void
    {
        array_splice($this->stages, $index, 1);
        $this->reorder();
        $this->syncState();
    }

    public function updateStage(int $index, string $field, mixed $value): void
    {
        $this->stages[$index][$field] = $value;
        $this->syncState();
    }

    public function reorderStages(array $orderedIds): void
    {
        $stageMap = collect($this->stages)->keyBy('id');
        $this->stages = collect($orderedIds)
            ->map(fn ($id, $pos) => array_merge($stageMap[$id] ?? [], ['order' => $pos]))
            ->values()
            ->toArray();
        $this->syncState();
    }

    private function reorder(): void
    {
        foreach ($this->stages as $i => $stage) {
            $this->stages[$i]['order'] = $i;
        }
    }

    private function syncState(): void
    {
        // Emit state up to the parent Filament form field
        $this->dispatch('workflow-builder-updated', statePath: $this->statePath, stages: $this->stages);
    }

    public function render()
    {
        return view('livewire.workflow-builder');
    }
}
```

### 1.2 Create the Blade View

Create `resources/views/livewire/workflow-builder.blade.php`:

```blade
<div
    x-data="{
        dragIndex: null,
        dragOver: null,
        startDrag(index) { this.dragIndex = index; },
        onDragOver(index) { this.dragOver = index; },
        endDrag() {
            if (this.dragIndex !== null && this.dragOver !== null && this.dragIndex !== this.dragOver) {
                const ids = Array.from(document.querySelectorAll('[data-stage-id]'))
                    .map(el => el.dataset.stageId);
                // Reorder: move dragIndex to dragOver position
                const moved = ids.splice(this.dragIndex, 1)[0];
                ids.splice(this.dragOver, 0, moved);
                $wire.reorderStages(ids);
            }
            this.dragIndex = null;
            this.dragOver = null;
        }
    }"
    class="space-y-3"
>
    {{-- Stage Cards --}}
    @foreach ($stages as $index => $stage)
        <div
            data-stage-id="{{ $stage['id'] }}"
            draggable="true"
            @dragstart="startDrag({{ $index }})"
            @dragover.prevent="onDragOver({{ $index }})"
            @dragend="endDrag()"
            :class="dragOver === {{ $index }} ? 'ring-2 ring-primary-500' : ''"
            class="flex items-start gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 cursor-grab"
        >
            {{-- Drag handle --}}
            <div class="mt-1 text-gray-400 cursor-grab">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 8h16M4 16h16"/>
                </svg>
            </div>

            {{-- Stage number badge --}}
            <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                {{ $index + 1 }}
            </div>

            {{-- Fields --}}
            <div class="grid flex-1 grid-cols-2 gap-3 sm:grid-cols-4">
                {{-- Stage Name --}}
                <div class="col-span-2 sm:col-span-1">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Stage Name</label>
                    <input
                        type="text"
                        value="{{ $stage['name'] }}"
                        wire:change="updateStage({{ $index }}, 'name', $event.target.value)"
                        class="w-full rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700"
                    />
                </div>

                {{-- Role --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Role</label>
                    <select
                        wire:change="updateStage({{ $index }}, 'role', $event.target.value)"
                        class="w-full rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700"
                    >
                        @foreach (['legal', 'commercial', 'finance', 'operations', 'system_admin'] as $role)
                            <option value="{{ $role }}" {{ $stage['role'] === $role ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $role)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Duration --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Days</label>
                    <input
                        type="number"
                        min="1"
                        value="{{ $stage['duration_days'] }}"
                        wire:change="updateStage({{ $index }}, 'duration_days', (int) $event.target.value)"
                        class="w-full rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700"
                    />
                </div>

                {{-- Is Approval --}}
                <div class="flex items-center gap-2 pt-5">
                    <input
                        type="checkbox"
                        id="approval_{{ $index }}"
                        {{ $stage['is_approval'] ? 'checked' : '' }}
                        wire:click="updateStage({{ $index }}, 'is_approval', !{{ $stage['is_approval'] ? 'true' : 'false' }})"
                        class="h-4 w-4 rounded"
                    />
                    <label for="approval_{{ $index }}" class="text-xs text-gray-600 dark:text-gray-400">Approval</label>
                </div>
            </div>

            {{-- Remove button --}}
            <button
                type="button"
                wire:click="removeStage({{ $index }})"
                class="mt-1 text-red-400 hover:text-red-600"
                title="Remove stage"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endforeach

    {{-- Connecting arrows between stages (simple visual line) --}}
    @if(count($stages) > 1)
        <div class="pl-10 text-xs text-gray-400 dark:text-gray-500 select-none">
            {{ count($stages) }} stages · drag cards to reorder
        </div>
    @endif

    {{-- Add Stage button --}}
    <button
        type="button"
        wire:click="addStage"
        class="flex items-center gap-2 rounded-md border-2 border-dashed border-gray-300 px-4 py-2 text-sm text-gray-500 hover:border-primary-400 hover:text-primary-600 dark:border-gray-600"
    >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Stage
    </button>
</div>
```

### 1.3 Create the Custom Filament Form Component

Create `app/Forms/Components/WorkflowBuilderField.php`:

```php
<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class WorkflowBuilderField extends Field
{
    protected string $view = 'forms.components.workflow-builder-field';

    public function getDefaultState(): mixed
    {
        return [];
    }
}
```

Create `resources/views/forms/components/workflow-builder-field.blade.php`:

```blade
<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @livewire('workflow-builder', [
        'state'     => $getState() ?: [],
        'statePath' => $getStatePath(),
    ])

    {{-- Wire Livewire event back into the Filament form state --}}
    <div
        x-data="{}"
        x-on:workflow-builder-updated.window="
            if ($event.detail.statePath === '{{ $getStatePath() }}') {
                $wire.set('{{ $getStatePath() }}', $event.detail.stages);
            }
        "
    ></div>
</x-dynamic-component>
```

### 1.4 Use in `WorkflowTemplateResource`

In `app/Filament/Resources/WorkflowTemplateResource.php`, replace the existing `Repeater` for stages with:

```php
use App\Forms\Components\WorkflowBuilderField;

// In the form schema:
WorkflowBuilderField::make('stages')
    ->label('Workflow Stages')
    ->columnSpanFull(),
```

---

## Task 2: Bulk Contract Upload Pipeline (R5)

### 2.1 Create the `ProcessContractBatch` Job

Create `app/Jobs/ProcessContractBatch.php`:

```php
<?php

namespace App\Jobs;

use App\Models\BulkUploadRow;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Region;
use App\Models\Entity;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessContractBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly string $bulkUploadRowId,
    ) {}

    public function handle(WorkflowService $workflowService): void
    {
        $row = BulkUploadRow::findOrFail($this->bulkUploadRowId);
        $row->update(['status' => 'processing']);

        try {
            $data = $row->row_data;

            // Resolve FKs by code/registration
            $region = Region::where('code', $data['region_code'])->firstOrFail();
            $entity = Entity::where('code', $data['entity_code'])->where('region_id', $region->id)->firstOrFail();
            $project = Project::where('code', $data['project_code'])->where('entity_id', $entity->id)->firstOrFail();
            $counterparty = Counterparty::where('registration_number', $data['counterparty_registration'])->firstOrFail();

            // Move file from temp upload location to permanent S3 key
            $sourceKey = 'bulk_uploads/files/' . $data['file_path'];
            $destKey   = 'contracts/' . Str::uuid() . '/' . basename($data['file_path']);
            Storage::disk('s3')->copy($sourceKey, $destKey);

            $contract = Contract::create([
                'id'              => Str::uuid()->toString(),
                'title'           => $data['title'],
                'contract_type'   => $data['contract_type'] ?? 'contract',
                'counterparty_id' => $counterparty->id,
                'region_id'       => $region->id,
                'entity_id'       => $entity->id,
                'project_id'      => $project->id,
                'workflow_state'  => 'draft',
                'storage_path'    => $destKey,
                'created_by'      => $row->created_by,
            ]);

            AuditService::log(
                action: 'contract.bulk_created',
                resourceType: 'contract',
                resourceId: $contract->id,
                details: ['bulk_upload_row_id' => $row->id],
            );

            $row->update([
                'status'      => 'completed',
                'contract_id' => $contract->id,
                'error'       => null,
            ]);
        } catch (\Throwable $e) {
            $row->update([
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
            throw $e; // allow retry
        }
    }
}
```

### 2.2 Create the `bulk_upload_rows` Migration

Create `database/migrations/XXXX_create_bulk_upload_rows_table.php` (use `php artisan make:migration create_bulk_upload_rows_table`):

```php
Schema::create('bulk_upload_rows', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('bulk_upload_id', 36)->index();
    $table->unsignedSmallInteger('row_number');
    $table->json('row_data');
    $table->string('status', 20)->default('pending'); // pending|processing|completed|failed
    $table->char('contract_id', 36)->nullable();
    $table->char('created_by', 36)->nullable();
    $table->text('error')->nullable();
    $table->timestamps();
});

Schema::create('bulk_uploads', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('created_by', 36)->nullable();
    $table->string('csv_filename');
    $table->string('zip_filename')->nullable();
    $table->unsignedInteger('total_rows')->default(0);
    $table->unsignedInteger('completed_rows')->default(0);
    $table->unsignedInteger('failed_rows')->default(0);
    $table->string('status', 20)->default('processing'); // processing|completed|partial|failed
    $table->timestamps();
});
```

Create `app/Models/BulkUpload.php` and `app/Models/BulkUploadRow.php` with `HasUuidPrimaryKey` trait and `$fillable` arrays matching the migration columns.

### 2.3 Create `BulkContractUploadPage`

Create `app/Filament/Pages/BulkContractUploadPage.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessContractBatch;
use App\Models\BulkUpload;
use App\Models\BulkUploadRow;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BulkContractUploadPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.bulk-contract-upload';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 90;

    public ?array $data = [];
    public ?string $currentBulkUploadId = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('csv_file')
                    ->label('CSV Manifest')
                    ->acceptedFileTypes(['text/csv', 'application/csv'])
                    ->required()
                    ->helperText('Columns: title, contract_type, region_code, entity_code, project_code, counterparty_registration, file_path'),

                FileUpload::make('zip_file')
                    ->label('Contract Files (ZIP)')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->helperText('ZIP containing the contract files referenced by file_path column'),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $bulkUploadId = Str::uuid()->toString();
        $rows = [];

        // Parse CSV
        $csvPath = Storage::disk('local')->path($data['csv_file']);
        $csvHandle = fopen($csvPath, 'r');
        $headers = fgetcsv($csvHandle);
        $rowNumber = 0;
        while (($line = fgetcsv($csvHandle)) !== false) {
            $rowNumber++;
            $rowData = array_combine($headers, $line);
            $rows[] = [
                'id'             => Str::uuid()->toString(),
                'bulk_upload_id' => $bulkUploadId,
                'row_number'     => $rowNumber,
                'row_data'       => json_encode($rowData),
                'status'         => 'pending',
                'created_by'     => auth()->id(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }
        fclose($csvHandle);

        if (empty($rows)) {
            Notification::make()->title('CSV is empty')->danger()->send();
            return;
        }

        // Upload ZIP files to S3 bulk_uploads/files/ prefix if provided
        if (! empty($data['zip_file'])) {
            $zipPath = Storage::disk('local')->path($data['zip_file']);
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $contents = $zip->getFromIndex($i);
                    Storage::disk('s3')->put('bulk_uploads/files/' . $filename, $contents);
                }
                $zip->close();
            }
        }

        // Create BulkUpload record
        BulkUpload::create([
            'id'           => $bulkUploadId,
            'created_by'   => auth()->id(),
            'csv_filename' => basename($data['csv_file']),
            'zip_filename' => $data['zip_file'] ? basename($data['zip_file']) : null,
            'total_rows'   => $rowNumber,
            'status'       => 'processing',
        ]);

        // Insert rows and dispatch jobs
        BulkUploadRow::insert($rows);
        foreach ($rows as $row) {
            ProcessContractBatch::dispatch($row['id'])->onQueue('default');
        }

        $this->currentBulkUploadId = $bulkUploadId;

        Notification::make()
            ->title('Upload started')
            ->body("{$rowNumber} contracts queued for processing.")
            ->success()
            ->send();

        $this->form->fill();
    }

    public function getProgressData(): array
    {
        if (! $this->currentBulkUploadId) return [];

        $rows = BulkUploadRow::where('bulk_upload_id', $this->currentBulkUploadId)->get();

        return [
            'total'      => $rows->count(),
            'completed'  => $rows->where('status', 'completed')->count(),
            'failed'     => $rows->where('status', 'failed')->count(),
            'processing' => $rows->where('status', 'processing')->count(),
            'pending'    => $rows->where('status', 'pending')->count(),
            'rows'       => $rows->map(fn ($r) => [
                'row_number'  => $r->row_number,
                'status'      => $r->status,
                'error'       => $r->error,
                'contract_id' => $r->contract_id,
            ])->toArray(),
        ];
    }
}
```

### 2.4 Create the Blade View

Create `resources/views/filament/pages/bulk-contract-upload.blade.php`:

```blade
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Upload Contracts in Bulk</x-slot>
        <x-slot name="description">
            Upload a CSV manifest and an optional ZIP of contract files. Each row will be queued as a separate job.
        </x-slot>

        <form wire:submit="submit" class="space-y-6">
            {{ $this->form }}

            <x-filament::button type="submit" color="primary">
                Start Upload
            </x-filament::button>
        </form>
    </x-filament::section>

    @if ($currentBulkUploadId)
        @php $progress = $this->getProgressData(); @endphp
        <x-filament::section class="mt-6">
            <x-slot name="heading">Progress — Bulk Upload {{ $currentBulkUploadId }}</x-slot>

            <div class="grid grid-cols-4 gap-4 mb-4">
                <div class="rounded-lg bg-gray-100 p-3 text-center dark:bg-gray-700">
                    <div class="text-2xl font-bold">{{ $progress['total'] }}</div>
                    <div class="text-xs text-gray-500">Total</div>
                </div>
                <div class="rounded-lg bg-green-100 p-3 text-center dark:bg-green-900">
                    <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ $progress['completed'] }}</div>
                    <div class="text-xs text-gray-500">Completed</div>
                </div>
                <div class="rounded-lg bg-red-100 p-3 text-center dark:bg-red-900">
                    <div class="text-2xl font-bold text-red-700 dark:text-red-300">{{ $progress['failed'] }}</div>
                    <div class="text-xs text-gray-500">Failed</div>
                </div>
                <div class="rounded-lg bg-yellow-100 p-3 text-center dark:bg-yellow-900">
                    <div class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">{{ $progress['pending'] + $progress['processing'] }}</div>
                    <div class="text-xs text-gray-500">Pending</div>
                </div>
            </div>

            <div wire:poll.2s class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs text-gray-500">
                            <th class="py-1 pr-4">Row</th>
                            <th class="py-1 pr-4">Status</th>
                            <th class="py-1 pr-4">Contract ID</th>
                            <th class="py-1">Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($progress['rows'] as $row)
                            <tr class="border-b last:border-0">
                                <td class="py-1 pr-4">{{ $row['row_number'] }}</td>
                                <td class="py-1 pr-4">
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium
                                        {{ $row['status'] === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $row['status'] === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                        {{ in_array($row['status'], ['pending', 'processing']) ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    ">{{ $row['status'] }}</span>
                                </td>
                                <td class="py-1 pr-4 font-mono text-xs">{{ $row['contract_id'] ?? '—' }}</td>
                                <td class="py-1 text-red-600 text-xs">{{ $row['error'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
```

### 2.5 Register the Page

In `app/Providers/Filament/AdminPanelProvider.php`, add `BulkContractUploadPage::class` to the `->pages([...])` array.

---

## Task 3: Counterparty Duplicate Detection (R7)

### 3.1 Add Duplicate Detection to `CounterpartyService`

In `app/Services/CounterpartyService.php`, add:

```php
/**
 * Find counterparties that are likely duplicates of the given candidate.
 * Uses exact registration_number match and trigram-like LIKE search on legal_name.
 * Returns up to 5 matches excluding the given $excludeId.
 *
 * @return \Illuminate\Database\Eloquent\Collection<int, Counterparty>
 */
public function findDuplicates(string $legalName, string $registrationNumber, ?string $excludeId = null): \Illuminate\Database\Eloquent\Collection
{
    $query = Counterparty::query()
        ->where(function ($q) use ($legalName, $registrationNumber) {
            // Exact registration match (highest confidence)
            $q->where('registration_number', $registrationNumber)
              // OR legal name contains first 6 chars (approximate fuzzy)
              ->orWhere('legal_name', 'LIKE', '%' . substr(trim($legalName), 0, 6) . '%');
        })
        ->where('status', '!=', 'blacklisted')
        ->limit(5);

    if ($excludeId) {
        $query->where('id', '!=', $excludeId);
    }

    return $query->get(['id', 'legal_name', 'registration_number', 'status', 'country_of_incorporation']);
}
```

### 3.2 Add `CheckDuplicates` Livewire Action to `CounterpartyResource`

In `app/Filament/Resources/CounterpartyResource.php`, in the `form()` method, add a `Actions\Action` that triggers before save. Use a `before-create` / `before-save` Filament lifecycle hook:

```php
use App\Services\CounterpartyService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;

// Add a hidden field to track duplicate acknowledgment
\Filament\Forms\Components\Hidden::make('duplicate_acknowledged')
    ->default(false),

// Add a form Action button that checks for duplicates
\Filament\Forms\Components\Actions::make([
    Action::make('check_duplicates')
        ->label('Check for Duplicates')
        ->color('warning')
        ->icon('heroicon-o-magnifying-glass')
        ->action(function (array $data, \Filament\Forms\Set $set) {
            $duplicates = app(CounterpartyService::class)->findDuplicates(
                $data['legal_name'] ?? '',
                $data['registration_number'] ?? '',
                $data['id'] ?? null,
            );

            if ($duplicates->isEmpty()) {
                \Filament\Notifications\Notification::make()
                    ->title('No duplicates found')
                    ->success()
                    ->send();
                $set('duplicate_acknowledged', true);
                return;
            }

            // Build modal body listing duplicates
            $list = $duplicates->map(fn ($d) =>
                "• {$d->legal_name} ({$d->registration_number}) — {$d->status}"
            )->implode("\n");

            \Filament\Notifications\Notification::make()
                ->title('Possible duplicates found')
                ->body("The following existing counterparties may be duplicates:\n\n{$list}\n\nIf you proceed, a new record will be created. Consider linking to an existing record instead.")
                ->warning()
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('acknowledge')
                        ->label('Proceed anyway')
                        ->button()
                        ->close()
                        ->action(fn () => $set('duplicate_acknowledged', true)),
                ])
                ->send();
        }),
]),
```

### 3.3 Guard `CreateCounterparty` and `EditCounterparty` with Duplicate Check

Override the `mutateFormDataBeforeCreate` and `mutateFormDataBeforeSave` hooks in the `CreateCounterparty` and `EditCounterparty` pages of `CounterpartyResource`:

```php
// In CounterpartyResource/Pages/CreateCounterparty.php:

protected function mutateFormDataBeforeCreate(array $data): array
{
    $duplicates = app(CounterpartyService::class)->findDuplicates(
        $data['legal_name'] ?? '',
        $data['registration_number'] ?? '',
    );

    if ($duplicates->isNotEmpty() && ! ($data['duplicate_acknowledged'] ?? false)) {
        // Stop and send a warning notification
        \Filament\Notifications\Notification::make()
            ->title('Duplicate check required')
            ->body('Please use the "Check for Duplicates" button and acknowledge before saving.')
            ->warning()
            ->send();

        $this->halt(); // Halt Filament's create lifecycle
    }

    unset($data['duplicate_acknowledged']); // don't persist this field
    return $data;
}
```

Apply the same pattern in `EditCounterparty.php` using `mutateFormDataBeforeSave`.

---

## Verification Checklist

After completing all tasks, verify the following:

1. **Visual Workflow Builder:**
   - Navigate to Workflow Templates → Create or edit a template.
   - The Stages field renders as interactive cards, not a plain repeater.
   - Click "Add Stage" — a new card appears.
   - Drag cards to reorder — order is preserved when you save.
   - Edit stage name, role, days, and approval toggle — values are saved to the `stages` JSON column.

2. **Bulk Contract Upload:**
   - Navigate to Administration → Bulk Contract Upload.
   - Upload a test CSV with 3 rows (with matching regions/entities/counterparties already in the DB).
   - Confirm the progress table appears and rows transition from `pending` → `processing` → `completed`.
   - Confirm 3 new contracts appear in the Contracts table.
   - Confirm a failed row (e.g., unknown region code) shows the error message in the progress table.
   - `php artisan test --filter=ProcessContractBatchTest` passes.

3. **Duplicate Detection:**
   - Navigate to Counterparties → Create.
   - Enter a `legal_name` that partially matches an existing counterparty.
   - Click "Check for Duplicates" — a persistent warning notification lists the matching counterparties.
   - Click "Proceed anyway" — notification closes; save succeeds.
   - If you skip the check and attempt to save with a registration number matching an existing counterparty, you are blocked with a warning.
