<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ObligationsRelationManager extends RelationManager
{
    protected static string $relationship = 'obligations';
    protected static ?string $title = 'Obligations';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('obligation_type')->options([
                'reporting' => 'Reporting', 'sla' => 'SLA', 'insurance' => 'Insurance',
                'deliverable' => 'Deliverable', 'payment' => 'Payment', 'other' => 'Other',
            ])->required()
                ->helperText('Category of obligation (financial, reporting, delivery, etc.).'),
            Forms\Components\Textarea::make('description')->required()
                ->helperText('Details of what this obligation requires.'),
            Forms\Components\DatePicker::make('due_date')
                ->helperText('Date by which this obligation must be fulfilled.'),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'completed' => 'Completed', 'waived' => 'Waived', 'overdue' => 'Overdue'])->default('active')
                ->helperText('Current completion status of this obligation.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('obligation_type'),
            Tables\Columns\TextColumn::make('description')->limit(40),
            Tables\Columns\TextColumn::make('due_date')->date(),
            Tables\Columns\TextColumn::make('status'),
        ])->headerActions([Tables\Actions\CreateAction::make()]);
    }
}
