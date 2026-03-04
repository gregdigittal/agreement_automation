<?php

namespace App\Filament\Widgets;

use App\Models\AiAnalysisResult;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class AiProcessingBannerWidget extends Widget
{
    protected static string $view = 'filament.widgets.ai-processing-banner';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function getIsProcessing(): bool
    {
        if (! $this->record) {
            return false;
        }

        return AiAnalysisResult::where('contract_id', $this->record->getKey())
            ->where('status', 'processing')
            ->exists();
    }

    public function getProcessingTypes(): array
    {
        if (! $this->record) {
            return [];
        }

        return AiAnalysisResult::where('contract_id', $this->record->getKey())
            ->where('status', 'processing')
            ->pluck('analysis_type')
            ->map(fn (string $type) => ucfirst(str_replace('_', ' ', $type)))
            ->toArray();
    }
}
