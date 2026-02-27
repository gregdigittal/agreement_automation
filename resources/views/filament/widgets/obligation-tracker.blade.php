<x-filament-widgets::widget>
    <x-filament::section heading="Obligation Tracker" description="Upcoming and overdue obligations (next 90 days)">
        @php $obligations = $this->getObligations(); @endphp

        @if (empty($obligations))
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" />
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No upcoming obligations</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Obligations due within the next 90 days will appear here.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" aria-label="Upcoming obligations">
                    <thead>
                        <tr class="text-left text-gray-600 dark:text-gray-400 border-b dark:border-gray-700">
                            <th class="py-2 px-2 font-medium">Contract</th>
                            <th class="py-2 px-2 font-medium">Type</th>
                            <th class="py-2 px-2 font-medium">Description</th>
                            <th class="py-2 px-2 font-medium">Due Date</th>
                            <th class="py-2 px-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($obligations as $ob)
                            @php
                                $isOverdue = \Carbon\Carbon::parse($ob['due_date'])->isPast() && $ob['status'] !== 'completed';
                                $contractUrl = route('filament.admin.resources.contracts.edit', ['record' => $ob['contract_id']]);
                            @endphp
                            <tr class="border-b dark:border-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ $isOverdue ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                                <td class="py-2 px-2">
                                    <a href="{{ $contractUrl }}" class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                                        {{ \Illuminate\Support\Str::limit($ob['contract_title'], 40) }}
                                    </a>
                                </td>
                                <td class="py-2 px-2">
                                    <span class="inline-flex px-2 py-0.5 text-xs rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
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
                                    <span class="inline-flex px-2 py-0.5 text-xs rounded-md
                                        {{ $ob['status'] === 'pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                                        {{ $ob['status'] === 'in_progress' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                                        {{ $ob['status'] === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                        {{ $ob['status'] === 'overdue' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : '' }}
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
