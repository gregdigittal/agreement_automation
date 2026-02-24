<x-filament-panels::page>
    @php $stats = $this->getDashboardStats() @endphp

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        @foreach ([
            ['Active Agreements', $stats['active_agreements'], 'heroicon-o-document-check', 'text-emerald-600'],
            ['Pending Signing', $stats['pending_signing'], 'heroicon-o-pencil', 'text-amber-600'],
            ['Documents Uploaded', $stats['documents_uploaded'], 'heroicon-o-folder', 'text-blue-600'],
            ['Unread Notifications', $stats['unread_notifications'], 'heroicon-o-bell', 'text-red-500'],
        ] as [$label, $value, $icon, $color])
            <div class="rounded-xl border bg-white p-5 shadow-sm dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <x-filament::icon :icon="$icon" class="h-6 w-6 {{ $color }}" />
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
                        <p class="text-2xl font-bold {{ $color }}">{{ $value }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-filament::section heading="Quick Actions">
        <div class="flex flex-wrap gap-3">
            <x-filament::button
                href="{{ \App\Filament\Vendor\Resources\VendorDocumentResource::getUrl('create') }}"
                tag="a"
                color="primary"
                icon="heroicon-o-arrow-up-tray"
            >
                Upload Document
            </x-filament::button>
            <x-filament::button
                href="{{ \App\Filament\Vendor\Resources\VendorContractResource::getUrl('index') }}"
                tag="a"
                color="gray"
                icon="heroicon-o-document-text"
            >
                View Agreements
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
