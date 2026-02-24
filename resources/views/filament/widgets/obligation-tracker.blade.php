<x-filament-widgets::widget>
    <x-filament::section heading="Obligation Tracker" description="Upcoming and overdue obligations (next 90 days)">
        @php $obligations = $this->getObligations(); @endphp

        @if (empty($obligations))
            <p class="text-gray-600 dark:text-gray-400 italic text-sm">No upcoming obligations found.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" aria-label="Upcoming obligations">
                    <thead>
                        <tr class="text-left text-gray-600 dark:text-gray-400 border-b dark:border-gray-700">
                            <th class="py-2 px-2">Contract</th>
                            <th class="py-2 px-2">Type</th>
                            <th class="py-2 px-2">Description</th>
                            <th class="py-2 px-2">Due Date</th>
                            <th class="py-2 px-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($obligations as $ob)
                            @php
                                $isOverdue = \Carbon\Carbon::parse($ob['due_date'])->isPast() && $ob['status'] !== 'completed';
                            @endphp
                            <tr class="border-b dark:border-gray-700 {{ $isOverdue ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                                <td class="py-2 px-2 text-gray-900 dark:text-gray-100">
                                    {{ \Illuminate\Support\Str::limit($ob['contract_title'], 40) }}
                                </td>
                                <td class="py-2 px-2">
                                    <span class="inline-flex px-2 py-0.5 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                        {{ ucwords(str_replace('_', ' ', $ob['obligation_type'])) }}
                                    </span>
                                </td>
                                <td class="py-2 px-2 text-gray-600 dark:text-gray-400">
                                    {{ \Illuminate\Support\Str::limit($ob['description'] ?? '', 60) }}
                                </td>
                                <td class="py-2 px-2 {{ $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ \Carbon\Carbon::parse($ob['due_date'])->format('d M Y') }}
                                    @if ($isOverdue)
                                        <span class="text-xs text-red-500 ml-1">(OVERDUE)</span>
                                    @endif
                                </td>
                                <td class="py-2 px-2">
                                    <span class="inline-flex px-2 py-0.5 text-xs rounded
                                        {{ $ob['status'] === 'pending' ? 'bg-amber-100 text-amber-700' : '' }}
                                        {{ $ob['status'] === 'in_progress' ? 'bg-blue-100 text-blue-700' : '' }}
                                        {{ $ob['status'] === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $ob['status'] === 'overdue' ? 'bg-red-100 text-red-700' : '' }}
                                    ">
                                        {{ ucfirst($ob['status']) }}
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
