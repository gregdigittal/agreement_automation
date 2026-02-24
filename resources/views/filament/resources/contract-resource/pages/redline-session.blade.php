<x-filament-panels::page>
    {{-- Session Header --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                    {{ $session->contract->title }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Template: {{ $session->wikiContract?->name ?? 'N/A' }}
                    &middot;
                    Started by {{ $session->creator?->name ?? 'Unknown' }}
                    &middot;
                    {{ $session->created_at->diffForHumans() }}
                </p>
            </div>
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    @switch($session->status)
                        @case('pending') bg-yellow-100 text-yellow-800 @break
                        @case('processing') bg-blue-100 text-blue-800 @break
                        @case('completed') bg-green-100 text-green-800 @break
                        @case('failed') bg-red-100 text-red-800 @break
                    @endswitch
                ">
                    {{ ucfirst($session->status) }}
                </span>
            </div>
        </div>

        {{-- Progress Bar --}}
        @if ($session->status === 'completed')
            <div class="mt-4">
                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                    <span>Review Progress</span>
                    <span>{{ $session->reviewed_clauses }} / {{ $session->total_clauses }} clauses reviewed ({{ $session->progress_percentage }}%)</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3"
                     role="progressbar"
                     aria-label="Review Progress"
                     aria-valuenow="{{ $session->progress_percentage }}"
                     aria-valuemin="0"
                     aria-valuemax="100"
                >
                    <div
                        class="bg-primary-600 h-3 rounded-full transition-all duration-300"
                        style="width: {{ $session->progress_percentage }}%"
                    ></div>
                </div>
            </div>
        @endif

        {{-- Error Message --}}
        @if ($session->status === 'failed' && $session->error_message)
            <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-sm text-red-800 dark:text-red-200">
                    <strong>Error:</strong> {{ $session->error_message }}
                </p>
            </div>
        @endif

        {{-- Summary --}}
        @if ($session->status === 'completed' && $session->summary)
            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">AI Analysis Summary</h4>
                @if (isset($session->summary['overall_assessment']))
                    <p class="text-sm text-blue-800 dark:text-blue-200 mb-2">{{ $session->summary['overall_assessment'] }}</p>
                @endif
                @if (isset($session->summary['material_risk_areas']) && count($session->summary['material_risk_areas']) > 0)
                    <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 mt-2">Material Risk Areas:</p>
                    <ul class="list-disc list-inside text-sm text-blue-700 dark:text-blue-300">
                        @foreach ($session->summary['material_risk_areas'] as $risk)
                            <li>{{ $risk }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="flex gap-4 mt-3 text-xs text-blue-600 dark:text-blue-400">
                    <span>Unchanged: {{ $session->summary['unchanged'] ?? 0 }}</span>
                    <span>Modifications: {{ $session->summary['modifications'] ?? 0 }}</span>
                    <span>Deletions: {{ $session->summary['deletions'] ?? 0 }}</span>
                    <span>Additions: {{ $session->summary['additions'] ?? 0 }}</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Pending/Processing State --}}
    @if (in_array($session->status, ['pending', 'processing']))
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-12 text-center" wire:poll.5s="$refresh">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto mb-4"></div>
            <p class="text-gray-600 dark:text-gray-400">
                AI is analyzing the contract clauses against the template...
            </p>
            <p class="text-sm text-gray-500 mt-2">
                This page will update automatically when the analysis is complete.
            </p>
        </div>
    @endif

    {{-- Clause-by-Clause Diff View --}}
    @if ($session->status === 'completed')
        <div class="space-y-6">
            @foreach ($session->clauses as $clause)
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden
                        @if ($clause->status !== 'pending') ring-1
                            @if ($clause->status === 'accepted') ring-green-300 dark:ring-green-700
                            @elseif ($clause->status === 'rejected') ring-red-300 dark:ring-red-700
                            @elseif ($clause->status === 'modified') ring-amber-300 dark:ring-amber-700
                            @endif
                        @endif"
                    id="clause-{{ $clause->clause_number }}"
                >
                    {{-- Clause Header --}}
                    <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-b dark:border-gray-700 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300">
                                Clause {{ $clause->clause_number }}
                            </span>
                            @if ($clause->clause_heading)
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    &mdash; {{ $clause->clause_heading }}
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            {{-- Change Type Badge --}}
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                @switch($clause->change_type)
                                    @case('unchanged') bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 @break
                                    @case('addition') bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 @break
                                    @case('deletion') bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 @break
                                    @case('modification') bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300 @break
                                @endswitch
                            ">
                                {{ ucfirst($clause->change_type) }}
                            </span>

                            {{-- Confidence Badge --}}
                            @if ($clause->confidence !== null)
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                    {{ $clause->confidence >= 0.8 ? 'bg-green-100 text-green-700' : ($clause->confidence >= 0.5 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                                    {{ number_format($clause->confidence * 100) }}% confident
                                </span>
                            @endif

                            {{-- Review Status Badge --}}
                            @if ($clause->status !== 'pending')
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                    @switch($clause->status)
                                        @case('accepted') bg-green-600 text-white @break
                                        @case('rejected') bg-red-600 text-white @break
                                        @case('modified') bg-amber-600 text-white @break
                                    @endswitch
                                ">
                                    {{ ucfirst($clause->status) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Two-Column Diff View --}}
                    @if ($clause->change_type !== 'unchanged')
                        <div class="grid grid-cols-2 divide-x dark:divide-gray-700">
                            {{-- Left: Original (Contract) --}}
                            <div class="p-4">
                                <h5 class="text-xs font-semibold uppercase text-gray-500 mb-2">Original (Contract)</h5>
                                <div class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap bg-red-50 dark:bg-red-900/10 p-3 rounded border border-red-100 dark:border-red-900">
                                    {{ $clause->original_text }}
                                </div>
                            </div>

                            {{-- Right: Suggested (Template) --}}
                            <div class="p-4">
                                <h5 class="text-xs font-semibold uppercase text-gray-500 mb-2">Suggested (Template)</h5>
                                <div class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap bg-green-50 dark:bg-green-900/10 p-3 rounded border border-green-100 dark:border-green-900">
                                    {{ $clause->suggested_text ?? '(No suggested text)' }}
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Unchanged clause — show single column --}}
                        <div class="p-4">
                            <h5 class="text-xs font-semibold uppercase text-gray-500 mb-2">Clause Text (Unchanged)</h5>
                            <div class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">
                                {{ $clause->original_text }}
                            </div>
                        </div>
                    @endif

                    {{-- AI Rationale --}}
                    @if ($clause->ai_rationale)
                        <div class="px-6 py-3 bg-yellow-50 dark:bg-yellow-900/10 border-t dark:border-gray-700">
                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                <strong>AI Rationale:</strong> {{ $clause->ai_rationale }}
                            </p>
                        </div>
                    @endif

                    {{-- Action Buttons --}}
                    @if ($clause->change_type !== 'unchanged' && $clause->status === 'pending')
                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t dark:border-gray-700 flex items-center gap-3">
                            <button
                                wire:click="acceptClause('{{ $clause->id }}')"
                                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                aria-label="Accept clause {{ $clause->clause_number }}"
                            >
                                <x-heroicon-s-check class="w-4 h-4 mr-1" />
                                Accept
                            </button>

                            <button
                                wire:click="rejectClause('{{ $clause->id }}')"
                                class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                aria-label="Reject clause {{ $clause->clause_number }}"
                            >
                                <x-heroicon-s-x-mark class="w-4 h-4 mr-1" />
                                Reject
                            </button>

                            <div x-data="{ editing: false, modifiedText: @js($clause->suggested_text ?? $clause->original_text) }" class="flex-1">
                                <button
                                    x-show="!editing"
                                    x-on:click="editing = true"
                                    class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg transition focus:ring-2 focus:ring-offset-2 focus:ring-amber-500"
                                    aria-label="Modify clause {{ $clause->clause_number }}"
                                >
                                    <x-heroicon-s-pencil class="w-4 h-4 mr-1" />
                                    Modify
                                </button>

                                <div x-show="editing" x-cloak class="flex-1">
                                    <textarea
                                        x-model="modifiedText"
                                        class="w-full border border-amber-300 rounded-lg p-3 text-sm dark:bg-gray-800 dark:border-amber-700 dark:text-gray-200 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                        rows="5"
                                        aria-label="Modified text for clause {{ $clause->clause_number }}"
                                    ></textarea>
                                    <div class="flex gap-2 mt-2">
                                        <button
                                            x-on:click="$wire.modifyClause('{{ $clause->id }}', modifiedText); editing = false"
                                            class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-sm rounded-lg transition"
                                        >
                                            Save Modification
                                        </button>
                                        <button
                                            x-on:click="editing = false"
                                            class="px-3 py-1.5 bg-gray-300 hover:bg-gray-400 text-gray-700 text-sm rounded-lg transition"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif ($clause->status !== 'pending')
                        {{-- Already reviewed — show final text --}}
                        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-t dark:border-gray-700">
                            <p class="text-xs text-gray-500">
                                Reviewed by {{ $clause->reviewer?->name ?? 'Unknown' }}
                                {{ $clause->reviewed_at?->diffForHumans() }}
                                &middot;
                                {{ ucfirst($clause->status) }}
                            </p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
