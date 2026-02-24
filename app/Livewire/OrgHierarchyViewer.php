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

        // Batch-load active workflow counts to avoid N+1 queries
        $workflowCounts = $this->batchActiveWorkflowCounts($regions);

        // Batch-load counterparty highlights to avoid N+1 queries
        $highlights = $this->batchHighlights($regions);

        return $regions->map(fn (Region $region) => [
            'id' => $region->id,
            'type' => 'region',
            'name' => $region->name,
            'code' => $region->code ?? '',
            'active_contracts' => $region->active_contracts_count,
            'active_workflows' => $workflowCounts['region'][$region->id] ?? 0,
            'highlighted' => $highlights['region'][$region->id] ?? false,
            'children' => $region->entities->map(fn (Entity $entity) => [
                'id' => $entity->id,
                'type' => 'entity',
                'name' => $entity->name,
                'code' => $entity->code ?? '',
                'active_contracts' => $entity->active_contracts_count,
                'active_workflows' => $workflowCounts['entity'][$entity->id] ?? 0,
                'highlighted' => $highlights['entity'][$entity->id] ?? false,
                'children' => $entity->projects->map(fn (Project $project) => [
                    'id' => $project->id,
                    'type' => 'project',
                    'name' => $project->name,
                    'code' => $project->code ?? '',
                    'active_contracts' => $project->active_contracts_count,
                    'active_workflows' => $workflowCounts['project'][$project->id] ?? 0,
                    'highlighted' => $highlights['project'][$project->id] ?? false,
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
        $project = null;
        $region = null;
        $entityIds = [];

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
            $templateQuery->where(function ($q) use ($id, $project) {
                $q->where('project_id', $id)
                  ->orWhere('entity_id', $project?->entity_id)
                  ->orWhereNull('entity_id');
            });
        } elseif ($type === 'region') {
            if (empty($entityIds) && $region === null) {
                $region = Region::with('entities')->find($id);
                $entityIds = $region?->entities->pluck('id')->toArray() ?? [];
            }
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

    private function batchActiveWorkflowCounts($regions): array
    {
        $counts = ['region' => [], 'entity' => [], 'project' => []];

        // Get all active workflow instances with their contract's entity_id and project_id
        $activeWorkflows = WorkflowInstance::where('state', 'active')
            ->with('contract:id,entity_id,project_id')
            ->get();

        // Build entity→region map
        $entityRegionMap = [];
        foreach ($regions as $region) {
            foreach ($region->entities as $entity) {
                $entityRegionMap[$entity->id] = $region->id;
            }
        }

        foreach ($activeWorkflows as $wi) {
            $contract = $wi->contract;
            if (!$contract) {
                continue;
            }

            // Count by project
            if ($contract->project_id) {
                $counts['project'][$contract->project_id] = ($counts['project'][$contract->project_id] ?? 0) + 1;
            }

            // Count by entity
            if ($contract->entity_id) {
                $counts['entity'][$contract->entity_id] = ($counts['entity'][$contract->entity_id] ?? 0) + 1;

                // Count by region (via entity)
                $regionId = $entityRegionMap[$contract->entity_id] ?? null;
                if ($regionId) {
                    $counts['region'][$regionId] = ($counts['region'][$regionId] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    private function batchHighlights($regions): array
    {
        $highlights = ['region' => [], 'entity' => [], 'project' => []];

        if (!$this->counterpartyId) {
            return $highlights;
        }

        // Single query: get all active contracts for this counterparty
        $contracts = Contract::where('counterparty_id', $this->counterpartyId)
            ->whereNotIn('workflow_state', ['cancelled', 'expired'])
            ->select('entity_id', 'project_id')
            ->get();

        // Build entity→region map
        $entityRegionMap = [];
        foreach ($regions as $region) {
            foreach ($region->entities as $entity) {
                $entityRegionMap[$entity->id] = $region->id;
            }
        }

        foreach ($contracts as $contract) {
            if ($contract->project_id) {
                $highlights['project'][$contract->project_id] = true;
            }
            if ($contract->entity_id) {
                $highlights['entity'][$contract->entity_id] = true;
                $regionId = $entityRegionMap[$contract->entity_id] ?? null;
                if ($regionId) {
                    $highlights['region'][$regionId] = true;
                }
            }
        }

        return $highlights;
    }

    public function render()
    {
        return view('livewire.org-hierarchy-viewer', [
            'tree' => $this->tree,
        ]);
    }
}
