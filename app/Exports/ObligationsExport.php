<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ObligationsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private Collection $data,
    ) {}

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Contract',
            'Obligation Type',
            'Description',
            'Due Date',
            'Status',
            'Created At',
        ];
    }

    public function map($row): array
    {
        return [
            $row->contract_title,
            ucwords(str_replace('_', ' ', $row->obligation_type)),
            $row->description,
            $row->due_date,
            ucfirst($row->status),
            $row->created_at,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
