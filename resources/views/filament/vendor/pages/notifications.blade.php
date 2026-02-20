<x-filament-panels::page>
    @if ($this->getUnreadCount() > 0)
        <x-filament::section>
            <p class="text-sm text-amber-700 dark:text-amber-300">You have {{ $this->getUnreadCount() }} unread notification(s).</p>
        </x-filament::section>
    @endif
    {{ $this->table }}
</x-filament-panels::page>
