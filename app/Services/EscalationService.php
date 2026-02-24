<?php

namespace App\Services;

use App\Models\EscalationEvent;
use App\Models\EscalationRule;
use App\Models\User;
use App\Models\WorkflowInstance;
use Illuminate\Support\Facades\DB;

class EscalationService
{
    public function checkSlaBreaches(): int
    {
        $escalated = 0;

        WorkflowInstance::where('state', 'active')
            ->with(['template.escalationRules'])
            ->chunkById(100, function ($instances) use (&$escalated) {
                foreach ($instances as $instance) {
                    $rules = $instance->template->escalationRules
                        ->where('stage_name', $instance->current_stage);

                    foreach ($rules as $rule) {
                        $lastAction = $instance->stageActions()
                            ->where('stage_name', $instance->current_stage)
                            ->latest('created_at')
                            ->first();

                        $stageEnteredAt = $lastAction?->created_at ?? $instance->started_at;
                        $hoursInStage = (int) abs(now()->diffInHours($stageEnteredAt));

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
                                    'tier' => $rule->tier ?? 1,
                                    'escalated_at' => now(),
                                    'created_at' => now(),
                                ]);
                                $this->notifyEscalation($rule, $instance);
                                $escalated++;
                            }
                        }
                    }
                }
            });

        return $escalated;
    }

    public function resolveEscalation(string $eventId, User $actor): EscalationEvent
    {
        $event = EscalationEvent::findOrFail($eventId);
        $event->update([
            'resolved_at' => now(),
            'resolved_by' => $actor->email,
        ]);
        AuditService::log('escalation_resolved', 'escalation_event', $event->id, [], $actor);
        return $event->fresh();
    }

    private function notifyEscalation(EscalationRule $rule, WorkflowInstance $instance): void
    {
        $contract = $instance->contract;
        $role = $rule->escalate_to_role;
        $users = $role ? \App\Models\User::role($role)->get() : collect();
        foreach ($users as $user) {
            app(NotificationService::class)->create([
                'recipient_email' => $user->email,
                'recipient_user_id' => $user->id,
                'channel' => 'email',
                'subject' => 'Escalation: SLA breach',
                'body' => "Contract {$contract->title} stage {$instance->current_stage} has breached SLA.",
                'related_resource_type' => 'escalation_event',
                'related_resource_id' => null,
                'notification_category' => 'escalations',
                'status' => 'pending',
            ]);
        }
    }
}
