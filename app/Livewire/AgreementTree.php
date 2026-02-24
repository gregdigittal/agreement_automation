<?php

namespace App\Livewire;

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Jurisdiction;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AgreementTree extends Component
{
    public string $groupBy = 'entity';

    public string $search = '';

    public string $statusFilter = '';

    /** @var array<string, bool> */
    public array $expanded = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $loadedContracts = [];

    public function setGroupBy(string $groupBy): void
    {
        if (in_array($groupBy, ['entity', 'counterparty', 'jurisdiction', 'project'])) {
            $this->groupBy = $groupBy;
            $this->expanded = [];
            $this->loadedContracts = [];
        }
    }

    public function toggleNode(string $nodeId): void
    {
        if (isset($this->expanded[$nodeId])) {
            unset($this->expanded[$nodeId]);
            unset($this->loadedContracts[$nodeId]);
        } else {
            $this->expanded[$nodeId] = true;
        }
    }

    public function loadContracts(string $type, string $id): void
    {
        $nodeKey = "{$type}_{$id}";

        $query = Contract::query()->select(['id', 'title', 'workflow_state', 'contract_type', 'expiry_date']);

        if ($this->statusFilter) {
            $query->where('workflow_state', $this->statusFilter);
        }

        if ($this->search) {
            $query->where('title', 'like', '%' . $this->search . '%');
        }

        switch ($type) {
            case 'entity':
                $query->where('entity_id', $id);
                break;
            case 'counterparty':
                $query->where('counterparty_id', $id);
                break;
            case 'jurisdiction':
                // Jurisdiction -> entities -> contracts
                $entityIds = Entity::whereHas('jurisdictions', fn ($q) => $q->where('jurisdictions.id', $id))
                    ->pluck('id');
                $query->whereIn('entity_id', $entityIds);
                break;
            case 'project':
                $query->where('project_id', $id);
                break;
        }

        $this->loadedContracts[$nodeKey] = $query->orderBy('title')->limit(50)->get()->map(fn (Contract $c) => [
            'id' => $c->id,
            'title' => $c->title,
            'workflow_state' => $c->workflow_state,
            'contract_type' => $c->contract_type,
            'expiry_date' => $c->expiry_date?->format('Y-m-d'),
        ])->toArray();
    }

    #[Computed]
    public function tree(): array
    {
        return match ($this->groupBy) {
            'entity' => $this->buildEntityTree(),
            'counterparty' => $this->buildCounterpartyTree(),
            'jurisdiction' => $this->buildJurisdictionTree(),
            'project' => $this->buildProjectTree(),
            default => [],
        };
    }

    protected function buildEntityTree(): array
    {
        $query = Entity::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('contracts', fn ($cq) => $cq->where('title', 'like', '%' . $this->search . '%'));
            });
        }

        $query->withCount($this->contractCountScopes());

        return $query->orderBy('name')->get()->map(fn (Entity $entity) => [
            'id' => $entity->id,
            'name' => $entity->name,
            'code' => $entity->code ?? '',
            'type' => 'entity',
            'draft_count' => $entity->draft_contracts_count ?? 0,
            'active_count' => $entity->active_contracts_count ?? 0,
            'executed_count' => $entity->executed_contracts_count ?? 0,
            'total_count' => $entity->total_contracts_count ?? 0,
        ])->toArray();
    }

    protected function buildCounterpartyTree(): array
    {
        $query = Counterparty::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('legal_name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('contracts', fn ($cq) => $cq->where('title', 'like', '%' . $this->search . '%'));
            });
        }

        $query->withCount($this->contractCountScopes());

        return $query->orderBy('legal_name')->get()->map(fn (Counterparty $cp) => [
            'id' => $cp->id,
            'name' => $cp->legal_name,
            'code' => $cp->registration_number ?? '',
            'type' => 'counterparty',
            'draft_count' => $cp->draft_contracts_count ?? 0,
            'active_count' => $cp->active_contracts_count ?? 0,
            'executed_count' => $cp->executed_contracts_count ?? 0,
            'total_count' => $cp->total_contracts_count ?? 0,
        ])->toArray();
    }

    protected function buildJurisdictionTree(): array
    {
        $query = Jurisdiction::query()->with([
            'entities' => fn ($q) => $q->withCount($this->contractCountScopes()),
        ]);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('entities', fn ($eq) => $eq->where('name', 'like', '%' . $this->search . '%'));
            });
        }

        return $query->orderBy('name')->get()->map(fn (Jurisdiction $jurisdiction) => [
            'id' => $jurisdiction->id,
            'name' => $jurisdiction->name,
            'code' => $jurisdiction->country_code ?? '',
            'type' => 'jurisdiction',
            'draft_count' => $jurisdiction->entities->sum(fn ($e) => $e->draft_contracts_count ?? 0),
            'active_count' => $jurisdiction->entities->sum(fn ($e) => $e->active_contracts_count ?? 0),
            'executed_count' => $jurisdiction->entities->sum(fn ($e) => $e->executed_contracts_count ?? 0),
            'total_count' => $jurisdiction->entities->sum(fn ($e) => $e->total_contracts_count ?? 0),
            'children' => $jurisdiction->entities->map(fn (Entity $entity) => [
                'id' => $entity->id,
                'name' => $entity->name,
                'code' => $entity->code ?? '',
                'type' => 'entity',
                'draft_count' => $entity->draft_contracts_count ?? 0,
                'active_count' => $entity->active_contracts_count ?? 0,
                'executed_count' => $entity->executed_contracts_count ?? 0,
                'total_count' => $entity->total_contracts_count ?? 0,
            ])->toArray(),
        ])->toArray();
    }

    protected function buildProjectTree(): array
    {
        $query = Project::query()->with('entity');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('entity', fn ($eq) => $eq->where('name', 'like', '%' . $this->search . '%'))
                  ->orWhereHas('contracts', fn ($cq) => $cq->where('title', 'like', '%' . $this->search . '%'));
            });
        }

        $query->withCount($this->contractCountScopes());

        return $query->orderBy('name')->get()->map(fn (Project $project) => [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->code ?? '',
            'type' => 'project',
            'entity_name' => $project->entity?->name ?? 'No Entity',
            'draft_count' => $project->draft_contracts_count ?? 0,
            'active_count' => $project->active_contracts_count ?? 0,
            'executed_count' => $project->executed_contracts_count ?? 0,
            'total_count' => $project->total_contracts_count ?? 0,
        ])->toArray();
    }

    /**
     * Get the withCount scopes for contracts broken down by workflow state.
     */
    protected function contractCountScopes(): array
    {
        $scopes = [
            'contracts as draft_contracts_count' => fn ($q) => $q->where('workflow_state', 'draft'),
            'contracts as active_contracts_count' => fn ($q) => $q->where('workflow_state', 'in_review'),
            'contracts as executed_contracts_count' => fn ($q) => $q->where('workflow_state', 'executed'),
            'contracts as total_contracts_count' => fn ($q) => $q,
        ];

        if ($this->statusFilter) {
            $scopes = [
                'contracts as draft_contracts_count' => fn ($q) => $q->where('workflow_state', 'draft')->where('workflow_state', $this->statusFilter),
                'contracts as active_contracts_count' => fn ($q) => $q->where('workflow_state', 'in_review')->where('workflow_state', $this->statusFilter),
                'contracts as executed_contracts_count' => fn ($q) => $q->where('workflow_state', 'executed')->where('workflow_state', $this->statusFilter),
                'contracts as total_contracts_count' => fn ($q) => $q->where('workflow_state', $this->statusFilter),
            ];
        }

        return $scopes;
    }

    public function render()
    {
        return view('livewire.agreement-tree', [
            'treeData' => $this->tree,
        ]);
    }
}
