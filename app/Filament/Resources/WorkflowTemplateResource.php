<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkflowTemplateResource\Pages;
use App\Filament\Resources\WorkflowTemplateResource\RelationManagers\EscalationRulesRelationManager;
use App\Models\WorkflowTemplate;
use Filament\Forms;
use App\Forms\Components\WorkflowBuilderField;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WorkflowTemplateResource extends Resource
{
    protected static ?string $model = WorkflowTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationGroup = 'Workflows';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Select::make('contract_type')->options(['Commercial' => 'Commercial', 'Merchant' => 'Merchant'])->required(),
            Forms\Components\Select::make('region_id')->relationship('region', 'name')->searchable(),
            Forms\Components\Select::make('entity_id')->relationship('entity', 'name')->searchable(),
            Forms\Components\Select::make('project_id')->relationship('project', 'name')->searchable(),
            WorkflowBuilderField::make('stages')->columnSpanFull(),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived'])->default('draft'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('contract_type')->sortable(),
            Tables\Columns\BadgeColumn::make('status')->colors(['success' => 'active', 'gray' => 'draft', 'secondary' => 'archived']),
            Tables\Columns\TextColumn::make('version')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => auth()->user()?->hasRole('system_admin') ?? false)
                ->action(function (WorkflowTemplate $record): void {
                    $stages = $record->stages ?? [];
                    if (empty($stages) || !is_array($stages)) {
                        \Filament\Notifications\Notification::make()->title('Cannot publish: stages empty')->danger()->send();
                        return;
                    }
                    $record->update(['status' => 'published', 'version' => ($record->version ?? 0) + 1, 'published_at' => now()]);
                    \Filament\Notifications\Notification::make()->title('Template published')->success()->send();
                }),
            Tables\Actions\Action::make('generate_ai')
                ->label('Generate with AI')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->form([
                    Forms\Components\Textarea::make('description')->label('Describe the workflow')->required()->rows(3),
                ])
                ->action(function (array $data, $livewire) {
                    try {
                        $client = app(\App\Services\AiWorkerClient::class);
                        $result = $client->generateWorkflow($data['description']);
                        $livewire->data['stages'] = $result['stages'] ?? [];
                        \Filament\Notifications\Notification::make()->title('Workflow generated')->success()->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->title('Generation failed')->body($e->getMessage())->danger()->send();
                    }
                }),
        ]);
    }

    public static function getRelationManagers(): array
    {
        return [EscalationRulesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflowTemplates::route('/'),
            'create' => Pages\CreateWorkflowTemplate::route('/create'),
            'edit' => Pages\EditWorkflowTemplate::route('/{record}/edit'),
        ];
    }
}
