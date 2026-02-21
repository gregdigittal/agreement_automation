<?php

namespace App\Livewire;

use Livewire\Component;

class WorkflowBuilder extends Component
{
    /** @var array<int, array{id: string, name: string, role: string, duration_days: int, is_approval: bool, order: int}> */
    public array $stages = [];

    public string $statePath = '';

    public function mount(array $state = [], string $statePath = ''): void
    {
        
        $normalized = array_values($state ?: []);
        foreach ($normalized as $i => $s) {
            if (empty($s['id'])) { $normalized[$i]['id'] = (string) \Illuminate\Support\Str::uuid(); }
            if (!isset($s['order'])) { $normalized[$i]['order'] = $i; }
        }
        $this->stages = $normalized;
        $this->statePath = $statePath;
    }

    public function addStage(): void
    {
        $this->stages[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'New Stage',
            'role' => 'legal',
            'duration_days' => 5,
            'is_approval' => false,
            'order' => count($this->stages),
        ];
        $this->syncState();
    }

    public function removeStage(int $index): void
    {
        array_splice($this->stages, $index, 1);
        $this->reorder();
        $this->syncState();
    }

    public function updateStage(int $index, string $field, mixed $value): void
    {
        $this->stages[$index][$field] = $value;
        $this->syncState();
    }

    public function reorderStages(array $orderedIds): void
    {
        $stageMap = collect($this->stages)->keyBy('id');
        $this->stages = collect($orderedIds)
            ->map(fn ($id, $pos) => array_merge($stageMap[$id] ?? [], ['order' => $pos]))
            ->values()
            ->toArray();
        $this->syncState();
    }

    private function reorder(): void
    {
        foreach ($this->stages as $i => $stage) {
            $this->stages[$i]['order'] = $i;
        }
    }

    private function syncState(): void
    {
        $this->dispatch('workflow-builder-updated', statePath: $this->statePath, stages: $this->stages);
    }

    public function render()
    {
        return view('livewire.workflow-builder');
    }
}
