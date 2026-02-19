<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{ stages: $wire.entangle('{{ $getStatePath() }}') }"
        x-on:workflow-stages-updated.window="stages = $event.detail.stages"
    >
        @livewire('workflow-builder', ['stages' => $getState() ?? [], 'fieldName' => $getStatePath()], key($getStatePath()))
    </div>
</x-dynamic-component>
