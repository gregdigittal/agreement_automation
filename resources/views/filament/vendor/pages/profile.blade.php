<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}
            <x-filament::button type="submit" color="primary">Save Changes</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
