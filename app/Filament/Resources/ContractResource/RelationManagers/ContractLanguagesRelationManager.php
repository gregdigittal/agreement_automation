<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Models\ContractLanguage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Helpers\StorageHelper;

class ContractLanguagesRelationManager extends RelationManager
{
    protected static string $relationship = 'languages';
    protected static ?string $title = 'Language Versions';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('language_code')
                ->label('Language')
                ->options([
                    'en' => 'English', 'fr' => 'French', 'ar' => 'Arabic',
                    'es' => 'Spanish', 'pt' => 'Portuguese', 'zh' => 'Chinese',
                    'de' => 'German', 'it' => 'Italian', 'ru' => 'Russian', 'ja' => 'Japanese',
                ])
                ->searchable()->required()
                ->helperText('ISO language code for this translation.'),
            Forms\Components\Toggle::make('is_primary')
                ->label('Primary Language Version')
                ->default(false)
                ->helperText('Only one version can be primary.'),
            Forms\Components\FileUpload::make('file')
                ->label('Contract File (PDF / DOCX)')
                ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                ->disk(config('ccrs.contracts_disk'))->directory('contract_languages')
                ->required()->live()
                ->helperText('Upload the translated contract document (PDF or DOCX).')
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) $set('file_name', basename($state));
                }),
            Forms\Components\Hidden::make('file_name'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('language_code')->label('Language')->formatStateUsing(fn ($state) => strtoupper($state))->badge(),
                Tables\Columns\IconColumn::make('is_primary')->label('Primary')->boolean(),
                Tables\Columns\TextColumn::make('file_name')->label('File')->limit(40),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Add Language Version')
                    ->mutateFormDataUsing(function (array $data) {
                        $data['storage_path'] = $data['file'];
                        $data['file_name'] = $data['file_name'] ?: basename($data['file']);
                        unset($data['file']);
                        return $data;
                    })
                    ->before(function (array $data, $livewire) {
                        if (!empty($data['is_primary'])) {
                            \App\Models\ContractLanguage::where('contract_id', $livewire->ownerRecord->id)->update(['is_primary' => false]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ContractLanguage $record) => StorageHelper::temporaryUrl($record->storage_path, 'preview'))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('set_primary')->label('Set Primary')->icon('heroicon-o-star')->color('warning')
                    ->visible(fn (ContractLanguage $record) => !$record->is_primary)
                    ->action(function (ContractLanguage $record) {
                        \App\Models\ContractLanguage::where('contract_id', $record->contract_id)->update(['is_primary' => false]);
                        $record->update(['is_primary' => true]);
                        \Filament\Notifications\Notification::make()->title('Primary language updated')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
