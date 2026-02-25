<?php

namespace App\Jobs;

use App\Models\BulkUploadRow;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Services\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessContractBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [10, 60];

    public function __construct(
        public readonly string $bulkUploadRowId,
    ) {}

    public function handle(): void
    {
        $row = BulkUploadRow::findOrFail($this->bulkUploadRowId);
        $row->update(['status' => 'processing']);

        try {
            $data = $row->row_data;

            $region = Region::where('code', $data['region_code'])->firstOrFail();
            $entity = Entity::where('code', $data['entity_code'])->where('region_id', $region->id)->firstOrFail();
            $project = Project::where('code', $data['project_code'])->where('entity_id', $entity->id)->firstOrFail();
            $counterparty = Counterparty::where('registration_number', $data['counterparty_registration'])->firstOrFail();

            $sourceKey = 'bulk_uploads/files/' . $data['file_path'];
            $destKey = 'contracts/' . Str::uuid() . '/' . basename($data['file_path']);
            Storage::disk('s3')->copy($sourceKey, $destKey);

            $contract = new Contract([
                'title' => $data['title'],
                'contract_type' => $data['contract_type'] ?? 'Commercial',
                'counterparty_id' => $counterparty->id,
                'region_id' => $region->id,
                'entity_id' => $entity->id,
                'project_id' => $project->id,
                'storage_path' => $destKey,
                'created_by' => $row->created_by,
            ]);
            $contract->workflow_state = 'draft';
            $contract->save();

            AuditService::log(
                'contract.bulk_created',
                'contract',
                $contract->id,
                ['bulk_upload_row_id' => $row->id],
            );

            $row->update([
                'status' => 'completed',
                'contract_id' => $contract->id,
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $row->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
