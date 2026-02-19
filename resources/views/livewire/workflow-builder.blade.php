<div>
    <div class="space-y-3" x-data="{
        dragging: null,
        dragOver: null,
        startDrag(index) { this.dragging = index; },
        onDragOver(index) { this.dragOver = index; },
        endDrag() {
            if (this.dragging !== null && this.dragOver !== null && this.dragging !== this.dragOver) {
                $wire.updateStageOrder(this.reorder(this.dragging, this.dragOver));
            }
            this.dragging = null;
            this.dragOver = null;
        },
        reorder(from, to) {
            let items = Array.from({length: $wire.stages.length}, (_, i) => i);
            const [moved] = items.splice(from, 1);
            items.splice(to, 0, moved);
            return items;
        }
    }">
        @forelse($stages as $index => $stage)
            <div
                draggable="true"
                x-on:dragstart="startDrag({{ $index }})"
                x-on:dragover.prevent="onDragOver({{ $index }})"
                x-on:drop.prevent="endDrag()"
                class="rounded-lg border bg-white p-4 shadow-sm transition-all"
                :class="{ 'border-primary-500 bg-primary-50': dragOver === {{ $index }} }"
            >
                <div class="flex items-center justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="cursor-grab text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                        </span>
                        <span class="rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-semibold text-primary-700">{{ $stage['order'] ?? $index + 1 }}</span>
                    </div>
                    <button type="button" wire:click="removeStage({{ $index }})" class="text-red-500 hover:text-red-700 text-sm">Remove</button>
                </div>

                <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Stage Name</label>
                        <input type="text" value="{{ $stage['name'] ?? '' }}" wire:change="updateStageField({{ $index }}, 'name', $event.target.value)" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Approver Role</label>
                        <select wire:change="updateStageField({{ $index }}, 'approver_role', $event.target.value)" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="" @selected(empty($stage['approver_role']))>Select role</option>
                            <option value="system_admin" @selected(($stage['approver_role'] ?? '') === 'system_admin')>System Admin</option>
                            <option value="legal" @selected(($stage['approver_role'] ?? '') === 'legal')>Legal</option>
                            <option value="commercial" @selected(($stage['approver_role'] ?? '') === 'commercial')>Commercial</option>
                            <option value="finance" @selected(($stage['approver_role'] ?? '') === 'finance')>Finance</option>
                            <option value="operations" @selected(($stage['approver_role'] ?? '') === 'operations')>Operations</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">SLA (hours)</label>
                        <input type="number" value="{{ $stage['sla_hours'] ?? 24 }}" wire:change="updateStageField({{ $index }}, 'sla_hours', $event.target.value)" class="w-full rounded-md border-gray-300 text-sm shadow-sm" />
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" @checked($stage['required'] ?? true) wire:change="updateStageField({{ $index }}, 'required', $event.target.checked)" class="rounded border-gray-300 text-primary-600" />
                            <span class="text-sm text-gray-600">Required</span>
                        </label>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border-2 border-dashed border-gray-300 p-8 text-center text-gray-500">
                No stages defined. Click "Add Stage" to begin.
            </div>
        @endforelse
    </div>

    <div class="mt-3">
        <button type="button" wire:click="addStage" class="inline-flex items-center gap-1.5 rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Add Stage
        </button>
    </div>
</div>
