<?php

namespace App\Exports;

use App\Models\Contract;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ContractExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    use Exportable;

    public function __construct(
        private readonly ?string $regionId = null,
        private readonly ?string $entityId = null,
        private readonly ?string $contractType = null,
        private readonly ?string $workflowState = null,
    ) {}

    public function query()
    {
        $query = Contract::query()->with(['counterparty', 'region', 'entity', 'project'])->orderByDesc('created_at');
        if ($this->regionId) $query->where('region_id', $this->regionId);
        if ($this->entityId) $query->where('entity_id', $this->entityId);
        if ($this->contractType) $query->where('contract_type', $this->contractType);
        if ($this->workflowState) $query->where('workflow_state', $this->workflowState);
        return $query;
    }

    public function headings(): array
    {
        return ['ID', 'Title', 'Type', 'State', 'Counterparty', 'Region', 'Entity', 'Project', 'Signing Status', 'Created At'];
    }

    public function map($contract): array
    {
        return [
            $contract->id, $contract->title, $contract->contract_type, $contract->workflow_state,
            $contract->counterparty?->legal_name, $contract->region?->name, $contract->entity?->name,
            $contract->project?->name, $contract->signing_status ?? 'N/A', $contract->created_at?->format('Y-m-d H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
