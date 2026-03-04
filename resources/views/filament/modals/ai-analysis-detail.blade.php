<div class="space-y-4">
    {{-- Status --}}
    <div class="flex items-center gap-2">
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Status:</span>
        @if($record->status === 'completed')
            <x-filament::badge color="success">Completed</x-filament::badge>
        @elseif($record->status === 'failed')
            <x-filament::badge color="danger">Failed</x-filament::badge>
        @else
            <x-filament::badge>{{ ucfirst($record->status) }}</x-filament::badge>
        @endif
    </div>

    {{-- Error Message (if failed) --}}
    @if($record->status === 'failed' && $record->error_message)
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-4 dark:border-danger-600 dark:bg-danger-950">
            <p class="text-sm font-semibold text-danger-700 dark:text-danger-400">Error:</p>
            <p class="mt-1 text-sm text-danger-600 dark:text-danger-300 whitespace-pre-wrap break-words">{{ $record->error_message }}</p>
        </div>
    @endif

    {{-- Usage Stats --}}
    @if($record->model_used || $record->cost_usd || $record->processing_time_ms)
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @if($record->model_used)
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Model</p>
                    <p class="text-sm font-medium">{{ $record->model_used }}</p>
                </div>
            @endif
            @if($record->processing_time_ms)
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Processing Time</p>
                    <p class="text-sm font-medium">{{ round($record->processing_time_ms / 1000, 1) }}s</p>
                </div>
            @endif
            @if($record->token_usage_input || $record->token_usage_output)
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Tokens</p>
                    <p class="text-sm font-medium">{{ number_format($record->token_usage_input ?? 0) }} in / {{ number_format($record->token_usage_output ?? 0) }} out</p>
                </div>
            @endif
            @if($record->cost_usd)
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Cost</p>
                    <p class="text-sm font-medium">${{ number_format($record->cost_usd, 4) }}</p>
                </div>
            @endif
        </div>
    @endif

    {{-- Result JSON --}}
    @if($record->result && $record->status === 'completed')
        <div>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Result:</p>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 overflow-auto max-h-96">
                <pre class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words">{{ json_encode($record->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    @endif

    {{-- Confidence --}}
    @if($record->confidence_score)
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Confidence Score</p>
            <div class="flex items-center gap-2 mt-1">
                <div class="h-2 flex-1 rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-2 rounded-full {{ $record->confidence_score >= 0.7 ? 'bg-success-500' : ($record->confidence_score >= 0.4 ? 'bg-warning-500' : 'bg-danger-500') }}" style="width: {{ $record->confidence_score * 100 }}%"></div>
                </div>
                <span class="text-sm font-medium">{{ number_format($record->confidence_score * 100, 0) }}%</span>
            </div>
        </div>
    @endif
</div>
