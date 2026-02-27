<?php

namespace App\Filament\Resources\WorkflowTemplateResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EscalationRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'escalationRules';
    protected static ?string $title = 'Escalation Rules';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('stage_name')->required()->maxLength(255)
                ->helperText('Workflow stage this escalation rule applies to.'),
            Forms\Components\TextInput::make('sla_breach_hours')->numeric()->required()
                ->helperText('Hours after which the SLA is considered breached.'),
            Forms\Components\TextInput::make('tier')->maxLength(50)
                ->helperText('Escalation level (1 = first, 2 = second, etc.).'),
            Forms\Components\Select::make('escalate_to_role')
                ->options([
                    'system_admin' => 'System Admin', 'legal' => 'Legal', 'commercial' => 'Commercial',
                    'finance' => 'Finance', 'operations' => 'Operations',
                ])
                ->helperText('Role that receives the escalation notification.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('stage_name'),
            Tables\Columns\TextColumn::make('sla_breach_hours'),
            Tables\Columns\TextColumn::make('tier'),
            Tables\Columns\TextColumn::make('escalate_to_role'),
        ])->headerActions([Tables\Actions\CreateAction::make()]);
    }
}
