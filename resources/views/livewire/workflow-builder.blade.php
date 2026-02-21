<div
    x-data="{
        dragIndex: null,
        dragOver: null,
        startDrag(index) { this.dragIndex = index; },
        onDragOver(index) { this.dragOver = index; },
        endDrag() {
            if (this.dragIndex !== null && this.dragOver !== null && this.dragIndex !== this.dragOver) {
                const ids = Array.from(document.querySelectorAll('[data-stage-id]'))
                    .map(el => el.dataset.stageId);
                const moved = ids.splice(this.dragIndex, 1)[0];
                ids.splice(this.dragOver, 0, moved);
                $wire.reorderStages(ids);
            }
            this.dragIndex = null;
            this.dragOver = null;
        }
    }"
    class="space-y-3"
>
    @foreach ($stages as $index => $stage)
        <div
            data-stage-id="{{ $stage['id'] }}"
            draggable="true"
            @dragstart="startDrag({{ $index }})"
            @dragover.prevent="onDragOver({{ $index }})"
            @dragend="endDrag()"
            :class="dragOver === {{ $index }} ? 'ring-2 ring-primary-500' : ''"
            class="flex items-start gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 cursor-grab"
        >
            <div class="mt-1 text-gray-400 cursor-grab">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 8h16M4 16h16"/>
                </svg>
            </div>

            <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                {{ $index + 1 }}
            </div>

            <div class="grid flex-1 grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="col-span-2 sm:col-span-1">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Stage Name</label>
                    <input
                        type="text"
                        value="{{ $stage['name'] ?? '' }}"
                        wire:change="updateStage({{ $index }}, 'name', $event.target.value)"
                        class="w-full rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Role</label>
                    <select
                        wire:change="updateStage({{ $index }}, 'role', $event.target.value)"
                        class="w-full rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700"
                    >
                        @foreach (['legal', 'commercial', 'finance', 'operations', 'system_admin'] as $role)
                            <option value="{{ $role }}" {{ ($stage['role'] ?? '') === $role ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $role)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Days</label>
                    <input
                        type="number"
                        min="1"
                        value="{{ $stage['duration_days'] ?? 5 }}"
                        wire:change="updateStage({{ $index }}, 'duration_days', (int) $event.target.value)"
                        class="w-full rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700"
                    />
                </div>

                <div class="flex items-center gap-2 pt-5">
                    <input
                        type="checkbox"
                        id="approval_{{ $index }}"
                        {{ ($stage['is_approval'] ?? false) ? 'checked' : '' }}
                        wire:change="updateStage({{ $index }}, 'is_approval', $event.target.checked)"
                        class="h-4 w-4 rounded"
                    />
                    <label for="approval_{{ $index }}" class="text-xs text-gray-600 dark:text-gray-400">Approval</label>
                </div>
            </div>

            <button
                type="button"
                wire:click="removeStage({{ $index }})"
                class="mt-1 text-red-400 hover:text-red-600"
                title="Remove stage"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endforeach

    @if(count($stages) > 1)
        <div class="pl-10 text-xs text-gray-400 dark:text-gray-500 select-none">
            {{ count($stages) }} stages · drag cards to reorder
        </div>
    @endif

    <button
        type="button"
        wire:click="addStage"
        class="flex items-center gap-2 rounded-md border-2 border-dashed border-gray-300 px-4 py-2 text-sm text-gray-500 hover:border-primary-400 hover:text-primary-600 dark:border-gray-600"
    >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Stage
    </button>
</div>
