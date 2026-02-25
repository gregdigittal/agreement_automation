<div class="space-y-4">
    {{-- Grouping tabs --}}
    <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Group contracts by">
        @foreach ([
            'entity' => 'Entity',
            'counterparty' => 'Counterparty',
            'jurisdiction' => 'Jurisdiction',
            'project' => 'Project',
        ] as $key => $label)
            <button
                wire:click="setGroupBy('{{ $key }}')"
                role="tab"
                aria-selected="{{ $groupBy === $key ? 'true' : 'false' }}"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-150
                    {{ $groupBy === $key
                        ? 'bg-primary-600 text-white shadow-sm'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Search & filter row --}}
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search agreements..."
                class="w-full pl-10 pr-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                aria-label="Search agreements"
            />
        </div>
        <select
            wire:model.live="statusFilter"
            class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            aria-label="Filter by status"
        >
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="in_review">In Review</option>
            <option value="approved">Approved</option>
            <option value="executed">Executed</option>
            <option value="archived">Archived</option>
        </select>
    </div>

    {{-- Loading overlay --}}
    <div wire:loading class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        Loading...
    </div>

    {{-- Tree content --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden" role="tree" aria-label="Agreement repository tree">
        @forelse ($treeData as $node)
            @include('livewire.partials.agreement-tree-node', ['node' => $node, 'level' => 0])
        @empty
            <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.75 7.5h16.5" />
                </svg>
                <p class="text-sm">No agreements found matching your criteria.</p>
            </div>
        @endforelse

        @if ($treeTotal > count($treeData))
            <div class="px-4 py-3 text-sm text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border-t border-amber-200 dark:border-amber-800">
                Showing {{ count($treeData) }} of {{ $treeTotal }} nodes. Use search or filters to narrow results.
            </div>
        @endif
    </div>
</div>
