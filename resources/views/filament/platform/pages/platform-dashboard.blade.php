<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-filament::card>
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Tenants</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalTenants }}</p>
                </div>
                <x-filament::icon icon="heroicon-o-building-office-2" class="h-8 w-8 text-gray-400" />
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active</p>
                    <p class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $activeTenants }}</p>
                </div>
                <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8 text-success-400" />
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Provisioning</p>
                    <p class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $provisioningTenants }}</p>
                </div>
                <x-filament::icon icon="heroicon-o-arrow-path" class="h-8 w-8 text-warning-400" />
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Suspended</p>
                    <p class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $suspendedTenants }}</p>
                </div>
                <x-filament::icon icon="heroicon-o-pause-circle" class="h-8 w-8 text-danger-400" />
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::page>
