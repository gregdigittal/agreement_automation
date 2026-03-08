<div
    x-data="orgStructureTree()"
    x-init="init($wire)"
    class="relative"
>
    {{-- Toolbar --}}
    <div class="flex items-center justify-between mb-4 px-2">
        <div class="flex items-center gap-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Entity Ownership Tree</h3>
            @if ($treeData['truncated'])
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                    Showing first {{ count($treeData['nodes']) }} entities
                </span>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            {{-- Shareholdings toggle --}}
            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 cursor-pointer select-none">
                <input
                    type="checkbox"
                    x-model="showShareholdings"
                    x-on:change="toggleShareholdings()"
                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                    checked
                />
                <span>Shareholdings</span>
            </label>
            {{-- Region swim lanes toggle (Item 1) --}}
            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 cursor-pointer select-none">
                <input
                    type="checkbox"
                    x-model="showSwimLanes"
                    x-on:change="toggleSwimLanes()"
                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                />
                <span>Region Lanes</span>
            </label>
            {{-- Layout direction toggle (Item 2) --}}
            <button
                x-on:click="toggleLayoutDirection()"
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                :title="layoutDirection === 'vertical' ? 'Switch to left-to-right' : 'Switch to top-down'"
            >
                <x-heroicon-m-arrows-right-left class="w-4 h-4" x-show="layoutDirection === 'vertical'" />
                <x-heroicon-m-arrows-up-down class="w-4 h-4" x-show="layoutDirection === 'horizontal'" x-cloak />
                <span x-text="layoutDirection === 'vertical' ? 'L→R' : 'T↓D'"></span>
            </button>
            <button
                x-on:click="fitToScreen()"
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                title="Fit to screen"
            >
                <x-heroicon-m-arrows-pointing-out class="w-4 h-4" />
                Fit
            </button>
            <button
                x-on:click="resetZoom()"
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                title="Reset zoom"
            >
                <x-heroicon-m-magnifying-glass class="w-4 h-4" />
                Reset
            </button>
        </div>
    </div>

    {{-- D3.js Tree Container --}}
    <div
        id="org-structure-svg-container"
        class="w-full bg-white dark:bg-gray-900 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden"
        style="height: 600px;"
    >
        @if (count($treeData['nodes']) === 0)
            <div class="flex items-center justify-center h-full text-gray-400 dark:text-gray-500">
                <div class="text-center">
                    <x-heroicon-o-share class="w-12 h-12 mx-auto mb-2" />
                    <p class="text-sm">No entities found.</p>
                </div>
            </div>
        @else
            <div
                class="flex items-center justify-center h-full text-gray-400 dark:text-gray-500"
                x-ref="treePlaceholder"
                x-show="!treeRendered"
            >
                <div class="text-center">
                    <x-heroicon-o-share class="w-12 h-12 mx-auto mb-2" />
                    <p class="text-sm">Loading organisation structure&hellip;</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Legend --}}
    <div class="flex items-center gap-6 mt-3 px-2 text-xs text-gray-500 dark:text-gray-400">
        <div class="flex items-center gap-1.5">
            <span class="w-6 h-0.5 bg-gray-400 dark:bg-gray-500 inline-block"></span>
            <span>Parent&ndash;Child</span>
        </div>
        <div class="flex items-center gap-1.5">
            <svg width="24" height="4" class="inline-block"><line x1="0" y1="2" x2="24" y2="2" stroke="#3b82f6" stroke-width="2" stroke-dasharray="5 3" /></svg>
            <span>Shareholding</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded bg-primary-500 inline-block"></span>
            <span>Selected</span>
        </div>
    </div>

    {{-- Shareholding editor popover (Step D) --}}
    @if ($editingShareholdingId)
        <div
            x-data="{ show: true }"
            x-show="show"
            x-on:keydown.escape.window="show = false; $wire.cancelEditShareholding()"
            x-on:shareholding-popover-position.window="
                $el.style.left = $event.detail.x + 'px';
                $el.style.top = $event.detail.y + 'px';
            "
            class="absolute z-40 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 p-4 w-64"
            style="left: 50%; top: 50%; transform: translate(-50%, -50%);"
            x-on:click.outside="show = false; $wire.cancelEditShareholding()"
            x-cloak
        >
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Edit Shareholding</h4>
                <button
                    x-on:click="show = false; $wire.cancelEditShareholding()"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <x-heroicon-m-x-mark class="w-4 h-4" />
                </button>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Percentage</label>
                    <input
                        type="number"
                        wire:model="editingPercentage"
                        min="0.01"
                        max="100"
                        step="0.01"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                    <select
                        wire:model="editingOwnershipType"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="direct">Direct</option>
                        <option value="indirect">Indirect</option>
                        <option value="beneficial">Beneficial</option>
                        <option value="nominee">Nominee</option>
                    </select>
                </div>
                {{-- Item 10: Effective date --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Effective Date</label>
                    <input
                        type="date"
                        wire:model="editingEffectiveDate"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>
                {{-- Item 10: Notes --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                    <textarea
                        wire:model="editingNotes"
                        rows="2"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        placeholder="Optional notes…"
                    ></textarea>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <button
                        wire:click="deleteShareholding"
                        wire:confirm="Are you sure you want to delete this shareholding?"
                        class="text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 font-medium"
                    >
                        Delete
                    </button>
                    <div class="flex items-center gap-2">
                        <button
                            wire:click="cancelEditShareholding"
                            class="px-3 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
                        >
                            Cancel
                        </button>
                        <button
                            wire:click="saveShareholding"
                            class="px-3 py-1 text-xs font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700"
                        >
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Add Shareholding form (Item 11) --}}
    @if ($showNewShareholdingForm)
        <div
            x-data="{ show: true }"
            x-show="show"
            x-on:keydown.escape.window="show = false; $wire.cancelNewShareholding()"
            class="absolute z-40 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 p-4 w-72"
            style="left: 50%; top: 50%; transform: translate(-50%, -50%);"
            x-on:click.outside="show = false; $wire.cancelNewShareholding()"
            x-cloak
        >
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Add Shareholding</h4>
                <button
                    x-on:click="show = false; $wire.cancelNewShareholding()"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <x-heroicon-m-x-mark class="w-4 h-4" />
                </button>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Percentage</label>
                    <input
                        type="number"
                        wire:model="newShareholdingPercentage"
                        min="0.01"
                        max="100"
                        step="0.01"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                    <select
                        wire:model="newShareholdingOwnershipType"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="direct">Direct</option>
                        <option value="indirect">Indirect</option>
                        <option value="beneficial">Beneficial</option>
                        <option value="nominee">Nominee</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <button
                        wire:click="cancelNewShareholding"
                        class="px-3 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        wire:click="createShareholding"
                        class="px-3 py-1 text-xs font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700"
                    >
                        Create
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Contracts modal with Filament table (Step E + Items 4, 7) --}}
    @if ($showContractsModal && $selectedEntityId)
        @php
            $selectedEntity = \App\Models\Entity::with(['region', 'parent.shareholdingsOwned' => fn ($q) => $q->where('owned_entity_id', $selectedEntityId)])->find($selectedEntityId);
            $parentShareholding = $selectedEntity?->parent?->shareholdingsOwned->first();
            $stats = $this->contractStats;
        @endphp
        <div
            x-data="{ open: @entangle('showContractsModal'), showLegalDetails: false }"
            x-show="open"
            x-on:keydown.escape.window="open = false; $wire.closeContractsModal()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            x-cloak
        >
            <div
                class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-6xl max-h-[85vh] overflow-auto p-6 mx-4"
                x-on:click.outside="open = false; $wire.closeContractsModal()"
            >
                {{-- Expanded header (Item 4) --}}
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate">
                                {{ $selectedEntity?->name ?? 'Entity' }}
                            </h3>
                            @if ($selectedEntity?->region)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                    {{ $selectedEntity->region->name }}
                                </span>
                            @endif
                            {{-- Edit link --}}
                            <a
                                href="{{ route('filament.admin.resources.entities.edit', $selectedEntityId) }}"
                                class="text-gray-400 hover:text-primary-600 dark:hover:text-primary-400"
                                title="Edit entity"
                                target="_blank"
                            >
                                <x-heroicon-m-pencil-square class="w-4 h-4" />
                            </a>
                        </div>
                        @if ($selectedEntity?->code)
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $selectedEntity->code }}</p>
                        @endif
                        @if ($selectedEntity?->parent)
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                Parent: {{ $selectedEntity->parent->name }}
                                @if ($parentShareholding)
                                    <span class="font-medium text-blue-600 dark:text-blue-400">({{ $parentShareholding->percentage }}% {{ $parentShareholding->ownership_type }})</span>
                                @endif
                            </p>
                        @endif

                        {{-- Collapsible legal details --}}
                        @if ($selectedEntity?->legal_name || $selectedEntity?->registration_number || $selectedEntity?->registered_address)
                            <button
                                x-on:click="showLegalDetails = !showLegalDetails"
                                class="text-xs text-primary-600 dark:text-primary-400 hover:underline mt-1"
                            >
                                <span x-text="showLegalDetails ? 'Hide legal details' : 'Show legal details'"></span>
                            </button>
                            <div x-show="showLegalDetails" x-collapse x-cloak class="mt-2 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                @if ($selectedEntity->legal_name)
                                    <p><span class="font-medium">Legal name:</span> {{ $selectedEntity->legal_name }}</p>
                                @endif
                                @if ($selectedEntity->registration_number)
                                    <p><span class="font-medium">Registration:</span> {{ $selectedEntity->registration_number }}</p>
                                @endif
                                @if ($selectedEntity->registered_address)
                                    <p><span class="font-medium">Address:</span> {{ $selectedEntity->registered_address }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                    <button
                        x-on:click="open = false; $wire.closeContractsModal()"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 ml-4"
                    >
                        <x-heroicon-m-x-mark class="w-5 h-5" />
                    </button>
                </div>

                {{-- Summary stats (Item 7) --}}
                @if ($stats['total'] > 0)
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Total</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $stats['total'] }}</p>
                        </div>
                        @foreach ($stats['by_type'] as $type => $count)
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2">
                                <p class="text-xs text-gray-500 dark:text-gray-400 capitalize">{{ str_replace('_', ' ', $type) }}</p>
                                <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $count }}</p>
                            </div>
                        @endforeach
                    </div>
                    @if (count($stats['by_state']) > 1)
                        <div class="flex flex-wrap gap-2 mb-4">
                            @foreach ($stats['by_state'] as $state => $count)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ match($state) {
                                        'draft' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                        'review' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                        'approval' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                        'signing','countersign' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300',
                                        'executed' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                        'archived' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                    } }}
                                ">
                                    {{ ucfirst($state) }}: {{ $count }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                @endif

                {{ $this->table }}
            </div>
        </div>
    @endif
</div>

@push('scripts')
{{-- D3.js v7 loaded via CDN (matches existing jsdelivr pattern for Mermaid) --}}
<script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
<script>
    /**
     * Alpine.js component for the D3.js organisation-structure tree.
     *
     * Receives a flat { nodes, links, truncated } payload from Livewire,
     * builds a tree hierarchy client-side via d3.stratify(), then renders
     * an interactive SVG with zoom/pan, dark-mode support, and click handlers.
     */
    function orgStructureTree() {
        return {
            wire: null,
            treeData: @js($treeData),
            treeRendered: false,
            selectedEntityId: @js($selectedEntityId),
            canEditShareholdings: @js($this->canEditShareholdings()),
            showShareholdings: true,
            showSwimLanes: false,      // Item 1
            layoutDirection: 'vertical', // Item 2: 'vertical' (top-down) or 'horizontal' (left-to-right)

            // D3 references
            svg: null,
            rootGroup: null,
            zoomBehavior: null,
            initialTransform: null,
            swimLaneGroup: null,       // Item 1

            // Layout constants
            nodeWidth: 190,
            nodeHeight: 72, // Increased to fit project count (Item 5)
            nodePadding: 8,

            init(wire) {
                this.wire = wire;

                if (this.treeData.nodes.length === 0) return;

                // Wait for the DOM to be ready, then render
                this.$nextTick(() => {
                    this.renderTree();
                });

                // Watch for dark-mode toggles on <html> element
                this._darkObserver = new MutationObserver(() => this.applyThemeColors());
                this._darkObserver.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['class'],
                });

                // Listen for shareholding updates to re-render the tree
                Livewire.on('shareholding-updated', () => {
                    this.refreshTree();
                });
            },

            destroy() {
                if (this._darkObserver) this._darkObserver.disconnect();
            },

            /* ----------------------------------------------------------------
             *  Theme helpers
             * -------------------------------------------------------------- */
            isDark() {
                return document.documentElement.classList.contains('dark');
            },

            colors() {
                const dark = this.isDark();
                return {
                    nodeFill:        dark ? '#1f2937' : '#ffffff',   // gray-800 / white
                    nodeStroke:      dark ? '#4b5563' : '#d1d5db',   // gray-600 / gray-300
                    nodeText:        dark ? '#f3f4f6' : '#111827',   // gray-100 / gray-900
                    nodeSubText:     dark ? '#9ca3af' : '#6b7280',   // gray-400 / gray-500
                    selectedFill:    dark ? '#312e81' : '#eef2ff',   // indigo-900 / indigo-50
                    selectedStroke:  dark ? '#6366f1' : '#6366f1',   // indigo-500
                    linkParent:      dark ? '#6b7280' : '#9ca3af',   // gray-500 / gray-400
                    linkSharehold:   '#3b82f6',                      // blue-500
                    linkShareText:   dark ? '#93c5fd' : '#2563eb',   // blue-300 / blue-600
                    badgeFill:       dark ? '#065f46' : '#d1fae5',   // emerald-900 / emerald-100
                    badgeText:       dark ? '#6ee7b7' : '#065f46',   // emerald-300 / emerald-800
                };
            },

            applyThemeColors() {
                if (!this.svg) return;
                const c = this.colors();

                // Update node rectangles
                this.svg.selectAll('.org-node-rect')
                    .attr('fill', d => d.data.id === this.selectedEntityId ? c.selectedFill : c.nodeFill)
                    .attr('stroke', d => d.data.id === this.selectedEntityId ? c.selectedStroke : c.nodeStroke);

                // Update text colors
                this.svg.selectAll('.org-node-name').attr('fill', c.nodeText);
                this.svg.selectAll('.org-node-sub').attr('fill', c.nodeSubText);

                // Update links
                this.svg.selectAll('.link-parent-child').attr('stroke', c.linkParent);
                this.svg.selectAll('.link-shareholding').attr('stroke', c.linkSharehold);
                this.svg.selectAll('.link-share-label').attr('fill', c.linkShareText);

                // Update contract badges
                this.svg.selectAll('.org-badge-rect').attr('fill', c.badgeFill);
                this.svg.selectAll('.org-badge-text').attr('fill', c.badgeText);
            },

            /* ----------------------------------------------------------------
             *  Region colour helper
             * -------------------------------------------------------------- */
            regionColor(regionName) {
                const palette = [
                    '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
                    '#8B5CF6', '#EC4899', '#14B8A6', '#F97316',
                ];
                if (!regionName) return palette[0];
                let hash = 0;
                for (let i = 0; i < regionName.length; i++) {
                    hash = regionName.charCodeAt(i) + ((hash << 5) - hash);
                }
                return palette[Math.abs(hash) % palette.length];
            },

            /* ----------------------------------------------------------------
             *  Tree rendering (Items 1-3, 5, 11)
             * -------------------------------------------------------------- */
            renderTree() {
                const container = document.getElementById('org-structure-svg-container');
                if (!container) return;

                const width = container.clientWidth;
                const height = container.clientHeight;
                const nodes = this.treeData.nodes;
                const links = this.treeData.links;
                const missingShareholdings = this.treeData.missing_shareholdings || [];
                const c = this.colors();
                const isHorizontal = this.layoutDirection === 'horizontal';

                // ---- Build hierarchy from flat nodes ----
                const nodeMap = new Map(nodes.map(n => [n.id, n]));
                const idSet = new Set(nodes.map(n => n.id));

                // Find root nodes: no parent or parent not in dataset
                const rootIds = nodes
                    .filter(n => !n.parent_entity_id || !idSet.has(n.parent_entity_id))
                    .map(n => n.id);

                // Need virtual root when multiple roots exist
                const useVirtualRoot = rootIds.length !== 1;

                // Prepare stratify data
                let stratifyData = nodes.map(n => ({
                    id: n.id,
                    parentId: (n.parent_entity_id && idSet.has(n.parent_entity_id))
                        ? n.parent_entity_id
                        : (useVirtualRoot ? '__vroot__' : null),
                    ...n,
                }));

                if (useVirtualRoot) {
                    stratifyData.unshift({
                        id: '__vroot__',
                        parentId: null,
                        name: 'Organisation',
                        code: '',
                        active_contracts: 0,
                        active_projects: 0,
                        region_name: '',
                    });
                }

                let root;
                try {
                    root = d3.stratify()
                        .id(d => d.id)
                        .parentId(d => d.parentId)(stratifyData);
                } catch (e) {
                    console.error('D3 stratify error:', e);
                    return;
                }

                // ---- Layout (Item 2: support horizontal) ----
                const nodeGapX = this.nodeWidth + 20;
                const nodeGapY = this.nodeHeight + 50;
                const treeLayout = isHorizontal
                    ? d3.tree().nodeSize([nodeGapY * 0.8, nodeGapX + 40])
                    : d3.tree().nodeSize([nodeGapX, nodeGapY]);
                treeLayout(root);

                // ---- SVG setup ----
                this.svg = d3.select(container)
                    .append('svg')
                    .attr('width', '100%')
                    .attr('height', '100%')
                    .attr('viewBox', `0 0 ${width} ${height}`)
                    .style('font-family', 'ui-sans-serif, system-ui, sans-serif');

                this.rootGroup = this.svg.append('g');

                // ---- Swim lanes layer (Item 1) ----
                this.swimLaneGroup = this.rootGroup.append('g').attr('class', 'swim-lanes').style('display', 'none');

                // ---- Zoom / pan ----
                this.zoomBehavior = d3.zoom()
                    .scaleExtent([0.1, 3])
                    .on('zoom', (event) => {
                        this.rootGroup.attr('transform', event.transform);
                    });

                this.svg.call(this.zoomBehavior);

                // Separate parent-child links from shareholding links
                const parentChildLinks = links.filter(l => l.type === 'parent_child');
                const shareholdingLinks = links.filter(l => l.type === 'shareholding');

                // Build lookup from id → tree node position
                const posMap = new Map();
                root.descendants().forEach(d => {
                    if (isHorizontal) {
                        // d3.tree in horizontal: swap x/y
                        posMap.set(d.data.id, { x: d.y, y: d.x });
                    } else {
                        posMap.set(d.data.id, { x: d.x, y: d.y });
                    }
                });

                // ---- Draw parent-child links (tree edges) ----
                const treeNodes = useVirtualRoot ? root.descendants().filter(d => d.data.id !== '__vroot__') : root.descendants();
                const treeLinks = root.links().filter(l => {
                    if (useVirtualRoot && l.source.data.id === '__vroot__') return false;
                    return true;
                });

                const linkGenerator = isHorizontal
                    ? d3.linkHorizontal().x(d => d.y).y(d => d.x)
                    : d3.linkVertical().x(d => d.x).y(d => d.y);

                this.rootGroup.selectAll('.link-parent-child')
                    .data(treeLinks)
                    .enter()
                    .append('path')
                    .attr('class', 'link-parent-child')
                    .attr('d', linkGenerator)
                    .attr('fill', 'none')
                    .attr('stroke', c.linkParent)
                    .attr('stroke-width', 1.5);

                // ---- Draw shareholding links (curved dashed) ----
                const shareGroup = this.rootGroup.append('g').attr('class', 'shareholding-links');
                const self = this;

                shareholdingLinks.forEach(link => {
                    const src = posMap.get(link.source);
                    const tgt = posMap.get(link.target);
                    if (!src || !tgt) return;

                    const dx = tgt.x - src.x;
                    const dy = tgt.y - src.y;
                    const curveOffset = Math.min(Math.abs(isHorizontal ? dy : dx) * 0.3 + 30, 80);

                    let pathData;
                    if (isHorizontal) {
                        pathData = `M ${src.x + this.nodeWidth / 2},${src.y + this.nodeHeight * 0.4}
                            C ${src.x + this.nodeWidth / 2 + dx * 0.3},${src.y + this.nodeHeight * 0.4 + curveOffset}
                              ${tgt.x - dx * 0.3},${tgt.y + this.nodeHeight * 0.4 + curveOffset}
                              ${tgt.x - this.nodeWidth / 2},${tgt.y + this.nodeHeight * 0.4}`;
                    } else {
                        pathData = `M ${src.x + this.nodeWidth * 0.4},${src.y + this.nodeHeight / 2}
                            C ${src.x + this.nodeWidth * 0.4 + curveOffset},${src.y + this.nodeHeight / 2 + dy * 0.3}
                              ${tgt.x + this.nodeWidth * 0.4 + curveOffset},${tgt.y - dy * 0.3}
                              ${tgt.x + this.nodeWidth * 0.4},${tgt.y - this.nodeHeight / 2}`;
                    }

                    // Determine line style based on percentage (Item spec: colour-coded by range)
                    let dashArray = '6 3';
                    let strokeW = 1.5;
                    if (link.percentage >= 100) { dashArray = 'none'; strokeW = 2.5; }
                    else if (link.percentage > 50) { dashArray = 'none'; strokeW = 2; }
                    else if (link.percentage > 25) { dashArray = '8 4'; strokeW = 1.5; }
                    else { dashArray = '3 3'; strokeW = 1; }

                    const linkPath = shareGroup.append('path')
                        .attr('class', 'link-shareholding')
                        .attr('d', pathData)
                        .attr('fill', 'none')
                        .attr('stroke', c.linkSharehold)
                        .attr('stroke-width', strokeW)
                        .attr('stroke-dasharray', dashArray)
                        .attr('data-shareholding-id', link.shareholding_id || '');

                    // Make shareholding links clickable for editing (system_admin only)
                    if (self.canEditShareholdings && link.shareholding_id) {
                        linkPath
                            .style('cursor', 'pointer')
                            .attr('stroke-width', Math.max(strokeW, 3))
                            .on('click', function(event) {
                                event.stopPropagation();
                                self.handleShareholdingClick(link.shareholding_id);
                            })
                            .on('mouseenter', function() {
                                d3.select(this).attr('stroke-width', 4).attr('stroke', '#2563eb');
                            })
                            .on('mouseleave', function() {
                                d3.select(this).attr('stroke-width', Math.max(strokeW, 3)).attr('stroke', c.linkSharehold);
                            });
                    }

                    // Percentage + ownership type label at midpoint
                    if (link.percentage) {
                        let midX, midY;
                        if (isHorizontal) {
                            midX = (src.x + tgt.x) / 2;
                            midY = (src.y + tgt.y) / 2 + this.nodeHeight * 0.4 + curveOffset * 0.5;
                        } else {
                            midX = (src.x + tgt.x) / 2 + this.nodeWidth * 0.4 + curveOffset * 0.5;
                            midY = (src.y + tgt.y) / 2;
                        }

                        shareGroup.append('text')
                            .attr('class', 'link-share-label')
                            .attr('x', midX)
                            .attr('y', midY)
                            .attr('text-anchor', 'middle')
                            .attr('dominant-baseline', 'middle')
                            .attr('fill', c.linkShareText)
                            .attr('font-size', '10px')
                            .attr('font-weight', '600')
                            .text(`${link.percentage}%`);

                        // Ownership type sub-label
                        if (link.ownership_type && link.ownership_type !== 'direct') {
                            shareGroup.append('text')
                                .attr('class', 'link-share-label')
                                .attr('x', midX)
                                .attr('y', midY + 12)
                                .attr('text-anchor', 'middle')
                                .attr('dominant-baseline', 'middle')
                                .attr('fill', c.linkShareText)
                                .attr('font-size', '8px')
                                .attr('font-style', 'italic')
                                .text(link.ownership_type);
                        }
                    }
                });

                // ---- "Add Shareholding" prompts on parent-child links without shareholdings (Item 11) ----
                if (self.canEditShareholdings && missingShareholdings.length > 0) {
                    const addGroup = this.rootGroup.append('g').attr('class', 'add-shareholding-prompts');

                    missingShareholdings.forEach(pair => {
                        const src = posMap.get(pair.owner_entity_id);
                        const tgt = posMap.get(pair.owned_entity_id);
                        if (!src || !tgt) return;

                        const midX = (src.x + tgt.x) / 2;
                        const midY = (src.y + tgt.y) / 2;

                        const promptGroup = addGroup.append('g')
                            .attr('transform', `translate(${midX}, ${midY})`)
                            .style('cursor', 'pointer')
                            .on('click', function(event) {
                                event.stopPropagation();
                                self.wire.openNewShareholdingForm(pair.owner_entity_id, pair.owned_entity_id);
                            });

                        promptGroup.append('rect')
                            .attr('x', -32)
                            .attr('y', -10)
                            .attr('width', 64)
                            .attr('height', 20)
                            .attr('rx', 10)
                            .attr('fill', self.isDark() ? '#374151' : '#f3f4f6')
                            .attr('stroke', '#9ca3af')
                            .attr('stroke-width', 1)
                            .attr('stroke-dasharray', '3 2');

                        promptGroup.append('text')
                            .attr('text-anchor', 'middle')
                            .attr('dominant-baseline', 'middle')
                            .attr('fill', '#9ca3af')
                            .attr('font-size', '9px')
                            .text('+ Add %');

                        promptGroup
                            .on('mouseenter', function() {
                                d3.select(this).select('rect').attr('stroke', '#6366f1').attr('fill', self.isDark() ? '#4338ca' : '#eef2ff');
                                d3.select(this).select('text').attr('fill', '#6366f1');
                            })
                            .on('mouseleave', function() {
                                d3.select(this).select('rect').attr('stroke', '#9ca3af').attr('fill', self.isDark() ? '#374151' : '#f3f4f6');
                                d3.select(this).select('text').attr('fill', '#9ca3af');
                            });
                    });
                }

                // ---- Draw nodes ----
                const nodeGroups = this.rootGroup.selectAll('.org-node')
                    .data(treeNodes)
                    .enter()
                    .append('g')
                    .attr('class', 'org-node')
                    .attr('transform', d => {
                        const pos = posMap.get(d.data.id);
                        return `translate(${pos.x - this.nodeWidth / 2}, ${pos.y - this.nodeHeight / 2})`;
                    })
                    .style('cursor', 'pointer')
                    .on('click', function(event, d) {
                        event.stopPropagation();
                        self.handleNodeClick(d.data.id);
                    })
                    .on('dblclick', function(event, d) {
                        event.stopPropagation();
                        self.handleNodeDblClick(d.data.id);
                    });

                // Item 3: Draggable nodes
                const drag = d3.drag()
                    .on('start', function(event, d) {
                        d3.select(this).raise().classed('dragging', true);
                    })
                    .on('drag', function(event, d) {
                        const pos = posMap.get(d.data.id);
                        pos.x += event.dx;
                        pos.y += event.dy;
                        d3.select(this).attr('transform', `translate(${pos.x - self.nodeWidth / 2}, ${pos.y - self.nodeHeight / 2})`);
                    })
                    .on('end', function(event, d) {
                        d3.select(this).classed('dragging', false);
                    });

                nodeGroups.call(drag);

                // Node rectangle with hover shadow
                nodeGroups.append('rect')
                    .attr('class', 'org-node-rect')
                    .attr('width', this.nodeWidth)
                    .attr('height', this.nodeHeight)
                    .attr('rx', 8)
                    .attr('ry', 8)
                    .attr('fill', d => d.data.id === this.selectedEntityId ? c.selectedFill : c.nodeFill)
                    .attr('stroke', d => d.data.id === this.selectedEntityId ? c.selectedStroke : c.nodeStroke)
                    .attr('stroke-width', 1.5)
                    .attr('filter', 'drop-shadow(0 1px 2px rgba(0,0,0,0.1))');

                // Region colour bar on left edge
                nodeGroups.append('rect')
                    .attr('x', 0)
                    .attr('y', 0)
                    .attr('width', 4)
                    .attr('height', this.nodeHeight)
                    .attr('rx', 2)
                    .attr('fill', d => this.regionColor(d.data.region_name));

                // Entity name (primary text)
                nodeGroups.append('text')
                    .attr('class', 'org-node-name')
                    .attr('x', this.nodePadding + 4)
                    .attr('y', 20)
                    .attr('fill', c.nodeText)
                    .attr('font-size', '12px')
                    .attr('font-weight', '600')
                    .text(d => {
                        const name = d.data.name || '';
                        return name.length > 22 ? name.substring(0, 20) + '…' : name;
                    })
                    .append('title')
                    .text(d => d.data.name);

                // Subtitle: code + region
                nodeGroups.append('text')
                    .attr('class', 'org-node-sub')
                    .attr('x', this.nodePadding + 4)
                    .attr('y', 36)
                    .attr('fill', c.nodeSubText)
                    .attr('font-size', '10px')
                    .text(d => {
                        const parts = [];
                        if (d.data.code) parts.push(d.data.code);
                        if (d.data.region_name) parts.push(d.data.region_name);
                        const sub = parts.join(' · ');
                        return sub.length > 26 ? sub.substring(0, 24) + '…' : sub;
                    });

                // Counts row: contracts + projects (Item 5)
                nodeGroups.append('text')
                    .attr('class', 'org-node-sub')
                    .attr('x', this.nodePadding + 4)
                    .attr('y', 52)
                    .attr('fill', c.nodeSubText)
                    .attr('font-size', '9px')
                    .text(d => {
                        const parts = [];
                        const contracts = d.data.active_contracts || 0;
                        const projects = d.data.active_projects || 0;
                        if (contracts > 0) parts.push(`📄 ${contracts}`);
                        if (projects > 0) parts.push(`📁 ${projects}`);
                        return parts.join('  ');
                    });

                // "View Details" clickable label (bottom-right)
                nodeGroups.append('text')
                    .attr('x', this.nodeWidth - this.nodePadding)
                    .attr('y', this.nodeHeight - 8)
                    .attr('text-anchor', 'end')
                    .attr('fill', '#6366f1')
                    .attr('font-size', '9px')
                    .attr('font-weight', '500')
                    .style('cursor', 'pointer')
                    .text('View Details ›')
                    .on('click', function(event, d) {
                        event.stopPropagation();
                        self.handleNodeDblClick(d.data.id);
                    });

                // Contract count badge (top-right corner)
                const badgeNodes = nodeGroups.filter(d => d.data.active_contracts > 0);

                badgeNodes.append('rect')
                    .attr('class', 'org-badge-rect')
                    .attr('x', this.nodeWidth - 32)
                    .attr('y', 4)
                    .attr('width', 28)
                    .attr('height', 16)
                    .attr('rx', 8)
                    .attr('fill', c.badgeFill);

                badgeNodes.append('text')
                    .attr('class', 'org-badge-text')
                    .attr('x', this.nodeWidth - 18)
                    .attr('y', 15)
                    .attr('text-anchor', 'middle')
                    .attr('fill', c.badgeText)
                    .attr('font-size', '10px')
                    .attr('font-weight', '600')
                    .text(d => d.data.active_contracts);

                // ---- Swim lanes (Item 1) ----
                this.drawSwimLanes(treeNodes, posMap);

                // ---- Initial positioning ----
                this.treeRendered = true;

                // Fit the tree into view after first render
                this.$nextTick(() => {
                    this.fitToScreen();
                    const currentTransform = d3.zoomTransform(this.svg.node());
                    this.initialTransform = currentTransform;
                });
            },

            /* ----------------------------------------------------------------
             *  Region swim lanes (Item 1)
             * -------------------------------------------------------------- */
            drawSwimLanes(treeNodes, posMap) {
                if (!this.swimLaneGroup) return;
                this.swimLaneGroup.selectAll('*').remove();

                // Group nodes by region
                const regionBounds = {};
                treeNodes.forEach(d => {
                    const region = d.data.region_name || 'Unassigned';
                    const pos = posMap.get(d.data.id);
                    if (!pos) return;
                    if (!regionBounds[region]) {
                        regionBounds[region] = { minX: Infinity, maxX: -Infinity, minY: Infinity, maxY: -Infinity };
                    }
                    const b = regionBounds[region];
                    b.minX = Math.min(b.minX, pos.x - this.nodeWidth / 2);
                    b.maxX = Math.max(b.maxX, pos.x + this.nodeWidth / 2);
                    b.minY = Math.min(b.minY, pos.y - this.nodeHeight / 2);
                    b.maxY = Math.max(b.maxY, pos.y + this.nodeHeight / 2);
                });

                const padding = 20;
                Object.entries(regionBounds).forEach(([region, b]) => {
                    const color = this.regionColor(region);
                    this.swimLaneGroup.append('rect')
                        .attr('x', b.minX - padding)
                        .attr('y', b.minY - padding - 16)
                        .attr('width', b.maxX - b.minX + padding * 2)
                        .attr('height', b.maxY - b.minY + padding * 2 + 16)
                        .attr('rx', 12)
                        .attr('fill', color)
                        .attr('fill-opacity', 0.06)
                        .attr('stroke', color)
                        .attr('stroke-opacity', 0.2)
                        .attr('stroke-width', 1);

                    this.swimLaneGroup.append('text')
                        .attr('x', b.minX - padding + 8)
                        .attr('y', b.minY - padding - 4)
                        .attr('fill', color)
                        .attr('font-size', '11px')
                        .attr('font-weight', '600')
                        .attr('fill-opacity', 0.7)
                        .text(region);
                });
            },

            /* ----------------------------------------------------------------
             *  Zoom controls
             * -------------------------------------------------------------- */
            fitToScreen() {
                if (!this.svg || !this.rootGroup) return;

                const container = document.getElementById('org-structure-svg-container');
                const width = container.clientWidth;
                const height = container.clientHeight;

                // Get the bounding box of all rendered content
                const bounds = this.rootGroup.node().getBBox();

                if (bounds.width === 0 || bounds.height === 0) return;

                const padding = 40;
                const scaleX = (width - padding * 2) / bounds.width;
                const scaleY = (height - padding * 2) / bounds.height;
                const scale = Math.min(scaleX, scaleY, 1.5); // cap at 1.5x

                const centerX = bounds.x + bounds.width / 2;
                const centerY = bounds.y + bounds.height / 2;

                const transform = d3.zoomIdentity
                    .translate(width / 2, height / 2)
                    .scale(scale)
                    .translate(-centerX, -centerY);

                this.svg.transition()
                    .duration(500)
                    .call(this.zoomBehavior.transform, transform);
            },

            resetZoom() {
                if (!this.svg || !this.zoomBehavior) return;

                if (this.initialTransform) {
                    this.svg.transition()
                        .duration(500)
                        .call(this.zoomBehavior.transform, this.initialTransform);
                } else {
                    this.fitToScreen();
                }
            },

            /* ----------------------------------------------------------------
             *  Toggles
             * -------------------------------------------------------------- */
            toggleShareholdings() {
                if (!this.svg) return;
                const visible = this.showShareholdings;
                this.svg.selectAll('.shareholding-links')
                    .style('display', visible ? 'block' : 'none');
            },

            toggleSwimLanes() {
                if (!this.swimLaneGroup) return;
                this.swimLaneGroup.style('display', this.showSwimLanes ? 'block' : 'none');
            },

            toggleLayoutDirection() {
                this.layoutDirection = this.layoutDirection === 'vertical' ? 'horizontal' : 'vertical';
                // Re-render the tree with the new layout direction
                const container = document.getElementById('org-structure-svg-container');
                if (container) {
                    const existingSvg = container.querySelector('svg');
                    if (existingSvg) existingSvg.remove();
                }
                this.treeRendered = false;
                this.$nextTick(() => {
                    if (this.treeData && this.treeData.nodes && this.treeData.nodes.length > 0) {
                        this.renderTree();
                    }
                });
            },

            /* ----------------------------------------------------------------
             *  Click handlers
             * -------------------------------------------------------------- */
            handleNodeClick(entityId) {
                this.selectedEntityId = entityId;
                this.wire.selectEntity(entityId);
                this.applyThemeColors(); // re-apply to highlight selected
            },

            handleNodeDblClick(entityId) {
                this.wire.openContractsModal(entityId);
            },

            handleShareholdingClick(shareholdingId) {
                if (!this.canEditShareholdings) return;
                this.wire.editShareholding(shareholdingId);
            },

            /**
             * Re-fetch tree data from Livewire and re-render the SVG.
             */
            refreshTree() {
                // Remove the existing SVG
                const container = document.getElementById('org-structure-svg-container');
                if (container) {
                    const existingSvg = container.querySelector('svg');
                    if (existingSvg) existingSvg.remove();
                }

                this.treeRendered = false;

                // Fetch fresh data from the Livewire component
                this.wire.$refresh().then(() => {
                    this.$nextTick(() => {
                        // Re-read the treeData from the Livewire component
                        this.treeData = this.wire.$get('treeData') || this.treeData;
                        if (this.treeData.nodes.length > 0) {
                            this.renderTree();
                            // Re-apply toggle states after fresh render
                            this.toggleSwimLanes();
                            this.toggleShareholdings();
                        }
                    });
                });
            },
        };
    }
</script>
@endpush
