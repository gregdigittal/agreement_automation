<?php

namespace App\Livewire;

use App\Helpers\StorageHelper;
use App\Models\Contract;
use App\Models\Entity;
use App\Models\EntityShareholding;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class OrganisationStructureViewer extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    /**
     * Maximum number of entities to load, matching AgreementTree::TREE_NODE_LIMIT.
     */
    private const ENTITY_LIMIT = 200;

    public ?string $selectedEntityId = null;
    public bool $showContractsModal = false;

    // Shareholding editor state (Step D + Item 10)
    public ?string $editingShareholdingId = null;
    public ?float $editingPercentage = null;
    public ?string $editingOwnershipType = null;
    public ?string $editingEffectiveDate = null;
    public ?string $editingNotes = null;

    // New shareholding creation state (Item 11)
    public ?string $newShareholdingOwnerId = null;
    public ?string $newShareholdingOwnedId = null;
    public ?float $newShareholdingPercentage = null;
    public ?string $newShareholdingOwnershipType = 'direct';
    public bool $showNewShareholdingForm = false;

    /**
     * Build the flat node + link structure for the D3.js tree.
     *
     * Nodes are entities with contract counts (excluding staging/cancelled/expired).
     * Links include both parent-child hierarchy and shareholding connections.
     * The tree is assembled client-side from this flat payload.
     */
    /**
     * Cache key for tree data — invalidated when entities or shareholdings change.
     */
    private const TREE_CACHE_KEY = 'org_structure_tree_data';

    private const TREE_CACHE_TTL = 300; // 5 minutes

    public function getTreeDataProperty(): array
    {
        return Cache::remember(self::TREE_CACHE_KEY, self::TREE_CACHE_TTL, function () {
            return $this->buildTreeData();
        });
    }

    /**
     * Build the flat node + link structure for the D3.js tree.
     * Items 5 & 12: includes project counts and is cached.
     */
    private function buildTreeData(): array
    {
        $entities = Entity::with(['region:id,name'])
            ->withCount([
                'contracts as active_contracts_count' => fn ($q) => $q->whereNotIn('workflow_state', ['staging', 'cancelled', 'expired']),
                'projects as active_projects_count',
            ])
            ->limit(self::ENTITY_LIMIT)
            ->get();

        $entityIds = $entities->pluck('id')->toArray();

        $nodes = $entities->map(fn (Entity $entity) => [
            'id' => $entity->id,
            'name' => $entity->name,
            'code' => $entity->code ?? '',
            'legal_name' => $entity->legal_name ?? '',
            'registration_number' => $entity->registration_number ?? '',
            'registered_address' => $entity->registered_address ?? '',
            'parent_entity_id' => $entity->parent_entity_id,
            'active_contracts' => $entity->active_contracts_count,
            'active_projects' => $entity->active_projects_count,
            'region_id' => $entity->region_id,
            'region_name' => $entity->region?->name ?? '',
        ])->toArray();

        $links = [];

        // Parent-child links from entity hierarchy
        foreach ($entities as $entity) {
            if ($entity->parent_entity_id && in_array($entity->parent_entity_id, $entityIds)) {
                $links[] = [
                    'source' => $entity->parent_entity_id,
                    'target' => $entity->id,
                    'type' => 'parent_child',
                ];
            }
        }

        // Shareholding links — include all fields for editing (Item 10, 11)
        $shareholdings = EntityShareholding::whereIn('owner_entity_id', $entityIds)
            ->whereIn('owned_entity_id', $entityIds)
            ->get();

        foreach ($shareholdings as $sh) {
            $links[] = [
                'source' => $sh->owner_entity_id,
                'target' => $sh->owned_entity_id,
                'type' => 'shareholding',
                'percentage' => (float) $sh->percentage,
                'ownership_type' => $sh->ownership_type,
                'shareholding_id' => $sh->id,
            ];
        }

        // Track parent-child pairs that lack a shareholding record (Item 11)
        $shareholdingPairs = $shareholdings->map(fn ($sh) => $sh->owner_entity_id.':'.$sh->owned_entity_id)->toArray();
        $parentChildWithoutShareholding = [];

        foreach ($entities as $entity) {
            if ($entity->parent_entity_id && in_array($entity->parent_entity_id, $entityIds)) {
                $pair = $entity->parent_entity_id.':'.$entity->id;
                if (! in_array($pair, $shareholdingPairs)) {
                    $parentChildWithoutShareholding[] = [
                        'owner_entity_id' => $entity->parent_entity_id,
                        'owned_entity_id' => $entity->id,
                    ];
                }
            }
        }

        return [
            'nodes' => $nodes,
            'links' => $links,
            'truncated' => $entities->count() >= self::ENTITY_LIMIT,
            'missing_shareholdings' => $parentChildWithoutShareholding,
        ];
    }

    /**
     * Flush the tree data cache (Item 12).
     * Called after shareholding save/delete and can be called from Entity observers.
     */
    public static function flushTreeCache(): void
    {
        Cache::forget(self::TREE_CACHE_KEY);
    }

    /**
     * Called from D3.js when a user clicks an entity node.
     */
    public function selectEntity(string $entityId): void
    {
        $this->selectedEntityId = $entityId;
    }

    /**
     * Open the contracts modal for the selected entity.
     */
    public function openContractsModal(string $entityId): void
    {
        $this->selectedEntityId = $entityId;
        $this->showContractsModal = true;
    }

    /**
     * Close the contracts modal.
     */
    public function closeContractsModal(): void
    {
        $this->showContractsModal = false;
    }

    /* ----------------------------------------------------------------
     *  Filament table: contracts for the selected entity (Step E)
     *
     *  Replicates the is_restricted security filter from
     *  ContractResource::getEloquentQuery() so non-system_admin users
     *  only see contracts they are authorised to view.
     * -------------------------------------------------------------- */

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                // When no entity is selected, return an empty query
                if (! $this->selectedEntityId) {
                    return Contract::query()->whereRaw('1 = 0');
                }

                $query = Contract::query()
                    ->with(['counterparty', 'region', 'entity', 'secondEntity', 'project', 'governingLaw'])
                    ->where(function (Builder $q) {
                        $q->where('entity_id', $this->selectedEntityId)
                            ->orWhere('second_entity_id', $this->selectedEntityId);
                    })
                    ->whereNotIn('workflow_state', ['staging', 'cancelled', 'expired']);

                // Replicate is_restricted security filter from ContractResource
                $user = auth()->user();
                if ($user && ! $user->hasRole('system_admin')) {
                    $userId = $user->id;
                    $query->where(function (Builder $q) use ($userId) {
                        $q->where('is_restricted', false)
                            ->orWhereHas('authorizedUsers', fn (Builder $sub) => $sub->where('users.id', $userId));
                    });
                }

                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('contract_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('workflow_state')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'gray',
                        'review' => 'warning',
                        'approval' => 'info',
                        'signing' => 'primary',
                        'countersign' => 'warning',
                        'executed' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('counterparty.legal_name')
                    ->label('Counterparty')
                    ->sortable()
                    ->limit(30)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('secondEntity.name')
                    ->label('Second Entity')
                    ->sortable()
                    ->limit(25)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->sortable()
                    ->limit(25)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('governingLaw.name')
                    ->label('Governing Law')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('signing_status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'unsigned' => 'gray',
                        'pending' => 'warning',
                        'partially_signed' => 'info',
                        'signed' => 'success',
                        'declined' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('region.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->date()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('contract_type')
                    ->options(\App\Models\ContractType::options()),
                Tables\Filters\SelectFilter::make('workflow_state')
                    ->options([
                        'draft' => 'Draft',
                        'review' => 'Review',
                        'approval' => 'Approval',
                        'signing' => 'Signing',
                        'countersign' => 'Countersign',
                        'executed' => 'Executed',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\Filter::make('expiry_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('expiry_from')->label('Expiry from'),
                        \Filament\Forms\Components\DatePicker::make('expiry_until')->label('Expiry until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['expiry_from'], fn (Builder $q, $date) => $q->whereDate('expiry_date', '>=', $date))
                            ->when($data['expiry_until'], fn (Builder $q, $date) => $q->whereDate('expiry_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['expiry_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Expiry from '.\Carbon\Carbon::parse($data['expiry_from'])->toFormattedDateString());
                        }
                        if ($data['expiry_until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Expiry until '.\Carbon\Carbon::parse($data['expiry_until'])->toFormattedDateString());
                        }

                        return $indicators;
                    }),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')->label('Created from'),
                        \Filament\Forms\Components\DatePicker::make('created_until')->label('Created until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Created from '.\Carbon\Carbon::parse($data['created_from'])->toFormattedDateString());
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Created until '.\Carbon\Carbon::parse($data['created_until'])->toFormattedDateString());
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Contract $record) => route('filament.admin.resources.contracts.edit', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Contract $record) => filled($record->storage_path))
                    ->url(fn (Contract $record) => route('contract.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No contracts')
            ->emptyStateDescription('This entity has no active contracts.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * Summary statistics for the contracts modal (Item 7).
     */
    public function getContractStatsProperty(): array
    {
        if (! $this->selectedEntityId) {
            return ['total' => 0, 'by_type' => [], 'by_state' => []];
        }

        $query = Contract::query()
            ->where(function (Builder $q) {
                $q->where('entity_id', $this->selectedEntityId)
                    ->orWhere('second_entity_id', $this->selectedEntityId);
            })
            ->whereNotIn('workflow_state', ['staging', 'cancelled', 'expired']);

        // Apply same security filter
        $user = auth()->user();
        if ($user && ! $user->hasRole('system_admin')) {
            $userId = $user->id;
            $query->where(function (Builder $q) use ($userId) {
                $q->where('is_restricted', false)
                    ->orWhereHas('authorizedUsers', fn (Builder $sub) => $sub->where('users.id', $userId));
            });
        }

        $contracts = $query->get(['contract_type', 'workflow_state']);

        return [
            'total' => $contracts->count(),
            'by_type' => $contracts->groupBy('contract_type')->map->count()->toArray(),
            'by_state' => $contracts->groupBy('workflow_state')->map->count()->toArray(),
        ];
    }

    /* ----------------------------------------------------------------
     *  Shareholding inline editing (Step D)
     *  Only system_admin may edit shareholdings from the tree view.
     * -------------------------------------------------------------- */

    /**
     * Load a shareholding record into the editor state.
     */
    public function editShareholding(string $shareholdingId): void
    {
        if (! $this->canEditShareholdings()) {
            return;
        }

        $sh = EntityShareholding::find($shareholdingId);
        if (! $sh) {
            return;
        }

        $this->editingShareholdingId = $sh->id;
        $this->editingPercentage = (float) $sh->percentage;
        $this->editingOwnershipType = $sh->ownership_type;
        $this->editingEffectiveDate = $sh->effective_date?->format('Y-m-d');
        $this->editingNotes = $sh->notes;
    }

    /**
     * Save the currently edited shareholding (Item 10: includes effective_date + notes).
     */
    public function saveShareholding(): void
    {
        if (! $this->canEditShareholdings() || ! $this->editingShareholdingId) {
            return;
        }

        $sh = EntityShareholding::find($this->editingShareholdingId);
        if (! $sh) {
            $this->cancelEditShareholding();

            return;
        }

        // Validate percentage range
        $percentage = max(0.01, min(100, (float) $this->editingPercentage));

        // Validate total won't exceed 100% for the owned entity
        $existingSum = EntityShareholding::where('owned_entity_id', $sh->owned_entity_id)
            ->where('id', '!=', $sh->id)
            ->sum('percentage');

        if (($existingSum + $percentage) > 100) {
            $remaining = round(100 - $existingSum, 2);
            $this->dispatch('notify', type: 'warning', message: "Total shareholding would exceed 100%. Maximum: {$remaining}%.");

            return;
        }

        $sh->update([
            'percentage' => $percentage,
            'ownership_type' => in_array($this->editingOwnershipType, ['direct', 'indirect', 'beneficial', 'nominee'])
                ? $this->editingOwnershipType
                : $sh->ownership_type,
            'effective_date' => $this->editingEffectiveDate ?: null,
            'notes' => $this->editingNotes ?: null,
        ]);

        self::flushTreeCache();
        $this->cancelEditShareholding();
        $this->dispatch('shareholding-updated');
    }

    /**
     * Delete the currently edited shareholding.
     */
    public function deleteShareholding(): void
    {
        if (! $this->canEditShareholdings() || ! $this->editingShareholdingId) {
            return;
        }

        $sh = EntityShareholding::find($this->editingShareholdingId);
        if ($sh) {
            $sh->delete();
        }

        self::flushTreeCache();
        $this->cancelEditShareholding();
        $this->dispatch('shareholding-updated');
    }

    /**
     * Cancel the editing state (Item 10: clears effective_date + notes too).
     */
    public function cancelEditShareholding(): void
    {
        $this->editingShareholdingId = null;
        $this->editingPercentage = null;
        $this->editingOwnershipType = null;
        $this->editingEffectiveDate = null;
        $this->editingNotes = null;
    }

    /* ----------------------------------------------------------------
     *  New shareholding creation (Item 11)
     * -------------------------------------------------------------- */

    /**
     * Open the "Add Shareholding" form for a parent-child pair
     * that doesn't yet have a shareholding record.
     */
    public function openNewShareholdingForm(string $ownerId, string $ownedId): void
    {
        if (! $this->canEditShareholdings()) {
            return;
        }

        $this->newShareholdingOwnerId = $ownerId;
        $this->newShareholdingOwnedId = $ownedId;
        $this->newShareholdingPercentage = 100.00;
        $this->newShareholdingOwnershipType = 'direct';
        $this->showNewShareholdingForm = true;
    }

    /**
     * Create the new shareholding record.
     */
    public function createShareholding(): void
    {
        if (! $this->canEditShareholdings() || ! $this->newShareholdingOwnerId || ! $this->newShareholdingOwnedId) {
            return;
        }

        // Self-ownership check
        if ($this->newShareholdingOwnerId === $this->newShareholdingOwnedId) {
            $this->dispatch('notify', type: 'danger', message: 'An entity cannot own shares in itself.');

            return;
        }

        $percentage = max(0.01, min(100, (float) $this->newShareholdingPercentage));

        // Validate total won't exceed 100%
        $existingSum = EntityShareholding::where('owned_entity_id', $this->newShareholdingOwnedId)
            ->where('ownership_type', 'direct')
            ->sum('percentage');

        if ($this->newShareholdingOwnershipType === 'direct' && ($existingSum + $percentage) > 100) {
            $remaining = round(100 - $existingSum, 2);
            $this->dispatch('notify', type: 'warning', message: "Total direct shareholding would exceed 100%. Maximum: {$remaining}%.");

            return;
        }

        // Check for duplicate
        $exists = EntityShareholding::where('owner_entity_id', $this->newShareholdingOwnerId)
            ->where('owned_entity_id', $this->newShareholdingOwnedId)
            ->where('ownership_type', $this->newShareholdingOwnershipType)
            ->exists();

        if ($exists) {
            $this->dispatch('notify', type: 'warning', message: 'A shareholding with this owner, owned entity, and type already exists.');

            return;
        }

        EntityShareholding::create([
            'owner_entity_id' => $this->newShareholdingOwnerId,
            'owned_entity_id' => $this->newShareholdingOwnedId,
            'percentage' => $percentage,
            'ownership_type' => in_array($this->newShareholdingOwnershipType, ['direct', 'indirect', 'beneficial', 'nominee'])
                ? $this->newShareholdingOwnershipType
                : 'direct',
        ]);

        self::flushTreeCache();
        $this->cancelNewShareholding();
        $this->dispatch('shareholding-updated');
    }

    /**
     * Cancel the new shareholding form.
     */
    public function cancelNewShareholding(): void
    {
        $this->showNewShareholdingForm = false;
        $this->newShareholdingOwnerId = null;
        $this->newShareholdingOwnedId = null;
        $this->newShareholdingPercentage = null;
        $this->newShareholdingOwnershipType = 'direct';
    }

    /**
     * Check if the current user can edit shareholdings.
     */
    public function canEditShareholdings(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public function render()
    {
        return view('livewire.organisation-structure-viewer', [
            'treeData' => $this->treeData,
        ]);
    }
}
