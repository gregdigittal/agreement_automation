<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessContractBatch;
use App\Models\BulkUpload;
use App\Models\BulkUploadRow;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BulkContractUploadPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.bulk-contract-upload';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 90;

    public ?array $data = [];
    public ?string $currentBulkUploadId = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('csv_file')
                    ->label('CSV Manifest')
                    ->acceptedFileTypes(['text/csv', 'application/csv'])
                    ->disk('local')->required()
                    ->helperText('Columns: title, contract_type, region_code, entity_code, project_code, counterparty_registration, file_path'),

                FileUpload::make('zip_file')
                    ->label('Contract Files (ZIP)')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->disk('local')->helperText('ZIP containing the contract files referenced by file_path column'),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $bulkUploadId = Str::uuid()->toString();
        $rows = [];

        $csvPath = Storage::disk('local')->path($data['csv_file']);
        $csvHandle = fopen($csvPath, 'r');
        $headers = fgetcsv($csvHandle);
        $rowNumber = 0;
        while (($line = fgetcsv($csvHandle)) !== false) {
            $rowNumber++;
            $rowData = array_combine($headers, $line);
            $rows[] = [
                'id' => Str::uuid()->toString(),
                'bulk_upload_id' => $bulkUploadId,
                'row_number' => $rowNumber,
                'row_data' => json_encode($rowData),
                'status' => 'pending',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        fclose($csvHandle);

        if (empty($rows)) {
            Notification::make()->title('CSV is empty')->danger()->send();
            return;
        }

        if (! empty($data['zip_file'])) {
            $zipPath = Storage::disk('local')->path($data['zip_file']);
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = basename($zip->getNameIndex($i));
                    if (empty($filename) || $filename === '.' || $filename === '..') {
                        continue;
                    }
                    $contents = $zip->getFromIndex($i);
                    Storage::disk('s3')->put('bulk_uploads/files/' . $filename, $contents);
                }
                $zip->close();
            }
        }

        BulkUpload::create([
            'id' => $bulkUploadId,
            'created_by' => auth()->id(),
            'csv_filename' => basename($data['csv_file']),
            'zip_filename' => $data['zip_file'] ? basename($data['zip_file']) : null,
            'total_rows' => $rowNumber,
            'status' => 'processing',
        ]);

        BulkUploadRow::insert($rows);
        foreach ($rows as $row) {
            ProcessContractBatch::dispatch($row['id'])->onQueue('default');
        }

        $this->currentBulkUploadId = $bulkUploadId;

        Notification::make()
            ->title('Upload started')
            ->body("{$rowNumber} contracts queued for processing.")
            ->success()
            ->send();

        $this->form->fill();
    }

    public function getProgressData(): array
    {
        if (! $this->currentBulkUploadId) {
            return [];
        }

        $rows = BulkUploadRow::where('bulk_upload_id', $this->currentBulkUploadId)->get();

        return [
            'total' => $rows->count(),
            'completed' => $rows->where('status', 'completed')->count(),
            'failed' => $rows->where('status', 'failed')->count(),
            'processing' => $rows->where('status', 'processing')->count(),
            'pending' => $rows->where('status', 'pending')->count(),
            'rows' => $rows->map(fn ($r) => [
                'row_number' => $r->row_number,
                'status' => $r->status,
                'error' => $r->error,
                'contract_id' => $r->contract_id,
            ])->toArray(),
        ];
    }
}
