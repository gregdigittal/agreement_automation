<div class="flex gap-6" x-data="{ expandedNodes: {} }">
    {{-- Left panel: Tree --}}
    <div class="w-2/3 bg-white dark:bg-gray-800 rounded-xl shadow p-4 overflow-auto" role="tree" aria-label="Organization hierarchy">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Region / Entity / Project Hierarchy</h3>

        @forelse ($tree as $region)
            <div class="mb-2" role="treeitem" x-data="{ open: false }">
                {{-- Region node --}}
                <button
                    class="flex items-center gap-2 w-full text-left px-3 py-2 rounded-lg transition
                        {{ $region['highlighted'] ? 'bg-yellow-100 dark:bg-yellow-900 border border-yellow-400' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}
                        {{ $selectedNodeId === $region['id'] ? 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900' : '' }}"
                    x-on:click="open = !open"
                    wire:click="selectNode('region', '{{ $region['id'] }}')"
                    aria-label="Region: {{ $region['name'] }}"
                    :aria-expanded="open.toString()"
                >
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <x-heroicon-o-globe-alt class="w-5 h-5 text-blue-600" />
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $region['name'] }}</span>
                    @if ($region['code'])
                        <span class="text-xs text-gray-500">({{ $region['code'] }})</span>
                    @endif
                    <span class="ml-auto text-xs text-gray-500">{{ $region['active_contracts'] }} contracts &middot; {{ $region['active_workflows'] }} workflows</span>
                </button>

                {{-- Entities --}}
                <div x-show="open" x-collapse class="ml-6 mt-1" role="group">
                    @foreach ($region['children'] as $entity)
                        <div class="mb-1" role="treeitem" x-data="{ entityOpen: false }">
                            <button
                                class="flex items-center gap-2 w-full text-left px-3 py-1.5 rounded-lg transition
                                    {{ $entity['highlighted'] ? 'bg-yellow-100 dark:bg-yellow-900 border border-yellow-400' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}
                                    {{ $selectedNodeId === $entity['id'] ? 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900' : '' }}"
                                x-on:click="entityOpen = !entityOpen"
                                wire:click="selectNode('entity', '{{ $entity['id'] }}')"
                                aria-label="Entity: {{ $entity['name'] }}"
                                :aria-expanded="entityOpen.toString()"
                            >
                                <svg class="w-3 h-3 transition-transform" :class="entityOpen ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <x-heroicon-o-building-office class="w-4 h-4 text-indigo-600" />
                                <span class="text-gray-800 dark:text-gray-200">{{ $entity['name'] }}</span>
                                @if ($entity['code'])
                                    <span class="text-xs text-gray-500">({{ $entity['code'] }})</span>
                                @endif
                                <span class="ml-auto text-xs text-gray-500">{{ $entity['active_contracts'] }} contracts &middot; {{ $entity['active_workflows'] }} workflows</span>
                            </button>

                            {{-- Projects --}}
                            <div x-show="entityOpen" x-collapse class="ml-6 mt-1" role="group">
                                @foreach ($entity['children'] as $project)
                                    <button
                                        class="flex items-center gap-2 w-full text-left px-3 py-1.5 rounded-lg transition
                                            {{ $project['highlighted'] ? 'bg-yellow-100 dark:bg-yellow-900 border border-yellow-400' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}
                                            {{ $selectedNodeId === $project['id'] ? 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900' : '' }}"
                                        wire:click="selectNode('project', '{{ $project['id'] }}')"
                                        role="treeitem"
                                        aria-label="Project: {{ $project['name'] }}"
                                    >
                                        <x-heroicon-o-folder class="w-4 h-4 text-green-600" />
                                        <span class="text-gray-700 dark:text-gray-300">{{ $project['name'] }}</span>
                                        @if ($project['code'])
                                            <span class="text-xs text-gray-500">({{ $project['code'] }})</span>
                                        @endif
                                        <span class="ml-auto text-xs text-gray-500">{{ $project['active_contracts'] }} contracts &middot; {{ $project['active_workflows'] }} workflows</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-gray-500 italic">No regions, entities, or projects found.</p>
        @endforelse
    </div>

    {{-- Right panel: Details --}}
    <div class="w-1/3 bg-white dark:bg-gray-800 rounded-xl shadow p-4 overflow-auto" aria-live="polite">
        @if ($selectedNodeId)
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">
                {{ $selectedNodeLabel }}
                <span class="text-xs font-normal text-gray-500 uppercase">{{ $selectedNodeType }}</span>
            </h3>

            {{-- Signing Authorities --}}
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Signing Authorities</h4>
                @if (count($signingAuthorities) > 0)
                    <table class="w-full text-sm" aria-label="Signing authorities for {{ $selectedNodeLabel }}">
                        <thead>
                            <tr class="text-left text-gray-500 border-b dark:border-gray-700">
                                <th class="py-1">Name</th>
                                <th class="py-1">Role</th>
                                <th class="py-1">Type Pattern</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($signingAuthorities as $auth)
                                <tr class="border-b dark:border-gray-700">
                                    <td class="py-1 text-gray-900 dark:text-gray-100">{{ $auth['user_name'] }}</td>
                                    <td class="py-1 text-gray-600 dark:text-gray-400">{{ $auth['role'] }}</td>
                                    <td class="py-1 text-gray-600 dark:text-gray-400">{{ $auth['contract_type_pattern'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-gray-500 text-sm italic">No signing authorities configured.</p>
                @endif
            </div>

            {{-- Workflow Templates --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Workflow Templates</h4>
                @if (count($workflowTemplates) > 0)
                    @foreach ($workflowTemplates as $tpl)
                        <div class="mb-3 border rounded-lg p-3 dark:border-gray-700" x-data="{ expanded: false }">
                            <button
                                class="flex items-center justify-between w-full text-left"
                                x-on:click="expanded = !expanded"
                                :aria-expanded="expanded.toString()"
                            >
                                <div>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $tpl['name'] }}</span>
                                    <span class="text-xs text-gray-500 ml-2">{{ $tpl['contract_type'] }} &middot; {{ $tpl['stage_count'] }} stages</span>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $tpl['status'] === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($tpl['status']) }}
                                </span>
                            </button>

                            <div x-show="expanded" x-collapse class="mt-2">
                                <table class="w-full text-xs" aria-label="Stages for {{ $tpl['name'] }}">
                                    <thead>
                                        <tr class="text-left text-gray-500 border-b dark:border-gray-700">
                                            <th class="py-1">Stage</th>
                                            <th class="py-1">Type</th>
                                            <th class="py-1">Owners</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($tpl['stages'] as $stage)
                                            <tr class="border-b dark:border-gray-700">
                                                <td class="py-1 text-gray-900 dark:text-gray-100">{{ $stage['name'] }}</td>
                                                <td class="py-1">
                                                    <span class="inline-flex px-1.5 py-0.5 rounded text-xs
                                                        {{ in_array($stage['type'], ['signing', 'countersign']) ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                                        {{ $stage['type'] }}
                                                    </span>
                                                </td>
                                                <td class="py-1 text-gray-600 dark:text-gray-400">{{ implode(', ', $stage['owners']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                @else
                    <p class="text-gray-500 text-sm italic">No workflow templates scoped to this node.</p>
                @endif
            </div>
        @else
            <div class="flex flex-col items-center justify-center h-64 text-gray-400">
                <x-heroicon-o-cursor-arrow-rays class="w-12 h-12 mb-2" />
                <p>Select a node to view details</p>
            </div>
        @endif
    </div>
</div>
