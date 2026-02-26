<x-filament-panels::page>
    @if (! config('features.advanced_analytics', false))
        <div class="text-center text-gray-500 py-12">
            Advanced Analytics is not enabled. Set <code>FEATURE_ADVANCED_ANALYTICS=true</code> in your environment.
        </div>
    @else
        <x-filament-widgets::widgets
            :widgets="$this->getVisibleHeaderWidgets()"
            :columns="$this->getHeaderWidgetsColumns()"
        />
    @endif
</x-filament-panels::page>
