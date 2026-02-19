<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkflowTemplateResource\Pages;
use App\Models\WorkflowTemplate;
use Filament\Forms;
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
            Forms\Components\Repeater::make('stages')
                ->schema([
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('order')->numeric()->required(),
                    Forms\Components\Select::make('type')->options([
                        'review' => 'Review',
                        'approval' => 'Approval',
                        'signing' => 'Signing',
                    ])->required(),
                    Forms\Components\TextInput::make('approver_role')->maxLength(255),
                ])
                ->columns(4)
                ->collapsible()
                ->orderColumn('order'),
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
        ])->actions([Tables\Actions\EditAction::make()]);
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
