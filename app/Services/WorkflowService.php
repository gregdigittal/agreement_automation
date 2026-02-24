<?php

namespace App\Services;

use App\Helpers\Feature;
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
        $currentStageConfig = collect($stages)->firstWhere('name', $stageName);

        // Signing/countersign stage validation.
        // Note: The actual e-signing is handled externally:
        //   - In-house signing (default): via SigningService + Filament "Send for Signing" action
        //   - BoldSign (legacy): via BoldsignService + Filament "Send for Countersigning" action
        // This method only validates signing authority and KYC readiness; it does NOT
        // auto-trigger any signing service. See Feature::inHouseSigning().
        if ($currentStageConfig && in_array($currentStageConfig['type'] ?? null, ['signing', 'countersign'])) {
            $this->checkSigningAuthority($instance->contract, $actor, $stageName);
        }

        // KYC signing gate check
        if ($currentStageConfig && in_array($currentStageConfig['type'] ?? null, ['signing', 'countersign'])) {
            $kycService = app(\App\Services\KycService::class);
            if (!$kycService->isReadyForSigning($instance->contract)) {
                $missing = $kycService->getMissingItems($instance->contract);
                throw new \RuntimeException(
                    "KYC pack incomplete. {$missing->count()} required items pending: " .
                    $missing->pluck('label')->implode(', ')
                );
            }
        }

        $currentIndex = collect($stages)->search(fn ($s) => ($s['name'] ?? '') === $instance->current_stage);

        return DB::transaction(function () use ($instance, $stageName, $action, $actor, $comment, $artifacts, $stages, $currentIndex) {
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
                app(\App\Services\VendorNotificationService::class)->notifyContractStatusChange($instance->contract, 'executed');
            } elseif ($nextStage !== null) {
                $instance->update(['current_stage' => $nextStage]);
                $instance->contract->update(['workflow_state' => $nextStage]);
                if (in_array($nextStage, ['signing', 'countersign', 'executed'])) {
                    app(\App\Services\VendorNotificationService::class)->notifyContractStatusChange($instance->contract, $nextStage);
                }
            }

            AuditService::log("workflow_stage.{$action}", 'workflow_instance', $instance->id, ['stage' => $stageName], $actor);

            return $stageAction;
        });
    }

    public function getActiveInstance(string $contractId): ?WorkflowInstance
    {
        return WorkflowInstance::where('contract_id', $contractId)->where('state', 'active')->first();
    }

    public function getHistory(string $instanceId): Collection
    {
        return WorkflowStageAction::where('instance_id', $instanceId)->orderBy('created_at')->get();
    }


    /**
     * Verify that the actor has signing authority for this contract.
     * Called when advancing through a signing-type workflow stage.
     *
     * @throws \RuntimeException if no matching signing authority exists
     */
    private function checkSigningAuthority(Contract $contract, User $actor, string $stageName): void
    {
        $query = \App\Models\SigningAuthority::query()
            ->where('entity_id', $contract->entity_id)
            ->where(function ($q) use ($contract) {
                $q->whereNull('project_id')
                  ->orWhere('project_id', $contract->project_id);
            })
            ->where('user_id', $actor->id);

        $authority = $query->first();

        if (!$authority) {
            throw new \RuntimeException(
                "No signing authority for user {$actor->email} on entity {$contract->entity_id}" .
                ($contract->project_id ? " / project {$contract->project_id}" : '') .
                ". A signing authority record must exist before contracts can be signed at stage '{$stageName}'."
            );
        }

        if ($authority->contract_type_pattern) {
            $pattern = strtolower($authority->contract_type_pattern);
            $type = strtolower($contract->contract_type ?? '');
            if ($pattern !== '*' && $pattern !== $type) {
                throw new \RuntimeException(
                    "Signing authority for {$actor->email} is restricted to '{$authority->contract_type_pattern}' " .
                    "contracts, but this contract is '{$contract->contract_type}'."
                );
            }
        }
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
