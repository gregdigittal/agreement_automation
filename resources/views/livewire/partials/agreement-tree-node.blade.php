@php
    $nodeKey = $node['type'] . '_' . $node['id'];
    $isExpanded = isset($expanded[$nodeKey]);
    $hasChildren = !empty($node['children'] ?? []);
    $paddingLeft = match ($level) {
        0 => 'pl-4',
        1 => 'pl-10',
        2 => 'pl-16',
        default => 'pl-20',
    };
    $iconColor = match ($node['type']) {
        'entity' => 'text-indigo-600 dark:text-indigo-400',
        'counterparty' => 'text-amber-600 dark:text-amber-400',
        'jurisdiction' => 'text-teal-600 dark:text-teal-400',
        'project' => 'text-green-600 dark:text-green-400',
        default => 'text-gray-600',
    };
@endphp

<div role="treeitem" aria-expanded="{{ $isExpanded ? 'true' : 'false' }}">
    {{-- Node row --}}
    <button
        wire:click="toggleNode('{{ $nodeKey }}');{{ !$isExpanded ? "loadContracts('{$node['type']}', '{$node['id']}')" : '' }}"
        class="flex items-center gap-2 w-full text-left px-3 py-2.5 {{ $paddingLeft }} border-b border-gray-100 dark:border-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-gray-750 group"
        aria-label="{{ ucfirst($node['type']) }}: {{ $node['name'] }}"
    >
        {{-- Expand/collapse chevron --}}
        <svg
            class="w-4 h-4 text-gray-400 transition-transform duration-200 {{ $isExpanded ? 'rotate-90' : '' }}"
            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>

        {{-- Type icon --}}
        @switch($node['type'])
            @case('entity')
                <svg class="w-5 h-5 {{ $iconColor }} flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
                @break
            @case('counterparty')
                <svg class="w-5 h-5 {{ $iconColor }} flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                @break
            @case('jurisdiction')
                <svg class="w-5 h-5 {{ $iconColor }} flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                @break
            @case('project')
                <svg class="w-5 h-5 {{ $iconColor }} flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                @break
        @endswitch

        {{-- Node name --}}
        <span class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $node['name'] }}</span>

        @if (!empty($node['code']))
            <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">({{ $node['code'] }})</span>
        @endif

        @if (!empty($node['entity_name']))
            <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">- {{ $node['entity_name'] }}</span>
        @endif

        {{-- Count badges --}}
        <div class="ml-auto flex items-center gap-1.5 flex-shrink-0">
            @if (($node['draft_count'] ?? 0) > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300" title="Draft">
                    {{ $node['draft_count'] }} draft
                </span>
            @endif
            @if (($node['active_count'] ?? 0) > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300" title="In Review">
                    {{ $node['active_count'] }} active
                </span>
            @endif
            @if (($node['executed_count'] ?? 0) > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300" title="Executed">
                    {{ $node['executed_count'] }} executed
                </span>
            @endif
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300" title="Total contracts">
                {{ $node['total_count'] ?? 0 }} total
            </span>
        </div>
    </button>

    {{-- Expanded content --}}
    @if ($isExpanded)
        {{-- Sub-nodes (jurisdiction -> entities) --}}
        @if ($hasChildren)
            <div role="group">
                @foreach ($node['children'] as $child)
                    @include('livewire.partials.agreement-tree-node', ['node' => $child, 'level' => $level + 1])
                @endforeach
            </div>
        @endif

        {{-- Contract rows --}}
        @php
            $contracts = $loadedContracts[$nodeKey] ?? [];
        @endphp

        @if (count($contracts) > 0)
            <div class="border-b border-gray-100 dark:border-gray-700">
                @foreach ($contracts as $contract)
                    <a
                        href="{{ \App\Filament\Resources\ContractResource::getUrl('edit', ['record' => $contract['id']]) }}"
                        class="flex items-center gap-3 px-3 py-2 {{ $level === 0 ? 'pl-12' : ($level === 1 ? 'pl-[4.5rem]' : 'pl-24') }} text-sm hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors duration-150 border-t border-gray-50 dark:border-gray-750"
                    >
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>

                        <span class="text-gray-800 dark:text-gray-200 truncate">{{ $contract['title'] }}</span>

                        @if (!empty($contract['contract_type']))
                            <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">{{ $contract['contract_type'] }}</span>
                        @endif

                        @if (!empty($contract['expiry_date']))
                            <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">Exp: {{ $contract['expiry_date'] }}</span>
                        @endif

                        @php
                            $stateColor = match ($contract['workflow_state'] ?? '') {
                                'draft' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                'in_review' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                'approved' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                'executed' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                'archived' => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <span class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $stateColor }} flex-shrink-0">
                            {{ str_replace('_', ' ', ucfirst($contract['workflow_state'] ?? 'unknown')) }}
                        </span>
                    </a>
                @endforeach
            </div>
        @elseif (empty($node['children'] ?? []))
            <div class="px-3 py-3 {{ $level === 0 ? 'pl-12' : ($level === 1 ? 'pl-[4.5rem]' : 'pl-24') }} text-sm text-gray-400 dark:text-gray-500 italic border-b border-gray-100 dark:border-gray-700">
                No contracts found.
            </div>
        @endif
    @endif
</div>
