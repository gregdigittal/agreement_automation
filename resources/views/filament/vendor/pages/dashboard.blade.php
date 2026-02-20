<x-filament-panels::page>
    @php $stats = $this->getDashboardStats() @endphp
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        @foreach ([
            ['Active Agreements', $stats['active_agreements'], 'text-emerald-600'],
            ['Pending Signing', $stats['pending_signing'], 'text-amber-600'],
            ['Documents Uploaded', $stats['documents_uploaded'], 'text-blue-600'],
            ['Unread Notifications', $stats['unread_notifications'], 'text-red-500'],
        ] as [$label, $value, $color])
            <div class="rounded-xl border bg-white p-5 shadow-sm dark:bg-gray-800">
                <p class="text-xs text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold {{ $color }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>
    <x-filament::section heading="Quick Actions">
        <div class="flex flex-wrap gap-3">
            <x-filament::button href="{{ \App\Filament\Vendor\Resources\VendorDocumentResource::getUrl('create') }}" tag="a" color="primary" icon="heroicon-o-arrow-up-tray">Upload Document</x-filament::button>
            <x-filament::button href="{{ \App\Filament\Vendor\Resources\VendorContractResource::getUrl('index') }}" tag="a" color="gray" icon="heroicon-o-document-text">View Agreements</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
