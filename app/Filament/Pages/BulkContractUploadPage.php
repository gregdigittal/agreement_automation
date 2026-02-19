<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessContractBatch;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class BulkContractUploadPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?string $title = 'Bulk Upload';
    protected static string $view = 'filament.pages.bulk-contract-upload';

    public ?string $csvPath = null;
    public ?string $zipPath = null;
    public array $uploadResults = [];
    public bool $processing = false;
    public string $batchId = '';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('csvPath')
                ->label('CSV Manifest')
                ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                ->required()
                ->disk('local')
                ->directory('bulk-uploads'),
            Forms\Components\FileUpload::make('zipPath')
                ->label('ZIP Archive (optional)')
                ->acceptedFileTypes(['application/zip'])
                ->disk('local')
                ->directory('bulk-uploads'),
        ]);
    }

    public function upload(): void
    {
        $data = $this->form->getState();
        $this->batchId = uniqid('bulk_', true);
        $this->processing = true;
        $this->uploadResults = [];

        $csvFullPath = Storage::disk('local')->path($data['csvPath']);
        $csv = Reader::createFromPath($csvFullPath, 'r');
        $csv->setHeaderOffset(0);

        $zipFullPath = $data['zipPath'] ? Storage::disk('local')->path($data['zipPath']) : null;

        $rowIndex = 0;
        foreach ($csv->getRecords() as $record) {
            $this->uploadResults[] = [
                'row' => $rowIndex,
                'title' => $record['title'] ?? 'Row ' . $rowIndex,
                'status' => 'queued',
            ];

            ProcessContractBatch::dispatch(
                $this->batchId,
                $rowIndex,
                $record,
                $zipFullPath
            );

            $rowIndex++;
        }

        Notification::make()->title("{$rowIndex} contracts queued for processing")->success()->send();
    }

    public function pollStatus(): void
    {
        $cache = cache()->get("bulk_upload:{$this->batchId}", []);
        foreach ($this->uploadResults as $i => &$result) {
            if (isset($cache[$result['row']])) {
                $result['status'] = $cache[$result['row']];
            }
        }
        if (collect($this->uploadResults)->every(fn ($r) => in_array($r['status'], ['completed', 'failed']))) {
            $this->processing = false;
        }
    }
}
