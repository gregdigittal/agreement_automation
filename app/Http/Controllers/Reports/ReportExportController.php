<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ContractExport;
use App\Exports\ObligationsExport;
use App\Http\Controllers\Controller;
use App\Models\ComplianceFinding;
use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
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

    /** Full analytics dashboard as a PDF snapshot. */
    public function analyticsPdf(Request $request)
    {
        $this->authorizeRole($request);

        if (! config('features.advanced_analytics', false)) {
            abort(404);
        }

        $pipeline = Contract::select('workflow_state', DB::raw('COUNT(*) as count'))
            ->groupBy('workflow_state')
            ->pluck('count', 'workflow_state')
            ->toArray();

        $riskDistribution = DB::table('contracts')
            ->join('regions', 'contracts.region_id', '=', 'regions.id')
            ->leftJoin('ai_analysis_results', function ($join) {
                $join->on('ai_analysis_results.contract_id', '=', 'contracts.id')
                    ->where('ai_analysis_results.analysis_type', '=', 'risk');
            })
            ->select(
                'regions.name as region_name',
                DB::raw("COALESCE(ai_analysis_results.result->>'$.risk_level', 'unscored') as risk_level"),
                DB::raw('COUNT(*) as count')
            )
            ->whereNotIn('contracts.workflow_state', ['cancelled'])
            ->groupBy('regions.name', 'risk_level')
            ->get();

        $pdf = Pdf::loadView('reports.analytics-snapshot', [
            'pipeline' => $pipeline,
            'risk_distribution' => $riskDistribution,
            'generated_at' => now()->format('d M Y H:i'),
        ]);

        return $pdf->download('ccrs-analytics-snapshot-'.now()->format('Y-m-d').'.pdf');
    }

    /** Compliance report for a specific contract. */
    public function compliancePdf(Request $request, string $contractId)
    {
        $this->authorizeRole($request);

        if (! config('features.regulatory_compliance', false)) {
            abort(404);
        }

        $contract = Contract::with(['counterparty', 'entity', 'region'])->findOrFail($contractId);
        $findings = ComplianceFinding::where('contract_id', $contractId)
            ->with('framework')
            ->orderBy('framework_id')
            ->orderBy('requirement_id')
            ->get()
            ->groupBy('framework_id');

        $service = app(\App\Services\RegulatoryComplianceService::class);
        $scores = $service->getScoreSummary($contract);

        $pdf = Pdf::loadView('reports.compliance-report', [
            'contract' => $contract,
            'findings' => $findings,
            'scores' => $scores,
            'generated_at' => now()->format('d M Y H:i'),
        ]);

        return $pdf->download('ccrs-compliance-report-'.$contract->id.'.pdf');
    }

    /** Obligations register filtered export as Excel. */
    public function obligationsExcel(Request $request)
    {
        $this->authorizeRole($request);

        $query = DB::table('obligations_register')
            ->join('contracts', 'obligations_register.contract_id', '=', 'contracts.id')
            ->select(
                'contracts.title as contract_title',
                'obligations_register.obligation_type',
                'obligations_register.description',
                'obligations_register.due_date',
                'obligations_register.status',
                'obligations_register.created_at'
            );

        if ($request->filled('status')) {
            $query->where('obligations_register.status', $request->input('status'));
        }
        if ($request->filled('obligation_type')) {
            $query->where('obligations_register.obligation_type', $request->input('obligation_type'));
        }
        if ($request->filled('date_from')) {
            $query->where('obligations_register.due_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('obligations_register.due_date', '<=', $request->input('date_to'));
        }

        $data = $query->orderBy('obligations_register.due_date')->get();

        return Excel::download(
            new ObligationsExport($data),
            'ccrs-obligations-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    private function authorizeRole(Request $request): void
    {
        if (! auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial', 'finance', 'audit'])) {
            abort(403, 'Insufficient permissions for report export.');
        }
    }
}
