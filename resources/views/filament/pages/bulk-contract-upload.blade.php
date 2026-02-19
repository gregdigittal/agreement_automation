<x-filament-panels::page>
    <form wire:submit="upload">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" :disabled="$processing">
                {{ $processing ? 'Processing...' : 'Upload & Process' }}
            </x-filament::button>
        </div>
    </form>

    @if(count($uploadResults) > 0)
        <div class="mt-6" @if($processing) wire:poll.2s="pollStatus" @endif>
            <h3 class="text-lg font-medium mb-3">Upload Progress</h3>
            <div class="rounded-lg border overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Row</th>
                            <th class="px-4 py-2 text-left">Title</th>
                            <th class="px-4 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($uploadResults as $result)
                            <tr class="border-t">
                                <td class="px-4 py-2">{{ $result['row'] + 1 }}</td>
                                <td class="px-4 py-2">{{ $result['title'] }}</td>
                                <td class="px-4 py-2">
                                    @if($result['status'] === 'completed')
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Completed</span>
                                    @elseif($result['status'] === 'failed')
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">Failed</span>
                                    @elseif($result['status'] === 'processing')
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">Processing</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">Queued</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
