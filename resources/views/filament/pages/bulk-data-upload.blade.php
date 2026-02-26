<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Bulk Data Upload</x-slot>
        <x-slot name="description">
            Import Regions, Entities, Projects, Users, or Counterparties from a CSV file.
            Select the data type, download the template, fill it in, then upload.
        </x-slot>

        <form wire:submit="submit" class="space-y-6">
            {{ $this->form }}

            <div class="flex gap-3">
                <x-filament::button type="submit" color="primary">
                    Import CSV
                </x-filament::button>

                @if (!empty($data['upload_type']))
                    <x-filament::button type="button" color="gray" wire:click="downloadTemplate">
                        Download Template
                    </x-filament::button>
                @endif
            </div>
        </form>
    </x-filament::section>

    @if ($results)
        <x-filament::section class="mt-6">
            <x-slot name="heading">Import Results</x-slot>

            <div class="grid grid-cols-3 gap-4 mb-4">
                <div class="rounded-lg bg-green-100 p-3 text-center dark:bg-green-900">
                    <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ $results['success'] }}</div>
                    <div class="text-xs text-gray-500">Imported</div>
                </div>
                <div class="rounded-lg bg-red-100 p-3 text-center dark:bg-red-900">
                    <div class="text-2xl font-bold text-red-700 dark:text-red-300">{{ $results['failed'] }}</div>
                    <div class="text-xs text-gray-500">Failed</div>
                </div>
                <div class="rounded-lg bg-gray-100 p-3 text-center dark:bg-gray-700">
                    <div class="text-2xl font-bold">{{ $results['success'] + $results['failed'] }}</div>
                    <div class="text-xs text-gray-500">Total Rows</div>
                </div>
            </div>

            @if (!empty($results['errors']))
                <div class="mt-4">
                    <h4 class="text-sm font-semibold text-red-600 mb-2">Errors:</h4>
                    <ul class="list-disc list-inside text-sm text-red-600 space-y-1">
                        @foreach ($results['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
