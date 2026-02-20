<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStageAction;
use App\Models\WorkflowTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    public function startWorkflow(string $contractId, string $templateId, User $actor): WorkflowInstance
    {
        $template = WorkflowTemplate::where('id', $templateId)->where('status', 'published')->firstOrFail();
        $contract = Contract::findOrFail($contractId);

        return DB::transaction(function () use ($contract, $template, $actor) {
            $existing = WorkflowInstance::where('contract_id', $contract->id)
                ->where('state', 'active')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new \RuntimeException('Active workflow already exists for this contract');
            }

            $stages = $template->stages ?? [];
            $firstStage = $stages[0]['name'] ?? 'draft';

            $instance = WorkflowInstance::create([
                'contract_id' => $contract->id,
                'template_id' => $template->id,
                'template_version' => $template->version,
                'current_stage' => $firstStage,
                'state' => 'active',
                'started_at' => now(),
            ]);

            $contract->update(['workflow_state' => $firstStage]);

            AuditService::log('workflow_instance.start', 'workflow_instance', $instance->id, [], $actor);

            return $instance;
        });
    }

    public function recordAction(WorkflowInstance $instance, string $stageName, string $action, User $actor, ?string $comment = null, ?array $artifacts = null): WorkflowStageAction
    {
        if ($instance->current_stage !== $stageName) {
            throw new \RuntimeException("Current stage is {$instance->current_stage}, not {$stageName}");
        }

        $template = $instance->template;
        $stages = $template->stages ?? [];
        $currentIndex = collect($stages)->search(fn ($s) => ($s['name'] ?? '') === $instance->current_stage);

        $stageAction = WorkflowStageAction::create([
            'instance_id' => $instance->id,
            'stage_name' => $stageName,
            'action' => $action,
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'comment' => $comment,
            'artifacts' => $artifacts,
            'created_at' => now(),
        ]);

        $nextStage = $this->resolveNextStage($stages, $currentIndex, $action);

        if ($nextStage === null && $action === 'approve') {
            $instance->update(['state' => 'completed', 'completed_at' => now()]);
            $instance->contract->update(['workflow_state' => 'completed']);
        } elseif ($nextStage !== null) {
            $instance->update(['current_stage' => $nextStage]);
            $instance->contract->update(['workflow_state' => $nextStage]);
        }

        AuditService::log("workflow_stage.{$action}", 'workflow_instance', $instance->id, ['stage' => $stageName], $actor);

        return $stageAction;
    }

    public function getActiveInstance(string $contractId): ?WorkflowInstance
    {
        return WorkflowInstance::where('contract_id', $contractId)->where('state', 'active')->first();
    }

    public function getHistory(string $instanceId): Collection
    {
        return WorkflowStageAction::where('instance_id', $instanceId)->orderBy('created_at')->get();
    }

    private function resolveNextStage(array $stages, int|false $currentIndex, string $action): ?string
    {
        if ($currentIndex === false) return null;
        if ($action === 'reject' || $action === 'rework') {
            return $currentIndex > 0 ? ($stages[$currentIndex - 1]['name'] ?? null) : $stages[$currentIndex]['name'] ?? null;
        }
        $nextIndex = $currentIndex + 1;
        return isset($stages[$nextIndex]) ? ($stages[$nextIndex]['name'] ?? null) : null;
    }
}
