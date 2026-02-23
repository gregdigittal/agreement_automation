<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ContractExport;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /** Export contracts as XLSX */
    public function contractsExcel(Request $request)
    {
        $this->authorizeRole($request);
        $export = new ContractExport(
            regionId:     $request->query('region_id'),
            entityId:     $request->query('entity_id'),
            contractType: $request->query('contract_type'),
            workflowState:$request->query('workflow_state'),
        );
        return Excel::download($export, 'contracts_' . now()->format('Ymd_His') . '.xlsx');
    }

    /** Export contracts summary as PDF */
    public function contractsPdf(Request $request)
    {
        $this->authorizeRole($request);
        $contracts = Contract::query()
            ->with(['counterparty', 'region', 'entity'])
            ->when($request->query('region_id'),      fn ($q, $v) => $q->where('region_id', $v))
            ->when($request->query('contract_type'),  fn ($q, $v) => $q->where('contract_type', $v))
            ->when($request->query('workflow_state'), fn ($q, $v) => $q->where('workflow_state', $v))
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        $pdf = Pdf::loadView('reports.contracts-pdf', [
            'contracts'   => $contracts,
            'generatedAt' => now()->format('d M Y H:i'),
            'filters'     => $request->only(['region_id', 'contract_type', 'workflow_state']),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('contracts_report_' . now()->format('Ymd') . '.pdf');
    }

    private function authorizeRole(Request $request): void
    {
        if (! auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial', 'finance', 'audit'])) {
            abort(403, 'Insufficient permissions for report export.');
        }
    }
}
