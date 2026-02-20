<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStageAction;
use App\Models\WorkflowTemplate;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    public function startWorkflow(Contract $contract, WorkflowTemplate $template): WorkflowInstance
    {
        return DB::transaction(function () use ($contract, $template) {
            $existing = WorkflowInstance::where('contract_id', $contract->id)
                ->where('state', 'active')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new \RuntimeException('Contract already has an active workflow');
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

            AuditService::log('workflow_started', 'contract', $contract->id, [
                'template_id' => $template->id,
                'stage' => $firstStage,
            ]);

            return $instance;
        });
    }

    public function performAction(WorkflowInstance $instance, string $action, ?string $comment = null): WorkflowInstance
    {
        $template = $instance->template;
        $stages = $template->stages ?? [];
        $currentIndex = collect($stages)->search(fn ($s) => $s['name'] === $instance->current_stage);

        WorkflowStageAction::create([
            'instance_id' => $instance->id,
            'stage_name' => $instance->current_stage,
            'action' => $action,
            'actor_id' => auth()->id(),
            'actor_email' => auth()->user()?->email,
            'comment' => $comment,
            'created_at' => now(),
        ]);

        $nextStage = $this->resolveNextStage($stages, $currentIndex, $action);

        if ($nextStage === null) {
            $instance->update(['state' => 'completed', 'completed_at' => now()]);
            $newState = 'executed';
            $instance->contract->update(['workflow_state' => $newState]);
        } else {
            $newState = $nextStage;
            $instance->update(['current_stage' => $nextStage]);
            $instance->contract->update(['workflow_state' => $nextStage]);
        }

        AuditService::log("workflow_{$action}", 'contract', $instance->contract_id, [
            'stage' => $instance->current_stage,
            'action' => $action,
        ]);

        if (in_array($newState, ['active', 'executed'])) {
            $contract = $instance->contract->fresh('counterparty');
            app(VendorNotificationService::class)->notifyContractStatusChange($contract, $newState);
        }

        return $instance->fresh();
    }

    private function resolveNextStage(array $stages, int|false $currentIndex, string $action): ?string
    {
        if ($currentIndex === false) return null;

        if ($action === 'reject' || $action === 'rework') {
            return $currentIndex > 0 ? $stages[$currentIndex - 1]['name'] : $stages[$currentIndex]['name'];
        }

        $nextIndex = $currentIndex + 1;
        return isset($stages[$nextIndex]) ? $stages[$nextIndex]['name'] : null;
    }
}
