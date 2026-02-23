<?php

namespace App\Filament\Pages;

use App\Models\AiAnalysisResult;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AiCostReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static string $view = 'filament.pages.ai-cost-report';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationLabel = 'AI Cost Analytics';
    protected static ?int $navigationSort = 50;

    public function table(Table $table): Table
    {
        return $table
            ->query(AiAnalysisResult::query()->with('contract'))
            ->columns([
                Tables\Columns\TextColumn::make('contract.title')->label('Contract')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('analysis_type')->badge(),
                Tables\Columns\TextColumn::make('model_used')->label('Model'),
                Tables\Columns\TextColumn::make('token_usage_input')->label('Input Tokens')->numeric(thousandsSeparator: ','),
                Tables\Columns\TextColumn::make('token_usage_output')->label('Output Tokens')->numeric(thousandsSeparator: ','),
                Tables\Columns\TextColumn::make('cost_usd')->label('Cost (USD)')->money('USD'),
                Tables\Columns\TextColumn::make('processing_time_ms')->label('Time (ms)')->numeric(thousandsSeparator: ','),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match($state) { 'completed' => 'success', 'failed' => 'danger', 'processing' => 'warning', default => 'gray' }),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('analysis_type')->options([
                    'summary' => 'Summary', 'extraction' => 'Extraction', 'risk' => 'Risk',
                    'deviation' => 'Deviation', 'obligations' => 'Obligations',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function getSummaryStats(): array
    {
        $results = AiAnalysisResult::where('status', 'completed')
            ->selectRaw('SUM(cost_usd) as total_cost, SUM(token_usage_input + token_usage_output) as total_tokens, COUNT(*) as total_analyses')
            ->first();
        return [
            'total_cost' => number_format($results->total_cost ?? 0, 4),
            'total_tokens' => number_format($results->total_tokens ?? 0),
            'total_analyses' => $results->total_analyses ?? 0,
            'avg_cost' => $results->total_analyses > 0 ? number_format(($results->total_cost ?? 0) / $results->total_analyses, 4) : '0.0000',
        ];
    }
}
