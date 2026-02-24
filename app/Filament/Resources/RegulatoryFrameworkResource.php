<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegulatoryFrameworkResource\Pages;
use App\Models\RegulatoryFramework;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RegulatoryFrameworkResource extends Resource
{
    protected static ?string $model = RegulatoryFramework::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationGroup = 'Compliance';
    protected static ?int $navigationSort = 36;

    public static function shouldRegisterNavigation(): bool
    {
        return config('features.regulatory_compliance', false);
    }

    public static function canViewAny(): bool
    {
        if (! config('features.regulatory_compliance', false)) {
            return false;
        }

        $user = auth()->user();

        return $user && $user->hasAnyRole(['system_admin', 'legal']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Framework Details')
                ->schema([
                    Forms\Components\Select::make('jurisdiction_code')
                        ->label('Jurisdiction')
                        ->options([
                            'EU' => 'European Union',
                            'US' => 'United States',
                            'GB' => 'United Kingdom',
                            'AE' => 'United Arab Emirates',
                            'SA' => 'Saudi Arabia',
                            'SG' => 'Singapore',
                            'HK' => 'Hong Kong',
                            'JP' => 'Japan',
                            'AU' => 'Australia',
                            'CA' => 'Canada',
                            'IN' => 'India',
                            'GLOBAL' => 'Global / Multi-jurisdictional',
                        ])
                        ->required()
                        ->searchable()
                        ->helperText('ISO 3166-1 alpha-2 code or GLOBAL for multi-jurisdictional frameworks.'),

                    Forms\Components\TextInput::make('framework_name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. GDPR — Data Processing Requirements'),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->placeholder('Brief description of what this framework covers...'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Inactive frameworks will not appear in compliance check options.'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Requirements')
                ->description('Define individual requirements that contracts will be checked against.')
                ->schema([
                    Forms\Components\Repeater::make('requirements')
                        ->schema([
                            Forms\Components\TextInput::make('id')
                                ->label('Requirement ID')
                                ->required()
                                ->placeholder('e.g. gdpr-1')
                                ->helperText('Unique identifier within this framework.')
                                ->maxLength(100),

                            Forms\Components\Textarea::make('text')
                                ->label('Requirement Text')
                                ->required()
                                ->rows(2)
                                ->placeholder('Describe the specific requirement the contract must address...'),

                            Forms\Components\Select::make('category')
                                ->options([
                                    'data_protection' => 'Data Protection',
                                    'financial' => 'Financial',
                                    'employment' => 'Employment',
                                    'intellectual_property' => 'Intellectual Property',
                                    'dispute_resolution' => 'Dispute Resolution',
                                    'liability' => 'Liability & Indemnification',
                                    'confidentiality' => 'Confidentiality',
                                    'termination' => 'Termination',
                                    'other' => 'Other',
                                ])
                                ->required(),

                            Forms\Components\Select::make('severity')
                                ->options([
                                    'critical' => 'Critical',
                                    'high' => 'High',
                                    'medium' => 'Medium',
                                    'low' => 'Low',
                                ])
                                ->required()
                                ->default('medium'),
                        ])
                        ->columns(2)
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => ($state['id'] ?? '') . ' — ' . ($state['text'] ?? ''))
                        ->defaultItems(0)
                        ->addActionLabel('Add Requirement'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('jurisdiction_code')
                    ->label('Jurisdiction')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('framework_name')
                    ->label('Framework')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('requirement_count')
                    ->label('Requirements')
                    ->getStateUsing(fn (RegulatoryFramework $record): int => $record->requirement_count)
                    ->sortable(false),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('findings_count')
                    ->label('Findings')
                    ->counts('findings')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('framework_name')
            ->filters([
                Tables\Filters\SelectFilter::make('jurisdiction_code')
                    ->label('Jurisdiction')
                    ->options(fn () => RegulatoryFramework::query()
                        ->distinct()
                        ->pluck('jurisdiction_code', 'jurisdiction_code')
                        ->toArray()
                    ),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegulatoryFrameworks::route('/'),
            'create' => Pages\CreateRegulatoryFramework::route('/create'),
            'edit' => Pages\EditRegulatoryFramework::route('/{record}/edit'),
        ];
    }
}
