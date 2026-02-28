<?php

namespace App\Filament\Pages;

use App\Services\BulkDataImportService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkDataUploadPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static string $view = 'filament.pages.bulk-data-upload';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 91;
    protected static ?string $title = 'Bulk Data Upload';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public ?array $data = [];
    public ?array $results = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('upload_type')
                    ->label('Data Type')
                    ->options([
                        'regions' => 'Regions',
                        'entities' => 'Entities',
                        'projects' => 'Projects',
                        'users' => 'Users',
                        'counterparties' => 'Counterparties',
                    ])
                    ->required()
                    ->live()
                    ->helperText('Select the type of data you want to import.'),

                Placeholder::make('required_columns')
                    ->label('Required CSV Columns')
                    ->visible(fn (Get $get): bool => filled($get('upload_type')))
                    ->content(function (Get $get): HtmlString {
                        $type = $get('upload_type');
                        $headers = BulkDataImportService::HEADERS[$type] ?? [];
                        $required = BulkDataImportService::REQUIRED[$type] ?? [];
                        $cols = array_map(function ($h) use ($required) {
                            $label = in_array($h, $required) ? "<strong>{$h}</strong>" : $h;
                            return $label;
                        }, $headers);
                        return new HtmlString('Columns: ' . implode(', ', $cols) . '<br><small>Bold = required</small>');
                    }),

                FileUpload::make('csv_file')
                    ->label('CSV File')
                    ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain'])
                    ->disk('local')
                    ->required()
                    ->helperText('Upload a CSV file with the columns shown above.'),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $type = $data['upload_type'];
        $csvPath = Storage::disk('local')->path($data['csv_file']);

        $service = app(BulkDataImportService::class);
        $this->results = $service->import($type, $csvPath);

        // Clean up temp file
        Storage::disk('local')->delete($data['csv_file']);

        if ($this->results['failed'] === 0 && empty($this->results['errors'])) {
            Notification::make()
                ->title('Import completed successfully')
                ->body("{$this->results['success']} records imported.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Import completed with issues')
                ->body("{$this->results['success']} succeeded, {$this->results['failed']} failed.")
                ->warning()
                ->send();
        }

        $this->form->fill();
    }

    public function downloadTemplate(?string $type = null): StreamedResponse
    {
        $type = $type ?? $this->data['upload_type'] ?? 'regions';
        $service = app(BulkDataImportService::class);
        $csv = $service->generateTemplate($type);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, "{$type}_template.csv", ['Content-Type' => 'text/csv']);
    }
}
