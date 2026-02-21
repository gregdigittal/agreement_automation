<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @livewire('workflow-builder', [
        'state' => $getState() ?: [],
        'statePath' => $getStatePath(),
    ])

    <div
        x-data="{}"
        x-on:workflow-builder-updated.window="
            if ($event.detail.statePath === '{{ $getStatePath() }}') {
                $wire.set('{{ $getStatePath() }}', $event.detail.stages);
            }
        "
    ></div>
</x-dynamic-component>
