<?php

namespace App\Services;

use App\Models\EscalationEvent;
use App\Models\EscalationRule;
use App\Models\WorkflowInstance;

class EscalationService
{
    public function checkBreaches(): int
    {
        $activeInstances = WorkflowInstance::where('state', 'active')
            ->with('template.escalationRules')
            ->get();

        $escalated = 0;
        foreach ($activeInstances as $instance) {
            $rules = $instance->template->escalationRules
                ->where('stage_name', $instance->current_stage);

            foreach ($rules as $rule) {
                $lastAction = $instance->stageActions()
                    ->where('stage_name', $instance->current_stage)
                    ->latest('created_at')
                    ->first();

                $stageEnteredAt = $lastAction?->created_at ?? $instance->started_at;
                $hoursInStage = now()->diffInHours($stageEnteredAt);

                if ($hoursInStage >= $rule->sla_breach_hours) {
                    $existing = EscalationEvent::where('workflow_instance_id', $instance->id)
                        ->where('rule_id', $rule->id)
                        ->whereNull('resolved_at')
                        ->exists();

                    if (!$existing) {
                        EscalationEvent::create([
                            'workflow_instance_id' => $instance->id,
                            'rule_id' => $rule->id,
                            'contract_id' => $instance->contract_id,
                            'stage_name' => $instance->current_stage,
                            'tier' => $rule->tier,
                            'escalated_at' => now(),
                            'created_at' => now(),
                        ]);
                        $escalated++;
                    }
                }
            }
        }
        return $escalated;
    }
}
