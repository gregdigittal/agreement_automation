<?php

namespace App\Livewire;

use Livewire\Component;

class WorkflowBuilder extends Component
{
    public array $stages = [];
    public string $fieldName = 'stages';

    public function mount(array $stages = [], string $fieldName = 'stages'): void
    {
        $this->stages = $stages;
        $this->fieldName = $fieldName;
    }

    public function addStage(): void
    {
        $this->stages[] = [
            'name' => 'New Stage',
            'approver_role' => '',
            'sla_hours' => 24,
            'required' => true,
            'order' => count($this->stages) + 1,
        ];
        $this->syncToParent();
    }

    public function removeStage(int $index): void
    {
        unset($this->stages[$index]);
        $this->stages = array_values($this->stages);
        $this->reorderStages();
        $this->syncToParent();
    }

    public function updateStageOrder(array $orderedIds): void
    {
        $reordered = [];
        foreach ($orderedIds as $position => $index) {
            if (isset($this->stages[$index])) {
                $stage = $this->stages[$index];
                $stage['order'] = $position + 1;
                $reordered[] = $stage;
            }
        }
        $this->stages = $reordered;
        $this->syncToParent();
    }

    public function updateStageField(int $index, string $field, mixed $value): void
    {
        if (isset($this->stages[$index])) {
            $this->stages[$index][$field] = $value;
            $this->syncToParent();
        }
    }

    private function reorderStages(): void
    {
        foreach ($this->stages as $i => &$stage) {
            $stage['order'] = $i + 1;
        }
    }

    private function syncToParent(): void
    {
        $this->dispatch('workflow-stages-updated', stages: $this->stages);
    }

    public function render()
    {
        return view('livewire.workflow-builder');
    }
}
