<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Upload Contracts in Bulk</x-slot>
        <x-slot name="description">
            Upload a CSV manifest and an optional ZIP of contract files. Each row will be queued as a separate job.
        </x-slot>

        <form wire:submit="submit" class="space-y-6">
            {{ $this->form }}

            <x-filament::button type="submit" color="primary">
                Start Upload
            </x-filament::button>
        </form>
    </x-filament::section>

    @if ($currentBulkUploadId)
        @php $progress = $this->getProgressData(); @endphp
        <x-filament::section class="mt-6">
            <x-slot name="heading">Progress — Bulk Upload {{ $currentBulkUploadId }}</x-slot>

            <div class="grid grid-cols-4 gap-4 mb-4">
                <div class="rounded-lg bg-gray-100 p-3 text-center dark:bg-gray-700">
                    <div class="text-2xl font-bold">{{ $progress['total'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">Total</div>
                </div>
                <div class="rounded-lg bg-green-100 p-3 text-center dark:bg-green-900">
                    <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ $progress['completed'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">Completed</div>
                </div>
                <div class="rounded-lg bg-red-100 p-3 text-center dark:bg-red-900">
                    <div class="text-2xl font-bold text-red-700 dark:text-red-300">{{ $progress['failed'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">Failed</div>
                </div>
                <div class="rounded-lg bg-yellow-100 p-3 text-center dark:bg-yellow-900">
                    <div class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">{{ ($progress['pending'] ?? 0) + ($progress['processing'] ?? 0) }}</div>
                    <div class="text-xs text-gray-500">Pending</div>
                </div>
            </div>

            <div wire:poll.2s class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs text-gray-500">
                            <th class="py-1 pr-4">Row</th>
                            <th class="py-1 pr-4">Status</th>
                            <th class="py-1 pr-4">Contract ID</th>
                            <th class="py-1">Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($progress['rows'] ?? []) as $row)
                            <tr class="border-b last:border-0">
                                <td class="py-1 pr-4">{{ $row['row_number'] }}</td>
                                <td class="py-1 pr-4">
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium
                                        {{ $row['status'] === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $row['status'] === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                        {{ in_array($row['status'], ['pending', 'processing']) ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    ">{{ $row['status'] }}</span>
                                </td>
                                <td class="py-1 pr-4 font-mono text-xs">{{ $row['contract_id'] ?? '—' }}</td>
                                <td class="py-1 text-red-600 text-xs">{{ $row['error'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
