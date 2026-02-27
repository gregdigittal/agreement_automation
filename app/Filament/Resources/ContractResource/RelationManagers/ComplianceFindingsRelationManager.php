<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Services\RegulatoryComplianceService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ComplianceFindingsRelationManager extends RelationManager
{
    protected static string $relationship = 'complianceFindings';

    protected static ?string $title = 'Compliance Findings';

    protected static ?string $icon = 'heroicon-o-shield-check';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return config('features.regulatory_compliance', false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('framework.framework_name')
                    ->label('Framework')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('requirement_id')
                    ->label('Req. ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('requirement_text')
                    ->label('Requirement')
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->requirement_text)
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'compliant' => 'success',
                        'non_compliant' => 'danger',
                        'unclear' => 'warning',
                        'not_applicable' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('evidence_clause')
                    ->label('Evidence')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->evidence_clause)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ai_rationale')
                    ->label('AI Rationale')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->ai_rationale)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('confidence')
                    ->label('Confidence')
                    ->formatStateUsing(fn (?float $state): string => $state !== null ? round($state * 100) . '%' : '—')
                    ->badge()
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 0.8 => 'success',
                        $state >= 0.5 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('reviewer.name')
                    ->label('Reviewed By')
                    ->placeholder('Not reviewed')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Reviewed At')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('requirement_id')
            ->groups([
                Tables\Grouping\Group::make('framework.framework_name')
                    ->label('Framework')
                    ->collapsible(),
            ])
            ->defaultGroup('framework.framework_name')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'compliant' => 'Compliant',
                        'non_compliant' => 'Non-Compliant',
                        'unclear' => 'Unclear',
                        'not_applicable' => 'Not Applicable',
                    ]),
                Tables\Filters\SelectFilter::make('framework_id')
                    ->label('Framework')
                    ->relationship('framework', 'framework_name'),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['system_admin', 'legal']))
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Override Status')
                            ->options([
                                'compliant' => 'Compliant',
                                'non_compliant' => 'Non-Compliant',
                                'unclear' => 'Unclear',
                                'not_applicable' => 'Not Applicable',
                            ])
                            ->required()
                            ->helperText('Updated compliance status for this finding.'),
                    ])
                    ->action(function ($record, array $data): void {
                        $service = app(RegulatoryComplianceService::class);
                        $service->reviewFinding($record, $data['status'], auth()->user());

                        Notification::make()
                            ->title('Finding reviewed')
                            ->body("Status updated to: {$data['status']}")
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('runComplianceCheck')
                    ->label('Run Compliance Check')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['system_admin', 'legal']))
                    ->form([
                        Forms\Components\Select::make('framework_id')
                            ->label('Regulatory Framework')
                            ->options(
                                \App\Models\RegulatoryFramework::where('is_active', true)
                                    ->pluck('framework_name', 'id')
                            )
                            ->placeholder('Auto-detect based on jurisdiction')
                            ->helperText('Leave empty to automatically select frameworks based on the contract\'s region/jurisdiction.')
                            ->searchable(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Run Compliance Check')
                    ->modalDescription('This will send the contract text to the AI worker for compliance analysis. Results will appear here once processing is complete.')
                    ->action(function (array $data): void {
                        $contract = $this->getOwnerRecord();
                        $framework = null;

                        if (! empty($data['framework_id'])) {
                            $framework = \App\Models\RegulatoryFramework::find($data['framework_id']);
                        }

                        $service = app(RegulatoryComplianceService::class);
                        $service->runComplianceCheck($contract, $framework);

                        Notification::make()
                            ->title('Compliance check dispatched')
                            ->body('The AI worker is analysing this contract. Findings will appear shortly.')
                            ->info()
                            ->send();
                    }),
            ]);
    }
}
