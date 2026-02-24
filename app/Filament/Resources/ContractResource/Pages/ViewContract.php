<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Models\Contract;
use App\Services\RegulatoryComplianceService;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;


    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Contract Status')
                    ->schema([
                        Components\TextEntry::make('workflow_state')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'executed' => 'success',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'warning',
                            }),
                    ])
                    ->visible(fn (Contract $record): bool => in_array($record->workflow_state, ['executed', 'completed']))
                    ->columnSpanFull(),
                Components\Section::make("Linked From")
                    ->visible(fn (Contract $record) => $record->parentContract !== null)
                    ->schema([
                        Components\TextEntry::make("parentContract.link_type")
                            ->label("Link Type")
                            ->badge(),
                        Components\TextEntry::make("parentContract.parentContract.title")
                            ->label("Parent Contract")
                            ->url(fn (Contract $record) => ContractResource::getUrl("edit", ["record" => $record->parentContract->parentContract])),
                    ])
                    ->columns(2),
                Components\Section::make("SharePoint")
                    ->visible(fn (Contract $record) => ! empty($record->sharepoint_url))
                    ->schema([
                        Components\TextEntry::make("sharepoint_url")
                            ->label("Document URL")
                            ->url()
                            ->openUrlInNewTab(),
                        Components\TextEntry::make("sharepoint_version")
                            ->label("Version"),
                    ])
                    ->columns(2),
                Components\Section::make('Compliance Overview')
                    ->visible(fn (Contract $record): bool => config('features.regulatory_compliance', false)
                        && $record->complianceFindings()->exists())
                    ->schema(function (Contract $record): array {
                        $scores = app(RegulatoryComplianceService::class)->getScoreSummary($record);
                        $entries = [];
                        foreach ($scores as $frameworkId => $score) {
                            $framework = \App\Models\RegulatoryFramework::find($frameworkId);
                            $name = $framework?->framework_name ?? $frameworkId;
                            $label = "{$score['score']}% compliant ({$score['compliant']}/{$score['total']}"
                                . " — {$score['non_compliant']} non-compliant, {$score['unclear']} unclear)";
                            $color = match (true) {
                                $score['score'] >= 80.0 => 'success',
                                $score['score'] >= 50.0 => 'warning',
                                default => 'danger',
                            };
                            $entries[] = Components\TextEntry::make("compliance_score_{$frameworkId}")
                                ->label($name)
                                ->state($label)
                                ->badge()
                                ->color($color);
                        }
                        return $entries ?: [
                            Components\TextEntry::make('no_compliance_scores')
                                ->label('')
                                ->state('No compliance data available'),
                        ];
                    })
                    ->columnSpanFull(),
            ]);
    }
}
