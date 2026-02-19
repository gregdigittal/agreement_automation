<?php
namespace App\Exports;

use App\Models\AuditLog;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;

class AuditLogExport implements FromQuery, WithHeadings
{
    use Exportable;

    public function query() { return AuditLog::query()->orderByDesc('at'); }

    public function headings(): array
    {
        return ['ID', 'Timestamp', 'Actor ID', 'Actor Email', 'Action', 'Resource Type', 'Resource ID', 'Details', 'IP Address'];
    }
}
