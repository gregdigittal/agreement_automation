<?php

namespace App\Livewire;

use App\Models\Contract;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\SigningAuthority;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTemplate;
use Livewire\Component;

class OrgHierarchyViewer extends Component
{
    public ?string $selectedNodeType = null;
    public ?string $selectedNodeId = null;
    public ?string $counterpartyId = null;

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

    public function selectNode(string $type, string $id): void
    {
        $this->selectedNodeType = $type;
        $this->selectedNodeId = $id;

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
            'role' => $auth->role_or_name ?? '',
            'contract_type_pattern' => $auth->contract_type_pattern ?? '*',
        ])->toArray();

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
        } elseif ($type === 'region') {
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
        return WorkflowInstance::where('state', 'active')
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

        return Contract::query()
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
