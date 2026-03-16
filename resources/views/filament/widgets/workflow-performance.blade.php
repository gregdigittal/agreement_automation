<x-filament-widgets::widget>
    <x-filament::section heading="Workflow Performance" description="Action distribution per stage (last 90 days)">
        @php $metrics = $this->getPerformanceData(); @endphp

        @if (empty($metrics))
            <p class="text-gray-600 dark:text-gray-400 italic text-sm">No workflow performance data available.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" aria-label="Workflow performance metrics">
                    <thead>
                        <tr class="text-left text-gray-600 dark:text-gray-400 border-b dark:border-gray-700">
                            <th class="py-2 px-2">Stage</th>
                            <th class="py-2 px-2 text-right">Total Actions</th>
                            <th class="py-2 px-2 text-right">Approved</th>
                            <th class="py-2 px-2 text-right">Rejected</th>
                            <th class="py-2 px-2 text-right">Rework</th>
                            <th class="py-2 px-2 text-right">Skipped</th>
                            <th class="py-2 px-2 text-right">Rework Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($metrics as $metric)
                            @php
                                $isHighRework = $metric['rework_rate'] > 20;
                            @endphp
                            <tr class="border-b dark:border-gray-700 {{ $isHighRework ? 'bg-amber-50 dark:bg-amber-900/20' : '' }}">
                                <td class="py-2 px-2 text-gray-900 dark:text-gray-100 font-medium">
                                    {{ ucwords(str_replace('_', ' ', $metric['stage_name'])) }}
                                    @if ($isHighRework)
                                        <span class="text-xs text-amber-600 ml-1" title="High rework rate">(review quality)</span>
                                    @endif
                                </td>
                                <td class="py-2 px-2 text-right text-gray-900 dark:text-gray-100">{{ $metric['total_actions'] }}</td>
                                <td class="py-2 px-2 text-right text-green-600 dark:text-green-400">{{ $metric['approvals'] }}</td>
                                <td class="py-2 px-2 text-right {{ $metric['rejections'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-600 dark:text-gray-400' }}">
                                    {{ $metric['rejections'] }}
                                </td>
                                <td class="py-2 px-2 text-right {{ $metric['reworks'] > 0 ? 'text-amber-600' : 'text-gray-600 dark:text-gray-400' }}">
                                    {{ $metric['reworks'] }}
                                </td>
                                <td class="py-2 px-2 text-right text-gray-600 dark:text-gray-400">{{ $metric['skips'] }}</td>
                                <td class="py-2 px-2 text-right">
                                    <span class="inline-flex px-2 py-0.5 text-xs rounded
                                        {{ $metric['rework_rate'] > 20 ? 'bg-red-100 text-red-700' : '' }}
                                        {{ $metric['rework_rate'] > 5 && $metric['rework_rate'] <= 20 ? 'bg-amber-100 text-amber-700' : '' }}
                                        {{ $metric['rework_rate'] <= 5 ? 'bg-green-100 text-green-700' : '' }}
                                    ">
                                        {{ $metric['rework_rate'] }}%
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
