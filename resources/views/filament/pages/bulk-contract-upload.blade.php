<x-filament-panels::page>
    {{-- Smart Upload (AI-Powered) --}}
    <x-filament::section>
        <x-slot name="heading">Smart Upload (AI-Powered)</x-slot>
        <x-slot name="description">
            Upload contract files and let AI extract metadata automatically. Each file becomes a staged agreement
            that appears in the Agreements list. AI will extract title, contract type, counterparty, entity, and
            governing law. You can review and approve AI findings on the AI Discovery Review page.
        </x-slot>

        <form wire:submit="submitSmartUpload" class="space-y-6">
            {{ $this->smartUploadForm }}

            <div>
                <x-filament::button type="submit" color="primary" icon="heroicon-o-sparkles">
                    Start Smart Upload
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- Smart Upload Progress --}}
    @if (! empty($smartUploadContractIds))
        @php $smartProgress = $this->getSmartUploadProgress(); @endphp
        <x-filament::section>
            <x-slot name="heading">Smart Upload Progress</x-slot>

            <div wire:poll.5s class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs text-gray-500">
                            <th class="py-1 pr-4">File</th>
                            <th class="py-1 pr-4">Title</th>
                            <th class="py-1 pr-4">State</th>
                            <th class="py-1 pr-4">Extraction</th>
                            <th class="py-1">Discovery</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($smartProgress as $item)
                            <tr class="border-b last:border-0">
                                <td class="py-1 pr-4 text-xs">{{ $item['file_name'] }}</td>
                                <td class="py-1 pr-4 text-xs">{{ $item['title'] }}</td>
                                <td class="py-1 pr-4">
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">
                                        {{ $item['workflow_state'] }}
                                    </span>
                                </td>
                                <td class="py-1 pr-4">
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium
                                        {{ $item['extraction'] === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $item['extraction'] === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                        {{ in_array($item['extraction'], ['pending', 'processing']) ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    ">{{ $item['extraction'] }}</span>
                                </td>
                                <td class="py-1">
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium
                                        {{ $item['discovery'] === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $item['discovery'] === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                        {{ in_array($item['discovery'], ['pending', 'processing']) ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    ">{{ $item['discovery'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- CSV Manifest Upload --}}
    <x-filament::section>
        <x-slot name="heading">CSV Manifest Upload</x-slot>
        <x-slot name="description">
            Upload a CSV manifest and an optional ZIP of contract files. Each row will be queued as a separate job.
            Use this when you already have the metadata (region, entity, project, counterparty) prepared.
        </x-slot>

        <form wire:submit="submit" class="space-y-6">
            {{ $this->form }}

            <div class="flex gap-3">
                <x-filament::button type="submit" color="primary">
                    Start Upload
                </x-filament::button>

                <x-filament::button type="button" color="gray" wire:click="downloadTemplate">
                    Download CSV Template
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- Individual File Upload (for CSV manifest reference) --}}
    <x-filament::section>
        <x-slot name="heading">Individual File Upload</x-slot>
        <x-slot name="description">
            Upload contract files individually instead of as a ZIP archive.
            Files uploaded here can be referenced by filename in the CSV manifest above.
        </x-slot>

        <form wire:submit="uploadIndividualFiles" class="space-y-6">
            {{ $this->individualUploadForm }}

            <div>
                <x-filament::button type="submit" color="primary">
                    Upload Files
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- Bulk Upload Progress --}}
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
