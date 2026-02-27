<x-filament-panels::page>
    @php $stats = $this->getDashboardStats() @endphp

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        @foreach ([
            ['Active Agreements', $stats['active_agreements'], 'heroicon-o-document-check', 'text-emerald-600'],
            ['Pending Signing', $stats['pending_signing'], 'heroicon-o-pencil', 'text-amber-600'],
            ['Documents Uploaded', $stats['documents_uploaded'], 'heroicon-o-folder', 'text-blue-600'],
            ['Unread Notifications', $stats['unread_notifications'], 'heroicon-o-bell', 'text-red-500'],
        ] as [$label, $value, $icon, $color])
            <div class="rounded-xl border bg-white p-5 shadow-sm dark:bg-gray-800 dark:border-gray-700">
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

    @php $activity = $this->getRecentActivity() @endphp
    <x-filament::section heading="Recent Activity" description="Latest updates on your agreements and documents">
        @if (empty($activity))
            <div class="flex flex-col items-center justify-center py-6 text-center">
                <x-filament::icon icon="heroicon-o-clock" class="h-10 w-10 text-gray-300 dark:text-gray-600 mb-2" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No recent activity</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($activity as $item)
                    <div class="flex items-start gap-3 rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <x-filament::icon :icon="$item['icon']" class="h-5 w-5 mt-0.5 {{ $item['color'] }}" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $item['description'] }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $item['time'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
