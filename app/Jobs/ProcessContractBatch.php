<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Services\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ProcessContractBatch implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $batchId,
        public int $rowIndex,
        public array $record,
        public ?string $zipPath = null,
    ) {}

    public function handle(): void
    {
        $this->updateStatus('processing');

        try {
            $contract = Contract::create([
                'region_id' => $this->record['region_id'] ?? null,
                'entity_id' => $this->record['entity_id'] ?? null,
                'project_id' => $this->record['project_id'] ?? null,
                'counterparty_id' => $this->record['counterparty_id'] ?? null,
                'contract_type' => $this->record['contract_type'] ?? 'Commercial',
                'title' => $this->record['title'] ?? 'Bulk Upload Row ' . $this->rowIndex,
                'workflow_state' => 'draft',
                'created_by' => $this->record['created_by'] ?? null,
            ]);

            if ($this->zipPath && !empty($this->record['file_name'])) {
                $this->extractAndUploadFile($contract, $this->record['file_name']);
            }

            AuditService::log('bulk_contract_created', 'contract', $contract->id, [
                'batch_id' => $this->batchId,
                'row' => $this->rowIndex,
            ]);

            $this->updateStatus('completed');
        } catch (\Exception $e) {
            $this->updateStatus('failed');
            throw $e;
        }
    }

    private function extractAndUploadFile(Contract $contract, string $fileName): void
    {
        $zip = new ZipArchive();
        if ($zip->open($this->zipPath) !== true) return;

        $index = $zip->locateName($fileName);
        if ($index === false) {
            $zip->close();
            return;
        }

        $content = $zip->getFromIndex($index);
        $zip->close();

        $s3Path = "contracts/{$contract->id}/{$fileName}";
        Storage::disk(config('ccrs.contracts_disk', 's3'))->put($s3Path, $content);

        $contract->update([
            'storage_path' => $s3Path,
            'file_name' => $fileName,
            'file_version' => 1,
        ]);
    }

    private function updateStatus(string $status): void
    {
        $key = "bulk_upload:{$this->batchId}";
        $data = Cache::get($key, []);
        $data[$this->rowIndex] = $status;
        Cache::put($key, $data, 3600);
    }
}
