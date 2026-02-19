<x-filament-panels::page>
    @php $stats = $this->getSummaryStats() @endphp
    <div class="grid grid-cols-4 gap-4 mb-6">
        @foreach ([
            ['Total Cost (USD)', '$' . $stats['total_cost'], 'text-red-600'],
            ['Total Tokens', $stats['total_tokens'], 'text-blue-600'],
            ['Total Analyses', $stats['total_analyses'], 'text-green-600'],
            ['Avg Cost / Analysis', '$' . $stats['avg_cost'], 'text-purple-600'],
        ] as [$label, $value, $color])
            <div class="rounded-xl border bg-white p-5 shadow-sm dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold {{ $color }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>
    {{ $this->table }}
</x-filament-panels::page>
