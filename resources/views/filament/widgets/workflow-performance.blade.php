<x-filament-widgets::widget>
    <x-filament::section heading="Workflow Performance" description="Average stage durations and SLA breach rates (last 90 days)">
        @php $metrics = $this->getPerformanceData(); @endphp

        @if (empty($metrics))
            <p class="text-gray-600 dark:text-gray-400 italic text-sm">No workflow performance data available.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" aria-label="Workflow performance metrics">
                    <thead>
                        <tr class="text-left text-gray-600 dark:text-gray-400 border-b dark:border-gray-700">
                            <th class="py-2 px-2">Stage</th>
                            <th class="py-2 px-2 text-right">Avg Duration (hrs)</th>
                            <th class="py-2 px-2 text-right">Min (hrs)</th>
                            <th class="py-2 px-2 text-right">Max (hrs)</th>
                            <th class="py-2 px-2 text-right">Actions</th>
                            <th class="py-2 px-2 text-right">SLA Breaches</th>
                            <th class="py-2 px-2 text-right">Breach Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($metrics as $metric)
                            @php
                                $isBottleneck = $metric['avg_hours'] > 48;
                            @endphp
                            <tr class="border-b dark:border-gray-700 {{ $isBottleneck ? 'bg-amber-50 dark:bg-amber-900/20' : '' }}">
                                <td class="py-2 px-2 text-gray-900 dark:text-gray-100 font-medium">
                                    {{ ucwords(str_replace('_', ' ', $metric['stage_name'])) }}
                                    @if ($isBottleneck)
                                        <span class="text-xs text-amber-600 ml-1" title="Potential bottleneck">(bottleneck)</span>
                                    @endif
                                </td>
                                <td class="py-2 px-2 text-right text-gray-900 dark:text-gray-100">{{ $metric['avg_hours'] }}</td>
                                <td class="py-2 px-2 text-right text-gray-600 dark:text-gray-400">{{ $metric['min_hours'] }}</td>
                                <td class="py-2 px-2 text-right text-gray-600 dark:text-gray-400">{{ $metric['max_hours'] }}</td>
                                <td class="py-2 px-2 text-right text-gray-600 dark:text-gray-400">{{ $metric['total_actions'] }}</td>
                                <td class="py-2 px-2 text-right {{ $metric['sla_breaches'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-600 dark:text-gray-400' }}">
                                    {{ $metric['sla_breaches'] }}
                                </td>
                                <td class="py-2 px-2 text-right">
                                    <span class="inline-flex px-2 py-0.5 text-xs rounded
                                        {{ $metric['sla_breach_rate'] > 20 ? 'bg-red-100 text-red-700' : '' }}
                                        {{ $metric['sla_breach_rate'] > 5 && $metric['sla_breach_rate'] <= 20 ? 'bg-amber-100 text-amber-700' : '' }}
                                        {{ $metric['sla_breach_rate'] <= 5 ? 'bg-green-100 text-green-700' : '' }}
                                    ">
                                        {{ $metric['sla_breach_rate'] }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
