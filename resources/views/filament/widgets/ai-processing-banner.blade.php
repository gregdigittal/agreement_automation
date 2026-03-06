@php
    $isProcessing = $this->getIsProcessing();
    $processingTypes = $this->getProcessingTypes();
@endphp

<div wire:poll{{ $isProcessing ? '.4s' : '.10s' }}>
    @if ($isProcessing)
    <div
        x-data="{
            messages: [
                'Rebuilding the flux capacitor...',
                'Charging the fluggelbinder...',
                'Teaching the AI to read legalese...',
                'Translating contract jargon to English...',
                'Counting all the \u201cwhereas\u201d clauses...',
                'Looking for the fine print... and the finer print...',
                'Reticulating legal splines...',
                'Herding semicolons into proper clauses...',
                'Warming up the legal-to-human translator...',
                'Negotiating with the server hamsters...',
                'Untangling nested subclauses...',
                'Feeding the document to our trained velociraptors...',
                'Calibrating the agreement-ometer...',
                'Turning coffee into contract analysis...',
                'Asking the AI nicely to hurry up...',
                'Deciphering hieroglyphic indemnification clauses...',
                'Running compliance checks at warp speed...',
                'Consulting the ancient scrolls of case law...',
                'Performing quantum contract entanglement...',
                'Convincing the algorithm this isn\'t a grocery list...',
                'Cross-referencing with the Library of Babel...',
                'Summoning the spirit of every lawyer who ever lived...',
                'Making sure nobody sold their soul in the fine print...',
                'Almost there... probably... maybe...',
            ],
            current: 0,
            init() {
                this.current = Math.floor(Math.random() * this.messages.length);
                setInterval(() => {
                    this.current = (this.current + 1) % this.messages.length;
                }, 3500);
            }
        }"
        x-cloak
        class="mb-4"
    >
        <div class="rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50 via-blue-50 to-indigo-50 p-4 shadow-sm dark:border-indigo-700 dark:from-indigo-950/50 dark:via-blue-950/50 dark:to-indigo-950/50">
            <div class="flex items-center gap-4">
                {{-- Animated spinner --}}
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 animate-spin text-indigo-500 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <div class="min-w-0 flex-1">
                    {{-- Rotating humorous message --}}
                    <p
                        x-text="messages[current]"
                        class="text-sm font-semibold text-indigo-700 dark:text-indigo-300"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                    ></p>

                    {{-- What's actually running --}}
                    <p class="mt-0.5 text-xs text-indigo-500/80 dark:text-indigo-400/70">
                        <span class="inline-flex items-center gap-1">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-indigo-500"></span>
                            </span>
                            Running: {{ implode(', ', $processingTypes) }}
                        </span>
                    </p>
                </div>

                {{-- AI sparkle icon --}}
                <div class="flex-shrink-0">
                    <x-heroicon-o-sparkles class="h-5 w-5 text-indigo-400/60 dark:text-indigo-500/60" />
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
