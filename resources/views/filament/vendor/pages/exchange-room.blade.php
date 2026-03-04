<x-filament-panels::page>
    <x-filament::section>
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->contract->title }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Document Exchange Room</p>
            </div>
            <div class="flex items-center gap-3">
                <x-filament::badge :color="$this->getRoomStatus() === 'open' ? 'success' : 'danger'">
                    {{ ucfirst($this->getRoomStatus()) }}
                </x-filament::badge>
                <x-filament::badge color="info">
                    {{ $this->getNegotiationStage() }}
                </x-filament::badge>
            </div>
        </div>
    </x-filament::section>

    @if ($this->getRoomStatus() === 'closed')
        <x-filament::section>
            <p class="text-sm text-amber-700 dark:text-amber-300">This exchange room has been closed. No further posts or uploads are allowed.</p>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
